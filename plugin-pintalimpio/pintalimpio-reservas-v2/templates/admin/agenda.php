<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Semana actual o la navegada
$semana_offset = (int) ( $_GET['semana'] ?? 0 );
$hoy           = new DateTime();
$inicio_semana = clone $hoy;
$inicio_semana->modify('monday this week')->modify( "{$semana_offset} week" );
$fin_semana    = clone $inicio_semana;
$fin_semana->modify('+6 days');

$max_dia     = (int) get_option( 'plr_max_reservas_dia', 5 );
$hora_inicio = get_option( 'plr_hora_inicio', '09:00' );
$hora_fin    = get_option( 'plr_hora_fin', '11:00' );
$slots_base  = PLR_DB_Reservas::get_todos_los_slots();

$dias_semana = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
$dias_laborables = get_option('plr_dias_laborables', ['lunes','martes','miercoles','jueves','viernes']);
$dias_map = ['lunes'=>0,'martes'=>1,'miercoles'=>2,'jueves'=>3,'viernes'=>4,'sabado'=>5,'domingo'=>6];

// Construir los 7 días de la semana
$dias = [];
for ( $i = 0; $i < 7; $i++ ) {
    $dia = clone $inicio_semana;
    $dia->modify( "+{$i} days" );
    $dias[] = $dia;
}

// Obtener todas las reservas de la semana
global $wpdb;
$bloqueantes = [ PLR_DB_Reservas::ESTADO_PENDIENTE, PLR_DB_Reservas::ESTADO_RESERVADO, PLR_DB_Reservas::ESTADO_FINALIZADO ];
$ph = implode(',', array_fill(0, count($bloqueantes), '%s'));
$reservas_semana = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, nombre, fecha_servicio, TIME_FORMAT(hora_servicio,'%%H:%%i') as hora, estado, tipo_label, total
         FROM %i
         WHERE fecha_servicio BETWEEN %s AND %s AND estado IN ({$ph})
         ORDER BY fecha_servicio, hora_servicio",
        array_merge([PLR_TABLE_RESERVAS, $inicio_semana->format('Y-m-d'), $fin_semana->format('Y-m-d')], $bloqueantes)
    )
);

// Indexar reservas por fecha y hora
$agenda = [];
foreach ( $reservas_semana as $r ) {
    $agenda[$r->fecha_servicio][$r->hora][] = $r;
}
?>

<div class="wrap plr-admin">
    <h1>📅 Agenda de reservas</h1>

    <!-- Navegación de semanas -->
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:14px 20px">
        <a href="?page=plr-agenda&semana=<?php echo $semana_offset - 1 ?>" class="button">← Semana anterior</a>
        <div style="flex:1;text-align:center">
            <strong style="font-size:16px">
                <?php echo $inicio_semana->format('d/m/Y') ?> — <?php echo $fin_semana->format('d/m/Y') ?>
            </strong>
            <?php if ( $semana_offset === 0 ) : ?>
                <span style="background:#1a7f5a;color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;margin-left:8px">Esta semana</span>
            <?php endif ?>
        </div>
        <a href="?page=plr-agenda&semana=<?php echo $semana_offset + 1 ?>" class="button">Semana siguiente →</a>
        <?php if ( $semana_offset !== 0 ) : ?>
            <a href="?page=plr-agenda" class="button button-primary">Hoy</a>
        <?php endif ?>
    </div>

<!-- ═══ PANEL DE BLOQUEO DE FECHAS ═══ -->
<div style="background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:20px 24px;margin-top:20px">
    <h2 style="margin-top:0;font-size:16px">🔒 Bloquear fechas</h2>
    <p style="color:#646970;margin-bottom:16px;font-size:13px">
        Bloquea días completos para que los clientes no puedan reservar. Útil para vacaciones, festivos o cualquier día no disponible.
    </p>

    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:20px">
        <div>
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Fecha a bloquear</label>
            <input type="date" id="plr-bloqueo-fecha" min="<?php echo date('Y-m-d') ?>"
                style="padding:8px 12px;border:1px solid #c3c4c7;border-radius:4px;font-size:14px">
        </div>
        <div>
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Motivo (opcional)</label>
            <input type="text" id="plr-bloqueo-motivo" placeholder="Ej: Festivo, vacaciones..."
                style="padding:8px 12px;border:1px solid #c3c4c7;border-radius:4px;font-size:14px;width:220px">
        </div>
        <button type="button" id="plr-btn-bloquear" class="button button-primary">🔒 Bloquear fecha</button>
        <span id="plr-bloqueo-msg" style="font-size:13px"></span>
    </div>

    <!-- Lista de fechas bloqueadas -->
    <h3 style="font-size:14px;margin-bottom:10px">Fechas bloqueadas actualmente:</h3>
    <div id="plr-lista-bloqueos">
        <?php
        $bloqueadas = PLR_DB_Reservas::get_fechas_bloqueadas();
        if ( empty($bloqueadas) ) : ?>
            <p style="color:#a0aec0;font-style:italic;font-size:13px" id="plr-sin-bloqueos">No hay fechas bloqueadas.</p>
        <?php else : foreach ( $bloqueadas as $b ) :
            $fecha_fmt = date('d/m/Y', strtotime($b->fecha));
        ?>
        <div class="plr-bloqueo-fila" id="bloqueo-<?php echo esc_attr($b->fecha) ?>"
             style="display:flex;align-items:center;gap:12px;padding:8px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;margin-bottom:6px">
            <span style="font-weight:700;color:#dc2626">🔒 <?php echo esc_html($fecha_fmt) ?></span>
            <?php if ( $b->motivo ) : ?>
                <span style="color:#6b7280;font-size:13px">— <?php echo esc_html($b->motivo) ?></span>
            <?php endif ?>
            <button type="button" class="button plr-btn-desbloquear" data-fecha="<?php echo esc_attr($b->fecha) ?>"
                style="margin-left:auto;color:#dc2626;border-color:#fecaca">✕ Desbloquear</button>
        </div>
        <?php endforeach; endif ?>
    </div>
