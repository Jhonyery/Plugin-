<?php
/**
 * Generador de PDF — Orden de Trabajo
 * Pintalimpio Reservas
 *
 * Genera un PDF de orden de trabajo para el jefe de equipo.
 * Sin precios ni datos de pago — solo información operativa.
 *
 * Usa HTML puro + dompdf (instalado vía composer o incluido).
 * Si dompdf no está disponible, genera un HTML descargable como fallback.
 *
 * @author  Jhon Bastidas Rodrigues
 * @link    https://controlhorariowp.com/
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PLR_PDF_Orden {

    /**
     * Genera y sirve el PDF de orden de trabajo.
     * Se llama directamente desde el admin — termina con exit.
     *
     * @param int $reserva_id
     */
    public static function generar( int $reserva_id ) {
        $reserva = PLR_DB_Reservas::get_reserva( $reserva_id );
        if ( ! $reserva ) wp_die( 'Reserva no encontrada.' );

        $html = self::generar_html( $reserva );

        // Intentar con dompdf si está disponible
        $dompdf_path = PLR_PLUGIN_DIR . 'vendor/dompdf/dompdf/src/Dompdf.php';
        if ( file_exists( $dompdf_path ) ) {
            self::servir_con_dompdf( $html, $reserva_id );
        } else {
            // Fallback: servir como HTML imprimible
            self::servir_como_html( $html, $reserva_id );
        }
        exit;
    }

    /**
     * Genera el HTML de la orden de trabajo.
     */
    private static function generar_html( object $reserva ) {
        $estancias_cfg = PLR_Calculadora::get_estancias();
        $estancias_map = array_column( $estancias_cfg, null, 'id' );

        $grados_label = [
            'normal'    => [ 'texto' => 'Suciedad normal',    'color' => '#d97706' ],
            'avanzada'  => [ 'texto' => 'Suciedad avanzada',  'color' => '#ea580c' ],
            'muy_sucia' => [ 'texto' => 'Suciedad extrema',   'color' => '#dc2626' ],
        ];

        $fecha      = self::fmt_fecha( $reserva->fecha_servicio );
        $hora       = substr( trim( $reserva->hora_servicio ), 0, 5 );
        $estancias  = is_array( $reserva->extras ) ? $reserva->extras : [];
        $num_orden  = str_pad( $reserva->id, 5, '0', STR_PAD_LEFT );
        $emitido    = date_i18n( 'd/m/Y H:i' );

        // Obtener fotos agrupadas por estancia
        $fotos = PLR_Analisis_IA::get_fotos_reserva( $reserva->id, 'antes' );

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, sans-serif; font-size: 13px; color: #1a202c; background: #fff; }

  /* Cabecera */
  .header { background: #1a7f5a; color: #fff; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; }
  .header-logo { font-size: 22px; font-weight: 700; letter-spacing: -0.5px; }
  .header-logo span { font-size: 12px; font-weight: 400; opacity: .8; display: block; margin-top: 2px; }
  .header-orden { text-align: right; }
  .header-orden .num { font-size: 20px; font-weight: 700; }
  .header-orden .fecha-emision { font-size: 11px; opacity: .8; margin-top: 3px; }

  /* Banda de servicio */
  .banda-servicio { background: #f0fdf4; border-bottom: 2px solid #1a7f5a; padding: 14px 30px; display: flex; gap: 40px; align-items: center; }
  .banda-item { display: flex; align-items: center; gap: 8px; }
  .banda-item .icono { font-size: 20px; }
  .banda-item .label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; }
  .banda-item .valor { font-size: 15px; font-weight: 700; color: #1a7f5a; }

  /* Secciones */
  .seccion { padding: 16px 30px; border-bottom: 1px solid #e5e7eb; }
  .seccion-titulo { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 10px; }

  /* Datos cliente */
  .cliente-grid { display: flex; gap: 30px; }
  .cliente-campo { flex: 1; }
  .cliente-campo .campo-label { font-size: 11px; color: #9ca3af; margin-bottom: 2px; }
  .cliente-campo .campo-valor { font-size: 14px; font-weight: 600; color: #1a202c; }

  /* Tipo de limpieza */
  .tipo-badge { display: inline-block; background: #1a7f5a; color: #fff; padding: 5px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; margin-right: 10px; }
  .m2-badge { display: inline-block; background: #e5e7eb; color: #374151; padding: 5px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; }

  /* Estancias */
  .estancia { margin-bottom: 14px; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; }
  .estancia-header { background: #f9fafb; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; }
  .estancia-nombre { font-size: 14px; font-weight: 700; color: #1a202c; }
  .estancia-grado { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px; color: #fff; }
  .estancia-body { padding: 10px 14px; }
  .opciones-lista { list-style: none; display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
  .opciones-lista li { background: #eff6ff; color: #1d4ed8; padding: 3px 10px; border-radius: 4px; font-size: 12px; }
  .fotos-wrap { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
  .foto-thumb { width: 80px; height: 80px; object-fit: cover; border-radius: 4px; border: 1px solid #e5e7eb; }
  .no-opciones { color: #9ca3af; font-size: 12px; font-style: italic; }
  .cant-badge { background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600; margin-left: 8px; }

  /* Notas */
  .notas-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px; padding: 12px 14px; font-size: 13px; color: #78350f; }

  /* Footer */
  .footer { padding: 14px 30px; text-align: center; font-size: 11px; color: #9ca3af; border-top: 2px solid #e5e7eb; margin-top: 10px; }

  /* Print */
  @media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body>

<!-- CABECERA -->
<div class="header">
  <div class="header-logo">
    🧹 Pintalimpio
    <span>Servicios profesionales de limpieza</span>
  </div>
  <div class="header-orden">
    <div style="font-size:11px;opacity:.8;margin-bottom:4px;">ORDEN DE TRABAJO</div>
    <div class="num">#<?php echo esc_html( $num_orden ) ?></div>
    <div class="fecha-emision">Emitida: <?php echo esc_html( $emitido ) ?></div>
  </div>
</div>

<!-- BANDA FECHA / HORA / DIRECCIÓN -->
<div class="banda-servicio">
  <div class="banda-item">
    <span class="icono">📅</span>
    <div>
      <div class="label">Fecha</div>
      <div class="valor"><?php echo esc_html( $fecha ) ?></div>
    </div>
  </div>
  <div class="banda-item">
    <span class="icono">🕐</span>
    <div>
      <div class="label">Hora de llegada</div>
      <div class="valor"><?php echo esc_html( $hora ) ?></div>
    </div>
  </div>
  <div class="banda-item">
    <span class="icono">📍</span>
    <div>
      <div class="label">Dirección del servicio</div>
      <div class="valor"><?php echo esc_html( $reserva->direccion ?: '—' ) ?></div>
    </div>
  </div>
</div>

<!-- DATOS DEL CLIENTE -->
<div class="seccion">
  <div class="seccion-titulo">Datos de contacto</div>
  <div class="cliente-grid">
    <div class="cliente-campo">
      <div class="campo-label">Nombre</div>
      <div class="campo-valor"><?php echo esc_html( $reserva->nombre ) ?></div>
    </div>
    <div class="cliente-campo">
      <div class="campo-label">Teléfono</div>
      <div class="campo-valor"><?php echo esc_html( $reserva->telefono ?: '—' ) ?></div>
    </div>
    <div class="cliente-campo">
      <div class="campo-label">Email</div>
      <div class="campo-valor"><?php echo esc_html( $reserva->email ) ?></div>
    </div>
  </div>
</div>

<!-- TIPO DE LIMPIEZA -->
<div class="seccion">
  <div class="seccion-titulo">Servicio</div>
  <span class="tipo-badge"><?php echo esc_html( $reserva->tipo_label ?: $reserva->tipo_id ) ?></span>
  <span class="m2-badge"><?php echo esc_html( $reserva->m2 ) ?> m²</span>
</div>

<!-- DESGLOSE DE TRABAJOS -->
<div class="seccion">
  <div class="seccion-titulo">Trabajos a realizar por estancia</div>

  <?php
  // Leer estancias del campo estancias_json
  $estancias_sel = [];
  if ( ! empty( $reserva->estancias_json ) ) {
      if ( is_string( $reserva->estancias_json ) ) {
          $estancias_sel = json_decode( $reserva->estancias_json, true ) ?: [];
      } elseif ( is_array( $reserva->estancias_json ) ) {
          $estancias_sel = $reserva->estancias_json;
      }
  }

  if ( empty( $estancias_sel ) ) :
  ?>
    <!-- Sin estancias: mostrar el tipo de servicio como tarea general -->
    <div class="estancia">
      <div class="estancia-header">
        <span class="estancia-nombre">🏠 Servicio completo — <?php echo esc_html( $reserva->tipo_label ?: $reserva->tipo_id ) ?></span>
        <span class="estancia-grado" style="background:#1a7f5a">Limpieza general</span>
      </div>
      <div class="estancia-body">
        <ul class="opciones-lista">
          <li>✓ Limpieza completa de la vivienda (<?php echo esc_html($reserva->m2) ?> m²)</li>
          <li>✓ Todas las estancias incluidas</li>
        </ul>
        <p style="font-size:11px;color:#9ca3af;margin-top:8px">
          El cliente no especificó estancias individuales. Aplicar limpieza estándar completa.
        </p>
      </div>
    </div>
  <?php else : ?>

    <?php foreach ( $estancias_sel as $est_id => $datos ) :
        if ( ! is_array( $datos ) ) continue;

        $cfg_est    = $estancias_map[ $est_id ] ?? null;
        if ( ! $cfg_est ) continue;

        // Determinar si está activo
        $activo = false;
        if ( isset( $datos['activo'] ) && $datos['activo'] ) $activo = true;
        if ( isset( $datos['cantidad'] ) && (int)$datos['cantidad'] > 0 ) $activo = true;
        if ( isset( $datos['m2'] ) && (float)$datos['m2'] > 0 ) $activo = true;
        if ( ! $activo ) continue;

        $grado     = $datos['grado_suciedad'] ?? 'normal';
        $grado_cfg = $grados_label[ $grado ] ?? $grados_label['normal'];
        $opciones  = $datos['opciones'] ?? [];
        $op_map    = array_column( $cfg_est['opciones'] ?? [], null, 'id' );
        $fotos_est = $fotos[ $est_id ] ?? [];
    ?>
    <div class="estancia">
      <div class="estancia-header">
        <span class="estancia-nombre">
          <?php echo esc_html( $cfg_est['icono'] ?? '' ) ?>
          <?php echo esc_html( $cfg_est['label'] ) ?>
          <?php if ( isset( $datos['cantidad'] ) && (int)$datos['cantidad'] > 0 ) : ?>
            <span class="cant-badge"><?php echo (int)$datos['cantidad'] ?> uds.</span>
          <?php elseif ( isset( $datos['m2'] ) && (float)$datos['m2'] > 0 ) : ?>
            <span class="cant-badge"><?php echo (float)$datos['m2'] ?> m²</span>
          <?php endif ?>
        </span>
        <span class="estancia-grado" style="background:<?php echo esc_attr( $grado_cfg['color'] ) ?>">
          <?php echo esc_html( $grado_cfg['texto'] ) ?>
        </span>
      </div>

      <div class="estancia-body">
        <?php if ( ! empty( $opciones ) ) : ?>
          <ul class="opciones-lista">
            <?php foreach ( $opciones as $op_id ) :
                $op = $op_map[ $op_id ] ?? null;
                if ( ! $op ) continue;
            ?>
              <li>✓ <?php echo esc_html( $op['label'] ) ?></li>
            <?php endforeach ?>
          </ul>
        <?php else : ?>
          <p class="no-opciones">Limpieza estándar de la estancia</p>
        <?php endif ?>

        <?php if ( ! empty( $fotos_est ) ) : ?>
          <div class="fotos-wrap">
            <?php foreach ( $fotos_est as $att_id ) :
                $url = wp_get_attachment_image_url( $att_id, 'thumbnail' );
                if ( $url ) :
            ?>
              <img src="<?php echo esc_url( $url ) ?>" class="foto-thumb" alt="Foto estado previo">
            <?php endif; endforeach ?>
          </div>
        <?php endif ?>
      </div>
    </div>
    <?php endforeach ?>

  <?php endif ?>
</div>

<?php if ( ! empty( $reserva->notas_admin ) ) : ?>
<!-- NOTAS INTERNAS -->
<div class="seccion">
  <div class="seccion-titulo">Notas internas</div>
  <div class="notas-box"><?php echo esc_html( $reserva->notas_admin ) ?></div>
</div>
<?php endif ?>

<!-- FOOTER -->
<div class="footer">
  Orden de trabajo generada por el sistema Pintalimpio Reservas ·
  <strong>CONFIDENCIAL — USO INTERNO</strong>
</div>

</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Sirve el HTML como página imprimible con botón de imprimir/guardar PDF.
     * Fallback cuando dompdf no está disponible.
     * El navegador puede guardar como PDF desde Ctrl+P → Guardar como PDF.
     */
    private static function servir_como_html( string $html, int $reserva_id ) {
        $num = str_pad( $reserva_id, 5, '0', STR_PAD_LEFT );

        // Inyectar barra de herramientas de impresión
        $barra = '
        <div class="no-print" style="background:#1a7f5a;color:#fff;padding:12px 30px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:999">
            <span style="font-weight:700">Orden de Trabajo #' . esc_html($num) . '</span>
            <div style="display:flex;gap:10px">
                <button onclick="window.print()" style="background:#fff;color:#1a7f5a;border:none;padding:8px 20px;border-radius:6px;font-weight:700;cursor:pointer;font-size:14px">
                    🖨 Imprimir / Guardar PDF
                </button>
                <button onclick="window.close()" style="background:rgba(255,255,255,.2);color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:14px">
                    ✕ Cerrar
                </button>
            </div>
        </div>';

        // Insertar barra después del <body>
        $html = str_replace( '<body>', '<body>' . $barra, $html );

        header( 'Content-Type: text/html; charset=UTF-8' );
        header( 'Content-Disposition: inline; filename="orden-trabajo-' . $num . '.html"' );
        echo $html; // phpcs:ignore
    }

    /**
     * Formatea fecha MySQL (YYYY-MM-DD) a DD/MM/YYYY.
     */
    private static function fmt_fecha( string $fecha ) {
        $fecha = trim( $fecha );
        if ( empty( $fecha ) || $fecha === '0000-00-00' ) return '—';
        // Intentar formato MySQL estándar
        $dt = DateTime::createFromFormat( 'Y-m-d', $fecha );
        if ( $dt && $dt->format('Y') > 0 ) return $dt->format( 'd/m/Y' );
        // Intentar con timestamp
        $ts = strtotime( $fecha );
        if ( $ts && $ts > 0 ) return date( 'd/m/Y', $ts );
        return $fecha;
    }
}
