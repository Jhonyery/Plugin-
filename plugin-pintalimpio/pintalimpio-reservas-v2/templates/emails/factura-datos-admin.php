<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Solicitud de factura</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #f0f4f3; font-family: Arial, Helvetica, sans-serif; }
  .wrap { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,.10); }
  .header { background: #2c5282; padding: 28px 30px; color: #fff; display: flex; align-items: center; gap: 14px; }
  .header .icono { font-size: 36px; }
  .header h1 { font-size: 20px; font-weight: 700; }
  .header p { font-size: 13px; opacity: .8; margin-top: 3px; }
  .body { padding: 28px 30px; }
  .seccion { margin-bottom: 22px; }
  .seccion-titulo { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; }
  .dato-fila { display: flex; padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
  .dato-fila:last-child { border: none; }
  .dato-label { color: #6b7280; width: 160px; flex-shrink: 0; }
  .dato-valor { font-weight: 600; color: #1a202c; }
  .fiscal-box { background: #eff6ff; border: 2px solid #3b82f6; border-radius: 8px; padding: 16px 20px; }
  .fiscal-box .dato-fila { border-bottom-color: #dbeafe; }
  .nif-valor { font-size: 18px; font-weight: 700; color: #1d4ed8; letter-spacing: 1px; }
  .btn { display: inline-block; background: #2c5282; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 700; margin-top: 20px; }
  .aviso { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 14px; font-size: 13px; color: #78350f; border-radius: 0 6px 6px 0; margin-top: 16px; }
  .footer { background: #f0f4f3; padding: 16px 30px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <div class="icono">🧾</div>
    <div>
      <h1>Solicitud de factura</h1>
      <p>Reserva #<?php echo esc_html( str_pad($reserva_id, 5, '0', STR_PAD_LEFT) ) ?> — <?php echo esc_html($nombre_cliente) ?></p>
    </div>
  </div>

  <div class="body">

    <div class="seccion">
      <div class="seccion-titulo">Datos del cliente</div>
      <div class="dato-fila"><span class="dato-label">Cliente</span><span class="dato-valor"><?php echo esc_html($nombre_cliente) ?></span></div>
      <div class="dato-fila"><span class="dato-label">Email</span><span class="dato-valor"><?php echo esc_html($email_cliente) ?></span></div>
      <div class="dato-fila"><span class="dato-label">Fecha del servicio</span><span class="dato-valor"><?php echo esc_html($fecha_servicio) ?></span></div>
    </div>

    <div class="seccion">
      <div class="seccion-titulo">Datos fiscales para la factura</div>
      <div class="fiscal-box">
        <div class="dato-fila"><span class="dato-label">Nombre / Razón social</span><span class="dato-valor"><?php echo esc_html($fac_nombre) ?></span></div>
        <div class="dato-fila"><span class="dato-label">NIF / CIF</span><span class="dato-valor nif-valor"><?php echo esc_html($fac_nif) ?></span></div>
        <div class="dato-fila"><span class="dato-label">Dirección fiscal</span><span class="dato-valor"><?php echo esc_html($fac_direccion) ?></span></div>
        <div class="dato-fila"><span class="dato-label">Código postal</span><span class="dato-valor"><?php echo esc_html($fac_cp) ?></span></div>
        <div class="dato-fila"><span class="dato-label">Ciudad</span><span class="dato-valor"><?php echo esc_html($fac_ciudad) ?></span></div>
      </div>
    </div>

    <div class="aviso">
      ⚠️ <strong>Acción requerida:</strong> El cliente ha solicitado factura. Emítela desde tu programa de facturación una vez el servicio esté completamente pagado.
    </div>

    <div class="seccion">
      <div class="seccion-titulo">Desglose fiscal (IVA <?php echo esc_html($iva_pct) ?>%)</div>
      <div class="fiscal-box">
        <div class="dato-fila"><span class="dato-label">Base imponible</span><span class="dato-valor"><?php echo esc_html($base_imp) ?></span></div>
        <div class="dato-fila"><span class="dato-label">IVA (<?php echo esc_html($iva_pct) ?>%)</span><span class="dato-valor"><?php echo esc_html($cuota_iva) ?></span></div>
        <div class="dato-fila" style="border-top:2px solid #3b82f6;margin-top:4px;padding-top:8px"><span class="dato-label" style="font-weight:700">Total con IVA</span><span class="dato-valor nif-valor"><?php echo esc_html($total) ?></span></div>
      </div>
    </div>

    <a href="<?php echo esc_url($admin_url) ?>" class="btn">Ver reserva en el panel →</a>

  </div>

  <div class="footer">
    <strong>Pintalimpio</strong> · Sistema interno de gestión
  </div>

</div>
</body>
</html>
