<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Al guardar (POST) usar el tab enviado en el form
// Al navegar (GET) usar el tab de la URL
$tab_activa = '';
if ( isset($_POST['plr_guardar_config'], $_POST['plr_tab']) ) {
    $tab_activa = sanitize_text_field( wp_unslash( $_POST['plr_tab'] ) );
} elseif ( isset($_GET['tab']) ) {
    $tab_activa = sanitize_text_field( $_GET['tab'] );
}
if ( ! $tab_activa ) $tab_activa = 'general';

// Redirigir a la URL correcta después de guardar (para que el GET tenga el tab)
if ( isset($_POST['plr_guardar_config']) && ! headers_sent() ) {
    // No redirigir — WordPress ya maneja esto
}
$tabs = [
    'general'    => '🏢 Mi empresa',
    'precios'    => '💰 Precios',
    'catalogo'   => '🗂️ Catálogo',
    'estancias'  => '🏠 Estancias',
    'clausulas'  => '📋 Cláusulas',
    'pagos'      => '💳 Pagos',
    'avanzado'   => '⚙️ Avanzado',
];
?>
<div class="wrap plr-admin">
<h1>⚙️ Configuración del Plugin</h1>

<?php if ( isset($_POST['plr_guardar_config']) ) : ?>
    <div class="notice notice-success is-dismissible"><p>✅ Guardado correctamente.</p></div>
<?php endif ?>

<!-- Pestañas -->
<nav class="nav-tab-wrapper plr-nav-tabs" style="margin-bottom:0">
    <?php foreach ( $tabs as $id => $label ) : ?>
        <a href="?page=plr-configuracion&tab=<?php echo esc_attr($id) ?>"
           class="nav-tab <?php echo $tab_activa === $id ? 'nav-tab-active' : '' ?>">
            <?php echo esc_html($label) ?>
        </a>
    <?php endforeach ?>
</nav>

