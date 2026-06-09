<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instrucciones de pago — Pintalimpio</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #f0f4f3; font-family: Arial, Helvetica, sans-serif; }
  .wrap { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,.10); }
  .header { background: #2c5282; padding: 36px 30px; text-align: center; color: #fff; }
  .header .icono { font-size: 48px; margin-bottom: 10px; }
  .header h1 { font-size: 24px; font-weight: 700; }
  .body { padding: 32px 30px; }
  .saludo { font-size: 17px; color: #222; margin-bottom: 14px; }
  .saludo strong { color: #2c5282; }
  .intro { color: #555; font-size: 15px; line-height: 1.6; margin-bottom: 20px; }
  .importe-box { background: #2c5282; color: #fff; border-radius: 8px; padding: 18px 24px; text-align: center; margin-bottom: 24px; }
  .importe-label { font-size: 14px; opacity: .85; margin-bottom: 6px; }
  .importe-valor { font-size: 32px; font-weight: 700; }
  .banco { background: #ebf8ff; border: 1px solid #90cdf4; border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
  .banco-fila { display: flex; justify-content: space-between; align-items: center; padding: 12px 18px; border-bottom: 1px solid #bee3f8; font-size: 15px; gap: 12px; }
  .banco-fila:last-child { border: none; }
  .banco-fila .label { color: #4a5568; white-space: nowrap; }
  .banco-fila .valor { font-weight: 700; color: #1a365d; text-align: right; word-break: break-all; }
  .concepto-fila { background: #ebf4ff; }
  .concepto-fila .valor { color: #2b6cb0; font-size: 16px; }
  .aviso-tiempo { background: #fff5f5; border: 1px solid #fc8181; border-radius: 6px; padding: 14px 16px; font-size: 14px; color: #c53030; margin-bottom: 20px; line-height: 1.5; }
  .cierre { font-size: 14px; color: #718096; line-height: 1.6; }
  .footer { background: #f0f4f3; padding: 18px 30px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e2e8f0; }
  .footer a { color: #2c5282; text-decoration: none; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <div class="icono">🏦</div>
    <h1>Instrucciones para tu reserva</h1>
  </div>

  <div class="body">
    <p class="saludo">Hola, <strong><?php echo esc_html( $nombre ) ?></strong>.</p>
    <p class="intro">Tu reserva del <strong><?php echo esc_html( $fecha ) ?></strong> a las <strong><?php echo esc_html( $hora ) ?></strong> está casi lista. Para confirmarla realiza la transferencia con los siguientes datos:</p>

    <div class="importe-box">
      <div class="importe-label">Importe a transferir</div>
      <div class="importe-valor"><?php echo esc_html( $deposito ) ?></div>
    </div>

    <div class="banco">
      <div class="banco-fila">
        <span class="label">Titular</span>
        <span class="valor"><?php echo esc_html( $titular ) ?></span>
      </div>
      <div class="banco-fila">
        <span class="label">Entidad</span>
        <span class="valor"><?php echo esc_html( $entidad ) ?></span>
      </div>
      <div class="banco-fila">
        <span class="label">IBAN</span>
        <span class="valor"><?php echo esc_html( $iban ) ?></span>
      </div>
      <div class="banco-fila concepto-fila">
        <span class="label">⚠️ Concepto <strong>(obligatorio)</strong></span>
        <span class="valor"><?php echo esc_html( $concepto ) ?></span>
      </div>
    </div>

    <div class="aviso-tiempo">
      ⏰ <strong>Importante:</strong> Tienes <strong><?php echo esc_html( $horas ) ?> horas</strong> para realizar la transferencia. Si no la recibimos en ese plazo, la reserva se cancelará automáticamente.
    </div>

    <p class="cierre">Una vez confirmemos la recepción te enviaremos un email con la confirmación definitiva. ¡Gracias por elegir Pintalimpio! 🧹</p>
  </div>

  <div class="footer">
    <strong>Pintalimpio</strong> · Servicios profesionales de limpieza<br>
    <a href="https://pintalimpio.com">pintalimpio.com</a>
  </div>

</div>
</body>
</html>
