<?php
/**
 * Plugin Name:       Pintalimpio Reservas
 * Plugin URI:        https://controlhorariowp.com/
 * Description:       Sistema ERP interno de presupuestos, reservas y cobros automáticos para servicios de limpieza.
 * Version:           1.0.6
 * Author:            Jhon Bastidas Rodrigues
 * Author URI:        https://controlhorariowp.com/
 * License:           GPL-2.0+
 * Text Domain:       pintalimpio-reservas
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PLR_VERSION',        '1.3.5' );
define( 'PLR_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'PLR_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );

global $wpdb;
define( 'PLR_TABLE_RESERVAS', $wpdb->prefix . 'plr_reservas' );

// ── Carga de módulos ──────────────────────────────────────────
function plr_cargar_modulos() {
    $modulos = [
        'includes/class-db-reservas.php',
        'includes/class-calculadora.php',
        'includes/class-api-stripe.php',
        'includes/class-automatizacion.php',
        'includes/class-kilometraje.php',
        'includes/class-analisis-ia.php',
        'includes/class-google-calendar.php',
        'includes/class-pdf-orden.php',
    ];
    foreach ( $modulos as $m ) {
        $ruta = PLR_PLUGIN_DIR . $m;
        if ( file_exists( $ruta ) ) require_once $ruta;
    }
}
add_action( 'plugins_loaded', 'plr_cargar_modulos' );

// Ejecutar migración de BD si la versión cambió
function plr_verificar_migracion() {
    if ( get_option('plr_db_version') !== PLR_VERSION ) {
        require_once PLR_PLUGIN_DIR . 'includes/class-db-reservas.php';
        PLR_DB_Reservas::crear_tabla();
        PLR_DB_Reservas::migrar_columnas();
        global $wpdb;
        // Limpiar valores conflictivos
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'plr_e_act_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'plr_o_act_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'plr_cat_activa_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'plr_tipo_activo_%'" );
        // Limpiar precios de garajes para que tomen los nuevos valores del código
        foreach ( ['gar_plazas','gar_plantas','gar_banos','gar_aspirado','gar_fregado','gar_fregadora','gar_bano_basico','gar_bano_desinfeccion'] as $id ) {
            delete_option( 'plr_e_pre_' . $id );
            delete_option( 'plr_e_nom_' . $id );
            delete_option( 'plr_e_act_' . $id );
        }
        // Forzar horario correcto: 09:00 a 11:00, slots de 30 min
        // Solo actualizar si el valor actual no es el correcto (para no sobreescribir cambios del admin)
        if ( get_option('plr_hora_inicio') === '08:00' || ! get_option('plr_hora_inicio') )
            update_option( 'plr_hora_inicio', '09:00' );
        if ( get_option('plr_hora_fin') === '18:00' || ! get_option('plr_hora_fin') )
            update_option( 'plr_hora_fin', '11:00' );
        if ( get_option('plr_duracion_slot') === '60' || ! get_option('plr_duracion_slot') )
            update_option( 'plr_duracion_slot', '30' );
        update_option( 'plr_db_version', PLR_VERSION );
    }
}
add_action( 'plugins_loaded', 'plr_verificar_migracion', 20 );

// ── Activación ────────────────────────────────────────────────
function plr_activar() {
    require_once PLR_PLUGIN_DIR . 'includes/class-db-reservas.php';
    PLR_DB_Reservas::crear_tabla();
    // Migración: añadir columna estancias_json si no existe
    PLR_DB_Reservas::migrar_columnas();
    update_option( 'plr_db_version', PLR_VERSION );

    $defaults = [
        'plr_precio_base_m2'        => 5.00,
        'plr_horas_por_m2'          => 0.40,
        // Módulo 1 — General
        'plr_nombre_empresa'        => 'Pintalimpio',
        'plr_color_principal'       => '#1a7f5a',
        'plr_logo_url'              => '',
        'plr_telefono_empresa'      => '',
        'plr_precio_hora'           => 20.00,
        'plr_iva_porcentaje'        => 21,
        'plr_porcentaje_deposito'   => 50,
        // Módulo 1 — General
        'plr_nombre_empresa'        => 'Pintalimpio',
        'plr_color_principal'       => '#1a7f5a',
        'plr_logo_url'              => '',
        'plr_telefono_empresa'      => '',
        'plr_precio_hora'           => 20.00,
        'plr_iva_porcentaje'        => 21,
        'plr_stripe_modo'           => 'test',
        'plr_stripe_pk_test'        => '',
        'plr_stripe_sk_test'        => '',
        'plr_stripe_pk_live'        => '',
        'plr_stripe_sk_live'        => '',
        'plr_stripe_webhook_secret' => '',
        'plr_email_admin'           => get_option( 'admin_email' ),
        'plr_google_review_url'     => '',
        'plr_anthropic_api_key'     => '',
        'plr_banco_titular'         => '',
        'plr_banco_iban'            => '',
        'plr_banco_entidad'         => '',
        'plr_banco_concepto'        => 'Reserva limpieza',
        'plr_transferencia_horas'   => 24,
        'plr_google_maps_key'       => '',
        'plr_sede_direccion'        => 'Calle del Planeta Venus 28, 28983 Parla, Madrid',
        'plr_km_gratis'             => 30,
        'plr_precio_km_extra'       => 1.00,
        'plr_extras'                => wp_json_encode( [
            [ 'id' => 'cristales', 'label' => 'Limpieza de cristales', 'precio' => 30 ],
            [ 'id' => 'plancha',   'label' => 'Servicio de plancha',   'precio' => 25 ],
            [ 'id' => 'armarios',  'label' => 'Interior de armarios',  'precio' => 20 ],
            [ 'id' => 'nevera',    'label' => 'Interior de nevera',    'precio' => 15 ],
            [ 'id' => 'horno',     'label' => 'Limpieza de horno',     'precio' => 15 ],
            [ 'id' => 'terraza',   'label' => 'Limpieza de terraza',   'precio' => 20 ],
        ] ),
    ];
    foreach ( $defaults as $k => $v ) add_option( $k, $v );
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'plr_activar' );

function plr_desactivar() { flush_rewrite_rules(); }
register_deactivation_hook( __FILE__, 'plr_desactivar' );

// ── Init principal ────────────────────────────────────────────
function plr_init() {
    load_plugin_textdomain( 'pintalimpio-reservas', false, PLR_PLUGIN_DIR . 'languages/' );
    add_shortcode( 'pintalimpio_cotizador', 'plr_shortcode_cotizador' );
    plr_registrar_rest();
    plr_registrar_webhook_rewrite();
}
add_action( 'init', 'plr_init' );

// ── Shortcode cotizador ───────────────────────────────────────
// wp_localize_script funciona dentro del shortcode porque el script
// se encola en el footer (ultimo parametro = true).
// WordPress procesa los shortcodes antes de imprimir el footer.
function plr_shortcode_cotizador() {
    // Garantizar que PLR_Calculadora esta disponible
    require_once PLR_PLUGIN_DIR . 'includes/class-calculadora.php';

    wp_enqueue_style(  'plr-cotizador', PLR_PLUGIN_URL . 'assets/css/cotizador.css', [], PLR_VERSION );
    wp_enqueue_script( 'stripe-js',     'https://js.stripe.com/v3/', [], null, true );
    wp_enqueue_script( 'plr-cotizador', PLR_PLUGIN_URL . 'assets/js/cotizador.js', [ 'stripe-js' ], PLR_VERSION, true );

    // wp_add_inline_script es mas fiable que wp_localize_script
    // dentro de shortcodes — funciona aunque el script ya este encolado
    $config = [
        'rest_url'       => rest_url( 'plr/v1/' ),
        'nonce'          => wp_create_nonce( 'wp_rest' ),
        'porcentaje_dep'   => (int)   get_option( 'plr_porcentaje_deposito', 50 ),
        'nombre_empresa'   => get_option( 'plr_nombre_empresa', 'Pintalimpio' ),
        'google_ads_id'    => get_option( 'plr_google_ads_id', '' ),
        'fechas_bloqueadas'=> array_column( (array) PLR_DB_Reservas::get_fechas_bloqueadas( date('Y-m-d'), date('Y-m-d', strtotime('+6 months')) ), 'fecha' ),
        'nonce_bloqueo'    => wp_create_nonce('wp_rest'),
        'color_principal'  => get_option( 'plr_color_principal', '#1a7f5a' ),
        'logo_url'         => get_option( 'plr_logo_url', '' ),
        'iva'            => (int)   get_option( 'plr_iva_porcentaje', 21 ),
        'precio_hora'    => (float) get_option( 'plr_precio_hora', 20.00 ),
        'stripe_pk'      => plr_stripe_pk(),
        'extras'         => json_decode( get_option( 'plr_extras', '[]' ), true ) ?: [],
        'catalogo'            => PLR_Calculadora::get_catalogo(),
        'estancias'           => array_values( PLR_Calculadora::get_estancias() ),
        'estancias_inmuebles' => array_values( PLR_Calculadora::get_estancias_inmuebles() ),
        'estancias_trasteros' => array_values( PLR_Calculadora::get_estancias_trasteros() ),
        'estancias_garajes'   => array_values( PLR_Calculadora::get_estancias_garajes() ),
        'estancias_vaciado'       => array_values( PLR_Calculadora::get_estancias_vaciado() ),
        'multiplicadores'         => PLR_Calculadora::get_multiplicadores_con_config(),
        'estancias_cristales'     => array_values( PLR_Calculadora::get_estancias_cristales() ),
        'estancias_mantenimiento' => array_values( PLR_Calculadora::get_estancias_mantenimiento() ),
    ];
    wp_add_inline_script(
        'plr-cotizador',
        'window.PLR_Config = ' . wp_json_encode( $config ) . ';',
        'before'
    );

    // Usar output buffering con limpieza garantizada
    $nivel_buffer = ob_get_level();
    ob_start();
    include PLR_PLUGIN_DIR . 'templates/cotizador.php';
    $output = ob_get_clean();
    // Limpiar cualquier buffer extra que haya podido quedar abierto
    while ( ob_get_level() > $nivel_buffer ) {
        ob_end_clean();
    }
    return $output;
}

// ── REST API ──────────────────────────────────────────────────
function plr_registrar_rest() {
    add_action( 'rest_api_init', function () {

        register_rest_route( 'plr/v1', '/disponibilidad', [
            'methods'             => 'GET',
            'callback'            => 'plr_rest_disponibilidad',
            'permission_callback' => 'plr_permiso_publico_con_nonce',
            'args'                => [ 'fecha' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ] ],
        ] );

        register_rest_route( 'plr/v1', '/crear-reserva', [
            'methods'             => 'POST',
            'callback'            => 'plr_rest_crear_reserva',
            'permission_callback' => 'plr_permiso_publico_con_nonce',
        ] );

        register_rest_route( 'plr/v1', '/confirmar-pago', [
            'methods'             => 'POST',
            'callback'            => 'plr_rest_confirmar_pago',
            'permission_callback' => 'plr_permiso_publico_con_nonce',
        ] );

        register_rest_route( 'plr/v1', '/reserva-transferencia', [
            'methods'             => 'POST',
            'callback'            => 'plr_rest_reserva_transferencia',
            'permission_callback' => 'plr_permiso_publico_con_nonce',
        ] );

        register_rest_route( 'plr/v1', '/subir-foto', [
            'methods'             => 'POST',
            'callback'            => 'plr_rest_subir_foto',
            'permission_callback' => 'plr_permiso_publico_con_nonce',
        ] );

        register_rest_route( 'plr/v1', '/analizar-estancia', [
            'methods'             => 'POST',
            'callback'            => 'plr_rest_analizar_estancia',
            'permission_callback' => 'plr_permiso_publico_con_nonce',
        ] );

        register_rest_route( 'plr/v1', '/verificar-gcal', [
            'methods'             => 'GET',
            'callback'            => 'plr_rest_verificar_gcal',
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ] );

        register_rest_route( 'plr/v1', '/bloquear-fecha', [
            'methods'             => 'POST',
            'callback'            => 'plr_rest_bloquear_fecha',
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ] );
        register_rest_route( 'plr/v1', '/desbloquear-fecha', [
            'methods'             => 'POST',
            'callback'            => 'plr_rest_desbloquear_fecha',
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ] );
        register_rest_route( 'plr/v1', '/fechas-bloqueadas', [
            'methods'             => 'GET',
            'callback'            => 'plr_rest_get_fechas_bloqueadas',
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'plr/v1', '/calcular-km', [
            'methods'             => 'POST',
            'callback'            => 'plr_rest_calcular_km',
            'permission_callback' => 'plr_permiso_publico_con_nonce',
        ] );
    } );
}

// ── Permiso con nonce REST estándar ──────────────────────────
function plr_permiso_publico_con_nonce( WP_REST_Request $request ) {
    $nonce = $request->get_header( 'X-WP-Nonce' ) ?? $request->get_param( '_wpnonce' );
    if ( empty( $nonce ) ) {
        return new WP_Error( 'plr_sin_nonce', 'Token de seguridad ausente.', [ 'status' => 403 ] );
    }
    if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_Error( 'plr_nonce_invalido', 'Token de seguridad caducado. Recarga la página.', [ 'status' => 403 ] );
    }
    return true;
}

// ── Callbacks REST ────────────────────────────────────────────
function plr_rest_disponibilidad( WP_REST_Request $r ) {
    $fecha = $r->get_param( 'fecha' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fecha ) ) {
        return new WP_REST_Response( [ 'error' => 'Fecha inválida.' ], 400 );
    }
    return new WP_REST_Response( [ 'slots' => PLR_DB_Reservas::get_slots_disponibles( $fecha ) ], 200 );
}

function plr_rest_crear_reserva( WP_REST_Request $r ) {
    $datos = [
        'nombre'         => sanitize_text_field( $r->get_param( 'nombre' ) ),
        'email'          => sanitize_email( $r->get_param( 'email' ) ),
        'telefono'       => sanitize_text_field( $r->get_param( 'telefono' ) ),
        'm2'             => absint( $r->get_param( 'm2' ) ),
        'tipo_id'        => sanitize_text_field( $r->get_param( 'tipo_id' ) ),
        'tipo_label'     => sanitize_text_field( $r->get_param( 'tipo_label' ) ),
        'estancias'      => (array) $r->get_param( 'estancias' ),
        'fecha_servicio' => sanitize_text_field( $r->get_param( 'fecha' ) ),
        'hora_servicio'  => sanitize_text_field( $r->get_param( 'hora' ) ),
        'total'          => floatval( $r->get_param( 'total' ) ),
        'direccion'      => sanitize_text_field( $r->get_param( 'direccion' ) ),
        'facturacion'    => plr_sanitizar_facturacion( $r->get_param( 'facturacion' ) ),
        'observaciones'  => sanitize_textarea_field( $r->get_param( 'observaciones' ) ?? '' ),
    ];

    $km = PLR_Kilometraje::calcular_extra( $datos['direccion'] );
    if ( ! is_wp_error( $km ) && $km['aplica'] ) {
        $datos['total']    = round( $datos['total'] + $km['coste'], 2 );
        $datos['km_extra'] = $km['km_facturados'];
        $datos['km_coste'] = $km['coste'];
        $datos['km_info']  = $km;
    }

    $intencion = PLR_API_Stripe::crear_payment_intent( $datos );
    if ( is_wp_error( $intencion ) ) return new WP_REST_Response( [ 'error' => $intencion->get_error_message() ], 400 );

    $datos['stripe_pi_id'] = $intencion['payment_intent_id'];
    $datos['deposito']     = $intencion['deposito'];
    $datos['metodo_pago']  = 'tarjeta';

    $id = PLR_DB_Reservas::insertar_reserva( $datos );
    if ( is_wp_error( $id ) ) return new WP_REST_Response( [ 'error' => $id->get_error_message() ], 400 );

    return new WP_REST_Response( [
        'reserva_id'    => $id,
        'client_secret' => $intencion['client_secret'],
        'deposito'      => $intencion['deposito'],
    ], 200 );
}

function plr_rest_confirmar_pago( WP_REST_Request $r ) {
    $reserva_id = absint( $r->get_param( 'reserva_id' ) );
    $pi_id      = sanitize_text_field( $r->get_param( 'payment_intent_id' ) );

    $verificado = PLR_API_Stripe::verificar_payment_intent( $pi_id );
    if ( is_wp_error( $verificado ) || 'succeeded' !== $verificado ) {
        return new WP_REST_Response( [ 'error' => 'Pago no confirmado por Stripe.' ], 400 );
    }

    $resultado = PLR_DB_Reservas::actualizar_estado( $reserva_id, PLR_DB_Reservas::ESTADO_RESERVADO );
    if ( is_wp_error( $resultado ) ) return new WP_REST_Response( [ 'error' => $resultado->get_error_message() ], 400 );

    return new WP_REST_Response( [ 'ok' => true ], 200 );
}

function plr_rest_reserva_transferencia( WP_REST_Request $r ) {
    $datos = [
        'nombre'         => sanitize_text_field( $r->get_param( 'nombre' ) ),
        'email'          => sanitize_email( $r->get_param( 'email' ) ),
        'telefono'       => sanitize_text_field( $r->get_param( 'telefono' ) ),
        'm2'             => absint( $r->get_param( 'm2' ) ),
        'tipo_id'        => sanitize_text_field( $r->get_param( 'tipo_id' ) ),
        'tipo_label'     => sanitize_text_field( $r->get_param( 'tipo_label' ) ),
        'estancias'      => (array) $r->get_param( 'estancias' ),
        'fecha_servicio' => sanitize_text_field( $r->get_param( 'fecha' ) ),
        'hora_servicio'  => sanitize_text_field( $r->get_param( 'hora' ) ),
        'total'          => floatval( $r->get_param( 'total' ) ),
        'direccion'      => sanitize_text_field( $r->get_param( 'direccion' ) ),
        'metodo_pago'    => 'transferencia',
        'facturacion'    => plr_sanitizar_facturacion( $r->get_param( 'facturacion' ) ),
        'observaciones'  => sanitize_textarea_field( $r->get_param( 'observaciones' ) ?? '' ),
    ];

    $km = PLR_Kilometraje::calcular_extra( $datos['direccion'] );
    if ( ! is_wp_error( $km ) && $km['aplica'] ) {
        $datos['total']    = round( $datos['total'] + $km['coste'], 2 );
        $datos['km_extra'] = $km['km_facturados'];
        $datos['km_coste'] = $km['coste'];
        $datos['km_info']  = $km;
    }

    $deposito          = round( $datos['total'] * ( (int) get_option( 'plr_porcentaje_deposito', 50 ) / 100 ), 2 );
    $datos['deposito'] = $deposito;

    $id = PLR_DB_Reservas::insertar_reserva( $datos );
    if ( is_wp_error( $id ) ) return new WP_REST_Response( [ 'error' => $id->get_error_message() ], 400 );

    $horas  = (int) get_option( 'plr_transferencia_horas', 24 );
    $expira = date( 'Y-m-d H:i:s', strtotime( "+{$horas} hours" ) );

    global $wpdb;
    $wpdb->update(
        PLR_TABLE_RESERVAS,
        [ 'estado' => PLR_DB_Reservas::ESTADO_PEND_TRANSFERENCIA, 'transferencia_expira' => $expira ],
        [ 'id' => $id ],
        [ '%s', '%s' ],
        [ '%d' ]
    );

    // Pasar los datos directamente para evitar problemas de lectura de BD
    do_action( 'plr_enviar_instrucciones_transferencia', $id, $deposito, $datos );

    return new WP_REST_Response( [
        'ok'        => true,
        'reserva_id'=> $id,
        'deposito'  => $deposito,
        'expira_en' => $horas,
        'banco'     => [
            'titular' => get_option( 'plr_banco_titular', '' ),
            'iban'    => get_option( 'plr_banco_iban', '' ),
            'entidad' => get_option( 'plr_banco_entidad', '' ),
            'concepto'=> get_option( 'plr_banco_concepto', 'Reserva limpieza' ) . ' #' . $id,
        ],
    ], 200 );
}

function plr_rest_subir_foto( WP_REST_Request $r ) {
    if ( empty( $_FILES['foto'] ) ) return new WP_REST_Response( [ 'error' => 'No se recibió ninguna imagen.' ], 400 );
    $reserva_id  = absint( $r->get_param( 'reserva_id' ) );
    $estancia_id = sanitize_text_field( $r->get_param( 'estancia_id' ) );
    $att_id      = PLR_Analisis_IA::subir_imagen( $_FILES['foto'], $reserva_id, $estancia_id );
    if ( is_wp_error( $att_id ) ) return new WP_REST_Response( [ 'error' => $att_id->get_error_message() ], 400 );
    return new WP_REST_Response( [
        'attachment_id' => $att_id,
        'url'           => wp_get_attachment_url( $att_id ),
        'thumb'         => wp_get_attachment_image_url( $att_id, 'thumbnail' ),
    ], 200 );
}

function plr_rest_analizar_estancia( WP_REST_Request $r ) {
    $attachment_ids = array_map( 'absint', (array) $r->get_param( 'attachment_ids' ) );
    $estancia_label = sanitize_text_field( $r->get_param( 'estancia_label' ) );
    if ( empty( $attachment_ids ) ) return new WP_REST_Response( [ 'error' => 'No hay imágenes.' ], 400 );
    $grado = PLR_Analisis_IA::analizar_estancia( $attachment_ids, $estancia_label );
    if ( is_wp_error( $grado ) ) $grado = 'normal';
    return new WP_REST_Response( [ 'grado' => $grado, 'multiplicador' => PLR_Analisis_IA::get_multiplicador( $grado ) ], 200 );
}

function plr_rest_bloquear_fecha( WP_REST_Request $r ) {
    require_once PLR_PLUGIN_DIR . 'includes/class-db-reservas.php';
    $fecha  = sanitize_text_field( $r->get_param('fecha') ?? '' );
    $motivo = sanitize_text_field( $r->get_param('motivo') ?? '' );
    if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) )
        return new WP_REST_Response( ['ok'=>false,'mensaje'=>'Fecha inválida'], 400 );
    PLR_DB_Reservas::bloquear_fecha( $fecha, $motivo );
    return new WP_REST_Response( ['ok'=>true,'fecha'=>$fecha] );
}

function plr_rest_desbloquear_fecha( WP_REST_Request $r ) {
    require_once PLR_PLUGIN_DIR . 'includes/class-db-reservas.php';
    $fecha = sanitize_text_field( $r->get_param('fecha') ?? '' );
    PLR_DB_Reservas::desbloquear_fecha( $fecha );
    return new WP_REST_Response( ['ok'=>true,'fecha'=>$fecha] );
}

function plr_rest_get_fechas_bloqueadas() {
    require_once PLR_PLUGIN_DIR . 'includes/class-db-reservas.php';
    $bloqueadas = PLR_DB_Reservas::get_fechas_bloqueadas();
    return new WP_REST_Response( array_column( (array)$bloqueadas, 'fecha' ) );
}

function plr_rest_verificar_gcal() {
    if ( ! class_exists('PLR_Google_Calendar') ) {
        return new WP_REST_Response( [ 'ok' => false, 'mensaje' => 'Módulo no cargado.' ], 400 );
    }
    $resultado = PLR_Google_Calendar::verificar_conexion();
    return new WP_REST_Response( $resultado, $resultado['ok'] ? 200 : 400 );
}

function plr_rest_calcular_km( WP_REST_Request $r ) {
    $direccion = sanitize_text_field( $r->get_param( 'direccion' ) );
    if ( empty( $direccion ) ) return new WP_REST_Response( [ 'error' => 'Dirección requerida.' ], 400 );
    $resultado = PLR_Kilometraje::calcular_extra( $direccion );
    if ( is_wp_error( $resultado ) ) return new WP_REST_Response( [ 'error' => $resultado->get_error_message() ], 400 );
    return new WP_REST_Response( $resultado, 200 );
}

// ── Webhook Stripe ────────────────────────────────────────────
function plr_registrar_webhook_rewrite() {
    add_rewrite_rule( '^plr-webhook/stripe/?$', 'index.php?plr_webhook=stripe', 'top' );
    add_filter( 'query_vars', fn( $v ) => array_merge( $v, [ 'plr_webhook' ] ) );
    add_action( 'template_redirect', function () {
        if ( 'stripe' === get_query_var( 'plr_webhook' ) ) {
            PLR_API_Stripe::manejar_webhook();
            exit;
        }
    } );
}

/**
 * Comprueba en cada carga si las reglas de rewrite del plugin
 * están registradas. Si no lo están (ej: tras actualizar el plugin
 * sin desactivar/activar) hace flush automáticamente.
 * Se guarda un flag en options para no hacer flush en cada petición.
 */
