/**
 * Cotizador Frontend — Pintalimpio Reservas
 * Autor: Jhon Bastidas Rodrigues | controlhorariowp.com
 * v1.0.4
 */

( function () {
    'use strict';

    const cfg = window.PLR_Config || {};

    // ── Estado global ─────────────────────────────────────────
    const estado = {
        pasoActual:     1,
        categoriaId:    '',
        tipoId:         '',
        tipoLabel:      '',
        precioM2:       0,
        m2:             0,
        estancias:      {},
        fecha:          '',
        hora:           '',
        nombre:         '',
        email:          '',
        telefono:       '',
        direccion:      '',
        total:          0,
        deposito:       0,
        kmCoste:        0,
        metodoPago:     '',
        facturacion:    null, // null = no necesita factura
        observaciones:  '',
        reservaId:      null,
        clientSecret:   null,
        stripeElements: null,
        paymentElement: null,
    };

    let stripe = null;
    if ( cfg.stripe_pk ) stripe = Stripe( cfg.stripe_pk );

    // Exponer estado para funciones globales de plazas (fuera del IIFE)
    window._plrEstado     = estado;
    window._plrRecalcular = function() { calcularYActualizar(); };

    // ── Persistencia solo al navegar "Volver" ────────────────
    // Se usa sessionStorage SOLO para el botón "← Volver".
    // Al cargar/recargar la página se limpia inmediatamente,
    // así el cliente siempre empieza de cero en una recarga.
    const SESSION_KEY    = 'plr_cotizador_estado';
    const SESSION_NAV    = 'plr_cotizador_nav';   // flag de navegación activa

    // Limpiar SIEMPRE al cargar la página, antes de nada
    // Excepción: si venimos de un "Volver" (flag activo)
    ( function() {
        try {
            const esVolver = sessionStorage.getItem( SESSION_NAV ) === '1';
            if ( ! esVolver ) {
                // Recarga normal → borrar todo
                sessionStorage.removeItem( SESSION_KEY );
            }
            // Siempre limpiar el flag para que la próxima carga empiece limpia
            sessionStorage.removeItem( SESSION_NAV );
        } catch(_) {}
    } )();

    function guardarEstado() {
        try {
            const datos = {
                pasoActual:  estado.pasoActual,
                categoriaId: estado.categoriaId,
                tipoId:      estado.tipoId,
                tipoLabel:   estado.tipoLabel,
                precioM2:    estado.precioM2,
                m2:          estado.m2,
                estancias:   estado.estancias,
                fecha:       estado.fecha,
                hora:        estado.hora,
                nombre:      estado.nombre,
                email:       estado.email,
                telefono:    estado.telefono,
                direccion:   estado.direccion,
                total:       estado.total,
                deposito:    estado.deposito,
                kmCoste:     estado.kmCoste,
                metodoPago:  estado.metodoPago,
                facturacion: estado.facturacion,
                reservaId:   estado.reservaId,
                clientSecret:estado.clientSecret,
            };
            sessionStorage.setItem( SESSION_KEY, JSON.stringify(datos) );
        } catch(_) {}
    }

    function restaurarEstado() {
        try {
            const raw = sessionStorage.getItem( SESSION_KEY );
            if ( ! raw ) return false;
            const datos = JSON.parse( raw );
            if ( ! datos || ! datos.categoriaId ) return false;
            Object.assign( estado, datos );
            return true;
        } catch(_) { return false; }
    }

    function limpiarEstadoGuardado() {
        try {
            sessionStorage.removeItem( SESSION_KEY );
            sessionStorage.removeItem( SESSION_NAV );
        } catch(_) {}
    }

    // Activar el flag SOLO al pulsar "← Volver"
    // Se llama desde el handler del botón antes de irAPaso
    function marcarNavegacionVolver() {
        try { sessionStorage.setItem( SESSION_NAV, '1' ); } catch(_) {}
    }

    // ── Init ──────────────────────────────────────────────────
    document.addEventListener( 'DOMContentLoaded', () => {
        // ── Bloquear fechas en el input según admin ─────────────
        // El input[type=date] no soporta deshabilitar fechas individuales
        // Usamos validación al seleccionar + texto de aviso
        const fechasBloqueadas = Array.isArray(cfg.fechas_bloqueadas) ? cfg.fechas_bloqueadas : [];

        function esFechaBloqueada( valor ) {
            return fechasBloqueadas.includes( valor );
        }

        // ── Selector de fecha — compatible escritorio y móvil ────
        const fechaReal    = document.getElementById('plr-fecha');
        const fechaDisplay = document.getElementById('plr-fecha-display');

        function actualizarDisplay( valor ) {
            if ( ! valor ) return;
            if ( esFechaBloqueada( valor ) ) {
                fechaDisplay.value = '⛔ Fecha no disponible — elige otra';
                fechaDisplay.style.color       = '#e53e3e';
                fechaDisplay.style.borderColor = '#e53e3e';
                fechaReal.value = '';
                estado.fecha = '';
                return;
            }
            const partes = valor.split('-');
            if ( partes.length !== 3 ) return;
            fechaDisplay.value = partes[2] + '/' + partes[1] + '/' + partes[0];
            fechaDisplay.style.color       = '#2d3748';
            fechaDisplay.style.borderColor = '#1a7f5a';
        }

        if ( fechaReal && fechaDisplay ) {
            // Cuando cambia la fecha real → actualizar display Y cargar slots
            fechaReal.addEventListener('change', function() {
                actualizarDisplay( this.value );
                cargarSlots();
            });
            // En iOS el evento es 'input' no 'change'
            fechaReal.addEventListener('input', function() {
                actualizarDisplay( this.value );
                cargarSlots();
            });

            // Click en el display → abrir el picker nativo
            // En escritorio: showPicker() es la API correcta
            // En móvil: focus() abre el selector
            fechaDisplay.addEventListener('click', function(e) {
                e.preventDefault();
                try {
                    if ( fechaReal.showPicker ) {
                        fechaReal.showPicker(); // Chrome 99+, Safari 16+
                    } else {
                        fechaReal.focus();
                        fechaReal.click();
                    }
                } catch(err) {
                    fechaReal.focus();
                }
            });
        }

        // Ajuste responsive de inputs según pantalla — no depende del tema
        function ajustarInputs() {
            const m2    = document.getElementById('plr-m2');
            const fecha = document.getElementById('plr-fecha');
            const w     = window.innerWidth;
            if ( m2 ) {
                m2.style.fontSize  = w < 600 ? '26px' : '32px';
                m2.style.padding   = w < 600 ? '18px' : '18px 20px';
                m2.style.maxWidth  = w < 600 ? '100%' : '260px';
                m2.style.minHeight = w < 600 ? '64px' : '68px';
            }
            const fechaDisp = document.getElementById('plr-fecha-display');
            if ( fechaDisp ) {
                fechaDisp.style.fontSize  = w < 600 ? '16px' : '18px';
                fechaDisp.style.padding   = w < 600 ? '18px 16px' : '16px 18px';
                fechaDisp.style.minHeight = w < 600 ? '60px' : '58px';
            }
        }
        ajustarInputs();
        window.addEventListener('resize', ajustarInputs);
        // Aplicar color principal desde configuración admin
        if ( cfg.color_principal ) {
            document.documentElement.style.setProperty('--plr-verde', cfg.color_principal);
            // Generar versión clara del color para fondos
            document.documentElement.style.setProperty('--plr-verde-claro', cfg.color_principal + '18');
        }

        // Limpiar parámetros de Stripe de la URL SIEMPRE que estén presentes,
        // independientemente del resultado. Si no se limpian, cada recarga
        // vuelve a intentar procesar el pago y bloquea el cotizador.
        const params   = new URLSearchParams( window.location.search );
        const piId     = params.get('payment_intent');
        const piStatus = params.get('redirect_status');

        if ( piId ) {
            // Limpiar URL inmediatamente antes de hacer nada más
            window.history.replaceState( {}, document.title, window.location.pathname );

            if ( piStatus === 'succeeded' ) {
                // Pago exitoso — mostrar paso de éxito directamente
                renderCategorias();
                bindEventos();
                // Pequeño delay para que el DOM esté listo
                setTimeout( () => {
                    document.querySelectorAll('.plr-paso').forEach( p => p.classList.remove('activo') );
                    const pasoExito = document.querySelector('[data-paso="9"]');
                    if ( pasoExito ) pasoExito.classList.add('activo');
                    // Confirmar en servidor en segundo plano (el webhook ya lo habrá hecho)
                    fetch( cfg.rest_url + 'confirmar-pago', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
                        body: JSON.stringify({ reserva_id: 0, payment_intent_id: piId }),
                    }).catch( () => {} );
                }, 100 );
                return;
            }
            // Pago fallido o cancelado — iniciar cotizador normal desde paso 1
        }

        renderCategorias();
        bindEventos();

        // Restaurar estado guardado si existe
        if ( restaurarEstado() ) {
            restaurarUI();
        }
    } );

    // ═══════════════════════════════════════════════════════════
    // PASO 1 — Categorías
    // ═══════════════════════════════════════════════════════════
    function renderCategorias() {
        const cont = document.getElementById('plr-categorias');
        if ( ! cont ) return;

        if ( ! Array.isArray(cfg.catalogo) || cfg.catalogo.length === 0 ) {
            cont.innerHTML = '<p style="color:red;font-size:13px">Error al cargar el catálogo. Recarga la página.</p>';
            console.error('[PLR] cfg.catalogo inválido:', cfg.catalogo);
            return;
        }

        const iconos = { casas: '🏠', inmuebles: '🏢', traumaticas: '🧹', vaciado: '📦' };

        cfg.catalogo.forEach( cat => {
            const card      = document.createElement('div');
            card.className  = 'plr-categoria-card';
            card.dataset.id = cat.id;
            card.innerHTML  = `<div class="plr-cat-icono">${ iconos[cat.id] || '🔹' }</div>
                               <div class="plr-cat-label">${ esc(cat.label) }</div>`;
            card.addEventListener('click', () => seleccionarCategoria(card, cat));
            cont.appendChild(card);
        });
    }

    function seleccionarCategoria( card, cat ) {
        document.querySelectorAll('.plr-categoria-card').forEach( c => c.classList.remove('seleccionado') );
        card.classList.add('seleccionado');
        estado.categoriaId = cat.id;
        estado.tipoId      = '';
        estado.tipoLabel   = '';
        estado.precioM2    = 0;
        estado.estancias   = {};
        document.getElementById('plr-btn-paso1').disabled = false;
        renderTipos(cat);
    }

    // ═══════════════════════════════════════════════════════════
    // PASO 2 — Subcategorías
    // ═══════════════════════════════════════════════════════════
    function renderTipos( cat ) {
        const cont = document.getElementById('plr-tipos');
        if ( ! cont ) return;
        cont.innerHTML = '';
        document.getElementById('plr-titulo-paso2').textContent = cat.label + ' — ¿De qué tipo?';

        cat.tipos.forEach( tipo => {
            const card      = document.createElement('div');
            card.className  = 'plr-tipo-card';
            card.dataset.id = tipo.id;
            card.innerHTML  = `<div class="plr-tipo-label">${ esc(tipo.label) }</div>`;
            card.addEventListener('click', () => seleccionarTipo(card, tipo));
            cont.appendChild(card);
        });

        document.getElementById('plr-btn-paso2').disabled = true;
    }

    // ── Descripciones por tipo de servicio ──────────────────
    const DESCRIPCIONES_TIPO = {
        // CASAS
        habitada: {
            icono: '🏠',
            titulo: 'Limpieza a fondo — Vivienda habitada y amueblada',
            texto: 'Nuestro equipo de profesionales acudirá a tu domicilio y realizará la limpieza en un solo día con productos industriales, vaporetas a 180°C y aspiradoras profesionales. Solo trabajaremos en las estancias y áreas que tú mismo hayas seleccionado, respetando el mobiliario y los objetos personales. El resultado: tu casa completamente limpia y desinfectada, lista para seguir disfrutando de ella.',
        },
        estrenar: {
            icono: '🏡',
            titulo: 'Limpieza de vivienda a estrenar',
            texto: 'Tu nuevo hogar te espera impecable. Realizamos una limpieza inicial completa para eliminar el polvo de obra, residuos de pintura, adhesivos y cualquier resto del proceso constructivo. Utilizamos productos industriales y vaporetas a 180°C para dejarlo listo para entrar a vivir desde el primer día.',
        },
        fin_obra_sin: {
            icono: '🔨',
            titulo: 'Limpieza de fin de obra — Sin muebles',
            texto: 'La obra ha terminado, nosotros nos encargamos del resto. Limpieza post-obra especializada en viviendas vacías: eliminamos polvo de yeso, restos de cemento, manchas de pintura y cualquier residuo de construcción. Trabajamos con maquinaria industrial y productos específicos para este tipo de limpieza profunda.',
        },
        fin_obra_con: {
            icono: '🏗️',
            titulo: 'Limpieza de fin de obra — Con muebles',
            texto: 'Reforma terminada con muebles ya instalados. Nuestro equipo realiza una limpieza exhaustiva respetando todo el mobiliario, eliminando el polvo de obra, manchas de pintura y residuos de la construcción tanto en superficies como en rincones difíciles. Vaporetas a 180°C para una desinfección total.',
        },
        fin_alquiler_sin: {
            icono: '🔑',
            titulo: 'Limpieza de fin de alquiler — Sin muebles',
            texto: 'Devuelve el piso en perfectas condiciones y recupera tu fianza. Limpieza profunda de toda la vivienda vacía para dejarlo como el primer día: suelos, paredes, azulejos, baños y cocina con productos industriales y vaporetas a 180°C. Dejamos constancia fotográfica del estado final.',
        },
        fin_alquiler_con: {
            icono: '🏠',
            titulo: 'Limpieza de fin de alquiler — Con muebles',
            texto: 'Cambio de inquilino o entrega del piso amueblado. Limpieza integral de toda la vivienda incluyendo muebles, electrodomésticos y zonas comunes. Nuestro equipo lo deja en condiciones óptimas para el siguiente inquilino o para la inspección del propietario.',
        },
        cocinas: {
            icono: '🍳',
            titulo: 'Limpieza especializada de cocina',
            texto: 'La cocina es la estancia que más acumula grasa, cal y bacterias. Realizamos una desinfección completa con productos industriales desengrasantes y vaporetas a 180°C que eliminan la grasa incrustada, la cal del agua y cualquier bacteria. Azulejos, campana, horno, muebles por dentro y por fuera... lo dejamos como nuevo.',
        },
        // INMUEBLES
        oficinas: {
            icono: '🏢',
            titulo: 'Limpieza profesional de oficinas',
            texto: 'Un espacio de trabajo limpio mejora la productividad y transmite profesionalidad. Realizamos una limpieza integral de oficinas con productos específicos para entornos de trabajo: mobiliario, suelos, cristales, baños y zonas comunes. Trabajamos con discreción y eficiencia para no interrumpir tu actividad.',
        },
        locales: {
            icono: '🏪',
            titulo: 'Limpieza de locales y establecimientos',
            texto: 'La imagen de tu negocio empieza por la limpieza. Realizamos limpiezas integrales de locales comerciales, restaurantes, tiendas y cualquier tipo de establecimiento. Productos industriales y maquinaria profesional para resultados que no se consiguen con limpieza convencional.',
        },
        trasteros: {
            icono: '📦',
            titulo: 'Limpieza de trasteros',
            texto: 'Recupera tu espacio de almacenamiento. Limpiamos en profundidad trasteros de cualquier tamaño: barremos, fregamos, eliminamos el polvo acumulado en estanterías y superficies, y retiramos los residuos que nos indiques. Dejamos el espacio listo para organizar y aprovechar al máximo.',
        },
        garajes: {
            icono: '🚗',
            titulo: 'Limpieza de garajes y aparcamientos',
            texto: 'Garajes, parkings y zonas de aparcamiento requieren una limpieza específica. Eliminamos manchas de aceite, polvo, suciedad acumulada y residuos con maquinaria industrial. Fregadoras de alta presión y productos específicos para suelos de garaje. También limpiamos paredes y estructuras si es necesario.',
        },
        // TRAUMÁTICAS
        diogenes: {
            icono: '🧹',
            titulo: 'Limpieza del síndrome de Diógenes',
            texto: 'Servicio especializado y discreto para situaciones de acumulación extrema. Nuestro equipo trabaja con total confidencialidad y sensibilidad, realizando la retirada de enseres acumulados, desinfección profunda con productos bactericidas y fungicidas, y eliminación de malos olores. Dejamos el espacio en condiciones habitables con total profesionalidad y respeto.',
        },
        fallecimientos: {
            icono: '🕊️',
            titulo: 'Limpieza tras fallecimiento',
            texto: 'En los momentos difíciles, nosotros nos ocupamos de todo con el máximo respeto y discreción. Realizamos la limpieza y desinfección completa de la vivienda tras un fallecimiento: eliminación de fluidos biológicos si los hubiera, desinfección total con productos específicos, tratamiento de malos olores y puesta a punto del inmueble. Trabajo profesional, confidencial y humano.',
        },
        desahucios: {
            icono: '🏚️',
            titulo: 'Limpieza post-desahucio',
            texto: 'Recupera tu propiedad en condiciones óptimas. Realizamos la limpieza integral de inmuebles tras un desahucio: retirada de enseres abandonados, limpieza profunda de todas las estancias, desinfección y eliminación de malos olores. Dejamos la propiedad en condiciones para ser habitada, alquilada o vendida de nuevo.',
        },
        // VACIADO
        vaciado_viviendas: {
            icono: '🚛',
            titulo: 'Vaciado de vivienda completa',
            texto: 'Nos encargamos de todo el proceso de vaciado: retirada de muebles, enseres y objetos de cualquier vivienda. Clasificamos lo que se puede donar, lo que tiene valor y lo que va a vertedero autorizado. Tras el vaciado, realizamos una limpieza a fondo del inmueble para dejarlo listo para su venta, alquiler o reforma.',
        },
        vaciado_oficinas: {
            icono: '🏢',
            titulo: 'Vaciado de oficinas y locales',
            texto: 'Cierre de negocio, traslado o liquidación: gestionamos el vaciado completo de oficinas y locales comerciales. Retirada de mobiliario de oficina, equipos, archivos y enseres. Gestión responsable de los residuos y limpieza final del local. Rapidez y eficiencia para que puedas devolver las llaves cuanto antes.',
        },
        vaciado_trasteros: {
            icono: '📦',
            titulo: 'Vaciado de trasteros',
            texto: 'Vacía tu trastero sin esfuerzo. Retiramos todo el contenido, clasificamos lo reutilizable y gestionamos el resto en vertedero autorizado. Servicio rápido y eficiente para que recuperes tu espacio sin que tengas que mover un solo dedo. Limpieza final incluida.',
        },
    };

    function seleccionarTipo( card, tipo ) {
        document.querySelectorAll('.plr-tipo-card').forEach( c => c.classList.remove('seleccionado') );
        card.classList.add('seleccionado');
        estado.tipoId    = tipo.id;
        estado.tipoLabel = tipo.label;
        estado.precioM2  = tipo.precio_m2;
        estado.estancias = {};
        document.getElementById('plr-btn-paso2').disabled = false;
        calcularYActualizar();

        // Mostrar descripción contextual del tipo seleccionado
        const desc = DESCRIPCIONES_TIPO[tipo.id];
        const wrap = document.getElementById('plr-tipo-descripcion');
        if ( wrap && desc ) {
            // Usamos textContent para el texto (evita XSS sin romper tildes)
            wrap.innerHTML = '<div class="plr-tipo-desc-card"><div class="plr-tipo-desc-icono"></div><div><strong class="plr-tipo-desc-titulo"></strong><p class="plr-tipo-desc-texto"></p></div></div>';
            wrap.querySelector('.plr-tipo-desc-icono').textContent  = desc.icono;
            wrap.querySelector('.plr-tipo-desc-titulo').textContent = desc.titulo;
            wrap.querySelector('.plr-tipo-desc-texto').textContent  = desc.texto;
            wrap.style.display = '';
        } else if ( wrap ) {
            wrap.style.display = 'none';
        }
    }

    // ═══════════════════════════════════════════════════════════
    // PASO 4 — Estancias
    // ═══════════════════════════════════════════════════════════
    function getEstanciasFiltradas() {
        const t = estado.tipoId;
        // Vaciado — usa su propio catálogo de items a retirar
        if ( t === 'vaciado_viviendas' || t === 'vaciado_oficinas' || t === 'vaciado_trasteros' ) {
            return Array.isArray(cfg.estancias_vaciado) ? cfg.estancias_vaciado : [];
        }
        if ( t === 'oficinas' )  return Array.isArray(cfg.estancias_inmuebles)    ? cfg.estancias_inmuebles    : [];
        if ( t === 'trasteros' ) return Array.isArray(cfg.estancias_trasteros)    ? cfg.estancias_trasteros    : [];
        if ( t === 'garajes' )   return Array.isArray(cfg.estancias_garajes)      ? cfg.estancias_garajes      : [];
        if ( t === 'cristales_interior' || t === 'cristales_exterior' )
                                 return Array.isArray(cfg.estancias_cristales)    ? cfg.estancias_cristales    : [];
        if ( t === 'mantenimiento_hogar' || t === 'mantenimiento_oficina' )
                                 return Array.isArray(cfg.estancias_mantenimiento)? cfg.estancias_mantenimiento: [];
        if ( ! Array.isArray(cfg.estancias) ) return [];
        if ( t === 'cocinas' ) return cfg.estancias.filter( e => e.id === 'cocina' );
        if ( estado.categoriaId === 'casas' )         return cfg.estancias;
        if ( estado.categoriaId === 'traumaticas' )   return cfg.estancias;
        if ( estado.categoriaId === 'cristales' )     return Array.isArray(cfg.estancias_cristales)    ? cfg.estancias_cristales    : [];
        if ( estado.categoriaId === 'mantenimiento' ) return Array.isArray(cfg.estancias_mantenimiento)? cfg.estancias_mantenimiento : [];
        return [];
    }

    function renderEstancias() {
        const wrap = document.getElementById('plr-estancias-wrap');
        if ( ! wrap ) return;
        wrap.innerHTML = '';

        const visibles    = getEstanciasFiltradas();
        const esCristales = ['cristales_interior','cristales_exterior'].includes(estado.tipoId);
        const esMant      = ['mantenimiento_hogar','mantenimiento_oficina'].includes(estado.tipoId);

        if ( visibles.length === 0 ) {
            wrap.innerHTML = '<p style="color:#718096;font-style:italic">No hay elementos adicionales para este tipo de servicio.</p>';
            return;
        }

        visibles.forEach( est => {
            const bloque = document.createElement('div');
            bloque.className = 'plr-estancia-bloque';

            // Cristales: input manual sin fotos
            // Mantenimiento: toggle informativo sin fotos
            if ( esCristales && est.tipo === 'cantidad' )             bloque.innerHTML = renderEstanciaCristales(est);
            else if ( esMant && ( est.tipo === 'toggle' || est.tipo === 'toggle_radio' ) ) bloque.innerHTML = renderEstanciaToggleSinFotos(est);
            else if ( est.tipo === 'toggle' )             bloque.innerHTML = renderEstanciaToggle(est);
            else if ( est.tipo === 'cantidad' )           bloque.innerHTML = renderEstanciaCantidad(est);
            else if ( est.tipo === 'cantidad_m2' )        bloque.innerHTML = renderEstanciaM2(est);

            wrap.appendChild(bloque);
            bindFotosEventos(bloque, est);
            bindEstanciaEventos(bloque, est);

            // Restaurar selección guardada para esta estancia
            const datosGuardados = estado.estancias[est.id];
            if ( datosGuardados ) {
                if ( est.tipo === 'toggle' && datosGuardados.activo ) {
                    const toggle = bloque.querySelector('.plr-est-toggle-input');
                    if ( toggle ) {
                        toggle.checked = true;
                        const ops   = document.getElementById('plr-ops-'   + est.id);
                        const fotos = document.getElementById('plr-fotos-' + est.id);
                        if ( ops )   ops.style.display   = '';
                        if ( fotos ) fotos.style.display  = '';
                    }
                }
                if ( est.tipo === 'cantidad' && datosGuardados.cantidad > 0 ) {
                    const display = document.getElementById('plr-cant-' + est.id);
                    if ( display ) display.textContent = datosGuardados.cantidad;
                    const ops   = document.getElementById('plr-ops-'   + est.id);
                    const fotos = document.getElementById('plr-fotos-' + est.id);
                    if ( ops )   ops.style.display   = '';
                    if ( fotos ) fotos.style.display  = '';
                }
                if ( est.tipo === 'cantidad_m2' && datosGuardados.m2 > 0 ) {
                    const input = bloque.querySelector('.plr-est-m2-input');
                    if ( input ) input.value = datosGuardados.m2;
                    const fotos = document.getElementById('plr-fotos-' + est.id);
                    if ( fotos ) fotos.style.display = '';
                }
                // Restaurar plazas de sofá si las hay
                Object.keys(datosGuardados).forEach( key => {
                    if ( key.startsWith('plazas_') ) {
                        const opId  = key.replace('plazas_', '');
                        const input = bloque.querySelector(`.plr-plazas-input[data-op-id="${opId}"]`);
                        if ( input ) input.value = datosGuardados[key];
                    }
                    if ( key.startsWith('m2_') ) {
                        const opId  = key.replace('m2_', '');
                        const input = bloque.querySelector(`.plr-m2-alf-input[data-op-id="${opId}"]`);
                        if ( input ) input.value = datosGuardados[key];
                    }
                    if ( key.startsWith('subopcion_') ) {
                        const opId  = key.replace('subopcion_', '');
                        const subId = datosGuardados[key];
                        const radio = bloque.querySelector(`.plr-est-subopcion-input[data-op-id="${opId}"][data-sub-id="${subId}"]`);
                        if ( radio ) {
                            radio.checked = true;
                            const subWrap = document.getElementById(`plr-subops-${est.id}-${opId}`);
                            if ( subWrap ) subWrap.style.display = '';
                        }
                    }
                });

                // Restaurar opciones marcadas
                if ( datosGuardados.opciones && datosGuardados.opciones.length ) {
                    datosGuardados.opciones.forEach( opId => {
                        const opInput = bloque.querySelector(`.plr-est-opcion-input[data-op-id="${opId}"]`);
                        if ( opInput ) opInput.checked = true;
                    });
                }
            }
        });

        calcularYActualizar();
    }

    // ── Bloque fotos ──────────────────────────────────────────
    function renderFotos( est ) {
        return `
        <div class="plr-fotos-wrap" id="plr-fotos-${ esc(est.id) }" style="display:none">
            <div class="plr-fotos-aviso">
                📷 <strong>Por favor, sube imágenes actuales de la vivienda.</strong>
                Las fotos reales nos ayudan a preparar el servicio correctamente.
            </div>
            <div class="plr-fotos-zona" id="plr-fotos-zona-${ esc(est.id) }">
                <label class="plr-foto-btn plr-btn-galeria" for="plr-foto-galeria-${ esc(est.id) }">
                    🖼 Galería
                    <input type="file" id="plr-foto-galeria-${ esc(est.id) }"
                        class="plr-foto-input" data-est-id="${ esc(est.id) }" data-est-label="${ esc(est.label) }"
                        accept="image/jpeg,image/png,image/webp" multiple style="display:none">
                </label>
                <label class="plr-foto-btn plr-foto-btn-camara plr-btn-camara" for="plr-foto-camara-${ esc(est.id) }">
                    📸 Tomar foto
                    <input type="file" id="plr-foto-camara-${ esc(est.id) }"
                        class="plr-foto-input" data-est-id="${ esc(est.id) }" data-est-label="${ esc(est.label) }"
                        accept="image/jpeg,image/png,image/webp" capture="environment" style="display:none">
                </label>
            </div>
            <div class="plr-fotos-preview"  id="plr-fotos-preview-${ esc(est.id) }"></div>
            <div class="plr-fotos-contador" id="plr-fotos-contador-${ esc(est.id) }"></div>
            <div class="plr-fotos-estado"   id="plr-fotos-estado-${ esc(est.id) }"></div>
        </div>`;
    }

    // ── Cristales: input manual + opción doble ventana ────────
    function renderEstanciaCristales( est ) {
        const tieneOpciones = est.opciones && est.opciones.length > 0;
        return `
        <div class="plr-est-header">
            <div class="plr-est-info">
                <span class="plr-est-icono">${ esc(est.icono) }</span>
                <span class="plr-est-label">${ esc(est.label) }</span>
            </div>
            <div class="plr-m2-input-wrap">
                <input type="number" class="plr-est-cantidad-input" data-est-id="${ esc(est.id) }"
                    min="0" max="200" value="0" placeholder="0"
                    style="width:65px;padding:6px 8px;border:1px solid var(--plr-borde);border-radius:6px;font-size:15px;text-align:center;font-weight:bold">
                <span style="font-size:13px;color:#718096"> uds.</span>
            </div>
        </div>
        ${ tieneOpciones ? `
        <div class="plr-est-opciones" id="plr-ops-${ esc(est.id) }" style="display:none">
            ${ est.opciones.map( op => `
            <label class="plr-opcion">
                <input type="checkbox" class="plr-est-opcion-input"
                    data-est-id="${ esc(est.id) }" data-op-id="${ esc(op.id) }">
                <span class="plr-opcion-label">${ esc(op.label) }</span>
            </label>` ).join('') }
        </div>` : '' }`;
    }

    // ── Toggle sin fotos (mantenimiento — items informativos) ──
    function renderEstanciaToggleSinFotos( est ) {
        // Modalidad especial con radio buttons
        if ( est.tipo === 'toggle_radio' && est.opciones && est.opciones.length ) {
            return `
            <div class="plr-est-header" style="flex-direction:column;align-items:flex-start;gap:10px">
                <div class="plr-est-info">
                    <span class="plr-est-icono">${ esc(est.icono) }</span>
                    <span class="plr-est-label" style="font-weight:700">${ esc(est.label) }</span>
                </div>
                <div class="plr-modalidad-opciones" style="width:100%;display:flex;flex-direction:column;gap:8px">
                    ${ est.opciones.map( op => `
                    <label class="plr-subopcion" style="cursor:pointer">
                        <input type="radio" class="plr-mant-modalidad-input"
                            name="plr_mant_modalidad"
                            data-est-id="${ esc(est.id) }" data-sub-id="${ esc(op.id) }"
                            style="accent-color:var(--plr-verde);width:16px;height:16px;flex-shrink:0">
                        <span class="plr-subopcion-label">${ esc(op.label) }</span>
                    </label>` ).join('') }
                </div>
            </div>`;
        }
        return `
        <div class="plr-est-header">
            <div class="plr-est-info">
                <span class="plr-est-icono">${ esc(est.icono) }</span>
                <span class="plr-est-label">${ esc(est.label) }</span>
            </div>
            <label class="plr-toggle">
                <input type="checkbox" class="plr-est-toggle-input" data-est-id="${ esc(est.id) }">
                <span class="plr-toggle-slider"></span>
            </label>
        </div>`;
    }

    // ── Toggle ────────────────────────────────────────────────
    function renderEstanciaToggle( est ) {
        const tieneOpciones = est.opciones && est.opciones.length > 0;
        const tieneDesc     = est.descripcion && est.descripcion.length > 0;
        return `
        <div class="plr-est-header">
            <div class="plr-est-info">
                <span class="plr-est-icono">${ esc(est.icono) }</span>
                <div class="plr-est-info-texto">
                    <span class="plr-est-label">${ esc(est.label) }</span>
                    ${ tieneDesc ? `<span class="plr-est-desc">${ esc(est.descripcion) }</span>` : '' }
                </div>
            </div>
            <label class="plr-toggle">
                <input type="checkbox" class="plr-est-toggle-input" data-est-id="${ esc(est.id) }">
                <span class="plr-toggle-slider"></span>
            </label>
        </div>
        ${ tieneOpciones ? `
        <div class="plr-est-opciones" id="plr-ops-${ esc(est.id) }" style="display:none">
            <p class="plr-opciones-titulo">¿Qué quieres incluir?</p>
            ${ est.opciones.map( op => op.subopciones ? renderOpcionConSubopciones(est, op) : `
            <label class="plr-opcion">
                <input type="checkbox" class="plr-est-opcion-input"
                    data-est-id="${ esc(est.id) }" data-op-id="${ esc(op.id) }">
                <span class="plr-opcion-label">${ esc(op.label) }</span>
            </label>` ).join('') }
        </div>` : '' }
        ${ renderFotos(est) }`;
    }

    // ── Opción con subopciones tipo radio + plazas ────────────
    function renderOpcionConSubopciones( est, op ) {
        const esPlazas  = op.tipo === 'radio_cantidad' || op.tipo === 'radio_cantidad_m2';
        const esM2      = op.tipo === 'radio_cantidad_m2';
        const esRadio   = op.tipo === 'radio'; // radio visible directamente sin checkbox padre

        if ( esRadio ) {
            return `
        <div class="plr-opcion-grupo plr-opcion-radio-directo">
            <p style="font-size:12px;font-weight:600;color:#718096;text-transform:uppercase;letter-spacing:.5px;margin:8px 0 6px">${ esc(op.label) }</p>
            ${ op.subopciones.map( sub => `
            <label class="plr-subopcion" style="display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:6px;cursor:pointer;margin-bottom:4px;background:#f7fafc">
                <input type="radio" class="plr-est-subopcion-input"
                    name="plr_subop_${ esc(est.id) }_${ esc(op.id) }"
                    data-est-id="${ esc(est.id) }" data-op-id="${ esc(op.id) }" data-sub-id="${ esc(sub.id) }"
                    style="accent-color:var(--plr-verde);width:16px;height:16px;flex-shrink:0">
                <span style="font-size:13px;color:#2d3748">${ esc(sub.label) }</span>
            </label>` ).join('') }
        </div>`;
        }

        return `
        <div class="plr-opcion-grupo">
            <div class="plr-opcion plr-opcion-padre" style="display:flex;align-items:center;gap:8px;padding:7px 12px;border:1px solid var(--plr-borde);border-radius:6px;cursor:pointer;background:#fff">
                <input type="checkbox" class="plr-est-opcion-input"
                    data-est-id="${ esc(est.id) }" data-op-id="${ esc(op.id) }"
                    style="width:16px;height:16px;accent-color:var(--plr-verde);cursor:pointer;flex-shrink:0">
                <span class="plr-opcion-label" style="font-size:14px;color:#2d3748;cursor:pointer">${ esc(op.label) }</span>
            </div>
            <div class="plr-subopciones" id="plr-subops-${ esc(est.id) }-${ esc(op.id) }"
                style="display:none">
                ${ esPlazas ? `
                <div class="plr-plazas-wrap">
                    <span class="plr-plazas-label">${ esM2 ? 'Número de alfombras:' : 'Número de plazas:' }</span>
                    <input type="number" class="plr-plazas-input"
                        id="plr-plazas-${ esc(est.id) }-${ esc(op.id) }"
                        data-est-id="${ esc(est.id) }" data-op-id="${ esc(op.id) }"
                        min="1" max="20" value="" placeholder="0"
                        style="width:65px;padding:6px 8px;border:1px solid #e2e8f0;border-radius:6px;font-size:15px;text-align:center;font-weight:bold">
                </div>` : '' }
                ${ esM2 ? `
                <div class="plr-plazas-wrap">
                    <span class="plr-plazas-label">m² aproximados por alfombra:</span>
                    <input type="number" class="plr-m2-alf-input"
                        id="plr-m2alf-${ esc(est.id) }-${ esc(op.id) }"
                        data-est-id="${ esc(est.id) }" data-op-id="${ esc(op.id) }"
                        min="0.5" max="50" step="0.5" value="" placeholder="0"
                        style="width:65px;padding:6px 8px;border:1px solid #e2e8f0;border-radius:6px;font-size:15px;text-align:center;font-weight:bold">
                </div>` : '' }
                ${ op.subopciones.map( sub => `
                <label class="plr-subopcion" onclick="event.stopPropagation()">
                    <input type="radio" class="plr-est-subopcion-input"
                        name="plr_subop_${ esc(est.id) }_${ esc(op.id) }"
                        data-est-id="${ esc(est.id) }" data-op-id="${ esc(op.id) }" data-sub-id="${ esc(sub.id) }"
                        onclick="event.stopPropagation()">
                    <span class="plr-subopcion-label">${ esc(sub.label) }</span>
                </label>` ).join('') }
            </div>
        </div>`;
    }

    // ── Cantidad ──────────────────────────────────────────────
    function renderEstanciaCantidad( est ) {
        const tieneOpciones   = est.opciones && est.opciones.length > 0;
        const opcionesRadio   = tieneOpciones ? est.opciones.filter( op => op.tipo === 'radio' ) : [];
        const opcionesNormal  = tieneOpciones ? est.opciones.filter( op => op.tipo !== 'radio' ) : [];
        return `
        <div class="plr-est-header">
            <div class="plr-est-info">
                <span class="plr-est-icono">${ esc(est.icono) }</span>
                <span class="plr-est-label">${ esc(est.label) }</span>
            </div>
            <div class="plr-contador">
                <button type="button" class="plr-contador-btn plr-contador-menos" data-est-id="${ esc(est.id) }">−</button>
                <span class="plr-contador-val" id="plr-cant-${ esc(est.id) }">0</span>
                <button type="button" class="plr-contador-btn plr-contador-mas" data-est-id="${ esc(est.id) }">+</button>
            </div>
        </div>
        ${ opcionesRadio.length ? opcionesRadio.map( op => renderOpcionConSubopciones(est, op) ).join('') : '' }
        ${ opcionesNormal.length ? `
        <div class="plr-est-opciones" id="plr-ops-${ esc(est.id) }" style="display:none">
            <p class="plr-opciones-titulo">¿Qué quieres incluir?</p>
            ${ opcionesNormal.map( op => op.subopciones ? renderOpcionConSubopciones(est, op) : `
            <label class="plr-opcion">
                <input type="checkbox" class="plr-est-opcion-input"
                    data-est-id="${ esc(est.id) }" data-op-id="${ esc(op.id) }">
                <span class="plr-opcion-label">${ esc(op.label) }</span>
            </label>` ).join('') }
        </div>` : '' }
        ${ renderFotos(est) }`;
    }

    // ── Bind fotos ────────────────────────────────────────────
    function bindFotosEventos( bloque, est ) {
        bloque.querySelectorAll('.plr-foto-input').forEach( input => {
            input.addEventListener('change', () => manejarFotos(input, est));
        });
    }

    async function manejarFotos( input, est ) {
        const files = Array.from(input.files).slice(0, 3);
        if ( ! files.length ) return;

        const preview    = document.getElementById('plr-fotos-preview-'  + est.id);
        const estadoEl   = document.getElementById('plr-fotos-estado-'   + est.id);
        const contadorEl = document.getElementById('plr-fotos-contador-' + est.id);

        const yaSubidas = estado.estancias[est.id]?.attachment_ids?.length || 0;
        const restantes = 3 - yaSubidas;
        if ( restantes <= 0 ) {
            estadoEl.textContent = '⚠️ Máximo 3 fotos por estancia.';
            estadoEl.className   = 'plr-fotos-estado plr-fotos-error';
            input.value = '';
            return;
        }

        const filesToUpload = files.slice(0, restantes);
        estadoEl.textContent = `📤 Subiendo ${ filesToUpload.length } foto${ filesToUpload.length > 1 ? 's' : '' }...`;
        estadoEl.className   = 'plr-fotos-estado plr-fotos-subiendo';

        document.querySelectorAll(`.plr-foto-input[data-est-id="${ est.id }"]`).forEach( i => i.disabled = true );

        const attIds = [];
        for ( const file of filesToUpload ) {
            const fd = new FormData();
            fd.append('foto',        file);
            fd.append('estancia_id', est.id);
            fd.append('reserva_id',  estado.reservaId || 0);
            try {
                const res  = await fetch(cfg.rest_url + 'subir-foto', { method: 'POST', headers: { 'X-WP-Nonce': cfg.nonce }, body: fd });
                const data = await res.json();
                if ( res.ok && data.attachment_id ) {
                    attIds.push(data.attachment_id);
                    const wrap = document.createElement('div');
                    wrap.className = 'plr-foto-thumb-wrap';
                    wrap.innerHTML = `<img src="${ esc(data.thumb || data.url) }" class="plr-foto-thumb" alt="">
                        <button type="button" class="plr-foto-eliminar" data-att-id="${ data.attachment_id }" data-est-id="${ esc(est.id) }">✕</button>`;
                    preview.appendChild(wrap);
                    wrap.querySelector('.plr-foto-eliminar').addEventListener('click', () => eliminarFoto(data.attachment_id, est.id, wrap));
                }
            } catch(_) {}
        }

        document.querySelectorAll(`.plr-foto-input[data-est-id="${ est.id }"]`).forEach( i => i.disabled = false );
        input.value = '';

        if ( ! attIds.length ) {
            estadoEl.textContent = '⚠️ Error al subir las fotos.';
            estadoEl.className   = 'plr-fotos-estado plr-fotos-error';
            return;
        }

        if ( ! estado.estancias[est.id] ) estado.estancias[est.id] = { opciones: [] };
        if ( ! estado.estancias[est.id].attachment_ids ) estado.estancias[est.id].attachment_ids = [];
        estado.estancias[est.id].attachment_ids.push(...attIds);

        const total = estado.estancias[est.id].attachment_ids.length;
        contadorEl.textContent = `${ total } foto${ total !== 1 ? 's' : '' } subida${ total !== 1 ? 's' : '' }`;
        if ( total >= 3 ) {
            document.querySelectorAll(`.plr-foto-input[data-est-id="${ est.id }"]`).forEach( i => i.closest('label').style.display = 'none');
        }

        estadoEl.textContent = '🤖 Analizando imágenes...';
        estadoEl.className   = 'plr-fotos-estado plr-fotos-analizando';

        try {
            const res  = await fetch(cfg.rest_url + 'analizar-estancia', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
                body: JSON.stringify({ attachment_ids: estado.estancias[est.id].attachment_ids, estancia_label: est.label }),
            });
            const data = await res.json();
            estado.estancias[est.id].grado_suciedad = data.grado || 'normal';
        } catch(_) {
            estado.estancias[est.id].grado_suciedad = 'normal';
        }

        estadoEl.textContent = '✅ Fotos recibidas correctamente. ¡Gracias!';
        estadoEl.className   = 'plr-fotos-estado plr-fotos-ok';
        calcularYActualizar();
    }

    function eliminarFoto( attId, estId, wrapEl ) {
        if ( estado.estancias[estId]?.attachment_ids ) {
            estado.estancias[estId].attachment_ids = estado.estancias[estId].attachment_ids.filter( id => id !== attId );
        }
        wrapEl.remove();
        const total      = estado.estancias[estId]?.attachment_ids?.length || 0;
        const contadorEl = document.getElementById('plr-fotos-contador-' + estId);
        if ( contadorEl ) contadorEl.textContent = total > 0 ? `${ total } foto${ total !== 1 ? 's' : '' } subida${ total !== 1 ? 's' : '' }` : '';
        if ( total < 3 ) {
            document.querySelectorAll(`.plr-foto-input[data-est-id="${ estId }"]`).forEach( i => i.closest('label').style.display = '');
        }
        if ( estado.estancias[estId] ) {
            estado.estancias[estId].grado_suciedad = total > 0 ? estado.estancias[estId].grado_suciedad : 'normal';
        }
        calcularYActualizar();
    }

    // ── Bind eventos estancias ────────────────────────────────
    function bindEstanciaEventos( bloque, est ) {
        const toggleInput = bloque.querySelector('.plr-est-toggle-input');
        if ( toggleInput ) {
            toggleInput.addEventListener('change', () => {
                const activo = toggleInput.checked;
                if ( ! estado.estancias[est.id] ) estado.estancias[est.id] = { opciones: [] };
                estado.estancias[est.id].activo = activo;
                const ops   = document.getElementById('plr-ops-'   + est.id);
                const fotos = document.getElementById('plr-fotos-' + est.id);
                if ( ops )   ops.style.display   = activo ? '' : 'none';
                if ( fotos ) fotos.style.display  = activo ? '' : 'none';
                if ( ! activo ) {
                    bloque.querySelectorAll('.plr-est-opcion-input').forEach( i => i.checked = false );
                    estado.estancias[est.id].opciones = [];
                }
                calcularYActualizar();
            });
        }

        bloque.querySelectorAll('.plr-contador-btn').forEach( btn => {
            btn.addEventListener('click', () => {
                if ( ! estado.estancias[est.id] ) estado.estancias[est.id] = { cantidad: 0, opciones: [] };
                let cant = estado.estancias[est.id].cantidad || 0;
                if ( btn.classList.contains('plr-contador-mas') )  cant = Math.min(cant + 1, 99);
                if ( btn.classList.contains('plr-contador-menos') ) cant = Math.max(cant - 1, 0);
                estado.estancias[est.id].cantidad = cant;
                const display = document.getElementById('plr-cant-' + est.id);
                if ( display ) display.textContent = cant;
                const ops   = document.getElementById('plr-ops-'   + est.id);
                const fotos = document.getElementById('plr-fotos-' + est.id);
                if ( ops )   ops.style.display   = cant > 0 ? '' : 'none';
                if ( fotos ) fotos.style.display  = cant > 0 ? '' : 'none';
                if ( cant === 0 ) {
                    bloque.querySelectorAll('.plr-est-opcion-input').forEach( i => i.checked = false );
                    estado.estancias[est.id].opciones = [];
                }
                calcularYActualizar();
            });
        });

        const m2input = bloque.querySelector('.plr-est-m2-input');
        if ( m2input ) {
            m2input.addEventListener('input', () => {
                if ( ! estado.estancias[est.id] ) estado.estancias[est.id] = {};
                estado.estancias[est.id].m2 = parseFloat(m2input.value) || 0;
                const fotos = document.getElementById('plr-fotos-' + est.id);
                if ( fotos ) fotos.style.display = (estado.estancias[est.id].m2 > 0) ? '' : 'none';
                calcularYActualizar();
            });
        }

        bloque.querySelectorAll('.plr-est-opcion-input').forEach( input => {
            // Click en el div padre (no es label, hay que bind manual)
            const padre = input.closest('.plr-opcion-grupo')?.querySelector('.plr-opcion-padre');
            if ( padre && ! padre.tagName === 'LABEL' ) {
                padre.addEventListener('click', (e) => {
                    if ( e.target === input ) return; // el click ya fue al checkbox
                    input.checked = ! input.checked;
                    input.dispatchEvent( new Event('change') );
                });
            }

            input.addEventListener('change', () => {
                const estId = input.dataset.estId;
                const opId  = input.dataset.opId;
                if ( ! estado.estancias[estId] ) estado.estancias[estId] = { opciones: [] };
                if ( ! estado.estancias[estId].opciones ) estado.estancias[estId].opciones = [];
                if ( input.checked ) {
                    if ( ! estado.estancias[estId].opciones.includes(opId) ) estado.estancias[estId].opciones.push(opId);
                    // Mostrar subopciones si las tiene
                    const subWrap = document.getElementById(`plr-subops-${estId}-${opId}`);
                    if ( subWrap ) subWrap.style.display = '';
                } else {
                    estado.estancias[estId].opciones = estado.estancias[estId].opciones.filter( o => o !== opId );
                    // Ocultar subopciones y limpiar selección
                    const subWrap = document.getElementById(`plr-subops-${estId}-${opId}`);
                    if ( subWrap ) {
                        subWrap.style.display = 'none';
                        subWrap.querySelectorAll('.plr-est-subopcion-input').forEach( r => r.checked = false );
                    }
                    delete estado.estancias[estId][`subopcion_${opId}`];
                }
                calcularYActualizar();
            });
        });

        // Input de horas para mantenimiento
        // Cláusulas mantenimiento — habilitar botón siguiente al aceptar
        document.getElementById('plr-clausulas-check')?.addEventListener('change', function() {
            const btn = document.getElementById('plr-btn-paso4');
            if ( btn ) btn.disabled = ! this.checked;
        });

        document.getElementById('plr-horas-mantenimiento')?.addEventListener('input', function() {
            const val = parseInt(this.value) || 0;
            if ( val > 0 && val < 8 ) {
                this.style.borderColor = '#e53e3e';
                this.title = 'Mínimo 8 horas';
            } else {
                this.style.borderColor = 'var(--plr-verde)';
                this.title = '';
            }
            estado.estancias.horas = val >= 8 ? val : val === 0 ? 0 : 8;
            calcularYActualizar();
        });
        document.getElementById('plr-horas-mantenimiento')?.addEventListener('blur', function() {
            const val = parseInt(this.value) || 0;
            if ( val > 0 && val < 8 ) {
                this.value = 8;
                this.style.borderColor = 'var(--plr-verde)';
                estado.estancias.horas = 8;
                calcularYActualizar();
            }
        });

        // Modalidad mantenimiento (radio)
        bloque.querySelectorAll('.plr-mant-modalidad-input').forEach( input => {
            input.addEventListener('change', () => {
                const estId = input.dataset.estId;
                const subId = input.dataset.subId;
                if ( ! estado.estancias[estId] ) estado.estancias[estId] = { opciones: [] };
                estado.estancias[estId].subopcion = subId;
                calcularYActualizar();
            });
        });

        // Input manual de cantidad (cristales)
        bloque.querySelectorAll('.plr-est-cantidad-input').forEach( input => {
            input.addEventListener('input', () => {
                const estId = input.dataset.estId;
                const cant  = Math.max( 0, parseInt(input.value) || 0 );
                input.value = cant;
                if ( ! estado.estancias[estId] ) estado.estancias[estId] = { opciones: [] };
                estado.estancias[estId].cantidad = cant;
                const ops = document.getElementById('plr-ops-' + estId);
                if ( ops ) ops.style.display = cant > 0 ? '' : 'none';
                calcularYActualizar();
            });
        });

        // Input manual de plazas (sofá)
        bloque.querySelectorAll('.plr-plazas-input').forEach( input => {
            input.addEventListener('input', () => {
                const estId  = input.dataset.estId;
                const opId   = input.dataset.opId;
                const plazas = Math.min( Math.max( parseInt(input.value) || 1, 1 ), 20 );
                input.value  = plazas;
                if ( ! estado.estancias[estId] ) estado.estancias[estId] = { opciones: [] };
                estado.estancias[estId][`plazas_${opId}`] = plazas;
                calcularYActualizar();
            });
        });

        // Input m² por alfombra
        bloque.querySelectorAll('.plr-m2-alf-input').forEach( input => {
            input.addEventListener('input', () => {
                const estId = input.dataset.estId;
                const opId  = input.dataset.opId;
                const m2    = Math.max( 0.5, parseFloat(input.value) || 0.5 );
                input.value = m2;
                if ( ! estado.estancias[estId] ) estado.estancias[estId] = { opciones: [] };
                estado.estancias[estId][`m2_${opId}`] = m2;
                calcularYActualizar();
            });
        });

        // Subopciones radio
        bloque.querySelectorAll('.plr-est-subopcion-input').forEach( input => {
            input.addEventListener('click', (e) => {
                e.stopPropagation(); // Evitar que cierre el desplegable padre
            });
            input.addEventListener('change', (e) => {
                e.stopPropagation();
                const estId = input.dataset.estId;
                const opId  = input.dataset.opId;
                const subId = input.dataset.subId;
                if ( ! estado.estancias[estId] ) estado.estancias[estId] = { opciones: [] };
                estado.estancias[estId][`subopcion_${opId}`] = subId;
                calcularYActualizar();
            });
        });


    }

    // ═══════════════════════════════════════════════════════════
    // CÁLCULO DE PRECIOS
    // ═══════════════════════════════════════════════════════════
    function calcularEstancias() {
        const fuente = getEstanciasFiltradas();
        if ( fuente.length === 0 ) return 0;
        const estMap = {};
        fuente.forEach( e => estMap[e.id] = e );
        const multSuciedad = { normal: 0.10, avanzada: 0.20, muy_sucia: 0.40 };
        let total = 0;

        for ( const [estId, datos] of Object.entries(estado.estancias) ) {
            const cfgEst = estMap[estId];
            if ( ! cfgEst ) continue;
            const opMap  = {};
            (cfgEst.opciones || []).forEach( o => opMap[o.id] = o );
            const opsSel = datos.opciones || [];
            const grado  = datos.grado_suciedad || 'normal';
            const mult   = multSuciedad[grado] ?? 0.10;

            if ( cfgEst.tipo === 'cantidad' ) {
                const cant = Math.max(0, datos.cantidad || 0);
                let sub    = cant * cfgEst.precio_unit;

                // Opciones normales (checkbox activado)
                opsSel.forEach( opId => {
                    const op = opMap[opId];
                    if ( ! op ) return;
                    if ( op.subopciones && op.subopciones.length ) {
                        const subSelId = datos[`subopcion_${opId}`];
                        if ( subSelId ) {
                            const subop = op.subopciones.find( s => s.id === subSelId );
                            if ( subop ) {
                                if ( subop.precio_m2 )    sub += subop.precio_m2 * Math.max(0, estado.m2 || 0);
                                else if ( subop.precio_fijo ) sub += subop.precio_fijo;
                                else if ( subop.mod )     sub += sub * subop.mod;
                            }
                        }
                    } else if ( cfgEst.mod_tipo === 'fijo' ) {
                        sub += cant * op.mod;
                    } else {
                        sub += sub * op.mod;
                    }
                });

                // Subopciones radio directas (tipo:'radio') — no necesitan checkbox padre
                (cfgEst.opciones || []).forEach( op => {
                    if ( op.tipo !== 'radio' ) return;
                    const subSelId = datos[`subopcion_${op.id}`];
                    if ( ! subSelId ) return;
                    const subop = (op.subopciones || []).find( s => s.id === subSelId );
                    if ( ! subop ) return;
                    if ( subop.precio_m2 )        sub += subop.precio_m2 * Math.max(0, estado.m2 || 0);
                    else if ( subop.precio_fijo )  sub += subop.precio_fijo;
                    else if ( subop.mod )          sub += sub * subop.mod;
                });

                sub   += sub * mult;
                total += sub;

            } else if ( cfgEst.tipo === 'toggle' && datos.activo ) {
                let sub = cfgEst.precio_base;
                opsSel.forEach( opId => {
                    const op = opMap[opId];
                    if ( ! op ) return;
                    // Opción con subopciones + plazas (radio_cantidad)
                    if ( op.subopciones && op.subopciones.length ) {
                        const subSelId = datos[`subopcion_${opId}`];
                        const plazas   = Math.max( 1, datos[`plazas_${opId}`] || 1 );
                        const m2alf    = Math.max( 0, datos[`m2_${opId}`] || 0 );
                        if ( subSelId ) {
                            const subop = op.subopciones.find( s => s.id === subSelId );
                            if ( subop ) {
                                if ( subop.precio_plaza )    sub += subop.precio_plaza * plazas;
                                else if ( subop.precio_fijo) sub += subop.precio_fijo;
                                else if ( subop.precio_m2 )  sub += subop.precio_m2 * m2alf * plazas;
                                else if ( subop.mod_por_m2 ) sub += sub * subop.mod_por_m2 * m2alf * plazas;
                                else if ( subop.mod_plaza )  sub += sub * subop.mod_plaza * plazas;
                                else if ( subop.mod )        sub += sub * subop.mod;
                            }
                        }
                    } else if ( op.mod ) {
                        sub += sub * op.mod;
                    }
                });
                sub   += sub * mult;
                total += sub;

            } else if ( cfgEst.tipo === 'cantidad_m2' ) {
                let sub = Math.max(0, datos.m2 || 0) * cfgEst.precio_unit;
                sub    += sub * mult;
                total  += sub;
            }
        }
        return Math.round(total * 100) / 100;
    }

    // ── Tablas de horas (espejo de PLR_Calculadora) ──────────
    // [ m², [horas_normal, horas_avanzada, horas_muy_sucia] ]
    const TABLA_HORAS = [
        [ 30,  [14, 16, 20] ],
        [ 50,  [16, 18, 20] ],
        [ 100, [24, 27, 32] ],
        [ 150, [32, 36, 40] ],
    ];

    const GRADO_IDX = { normal: 0, avanzada: 1, muy_sucia: 2 };

    // Multiplicadores por tipo de servicio
    // Tipos que NO usan m² — ocultar paso 3
    const TIPOS_SIN_M2 = [
        'cristales_interior','cristales_exterior',
        'mantenimiento_hogar','mantenimiento_oficina',
    ];

    // Multiplicadores por tipo — vienen desde el admin via PLR_Config
    // Si el admin los personaliza en Configuración → Catálogo, se actualizan automáticamente
    const MULT_TIPO = cfg.multiplicadores || {
        habitada: 0.80, estrenar: 0.70, fin_obra_sin: 0.80, fin_obra_con: 0.80,
        fin_alquiler_sin: 0.75, fin_alquiler_con: 0.80, cocinas: 0.90,
        oficinas: 0.85, locales: 0.90, trasteros: 0.70, garajes: 0.75,
        diogenes: 1.50, fallecimientos: 1.50, desahucios: 1.30,
        vaciado_viviendas: 1.40, vaciado_oficinas: 1.20, vaciado_trasteros: 1.10,
        cristales_interior: 1.00, cristales_exterior: 1.00,
        mantenimiento_hogar: 1.00, mantenimiento_oficina: 1.00,
    };

    function calcularHoras( m2, grado ) {
        m2 = Math.max(1, m2 || 0);
        const idx     = GRADO_IDX[grado] ?? 0;
        const primero = TABLA_HORAS[0];
        const ultimo  = TABLA_HORAS[TABLA_HORAS.length - 1];
        if ( m2 <= primero[0] ) return Math.round( m2 * (primero[1][idx] / primero[0]) * 100 ) / 100;
        if ( m2 >= ultimo[0]  ) return Math.round( m2 * (ultimo[1][idx]  / ultimo[0])  * 100 ) / 100;
        for ( let i = 0; i < TABLA_HORAS.length - 1; i++ ) {
            const [a, ha] = TABLA_HORAS[i];
            const [b, hb] = TABLA_HORAS[i + 1];
            if ( m2 >= a && m2 <= b ) {
                const h = ha[idx] + (m2 - a) * (hb[idx] - ha[idx]) / (b - a);
                return Math.round( h * 100 ) / 100;
            }
        }
        return 0;
    }

    // Determina el peor grado de suciedad entre todas las estancias seleccionadas
    function peorGrado() {
        const orden = { normal: 0, avanzada: 1, muy_sucia: 2 };
        let peor = 'normal';
        for ( const datos of Object.values(estado.estancias) ) {
            const g = datos.grado_suciedad || 'normal';
            if ( (orden[g] ?? 0) > (orden[peor] ?? 0) ) peor = g;
        }
        return peor;
    }

    function calcularEstanciasCristales() {
        const estList = cfg.estancias_cristales || [];
        const estMap  = {};
        estList.forEach( e => estMap[e.id] = e );
        let total = 0;
        for ( const [estId, datos] of Object.entries(estado.estancias) ) {
            const cfg_e = estMap[estId];
            if ( ! cfg_e ) continue;
            const cant = Math.max( 0, datos.cantidad || 0 );
            if ( cant <= 0 ) continue;
            let sub = cant * cfg_e.precio_unit;
            // Opción doble ventana: precio_fijo_ud
            const opsSel = datos.opciones || [];
            const opMap  = {};
            (cfg_e.opciones || []).forEach( o => opMap[o.id] = o );
            opsSel.forEach( opId => {
                const op = opMap[opId];
                if ( ! op ) return;
                if ( op.precio_fijo_ud ) sub += cant * op.precio_fijo_ud;
                else if ( op.mod > 0 )   sub += sub * op.mod;
            });
            total += sub;
        }
        return Math.round( total * 100 ) / 100;
    }

    function hayEstanciasSeleccionadas() {
        for ( const datos of Object.values(estado.estancias) ) {
            if ( datos.activo ) return true;
            if ( datos.cantidad > 0 ) return true;
            if ( datos.m2 > 0 ) return true;
        }
        return false;
    }

    function calcularPrecios() {
        const tarifa = cfg.precio_hora || 20;
        const t      = estado.tipoId;

        // ── Cristales: precio por unidades ──────────────────────
        if ( t === 'cristales_interior' || t === 'cristales_exterior' ) {
            const extras_est = calcularEstanciasCristales();
            const total      = Math.round( (extras_est + estado.kmCoste) * 100 ) / 100;
            const deposito   = Math.round( total * ((cfg.porcentaje_dep||50)/100) * 100 ) / 100;
            return { base_m2: 0, extras_est, total, deposito };
        }

        // ── Mantenimiento: horas × tarifa ────────────────────────
        if ( t === 'mantenimiento_hogar' || t === 'mantenimiento_oficina' ) {
            const horas        = Math.max( 8, estado.estancias.horas || 0 );
            const modalidad    = estado.estancias['mant_modalidad']?.subopcion || '';
            const tarifaExtra  = modalidad === 'con_herramientas' ? 2 : 0;
            const tarifaTotal  = tarifa + tarifaExtra;
            const base_m2      = Math.round( horas * tarifaTotal * 100 ) / 100;
            const total        = Math.round( (base_m2 + estado.kmCoste) * 100 ) / 100;
            const deposito     = Math.round( total * ((cfg.porcentaje_dep||50)/100) * 100 ) / 100;
            return { base_m2, extras_est: 0, total, deposito, horas, tarifaTotal };
        }

        // ── Resto: horas × m² ────────────────────────────────────
        const grado      = peorGrado();
        const mult       = MULT_TIPO[t] ?? 1.00;
        const horas      = calcularHoras( estado.m2, grado );
        const base_m2    = Math.round( horas * tarifa * mult * 100 ) / 100;
        const extras_est = calcularEstancias();
        const total      = Math.round( (base_m2 + extras_est + estado.kmCoste) * 100 ) / 100;
        const deposito   = Math.round( total * ((cfg.porcentaje_dep||50)/100) * 100 ) / 100;
        return { base_m2, extras_est, total, deposito, horas, grado };
    }

    function calcularYActualizar() {
        const p         = calcularPrecios();
        estado.total    = p.total;
        estado.deposito = p.deposito;
        // Estado en memoria — se guarda en sessionStorage solo al pulsar Volver
        setText('plr-total-acumulado',    formatPrecio(p.total));

        // Mostrar precio solo cuando el cliente interactúa en el paso 4
        if ( estado.pasoActual === 4 && hayEstanciasSeleccionadas() ) {
            const totalWrap = document.getElementById('plr-total-acumulado-wrap');
            if ( totalWrap ) totalWrap.style.display = '';
        }
        setText('plr-res-total-final',    formatPrecio(p.total));
        setText('plr-res-deposito-final', formatPrecio(p.deposito));
        setText('plr-pago-importe',       formatPrecio(p.deposito));
    }

    // ═══════════════════════════════════════════════════════════
    // EVENTOS GLOBALES
    // ═══════════════════════════════════════════════════════════
    function bindEventos() {
        // m²
        document.getElementById('plr-m2')?.addEventListener('input', () => {
            const v   = parseInt(document.getElementById('plr-m2').value, 10);
            estado.m2 = isNaN(v) || v < 1 ? 0 : v;
            calcularYActualizar();
            document.getElementById('plr-btn-paso3').disabled = estado.m2 < 10;
        });

        // Fecha
        document.getElementById('plr-fecha')?.addEventListener('change', () => {} ); // manejado arriba

        // Dirección con debounce
        const inputDir = document.getElementById('plr-direccion');
        if ( inputDir ) {
            let debounceKm;
            inputDir.addEventListener('input', () => {
                clearTimeout(debounceKm);
                const dir = inputDir.value.trim();
                if ( dir.length < 10 ) { estado.direccion = ''; estado.kmCoste = 0; ocultarKmEstado(); calcularYActualizar(); return; }
                debounceKm = setTimeout(() => calcularKm(dir), 800);
            });
        }

        // Observaciones — sin límite de caracteres
        const obsTextarea = document.getElementById('plr-observaciones');
        if ( obsTextarea ) {
            obsTextarea.addEventListener('input', () => {
                estado.observaciones = obsTextarea.value;
            });
        }

        // Factura — toggle desplegable + mostrar/ocultar desglose IVA
        document.getElementById('plr-factura-check')?.addEventListener('change', function() {
            const campos = document.getElementById('plr-factura-campos');
            if ( campos ) campos.style.display = this.checked ? '' : 'none';
            if ( ! this.checked ) estado.facturacion = null;
            calcularYActualizar(); // Actualizar desglose IVA
        });

        // Métodos de pago
        document.querySelectorAll('.plr-metodo-card').forEach( card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.plr-metodo-card').forEach( c => c.classList.remove('seleccionado') );
                card.classList.add('seleccionado');
                estado.metodoPago = card.dataset.metodo;
                document.getElementById('plr-btn-paso7').disabled = false;
            });
        });

        // Navegación delegada
        document.getElementById('plr-cotizador')?.addEventListener('click', async e => {
            const t = e.target.closest('[data-siguiente],[data-anterior]');
            if ( ! t ) return;
            if ( t.dataset.siguiente ) {
                if ( ! await validarPaso() ) return;
                irAPaso( parseInt(t.dataset.siguiente, 10) );
            } else if ( t.dataset.anterior ) {
                marcarNavegacionVolver();
                guardarEstado();
                irAPaso( parseInt(t.dataset.anterior, 10) );
            }
        });

        document.getElementById('plr-btn-pagar')?.addEventListener('click', procesarPago);
    }

    // ═══════════════════════════════════════════════════════════
    // NAVEGACIÓN
    // ═══════════════════════════════════════════════════════════
    // ── Restaurar UI desde estado guardado ───────────────────
    function restaurarUI() {
        // 1. Marcar categoría seleccionada
        if ( estado.categoriaId ) {
            const catCard = document.querySelector(`.plr-categoria-card[data-id="${ estado.categoriaId }"]`);
            if ( catCard ) {
                catCard.classList.add('seleccionado');
                document.getElementById('plr-btn-paso1').disabled = false;
                // Renderizar subcategorías del catálogo correcto
                const cat = (cfg.catalogo || []).find( c => c.id === estado.categoriaId );
                if ( cat ) renderTipos( cat );
            }
        }

        // 2. Marcar subcategoría seleccionada
        if ( estado.tipoId ) {
            const tipoCard = document.querySelector(`.plr-tipo-card[data-id="${ estado.tipoId }"]`);
            if ( tipoCard ) {
                tipoCard.classList.add('seleccionado');
                document.getElementById('plr-btn-paso2').disabled = false;
            }
        }

        // 3. Restaurar m²
        if ( estado.m2 > 0 ) {
            const inM2 = document.getElementById('plr-m2');
            if ( inM2 ) {
                inM2.value = estado.m2;
                document.getElementById('plr-btn-paso3').disabled = false;
            }
        }

        // 4. Restaurar fecha y hora
        if ( estado.fecha ) {
            const inFecha = document.getElementById('plr-fecha');
            if ( inFecha ) {
                inFecha.value = estado.fecha;
                // Cargar slots y marcar el guardado
                cargarSlots().then( () => {
                    if ( estado.hora ) {
                        document.querySelectorAll('.plr-slot').forEach( s => {
                            if ( s.textContent.trim() === estado.hora ) {
                                s.classList.add('activo');
                                document.getElementById('plr-btn-paso5').disabled = false;
                            }
                        });
                    }
                });
            }
        }

        // 5. Restaurar datos personales
        if ( estado.nombre )   { const el = document.getElementById('plr-nombre');   if(el) el.value = estado.nombre;   }
        if ( estado.email )    { const el = document.getElementById('plr-email');    if(el) el.value = estado.email;    }
        if ( estado.telefono ) { const el = document.getElementById('plr-telefono'); if(el) el.value = estado.telefono; }
        if ( estado.direccion )    { const el = document.getElementById('plr-direccion');    if(el) el.value = estado.direccion; }
        if ( estado.observaciones ) {
            const el = document.getElementById('plr-observaciones');
            if ( el ) { el.value = estado.observaciones; const c = document.getElementById('plr-obs-contador'); if(c) c.textContent = estado.observaciones.length; }
        }

        // 6. Restaurar datos de factura si los hay
        if ( estado.facturacion && estado.facturacion.nif ) {
            const check = document.getElementById('plr-factura-check');
            if ( check ) {
                check.checked = true;
                const campos = document.getElementById('plr-factura-campos');
                if ( campos ) campos.style.display = '';
                const f = estado.facturacion;
                if ( f.nombre )    { const el = document.getElementById('plr-fac-nombre');    if(el) el.value = f.nombre;    }
                if ( f.nif )       { const el = document.getElementById('plr-fac-nif');       if(el) el.value = f.nif;       }
                if ( f.cp )        { const el = document.getElementById('plr-fac-cp');        if(el) el.value = f.cp;        }
                if ( f.direccion ) { const el = document.getElementById('plr-fac-direccion'); if(el) el.value = f.direccion; }
                if ( f.ciudad )    { const el = document.getElementById('plr-fac-ciudad');    if(el) el.value = f.ciudad;    }
            }
        }

        // 7. Restaurar método de pago
        if ( estado.metodoPago ) {
            const metCard = document.querySelector(`.plr-metodo-card[data-metodo="${ estado.metodoPago }"]`);
            if ( metCard ) {
                metCard.classList.add('seleccionado');
                const btnPaso7 = document.getElementById('plr-btn-paso7');
                if ( btnPaso7 ) btnPaso7.disabled = false;
            }
        }

        // 8. Actualizar precios y navegar al paso donde estaba
        calcularYActualizar();

        // Ir al paso guardado (con pequeño delay para que el DOM esté listo)
        const paso = estado.pasoActual || 1;
        // No restaurar pasos de pago completado
        if ( paso < 8 ) {
            setTimeout( () => irAPaso( paso ), 150 );
        }
    }

    // ═══════════════════════════════════════════════════════════
    // TRACKING — Google Analytics 4 + GTM + Google Ads
    // Dispara eventos en cada paso para ver abandono en GA4/GTM
    // ═══════════════════════════════════════════════════════════
    const PLR_PASOS_LABELS = {
        1: 'seleccion_servicio',
        2: 'seleccion_tipo',
        3: 'metros_cuadrados',
        4: 'personalizacion_estancias',
        5: 'seleccion_fecha_hora',
        6: 'resumen_precio',
        7: 'datos_contacto',
        8: 'pago_deposito',
        9: 'reserva_confirmada',
    };

    function plrTrack( evento, params ) {
        params = Object.assign({
            event_category: 'cotizador',
            servicio:  estado.tipoLabel || estado.tipoId || '',
            m2:        estado.m2 || 0,
            paso:      estado.pasoActual,
        }, params );

        // Google Analytics 4
        if ( typeof gtag !== 'undefined' ) {
            gtag( 'event', evento, params );
        }
        // Google Tag Manager dataLayer
        if ( typeof dataLayer !== 'undefined' ) {
            dataLayer.push( Object.assign({ event: 'plr_' + evento }, params ) );
        }
    }

    function trackPaso( num ) {
        const nombre = PLR_PASOS_LABELS[num] || 'paso_' + num;
        plrTrack( 'paso_' + num, {
            paso_nombre:  nombre,
            paso_numero:  num,
            event_label:  nombre,
            value:        num,
        });
        // Evento unificado para embudos en GA4
        plrTrack( 'cotizador_paso', {
            paso_nombre: nombre,
            paso_numero: num,
        });
    }

    function irAPaso( num ) {
        // Saltar paso 3 (m²) si el tipo no lo necesita
        if ( num === 3 && TIPOS_SIN_M2.includes(estado.tipoId) ) {
            // Detectar dirección: si venimos de paso < 3 vamos al 4, si venimos de > 3 vamos al 2
            num = estado.pasoActual < 3 ? 4 : 2;
        }
        document.querySelectorAll('.plr-paso').forEach( p => p.classList.remove('activo') );
        document.querySelectorAll('[data-paso-indicador]').forEach( p => p.classList.remove('activo') );
        document.querySelector(`[data-paso="${num}"]`)?.classList.add('activo');
        document.querySelector(`[data-paso-indicador="${num}"]`)?.classList.add('activo');
        estado.pasoActual = num;

        // Tracking — dispara evento al entrar en cada paso
        trackPaso( num );

        // Ocultar precio al cambiar de paso — solo se muestra cuando el cliente
        // interactúa con las estancias en el paso 4
        const totalWrap = document.getElementById('plr-total-acumulado-wrap');
        if ( totalWrap ) totalWrap.style.display = 'none';

        if ( num === 4 ) {
            const esCristales     = ['cristales_interior','cristales_exterior'].includes(estado.tipoId);
            const esMantenimiento = ['mantenimiento_hogar','mantenimiento_oficina'].includes(estado.tipoId);
            const esVaciado       = ['vaciado_viviendas','vaciado_oficinas','vaciado_trasteros'].includes(estado.tipoId);

            // Mostrar/ocultar selector de horas
            const horasWrap = document.getElementById('plr-horas-mantenimiento-wrap');
            if ( horasWrap ) horasWrap.style.display = esMantenimiento ? '' : 'none';
            if ( esMantenimiento ) {
                const inputH = document.getElementById('plr-horas-mantenimiento');
                if ( inputH ) inputH.value = estado.estancias.horas > 0 ? estado.estancias.horas : '';
                calcularYActualizar();
            }

            // Mostrar/ocultar cláusulas y bloquear botón siguiente
            const clausulasWrap = document.getElementById('plr-clausulas-mantenimiento');
            const btnPaso4      = document.getElementById('plr-btn-paso4');
            if ( clausulasWrap ) clausulasWrap.style.display = esMantenimiento ? '' : 'none';
            if ( btnPaso4 && esMantenimiento ) {
                const check = document.getElementById('plr-clausulas-check');
                btnPaso4.disabled = ! ( check && check.checked );
            } else if ( btnPaso4 ) {
                btnPaso4.disabled = false;
            }

            const h2   = document.querySelector('[data-paso="4"] .plr-paso-titulo');
            const desc = document.querySelector('[data-paso="4"] .plr-paso-desc');
            if ( h2 ) {
                if ( esVaciado )          h2.textContent = 'Indica qué quieres retirar';
                else if ( esCristales )   h2.textContent = 'Personaliza la limpieza de cristales';
                else if ( esMantenimiento)h2.textContent = 'Indica qué quieres limpiar';
                else if ( estado.tipoId === 'cocinas') h2.textContent = 'Personaliza la limpieza de cocina';
                else                      h2.textContent = 'Personaliza tu limpieza';
            }
            if ( desc ) {
                if ( esVaciado )          desc.textContent = 'Selecciona los items que deseas retirar. Así preparamos el equipo y el transporte adecuado.';
                else if ( esCristales )   desc.textContent = 'Indica cuántas ventanas, persianas y raíles quieres limpiar.';
                else if ( esMantenimiento)desc.textContent = 'Selecciona las zonas que quieres limpiar e indica las horas de servicio.';
                else if ( estado.tipoId === 'cocinas') desc.textContent = 'Selecciona qué quieres incluir.';
                else                      desc.textContent = 'Selecciona las estancias y detalles que quieres incluir.';
            }
            renderEstancias();
        }
        if ( num === 6 ) {
            // Tracking — cliente ve el precio final
            plrTrack( 'precio_calculado', {
                value:         estado.total || 0,
                deposito:      estado.deposito || 0,
                currency:      'EUR',
                event_label:   estado.tipoLabel || '',
            });
            setText('plr-res-tipo-final', estado.tipoLabel);
            setText('plr-res-fecha',      estado.fecha);
            setText('plr-res-hora',       estado.hora);
            calcularYActualizar();
        }
        if ( num === 8 ) {
            plrTrack( 'inicio_pago', {
                value:     estado.deposito || 0,
                currency:  'EUR',
                event_label: 'deposito_50pct',
            });
            inicializarStripeElements();
        }

        window.scrollTo({ top: (document.getElementById('plr-cotizador')?.offsetTop ?? 0) - 20, behavior: 'smooth' });
    }

    // ═══════════════════════════════════════════════════════════
    // VALIDACIONES POR PASO
    // ═══════════════════════════════════════════════════════════
    async function validarPaso() {
        const p = estado.pasoActual;

        if ( p === 1 ) {
            if ( ! estado.categoriaId ) { alert('Selecciona un tipo de limpieza.'); return false; }
        }
        if ( p === 2 ) {
            if ( ! estado.tipoId ) { alert('Selecciona el tipo específico.'); return false; }
        }
        if ( p === 3 ) {
            if ( estado.m2 < 10 ) { alert('Introduce al menos 10 m².'); return false; }
        }
        if ( p === 5 ) {
            if ( ! estado.fecha ) { alert('Selecciona una fecha.'); return false; }
            if ( ! estado.hora )  { alert('Selecciona un horario.'); return false; }
        }
        if ( p === 6 ) {
            const nombre   = document.getElementById('plr-nombre')?.value.trim() ?? '';
            const email    = document.getElementById('plr-email')?.value.trim() ?? '';
            const telefono = document.getElementById('plr-telefono')?.value.trim() ?? '';
            const dir      = document.getElementById('plr-direccion')?.value.trim() ?? '';
            if ( nombre.length < 3 )     { alert('Introduce tu nombre completo.'); return false; }
            if ( ! email.includes('@') ) { alert('Introduce un email válido.'); return false; }
            if ( dir.length < 5 )        { alert('Introduce la dirección del servicio.'); return false; }
            estado.nombre        = nombre;
            estado.email         = email;
            estado.telefono      = telefono;
            estado.direccion     = dir;
            estado.observaciones = document.getElementById('plr-observaciones')?.value.trim() ?? '';

            // Recoger datos de factura si el cliente los rellenó
            const factCheck = document.getElementById('plr-factura-check');
            if ( factCheck && factCheck.checked ) {
                const facNombre   = document.getElementById('plr-fac-nombre')?.value.trim() ?? '';
                const facNif      = document.getElementById('plr-fac-nif')?.value.trim() ?? '';
                const facCp       = document.getElementById('plr-fac-cp')?.value.trim() ?? '';
                const facDireccion= document.getElementById('plr-fac-direccion')?.value.trim() ?? '';
                const facCiudad   = document.getElementById('plr-fac-ciudad')?.value.trim() ?? '';

                if ( ! facNombre || ! facNif || ! facCp || ! facDireccion || ! facCiudad ) {
                    alert('Si necesitas factura, rellena todos los campos fiscales.');
                    return false;
                }
                estado.facturacion = { nombre: facNombre, nif: facNif, cp: facCp, direccion: facDireccion, ciudad: facCiudad };
            } else {
                estado.facturacion = null;
            }

            return true; // Solo guardar datos, NO crear reserva aquí
        }
        if ( p === 7 ) {
            if ( ! estado.metodoPago ) { alert('Selecciona un método de pago.'); return false; }
            if ( estado.metodoPago === 'transferencia' ) {
                await crearReservaTransferencia();
                return false; // La función ya navega al paso 8b
            }
            // Tarjeta: crear PaymentIntent y continuar a paso 8
            return await crearReserva();
        }

        return true;
    }

    // ═══════════════════════════════════════════════════════════
    // CALENDARIO
    // ═══════════════════════════════════════════════════════════
    async function cargarSlots() {
        const fecha = document.getElementById('plr-fecha')?.value;
        if ( ! fecha ) return;
        estado.fecha = fecha;
        estado.hora  = '';

        const wrap   = document.getElementById('plr-slots-wrap');
        const cont   = document.getElementById('plr-slots');
        const btnSig = document.getElementById('plr-btn-paso5');

        cont.innerHTML     = '<em>Cargando horarios...</em>';
        wrap.style.display = '';
        if ( btnSig ) btnSig.disabled = true;

        try {
            const res  = await fetch(cfg.rest_url + 'disponibilidad?fecha=' + encodeURIComponent(fecha), { headers: { 'X-WP-Nonce': cfg.nonce } });
            const data = await res.json();
            if ( ! res.ok ) throw new Error(data.message || 'Error al cargar horarios.');

            cont.innerHTML = '';
            if ( ! data.slots?.length ) { cont.innerHTML = '<em>No hay horarios disponibles. Prueba otra fecha.</em>'; return; }

            data.slots.forEach( slot => {
                const btn = document.createElement('button');
                btn.className = 'plr-slot'; btn.textContent = slot; btn.type = 'button';
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.plr-slot').forEach( s => s.classList.remove('activo') );
                    btn.classList.add('activo');
                    estado.hora = slot;
                    if ( btnSig ) btnSig.disabled = false;
                });
                cont.appendChild(btn);
            });
        } catch(err) {
            cont.innerHTML = '<span style="color:red">' + esc(err.message) + '</span>';
        }
    }

    // ═══════════════════════════════════════════════════════════
    // KILOMETRAJE
    // ═══════════════════════════════════════════════════════════
    async function calcularKm( direccion ) {
        mostrarKmEstado('cargando', '📍 Calculando distancia...');
        try {
            const res  = await fetch(cfg.rest_url + 'calcular-km', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
                body: JSON.stringify({ direccion }),
            });
            const data = await res.json();
            if ( ! res.ok ) { mostrarKmEstado('error', '⚠️ No se pudo verificar la distancia.'); estado.direccion = direccion; estado.kmCoste = 0; calcularYActualizar(); return; }
            estado.direccion = direccion;
            estado.kmCoste   = data.coste || 0;
            if ( data.aplica ) mostrarKmEstado('info', `🚗 El servicio está a ${ data.distancia_km } km. Se aplicará un suplemento por desplazamiento.`);
            else               mostrarKmEstado('ok',   `✅ Sin suplemento de desplazamiento (${ data.distancia_km } km).`);
            calcularYActualizar();
        } catch(_) {
            mostrarKmEstado('error', '⚠️ Error al calcular la distancia.');
            estado.kmCoste = 0;
            calcularYActualizar();
        }
    }

    function mostrarKmEstado( tipo, msg ) {
        const el = document.getElementById('plr-km-estado');
        if ( ! el ) return;
        el.style.display = '';
        el.className     = 'plr-km-estado plr-km-' + tipo;
        el.textContent   = msg;
    }

    function ocultarKmEstado() {
        const el = document.getElementById('plr-km-estado');
        if ( el ) el.style.display = 'none';
    }

    // ═══════════════════════════════════════════════════════════
    // STRIPE
    // ═══════════════════════════════════════════════════════════
    async function crearReserva() {
        try {
            const res  = await fetch(cfg.rest_url + 'crear-reserva', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
                body: JSON.stringify({
                    nombre:     estado.nombre,
                    email:      estado.email,
                    telefono:   estado.telefono,
                    m2:         estado.m2,
                    tipo_id:    estado.tipoId,
                    tipo_label: estado.tipoLabel,
                    estancias:  estado.estancias,
                    fecha:      estado.fecha,
                    hora:       estado.hora,
                    total:      estado.total,
                    direccion:  estado.direccion,
                    facturacion:   estado.facturacion,
                    observaciones: estado.observaciones,
                }),
            });
            const data = await res.json();
            if ( ! res.ok ) { alert(data.message || data.error || 'Error al crear la reserva.'); return false; }
            estado.reservaId    = data.reserva_id;
            estado.clientSecret = data.client_secret;
            estado.deposito     = data.deposito;
            calcularYActualizar();
            return true;
        } catch(_) {
            alert('Error de conexión. Recarga la página e inténtalo de nuevo.');
            return false;
        }
    }

    async function crearReservaTransferencia() {
        try {
            const res  = await fetch(cfg.rest_url + 'reserva-transferencia', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
                body: JSON.stringify({
                    nombre:     estado.nombre,
                    email:      estado.email,
                    telefono:   estado.telefono,
                    m2:         estado.m2,
                    tipo_id:    estado.tipoId,
                    tipo_label: estado.tipoLabel,
                    estancias:  estado.estancias,
                    fecha:      estado.fecha,
                    hora:       estado.hora,
                    total:      estado.total,
                    direccion:  estado.direccion,
                    facturacion:   estado.facturacion,
                    observaciones: estado.observaciones,
                }),
            });
            const data = await res.json();
            if ( ! res.ok ) { alert(data.error || 'Error al procesar la reserva.'); return false; }

            const cont = document.getElementById('plr-transferencia-datos');
            if ( cont && data.banco ) {
                const b = data.banco;
                cont.innerHTML = `
                <div class="plr-banco-datos">
                    <div class="plr-banco-fila"><span>Titular</span><strong>${ esc(b.titular) }</strong></div>
                    <div class="plr-banco-fila"><span>Entidad</span><strong>${ esc(b.entidad) }</strong></div>
                    <div class="plr-banco-fila"><span>IBAN</span><strong>${ esc(b.iban) }</strong></div>
                    <div class="plr-banco-fila plr-banco-concepto"><span>Concepto <strong>(obligatorio)</strong></span><strong>${ esc(b.concepto) }</strong></div>
                    <div class="plr-banco-importe"><span>Importe</span><strong>${ formatPrecio(data.deposito) }</strong></div>
                </div>
                <p class="plr-banco-aviso">⏰ Tienes <strong>${ data.expira_en }h</strong> para realizar la transferencia antes de que la reserva se cancele automáticamente.</p>`;
            }
            limpiarEstadoGuardado();

            // ── TRACKING conversión por transferencia ─────────────
            const vDeposito = parseFloat( data.deposito || estado.deposito || 0 );
            const vTotal    = parseFloat( estado.total || 0 );
            if ( typeof dataLayer !== 'undefined' ) {
                dataLayer.push({
                    event:          'plr_reserva_completada',
                    value:          vDeposito,
                    total:          vTotal,
                    currency:       'EUR',
                    metodo_pago:    'transferencia',
                    servicio:       estado.tipoLabel || estado.tipoId || '',
                    event_category: 'cotizador',
                    event_label:    'reserva_transferencia',
                });
            }
            if ( typeof gtag !== 'undefined' ) {
                gtag( 'event', 'purchase', {
                    transaction_id: Date.now().toString(),
                    value:          vDeposito,
                    currency:       'EUR',
                    items: [{ item_name: estado.tipoLabel || 'Limpieza', price: vDeposito, quantity: 1 }]
                });
                if ( cfg.google_ads_id ) {
                    gtag( 'event', 'conversion', { send_to: cfg.google_ads_id, value: vDeposito, currency: 'EUR', transaction_id: Date.now().toString() });
                }
            }

            irAPaso('8b');
        } catch(_) {
            alert('Error de conexión. Inténtalo de nuevo.');
        }
    }

    function inicializarStripeElements() {
        if ( ! stripe || ! estado.clientSecret || estado.stripeElements ) return;
        estado.stripeElements = stripe.elements({ clientSecret: estado.clientSecret, locale: 'es' });
        estado.paymentElement = estado.stripeElements.create('payment');
        estado.paymentElement.mount('#plr-payment-element');
    }

    async function procesarPago() {
        if ( ! stripe || ! estado.stripeElements ) return;
        const btnPagar  = document.getElementById('plr-btn-pagar');
        const btnTxt    = document.getElementById('plr-btn-pagar-texto');
        const btnLoader = document.getElementById('plr-btn-pagar-loader');
        const errDiv    = document.getElementById('plr-stripe-error');

        btnPagar.disabled = true; btnTxt.style.display = 'none';
        btnLoader.style.display = ''; errDiv.style.display = 'none';

        const { error, paymentIntent } = await stripe.confirmPayment({ elements: estado.stripeElements, redirect: 'if_required' });

        if ( error ) {
            errDiv.textContent = error.message; errDiv.style.display = '';
            btnPagar.disabled = false; btnTxt.style.display = ''; btnLoader.style.display = 'none';
            return;
        }
        if ( paymentIntent?.status === 'succeeded' ) await confirmarPagoServidor(paymentIntent.id);
    }

    async function confirmarPagoServidor( piId ) {
        try {
            await fetch(cfg.rest_url + 'confirmar-pago', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
                // reserva_id puede ser null si venimos de redirect — el webhook de Stripe
                // ya habrá confirmado el pago; este endpoint es solo para actualizar el estado
                body: JSON.stringify({ reserva_id: estado.reservaId || 0, payment_intent_id: piId }),
            });
        } catch(_) {}

        // Si venimos de redirect de Stripe no tenemos fecha/hora en estado
        // Mostrar mensaje genérico de éxito
        const fechaTxt = estado.fecha || '';
        const horaTxt  = estado.hora  || '';
        setText('plr-exito-fecha', fechaTxt);
        setText('plr-exito-hora',  horaTxt);

        // ── TRACKING CONVERSIÓN ───────────────────────────────────
        // Se dispara aquí porque el paso 9 no pasa por irAPaso()
        // sino que llega directamente desde el redirect de Stripe
        const valorTotal    = parseFloat( estado.total    || 0 );
        const valorDeposito = parseFloat( estado.deposito || 0 );

        // GA4 + GTM dataLayer
        if ( typeof dataLayer !== 'undefined' ) {
            dataLayer.push({
                event:          'plr_reserva_completada',
                value:          valorDeposito,
                total:          valorTotal,
                currency:       'EUR',
                servicio:       estado.tipoLabel || estado.tipoId || '',
                event_category: 'cotizador',
                event_label:    'reserva_completada',
            });
        }
        // GA4 directo
        if ( typeof gtag !== 'undefined' ) {
            gtag( 'event', 'purchase', {
                transaction_id: Date.now().toString(),
                value:          valorDeposito,
                currency:       'EUR',
                items: [{
                    item_name:     estado.tipoLabel || 'Limpieza',
                    item_category: 'Limpieza',
                    price:         valorDeposito,
                    quantity:      1,
                }]
            });
            // Conversión Google Ads con valor dinámico
            if ( cfg.google_ads_id ) {
                gtag( 'event', 'conversion', {
                    send_to:        cfg.google_ads_id,
                    value:          valorDeposito,
                    currency:       'EUR',
                    transaction_id: Date.now().toString(),
                });
            }
        }

        // Mostrar directamente el paso de éxito (paso 9)
        // ocultando todos los demás pasos sin animación
        document.querySelectorAll('.plr-paso').forEach( p => p.classList.remove('activo') );
        const pasoExito = document.querySelector('[data-paso="9"]');
        if ( pasoExito ) {
            pasoExito.classList.add('activo');
            pasoExito.style.display = 'block';
        }
    }

    // ═══════════════════════════════════════════════════════════
    // UTILIDADES
    // ═══════════════════════════════════════════════════════════
    function formatPrecio(v) { return parseFloat(v||0).toFixed(2).replace('.',',') + ' €'; }
    function setText(id, val) { const el = document.getElementById(id); if(el) el.textContent = val; }
    function esc(str) {
        return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

} )();


