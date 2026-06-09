<?php
/**
 * Cálculo de Extra por Kilometraje — Pintalimpio Reservas
 *
 * Calcula la distancia real entre la sede y el domicilio del cliente
 * usando la Google Maps Distance Matrix API.
 * El extra es invisible para el cliente, se suma internamente al total.
 *
 * @author  Jhon Bastidas Rodrigues
 * @link    https://controlhorariowp.com/
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PLR_Kilometraje {

    /**
     * Obtiene la distancia en km entre la sede y la dirección del cliente.
     * Usa caché transitoria de 24h para no repetir llamadas a la API.
     *
     * @param string $direccion_cliente  Dirección completa del servicio.
     * @return float|WP_Error  Distancia en km, o WP_Error si falla.
     */
    public static function get_distancia_km( string $direccion_cliente ) {
        $api_key = get_option( 'plr_google_maps_key', '' );

        if ( empty( $api_key ) ) {
            // Sin API key: no cobramos extra de km (modo seguro)
            return 0.0;
        }

        $direccion_cliente = sanitize_text_field( $direccion_cliente );
        if ( empty( $direccion_cliente ) ) {
            return new WP_Error( 'direccion_vacia', 'La dirección del servicio no puede estar vacía.' );
        }

        $sede = get_option( 'plr_sede_direccion', 'Calle del Planeta Venus 28, 28983 Parla, Madrid' );

        // Cache para no gastar llamadas a la API con la misma dirección
        $cache_key = 'plr_km_' . md5( $sede . '|' . $direccion_cliente );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) return (float) $cached;

        $url = add_query_arg( [
            'origins'      => urlencode( $sede ),
            'destinations' => urlencode( $direccion_cliente ),
            'mode'         => 'driving',
            'units'        => 'metric',
            'language'     => 'es',
            'key'          => $api_key,
        ], 'https://maps.googleapis.com/maps/api/distancematrix/json' );

        $resp = wp_remote_get( $url, [ 'timeout' => 10 ] );

        if ( is_wp_error( $resp ) ) return $resp;

        $body = json_decode( wp_remote_retrieve_body( $resp ), true );

        if ( 'OK' !== ( $body['status'] ?? '' ) ) {
            return new WP_Error( 'maps_error', 'Error al consultar Google Maps: ' . ( $body['status'] ?? 'desconocido' ) );
        }

        $elemento = $body['rows'][0]['elements'][0] ?? null;

        if ( ! $elemento || 'OK' !== ( $elemento['status'] ?? '' ) ) {
            return new WP_Error( 'direccion_no_encontrada', 'No se pudo calcular la ruta. Verifica la dirección introducida.' );
        }

        // La API devuelve metros — convertimos a km
        $distancia_km = round( $elemento['distance']['value'] / 1000, 2 );

        // Guardar en caché 24 horas
        set_transient( $cache_key, $distancia_km, DAY_IN_SECONDS );

        return $distancia_km;
    }

    /**
     * Calcula el coste extra por kilometraje.
     * Los primeros $km_gratis km son gratuitos.
     * A partir de ahí: km_extra × 2 (ida y vuelta) × precio_km.
     *
     * @param string $direccion_cliente
     * @return array {
     *   'distancia_km'  => float,
     *   'km_extra'      => float,   // km facturables (solo los que superan el radio)
     *   'km_facturados' => float,   // ida + vuelta
     *   'coste'         => float,   // € a añadir al total
     *   'aplica'        => bool,    // true si hay extra
     * }|WP_Error
     */
    public static function calcular_extra( string $direccion_cliente ) {
        $distancia = self::get_distancia_km( $direccion_cliente );

        if ( is_wp_error( $distancia ) ) return $distancia;

        $km_gratis    = (float) get_option( 'plr_km_gratis', 30 );
        $precio_km    = (float) get_option( 'plr_precio_km_extra', 1.00 );

        $km_extra     = max( 0, $distancia - $km_gratis );
        $km_facturados = round( $km_extra * 2, 2 );       // ida y vuelta
        $coste         = round( $km_facturados * $precio_km, 2 );
        $aplica        = $coste > 0;

        return [
            'distancia_km'  => $distancia,
            'km_gratis'     => $km_gratis,
            'km_extra'      => $km_extra,
            'km_facturados' => $km_facturados,
            'coste'         => $coste,
            'aplica'        => $aplica,
        ];
    }
}