// Evitar que WordPress procese los parámetros de retorno de Stripe
// como query vars propias, lo que causaba página en blanco al recargar.
function plr_ignorar_params_stripe( $query_vars ) {
    // Stripe añade estos parámetros al redirigir de vuelta al cliente
    // El JS los gestiona en el cliente — WordPress no debe procesarlos
    $params_stripe = [
        'payment_intent',
        'payment_intent_client_secret',
        'redirect_status',
        'source',
        'source_redirect_slug',
    ];
    foreach ( $params_stripe as $param ) {
        if ( isset( $_GET[ $param ] ) ) {
            // Registrar como query var conocida para que WP no devuelva 404
            $query_vars[] = $param;
        }
    }
    return $query_vars;
}
add_filter( 'query_vars', 'plr_ignorar_params_stripe' );

function plr_verificar_rewrite_rules() {
    $rules = get_option( 'rewrite_rules' );
    if ( empty( $rules ) || ! isset( $rules['^plr-webhook/stripe/?$'] ) ) {
        flush_rewrite_rules();
    }
}
add_action( 'init', 'plr_verificar_rewrite_rules', 20 );

// ── Cron cancelación transferencias ──────────────────────────
// Limpiar caché de Google Calendar cuando cambia el estado de una reserva
add_action( 'plr_estado_cambiado', function( $reserva_id, $nuevo_estado, $estado_anterior, $reserva ) {
    if ( class_exists('PLR_Google_Calendar') && ! empty($reserva->fecha_servicio) ) {
        PLR_Google_Calendar::limpiar_cache( $reserva->fecha_servicio );
    }
}, 10, 4 );

