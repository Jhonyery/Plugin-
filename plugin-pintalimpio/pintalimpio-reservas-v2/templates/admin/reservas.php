<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap plr-admin">
    <h1 class="wp-heading-inline">📅 Reservas — Pintalimpio</h1>
    <hr class="wp-header-end">

    <?php
    $filtro_estado = sanitize_text_field( $_GET['estado'] ?? '' );
    $busqueda      = sanitize_text_field( $_GET['s'] ?? '' );
    $pagina        = max( 1, (int)( $_GET['paged'] ?? 1 ) );
    $por_pagina    = 20;

    $data    = PLR_DB_Reservas::get_reservas( [ 'estado' => $filtro_estado, 'busqueda' => $busqueda, 'pagina' => $pagina, 'por_pagina' => $por_pagina ] );
    $reservas = $data['reservas'];
    $total    = $data['total'];
    $stats    = PLR_DB_Reservas::get_estadisticas();
    ?>

    <!-- Stats rápidas -->
    <div class="plr-stats-bar">
        <?php foreach ( PLR_DB_Reservas::get_estados() as $slug => $label ) : $s = $stats[$slug] ?? ['count'=>0,'suma'=>0] ?>
        <div class="plr-stat-card plr-estado-<?php echo esc_attr($slug) ?>">
            <span class="plr-stat-num"><?php echo (int)$s['count'] ?></span>
            <span class="plr-stat-label"><?php echo esc_html($label) ?></span>
            <span class="plr-stat-suma"><?php echo esc_html( plr_formato_precio($s['suma']) ) ?></span>
        </div>
        <?php endforeach ?>
    </div>

    <!-- Filtros -->
    <form method="get" class="plr-filtros">
        <input type="hidden" name="page" value="plr-reservas">
        <select name="estado">
            <option value="">Todos los estados</option>
            <?php foreach ( PLR_DB_Reservas::get_estados() as $slug => $label ) : ?>
                <option value="<?php echo esc_attr($slug) ?>" <?php selected($filtro_estado, $slug) ?>><?php echo esc_html($label) ?></option>
            <?php endforeach ?>
        </select>
        <input type="search" name="s" value="<?php echo esc_attr($busqueda) ?>" placeholder="Buscar nombre o email...">
        <button type="submit" class="button">Filtrar</button>
    </form>

    <?php if ( empty( $reservas ) ) : ?>
        <p class="plr-vacio">No se encontraron reservas con los filtros aplicados.</p>
    <?php else : ?>

    <table class="wp-list-table widefat fixed striped plr-tabla">
        <thead>
            <tr>
                <th style="width:40px">#</th>
                <th>Cliente</th>
                <th>Fecha servicio</th>
                <th>Hora</th>
                <th>Total</th>
                <th>Depósito</th>
                <th>Estado</th>
                <th>Dirección / Km</th>
                <th>Acciones</th>
                <th>Orden</th>
                <th>Factura</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $reservas as $r ) : ?>
            <tr>
                <td><?php echo (int)$r->id ?></td>
                <td>
                    <strong><?php echo esc_html($r->nombre) ?></strong><br>
                    <small><?php echo esc_html($r->email) ?></small><br>
                    <small><?php echo esc_html($r->telefono) ?></small>
                </td>
                <td><?php echo esc_html( date_i18n('d/m/Y', strtotime($r->fecha_servicio)) ) ?></td>
                <td><?php echo esc_html( substr($r->hora_servicio, 0, 5) ) ?></td>
                <td><?php echo esc_html( plr_formato_precio((float)$r->total) ) ?></td>
                <td><?php echo esc_html( plr_formato_precio((float)$r->deposito) ) ?></td>
                <td>
                    <?php if ( ! empty($r->direccion) ) : ?>
                        <small><?php echo esc_html($r->direccion) ?></small><br>
                        <?php if ( $r->km_distancia > 0 ) : ?>
                            <small style="color:#718096"><?php echo esc_html($r->km_distancia) ?> km
                            <?php if ( $r->km_coste > 0 ) : ?>
                                · <strong style="color:#c53030">+<?php echo esc_html(plr_formato_precio((float)$r->km_coste)) ?></strong>
                            <?php endif ?>
                            </small>
                        <?php endif ?>
                    <?php else : ?>
                        <span style="color:#cbd5e0">—</span>
                    <?php endif ?>
                </td>
                <td>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?plr_pdf_orden=1&reserva_id=' . (int)$r->id), 'plr_pdf_orden' ) ) ?>"
                        target="_blank"
                        class="button button-small"
                        style="background:#1a7f5a;color:#fff;border-color:#1a7f5a"
                        title="Descargar orden de trabajo en PDF">
                        📋 Orden de trabajo
                    </a>
                </td>
                <td>
                    <?php
                    $fac = is_array($r->facturacion_json) ? $r->facturacion_json : [];
                    if ( ! empty($fac['nif']) ) : ?>
                        <span class="plr-badge" style="background:#dbeafe;color:#1e40af" title="<?php echo esc_attr($fac['nombre'] . ' — ' . $fac['nif'] . ' — ' . $fac['direccion'] . ', ' . $fac['cp'] . ' ' . $fac['ciudad']) ?>">
                            🧾 <?php echo esc_html($fac['nif']) ?>
                        </span>
                    <?php else : ?>
                        <span style="color:#d1d5db;font-size:12px">—</span>
                    <?php endif ?>
                </td>
                <td><span class="plr-badge plr-estado-<?php echo esc_attr($r->estado) ?>"><?php echo esc_html( PLR_DB_Reservas::get_estados()[$r->estado] ?? $r->estado ) ?></span></td>
                <td>
                    <form method="post" class="plr-form-estado">
                        <?php wp_nonce_field('plr_admin_accion','plr_nonce_admin') ?>
                        <input type="hidden" name="plr_reserva_id" value="<?php echo (int)$r->id ?>">
                        <select name="plr_cambiar_estado">
                            <?php foreach ( PLR_DB_Reservas::get_estados() as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr($slug) ?>" <?php selected($r->estado,$slug) ?>><?php echo esc_html($label) ?></option>
                            <?php endforeach ?>
                        </select>
                        <button type="submit" class="button button-small">Aplicar</button>
                        <?php if ( $r->estado === PLR_DB_Reservas::ESTADO_PEND_TRANSFERENCIA ) : ?>
                            <button type="submit" name="plr_cambiar_estado" value="<?php echo PLR_DB_Reservas::ESTADO_RESERVADO ?>"
                                class="button button-primary button-small"
                                onclick="return confirm('¿Confirmar que se recibió la transferencia?')">
                                ✅ Confirmar transferencia
                            </button>
                        <?php endif ?>
                        <?php if ( $r->estado === PLR_DB_Reservas::ESTADO_FINALIZADO ) : ?>
                            <button type="submit" name="plr_cambiar_estado" value="<?php echo PLR_DB_Reservas::ESTADO_PAGADO ?>"
                                class="button button-primary button-small"
                                style="background:#6b46c1;border-color:#6b46c1"
                                onclick="return confirm('¿Confirmar el pago final? Se enviará el email de agradecimiento y solicitud de reseña.')">
                                ⭐ Confirmar pago final
                            </button>
                        <?php endif ?>
                    </form>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>

    <!-- Paginación -->
    <?php if ( $total > $por_pagina ) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            echo paginate_links( [
                'base'      => add_query_arg( 'paged', '%#%' ),
                'format'    => '',
                'current'   => $pagina,
                'total'     => ceil( $total / $por_pagina ),
            ] );
            ?>
        </div>
    </div>
    <?php endif ?>

    <?php endif ?>
</div>
