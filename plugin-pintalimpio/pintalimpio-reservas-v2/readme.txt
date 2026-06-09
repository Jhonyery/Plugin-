=== Pintalimpio Reservas ===
Contributors:      jhonbastidas
Author:            Jhon Bastidas Rodrigues
Author URI:        https://controlhorariowp.com/
Plugin URI:        https://controlhorariowp.com/
Requires at least: 6.0
Tested up to:      6.5
Requires PHP:      8.0
License:           GPL-2.0+

== Descripción ==
Sistema ERP interno de presupuestos, reservas y cobros automáticos para servicios de limpieza.

== Instalación ==
1. Sube la carpeta `pintalimpio-reservas` a `/wp-content/plugins/`
2. Activa el plugin en el panel de WordPress
3. Ve a Reservas → Configuración e introduce tus claves de Stripe
4. En el webhook de Stripe configura la URL: `https://tuweb.com/plr-webhook/stripe/`
5. Inserta el shortcode `[pintalimpio_cotizador]` en cualquier página

== Shortcode ==
[pintalimpio_cotizador]

== Webhook Stripe ==
URL: https://tuweb.com/plr-webhook/stripe/
Eventos a escuchar:
- payment_intent.succeeded
- payment_intent.payment_failed
- checkout.session.completed

== Changelog ==
= 1.0.0 =
* Primera versión estable