function plr_cancelar_transferencias_expiradas() {
    global $wpdb;
    $ahora    = current_time( 'mysql' );
    $expiradas = $wpdb->get_col( $wpdb->prepare(
        "SELECT id FROM %i WHERE estado = %s AND transferencia_expira IS NOT NULL AND transferencia_expira < %s",
        PLR_TABLE_RESERVAS, PLR_DB_Reservas::ESTADO_PEND_TRANSFERENCIA, $ahora
    ) );
    foreach ( $expiradas as $id ) {
        PLR_DB_Reservas::actualizar_estado( (int) $id, PLR_DB_Reservas::ESTADO_CANCELADO );
    }
}
add_action( 'plr_cancelar_transferencias', 'plr_cancelar_transferencias_expiradas' );

function plr_activar_cron() {
    if ( ! wp_next_scheduled( 'plr_cancelar_transferencias' ) ) {
        wp_schedule_event( time(), 'hourly', 'plr_cancelar_transferencias' );
    }
}
add_action( 'wp', 'plr_activar_cron' );

// ── Menú admin ────────────────────────────────────────────────
function plr_admin_menu() {
    add_menu_page( 'Pintalimpio Reservas', 'Reservas', 'manage_options', 'plr-reservas',      'plr_page_reservas', 'dashicons-calendar-alt', 30 );
    add_submenu_page( 'plr-reservas', 'Todas las Reservas', 'Todas las Reservas', 'manage_options', 'plr-reservas',      'plr_page_reservas' );
    add_submenu_page( 'plr-reservas', 'Agenda',             '📅 Agenda',           'manage_options', 'plr-agenda',        'plr_page_agenda' );
    add_submenu_page( 'plr-reservas', 'Configuración',      'Configuración',      'manage_options', 'plr-configuracion', 'plr_page_config' );
}
add_action( 'admin_menu', 'plr_admin_menu' );

