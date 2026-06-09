<?php
/**
 * Desinstalación — Pintalimpio Reservas
 * Se ejecuta cuando el administrador elimina el plugin desde WordPress.
 * Elimina la tabla y todas las opciones del plugin.
 *
 * @author  Jhon Bastidas Rodrigues
 * @link    https://controlhorariowp.com/
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

// Eliminar tabla de reservas
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}plr_reservas" );

// Eliminar todas las opciones del plugin
$opciones = [
    'plr_db_version', 'plr_precio_base_m2', 'plr_porcentaje_deposito',
    'plr_stripe_modo', 'plr_stripe_pk_test', 'plr_stripe_sk_test',
    'plr_stripe_pk_live', 'plr_stripe_sk_live', 'plr_stripe_webhook_secret',
    'plr_email_admin', 'plr_google_review_url', 'plr_extras',
];
foreach ( $opciones as $opcion ) delete_option( $opcion );
