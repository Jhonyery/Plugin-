<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reserva confirmada — Pintalimpio</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #f0f4f3; font-family: Arial, Helvetica, sans-serif; }
  .wrap { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,.10); }
  .header { background: #1a7f5a; padding: 36px 30px; text-align: center; color: #fff; }
  .header .icono { font-size: 48px; margin-bottom: 10px; }
  .header h1 { font-size: 24px; font-weight: 700; letter-spacing: -.3px; }
  .body { padding: 32px 30px; }
  .saludo { font-size: 17px; color: #222; margin-bottom: 14px; }
  .saludo strong { color: #1a7f5a; }
  .intro { color: #555; font-size: 15px; line-height: 1.6; margin-bottom: 24px; }
  .detalle { background: #f6faf8; border: 1px solid #d0ead9; border-radius: 8px; overflow: hidden; margin-bottom: 24px; }
  .detalle-fila { display: flex; justify-content: space-between; align-items: center; padding: 12px 18px; border-bottom: 1px solid #e0ece5; font-size: 15px; }
  .detalle-fila:last-child { border: none; background: #1a7f5a; color: #fff; font-weight: 700; font-size: 16px; }
  .detalle-fila span:first-child { color: #4a5568; }
  .detalle-fila:last-child span { color: #fff; }
  .aviso { background: #fffbeb; border-left: 4px solid #f6a623; border-radius: 4px; padding: 14px 16px; font-size: 14px; color: #78350f; line-height: 1.5; margin-bottom: 20px; }
  .cierre { font-size: 15px; color: #555; line-height: 1.6; }
  .footer { background: #f0f4f3; padding: 18px 30px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e2e8f0; }
  .footer a { color: #1a7f5a; text-decoration: none; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <div class="icono">✅</div>
    <h1>¡Tu reserva está confirmada!</h1>
  </div>

  <div class="body">
    <p class="saludo">Hola, <strong><?php echo esc_html( $nombre ) ?></strong>.</p>
    <p class="intro">Tu depósito se ha procesado correctamente y hemos reservado tu servicio de limpieza. ¡Nos ponemos manos a la obra!</p>

    <div class="detalle">
      <div class="detalle-fila">
        <span>📅 Fecha del servicio</span>
        <span><?php echo esc_html( $fecha_servicio ) ?></span>
      </div>
      <div class="detalle-fila">
        <span>🕐 Hora de llegada</span>
        <span><?php echo esc_html( $hora_servicio ) ?></span>
      </div>
      <div class="detalle-fila">
        <span>✅ Depósito pagado hoy</span>
        <span><?php echo esc_html( $deposito ) ?></span>
      </div>
      <div class="detalle-fila">
        <span>💳 Resto a pagar al finalizar</span>
        <span><?php echo esc_html( $resto ) ?></span>
      </div>
      <div class="detalle-fila">
        <span>Total del servicio</span>
        <span><?php echo esc_html( $total ) ?></span>
      </div>
    </div>

    <div class="aviso">
      💡 <strong>¿Necesitas cambiar algo?</strong> Si necesitas modificar o cancelar tu reserva, contáctanos con al menos 24 horas de antelación.
    </div>

    <p class="cierre">¡Muchas gracias por confiar en Pintalimpio! Nos vemos el <strong><?php echo esc_html( $fecha_servicio ) ?></strong> a las <strong><?php echo esc_html( $hora_servicio ) ?></strong>. 🧹</p>
  </div>

  <div class="footer">
    <strong>Pintalimpio</strong> · Servicios profesionales de limpieza<br>
    <a href="https://pintalimpio.com">pintalimpio.com</a>
  </div>

</div>
</body>
</html>