// ── Endpoint descarga PDF orden de trabajo ────────────────────
function plr_admin_descargar_pdf() {
    if ( ! isset( $_GET['plr_pdf_orden'], $_GET['reserva_id'], $_GET['_wpnonce'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'plr_pdf_orden' ) ) wp_die( 'Nonce inválido.' );

    require_once PLR_PLUGIN_DIR . 'includes/class-calculadora.php';
    require_once PLR_PLUGIN_DIR . 'includes/class-db-reservas.php';
    require_once PLR_PLUGIN_DIR . 'includes/class-analisis-ia.php';
    require_once PLR_PLUGIN_DIR . 'includes/class-pdf-orden.php';

    PLR_PDF_Orden::generar( absint( $_GET['reserva_id'] ) );
}
add_action( 'admin_init', 'plr_admin_descargar_pdf' );

function plr_page_reservas() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos' );
    if ( isset( $_POST['plr_cambiar_estado'], $_POST['plr_reserva_id'], $_POST['plr_nonce_admin'] ) ) {
        if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['plr_nonce_admin'] ) ), 'plr_admin_accion' ) ) {
            PLR_DB_Reservas::actualizar_estado( absint( $_POST['plr_reserva_id'] ), sanitize_text_field( wp_unslash( $_POST['plr_cambiar_estado'] ) ) );
        }
    }
    include PLR_PLUGIN_DIR . 'templates/admin/reservas.php';
}