</div>

<script>
jQuery(function($) {
    const nonce = '<?php echo wp_create_nonce("wp_rest") ?>';
    const base  = '<?php echo esc_url(rest_url("plr/v1")) ?>';

    // Bloquear fecha
    $('#plr-btn-bloquear').on('click', function() {
        const fecha  = $('#plr-bloqueo-fecha').val();
        const motivo = $('#plr-bloqueo-motivo').val();
        const msg    = $('#plr-bloqueo-msg');
        if ( ! fecha ) { msg.text('⚠️ Selecciona una fecha').css('color','#e53e3e'); return; }

        $.post({ url: base+'/bloquear-fecha', data: JSON.stringify({fecha,motivo}),
            contentType:'application/json', beforeSend: h => h.setRequestHeader('X-WP-Nonce',nonce)
        }).done(function(r) {
            if ( ! r.ok ) return;
            msg.text('✅ Fecha bloqueada').css('color','#1a7f5a');
            $('#plr-sin-bloqueos').remove();
            const d = new Date(fecha+'T12:00:00');
            const fmt = ('0'+d.getDate()).slice(-2)+'/'+('0'+(d.getMonth()+1)).slice(-2)+'/'+d.getFullYear();
            const html = `<div class="plr-bloqueo-fila" id="bloqueo-${fecha}"
                style="display:flex;align-items:center;gap:12px;padding:8px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;margin-bottom:6px">
                <span style="font-weight:700;color:#dc2626">🔒 ${fmt}</span>
                ${motivo?`<span style="color:#6b7280;font-size:13px">— ${motivo}</span>`:''}
                <button type="button" class="button plr-btn-desbloquear" data-fecha="${fecha}"
                    style="margin-left:auto;color:#dc2626;border-color:#fecaca">✕ Desbloquear</button>
            </div>`;
            $('#plr-lista-bloqueos').append(html);
            $('#plr-bloqueo-fecha').val('');
            $('#plr-bloqueo-motivo').val('');
            setTimeout(()=>msg.text(''), 3000);
        });
    });

    // Desbloquear fecha (delegación de eventos)
    $(document).on('click','.plr-btn-desbloquear', function() {
        const fecha = $(this).data('fecha');
        const fila  = $(this).closest('.plr-bloqueo-fila');
        $.post({ url: base+'/desbloquear-fecha', data: JSON.stringify({fecha}),
            contentType:'application/json', beforeSend: h => h.setRequestHeader('X-WP-Nonce',nonce)
        }).done(function(r) {
            if ( r.ok ) {
                fila.fadeOut(300, function(){ $(this).remove(); });
                if ( ! $('.plr-bloqueo-fila').length )
                    $('#plr-lista-bloqueos').append('<p style="color:#a0aec0;font-style:italic;font-size:13px" id="plr-sin-bloqueos">No hay fechas bloqueadas.</p>');
            }
        });
    });
});
</script>

    <!-- Grid de la semana -->
    <div style="display:grid;grid-template-columns:80px repeat(7,1fr);gap:2px;background:#e5e7eb;border-radius:8px;overflow:hidden">

        <!-- Cabecera días -->
        <div style="background:#f9fafb;padding:10px 6px;text-align:center;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase">Hora</div>
        <?php foreach ( $dias as $i => $dia ) :
            $fecha_str   = $dia->format('Y-m-d');
            $es_hoy      = $dia->format('Y-m-d') === $hoy->format('Y-m-d');
            $dia_semana  = strtolower( strftime('%A', $dia->getTimestamp()) );
            // compatibilidad PHP8
            $nombres_es  = ['Monday'=>'lunes','Tuesday'=>'martes','Wednesday'=>'miercoles','Thursday'=>'jueves','Friday'=>'viernes','Saturday'=>'sabado','Sunday'=>'domingo'];
            $dia_key     = $nombres_es[$dia->format('l')] ?? '';
            $laborable   = in_array($dia_key, (array)$dias_laborables, true);
            $n_reservas  = count($agenda[$fecha_str] ?? []);
            $color_cab   = $es_hoy ? '#1a7f5a' : ( $laborable ? '#fff' : '#f3f4f6' );
            $color_texto = $es_hoy ? '#fff' : '#1a202c';
        ?>
        <div style="background:<?php echo $color_cab ?>;padding:10px 6px;text-align:center">
            <div style="font-size:12px;font-weight:700;color:<?php echo $color_texto ?>"><?php echo $dias_semana[$i] ?></div>
            <div style="font-size:18px;font-weight:800;color:<?php echo $color_texto ?>"><?php echo $dia->format('d') ?></div>
            <?php if ( $laborable ) : ?>
            <div style="font-size:10px;color:<?php echo $es_hoy?'rgba(255,255,255,.8)':'#6b7280' ?>">
                <?php echo $n_reservas ?>/<?php echo $max_dia ?>
            </div>
            <?php endif ?>
        </div>
        <?php endforeach ?>

        <!-- Filas de slots -->
        <?php foreach ( $slots_base as $slot ) : ?>
        <div style="background:#f9fafb;padding:8px 6px;text-align:center;font-size:12px;font-weight:600;color:#6b7280;border-top:1px solid #e5e7eb">
            <?php echo esc_html($slot) ?>
        </div>
        <?php foreach ( $dias as $dia ) :
            $fecha_str  = $dia->format('Y-m-d');
            $nombres_es = ['Monday'=>'lunes','Tuesday'=>'martes','Wednesday'=>'miercoles','Thursday'=>'jueves','Friday'=>'viernes','Saturday'=>'sabado','Sunday'=>'domingo'];
            $dia_key    = $nombres_es[$dia->format('l')] ?? '';
            $laborable  = in_array($dia_key, (array)$dias_laborables, true);
            $reservas_slot = $agenda[$fecha_str][$slot] ?? [];
            $pasado     = $dia->format('Y-m-d') < $hoy->format('Y-m-d');
        ?>
        <div style="background:<?php echo $pasado?'#f9fafb':( $laborable?'#fff':'#f3f4f6') ?>;padding:6px;min-height:60px;border-top:1px solid #e5e7eb;position:relative">
            <?php if ( ! $laborable ) : ?>
                <div style="text-align:center;font-size:10px;color:#d1d5db;padding-top:16px">—</div>
            <?php elseif ( ! empty($reservas_slot) ) : foreach ( $reservas_slot as $res ) :
                $colores = [
                    'pendiente'  => ['bg'=>'#fef3c7','border'=>'#f59e0b','txt'=>'#78350f'],
                    'reservado'  => ['bg'=>'#d1fae5','border'=>'#10b981','txt'=>'#065f46'],
                    'finalizado' => ['bg'=>'#dbeafe','border'=>'#3b82f6','txt'=>'#1e40af'],
                ];
                $c = $colores[$res->estado] ?? ['bg'=>'#f3f4f6','border'=>'#9ca3af','txt'=>'#374151'];
            ?>
            <a href="?page=plr-reservas&id=<?php echo $res->id ?>"
               style="display:block;background:<?php echo $c['bg'] ?>;border-left:3px solid <?php echo $c['border'] ?>;border-radius:4px;padding:4px 6px;margin-bottom:3px;text-decoration:none">
                <div style="font-size:11px;font-weight:700;color:<?php echo $c['txt'] ?>;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?php echo esc_html($res->nombre) ?>
                </div>
                <div style="font-size:10px;color:<?php echo $c['txt'] ?>;opacity:.8">
                    <?php echo esc_html($res->tipo_label) ?>
                </div>
            </a>
            <?php endforeach; elseif ( ! $pasado ) : ?>
                <div style="text-align:center;padding-top:14px">
                    <span style="font-size:10px;color:#10b981">✓ Libre</span>
                </div>
            <?php endif ?>
        </div>
        <?php endforeach ?>
        <?php endforeach ?>

    </div>

    <!-- Leyenda -->
    <div style="display:flex;gap:16px;margin-top:16px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:6px;font-size:13px">
            <span style="width:14px;height:14px;background:#fef3c7;border-left:3px solid #f59e0b;display:inline-block;border-radius:2px"></span> Pendiente
        </div>
        <div style="display:flex;align-items:center;gap:6px;font-size:13px">
            <span style="width:14px;height:14px;background:#d1fae5;border-left:3px solid #10b981;display:inline-block;border-radius:2px"></span> Reservado
        </div>
        <div style="display:flex;align-items:center;gap:6px;font-size:13px">
            <span style="width:14px;height:14px;background:#dbeafe;border-left:3px solid #3b82f6;display:inline-block;border-radius:2px"></span> Finalizado
        </div>
        <div style="margin-left:auto;font-size:13px;color:#6b7280">
            Máx. <?php echo $max_dia ?> reservas/día ·
            Horario: <?php echo esc_html($hora_inicio) ?> - <?php echo esc_html($hora_fin) ?>
            · <a href="?page=plr-configuracion&tab=avanzado">Cambiar →</a>
        </div>
    </div>
</div>
