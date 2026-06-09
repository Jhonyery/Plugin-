<!-- admin-nueva-reserva.php -->
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Nueva reserva</title>
</head>
<body style="margin:0;padding:0;background:#f4f7f6;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f7f6;">
  <tr>
    <td align="center" style="padding:30px 20px;">
      <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">

        <!-- Cabecera -->
        <tr>
          <td colspan="2" style="background:#1a7f5a;padding:20px 30px;">
            <h1 style="margin:0;color:#ffffff;font-size:20px;font-family:Arial,sans-serif;">&#128203; Nueva reserva confirmada</h1>
          </td>
        </tr>

        <!-- Filas de datos -->
        <tr>
          <td style="padding:12px 30px 4px;font-size:14px;color:#555555;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;width:40%;">Cliente</td>
          <td style="padding:12px 30px 4px;font-size:14px;color:#111111;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;font-weight:bold;"><?php echo esc_html( $nombre ); ?></td>
        </tr>
        <tr>
          <td style="padding:12px 30px 4px;font-size:14px;color:#555555;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;">Email</td>
          <td style="padding:12px 30px 4px;font-size:14px;color:#111111;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;"><?php echo esc_html( $email ); ?></td>
        </tr>
        <tr>
          <td style="padding:12px 30px 4px;font-size:14px;color:#555555;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;">Teléfono</td>
          <td style="padding:12px 30px 4px;font-size:14px;color:#111111;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;"><?php echo esc_html( $telefono ); ?></td>
        </tr>
        <tr>
          <td style="padding:12px 30px 4px;font-size:14px;color:#555555;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;">Fecha</td>
          <td style="padding:12px 30px 4px;font-size:14px;color:#111111;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;"><?php echo esc_html( $fecha_servicio ); ?></td>
        </tr>
        <tr>
          <td style="padding:12px 30px 4px;font-size:14px;color:#555555;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;">Hora</td>
          <td style="padding:12px 30px 4px;font-size:14px;color:#111111;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;"><?php echo esc_html( $hora_servicio ); ?></td>
        </tr>
        <tr>
          <td style="padding:12px 30px 4px;font-size:14px;color:#555555;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;">Total</td>
          <td style="padding:12px 30px 4px;font-size:14px;color:#111111;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;"><?php echo esc_html( $total ); ?></td>
        </tr>
        <tr>
          <td style="padding:12px 30px 12px;font-size:14px;color:#555555;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;">Depósito cobrado</td>
          <td style="padding:12px 30px 12px;font-size:14px;color:#111111;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;font-weight:bold;"><?php echo esc_html( $deposito ); ?></td>
        </tr>

        <?php if ( ! empty( $observaciones ) ) : ?>
        <tr>
          <td style="padding:12px 30px 12px;font-size:14px;color:#555555;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;vertical-align:top;">Observaciones</td>
          <td style="padding:12px 30px 12px;font-size:14px;color:#111111;font-family:Arial,sans-serif;border-bottom:1px solid #eeeeee;"><?php echo nl2br( esc_html( $observaciones ) ); ?></td>
        </tr>
        <?php endif; ?>

        <!-- Botón -->
        <tr>
          <td colspan="2" style="padding:24px 30px;">
            <a href="<?php echo esc_url( $admin_url ); ?>" style="display:inline-block;background:#1a7f5a;color:#ffffff;padding:10px 24px;border-radius:4px;text-decoration:none;font-size:14px;font-family:Arial,sans-serif;">Ver en el panel &rarr;</a>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