function plr_page_agenda() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos' );
    require_once PLR_PLUGIN_DIR . 'includes/class-db-reservas.php';
    include PLR_PLUGIN_DIR . 'templates/admin/agenda.php';
}

function plr_page_config() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos' );

    if ( isset( $_POST['plr_guardar_config'], $_POST['plr_nonce_config'] ) &&
         wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['plr_nonce_config'] ) ), 'plr_guardar_config' ) ) {

        // Solo guardar campos de la pestaña activa — evita que hidden=0 de otras pestañas sobreescriban datos
        $tab = sanitize_text_field( wp_unslash( $_POST['plr_tab'] ?? 'general' ) );

        if ( $tab === 'general' ) {
            foreach ( ['plr_nombre_empresa','plr_color_principal','plr_telefono_empresa','plr_email_admin','plr_google_review_url'] as $c )
                if ( isset($_POST[$c]) ) update_option( $c, sanitize_text_field(wp_unslash($_POST[$c])) );
            if ( isset($_POST['plr_logo_id']) ) {
                $lid = absint($_POST['plr_logo_id']);
                update_option('plr_logo_id', $lid);
                update_option('plr_logo_url', esc_url_raw($lid > 0 ? (wp_get_attachment_url($lid) ?: '') : ''));
            }
        }

        if ( $tab === 'precios' ) {
            foreach ( ['plr_precio_hora','plr_porcentaje_deposito','plr_iva_porcentaje','plr_sede_direccion','plr_km_gratis','plr_precio_km_extra','plr_google_maps_key'] as $c )
                if ( isset($_POST[$c]) ) update_option( $c, sanitize_text_field(wp_unslash($_POST[$c])) );
        }

        if ( $tab === 'catalogo' ) {
            require_once PLR_PLUGIN_DIR . 'includes/class-calculadora.php';
            foreach ( PLR_Calculadora::get_catalogo_raw() as $cat ) {
                $cid = sanitize_key($cat['id']);
                update_option( 'plr_cat_activa_'.$cid,
                    (isset($_POST['plr_cat_activa_'.$cid]) && $_POST['plr_cat_activa_'.$cid]==='1') ? '1' : '0' );
                foreach ( $cat['tipos'] as $tipo ) {
                    $tid = sanitize_key($tipo['id']);
                    update_option( 'plr_tipo_activo_'.$tid,
                        (isset($_POST['plr_tipo_activo_'.$tid]) && $_POST['plr_tipo_activo_'.$tid]==='1') ? '1' : '0' );
                    if ( isset($_POST['plr_tipo_nombre_'.$tid]) ) update_option('plr_tipo_nombre_'.$tid, sanitize_text_field(wp_unslash($_POST['plr_tipo_nombre_'.$tid])));
                    if ( isset($_POST['plr_tipo_mult_'.$tid])   ) update_option('plr_tipo_mult_'.$tid,   (string)floatval($_POST['plr_tipo_mult_'.$tid]));
                    if ( isset($_POST['plr_tipo_desc_'.$tid])   ) update_option('plr_tipo_desc_'.$tid,   sanitize_textarea_field(wp_unslash($_POST['plr_tipo_desc_'.$tid])));
                }
            }
        }

        if ( $tab === 'estancias' ) {
            require_once PLR_PLUGIN_DIR . 'includes/class-calculadora.php';
            foreach ( ['get_estancias','get_estancias_cristales','get_estancias_mantenimiento','get_estancias_inmuebles','get_estancias_trasteros','get_estancias_garajes','get_estancias_vaciado'] as $fn ) {
                foreach ( PLR_Calculadora::$fn() as $est ) {
                    $eid = sanitize_key($est['id']);
                    update_option('plr_e_act_'.$eid, (isset($_POST['plr_e_act_'.$eid]) && $_POST['plr_e_act_'.$eid]==='1') ? '1' : '0');
                    if ( isset($_POST['plr_e_nom_'.$eid]) ) update_option('plr_e_nom_'.$eid, sanitize_text_field(wp_unslash($_POST['plr_e_nom_'.$eid])));
                    if ( isset($_POST['plr_e_pre_'.$eid]) ) update_option('plr_e_pre_'.$eid, (string)floatval($_POST['plr_e_pre_'.$eid]));
                    foreach ( $est['opciones'] ?? [] as $op ) {
                        if ( isset($op['subopciones']) ) continue;
                        $oid = sanitize_key($op['id']);
                        update_option('plr_o_act_'.$oid, (isset($_POST['plr_o_act_'.$oid]) && $_POST['plr_o_act_'.$oid]==='1') ? '1' : '0');
                        if ( isset($_POST['plr_o_nom_'.$oid]) ) update_option('plr_o_nom_'.$oid, sanitize_text_field(wp_unslash($_POST['plr_o_nom_'.$oid])));
                        if ( isset($_POST['plr_o_pre_'.$oid]) ) update_option('plr_o_pre_'.$oid, (string)floatval($_POST['plr_o_pre_'.$oid]));
                    }
                }
            }
        }

        if ( $tab === 'clausulas' ) {
            if ( isset($_POST['plr_clausula']) )
                update_option('plr_clausulas_mantenimiento', array_values(array_filter(array_map('sanitize_textarea_field', (array)wp_unslash($_POST['plr_clausula'])))));
            if ( isset($_POST['plr_clausulas_acepto_texto']) )
                update_option('plr_clausulas_acepto_texto', sanitize_text_field(wp_unslash($_POST['plr_clausulas_acepto_texto'])));
        }

        if ( $tab === 'pagos' ) {
            // Claves Stripe — pueden ser muy largas, usar sanitize_textarea_field
            $claves_stripe = ['plr_stripe_pk_test','plr_stripe_sk_test','plr_stripe_pk_live','plr_stripe_sk_live','plr_stripe_webhook_secret'];
            foreach ( $claves_stripe as $c ) {
                if ( isset($_POST[$c]) ) {
                    $val = sanitize_textarea_field( wp_unslash( $_POST[$c] ) );
                    $val = preg_replace('/\s+/', '', $val); // Eliminar espacios/saltos
                    update_option( $c, $val );
                }
            }
            // Resto de campos de pagos
            foreach ( ['plr_stripe_modo','plr_banco_titular','plr_banco_iban','plr_banco_entidad','plr_banco_concepto','plr_transferencia_horas'] as $c )
                if ( isset($_POST[$c]) ) update_option($c, sanitize_text_field(wp_unslash($_POST[$c])));
        }

        if ( $tab === 'avanzado' ) {
            foreach ( ['plr_anthropic_api_key','plr_hora_inicio','plr_hora_fin','plr_duracion_slot','plr_max_reservas_dia','plr_gcal_api_key','plr_gcal_calendar_id','plr_google_ads_id'] as $c )
                if ( isset($_POST[$c]) ) update_option($c, sanitize_text_field(wp_unslash($_POST[$c])));
            update_option('plr_gcal_activo', isset($_POST['plr_gcal_activo']) && $_POST['plr_gcal_activo']==='1' ? '1' : '0');
            update_option('plr_dias_laborables', isset($_POST['plr_dias_laborables']) ? array_map('sanitize_text_field',(array)$_POST['plr_dias_laborables']) : []);
        }
    }

    include PLR_PLUGIN_DIR . 'templates/admin/configuracion.php';
}