<form method="post" enctype="multipart/form-data" class="plr-config-form"
      action="<?php echo esc_url( admin_url('admin.php?page=plr-configuracion&tab=' . esc_attr($tab_activa)) ) ?>"
      style="background:#fff;border:1px solid #c3c4c7;border-top:none;padding:24px 28px;margin-bottom:20px">
    <?php wp_nonce_field('plr_guardar_config','plr_nonce_config') ?>
    <input type="hidden" name="plr_guardar_config" value="1">
    <input type="hidden" name="plr_tab" value="<?php echo esc_attr($tab_activa) ?>">

    <?php if ( $tab_activa === 'general' ) : ?>
    <!-- ═══════════════════════════════════════════════════════
         PESTAÑA 1 — MI EMPRESA
    ═══════════════════════════════════════════════════════ -->
    <h2 style="margin-top:0">🏢 Datos de tu empresa</h2>
    <p style="color:#646970;margin-bottom:20px">Esta información aparece en los emails y en el cotizador.</p>

    <table class="form-table">
        <tr>
            <th><label for="plr_nombre_empresa">Nombre de la empresa</label></th>
            <td>
                <input type="text" id="plr_nombre_empresa" name="plr_nombre_empresa" class="regular-text"
                    value="<?php echo esc_attr(get_option('plr_nombre_empresa','Pintalimpio')) ?>">
                <p class="description">Aparece en emails, PDF y en el cotizador.</p>
            </td>
        </tr>
        <tr>
            <th><label for="plr_telefono_empresa">Teléfono de contacto</label></th>
            <td>
                <input type="text" id="plr_telefono_empresa" name="plr_telefono_empresa" class="regular-text"
                    value="<?php echo esc_attr(get_option('plr_telefono_empresa','')) ?>">
            </td>
        </tr>
        <tr>
            <th><label for="plr_email_admin">Email del administrador</label></th>
            <td>
                <input type="email" id="plr_email_admin" name="plr_email_admin" class="regular-text"
                    value="<?php echo esc_attr(get_option('plr_email_admin', get_option('admin_email'))) ?>">
                <p class="description">Aquí recibirás las notificaciones de nuevas reservas y solicitudes de factura.</p>
            </td>
        </tr>
        <tr>
            <th><label for="plr_google_review_url">Enlace de reseña en Google</label></th>
            <td>
                <input type="url" id="plr_google_review_url" name="plr_google_review_url" class="large-text"
                    value="<?php echo esc_attr(get_option('plr_google_review_url','')) ?>"
                    placeholder="https://g.page/r/tu-negocio/review">
                <p class="description">
                    Para obtenerlo: busca tu negocio en Google → "Escribe una reseña" → copia la URL.<br>
                    Se incluye en el email de agradecimiento tras el pago final.
                </p>
            </td>
        </tr>
        <tr>
            <th><label>Logo de la empresa</label></th>
            <td>
                <?php
                $logo_id  = (int) get_option('plr_logo_id', 0);
                $logo_url = get_option('plr_logo_url', '');
                ?>
                <div id="plr-logo-preview" style="margin-bottom:10px">
                    <?php if ( $logo_url ) : ?>
                        <img src="<?php echo esc_url($logo_url) ?>" style="max-height:80px;max-width:300px;border:1px solid #ddd;border-radius:4px;padding:6px">
                    <?php else : ?>
                        <div style="width:200px;height:60px;border:2px dashed #c3c4c7;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;font-size:13px">Sin logo</div>
                    <?php endif ?>
                </div>
                <input type="hidden" id="plr_logo_id" name="plr_logo_id" value="<?php echo esc_attr($logo_id) ?>">
                <button type="button" class="button" id="plr-subir-logo">📁 Seleccionar imagen</button>
                <?php if ( $logo_url ) : ?>
                    <button type="button" class="button" id="plr-quitar-logo" style="margin-left:6px;color:#b32d2e">✕ Quitar logo</button>
                <?php endif ?>
                <p class="description">Recomendado: PNG con fondo transparente, mínimo 200px de ancho.</p>
            </td>
        </tr>
        <tr>
            <th><label for="plr_color_principal">Color principal</label></th>
            <td>
                <div style="display:flex;align-items:center;gap:12px">
                    <input type="color" id="plr_color_principal" name="plr_color_principal"
                        value="<?php echo esc_attr(get_option('plr_color_principal','#1a7f5a')) ?>"
                        style="width:50px;height:36px;padding:2px;border:1px solid #ddd;border-radius:4px;cursor:pointer">
                    <span id="plr-color-hex" style="font-family:monospace;font-size:14px;color:#444">
                        <?php echo esc_html(get_option('plr_color_principal','#1a7f5a')) ?>
                    </span>
                </div>
                <p class="description">Color principal del cotizador (botones, bordes, cabeceras). Por defecto verde Pintalimpio.</p>
                <div id="plr-color-preview" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
                    <?php
                    $colores_sugeridos = ['#1a7f5a','#2563eb','#7c3aed','#dc2626','#ea580c','#0891b2','#374151'];
                    foreach ( $colores_sugeridos as $c ) :
                    ?>
                    <button type="button" class="plr-color-sugerido" data-color="<?php echo esc_attr($c) ?>"
                        style="width:28px;height:28px;background:<?php echo esc_attr($c) ?>;border:2px solid transparent;border-radius:50%;cursor:pointer;transition:transform .15s"
                        title="<?php echo esc_attr($c) ?>"></button>
                    <?php endforeach ?>
                </div>
            </td>
        </tr>
    </table>

    <?php elseif ( $tab_activa === 'precios' ) : ?>
    <!-- ═══════════════════════════════════════════════════════
         PESTAÑA 2 — PRECIOS
    ═══════════════════════════════════════════════════════ -->
    <h2 style="margin-top:0">💰 Configuración de precios</h2>

    <div class="plr-config-seccion">
        <h3>Tarifa de servicio</h3>
        <table class="form-table">
            <tr>
                <th><label for="plr_precio_hora">Precio por hora (€)</label></th>
                <td>
                    <input type="number" id="plr_precio_hora" name="plr_precio_hora" class="small-text"
                        value="<?php echo esc_attr(get_option('plr_precio_hora',20)) ?>" min="1" step="0.50"> €/hora
                    <p class="description">Incluye mano de obra, maquinaria, productos y vaporetas.<br>
                    Precio final = horas necesarias × esta tarifa × multiplicador del servicio.</p>
                </td>
            </tr>
            <tr>
                <th><label for="plr_porcentaje_deposito">Depósito inicial (%)</label></th>
                <td>
                    <input type="number" id="plr_porcentaje_deposito" name="plr_porcentaje_deposito" class="small-text"
                        value="<?php echo esc_attr(get_option('plr_porcentaje_deposito',50)) ?>" min="1" max="100"> %
                    <p class="description">Porcentaje del total que el cliente paga al reservar. El resto al finalizar el servicio.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="plr-config-seccion">
        <h3>🧾 IVA</h3>
        <table class="form-table">
            <tr>
                <th><label for="plr_iva_porcentaje">Porcentaje de IVA (%)</label></th>
                <td>
                    <input type="number" id="plr_iva_porcentaje" name="plr_iva_porcentaje" class="small-text"
                        value="<?php echo esc_attr(get_option('plr_iva_porcentaje',21)) ?>" min="0" max="100" step="1"> %
                    <p class="description">España: 21% · Portugal: 23% · Francia: 20%<br>
                    Los precios del cotizador <strong>ya incluyen IVA</strong>. Solo se muestra el desglose al pedir factura.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="plr-config-seccion">
        <h3>🚗 Kilometraje</h3>
        <table class="form-table">
            <tr>
                <th><label for="plr_sede_direccion">Dirección de la sede</label></th>
                <td>
                    <input type="text" id="plr_sede_direccion" name="plr_sede_direccion" class="large-text"
                        value="<?php echo esc_attr(get_option('plr_sede_direccion','Calle del Planeta Venus 28, 28983 Parla, Madrid')) ?>">
                    <p class="description">Punto de partida para calcular el suplemento de desplazamiento.</p>
                </td>
            </tr>
            <tr>
                <th><label for="plr_km_gratis">Km gratuitos</label></th>
                <td>
                    <input type="number" id="plr_km_gratis" name="plr_km_gratis" class="small-text"
                        value="<?php echo esc_attr(get_option('plr_km_gratis',30)) ?>" min="0"> km
                    <p class="description">Distancia máxima sin cargo. Por defecto 30 km.</p>
                </td>
            </tr>
            <tr>
                <th><label for="plr_precio_km_extra">Precio por km extra (€)</label></th>
                <td>
                    <input type="number" id="plr_precio_km_extra" name="plr_precio_km_extra" class="small-text"
                        value="<?php echo esc_attr(get_option('plr_precio_km_extra',1)) ?>" min="0" step="0.10"> €/km
                    <p class="description">Se cobra ida y vuelta (×2) a partir del km gratuito.</p>
                </td>
            </tr>
            <tr>
                <th><label for="plr_google_maps_key">Clave API Google Maps</label></th>
                <td>
                    <input type="text" id="plr_google_maps_key" name="plr_google_maps_key" class="large-text"
                        value="<?php echo esc_attr(get_option('plr_google_maps_key','')) ?>">
                    <p class="description">Necesaria para calcular distancias automáticamente. <a href="https://developers.google.com/maps/documentation/distance-matrix" target="_blank">Obtener clave →</a></p>
                </td>
            </tr>
        </table>
    </div>

    <?php elseif ( $tab_activa === 'catalogo' ) : ?>
    <!-- ═══════════════════════════════════════════════════════
         PESTAÑA 3 — CATÁLOGO
    ═══════════════════════════════════════════════════════ -->
    <h2 style="margin-top:0">🗂️ Catálogo de servicios</h2>
    <p style="color:#646970;margin-bottom:20px">Activa, desactiva y personaliza cada tipo de servicio. Los cambios se reflejan inmediatamente en el cotizador.</p>

    <?php
    require_once PLR_PLUGIN_DIR . 'includes/class-calculadora.php';
    $catalogo = PLR_Calculadora::get_catalogo_raw(); // Raw para mostrar todos, incluyendo desactivados
    foreach ( $catalogo as $cat ) :
        $cat_activa = get_option( 'plr_cat_activa_' . $cat['id'], '1' );
    ?>
    <div class="plr-config-seccion plr-cat-seccion" style="border-left:4px solid <?php echo esc_attr(get_option('plr_color_principal','#1a7f5a')) ?>">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
            <label class="plr-toggle-admin">
                <input type="hidden" name="plr_cat_activa_<?php echo esc_attr($cat['id']) ?>" value="0">
                <input type="checkbox" name="plr_cat_activa_<?php echo esc_attr($cat['id']) ?>" value="1"
                    <?php checked($cat_activa,'1') ?> class="plr-cat-toggle"
                    data-cat="<?php echo esc_attr($cat['id']) ?>">
                <span class="plr-toggle-slider-admin"></span>
            </label>
            <h3 style="margin:0"><?php echo esc_html($cat['label']) ?></h3>
        </div>

        <div class="plr-tipos-config" id="plr-tipos-<?php echo esc_attr($cat['id']) ?>"
             style="<?php echo $cat_activa !== '1' ? 'opacity:.4;pointer-events:none' : '' ?>">
            <?php foreach ( $cat['tipos'] as $tipo ) :
                $tipo_activo = get_option( 'plr_tipo_activo_' . $tipo['id'], '1' );
                $tipo_nombre = get_option( 'plr_tipo_nombre_' . $tipo['id'], $tipo['label'] );
                $tipo_mult   = get_option( 'plr_tipo_mult_'   . $tipo['id'], '' );
                $tipo_desc   = get_option( 'plr_tipo_desc_'   . $tipo['id'], '' );
                $mults = PLR_Calculadora::get_multiplicadores_publicos();
                $mult_default = $mults[$tipo['id']] ?? 1.00;
                if ( $tipo_mult === '' ) $tipo_mult = $mult_default;
            ?>
            <div class="plr-tipo-config-fila">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                    <input type="hidden" name="plr_tipo_activo_<?php echo esc_attr($tipo['id']) ?>" value="0">
                    <input type="checkbox" name="plr_tipo_activo_<?php echo esc_attr($tipo['id']) ?>" value="1"
                        <?php checked($tipo_activo,'1') ?> style="width:16px;height:16px;accent-color:var(--plr-color,#1a7f5a)">
                    <strong style="font-size:14px"><?php echo esc_html($tipo['label']) ?></strong>
                </div>
                <table style="width:100%;border-collapse:collapse">
                    <tr>
                        <td style="padding:4px 8px 4px 0;width:160px;font-size:13px;color:#646970">Nombre visible:</td>
                        <td><input type="text" name="plr_tipo_nombre_<?php echo esc_attr($tipo['id']) ?>"
                            value="<?php echo esc_attr($tipo_nombre) ?>" class="regular-text" style="font-size:13px"></td>
                    </tr>
                    <tr>
                        <td style="padding:4px 8px 4px 0;font-size:13px;color:#646970">Multiplicador precio:</td>
                        <td>
                            <input type="number" name="plr_tipo_mult_<?php echo esc_attr($tipo['id']) ?>"
                                value="<?php echo esc_attr($tipo_mult) ?>" step="0.01" min="0.1" max="5" class="small-text" style="font-size:13px">
                            <span style="font-size:12px;color:#999;margin-left:6px">
                                (<?php echo number_format(($tipo_mult-1)*100,0) ?>%
                                <?php echo $tipo_mult >= 1 ? 'sobre precio base' : 'descuento' ?>)
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:4px 8px 4px 0;font-size:13px;color:#646970;vertical-align:top;padding-top:8px">Descripción:</td>
                        <td><textarea name="plr_tipo_desc_<?php echo esc_attr($tipo['id']) ?>"
                            rows="2" class="large-text" style="font-size:13px"><?php echo esc_textarea($tipo_desc) ?></textarea>
                            <p style="font-size:11px;color:#999;margin:2px 0 0">Texto que ve el cliente al seleccionar este servicio. Déjalo vacío para usar el texto por defecto.</p>
                        </td>
                    </tr>
                </table>
            </div>
            <?php endforeach ?>
        </div>
    </div>
    <?php endforeach ?>

    <?php elseif ( $tab_activa === 'estancias' ) : ?>
    <!-- ═══════════════════════════════════════════════════════
         PESTAÑA — ESTANCIAS
    ═══════════════════════════════════════════════════════ -->
    <h2 style="margin-top:0">🏠 Estancias y elementos</h2>
    <p style="color:#646970;margin-bottom:20px">
        Activa o desactiva estancias, cambia los nombres y ajusta los precios. Los cambios se aplican inmediatamente al cotizador.
    </p>

    <?php
    require_once PLR_PLUGIN_DIR . 'includes/class-calculadora.php';

    // Grupos de estancias a mostrar
    // IMPORTANTE: Las keys del grupo NO deben coincidir con IDs de categorías
    // para evitar colisión en wp_options (plr_estancia_activa_casas != plr_cat_activa_casas)
    // Usamos funciones RAW para mostrar TODAS las estancias en el admin,
    // incluyendo las desactivadas (para poder reactivarlas)
    $grupos_estancias = [
        'est_casas'    => [ 'label' => '🏠 Casas (limpieza a fondo)',        'fn' => 'get_estancias_raw' ],
        'est_cristales'=> [ 'label' => '🪟 Limpieza de cristales',            'fn' => 'get_estancias_cristales_raw' ],
        'est_mant'     => [ 'label' => '🧹 Mantenimiento por horas',          'fn' => 'get_estancias_mantenimiento_raw' ],
        'est_inmuebles'=> [ 'label' => '🏢 Inmuebles (oficinas, locales)',     'fn' => 'get_estancias_inmuebles_raw' ],
        'est_trasteros'=> [ 'label' => '📦 Trasteros',                        'fn' => 'get_estancias_trasteros_raw' ],
        'est_garajes'  => [ 'label' => '🚗 Garajes',                          'fn' => 'get_estancias_garajes_raw' ],
        'est_vaciado'  => [ 'label' => '🚛 Vaciado de inmuebles',             'fn' => 'get_estancias_vaciado_raw' ],
    ];

    foreach ( $grupos_estancias as $grupo_id => $grupo ) :
        $estancias = PLR_Calculadora::{$grupo['fn']}();
    ?>
    <div class="plr-config-seccion" style="margin-bottom:24px">
        <h3 style="margin-top:0;padding-bottom:10px;border-bottom:1px solid #e5e7eb">
            <?php echo esc_html($grupo['label']) ?>
        </h3>

        <?php foreach ( $estancias as $est ) :
            $est_id      = sanitize_key($est['id']);
            $activa      = get_option( 'plr_e_act_'  . $est_id, '1' );
            $nombre      = get_option( 'plr_e_nom_'  . $est_id, $est['label'] );
            $precio      = get_option( 'plr_e_pre_'  . $est_id, $est['precio_unit'] ?? $est['precio_base'] ?? 0 );
        ?>
        <div class="plr-est-config-fila" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;margin-bottom:10px;opacity:<?php echo $activa==='1'?'1':'0.5' ?>">

            <!-- Cabecera estancia -->
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                <input type="hidden" name="plr_e_act_<?php echo esc_attr($est_id) ?>" value="0">
                <input type="checkbox" name="plr_e_act_<?php echo esc_attr($est_id) ?>" value="1"
                    <?php checked($activa,'1') ?>
                    style="width:16px;height:16px;accent-color:#1a7f5a;flex-shrink:0">
                <span style="font-size:18px"><?php echo esc_html($est['icono'] ?? '🔹') ?></span>
                <strong style="font-size:14px;color:#1a202c"><?php echo esc_html($est['label']) ?></strong>
                <span style="font-size:11px;background:#e5e7eb;padding:2px 8px;border-radius:10px;color:#6b7280">
                    <?php echo esc_html($est['tipo']) ?>
                </span>
            </div>

            <!-- Campos editables -->
            <table style="width:100%;border-collapse:collapse">
                <tr>
                    <td style="width:180px;padding:4px 8px 4px 28px;font-size:13px;color:#646970">Nombre visible:</td>
                    <td>
                        <input type="text" name="plr_e_nom_<?php echo esc_attr($est_id) ?>"
                            value="<?php echo esc_attr($nombre) ?>"
                            class="regular-text" style="font-size:13px">
                    </td>
                </tr>
                <?php if ( $est['tipo'] === 'cantidad' && isset($est['precio_unit']) ) : ?>
                <tr>
                    <td style="padding:4px 8px 4px 28px;font-size:13px;color:#646970">Precio por unidad (€):</td>
                    <td>
                        <input type="number" name="plr_e_pre_<?php echo esc_attr($est_id) ?>"
                            value="<?php echo esc_attr($precio) ?>"
                            min="0" step="0.50" class="small-text" style="font-size:13px"> €
                    </td>
                </tr>
                <?php elseif ( $est['tipo'] === 'toggle' && isset($est['precio_base']) ) : ?>
                <tr>
                    <td style="padding:4px 8px 4px 28px;font-size:13px;color:#646970">Precio base (€):</td>
                    <td>
                        <input type="number" name="plr_e_pre_<?php echo esc_attr($est_id) ?>"
                            value="<?php echo esc_attr($precio) ?>"
                            min="0" step="0.50" class="small-text" style="font-size:13px"> €
                        <?php if ( $precio == 0 ) : ?>
                            <span style="font-size:11px;color:#9ca3af;margin-left:6px">Informativo (no suma al precio)</span>
                        <?php endif ?>
                    </td>
                </tr>
                <?php elseif ( $est['tipo'] === 'cantidad_m2' && isset($est['precio_unit']) ) : ?>
                <tr>
                    <td style="padding:4px 8px 4px 28px;font-size:13px;color:#646970">Precio por m² (€):</td>
                    <td>
                        <input type="number" name="plr_e_pre_<?php echo esc_attr($est_id) ?>"
                            value="<?php echo esc_attr($precio) ?>"
                            min="0" step="0.50" class="small-text" style="font-size:13px"> €/m²
                    </td>
                </tr>
                <?php endif ?>
            </table>

            <!-- Opciones de la estancia -->
            <?php if ( ! empty($est['opciones']) ) : ?>
            <div style="margin-top:10px;padding-top:10px;border-top:1px dashed #e5e7eb">
                <p style="font-size:12px;font-weight:600;color:#6b7280;margin:0 0 8px 28px;text-transform:uppercase;letter-spacing:.5px">Opciones</p>
                <?php foreach ( $est['opciones'] as $op ) :
                    if ( isset($op['subopciones']) ) continue; // sofá/alfombras — gestión especial
                    $op_id     = sanitize_key($op['id']);
                    $op_activa = get_option( 'plr_o_act_' . $op_id, '1' );
                    $op_nombre = get_option( 'plr_o_nom_' . $op_id, $op['label'] );
                ?>
                <div style="display:flex;align-items:center;gap:10px;padding:4px 4px 4px 28px">
                    <input type="hidden" name="plr_o_act_<?php echo esc_attr($op_id) ?>" value="0">
                    <input type="checkbox" name="plr_o_act_<?php echo esc_attr($op_id) ?>" value="1"
                        <?php checked($op_activa,'1') ?>
                        style="width:14px;height:14px;accent-color:#1a7f5a;flex-shrink:0">
                    <input type="text" name="plr_o_nom_<?php echo esc_attr($op_id) ?>"
                        value="<?php echo esc_attr($op_nombre) ?>"
                        style="font-size:13px;width:300px;border:1px solid #e5e7eb;border-radius:4px;padding:4px 8px">
                    <?php if ( isset($op['precio_fijo_ud']) ) : ?>
                        <span style="font-size:12px;color:#6b7280;margin-right:4px">+</span>
                        <input type="number" name="plr_o_pre_<?php echo esc_attr($op_id) ?>"
                            value="<?php echo esc_attr(get_option('plr_o_pre_'.$op_id, $op['precio_fijo_ud'])) ?>"
                            min="0" step="0.50"
                            style="width:65px;font-size:13px;border:1px solid #e5e7eb;border-radius:4px;padding:3px 6px;text-align:center">
                        <span style="font-size:12px;color:#9ca3af">€/ud</span>
                    <?php elseif ( isset($op['mod']) && $op['mod'] > 0 ) : ?>
                        <span style="font-size:12px;color:#9ca3af">
                            +<?php echo $op['mod'] > 1 ? $op['mod'].'€' : ($op['mod']*100).'%' ?>
                        </span>
                    <?php endif ?>
                </div>
                <?php endforeach ?>
            </div>
            <?php endif ?>

        </div>
        <?php endforeach ?>
    </div>
    <?php endforeach ?>

    <?php elseif ( $tab_activa === 'clausulas' ) : ?>
    <!-- ═══════════════════════════════════════════════════════
         PESTAÑA 4 — CLÁUSULAS
    ═══════════════════════════════════════════════════════ -->
    <h2 style="margin-top:0">📋 Cláusulas del servicio de mantenimiento</h2>
    <p style="color:#646970;margin-bottom:20px">Estas cláusulas aparecen en el cotizador cuando el cliente selecciona "Limpieza por horas — Mantenimiento". El cliente debe aceptarlas antes de continuar.</p>

    <?php
    $clausulas_default = [
        'El personal de limpieza no realiza limpieza de cristales exteriores ni trabajos en altura.',
        'En caso de malos tratos o comportamiento irrespetuoso hacia el empleado/a, la empresa retirará al trabajador de inmediato sin derecho a devolución.',
        'Los productos de limpieza son manipulados exclusivamente por el personal de la empresa. El cliente no debe solicitar el uso de productos propios.',
        'El trabajo se realizará a un ritmo adecuado y saludable. Solo se llevarán a cabo las tareas que humanamente puedan realizarse dentro de la jornada contratada.',
        'El empleado/a tiene derecho a 15 minutos de descanso dentro de su jornada laboral.',
        'Si el cliente tiene alguna observación o incidencia, deberá comunicarlo a la empresa y no llamar la atención al empleado/a directamente.',
        'El empleado/a no está obligado a completar todas las tareas si no dispone de tiempo suficiente dentro de su jornada. No se realizan horas extras.',
        'Al aceptar estas condiciones, el cliente confirma su conformidad con todas las cláusulas de este contrato temporal de servicio.',
    ];
    $clausulas_guardadas = get_option('plr_clausulas_mantenimiento', $clausulas_default);
    if ( ! is_array($clausulas_guardadas) ) $clausulas_guardadas = $clausulas_default;
    ?>

    <div id="plr-clausulas-editor">
        <?php foreach ( $clausulas_guardadas as $i => $clausula ) : ?>
        <div class="plr-clausula-fila" style="display:flex;gap:8px;margin-bottom:10px;align-items:flex-start">
            <span style="background:#e5e7eb;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;margin-top:8px"><?php echo $i+1 ?></span>
            <textarea name="plr_clausula[]" rows="2" class="large-text" style="font-size:13px"><?php echo esc_textarea($clausula) ?></textarea>
            <button type="button" class="button plr-quitar-clausula" style="flex-shrink:0;margin-top:4px;color:#b32d2e">✕</button>
        </div>
        <?php endforeach ?>
    </div>

    <button type="button" class="button button-secondary" id="plr-nueva-clausula" style="margin-top:6px">
        ＋ Añadir cláusula
    </button>

    <div class="plr-config-seccion" style="margin-top:20px">
        <h3>Texto del checkbox de aceptación</h3>
        <input type="text" name="plr_clausulas_acepto_texto" class="large-text"
            value="<?php echo esc_attr(get_option('plr_clausulas_acepto_texto','He leído y acepto las condiciones del servicio de limpieza por horas')) ?>">
    </div>

    <?php elseif ( $tab_activa === 'pagos' ) : ?>
    <!-- ═══════════════════════════════════════════════════════
         PESTAÑA 5 — PAGOS
    ═══════════════════════════════════════════════════════ -->
    <h2 style="margin-top:0">💳 Métodos de pago</h2>

    <div class="plr-config-seccion">
        <h3>Stripe (tarjeta y Bizum)</h3>
        <table class="form-table">
            <tr>
                <th><label for="plr_stripe_modo">Modo</label></th>
                <td>
                    <select id="plr_stripe_modo" name="plr_stripe_modo">
                        <option value="test" <?php selected(get_option('plr_stripe_modo','test'),'test') ?>>🧪 Test (pruebas)</option>
                        <option value="live" <?php selected(get_option('plr_stripe_modo','test'),'live') ?>>✅ Live (producción)</option>
                    </select>
                    <p class="description">Usa "Test" mientras configuras. Cambia a "Live" cuando estés listo para cobrar de verdad.</p>
                </td>
            </tr>
            <tr>
                <th><label>Claves de Test</label></th>
                <td>
                    <p style="margin-bottom:6px"><span style="font-size:12px;color:#646970">Clave pública (pk_test_...)</span><br>
                    <input type="text" name="plr_stripe_pk_test" class="large-text"
                        value="<?php echo esc_attr(get_option('plr_stripe_pk_test','')) ?>" placeholder="pk_test_..."></p>
                    <p><span style="font-size:12px;color:#646970">Clave secreta (sk_test_...)</span><br>
                    <input type="password" name="plr_stripe_sk_test" class="large-text"
                        value="<?php echo esc_attr(get_option('plr_stripe_sk_test','')) ?>" placeholder="sk_test_..."></p>
                </td>
            </tr>
            <tr>
                <th><label>Claves de Producción</label></th>
                <td>
                    <p style="margin-bottom:6px"><span style="font-size:12px;color:#646970">Clave pública (pk_live_...)</span><br>
                    <input type="text" name="plr_stripe_pk_live" class="large-text"
                        value="<?php echo esc_attr(get_option('plr_stripe_pk_live','')) ?>" placeholder="pk_live_..."></p>
                    <p><span style="font-size:12px;color:#646970">Clave secreta (sk_live_...)</span><br>
                    <input type="password" name="plr_stripe_sk_live" class="large-text"
                        value="<?php echo esc_attr(get_option('plr_stripe_sk_live','')) ?>" placeholder="sk_live_..."></p>
                </td>
            </tr>
            <tr>
                <th><label for="plr_stripe_webhook_secret">Webhook Secret</label></th>
                <td>
                    <input type="password" id="plr_stripe_webhook_secret" name="plr_stripe_webhook_secret" class="large-text"
                        value="<?php echo esc_attr(get_option('plr_stripe_webhook_secret','')) ?>" placeholder="whsec_...">
                    <p class="description">
                        URL de tu webhook: <code><?php echo esc_html(home_url('/plr-webhook/stripe/')) ?></code><br>
                        <a href="https://dashboard.stripe.com/webhooks" target="_blank">Configurar en Stripe →</a>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <div class="plr-config-seccion">
        <h3>🏦 Transferencia bancaria</h3>
        <table class="form-table">
            <tr>
                <th><label for="plr_banco_titular">Titular de la cuenta</label></th>
                <td><input type="text" id="plr_banco_titular" name="plr_banco_titular" class="regular-text"
                    value="<?php echo esc_attr(get_option('plr_banco_titular','')) ?>"></td>
            </tr>
            <tr>
                <th><label for="plr_banco_entidad">Entidad bancaria</label></th>
                <td><input type="text" id="plr_banco_entidad" name="plr_banco_entidad" class="regular-text"
                    value="<?php echo esc_attr(get_option('plr_banco_entidad','')) ?>"></td>
            </tr>
            <tr>
                <th><label for="plr_banco_iban">IBAN</label></th>
                <td><input type="text" id="plr_banco_iban" name="plr_banco_iban" class="regular-text"
                    value="<?php echo esc_attr(get_option('plr_banco_iban','')) ?>" placeholder="ES00 0000 0000 0000 0000 0000"></td>
            </tr>
            <tr>
                <th><label for="plr_banco_concepto">Concepto base</label></th>
                <td>
                    <input type="text" id="plr_banco_concepto" name="plr_banco_concepto" class="regular-text"
                        value="<?php echo esc_attr(get_option('plr_banco_concepto','Reserva limpieza')) ?>">
                    <p class="description">Se añade el número de reserva automáticamente. Ej: "Reserva limpieza #00042"</p>
                </td>
            </tr>
            <tr>
                <th><label for="plr_transferencia_horas">Horas límite para pagar</label></th>
                <td>
                    <input type="number" id="plr_transferencia_horas" name="plr_transferencia_horas" class="small-text"
                        value="<?php echo esc_attr(get_option('plr_transferencia_horas',24)) ?>" min="1" max="168"> horas
                    <p class="description">Si no se recibe la transferencia en este tiempo, la reserva se cancela automáticamente.</p>
                </td>
            </tr>
        </table>
    </div>

    <?php elseif ( $tab_activa === 'avanzado' ) : ?>
    <!-- ═══════════════════════════════════════════════════════
         PESTAÑA 6 — AVANZADO
    ═══════════════════════════════════════════════════════ -->
    <h2 style="margin-top:0">⚙️ Configuración avanzada</h2>

    <div class="plr-config-seccion">
        <h3>🤖 Análisis de imágenes con IA</h3>
        <table class="form-table">
            <tr>
                <th><label for="plr_anthropic_api_key">Clave API Anthropic</label></th>
                <td>
                    <input type="password" id="plr_anthropic_api_key" name="plr_anthropic_api_key" class="large-text"
                        value="<?php echo esc_attr(get_option('plr_anthropic_api_key','')) ?>" placeholder="sk-ant-...">
                    <p class="description">Permite analizar las fotos que sube el cliente para detectar el grado de suciedad automáticamente. <a href="https://console.anthropic.com" target="_blank">Obtener clave →</a></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="plr-config-seccion">
        <h3>📅 Disponibilidad</h3>
        <table class="form-table">
            <tr>
                <th><label>Días laborables</label></th>
                <td>
                    <?php
                    $dias = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
                    $dias_activos = get_option('plr_dias_laborables', ['lunes','martes','miercoles','jueves','viernes']);
                    if ( ! is_array($dias_activos) ) $dias_activos = explode(',', $dias_activos);
                    foreach ( $dias as $dia ) :
                    ?>
                    <label style="display:inline-flex;align-items:center;gap:5px;margin-right:14px;font-size:14px">
                        <input type="checkbox" name="plr_dias_laborables[]" value="<?php echo esc_attr($dia) ?>"
                            <?php checked(in_array($dia,$dias_activos,true)) ?>>
                        <?php echo esc_html(ucfirst($dia)) ?>
                    </label>
                    <?php endforeach ?>
                </td>
            </tr>
            <tr>
                <th><label>Horario</label></th>
                <td>
                    <label style="font-size:14px">Inicio:
                        <input type="time" name="plr_hora_inicio" class="small-text"
                            value="<?php echo esc_attr(get_option('plr_hora_inicio','08:00')) ?>">
                    </label>
                    &nbsp;&nbsp;
                    <label style="font-size:14px">Fin:
                        <input type="time" name="plr_hora_fin" class="small-text"
                            value="<?php echo esc_attr(get_option('plr_hora_fin','18:00')) ?>">
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="plr_duracion_slot">Duración de cada slot</label></th>
                <td>
                    <select id="plr_duracion_slot" name="plr_duracion_slot">
                        <?php foreach ( [15,30,60,90,120] as $min ) : ?>
                        <option value="<?php echo $min ?>" <?php selected(get_option('plr_duracion_slot',60),$min) ?>>
                            <?php echo $min < 60 ? $min.' min' : ($min/60).'h' ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="plr_max_reservas_dia">Máx. reservas por día</label></th>
                <td>
                    <input type="number" id="plr_max_reservas_dia" name="plr_max_reservas_dia" class="small-text"
                        value="<?php echo esc_attr(get_option('plr_max_reservas_dia',5)) ?>" min="1" max="50">
                </td>
            </tr>
        </table>
    </div>

    <?php endif ?>

    <?php if ( $tab_activa === 'avanzado' ) : ?>
    <div class="plr-config-seccion" style="border-left:4px solid #4285f4">
        <h3 style="color:#4285f4">📅 Google Calendar — Bloqueo de fechas</h3>
        <p style="color:#646970;margin-bottom:16px">
            Conecta tu Google Calendar para bloquear automáticamente los horarios que ya tienes ocupados.
            Los clientes no podrán reservar en esas horas.
        </p>

        <table class="form-table">
            <tr>
                <th>Activar sincronización</th>
                <td>
                    <label style="display:flex;align-items:center;gap:8px;font-size:14px">
                        <input type="checkbox" name="plr_gcal_activo" value="1"
                            <?php checked(get_option('plr_gcal_activo','0'),'1') ?>>
                        Bloquear slots usando Google Calendar
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="plr_gcal_api_key">API Key de Google</label></th>
                <td>
                    <input type="text" id="plr_gcal_api_key" name="plr_gcal_api_key"
                        class="large-text" value="<?php echo esc_attr(get_option('plr_gcal_api_key','')) ?>"
                        placeholder="AIzaSy...">
                    <p class="description">
                        <strong>Cómo obtenerla:</strong><br>
                        1. Ve a <a href="https://console.cloud.google.com" target="_blank">console.cloud.google.com</a><br>
                        2. Crea un proyecto → Activa <strong>Google Calendar API</strong><br>
                        3. Credenciales → Crear credencial → <strong>Clave de API</strong><br>
                        4. Restringe la clave a "Google Calendar API"
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="plr_gcal_calendar_id">ID del Calendario</label></th>
                <td>
                    <input type="text" id="plr_gcal_calendar_id" name="plr_gcal_calendar_id"
                        class="large-text" value="<?php echo esc_attr(get_option('plr_gcal_calendar_id','')) ?>"
                        placeholder="tunegocio@gmail.com o xxxxx@group.calendar.google.com">
                    <p class="description">
                        <strong>Cómo obtenerlo:</strong><br>
                        1. Abre <a href="https://calendar.google.com" target="_blank">Google Calendar</a><br>
                        2. Click en los 3 puntos de tu calendario → <strong>Configuración</strong><br>
                        3. Desplázate hasta "ID del calendario" y cópialo<br>
                        4. En <strong>Permisos de acceso</strong> activa <strong>"Hacer disponible para todos"</strong>
                    </p>
                </td>
            </tr>
            <tr>
                <th>Verificar conexión</th>
                <td>
                    <button type="button" class="button" id="plr-verificar-gcal">
                        🔌 Probar conexión
                    </button>
                    <span id="plr-gcal-resultado" style="margin-left:10px;font-size:13px"></span>
                    <p class="description">Guarda los cambios antes de verificar.</p>
                </td>
            </tr>
        </table>
    </div>
    <?php endif ?>

    <?php if ( $tab_activa === 'avanzado' ) : ?>
    <div class="plr-config-seccion" style="border-left:4px solid #4285f4">
        <h3 style="color:#4285f4">📊 Google Ads — Seguimiento de conversiones</h3>
        <p style="color:#646970;margin-bottom:16px;font-size:13px">
            Introduce tu ID de conversión de Google Ads para disparar el evento cuando un cliente complete la reserva.
            Formato: <code>AW-XXXXXXXXXX/YYYYYYYYYYYYY</code>
        </p>
        <table class="form-table">
            <tr>
                <th><label for="plr_google_ads_id">ID de conversión</label></th>
                <td>
                    <input type="text" id="plr_google_ads_id" name="plr_google_ads_id"
                        class="regular-text" value="<?php echo esc_attr(get_option('plr_google_ads_id','')) ?>"
                        placeholder="AW-XXXXXXXXXX/YYYYYYYYYYYYY">
                    <p class="description">
                        Encuéntralo en Google Ads → Herramientas → Medición → Conversiones → tu conversión → Etiqueta de evento.
                    </p>
                </td>
            </tr>
        </table>
    </div>
    <?php endif ?>

    <p class="submit" style="padding:16px 0 0;margin:0;border-top:1px solid #e5e7eb">
        <button type="submit" class="button button-primary button-large">
            💾 Guardar cambios
        </button>
    </p>
