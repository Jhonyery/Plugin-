<!-- admin-nueva-reserva.php -->
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Nueva reserva</title>
<style>body{font-family:Arial,sans-serif;background:#f4f7f6}.wrap{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden}.header{background:#1a7f5a;padding:20px 30px;color:#fff}.header h1{margin:0;font-size:20px}.body{padding:30px}.fila{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee;font-size:14px}.btn{display:inline-block;background:#1a7f5a;color:#fff;padding:10px 24px;border-radius:4px;text-decoration:none;margin-top:20px;font-size:14px}</style>
</head><body>
<div class="wrap">
<div class="header"><h1>📋 Nueva reserva confirmada</h1></div>
<div class="body">
<div class="fila"><span>Cliente</span><strong><?php echo esc_html($nombre) ?></strong></div>
<div class="fila"><span>Email</span><span><?php echo esc_html($email) ?></span></div>
<div class="fila"><span>Teléfono</span><span><?php echo esc_html($telefono) ?></span></div>
<div class="fila"><span>Fecha</span><span><?php echo esc_html($fecha_servicio) ?></span></div>
<div class="fila"><span>Hora</span><span><?php echo esc_html($hora_servicio) ?></span></div>
<div class="fila"><span>Total</span><span><?php echo esc_html($total) ?></span></div>
<div class="fila"><span>Depósito cobrado</span><span><?php echo esc_html($deposito) ?></span></div>
<a href="<?php echo esc_url($admin_url) ?>" class="btn">Ver en el panel →</a>
</div>
</div></body></html>
