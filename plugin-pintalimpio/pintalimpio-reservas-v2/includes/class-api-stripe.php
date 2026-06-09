<?php
/**
 * Integración con Stripe — Pintalimpio Reservas
 *
 * Usa la API de Stripe mediante HTTP directo (wp_remote_post/get)
 * sin depender de la librería oficial de PHP de Stripe.
 *
 * @author  Jhon Bastidas Rodrigues
 * @link    https://controlhorariowp.com/
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PLR_API_Stripe {

    private static function sk() { return plr_stripe_sk(); }

    // ── Request helper ────────────────────────────────────────
    private static function request( string $method, string $endpoint, array $body = [] ) {
        $sk = self::sk();
        if ( empty( $sk ) ) return new WP_Error( 'stripe_no_key', 'Clave secreta de Stripe no configurada.' );

        $args = [
            'method'  => strtoupper( $method ),
            'headers' => [
                'Authorization' => 'Bearer ' . $sk,
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'Stripe-Version'=> '2023-10-16',
            ],
            'timeout' => 20,
        ];

        if ( 'POST' === $args['method'] && ! empty( $body ) ) {
            $args['body'] = http_build_query( $body );
        }

        $resp = wp_remote_request( 'https://api.stripe.com/v1/' . ltrim( $endpoint, '/' ), $args );

        if ( is_wp_error( $resp ) ) return $resp;

        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        $code = (int) wp_remote_retrieve_response_code( $resp );

        if ( $code >= 400 ) {
            $msg = $data['error']['message'] ?? 'Error desconocido de Stripe.';
            return new WP_Error( 'stripe_error', $msg );
        }

        return $data;
    }

    // ── Crear PaymentIntent (depósito 50%) ────────────────────
    public static function crear_payment_intent( array $datos ) {
        // Validar el total en servidor antes de cobrar
        if ( ! PLR_Calculadora::validar_total( (int)$datos['m2'], (string)$datos['tipo_id'], (array)($datos['estancias'] ?? []), (float)$datos['total'], (float)($datos['km_coste'] ?? 0) ) ) {
            return new WP_Error( 'precio_invalido', 'El precio no coincide con el calculado en servidor.' );
        }

        $calc     = PLR_Calculadora::calcular( (int)$datos['m2'], (string)$datos['tipo_id'], (array)($datos['estancias'] ?? []) );
        if ( is_wp_error( $calc ) ) return $calc;
        $deposito = $calc['deposito'];
        $centavos = (int) round( $deposito * 100 ); // Stripe trabaja en céntimos

        $resp = self::request( 'POST', 'payment_intents', [
            'amount'                    => $centavos,
            'currency'                  => 'eur',
            'automatic_payment_methods' => [ 'enabled' => 'true' ],
            'metadata'                  => [
                'plugin'         => 'pintalimpio-reservas',
                'cliente_nombre' => sanitize_text_field( $datos['nombre'] ),
                'cliente_email'  => sanitize_email( $datos['email'] ),
                'fecha_servicio' => sanitize_text_field( $datos['fecha_servicio'] ),
                'hora_servicio'  => sanitize_text_field( $datos['hora_servicio'] ),
                'total_completo' => $calc['total'],
            ],
            'receipt_email' => sanitize_email( $datos['email'] ),
            'description'   => sprintf( 'Depósito reserva limpieza %s — %s %s — %s',
                $datos['tipo_label'] ?? $datos['tipo_id'], $datos['fecha_servicio'], $datos['hora_servicio'], $datos['nombre']
            ),
        ] );

        if ( is_wp_error( $resp ) ) return $resp;

        return [
            'payment_intent_id' => $resp['id'],
            'client_secret'     => $resp['client_secret'],
            'deposito'          => $deposito,
            'total'             => $calc['total'],
        ];
    }

    // ── Verificar PaymentIntent ───────────────────────────────
    public static function verificar_payment_intent( string $pi_id ) {
        $pi_id = sanitize_text_field( $pi_id );
        if ( empty( $pi_id ) || strpos( $pi_id, 'pi_' ) !== 0 ) {
            return new WP_Error( 'pi_invalido', 'ID de PaymentIntent no válido.' );
        }

        $resp = self::request( 'GET', 'payment_intents/' . $pi_id );
        if ( is_wp_error( $resp ) ) return $resp;

        return $resp['status'] ?? 'unknown';
    }

    // ── Crear Checkout Session (pago final 50%) ───────────────
    public static function crear_checkout_session( object $reserva ) {
        $resto    = round( (float)$reserva->total - (float)$reserva->deposito, 2 );
        $centavos = (int) round( $resto * 100 );

        $resp = self::request( 'POST', 'checkout/sessions', [
            'mode'                 => 'payment',
            'customer_email'       => $reserva->email,
            'success_url'          => add_query_arg( 'plr_pago', 'ok',    home_url( '/' ) ),
            'cancel_url'           => add_query_arg( 'plr_pago', 'cancel', home_url( '/' ) ),
            'line_items[0][price_data][currency]'                  => 'eur',
            'line_items[0][price_data][unit_amount]'               => $centavos,
            'line_items[0][price_data][product_data][name]'        => 'Pago final servicio de limpieza',
            'line_items[0][price_data][product_data][description]' => sprintf(
                'Servicio del %s a las %s — %s',
                $reserva->fecha_servicio, $reserva->hora_servicio, $reserva->nombre
            ),
            'line_items[0][quantity]' => 1,
            'metadata[reserva_id]'    => $reserva->id,
            'metadata[plugin]'        => 'pintalimpio-reservas',
            'payment_intent_data[metadata][reserva_id]' => $reserva->id,
        ] );

        if ( is_wp_error( $resp ) ) return $resp;

        return [
            'checkout_url' => $resp['url'],
            'session_id'   => $resp['id'],
        ];
    }

    // ── Webhook ───────────────────────────────────────────────
    public static function manejar_webhook() {
        $payload    = file_get_contents( 'php://input' );
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret     = get_option( 'plr_stripe_webhook_secret', '' );

        if ( empty( $secret ) ) {
            http_response_code( 500 );
            echo 'Webhook secret no configurado.';
            return;
        }

        // Verificar firma del webhook manualmente (sin SDK de Stripe)
        $evento = self::verificar_firma_webhook( $payload, $sig_header, $secret );
        if ( is_wp_error( $evento ) ) {
            http_response_code( 400 );
            echo esc_html( $evento->get_error_message() );
            return;
        }

        $tipo = $evento['type'] ?? '';

        switch ( $tipo ) {

            // Depósito pagado correctamente
            case 'payment_intent.succeeded':
                $pi_id   = $evento['data']['object']['id'] ?? '';
                $reserva = PLR_DB_Reservas::get_reserva_por_pi( $pi_id );
                if ( $reserva && self::ESTADO_PENDIENTE === $reserva->estado ) {
                    PLR_DB_Reservas::actualizar_estado( (int)$reserva->id, PLR_DB_Reservas::ESTADO_RESERVADO );
                }
                break;

            // Pago final completado vía Checkout
            case 'checkout.session.completed':
                $cs_id   = $evento['data']['object']['id'] ?? '';
                $reserva = PLR_DB_Reservas::get_reserva_por_cs( $cs_id );
                if ( $reserva && self::ESTADO_FINALIZADO === $reserva->estado ) {
                    PLR_DB_Reservas::actualizar_estado( (int)$reserva->id, PLR_DB_Reservas::ESTADO_PAGADO );
                }
                break;

            // Pago fallido — notificar al admin
            case 'payment_intent.payment_failed':
                $pi_id   = $evento['data']['object']['id'] ?? '';
                $reserva = PLR_DB_Reservas::get_reserva_por_pi( $pi_id );
                if ( $reserva ) {
                    do_action( 'plr_pago_fallido', $reserva );
                }
                break;
        }

        http_response_code( 200 );
        echo 'ok';
    }

    // ── Verificar firma webhook (sin SDK) ─────────────────────
    private static function verificar_firma_webhook( string $payload, string $sig_header, string $secret ) {
        if ( empty( $sig_header ) ) return new WP_Error( 'sin_firma', 'Cabecera de firma ausente.' );

        // Parsear t=timestamp,v1=hash
        $parts = [];
        foreach ( explode( ',', $sig_header ) as $part ) {
            [ $k, $v ] = array_pad( explode( '=', $part, 2 ), 2, '' );
            $parts[ trim($k) ] = trim($v);
        }

        if ( empty( $parts['t'] ) || empty( $parts['v1'] ) ) {
            return new WP_Error( 'firma_invalida', 'Formato de firma incorrecto.' );
        }

        $timestamp = (int) $parts['t'];

        // Rechazar webhooks de más de 5 minutos (replay attack)
        if ( abs( time() - $timestamp ) > 300 ) {
            return new WP_Error( 'firma_expirada', 'Webhook demasiado antiguo.' );
        }

        $signed_payload = $timestamp . '.' . $payload;
        $expected       = hash_hmac( 'sha256', $signed_payload, $secret );

        if ( ! hash_equals( $expected, $parts['v1'] ) ) {
            return new WP_Error( 'firma_incorrecta', 'La firma del webhook no coincide.' );
        }

        return json_decode( $payload, true );
    }

    // Constante de estado reutilizada aquí para claridad
    private const ESTADO_PENDIENTE  = 'pendiente';
    private const ESTADO_FINALIZADO = 'finalizado';
}