</form>

<script>
jQuery(function($) {
    $('#plr-verificar-gcal').on('click', function() {
        var btn = $(this);
        var res = $('#plr-gcal-resultado');
        btn.prop('disabled', true).text('Verificando...');
        res.text('').css('color','#646970');
        $.get('<?php echo esc_url(rest_url('plr/v1/verificar-gcal')) ?>', {
            _wpnonce: '<?php echo wp_create_nonce('wp_rest') ?>'
        }, function(data) {
            res.text(data.mensaje).css('color', data.ok ? '#1a7f5a' : '#b32d2e');
        }).fail(function(xhr) {
            var msg = xhr.responseJSON ? xhr.responseJSON.mensaje : 'Error de conexión';
            res.text('❌ ' + msg).css('color','#b32d2e');
        }).always(function() {
            btn.prop('disabled', false).text('🔌 Probar conexión');
        });
    });
});
</script>
</div>

<style>
.plr-config-seccion { background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:20px 22px;margin-bottom:20px }
.plr-config-seccion h3 { margin-top:0;font-size:15px;color:#1a202c }
.plr-tipo-config-fila { background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:12px 14px;margin-bottom:10px }
.plr-cat-seccion { padding-left:18px !important }
.plr-clausula-fila textarea { resize:vertical }
.plr-toggle-admin { position:relative;display:inline-block;width:40px;height:22px }
.plr-toggle-admin input { opacity:0;width:0;height:0 }
.plr-toggle-slider-admin { position:absolute;cursor:pointer;inset:0;background:#ccc;border-radius:22px;transition:.3s }
.plr-toggle-slider-admin:before { content:"";position:absolute;width:16px;height:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s }
input:checked + .plr-toggle-slider-admin { background:#1a7f5a }
input:checked + .plr-toggle-slider-admin:before { transform:translateX(18px) }
</style>

<script>
(function() {
    // Color picker
    const colorInput = document.getElementById('plr_color_principal');
    const colorHex   = document.getElementById('plr-color-hex');
    if ( colorInput ) {
        colorInput.addEventListener('input', function() {
            if ( colorHex ) colorHex.textContent = this.value;
        });
    }
    document.querySelectorAll('.plr-color-sugerido').forEach( btn => {
        btn.addEventListener('click', function() {
            const c = this.dataset.color;
            if ( colorInput ) { colorInput.value = c; if(colorHex) colorHex.textContent = c; }
        });
    });

    // Logo media uploader — se inicializa con jQuery ready para garantizar que wp.media esté cargado
    jQuery(function($) {
        $('#plr-subir-logo').on('click', function(e) {
            e.preventDefault();
            var frame = wp.media({
                title: 'Seleccionar logo',
                button: { text: 'Usar este logo' },
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function() {
                var att = frame.state().get('selection').first().toJSON();
                $('#plr_logo_id').val(att.id);
                $('#plr-logo-preview').html('<img src="'+att.url+'" style="max-height:80px;max-width:300px;border:1px solid #ddd;border-radius:4px;padding:6px">');
            });
            frame.open();
        });

        $('#plr-quitar-logo').on('click', function(e) {
            e.preventDefault();
            $('#plr_logo_id').val('0');
            $('#plr-logo-preview').html('<div style="width:200px;height:60px;border:2px dashed #c3c4c7;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;font-size:13px">Sin logo</div>');
        });
    });

    // Toggle categorías
    document.querySelectorAll('.plr-cat-toggle').forEach( toggle => {
        toggle.addEventListener('change', function() {
            const bloque = document.getElementById('plr-tipos-' + this.dataset.cat);
            if ( bloque ) bloque.style.opacity = this.checked ? '1' : '0.4';
            if ( bloque ) bloque.style.pointerEvents = this.checked ? '' : 'none';
        });
    });

    // Cláusulas — añadir y quitar
    const btnNuevaClausula = document.getElementById('plr-nueva-clausula');
    if ( btnNuevaClausula ) {
        btnNuevaClausula.addEventListener('click', function() {
            const editor = document.getElementById('plr-clausulas-editor');
            const n = editor.querySelectorAll('.plr-clausula-fila').length + 1;
            const div = document.createElement('div');
            div.className = 'plr-clausula-fila';
            div.style.cssText = 'display:flex;gap:8px;margin-bottom:10px;align-items:flex-start';
            div.innerHTML = '<span style="background:#e5e7eb;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;margin-top:8px">'+n+'</span><textarea name="plr_clausula[]" rows="2" class="large-text" style="font-size:13px"></textarea><button type="button" class="button plr-quitar-clausula" style="flex-shrink:0;margin-top:4px;color:#b32d2e">✕</button>';
            editor.appendChild(div);
            div.querySelector('.plr-quitar-clausula').addEventListener('click', function() { div.remove(); });
        });
    }
    document.querySelectorAll('.plr-quitar-clausula').forEach( btn => {
        btn.addEventListener('click', function() { this.closest('.plr-clausula-fila').remove(); });
    });
})();
</script>
