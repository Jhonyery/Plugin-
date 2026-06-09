<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gracias por elegir Pintalimpio</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #f0f4f3; font-family: Arial, Helvetica, sans-serif; }
  .wrap { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,.10); }
  .header { background: linear-gradient(135deg, #1a7f5a 0%, #2d9e72 100%); padding: 40px 30px; text-align: center; color: #fff; }
  .header .icono { font-size: 56px; margin-bottom: 12px; }
  .header h1 { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
  .header p { font-size: 15px; opacity: .9; }
  .body { padding: 32px 30px; text-align: center; }
  .saludo { font-size: 18px; color: #222; margin-bottom: 16px; font-weight: 600; }
  .saludo span { color: #1a7f5a; }
  .mensaje { color: #555; font-size: 15px; line-height: 1.7; margin-bottom: 28px; text-align: left; }
  .estrellas-wrap { margin: 24px 0; }
  .estrellas { font-size: 40px; letter-spacing: 4px; display: block; margin-bottom: 8px; }
  .estrellas-texto { font-size: 13px; color: #718096; }
  .btn-wrap { margin: 24px 0 16px; }
  .btn { display: inline-block; background: #f6a623; color: #fff; padding: 18px 44px; border-radius: 50px; text-decoration: none; font-size: 17px; font-weight: 700; letter-spacing: -.2px; box-shadow: 0 4px 14px rgba(246,166,35,.4); }
  .tiempo { font-size: 13px; color: #a0aec0; margin-top: 12px; margin-bottom: 28px; }
  .separador { border: none; border-top: 1px solid #e2e8f0; margin: 24px 0; }
  .cierre { font-size: 14px; color: #718096; line-height: 1.7; text-align: left; }
  .cierre strong { color: #1a7f5a; }
  .footer { background: #f0f4f3; padding: 18px 30px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e2e8f0; }
  .footer a { color: #1a7f5a; text-decoration: none; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <div class="icono">🏆</div>
    <h1>¡Gracias por elegirnos!</h1>
    <p>Ha sido un placer trabajar para ti</p>
  </div>

  <div class="body">
    <p class="saludo">Hola, <span><?php echo esc_html( $nombre ) ?></span> 👋</p>

    <p class="mensaje">
      Tu pago se ha completado correctamente. Ya puedes disfrutar de tu hogar impecable. 
      Hemos puesto todo nuestro esfuerzo y profesionalidad para que el resultado fuera perfecto, 
      y esperamos haberte dejado con una gran sonrisa. 🧹✨
      <br><br>
      <strong>Esperamos verte pronto de nuevo.</strong> Recuerda que puedes volver a reservar en cualquier momento.
    </p>

    <hr class="separador">

    <div class="estrellas-wrap">
      <span class="estrellas">⭐⭐⭐⭐⭐</span>
      <p class="estrellas-texto">Tu opinión significa mucho para nosotros</p>
    </div>

    <p class="mensaje" style="text-align:center;">
      ¿Quedaste satisfecho con el servicio? <strong>Déjanos una reseña en Google.</strong><br>
      Solo te llevará <strong>menos de 1 minuto</strong> y nos ayuda muchísimo a llegar a más familias.
    </p>

    <div class="btn-wrap">
      <a href="<?php echo esc_url( $review_url ) ?>" class="btn">
        ⭐ Dejar mi valoración en Google
      </a>
    </div>
    <p class="tiempo">⏱ Menos de 1 minuto · Enlace directo a la valoración</p>

    <hr class="separador">

    <p class="cierre">
      Muchas gracias de corazón por tu confianza. 
      En <strong>Pintalimpio</strong> seguimos trabajando cada día para ofrecerte el mejor servicio de limpieza profesional.<br><br>
      ¡Hasta pronto! 🧡
    </p>
  </div>

  <div class="footer">
    <strong>Pintalimpio</strong> · Servicios profesionales de limpieza<br>
    <a href="https://pintalimpio.com">pintalimpio.com</a>
  </div>

</div>
</body>
</html>
