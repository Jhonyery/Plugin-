<?php
/**
 * Calculadora de Precios — Pintalimpio Reservas
 *
 * Lógica de precios basada en horas de trabajo × tarifa hora.
 * Precio hora configurable desde el panel admin (default: 20€/h).
 *
 * Tablas de horas por tramo de m² y grado de suciedad:
 *   Normal:    30→14h, 50→16h, 100→24h, 150→32h
 *   Avanzada:  30→16h, 50→18h, 100→27h, 150→36h
 *   Muy sucia: 30→20h, 50→20h, 100→32h, 150→40h
 *
 * Para m² fuera de rango e intermedios: interpolación lineal.
 * Para otras categorías: multiplicador sobre la base de casas.
 *
 * @author  Jhon Bastidas Rodrigues
 * @link    https://controlhorariowp.com/
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PLR_Calculadora {

    // ── Tabla de horas por m² y grado ────────────────────────
    // [ m² => [ normal, avanzada, muy_sucia ] ]
    private static function get_tabla_horas() {
        return [
             30 => [ 14, 16, 20 ],
             50 => [ 16, 18, 20 ],
            100 => [ 24, 27, 32 ],
            150 => [ 32, 36, 40 ],
        ];
    }

    /**
     * Índice del grado de suciedad en la tabla de horas.
     */
    private static function grado_index( string $grado ) {
        return match( $grado ) {
            'avanzada'  => 1,
            'muy_sucia' => 2,
            default     => 0, // normal
        };
    }

    /**
     * Calcula las horas necesarias para limpiar $m2 metros
     * con el grado de suciedad dado, interpolando entre tramos.
     */
    public static function calcular_horas( int $m2, string $grado = 'normal' ) {
        $m2    = max( 1, $m2 );
        $tabla = self::get_tabla_horas();
        $idx   = self::grado_index( $grado );
        $puntos = array_keys( $tabla );
        sort( $puntos );

        $primero = $puntos[0];
        $ultimo  = end( $puntos );

        // Fuera de rango inferior — proporcional al primer tramo
        if ( $m2 <= $primero ) {
            return round( $m2 * ( $tabla[$primero][$idx] / $primero ), 2 );
        }
        // Fuera de rango superior — proporcional al último tramo
        if ( $m2 >= $ultimo ) {
            return round( $m2 * ( $tabla[$ultimo][$idx] / $ultimo ), 2 );
        }
        // Interpolación lineal entre tramos
        for ( $i = 0; $i < count($puntos) - 1; $i++ ) {
            $a = $puntos[$i];
            $b = $puntos[$i + 1];
            if ( $m2 >= $a && $m2 <= $b ) {
                $ha = $tabla[$a][$idx];
                $hb = $tabla[$b][$idx];
                return round( $ha + ( $m2 - $a ) * ( $hb - $ha ) / ( $b - $a ), 2 );
            }
        }
        return 0;
    }

    /**
     * Precio base para casas: horas × tarifa/hora.
     * La tarifa es configurable desde Reservas → Configuración.
     */
    public static function precio_base_casas( int $m2, string $grado = 'normal' ) {
        $tarifa_hora = (float) get_option( 'plr_precio_hora', 20.00 );
        $horas       = self::calcular_horas( $m2, $grado );
        return round( $horas * $tarifa_hora, 2 );
    }

    // ── Multiplicadores por categoría ────────────────────────
    // Se aplican sobre el precio base de casas (mismo grado de suciedad).
    private static function get_multiplicadores_categoria() {
        return [
            // Casas — descuentos aplicados sobre precio base de horas
            'habitada'         => 0.80, // -20%
            'estrenar'         => 0.70, // -30%
            'fin_obra_sin'     => 0.80, // -20%
            'fin_obra_con'     => 0.80, // -20%
            'fin_alquiler_sin' => 0.75, // -25%
            'fin_alquiler_con' => 0.80, // -20%
            'cocinas'          => 0.90, // -10%

            // Otros inmuebles
            'oficinas'         => 0.85,
            'locales'          => 0.90,
            'trasteros'        => 0.70,
            'garajes'          => 0.75,

            // Traumáticas — más horas, mismo modelo pero ×1.5
            'diogenes'         => 1.50,
            'fallecimientos'   => 1.50,
            'desahucios'       => 1.30,

            // Vaciado — incluye retirada de muebles → ×1.4
            'vaciado_viviendas' => 1.40,
            'vaciado_oficinas'  => 1.20,
            'vaciado_trasteros' => 1.10,

            // Cristales — precio por unidad, no por horas (mult ignorado)
            'cristales_interior' => 1.00,
            'cristales_exterior' => 1.00,

            // Mantenimiento — precio directo por horas (mult ignorado)
            'mantenimiento_hogar'  => 1.00,
            'mantenimiento_oficina' => 1.00,
        ];
    }

    /**
     * Catálogo original sin filtros de BD — usado en el guardado del admin.
     * get_catalogo() filtra los desactivados; este devuelve todos siempre.
     */
    public static function get_catalogo_raw() {
        $cats = self::get_catalogo_sin_filtros();
        return $cats;
    }

    private static function get_catalogo_sin_filtros() {
        return [
            [ 'id' => 'casas',          'label' => 'Limpieza a fondo casas',           'tipos' => [
                [ 'id' => 'habitada',         'label' => 'Habitada y amueblada'     ],
                [ 'id' => 'estrenar',         'label' => 'Vivienda a estrenar'      ],
                [ 'id' => 'fin_obra_sin',     'label' => 'Fin obra SIN muebles'     ],
                [ 'id' => 'fin_obra_con',     'label' => 'Fin obra CON muebles'     ],
                [ 'id' => 'fin_alquiler_sin', 'label' => 'Fin alquiler SIN muebles' ],
                [ 'id' => 'fin_alquiler_con', 'label' => 'Fin alquiler CON muebles' ],
                [ 'id' => 'cocinas',          'label' => 'Cocinas CON enseres'      ],
            ]],
            [ 'id' => 'inmuebles',      'label' => 'Limpieza otros inmuebles',          'tipos' => [
                [ 'id' => 'oficinas',  'label' => 'Oficinas'                   ],
                [ 'id' => 'locales',   'label' => 'Locales y establecimientos' ],
                [ 'id' => 'trasteros', 'label' => 'Trasteros'                  ],
                [ 'id' => 'garajes',   'label' => 'Garajes'                    ],
            ]],
            [ 'id' => 'traumaticas',    'label' => 'Limpiezas traumáticas',             'tipos' => [
                [ 'id' => 'diogenes',       'label' => 'Síndrome Diógenes'   ],
                [ 'id' => 'fallecimientos', 'label' => 'Tras fallecimientos'  ],
                [ 'id' => 'desahucios',     'label' => 'Desahucios'           ],
            ]],
            [ 'id' => 'vaciado',        'label' => 'Vaciado de inmuebles',              'tipos' => [
                [ 'id' => 'vaciado_viviendas', 'label' => 'Viviendas enteras' ],
                [ 'id' => 'vaciado_oficinas',  'label' => 'Oficinas enteras'  ],
                [ 'id' => 'vaciado_trasteros', 'label' => 'Trasteros'         ],
            ]],
            [ 'id' => 'cristales',      'label' => 'Limpieza de cristales',             'tipos' => [
                [ 'id' => 'cristales_interior', 'label' => 'Cristales interiores accesibles' ],
                [ 'id' => 'cristales_exterior', 'label' => 'Cristales exteriores / no accesibles' ],
            ]],
            [ 'id' => 'mantenimiento',  'label' => 'Limpieza por horas — Mantenimiento','tipos' => [
                [ 'id' => 'mantenimiento_hogar',  'label' => 'Hogar / vivienda' ],
                [ 'id' => 'mantenimiento_oficina', 'label' => 'Oficina / local' ],
            ]],
        ];
    }

    // Exponemos los multiplicadores para la UI de configuración
    public static function get_multiplicadores_publicos() {
        return self::get_multiplicadores_categoria();
    }

    /**
     * Devuelve los multiplicadores aplicando los valores personalizados del admin.
     * Si el admin cambió un multiplicador en la BD, usa ese valor.
     * Se pasa al JS via PLR_Config para que el cálculo frontend sea idéntico al backend.
     */
    public static function get_multiplicadores_con_config() {
        $mults = self::get_multiplicadores_categoria();
        if ( ! function_exists('get_option') ) return $mults;
        foreach ( array_keys($mults) as $tipo_id ) {
            $guardado = get_option( 'plr_tipo_mult_' . $tipo_id, '' );
            if ( $guardado !== '' ) {
                $mults[$tipo_id] = (float) $guardado;
            }
        }
        return $mults;
    }

    // ── Catálogo de categorías ────────────────────────────────
    public static function get_catalogo() {
        $cats_raw = self::get_catalogo_sin_filtros();

        if ( ! function_exists('get_option') ) return $cats_raw;

        $resultado = [];
        foreach ( $cats_raw as $cat ) {
            // Filtrar categoría SOLO si está explícitamente desactivada ('0')
            // Cualquier otro valor (incluyendo vacío o no existente) = activa
            $cat_estado = get_option( 'plr_cat_activa_' . $cat['id'], '1' );
            if ( $cat_estado === '0' ) continue;

            // Aplicar nombre personalizado a la categoría
            $cat_nombre = get_option( 'plr_cat_nombre_' . $cat['id'], '' );
            if ( $cat_nombre ) $cat['label'] = $cat_nombre;

            // Filtrar y personalizar tipos
            $tipos = [];
            foreach ( $cat['tipos'] as $tipo ) {
                // Filtrar tipo SOLO si está explícitamente desactivado ('0')
                $tipo_estado = get_option( 'plr_tipo_activo_' . $tipo['id'], '1' );
                if ( $tipo_estado === '0' ) continue;
                // Aplicar nombre personalizado
                $nombre = get_option( 'plr_tipo_nombre_' . $tipo['id'], '' );
                if ( $nombre ) $tipo['label'] = $nombre;
                $tipos[] = $tipo;
            }

            // Solo incluir categoría si tiene al menos un tipo activo
            if ( empty($tipos) ) continue;
            $cat['tipos'] = $tipos;
            $resultado[]  = $cat;
        }

        return $resultado;
    }

    // ── Estancias ─────────────────────────────────────────────
    public static function get_estancias() { return self::aplicar_config_admin( self::get_estancias_data() ); }
    private static function get_estancias_data() {
        return( [
            [
                'id'          => 'habitaciones',
                'label'       => 'Habitaciones',
                'icono'       => '🛏',
                'tipo'        => 'cantidad',
                'precio_unit' => 10.00,
                'opciones'    => [
                    [ 'id' => 'hab_armarios_completo', 'label' => 'Armarios por dentro y por fuera', 'mod' => 0.05 ],
                    [ 'id' => 'hab_armarios_dentro',   'label' => 'Armarios solo por dentro',        'mod' => 0.05 ],
                    [ 'id' => 'hab_armarios_fuera',    'label' => 'Armarios solo por fuera',         'mod' => 0.05 ],
                    [ 'id' => 'hab_puerta',            'label' => 'Puerta (ambas caras)',            'mod' => 0.03 ],
                    [ 'id' => 'hab_rodapies',          'label' => 'Rodapiés',                        'mod' => 0.03 ],
                ],
            ],
            [
                'id'          => 'banos',
                'label'       => 'Baños',
                'icono'       => '🚿',
                'tipo'        => 'cantidad',
                'precio_unit' => 20.00,
                'mod_tipo'    => 'fijo',
                'opciones'    => [
                    [ 'id' => 'bano_azulejos',   'label' => 'Azulejos',          'mod' => 5.00 ],
                    [ 'id' => 'bano_mampara',    'label' => 'Mampara',           'mod' => 5.00 ],
                    [ 'id' => 'bano_mobiliario', 'label' => 'Mobiliario',        'mod' => 5.00 ],
                    [ 'id' => 'bano_puerta',     'label' => 'Puerta (ambas caras)', 'mod' => 3.00 ],
                    [ 'id' => 'bano_rodapies',   'label' => 'Rodapiés',          'mod' => 2.00 ],
                ],
            ],
            [
                'id'          => 'cristales',
                'label'       => 'Cristales / Ventanas',
                'icono'       => '🪟',
                'tipo'        => 'cantidad',
                'precio_unit' => 10.00,
                'opciones'    => [
                    [ 'id' => 'cristal_persiana', 'label' => 'Persianas', 'mod' => 0.10 ],
                    [ 'id' => 'cristal_railes',   'label' => 'Raíles',    'mod' => 0.05 ],
                    [ 'id' => 'cristal_marcos',   'label' => 'Marcos',    'mod' => 0.03 ],
                ],
            ],
            [
                'id'          => 'cocina',
                'label'       => 'Cocina',
                'icono'       => '🍳',
                'tipo'        => 'toggle',
                'precio_base' => 40.00,
                'descripcion' => 'Desinfección completa con productos industriales y vaporeta a 180° de temperatura.',
                'opciones'    => [
                    [ 'id' => 'cocina_azulejos',        'label' => 'Limpieza de azulejos',   'mod' => 0.05 ],
                    [ 'id' => 'cocina_huecos',          'label' => 'Limpieza de huecos',     'mod' => 0.05 ],
                    [ 'id' => 'cocina_nevera',          'label' => 'Nevera',                 'mod' => 0.05 ],
                    [ 'id' => 'cocina_lavadora',        'label' => 'Lavadora',               'mod' => 0.05 ],
                    [ 'id' => 'cocina_bajos_muebles',   'label' => 'Bajos de los muebles',   'mod' => 0.05 ],
                    [ 'id' => 'cocina_horno',           'label' => 'Horno',                  'mod' => 0.05 ],
                    [ 'id' => 'cocina_campana',         'label' => 'Campana extractora',     'mod' => 0.05 ],
                    [ 'id' => 'cocina_vaciado_muebles', 'label' => 'Vaciado de muebles',     'mod' => 0.05 ],
                    [ 'id' => 'cocina_arm_ext',         'label' => 'Armarios (exterior)',    'mod' => 0.05 ],
                    [ 'id' => 'cocina_arm_int',         'label' => 'Armarios (interior)',    'mod' => 0.05 ],
                    [ 'id' => 'cocina_puerta',          'label' => 'Puerta (ambas caras)',   'mod' => 0.03 ],
                    [ 'id' => 'cocina_rodapies',        'label' => 'Rodapiés',               'mod' => 0.03 ],
                ],
            ],
            [
                'id'          => 'hall',
                'label'       => 'Hall de entrada',
                'icono'       => '🚪',
                'tipo'        => 'toggle',
                'precio_base' => 8.00,
                'opciones'    => [
                    [ 'id' => 'hall_estanteria',      'label' => 'Estantería',                        'mod' => 0.10 ],
                    [ 'id' => 'hall_puerta_entrada',  'label' => 'Puerta de entrada (ambas caras)',   'mod' => 0.10 ],
                    [ 'id' => 'hall_zapatero',        'label' => 'Zapatero / mueble de entrada',      'mod' => 0.08 ],
                    [ 'id' => 'hall_espejo',          'label' => 'Espejo',                            'mod' => 0.05 ],
                    [ 'id' => 'hall_rodapies',        'label' => 'Rodapiés',                          'mod' => 0.03 ],
                ],
            ],
            [
                'id'          => 'salon',
                'label'       => 'Salón',
                'icono'       => '🛋',
                'tipo'        => 'toggle',
                'precio_base' => 10.00,
                'opciones'    => [
                    [ 'id' => 'salon_muebles_vacios',    'label' => 'Muebles vacíos (exterior e interior)',  'mod' => 0.05 ],
                    [ 'id' => 'salon_libros',            'label' => 'Estanterías con libros',               'mod' => 0.05 ],
                    [ 'id' => 'salon_tv',                'label' => 'TV y electrónica (pantallas, cables)',  'mod' => 0.05 ],
                    [ 'id' => 'salon_cajones',           'label' => 'Cajones y compartimentos interiores',  'mod' => 0.05 ],
                    [ 'id' => 'salon_puerta_acristalada','label' => 'Puerta acristalada',                   'mod' => 0.05 ],
                    [
                        'id'          => 'salon_sofa',
                        'label'       => 'Sofá',
                        'mod'         => 0,
                        'tipo'        => 'radio_cantidad', // radio + contador de plazas
                        'subopciones' => [
                            [ 'id' => 'sofa_maquina',  'label' => 'Limpieza con máquina tapicería industrial (elimina manchas)', 'precio_plaza' => 40.00 ],
                            [ 'id' => 'sofa_vaporeta', 'label' => 'Desinfección con vaporeta (no elimina manchas)',             'precio_plaza' => 10.00 ],
                            [ 'id' => 'sofa_aspirado', 'label' => 'Solo aspirado',                                             'mod_plaza'    => 0.02  ],
                        ],
                    ],
                    [
                        'id'          => 'salon_alfombras',
                        'label'       => 'Alfombras',
                        'mod'         => 0,
                        'tipo'        => 'radio_cantidad_m2', // cantidad + m² + radio
                        'subopciones' => [
                            [ 'id' => 'alfombra_maquina',  'label' => 'Limpieza con máquina tapicería industrial (elimina manchas)', 'precio_m2'   => 12.00 ],
                            [ 'id' => 'alfombra_vaporeta', 'label' => 'Desinfección con vaporeta (no elimina manchas)',             'precio_m2'   => 5.00  ],
                            [ 'id' => 'alfombra_aspirado', 'label' => 'Solo aspirado',                                             'mod_por_m2'  => 0.02  ],
                        ],
                    ],
                    [ 'id' => 'salon_cuadros',           'label' => 'Cuadros y decoración en pared',        'mod' => 0.05 ],
                    [ 'id' => 'salon_rodapies',          'label' => 'Rodapiés y esquinas',                  'mod' => 0.03 ],
                    [ 'id' => 'salon_puerta',           'label' => 'Puerta (ambas caras)',                 'mod' => 0.03 ],
                ],
            ],
            [
                'id'          => 'pasillos',
                'label'       => 'Pasillos',
                'icono'       => '🚪',
                'tipo'        => 'toggle',
                'precio_base' => 5.00,
                'opciones'    => [
                    [ 'id' => 'pasillo_estanteria', 'label' => 'Estantería',           'mod' => 0.10 ],
                    [ 'id' => 'pasillo_rodapies',   'label' => 'Rodapiés',             'mod' => 0.05 ],
                    [ 'id' => 'pasillo_puertas',    'label' => 'Puertas interiores',   'mod' => 0.05 ],
                ],
            ],
            [
                'id'          => 'lamparas',
                'label'       => 'Lámparas, enchufes y radiadores',
                'icono'       => '💡',
                'tipo'        => 'toggle',
                'precio_base' => 30.00,
                'opciones'    => [
                    [ 'id' => 'lamparas_dificiles', 'label' => 'Lámparas difíciles de limpiar (araña, techo, apliques)', 'mod' => 0.15 ],
                ],
            ],
            [
                'id'          => 'terraza',
                'label'       => 'Terraza',
                'icono'       => '🌿',
                'tipo'        => 'cantidad_m2',
                'precio_unit' => 3.00,
                'opciones'    => [],
            ],
        ] );
    }

    public static function get_estancias_garajes() { return self::aplicar_config_admin( self::get_estancias_garajes_data() ); }
    private static function get_estancias_garajes_data() {
        return( [
            [
                'id'          => 'gar_plantas',
                'label'       => 'Plantas',
                'icono'       => '🏢',
                'tipo'        => 'cantidad',
                'precio_unit' => 20.00,
                'opciones'    => [
                    [
                        'id'          => 'gar_plantas_nivel',
                        'label'       => 'Nivel de limpieza',
                        'mod'         => 0,
                        'tipo'        => 'radio',
                        'subopciones' => [
                            [ 'id' => 'gar_barrido_simple', 'label' => 'Barrido y fregado simple (incluido)',        'mod'         => 0    ],
                            [ 'id' => 'gar_fregadora',      'label' => 'Barrido + fregadora industrial',             'precio_m2'   => 4.00 ],
                        ],
                    ],
                ],
            ],
            [ 'id' => 'gar_plazas', 'label' => 'Plazas', 'icono' => '🅿', 'tipo' => 'cantidad', 'precio_unit' => 5.00, 'opciones' => [] ],
            [
                'id'          => 'gar_banos',
                'label'       => 'Baños',
                'icono'       => '🚿',
                'tipo'        => 'cantidad',
                'precio_unit' => 20.00,
                'opciones'    => [
                    [
                        'id'          => 'gar_banos_nivel',
                        'label'       => 'Nivel de limpieza',
                        'mod'         => 0,
                        'tipo'        => 'radio',
                        'subopciones' => [
                            [ 'id' => 'gar_bano_basico',       'label' => 'Barrido y fregado básico (incluido)',            'mod'         => 0     ],
                            [ 'id' => 'gar_bano_desinfeccion', 'label' => 'Desinfección total con productos bactericidas',  'precio_fijo' => 15.00 ],
                        ],
                    ],
                ],
            ],
        ] );
    }

    public static function get_estancias_trasteros() { return self::aplicar_config_admin( self::get_estancias_trasteros_data() ); }
    private static function get_estancias_trasteros_data() {
        return( [
            [ 'id' => 'tras_estanterias',         'label' => 'Estanterías',                    'icono' => '📚', 'tipo' => 'toggle', 'precio_base' => 30.00, 'opciones' => [] ],
            [ 'id' => 'tras_vaciado_estanterias', 'label' => 'Vaciado de estanterías',          'icono' => '📦', 'tipo' => 'toggle', 'precio_base' => 50.00, 'opciones' => [] ],
            [ 'id' => 'tras_retirada_basura',     'label' => 'Retirada de basura',              'icono' => '🗑',  'tipo' => 'toggle', 'precio_base' => 40.00, 'opciones' => [] ],
            [ 'id' => 'tras_voluminosos',         'label' => 'Eliminación objetos voluminosos', 'icono' => '🛋',  'tipo' => 'toggle', 'precio_base' => 60.00, 'opciones' => [] ],
            [ 'id' => 'tras_desinfeccion',        'label' => 'Desinfección',                   'icono' => '🧴',  'tipo' => 'toggle', 'precio_base' => 30.00, 'opciones' => [] ],
        ] );
    }

    public static function get_estancias_inmuebles() { return self::aplicar_config_admin( self::get_estancias_inmuebles_data() ); }
    private static function get_estancias_inmuebles_data() {
        return( [
            [ 'id' => 'mobiliario', 'label' => 'Mobiliario completo',   'icono' => '🪑', 'tipo' => 'toggle',   'precio_base' => 50.00, 'descripcion' => 'Mesas, sillas, archivadores y estanterías.', 'opciones' => [] ],
            [ 'id' => 'banos',      'label' => 'Baños',                 'icono' => '🚿', 'tipo' => 'cantidad', 'precio_unit' => 20.00, 'opciones' => [] ],
            [ 'id' => 'cristales',  'label' => 'Cristales / Ventanas',  'icono' => '🪟', 'tipo' => 'cantidad', 'precio_unit' => 10.00, 'opciones' => [] ],
            [ 'id' => 'persianas',  'label' => 'Persianas',             'icono' => '🪟', 'tipo' => 'toggle',   'precio_base' => 20.00, 'opciones' => [] ],
            [ 'id' => 'paredes',    'label' => 'Paredes',               'icono' => '🧱', 'tipo' => 'toggle',   'precio_base' => 40.00, 'opciones' => [] ],
            [ 'id' => 'suelos',     'label' => 'Suelos especiales',     'icono' => '🧹', 'tipo' => 'toggle',   'precio_base' => 30.00, 'opciones' => [] ],
        ] );
    }

    // ── Helpers ───────────────────────────────────────────────
    public static function get_categoria_de_tipo( string $tipo_id ) {
        foreach ( self::get_catalogo() as $cat ) {
            foreach ( $cat['tipos'] as $tipo ) {
                if ( $tipo['id'] === $tipo_id ) return $cat['id'];
            }
        }
        return null;
    }

    public static function is_categoria_casas( string $tipo_id ) {
        return 'casas' === self::get_categoria_de_tipo( $tipo_id );
    }

    public static function tiene_estancias( string $tipo_id ) {
        $cat = self::get_categoria_de_tipo( $tipo_id );
        return in_array( $cat, ['casas','traumaticas','vaciado','cristales','mantenimiento'], true )
            || in_array( $tipo_id, ['oficinas','trasteros','garajes','cristales_interior','cristales_exterior','mantenimiento_hogar','mantenimiento_oficina'], true );
    }

    public static function get_estancias_cristales() { return self::aplicar_config_admin( self::get_estancias_cristales_data() ); }
    private static function get_estancias_cristales_data() {
        return( [
            [
                'id'          => 'cris_ventanas',
                'label'       => 'Ventanas',
                'icono'       => '🪟',
                'tipo'        => 'cantidad',
                'precio_unit' => 10.00,
                'opciones'    => [
                    [ 'id' => 'cris_doble', 'label' => 'Doble ventana (extra por ventana)', 'mod' => 0, 'precio_fijo_ud' => 5.00 ],
                ],
            ],
            [
                'id'          => 'cris_persianas',
                'label'       => 'Persianas',
                'icono'       => '🔲',
                'tipo'        => 'cantidad',
                'precio_unit' => 7.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'cris_railes',
                'label'       => 'Raíles / guías',
                'icono'       => '➡️',
                'tipo'        => 'cantidad',
                'precio_unit' => 3.00,
                'opciones'    => [],
            ],
        ] );
    }

    public static function get_estancias_mantenimiento() { return self::aplicar_config_admin( self::get_estancias_mantenimiento_data() ); }
    private static function get_estancias_mantenimiento_data() {
        return( [
            [
                'id'          => 'mant_modalidad',
                'label'       => 'Modalidad del servicio',
                'icono'       => '🧹',
                'tipo'        => 'toggle_radio', // radio especial — no suma precio aquí, lo suma calcular()
                'precio_base' => 0,
                'opciones'    => [
                    [ 'id' => 'sin_herramientas', 'label' => 'Sin herramientas ni productos (la chica acude sola)',         'precio_hora_extra' => 0   ],
                    [ 'id' => 'con_herramientas', 'label' => 'Con herramientas y productos incluidos (+2€/hora)',           'precio_hora_extra' => 2.0 ],
                ],
            ],
            [ 'id' => 'mant_banos',       'label' => 'Baños',                   'icono' => '🚿', 'tipo' => 'toggle', 'precio_base' => 0, 'opciones' => [] ],
            [ 'id' => 'mant_cocina',      'label' => 'Cocina',                  'icono' => '🍳', 'tipo' => 'toggle', 'precio_base' => 0, 'opciones' => [] ],
            [ 'id' => 'mant_salon',       'label' => 'Salón / comedor',         'icono' => '🛋', 'tipo' => 'toggle', 'precio_base' => 0, 'opciones' => [] ],
            [ 'id' => 'mant_habitaciones','label' => 'Habitaciones',            'icono' => '🛏', 'tipo' => 'toggle', 'precio_base' => 0, 'opciones' => [] ],
            [ 'id' => 'mant_plancha',     'label' => 'Plancha y ropa',          'icono' => '👕', 'tipo' => 'toggle', 'precio_base' => 0, 'opciones' => [] ],
            [ 'id' => 'mant_suelos',      'label' => 'Suelos y fregado',        'icono' => '🧹', 'tipo' => 'toggle', 'precio_base' => 0, 'opciones' => [] ],
            [ 'id' => 'mant_terraza',     'label' => 'Terraza / balcón',        'icono' => '🌿', 'tipo' => 'toggle', 'precio_base' => 0, 'opciones' => [] ],
            [ 'id' => 'mant_escaleras',   'label' => 'Escaleras / pasillos',    'icono' => '🚪', 'tipo' => 'toggle', 'precio_base' => 0, 'opciones' => [] ],
            [ 'id' => 'mant_despacho',    'label' => 'Despacho / zona trabajo', 'icono' => '💼', 'tipo' => 'toggle', 'precio_base' => 0, 'opciones' => [] ],
        ] );
    }

    public static function get_estancias_vaciado() { return self::aplicar_config_admin( self::get_estancias_vaciado_data() ); }
    private static function get_estancias_vaciado_data() {
        return( [
            [
                'id'          => 'vac_sofas',
                'label'       => 'Sofás y sillones',
                'icono'       => '🛋',
                'tipo'        => 'cantidad',
                'precio_unit' => 10.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_camas',
                'label'       => 'Camas y somieres',
                'icono'       => '🛏',
                'tipo'        => 'cantidad',
                'precio_unit' => 5.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_armarios',
                'label'       => 'Armarios y roperos',
                'icono'       => '🚪',
                'tipo'        => 'cantidad',
                'precio_unit' => 10.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_mesas',
                'label'       => 'Mesas',
                'icono'       => '🪑',
                'tipo'        => 'cantidad',
                'precio_unit' => 5.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_sillas',
                'label'       => 'Sillas',
                'icono'       => '🪑',
                'tipo'        => 'cantidad',
                'precio_unit' => 3.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_estanterias',
                'label'       => 'Estanterías y librerías',
                'icono'       => '📚',
                'tipo'        => 'cantidad',
                'precio_unit' => 50.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_nevera',
                'label'       => 'Nevera / frigorífico',
                'icono'       => '🧊',
                'tipo'        => 'cantidad',
                'precio_unit' => 20.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_lavadora',
                'label'       => 'Lavadora / secadora',
                'icono'       => '🫧',
                'tipo'        => 'cantidad',
                'precio_unit' => 20.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_horno',
                'label'       => 'Horno / microondas',
                'icono'       => '🔥',
                'tipo'        => 'cantidad',
                'precio_unit' => 10.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_cocina_completa',
                'label'       => 'Cocina completa (muebles + electrodomésticos)',
                'icono'       => '🍳',
                'tipo'        => 'toggle',
                'precio_base' => 200.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_colchones',
                'label'       => 'Colchones',
                'icono'       => '🛌',
                'tipo'        => 'cantidad',
                'precio_unit' => 5.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_cajas',
                'label'       => 'Cajas y enseres varios',
                'icono'       => '📦',
                'tipo'        => 'cantidad',
                'precio_unit' => 3.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_ropa',
                'label'       => 'Ropa y textiles',
                'icono'       => '👕',
                'tipo'        => 'toggle',
                'precio_base' => 0.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_libros',
                'label'       => 'Libros y revistas',
                'icono'       => '📖',
                'tipo'        => 'toggle',
                'precio_base' => 0.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_decoracion',
                'label'       => 'Cuadros y decoración',
                'icono'       => '🖼',
                'tipo'        => 'toggle',
                'precio_base' => 0.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_escombros',
                'label'       => 'Residuos de obra (escombros, maderas...)',
                'icono'       => '🔨',
                'tipo'        => 'toggle',
                'precio_base' => 50.00,
                'opciones'    => [],
            ],
            [
                'id'          => 'vac_peligrosos',
                'label'       => 'Residuos especiales (pinturas, químicos...)',
                'icono'       => '⚠️',
                'tipo'        => 'toggle',
                'precio_base' => 100.00,
                'opciones'    => [],
            ],
        ] );
    }

    public static function get_estancias_para_tipo( string $tipo_id ) {
        if ( in_array( $tipo_id, ['vaciado_viviendas','vaciado_oficinas','vaciado_trasteros'], true ) ) return self::get_estancias_vaciado();
        if ( in_array( $tipo_id, ['cristales_interior','cristales_exterior'], true ) )                  return self::get_estancias_cristales();
        if ( in_array( $tipo_id, ['mantenimiento_hogar','mantenimiento_oficina'], true ) )              return self::get_estancias_mantenimiento();
        if ( in_array( $tipo_id, ['oficinas'], true ) )   return self::get_estancias_inmuebles();
        if ( in_array( $tipo_id, ['trasteros'], true ) )  return self::get_estancias_trasteros();
        if ( $tipo_id === 'garajes' )                     return self::get_estancias_garajes();
        $todas = self::get_estancias();
        if ( $tipo_id === 'cocinas' ) return array_filter( $todas, fn($e) => 'cocina' === $e['id'] );
        return $todas;
    }

    /**
     * Lee el precio de una estancia desde la BD si fue personalizado.
     * Si no hay valor guardado devuelve el default del catálogo.
     */
    /**
     * Aplica las personalizaciones guardadas en admin a un array de estancias.
     * Lee nombre, precio y estado activo/inactivo de la BD.
     */
    private static function aplicar_config_admin( array $estancias ) {
        if ( ! function_exists('get_option') ) return $estancias;
        $resultado = [];
        foreach ( $estancias as $est ) {
            $eid = sanitize_key( $est['id'] );
            // Filtrar desactivadas
            if ( get_option( 'plr_e_act_' . $eid, '1' ) === '0' ) continue; // solo filtrar si explícitamente desactivado
            // Nombre personalizado
            $nombre = get_option( 'plr_e_nom_' . $eid, '' );
            if ( $nombre ) $est['label'] = $nombre;
            // Precio personalizado
            $precio = get_option( 'plr_e_pre_' . $eid, '' );
            if ( $precio !== '' ) {
                if ( isset($est['precio_unit']) ) $est['precio_unit'] = (float)$precio;
                if ( isset($est['precio_base']) ) $est['precio_base'] = (float)$precio;
            }
            // Opciones personalizadas
            if ( ! empty($est['opciones']) ) {
                $ops = [];
                foreach ( $est['opciones'] as $op ) {
                    $oid = sanitize_key( $op['id'] );
                    if ( get_option( 'plr_o_act_' . $oid, '1' ) === '0' ) continue;
                    $op_nombre = get_option( 'plr_o_nom_' . $oid, '' );
                    if ( $op_nombre ) $op['label'] = $op_nombre;
                    // Precio fijo por unidad personalizado (ej: doble ventana)
                    if ( isset($op['precio_fijo_ud']) ) {
                        $op_precio = get_option( 'plr_o_pre_' . $oid, '' );
                        if ( $op_precio !== '' ) $op['precio_fijo_ud'] = (float)$op_precio;
                    }
                    $ops[] = $op;
                }
                $est['opciones'] = $ops;
            }
            $resultado[] = $est;
        }
        return $resultado;
    }

    private static function precio_estancia( string $est_id, float $default ) {
        if ( ! function_exists('get_option') ) return $default;
        $guardado = get_option( 'plr_e_pre_' . sanitize_key($est_id), '' );
        return $guardado !== '' ? (float)$guardado : $default;
    }

    // ── Versiones RAW para el panel admin (sin filtros de BD) ───
    public static function get_estancias_raw()            { return self::get_estancias_data(); }
    public static function get_estancias_cristales_raw()  { return self::get_estancias_cristales_data(); }
    public static function get_estancias_mantenimiento_raw() { return self::get_estancias_mantenimiento_data(); }
    public static function get_estancias_inmuebles_raw()  { return self::get_estancias_inmuebles_data(); }
    public static function get_estancias_trasteros_raw()  { return self::get_estancias_trasteros_data(); }
    public static function get_estancias_garajes_raw()    { return self::get_estancias_garajes_data(); }
    public static function get_estancias_vaciado_raw()    { return self::get_estancias_vaciado_data(); }

    // ── Cálculo principal ─────────────────────────────────────
    /**
     * Calcula el precio total del servicio.
     *
     * Para "casas" y categorías con multiplicador:
     *   precio_base = horas(m², grado) × tarifa_hora × multiplicador_tipo
     *
     * Las estancias suman por encima del precio base.
     * El grado de suciedad se extrae de las estancias seleccionadas
     * (el peor grado detectado por IA entre todas las estancias).
     */
    public static function calcular( int $m2, string $tipo_id, array $estancias_sel = [] ) {
        $tipo_id = sanitize_text_field( $tipo_id );
        $mults   = self::get_multiplicadores_categoria();

        if ( ! isset( $mults[$tipo_id] ) && null === self::get_categoria_de_tipo($tipo_id) ) {
            return new WP_Error( 'tipo_invalido', "Tipo '{$tipo_id}' no reconocido." );
        }

        // Si el admin personalizó el multiplicador, usarlo
        $mult_guardado = function_exists('get_option') ? get_option( 'plr_tipo_mult_' . $tipo_id, '' ) : '';
        $mult = $mult_guardado !== '' ? (float)$mult_guardado : ( $mults[$tipo_id] ?? 1.00 );
        $porcentaje = (int) get_option( 'plr_porcentaje_deposito', 50 );
        $tarifa     = (float) get_option( 'plr_precio_hora', 20.00 );

        // ── Cristales: precio por unidades ───────────────────────
        if ( in_array( $tipo_id, ['cristales_interior','cristales_exterior'], true ) ) {
            $est_cfg     = self::get_estancias_cristales();
            $est_valid   = array_column( $est_cfg, null, 'id' );
            $suma_extras = self::calcular_estancias_cristales( $estancias_sel, $est_valid );
            $total       = round( $suma_extras, 2 );
            $deposito    = round( $total * ( $porcentaje / 100 ), 2 );
            return [ 'base_m2' => 0, 'suma_extras' => $suma_extras, 'total' => $total, 'deposito' => $deposito, 'resto' => round($total - $deposito, 2), 'grado' => 'normal' ];
        }

        // ── Mantenimiento: horas × tarifa (mínimo 8h) ───────────
        if ( in_array( $tipo_id, ['mantenimiento_hogar','mantenimiento_oficina'], true ) ) {
            $horas         = max( 8, (int)($estancias_sel['horas'] ?? 8) );
            $tarifa_extra  = 0.0;
            // Comprobar si eligió modalidad con herramientas
            $modalidad_sel = $estancias_sel['mant_modalidad']['subopcion'] ?? '';
            if ( $modalidad_sel === 'con_herramientas' ) $tarifa_extra = 2.0;
            $tarifa_total = $tarifa + $tarifa_extra;
            $base_m2      = round( $horas * $tarifa_total, 2 );
            $total        = $base_m2;
            $deposito     = round( $total * ( $porcentaje / 100 ), 2 );
            return [ 'base_m2' => $base_m2, 'suma_extras' => 0, 'total' => $total, 'deposito' => $deposito, 'resto' => round($total - $deposito, 2), 'grado' => 'normal', 'horas' => $horas, 'tarifa_total' => $tarifa_total ];
        }

        // ── Resto de categorías: precio por horas × m² ──────────
        $grado = self::peor_grado( $estancias_sel );
        $base_m2 = self::precio_base_casas( max(0,$m2), $grado ) * $mult;

        $suma_extras = 0.0;
        if ( self::tiene_estancias($tipo_id) && ! empty($estancias_sel) ) {
            $est_cfg   = self::get_estancias_para_tipo( $tipo_id );
            $est_valid = array_column( $est_cfg, null, 'id' );
            $filtradas = array_filter( $estancias_sel, fn($k) => isset($est_valid[$k]), ARRAY_FILTER_USE_KEY );
            $suma_extras = self::calcular_estancias( $filtradas, $est_valid );
        }

        $total    = round( $base_m2 + $suma_extras, 2 );
        $deposito = round( $total * ( $porcentaje / 100 ), 2 );
        $resto    = round( $total - $deposito, 2 );

        return compact( 'base_m2', 'suma_extras', 'total', 'deposito', 'resto', 'grado' );
    }

    /**
     * Determina el peor grado de suciedad entre todas las estancias.
     * Si alguna tiene muy_sucia → muy_sucia. Si alguna avanzada → avanzada. Si no → normal.
     */
    private static function calcular_estancias_cristales( array $sel, array $cfg_map ) {
        $total = 0.0;
        foreach ( $sel as $est_id => $datos ) {
            $est_id = sanitize_text_field( $est_id );
            if ( ! isset( $cfg_map[$est_id] ) ) continue;
            $cfg  = $cfg_map[$est_id];
            $cant = max( 0, (int)($datos['cantidad'] ?? 0) );
            if ( $cant <= 0 ) continue;
            $sub = $cant * (float)$cfg['precio_unit'];
            // Opción doble ventana: precio_fijo_ud × cantidad
            $ops_sel = (array)($datos['opciones'] ?? []);
            $op_map  = array_column( $cfg['opciones'] ?? [], null, 'id' );
            foreach ( $ops_sel as $op_id ) {
                if ( ! isset($op_map[$op_id]) ) continue;
                $op = $op_map[$op_id];
                if ( isset($op['precio_fijo_ud']) ) $sub += $cant * (float)$op['precio_fijo_ud'];
                elseif ( isset($op['mod']) && $op['mod'] > 0 ) $sub += $sub * (float)$op['mod'];
            }
            $total += $sub;
        }
        return round( $total, 2 );
    }

    private static function peor_grado( array $estancias_sel ) {
        $orden = [ 'normal' => 0, 'avanzada' => 1, 'muy_sucia' => 2 ];
        $peor  = 'normal';
        foreach ( $estancias_sel as $datos ) {
            $g = $datos['grado_suciedad'] ?? 'normal';
            if ( ( $orden[$g] ?? 0 ) > ( $orden[$peor] ?? 0 ) ) {
                $peor = $g;
            }
        }
        return $peor;
    }

    /**
     * Calcula el coste de estancias adicionales.
     * Las estancias son extras sobre el precio base — no tienen multiplicador de suciedad
     * porque el grado ya se aplicó al precio base.
     */
    private static function calcular_estancias( array $sel, array $cfg_map ) {
        $total = 0.0;
        foreach ( $sel as $est_id => $datos ) {
            $est_id = sanitize_text_field( $est_id );
            if ( ! isset( $cfg_map[$est_id] ) ) continue;
            $cfg     = $cfg_map[$est_id];
            $op_map  = array_column( $cfg['opciones'] ?? [], null, 'id' );
            $ops_sel = array_map( 'sanitize_text_field', (array)($datos['opciones'] ?? []) );

            switch ( $cfg['tipo'] ) {
                case 'cantidad': {
                    $cant = max( 0, (int)($datos['cantidad'] ?? 0) );
                    $sub  = $cant * (float)$cfg['precio_unit'];
                    foreach ( $ops_sel as $op_id ) {
                        if ( ! isset($op_map[$op_id]) ) continue;
                        $op  = $op_map[$op_id];
                        $mod = (float)($op['mod'] ?? 0);
                        if ( isset($cfg['mod_tipo']) && 'fijo' === $cfg['mod_tipo'] ) $sub += $cant * $mod;
                        else if ( $mod > 0 ) $sub += $sub * $mod;
                    }
                    // Subopciones radio directas (tipo:'radio') — no necesitan checkbox padre
                    foreach ( $cfg['opciones'] ?? [] as $op ) {
                        if ( ($op['tipo'] ?? '') !== 'radio' ) continue;
                        $op_id   = sanitize_text_field( $op['id'] );
                        $sub_sel = sanitize_text_field( $datos['subopcion_' . $op_id] ?? '' );
                        if ( empty($sub_sel) ) continue;
                        $sub_map2 = array_column( $op['subopciones'] ?? [], null, 'id' );
                        if ( ! isset($sub_map2[$sub_sel]) ) continue;
                        $subop = $sub_map2[$sub_sel];
                        if ( isset($subop['precio_m2']) )       $sub += (float)$subop['precio_m2'] * max(0, (int)$m2);
                        elseif ( isset($subop['precio_fijo']) ) $sub += (float)$subop['precio_fijo'];
                        elseif ( isset($subop['mod']) && $subop['mod'] > 0 ) $sub += $sub * (float)$subop['mod'];
                    }
                    $total += $sub;
                    break;
                }
                case 'toggle': {
                    if ( empty($datos['activo']) ) break;
                    $sub = (float)$cfg['precio_base'];
                    foreach ( $ops_sel as $op_id ) {
                        if ( isset($op_map[$op_id]) ) $sub += $sub * (float)$op_map[$op_id]['mod'];
                    }
                    $total += $sub;
                    break;
                }
                case 'cantidad_m2': {
                    $total += max( 0, (float)($datos['m2'] ?? 0) ) * (float)$cfg['precio_unit'];
                    break;
                }
            }
        }
        return round( $total, 2 );
    }

    /**
     * Valida que el total del cliente ≈ total calculado en servidor.
     * Margen: 1€ por redondeos de interpolación JS.
     */
    public static function validar_total( int $m2, string $tipo_id, array $estancias_sel, float $total_cliente, float $km_coste = 0.0 ) {
        $calc = self::calcular( $m2, $tipo_id, $estancias_sel );
        if ( is_wp_error($calc) ) return false;
        // Sumar km al total calculado en servidor
        $total_servidor = round( $calc['total'] + $km_coste, 2 );
        // Margen de 2€ para cubrir diferencias de redondeo entre JS y PHP
        $diferencia = abs( $total_servidor - $total_cliente );
        if ( $diferencia > 2.00 ) {
            error_log( sprintf( '[PLR] Validación precio falló: cliente=%.2f servidor=%.2f km=%.2f diferencia=%.2f tipo=%s m2=%d',
                $total_cliente, $total_servidor, $km_coste, $diferencia, $tipo_id, $m2 ) );
            return false;
        }
        return true;
    }

    // get_precio_m2 se mantiene por compatibilidad con class-api-stripe
    public static function get_precio_m2( string $tipo_id ) {
        return 0; // No usado — precio calculado por horas
    }
}
