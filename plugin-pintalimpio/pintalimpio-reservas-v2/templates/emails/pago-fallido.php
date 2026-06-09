<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Problema con el pago</title>
<style>body{font-family:Arial,sans-serif;background:#f4f7f6}.wrap{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden}.header{background:#e53e3e;padding:30px;text-align:center;color:#fff}.header h1{margin:0;font-size:22px}.body{padding:30px;text-align:center}.body p{color:#444;line-height:1.6}.btn{display:inline-block;background:#1a7f5a;color:#fff;padding:14px 32px;border-radius:6px;text-decoration:none;font-size:16px;font-weight:bold;margin:20px 0}.footer{background:#f4f7f6;padding:20px;text-align:center;font-size:12px;color:#999}</style>
</head><body>
<div class="wrap">
<div class="header"><h1>⚠️ Problema con tu pago</h1></div>
<div class="body">
<p>Hola <strong><?php echo esc_html($nombre) ?></strong>,</p>
<p>Ha habido un problema al procesar tu pago y la reserva no se ha podido confirmar. No te preocupes, no se ha realizado ningún cargo.</p>
<p>Puedes intentarlo de nuevo haciendo clic en el botón de abajo:</p>
<a href="<?php echo esc_url($retry_url) ?>" class="btn">Reintentar reserva</a>
<p style="font-size:13px;color:#888">Si el problema persiste, contáctanos directamente.</p>
</div>
<div class="footer">Pintalimpio · <a href="https://controlhorariowp.com/" style="color:#999">controlhorariowp.com</a></div>
</div></body></html>
