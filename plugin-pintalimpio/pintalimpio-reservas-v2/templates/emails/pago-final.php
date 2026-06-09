<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tu servicio ha finalizado — Pintalimpio</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #f0f4f3; font-family: Arial, Helvetica, sans-serif; }
  .wrap { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,.10); }
  .header { background: #1a7f5a; padding: 36px 30px; text-align: center; color: #fff; }
  .header .icono { font-size: 52px; margin-bottom: 10px; }
  .header h1 { font-size: 24px; font-weight: 700; }
  .header p { font-size: 15px; opacity: .85; margin-top: 6px; }
  .body { padding: 32px 30px; }
  .saludo { font-size: 17px; color: #222; margin-bottom: 14px; }
  .saludo strong { color: #1a7f5a; }
  .intro { color: #555; font-size: 15px; line-height: 1.6; margin-bottom: 24px; }
  .importe-box { background: #f6faf8; border: 2px solid #1a7f5a; border-radius: 8px; padding: 20px 24px; text-align: center; margin-bottom: 24px; }
  .importe-label { font-size: 14px; color: #718096; margin-bottom: 6px; }
  .importe-valor { font-size: 36px; font-weight: 700; color: #1a7f5a; }
  .importe-sub { font-size: 13px; color: #718096; margin-top: 4px; }
  .btn-wrap { text-align: center; margin: 28px 0; }
  .btn { display: inline-block; background: #1a7f5a; color: #fff; padding: 16px 40px; border-radius: 8px; text-decoration: none; font-size: 17px; font-weight: 700; letter-spacing: -.2px; }
  .btn:hover { background: #15664a; }
  .seguridad { font-size: 13px; color: #a0aec0; text-align: center; margin-top: -8px; margin-bottom: 24px; }
  .separador { border: none; border-top: 1px solid #e2e8f0; margin: 24px 0; }
  .cierre { font-size: 14px; color: #718096; line-height: 1.6; }
  .footer { background: #f0f4f3; padding: 18px 30px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e2e8f0; }
  .footer a { color: #1a7f5a; text-decoration: none; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <div class="icono">🧹✨</div>
    <h1>¡Tu servicio ha finalizado!</h1>
    <p>Servicio del <?php echo esc_html( $fecha_servicio ) ?></p>
  </div>

  <div class="body">
    <p class="saludo">Hola, <strong><?php echo esc_html( $nombre ) ?></strong>.</p>
    <p class="intro">
      Nuestro equipo ha terminado el trabajo y esperamos que el resultado haya superado tus expectativas. 
      Ha sido un placer trabajar para ti.
    </p>
    <p class="intro">
      Para completar el servicio, queda pendiente el pago del importe restante:
    </p>

    <div class="importe-box">
      <div class="importe-label">Importe pendiente</div>
      <div class="importe-valor"><?php echo esc_html( $resto ) ?></div>
      <div class="importe-sub">Pago seguro procesado por Stripe</div>
    </div>

    <div class="btn-wrap">
      <a href="<?php echo esc_url( $checkout_url ) ?>" class="btn">
        💳 Completar el pago ahora
      </a>
    </div>
    <p class="seguridad">🔒 Enlace seguro · Un solo uso · Expira en 24h</p>

    <hr class="separador">

    <p class="cierre">
      Si tienes cualquier duda o incidencia, no dudes en contactarnos. Estaremos encantados de ayudarte.<br><br>
      ¡Muchas gracias por elegir <strong>Pintalimpio</strong>! 🧡
    </p>
  </div>

  <div class="footer">
    <strong>Pintalimpio</strong> · Servicios profesionales de limpieza<br>
    <a href="https://pintalimpio.com">pintalimpio.com</a>
  </div>

</div>
</body>
</html>
