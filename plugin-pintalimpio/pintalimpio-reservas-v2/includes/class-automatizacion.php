<?php
/**
 * Automatización de Emails — Pintalimpio Reservas
 *
 * Escucha el hook plr_estado_cambiado y dispara el email correcto
 * según la transición de estado.
 *
 * @author  Jhon Bastidas Rodrigues
 * @link    https://controlhorariowp.com/
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PLR_Automatizacion {

    /**
     * Formatea una fecha de BD (YYYY-MM-DD) a formato español (DD/MM/YYYY).
     * Más robusto que strtotime para fechas MySQL.
     */
    private static function formatear_fecha( string $fecha ) {
        $fecha = trim( $fecha );
        if ( empty($fecha) || $fecha === '0000-00-00' ) return '—';
        $dt = DateTime::createFromFormat( 'Y-m-d', $fecha );
        if ( $dt && (int)$dt->format('Y') > 2000 ) return $dt->format( 'd/m/Y' );
        $ts = strtotime( $fecha );
        if ( $ts && $ts > 0 && (int)date('Y',$ts) > 2000 ) return date( 'd/m/Y', $ts );
        return $fecha;
    }

    /**
     * Formatea una hora de BD (HH:MM:SS o HH:MM) a HH:MM.
     */
    private static function formatear_hora( string $hora ) {
        return substr( trim( $hora ), 0, 5 );
    }

    public static function init() {
        add_action( 'plr_estado_cambiado',                   [ __CLASS__, 'on_estado_cambiado' ],              10, 4 );
        add_action( 'plr_pago_fallido',                      [ __CLASS__, 'on_pago_fallido' ],                 10, 1 );
        add_action( 'plr_enviar_instrucciones_transferencia', [ __CLASS__, 'email_instrucciones_transferencia'], 10, 3 );
    }

    // ── Dispatcher principal ──────────────────────────────────
    public static function on_estado_cambiado( int $reserva_id, string $nuevo, string $anterior, object $reserva ) {
        switch ( $nuevo ) {

            case PLR_DB_Reservas::ESTADO_RESERVADO:
                // Depósito cobrado → confirmar reserva al cliente
                self::email_confirmacion_reserva( $reserva_id );
                break;

            case PLR_DB_Reservas::ESTADO_FINALIZADO:
                // Admin marcó trabajo finalizado → enviar enlace de pago final
                self::email_pago_final( $reserva_id );
                break;

            case PLR_DB_Reservas::ESTADO_PAGADO:
                // Pago final recibido → pedir reseña en Google
                self::email_solicitud_resena( $reserva_id );
                break;

            case PLR_DB_Reservas::ESTADO_CANCELADO:
                self::email_cancelacion( $reserva_id );
                break;
        }
    }

    // ── Email 1: Confirmación de reserva ──────────────────────
    public static function email_confirmacion_reserva( int $reserva_id ) {
        $reserva = PLR_DB_Reservas::get_reserva( $reserva_id );
        if ( ! $reserva ) return;

        $asunto = sprintf( '✅ Tu reserva de limpieza está confirmada — %s', $reserva->fecha_servicio );
        $cuerpo = self::cargar_template( 'confirmacion-reserva', [
            'nombre'         => $reserva->nombre,
            'fecha_servicio' => self::formatear_fecha( $reserva->fecha_servicio ),
            'hora_servicio'  => self::formatear_hora( $reserva->hora_servicio ),
            'deposito'       => plr_formato_precio( (float)$reserva->deposito ),
            'resto'          => plr_formato_precio( (float)$reserva->total - (float)$reserva->deposito ),
            'total'          => plr_formato_precio( (float)$reserva->total ),
        ] );

        self::enviar( $reserva->email, $asunto, $cuerpo );

        // Si el cliente pidió factura, notificar al admin con los datos fiscales
        $facturacion = is_array( $reserva->facturacion_json ) ? $reserva->facturacion_json : [];
        if ( ! empty( $facturacion['nif'] ) ) {
            self::email_datos_factura( (int) $reserva->id, $facturacion, $reserva );
        }

        // Notificar también al admin
        self::enviar(
            get_option( 'plr_email_admin' ),
            sprintf( '📋 Nueva reserva confirmada — %s (%s)', $reserva->nombre, $reserva->fecha_servicio ),
            self::cargar_template( 'admin-nueva-reserva', [
                'nombre'         => $reserva->nombre,
                'email'          => $reserva->email,
                'telefono'       => $reserva->telefono,
                'fecha_servicio' => $reserva->fecha_servicio,
                'hora_servicio'  => self::formatear_hora( $reserva->hora_servicio ),
                'observaciones'  => $reserva->observaciones ?? '',
                'total'          => plr_formato_precio( (float)$reserva->total ),
                'deposito'       => plr_formato_precio( (float)$reserva->deposito ),
                'admin_url'      => admin_url( 'admin.php?page=plr-reservas' ),
            ] )
        );
    }

    // ── Email 2: Pago final (cuando admin marca Finalizado) ───
    public static function email_pago_final( int $reserva_id ) {
        $reserva = PLR_DB_Reservas::get_reserva( $reserva_id );
        if ( ! $reserva ) {
            error_log( "[PLR] email_pago_final: reserva {$reserva_id} no encontrada." );
            return;
        }

        $resto = round( (float)$reserva->total - (float)$reserva->deposito, 2 );

        // Intentar crear Stripe Checkout Session para el pago restante
        $session      = PLR_API_Stripe::crear_checkout_session( $reserva );
        $checkout_url = '';

        if ( is_wp_error( $session ) ) {
            // Si Stripe falla (ej: modo test sin clave), enviamos el email igualmente
            // con un enlace de contacto en lugar del botón de pago
            error_log( '[PLR] email_pago_final: Error Checkout Session — ' . $session->get_error_message() );
            $checkout_url = home_url( '/' ); // Fallback: página principal
        } else {
            $checkout_url = $session['checkout_url'];
            // Guardar stripe_cs_id directamente en BD sin disparar el hook de nuevo
            global $wpdb;
            $wpdb->update(
                PLR_TABLE_RESERVAS,
                [ 'stripe_cs_id' => sanitize_text_field( $session['session_id'] ) ],
                [ 'id' => $reserva_id ],
                [ '%s' ],
                [ '%d' ]
            );
        }

        $asunto = '🧹 Tu servicio ha finalizado — Completa el pago';
        $cuerpo = self::cargar_template( 'pago-final', [
            'nombre'         => $reserva->nombre,
            'fecha_servicio' => self::formatear_fecha( $reserva->fecha_servicio ),
            'resto'          => plr_formato_precio( $resto ),
            'checkout_url'   => $checkout_url,
        ] );

        $enviado = self::enviar( $reserva->email, $asunto, $cuerpo );
        error_log( "[PLR] email_pago_final: email enviado a {$reserva->email} — resultado: " . ( $enviado ? 'OK' : 'FALLO' ) );
    }

    // ── Email 3: Solicitud de reseña ──────────────────────────
    public static function email_solicitud_resena( int $reserva_id ) {
        $reserva     = PLR_DB_Reservas::get_reserva( $reserva_id );
        $review_url  = get_option( 'plr_google_review_url', '' );
        if ( ! $reserva || empty( $review_url ) ) return;

        $asunto = '⭐ ¿Cómo fue tu experiencia? Cuéntanoslo en Google';
        $cuerpo = self::cargar_template( 'solicitud-resena', [
            'nombre'     => $reserva->nombre,
            'review_url' => esc_url( $review_url ),
        ] );

        self::enviar( $reserva->email, $asunto, $cuerpo );
    }

    // ── Email 4: Cancelación ──────────────────────────────────
    public static function email_cancelacion( int $reserva_id ) {
        $reserva = PLR_DB_Reservas::get_reserva( $reserva_id );
        if ( ! $reserva ) return;

        $asunto = 'Tu reserva ha sido cancelada';
        $cuerpo = self::cargar_template( 'cancelacion', [
            'nombre'         => $reserva->nombre,
            'fecha_servicio' => self::formatear_fecha( $reserva->fecha_servicio ),
        ] );

        self::enviar( $reserva->email, $asunto, $cuerpo );
    }

    // ── Email: Instrucciones de transferencia ────────────────
    public static function email_instrucciones_transferencia( int $reserva_id, float $deposito, array $datos = [] ) {
        // Usar datos directos si están disponibles, si no leer de la BD
        if ( ! empty( $datos ) ) {
            $nombre         = sanitize_text_field( $datos['nombre'] ?? '' );
            $email          = sanitize_email( $datos['email'] ?? '' );
            $fecha_servicio = sanitize_text_field( $datos['fecha_servicio'] ?? '' );
            $hora_servicio  = sanitize_text_field( $datos['hora_servicio'] ?? '' );
        } else {
            $reserva = PLR_DB_Reservas::get_reserva( $reserva_id );
            if ( ! $reserva ) return;
            $nombre         = $reserva->nombre;
            $email          = $reserva->email;
            $fecha_servicio = $reserva->fecha_servicio;
            $hora_servicio  = $reserva->hora_servicio;
        }

        $horas  = (int) get_option('plr_transferencia_horas', 24);
        $asunto = '🏦 Instrucciones para completar tu reserva — Transferencia bancaria';
        $cuerpo = self::cargar_template('transferencia-instrucciones', [
            'nombre'   => $nombre,
            'deposito' => plr_formato_precio($deposito),
            'fecha'    => self::formatear_fecha( $fecha_servicio ),
            'hora'     => substr( $hora_servicio, 0, 5 ),
            'titular'  => get_option('plr_banco_titular', ''),
            'iban'     => get_option('plr_banco_iban', ''),
            'entidad'  => get_option('plr_banco_entidad', ''),
            'concepto' => get_option('plr_banco_concepto', 'Reserva limpieza') . ' #' . $reserva_id,
            'horas'    => $horas,
        ]);
        self::enviar( $email, $asunto, $cuerpo );

        // Notificar admin
        self::enviar(
            get_option('plr_email_admin'),
            '🏦 Nueva reserva pendiente de transferencia — ' . $reserva->nombre,
            self::cargar_template('admin-nueva-reserva', [
                'nombre'         => $reserva->nombre,
                'email'          => $reserva->email,
                'telefono'       => $reserva->telefono,
                'fecha_servicio' => $reserva->fecha_servicio,
                'hora_servicio'  => substr($reserva->hora_servicio,0,5),
                'total'          => plr_formato_precio((float)$reserva->total),
                'deposito'       => plr_formato_precio($deposito),
                'admin_url'      => admin_url('admin.php?page=plr-reservas'),
            ])
        );
    }

    // ── Email: Datos de facturación al admin ─────────────────
    public static function email_datos_factura( int $reserva_id, array $fac, object $reserva ) {
        $asunto = '🧾 Solicitud de factura — Reserva #' . str_pad( $reserva_id, 5, '0', STR_PAD_LEFT );
        $iva        = (int) get_option( 'plr_iva_porcentaje', 21 );
        $total      = (float) $reserva->total;
        $base_imp   = round( $total / ( 1 + $iva / 100 ), 2 );
        $cuota_iva  = round( $total - $base_imp, 2 );

        $cuerpo = self::cargar_template( 'factura-datos-admin', [
            'reserva_id'     => $reserva_id,
            'nombre_cliente' => $reserva->nombre,
            'email_cliente'  => $reserva->email,
            'fecha_servicio' => self::formatear_fecha( $reserva->fecha_servicio ),
            'fac_nombre'     => $fac['nombre']    ?? '',
            'fac_nif'        => $fac['nif']        ?? '',
            'fac_direccion'  => $fac['direccion']  ?? '',
            'fac_cp'         => $fac['cp']         ?? '',
            'fac_ciudad'     => $fac['ciudad']     ?? '',
            'admin_url'      => admin_url( 'admin.php?page=plr-reservas' ),
            'total'          => plr_formato_precio( $total ),
            'base_imp'       => plr_formato_precio( $base_imp ),
            'cuota_iva'      => plr_formato_precio( $cuota_iva ),
            'iva_pct'        => $iva,
        ] );
        self::enviar( get_option( 'plr_email_admin' ), $asunto, $cuerpo );
    }

    // ── Email 5: Pago fallido (hook Stripe) ───────────────────
    public static function on_pago_fallido( object $reserva ) {
        $asunto = '⚠️ Ha habido un problema con tu pago';
        $cuerpo = self::cargar_template( 'pago-fallido', [
            'nombre'    => $reserva->nombre,
            'retry_url' => home_url( '/' ), // Puede apuntar al cotizador
        ] );
        self::enviar( $reserva->email, $asunto, $cuerpo );
    }

    // ── Loader de templates HTML ──────────────────────────────
    private static function cargar_template( string $nombre, array $vars = [] ) {
        $ruta = PLR_PLUGIN_DIR . "templates/emails/{$nombre}.php";
        if ( ! file_exists( $ruta ) ) {
            return "<p>Email: {$nombre}</p>";
        }
        // extract() no funciona correctamente en métodos estáticos de clase.
        // Usamos una función anónima para crear un scope limpio donde
        // las variables estén disponibles para el include.
        $render = function( $__ruta, $__vars ) {
            extract( $__vars ); // phpcs:ignore WordPress.PHP.DontExtract
            ob_start();
            include $__ruta;
            return ob_get_clean();
        };
        return $render( $ruta, $vars );
    }

    // ── Envío centralizado ────────────────────────────────────
    private static function enviar( string $to, string $asunto, string $cuerpo ) {
        if ( ! is_email( $to ) ) return;

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf( 'From: Pintalimpio <%s>', get_option( 'plr_email_admin', get_option( 'admin_email' ) ) ),
        ];

        return wp_mail( $to, $asunto, $cuerpo, $headers );
    }
}

// Inicializar hooks al cargar el módulo
PLR_Automatizacion::init();
