<?php
/**
 * Integración Google Calendar — Lectura de eventos
 * Lee eventos del calendario del admin para bloquear slots ocupados.
 *
 * Configuración necesaria:
 * 1. Crear proyecto en Google Cloud Console
 * 2. Activar Google Calendar API
 * 3. Crear API Key (no OAuth — solo lectura pública)
 * 4. Hacer el calendario PÚBLICO en Google Calendar
 * 5. Copiar el Calendar ID y la API Key en Configuración → Avanzado
 *
 * @author  Jhon Bastidas Rodrigues
 * @link    https://controlhorariowp.com/
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PLR_Google_Calendar {

    const CACHE_TTL = 900; // 15 minutos de caché

    /**
     * Obtiene los slots bloqueados por eventos de Google Calendar para una fecha.
     * Devuelve array de horas en formato 'HH:MM' que ya están ocupadas.
     */
    public static function get_slots_bloqueados( string $fecha ) {
        $api_key     = get_option( 'plr_gcal_api_key', '' );
        $calendar_id = get_option( 'plr_gcal_calendar_id', '' );

        if ( empty($api_key) || empty($calendar_id) ) return [];

        // Caché por fecha
        $cache_key = 'plr_gcal_' . md5( $fecha . $calendar_id );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        $slots_bloqueados = self::fetch_eventos( $fecha, $api_key, $calendar_id );
        set_transient( $cache_key, $slots_bloqueados, self::CACHE_TTL );

        return $slots_bloqueados;
    }

    /**
     * Llama a la API de Google Calendar y extrae las horas bloqueadas.
     */
    private static function fetch_eventos( string $fecha, string $api_key, string $calendar_id ) {
        // Rango del día completo
        $time_min = urlencode( $fecha . 'T00:00:00Z' );
        $time_max = urlencode( $fecha . 'T23:59:59Z' );
        $cal_id   = urlencode( $calendar_id );

        $url = "https://www.googleapis.com/calendar/v3/calendars/{$cal_id}/events"
             . "?key={$api_key}"
             . "&timeMin={$time_min}"
             . "&timeMax={$time_max}"
             . "&singleEvents=true"
             . "&orderBy=startTime"
             . "&maxResults=50";

        $response = wp_remote_get( $url, [
            'timeout'   => 10,
            'sslverify' => true,
        ]);

        if ( is_wp_error($response) ) {
            error_log( '[PLR] Google Calendar error: ' . $response->get_error_message() );
            return [];
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            error_log( '[PLR] Google Calendar HTTP ' . $code . ': ' . wp_remote_retrieve_body($response) );
            return [];
        }

        $data = json_decode( wp_remote_retrieve_body($response), true );
        if ( empty($data['items']) ) return [];

        $slots_bloqueados = [];
        $slots_disponibles = PLR_DB_Reservas::get_todos_los_slots();

        foreach ( $data['items'] as $evento ) {
            // Ignorar eventos de día completo (all-day)
            if ( isset($evento['start']['date']) && ! isset($evento['start']['dateTime']) ) {
                // Evento de día completo — bloquear TODOS los slots del día
                return $slots_disponibles;
            }

            if ( empty($evento['start']['dateTime']) ) continue;

            // Obtener hora de inicio y fin del evento
            $inicio_evento = strtotime( $evento['start']['dateTime'] );
            $fin_evento    = strtotime( $evento['end']['dateTime'] );

            // Convertir a timezone local de WordPress
            $tz_offset     = get_option('gmt_offset') * 3600;
            $inicio_local  = $inicio_evento + $tz_offset;
            $fin_local     = $fin_evento    + $tz_offset;

            // Bloquear los slots que coincidan con el evento
            foreach ( $slots_disponibles as $slot ) {
                $slot_ts = strtotime( $fecha . ' ' . $slot );
                // El slot se bloquea si su hora cae dentro del evento
                if ( $slot_ts >= $inicio_local && $slot_ts < $fin_local ) {
                    $slots_bloqueados[] = $slot;
                }
            }
        }

        return array_unique( $slots_bloqueados );
    }

    /**
     * Limpiar caché de Google Calendar para una fecha concreta.
     * Se llama cuando se crea o cancela una reserva.
     */
    public static function limpiar_cache( string $fecha ) {
        $calendar_id = get_option( 'plr_gcal_calendar_id', '' );
        if ( $calendar_id ) {
            delete_transient( 'plr_gcal_' . md5( $fecha . $calendar_id ) );
        }
    }

    /**
     * Verificar que la conexión funciona.
     * Devuelve true si la API responde correctamente.
     */
    public static function verificar_conexion() {
        $api_key     = get_option( 'plr_gcal_api_key', '' );
        $calendar_id = get_option( 'plr_gcal_calendar_id', '' );
        if ( empty($api_key) || empty($calendar_id) ) {
            return [ 'ok' => false, 'mensaje' => 'Faltan la API Key o el Calendar ID.' ];
        }

        $cal_id = urlencode( $calendar_id );
        $url    = "https://www.googleapis.com/calendar/v3/calendars/{$cal_id}?key={$api_key}";

        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );
        if ( is_wp_error($response) ) {
            return [ 'ok' => false, 'mensaje' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body($response), true );

        if ( $code === 200 ) {
            return [ 'ok' => true, 'mensaje' => '✅ Conectado correctamente a: ' . ($body['summary'] ?? $calendar_id) ];
        }

        $error = $body['error']['message'] ?? 'Error desconocido';
        return [ 'ok' => false, 'mensaje' => "Error {$code}: {$error}" ];
    }
}
