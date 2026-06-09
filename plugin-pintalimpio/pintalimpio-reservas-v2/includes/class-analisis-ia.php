<?php
/**
 * Análisis de Imágenes con IA — Pintalimpio Reservas
 *
 * Envía las fotos de cada estancia a Claude (Anthropic API)
 * y devuelve el grado de suciedad detectado.
 *
 * Grados: normal (+10%) | avanzada (+20%) | muy_sucia (+40%)
 *
 * @author  Jhon Bastidas Rodrigues
 * @link    https://controlhorariowp.com/
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PLR_Analisis_IA {

    /**
     * Multiplicadores de precio por grado de suciedad.
     * Se aplican SOBRE el precio base de cada estancia.
     */
    public static function get_multiplicadores() {
        return [
            'normal'    => 0.10, // +10% (estándar)
            'avanzada'  => 0.20, // +20% (media)
            'muy_sucia' => 0.40, // +40% (extrema)
        ];
    }

    /**
     * Analiza un conjunto de imágenes de una estancia
     * y devuelve el grado de suciedad.
     *
     * @param array  $attachment_ids  IDs de WordPress de las imágenes subidas.
     * @param string $estancia_label  Nombre de la estancia (para el prompt).
     * @return string|WP_Error  'normal'|'avanzada'|'muy_sucia' o WP_Error.
     */
    public static function analizar_estancia( array $attachment_ids, string $estancia_label ) {
        $api_key = get_option( 'plr_anthropic_api_key', '' );

        if ( empty( $api_key ) ) {
            // Sin API key: asumimos suciedad normal (no bloqueamos el proceso)
            return 'normal';
        }

        if ( empty( $attachment_ids ) ) {
            return 'normal';
        }

        // Construir el array de imágenes en base64 para Claude
        $imagenes_content = [];
        foreach ( $attachment_ids as $att_id ) {
            $att_id   = absint( $att_id );
            $ruta     = get_attached_file( $att_id );
            $mime     = get_post_mime_type( $att_id );

            if ( ! $ruta || ! file_exists( $ruta ) ) continue;

            // Solo imágenes soportadas por Claude
            if ( ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ], true ) ) continue;

            $base64 = base64_encode( file_get_contents( $ruta ) );
            if ( ! $base64 ) continue;

            $imagenes_content[] = [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => $mime,
                    'data'       => $base64,
                ],
            ];
        }

        if ( empty( $imagenes_content ) ) return 'normal';

        // Añadir el prompt de texto al final del array de contenido
        $imagenes_content[] = [
            'type' => 'text',
            'text' => self::get_prompt( $estancia_label ),
        ];

        $payload = [
            'model'      => 'claude-opus-4-5',
            'max_tokens' => 50,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => $imagenes_content,
                ],
            ],
        ];

        $resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 30,
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode( $payload ),
        ] );

        if ( is_wp_error( $resp ) ) return $resp;

        $code = (int) wp_remote_retrieve_response_code( $resp );
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? 'Error desconocido de la API.';
            return new WP_Error( 'ia_error', $msg );
        }

        $texto = trim( strtolower( $body['content'][0]['text'] ?? '' ) );

        return self::parsear_grado( $texto );
    }

    /**
     * Prompt optimizado para obtener solo una de las tres categorías.
     */
    private static function get_prompt( string $estancia ) {
        return "Eres un experto en servicios de limpieza profesional. Analiza las fotos de '{$estancia}' y determina el grado de suciedad.

Responde ÚNICAMENTE con una de estas tres palabras exactas, sin explicación:
- normal (suciedad cotidiana, polvo, manchas leves)
- avanzada (suciedad acumulada, manchas difíciles, grasa, hongos incipientes)
- muy_sucia (suciedad extrema, acumulación severa, posible síndrome de Diógenes, hongos, plagas)

Respuesta:";
    }

    /**
     * Extrae el grado del texto devuelto por Claude.
     * Si no reconoce la respuesta, devuelve 'normal' como fallback seguro.
     */
    private static function parsear_grado( string $texto ) {
        if ( str_contains( $texto, 'muy_sucia' ) || str_contains( $texto, 'muy sucia' ) ) return 'muy_sucia';
        if ( str_contains( $texto, 'avanzada' ) )  return 'avanzada';
        if ( str_contains( $texto, 'normal' ) )    return 'normal';
        // Fallback: normal (beneficio de la duda al cliente)
        return 'normal';
    }

    /**
     * Devuelve el multiplicador de precio para un grado dado.
     *
     * @param string $grado
     * @return float  Ej: 0.10 para normal
     */
    public static function get_multiplicador( string $grado ) {
        $mults = self::get_multiplicadores();
        return (float) ( $mults[ $grado ] ?? $mults['normal'] );
    }

    /**
     * Sube una imagen al Media Library de WordPress vinculada a la reserva.
     * Devuelve el attachment_id o WP_Error.
     *
     * @param array  $file        Elemento de $_FILES['archivo'].
     * @param int    $reserva_id  ID de la reserva (para vinculación).
     * @param string $estancia_id ID de la estancia (para el título).
     * @return int|WP_Error
     */
    public static function subir_imagen( array $file, int $reserva_id, string $estancia_id ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Validar tipo MIME antes de subir
        $tipos_permitidos = [ 'image/jpeg', 'image/png', 'image/webp' ];
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime  = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );

        if ( ! in_array( $mime, $tipos_permitidos, true ) ) {
            return new WP_Error( 'tipo_invalido', 'Solo se permiten imágenes JPG, PNG o WebP.' );
        }

        // Límite de 8MB por imagen
        if ( $file['size'] > 8 * 1024 * 1024 ) {
            return new WP_Error( 'archivo_grande', 'La imagen no puede superar 8MB.' );
        }

        // Título descriptivo para la galería
        $titulo = sprintf(
            'Reserva #%d — %s — %s',
            $reserva_id,
            ucfirst( sanitize_text_field( $estancia_id ) ),
            current_time( 'd/m/Y H:i' )
        );

        $att_id = media_handle_sideload(
            [
                'name'     => sanitize_file_name( $file['name'] ),
                'type'     => $mime,
                'tmp_name' => $file['tmp_name'],
                'size'     => $file['size'],
                'error'    => $file['error'],
            ],
            0, // Sin post padre (lo vinculamos via meta)
            $titulo
        );

        if ( is_wp_error( $att_id ) ) return $att_id;

        // Guardar metadatos para vincular con la reserva
        update_post_meta( $att_id, '_plr_reserva_id',  $reserva_id );
        update_post_meta( $att_id, '_plr_estancia_id', sanitize_text_field( $estancia_id ) );
        update_post_meta( $att_id, '_plr_tipo_foto',   'antes' ); // 'antes' | 'despues'

        return (int) $att_id;
    }

    /**
     * Obtiene las fotos de una reserva agrupadas por estancia.
     *
     * @param int    $reserva_id
     * @param string $tipo  'antes' | 'despues' | '' (todas)
     * @return array  [ 'estancia_id' => [ attachment_ids ] ]
     */
    public static function get_fotos_reserva( int $reserva_id, string $tipo = '' ) {
        $meta_query = [
            [
                'key'   => '_plr_reserva_id',
                'value' => $reserva_id,
                'type'  => 'NUMERIC',
            ],
        ];

        if ( ! empty( $tipo ) ) {
            $meta_query[] = [
                'key'   => '_plr_tipo_foto',
                'value' => sanitize_text_field( $tipo ),
            ];
        }

        $attachments = get_posts( [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'meta_query'     => $meta_query,
        ] );

        $resultado = [];
        foreach ( $attachments as $att ) {
            $estancia = get_post_meta( $att->ID, '_plr_estancia_id', true );
            $resultado[ $estancia ][] = $att->ID;
        }

        return $resultado;
    }
}
