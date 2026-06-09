<?php
/**
 * Gestión de Base de Datos — Pintalimpio Reservas
 *
 * @author  Jhon Bastidas Rodrigues
 * @link    https://controlhorariowp.com/
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PLR_DB_Reservas {

    const ESTADO_PENDIENTE  = 'pendiente';
    const ESTADO_RESERVADO  = 'reservado';
    const ESTADO_FINALIZADO = 'finalizado';
    const ESTADO_PAGADO     = 'pagado';
    const ESTADO_CANCELADO         = 'cancelado';
    const ESTADO_PEND_TRANSFERENCIA = 'pend_transferencia'; // esperando confirmación del banco
    const ESTADO_PEND_BIZUM         = 'pend_bizum';         // Bizum iniciado, pendiente webhook

    const METODO_TARJETA      = 'tarjeta';
    const METODO_TRANSFERENCIA = 'transferencia';
    const METODO_BIZUM        = 'bizum';

    public static function get_estados() {
        return [
            self::ESTADO_PENDIENTE           => 'Pendiente',
            self::ESTADO_PEND_TRANSFERENCIA  => '⏳ Pend. Transferencia',
            self::ESTADO_PEND_BIZUM          => '⏳ Pend. Bizum',
            self::ESTADO_RESERVADO           => 'Reservado',
            self::ESTADO_FINALIZADO          => 'Finalizado',
            self::ESTADO_PAGADO              => 'Pagado',
            self::ESTADO_CANCELADO           => 'Cancelado',
        ];
    }

    private static function get_slots() {
        // Leer configuración del admin
        $inicio   = get_option( 'plr_hora_inicio', '09:00' );
        $fin      = get_option( 'plr_hora_fin',    '11:00' );
        $duracion = (int) get_option( 'plr_duracion_slot', 30 ); // minutos

        $slots  = [];
        $inicio_ts = strtotime( '2000-01-01 ' . $inicio );
        $fin_ts    = strtotime( '2000-01-01 ' . $fin );

        $actual = $inicio_ts;
        while ( $actual <= $fin_ts ) {
            $slots[] = date( 'H:i', $actual );
            $actual  += $duracion * 60;
        }
        return $slots;
    }

    // ── Crear tabla ───────────────────────────────────────────
    public static function crear_tabla() {
        global $wpdb;
        $t   = PLR_TABLE_RESERVAS;
        $col = $wpdb->get_charset_collate();

        // Tabla de fechas bloqueadas manualmente por el admin
        $tb = PLR_TABLE_RESERVAS . '_bloqueos';
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$tb} (
            id          BIGINT(20) NOT NULL AUTO_INCREMENT,
            fecha       DATE       NOT NULL,
            motivo      VARCHAR(200) NOT NULL DEFAULT '',
            creado_por  BIGINT(20) NOT NULL DEFAULT 0,
            creado_en   DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_fecha (fecha)
        ) {$col};" );

        $sql = "CREATE TABLE {$t} (
            id              BIGINT(20)    NOT NULL AUTO_INCREMENT,
            nombre          VARCHAR(100)  NOT NULL,
            email           VARCHAR(100)  NOT NULL,
            telefono        VARCHAR(20)   NOT NULL DEFAULT '',
            m2              SMALLINT(5)   NOT NULL DEFAULT 0,
            tipo_id         VARCHAR(50)   NOT NULL DEFAULT '',
            tipo_label      VARCHAR(100)  NOT NULL DEFAULT '',
            direccion       VARCHAR(255)  NOT NULL DEFAULT '',
            km_distancia    DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
            km_facturados   DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
            km_coste        DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
            extras          TEXT          NOT NULL DEFAULT '',
            estancias_json  LONGTEXT      NOT NULL DEFAULT '',
            fecha_servicio  DATE          NOT NULL,
            hora_servicio   TIME          NOT NULL,
            total           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            deposito        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            estado          VARCHAR(20)   NOT NULL DEFAULT 'pendiente',
            metodo_pago     VARCHAR(20)   NOT NULL DEFAULT 'tarjeta',
            transferencia_expira DATETIME  NULL DEFAULT NULL,
            stripe_pi_id    VARCHAR(100)  NOT NULL DEFAULT '',
            stripe_cs_id    VARCHAR(100)  NOT NULL DEFAULT '',
            observaciones   LONGTEXT      NOT NULL DEFAULT '',
            notas_admin     TEXT          NOT NULL DEFAULT '',
            facturacion_json TEXT          NOT NULL DEFAULT '',
            creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_estado         (estado),
            KEY idx_fecha_servicio (fecha_servicio),
            KEY idx_email          (email(50))
        ) {$col};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    // ── Migración de columnas ────────────────────────────────
    /**
     * Añade columnas nuevas a la tabla si no existen.
     * Se ejecuta en activación del plugin (dbDelta no siempre las añade).
     */
    public static function migrar_columnas() {
        global $wpdb;
        $tabla = PLR_TABLE_RESERVAS;

        // Verificar si estancias_json existe
        $cols = $wpdb->get_col( "DESCRIBE {$tabla}", 0 );
        if ( ! in_array( 'estancias_json', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$tabla} ADD COLUMN estancias_json LONGTEXT NOT NULL DEFAULT '' AFTER extras" );
        }
        if ( ! in_array( 'metodo_pago', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$tabla} ADD COLUMN metodo_pago VARCHAR(20) NOT NULL DEFAULT 'tarjeta' AFTER estancias_json" );
        }
        if ( ! in_array( 'transferencia_expira', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$tabla} ADD COLUMN transferencia_expira DATETIME NULL DEFAULT NULL AFTER metodo_pago" );
        }
        if ( ! in_array( 'tipo_id', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$tabla} ADD COLUMN tipo_id VARCHAR(50) NOT NULL DEFAULT '' AFTER m2" );
        }
        if ( ! in_array( 'tipo_label', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$tabla} ADD COLUMN tipo_label VARCHAR(100) NOT NULL DEFAULT '' AFTER tipo_id" );
        }
        if ( ! in_array( 'facturacion_json', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$tabla} ADD COLUMN facturacion_json TEXT NOT NULL DEFAULT '' AFTER notas_admin" );
        }
        if ( ! in_array( 'observaciones', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$tabla} ADD COLUMN observaciones LONGTEXT NOT NULL DEFAULT '' AFTER km_coste" );
        } else {
            // Asegurar que es LONGTEXT por si se instaló como TEXT antes
            $wpdb->query( "ALTER TABLE {$tabla} MODIFY COLUMN observaciones LONGTEXT NOT NULL DEFAULT ''" );
        }
        if ( ! in_array( 'direccion', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$tabla} ADD COLUMN direccion VARCHAR(255) NOT NULL DEFAULT '' AFTER tipo_label" );
        }
    }

    // ── Insertar reserva ──────────────────────────────────────
    public static function insertar_reserva( array $d ) {
        global $wpdb;

        foreach ( [ 'nombre', 'email', 'fecha_servicio', 'hora_servicio', 'total' ] as $c ) {
            if ( empty( $d[ $c ] ) ) return new WP_Error( 'campo_requerido', "Campo {$c} obligatorio." );
        }
        if ( ! is_email( $d['email'] ) ) return new WP_Error( 'email_invalido', 'Email no válido.' );

        // Validar formato de fecha YYYY-MM-DD
        $dt = DateTime::createFromFormat( 'Y-m-d', $d['fecha_servicio'] );
        if ( ! $dt || $dt->format('Y') < 2020 ) {
            return new WP_Error( 'fecha_invalida', 'Fecha de servicio no válida.' );
        }

        // Anti-solapamiento: segunda verificación
        if ( ! in_array( $d['hora_servicio'], self::get_slots_disponibles( $d['fecha_servicio'] ), true ) ) {
            return new WP_Error( 'slot_ocupado', 'Horario ya no disponible. Por favor elige otro.' );
        }

        $ok = $wpdb->insert(
            PLR_TABLE_RESERVAS,
            [
                'nombre'           => sanitize_text_field( $d['nombre'] ),
                'email'            => sanitize_email( $d['email'] ),
                'telefono'         => sanitize_text_field( $d['telefono'] ?? '' ),
                'm2'               => absint( $d['m2'] ?? 0 ),
                'tipo_id'          => sanitize_text_field( $d['tipo_id'] ?? '' ),
                'tipo_label'       => sanitize_text_field( $d['tipo_label'] ?? '' ),
                'direccion'        => sanitize_text_field( $d['direccion'] ?? '' ),
                'km_distancia'     => floatval( $d['km_info']['distancia_km'] ?? 0 ),
                'km_facturados'    => floatval( $d['km_extra'] ?? 0 ),
                'km_coste'         => floatval( $d['km_coste'] ?? 0 ),
                'extras'           => wp_json_encode( (array) ( $d['extras'] ?? [] ) ),
                'estancias_json'   => wp_json_encode( (array) ( $d['estancias'] ?? [] ) ),
                'fecha_servicio'   => $dt->format('Y-m-d'), // formato garantizado
                'hora_servicio'    => sanitize_text_field( $d['hora_servicio'] ),
                'total'            => floatval( $d['total'] ),
                'deposito'         => floatval( $d['deposito'] ?? ( $d['total'] * 0.5 ) ),
                'metodo_pago'      => sanitize_text_field( $d['metodo_pago'] ?? 'tarjeta' ),
                'estado'           => self::ESTADO_PENDIENTE,
                'stripe_pi_id'     => sanitize_text_field( $d['stripe_pi_id'] ?? '' ),
                'facturacion_json' => wp_json_encode( (array) ( $d['facturacion'] ?? [] ) ),
                'observaciones'    => sanitize_textarea_field( $d['observaciones'] ?? '' ),
            ],
            [ '%s','%s','%s','%d','%s','%s','%s','%f','%f','%f','%s','%s','%s','%s','%f','%f','%s','%s','%s','%s','%s' ]
        );

        return $ok ? (int) $wpdb->insert_id : new WP_Error( 'db_error', 'Error al guardar la reserva.' );
    }

    // ── Bloqueos manuales de fechas ──────────────────────────

    public static function get_tabla_bloqueos() {
        return PLR_TABLE_RESERVAS . '_bloqueos';
    }

    public static function bloquear_fecha( string $fecha, string $motivo = '', int $user_id = 0 ) {
        global $wpdb;
        return $wpdb->replace( self::get_tabla_bloqueos(), [
            'fecha'      => sanitize_text_field( $fecha ),
            'motivo'     => sanitize_text_field( $motivo ),
            'creado_por' => $user_id ?: get_current_user_id(),
        ], ['%s','%s','%d'] );
    }

    public static function desbloquear_fecha( string $fecha ) {
        global $wpdb;
        return $wpdb->delete( self::get_tabla_bloqueos(), ['fecha' => $fecha], ['%s'] );
    }

    public static function get_fechas_bloqueadas( string $desde = '', string $hasta = '' ) {
        global $wpdb;
        $tb    = self::get_tabla_bloqueos();
        $desde = $desde ?: date('Y-m-d');
        $hasta = $hasta ?: date('Y-m-d', strtotime('+6 months'));
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM %i WHERE fecha BETWEEN %s AND %s ORDER BY fecha", $tb, $desde, $hasta )
        );
    }

    public static function esta_bloqueada( string $fecha ) {
        global $wpdb;
        return (bool) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE fecha=%s", self::get_tabla_bloqueos(), $fecha )
        );
    }

    // ── Actualizar estado ─────────────────────────────────────
    public static function actualizar_estado( int $id, string $estado, array $extra = [] ) {
        global $wpdb;

        if ( ! array_key_exists( $estado, self::get_estados() ) ) {
            return new WP_Error( 'estado_invalido', "Estado {$estado} no válido." );
        }

        $reserva = self::get_reserva( $id );
        if ( ! $reserva ) return new WP_Error( 'no_encontrado', 'Reserva no encontrada.' );

        $cols   = [ 'estado' => $estado ];
        $fmts   = [ '%s' ];

        if ( ! empty( $extra['stripe_cs_id'] ) ) { $cols['stripe_cs_id'] = sanitize_text_field( $extra['stripe_cs_id'] ); $fmts[] = '%s'; }
        if ( isset( $extra['notas_admin'] ) )     { $cols['notas_admin']  = sanitize_textarea_field( $extra['notas_admin'] );  $fmts[] = '%s'; }

        $ok = $wpdb->update( PLR_TABLE_RESERVAS, $cols, [ 'id' => $id ], $fmts, [ '%d' ] );

        if ( false === $ok ) return new WP_Error( 'db_error', 'Error al actualizar el estado.' );

        // Hook para que PLR_Automatizacion envíe emails sin acoplarse aquí
        do_action( 'plr_estado_cambiado', $id, $estado, $reserva->estado, $reserva );

        return true;
    }

    // ── Getters ───────────────────────────────────────────────
    public static function get_reserva( int $id ) {
        global $wpdb;
        $r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id = %d LIMIT 1", PLR_TABLE_RESERVAS, $id ) );
        if ( $r ) {
            $r->extras           = json_decode( $r->extras, true ) ?? [];
            $r->estancias_json   = json_decode( $r->estancias_json ?? '', true ) ?? [];
            $r->facturacion_json = json_decode( $r->facturacion_json ?? '', true ) ?? [];
        }
        return $r;
    }

    public static function get_reserva_por_pi( string $pi_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE stripe_pi_id = %s LIMIT 1", PLR_TABLE_RESERVAS, $pi_id ) );
    }

    public static function get_reserva_por_cs( string $cs_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE stripe_cs_id = %s LIMIT 1", PLR_TABLE_RESERVAS, $cs_id ) );
    }

    // ── Slots disponibles ─────────────────────────────────────
    // Devuelve todos los slots sin filtrar — usado por Google Calendar
    public static function get_todos_los_slots() {
        return self::get_slots();
    }

    public static function get_slots_disponibles( string $fecha ) {
        global $wpdb;

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fecha ) ) return [];
        if ( strtotime( $fecha ) < strtotime( 'today' ) ) return [];

        // 1. Fecha bloqueada manualmente por el admin → día completo no disponible
        if ( self::esta_bloqueada( $fecha ) ) return [];

        $bloqueantes  = [ self::ESTADO_PENDIENTE, self::ESTADO_RESERVADO, self::ESTADO_FINALIZADO, self::ESTADO_PAGADO ];
        $placeholders = implode( ',', array_fill( 0, count( $bloqueantes ), '%s' ) );

        // 2. Contar reservas confirmadas ese día
        $reservas_hoy = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE fecha_servicio=%s AND estado IN ({$placeholders})",
                array_merge( [ PLR_TABLE_RESERVAS, $fecha ], $bloqueantes )
            )
        );

        // 3. Límite máximo configurable por el admin (default: 1 reserva/día)
        $max_dia = (int) get_option( 'plr_max_reservas_dia', 1 );
        $max_dia = max( 1, $max_dia ); // nunca menos de 1

        // Si se alcanzó el máximo → día completo bloqueado, no se muestran slots
        if ( $reservas_hoy >= $max_dia ) return [];

        // 4. Si hay capacidad → mostrar todos los slots menos los ya ocupados
        $horas_ocupadas = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT TIME_FORMAT(hora_servicio,'%%H:%%i') FROM %i WHERE fecha_servicio=%s AND estado IN ({$placeholders})",
                array_merge( [ PLR_TABLE_RESERVAS, $fecha ], $bloqueantes )
            )
        );

        // 5. Slots bloqueados por Google Calendar
        $gcal_bloqueados = [];
        if ( class_exists('PLR_Google_Calendar') && get_option('plr_gcal_activo','0') === '1' ) {
            $gcal_bloqueados = PLR_Google_Calendar::get_slots_bloqueados( $fecha );
        }

        $ocupados = array_unique( array_merge( $horas_ocupadas, $gcal_bloqueados ) );
        return array_values( array_diff( self::get_slots(), $ocupados ) );
    }

    // ── Listado para admin ────────────────────────────────────
    public static function get_reservas( array $args = [] ) {
        global $wpdb;

        $args = wp_parse_args( $args, [
            'estado'     => '',
            'busqueda'   => '',
            'por_pagina' => 20,
            'pagina'     => 1,
            'orden'      => 'creado_en',
            'dir'        => 'DESC',
        ] );

        $cols_ok = [ 'id','nombre','fecha_servicio','total','estado','creado_en' ];
        $orden   = in_array( $args['orden'], $cols_ok, true ) ? $args['orden'] : 'creado_en';
        $dir     = 'ASC' === strtoupper( $args['dir'] ) ? 'ASC' : 'DESC';

        $where = [ '1=1' ]; $vals = [];

        if ( ! empty( $args['estado'] ) && array_key_exists( $args['estado'], self::get_estados() ) ) {
            $where[] = 'estado = %s'; $vals[] = $args['estado'];
        }

        if ( ! empty( $args['busqueda'] ) ) {
            $b = '%' . $wpdb->esc_like( sanitize_text_field( $args['busqueda'] ) ) . '%';
            $where[] = '(nombre LIKE %s OR email LIKE %s)'; $vals[] = $b; $vals[] = $b;
        }

        $w      = implode( ' AND ', $where );
        $total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$w}", array_merge( [ PLR_TABLE_RESERVAS ], $vals ) ) );
        $offset = ( max(1, (int)$args['pagina']) - 1 ) * max(1,(int)$args['por_pagina']);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE {$w} ORDER BY {$orden} {$dir} LIMIT %d OFFSET %d",
                array_merge( [ PLR_TABLE_RESERVAS ], $vals, [ (int)$args['por_pagina'], $offset ] )
            )
        );

        foreach ( $rows as $r ) {
            $r->extras           = json_decode( $r->extras, true ) ?? [];
            $r->estancias_json   = json_decode( $r->estancias_json ?? '', true ) ?? [];
            $r->facturacion_json = json_decode( $r->facturacion_json ?? '', true ) ?? [];
        }

        return [ 'reservas' => $rows, 'total' => $total ];
    }

    // ── Estadísticas dashboard ────────────────────────────────
    public static function get_estadisticas() {
        global $wpdb;
        $filas = $wpdb->get_results( $wpdb->prepare( "SELECT estado, COUNT(*) AS n, SUM(total) AS suma FROM %i GROUP BY estado", PLR_TABLE_RESERVAS ) );
        $out   = [];
        foreach ( $filas as $f ) $out[ $f->estado ] = [ 'count' => (int)$f->n, 'suma' => (float)$f->suma ];
        return $out;
    }
}