function plr_admin_assets( $hook ) {
    if ( ! in_array( $hook, [ 'toplevel_page_plr-reservas', 'reservas_page_plr-configuracion' ], true ) ) return;
    wp_enqueue_style(  'plr-admin', PLR_PLUGIN_URL . 'assets/css/admin.css',  [], PLR_VERSION );
    wp_enqueue_script( 'plr-admin', PLR_PLUGIN_URL . 'assets/js/admin.js', [], PLR_VERSION, true );
    // Media uploader para el logo
    if ( $hook === 'reservas_page_plr-configuracion' ) {
        wp_enqueue_media();
    }
}
add_action( 'admin_enqueue_scripts', 'plr_admin_assets' );

// ── Helper facturación ───────────────────────────────────────
function plr_sanitizar_facturacion( $raw ) {
    if ( empty( $raw ) || ! is_array( $raw ) ) return [];
    return [
        'nombre'    => sanitize_text_field( $raw['nombre']    ?? '' ),
        'nif'       => sanitize_text_field( $raw['nif']       ?? '' ),
        'cp'        => sanitize_text_field( $raw['cp']        ?? '' ),
        'direccion' => sanitize_text_field( $raw['direccion'] ?? '' ),
        'ciudad'    => sanitize_text_field( $raw['ciudad']    ?? '' ),
    ];
}

// ── Helpers globales ──────────────────────────────────────────
function plr_stripe_pk() {
    return 'live' === get_option( 'plr_stripe_modo', 'test' )
        ? (string) get_option( 'plr_stripe_pk_live', '' )
        : (string) get_option( 'plr_stripe_pk_test', '' );
}

function plr_stripe_sk() {
    return 'live' === get_option( 'plr_stripe_modo', 'test' )
        ? (string) get_option( 'plr_stripe_sk_live', '' )
        : (string) get_option( 'plr_stripe_sk_test', '' );
}

function plr_formato_precio( float $precio ) {
    return number_format( $precio, 2, ',', '.' ) . ' €';
}
