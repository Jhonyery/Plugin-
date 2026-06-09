<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="plr-cotizador" class="plr-wrap">

    <!-- PASO 1: Categoría -->
    <div class="plr-paso activo" data-paso="1">
        <h2 class="plr-paso-titulo">¿Qué limpieza deseas?</h2>
        <p class="plr-paso-desc">Selecciona la categoría de servicio que necesitas.</p>
        <div class="plr-categorias" id="plr-categorias"></div>
        <div class="plr-pasos-nav" style="justify-content:flex-end">
            <button class="plr-btn plr-btn-siguiente" data-siguiente="2" id="plr-btn-paso1" disabled>Siguiente →</button>
        </div>
    </div>

    <!-- PASO 2: Subcategoría -->
    <div class="plr-paso" data-paso="2">
        <h2 class="plr-paso-titulo" id="plr-titulo-paso2">¿De qué tipo?</h2>
        <p class="plr-paso-desc">Elige el tipo concreto de limpieza.</p>
        <div class="plr-tipos" id="plr-tipos"></div>
        <div id="plr-tipo-descripcion" style="display:none"></div>
        <div class="plr-pasos-nav">
            <button class="plr-btn plr-btn-volver" data-anterior="1">← Volver</button>
            <button class="plr-btn plr-btn-siguiente" data-siguiente="3" id="plr-btn-paso2" disabled>Siguiente →</button>
        </div>
    </div>

    <!-- PASO 3: Metros cuadrados -->
    <div class="plr-paso" data-paso="3">
        <h2 class="plr-paso-titulo">¿Cuántos metros cuadrados?</h2>
        <p class="plr-paso-desc">Introduce la superficie aproximada a limpiar.</p>
        <div class="plr-campo">
            <label for="plr-m2">Metros cuadrados (m²)</label>
            <input type="number" id="plr-m2" min="10" max="2000" placeholder="Ej: 80"
                style="display:block;width:100%;max-width:260px;padding:18px 20px;font-size:32px;font-weight:700;line-height:1;border:2px solid #e2e8f0;border-radius:12px;text-align:center;color:#2d3748;background:#fff;margin-top:10px;height:auto;min-height:68px;box-shadow:none;-webkit-appearance:none;appearance:none;box-sizing:border-box" />
        </div>
        <span id="plr-live-precio-m2" style="display:none"></span>
        <span id="plr-live-tipo" style="display:none"></span>
        <div class="plr-pasos-nav">
            <button class="plr-btn plr-btn-volver" data-anterior="2">← Volver</button>
            <button class="plr-btn plr-btn-siguiente" data-siguiente="4" id="plr-btn-paso3" disabled>Siguiente →</button>
        </div>
    </div>

    <!-- PASO 4: Estancias (solo para "casas") -->
    <div class="plr-paso" data-paso="4">
        <h2 class="plr-paso-titulo">Personaliza tu limpieza</h2>
        <p class="plr-paso-desc">Selecciona las estancias y detalles que quieres incluir.</p>

        <div id="plr-estancias-wrap">
            <!-- Generado dinámicamente por JS -->
        </div>

        <!-- Selector de horas para mantenimiento -->
        <div id="plr-horas-mantenimiento-wrap" style="display:none">
            <div class="plr-horas-selector">
                <label class="plr-horas-label">
                    ⏱ <strong>¿Cuántas horas necesitas?</strong>
                    <span>Mínimo 8 horas · 20€/hora</span>
                </label>
                <div class="plr-horas-input-wrap">
                    <input type="number" id="plr-horas-mantenimiento"
                        min="8" max="48" value="8" step="1"
                        style="width:80px;padding:8px 10px;border:2px solid var(--plr-verde);border-radius:8px;font-size:18px;font-weight:700;text-align:center;color:var(--plr-verde)">
                    <span style="font-size:14px;color:#718096;margin-left:8px">horas</span>
                </div>
                <p class="plr-horas-aviso">El servicio mínimo es de 8 horas. Un profesional acudirá a limpiar las zonas que selecciones.</p>
            </div>
        </div>

        <!-- Total acumulado visible — oculto hasta que el cliente seleccione algo -->
        <div class="plr-total-acumulado" id="plr-total-acumulado-wrap" style="display:none">
            <span>Total estimado:</span>
            <strong id="plr-total-acumulado">0,00 €</strong>
        </div>

        <!-- Cláusulas servicio de mantenimiento — solo visible para ese tipo -->
        <div id="plr-clausulas-mantenimiento" style="display:none">
            <div class="plr-clausulas-wrap">
                <h4 class="plr-clausulas-titulo">📋 Condiciones del servicio</h4>
                <ul class="plr-clausulas-lista">
                    <li>🪟 El personal de limpieza <strong>no realiza limpieza de cristales exteriores</strong> ni trabajos en altura.</li>
                    <li>🤝 En caso de malos tratos o comportamiento irrespetuoso hacia el empleado/a, <strong>la empresa retirará al trabajador de inmediato</strong> sin derecho a devolución.</li>
                    <li>🧴 Los productos de limpieza son <strong>manipulados exclusivamente por el personal de la empresa</strong>. El cliente no debe solicitar el uso de productos propios.</li>
                    <li>⚖️ El trabajo se realizará a un <strong>ritmo adecuado y saludable</strong>. Solo se llevarán a cabo las tareas que humanamente puedan realizarse dentro de la jornada contratada.</li>
                    <li>☕ El empleado/a tiene derecho a <strong>15 minutos de descanso</strong> dentro de su jornada laboral.</li>
                    <li>📞 Si el cliente tiene alguna observación o incidencia, deberá <strong>comunicarlo a la empresa</strong> y no llamar la atención al empleado/a directamente. Nosotros tomaremos las medidas pertinentes.</li>
                    <li>⏱️ El empleado/a <strong>no está obligado a completar todas las tareas</strong> si no dispone de tiempo suficiente dentro de su jornada. <strong>No se realizan horas extras.</strong></li>
                    <li>📄 Al aceptar estas condiciones, el cliente confirma su conformidad con todas las cláusulas de este <strong>contrato temporal de servicio</strong>, que quedará extinguido una vez el operario finalice su turno.</li>
                </ul>
                <label class="plr-clausulas-acepto">
                    <input type="checkbox" id="plr-clausulas-check">
                    <span>He leído y <strong>acepto las condiciones</strong> del servicio de limpieza por horas</span>
                </label>
            </div>
        </div>

        <div class="plr-pasos-nav">
            <button class="plr-btn plr-btn-volver" data-anterior="3">← Volver</button>
            <button class="plr-btn plr-btn-siguiente" data-siguiente="5" id="plr-btn-paso4">Siguiente →</button>
        </div>
    </div>

    <!-- PASO 5: Calendario -->
    <div class="plr-paso" data-paso="5">
        <h2 class="plr-paso-titulo">Elige tu fecha y hora</h2>
        <p class="plr-paso-desc">Selecciona el día y el horario disponible.</p>
        <div class="plr-campo">
            <label for="plr-fecha">Fecha del servicio</label>
            <!-- Wrapper con input visible + input date oculto para compatibilidad iOS -->
            <div style="position:relative;width:100%">
                <input type="text" id="plr-fecha-display" readonly
                    placeholder="📅 Toca aquí para elegir fecha"
                    style="display:block;width:100%;padding:16px 18px;font-size:18px;font-weight:500;line-height:1.4;border:2px solid #e2e8f0;border-radius:12px;color:#2d3748;background:#fff;margin-top:10px;min-height:58px;cursor:pointer;box-shadow:none;box-sizing:border-box;-webkit-appearance:none;appearance:none">
                <input type="date" id="plr-fecha"
                    min="<?php echo esc_attr( date('Y-m-d', strtotime('+1 day')) ) ?>"
                    style="position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;z-index:2;-webkit-appearance:none;appearance:none;border:none;background:transparent">
            </div>
        </div>
        <div id="plr-slots-wrap" style="display:none">
            <label>Horario disponible</label>
            <div class="plr-slots" id="plr-slots"></div>
        </div>
        <div class="plr-pasos-nav">
            <button class="plr-btn plr-btn-volver" data-anterior="4">← Volver</button>
            <button class="plr-btn plr-btn-siguiente" data-siguiente="6" id="plr-btn-paso5" disabled>Siguiente →</button>
        </div>
    </div>

    <!-- PASO 6: Datos del cliente -->
    <div class="plr-paso" data-paso="6">
        <h2 class="plr-paso-titulo">Tus datos de contacto</h2>
        <div class="plr-campo">
            <label for="plr-nombre">Nombre completo *</label>
            <input type="text" id="plr-nombre" placeholder="María García López" required />
        </div>
        <div class="plr-campo">
            <label for="plr-email">Email *</label>
            <input type="email" id="plr-email" placeholder="tu@email.com" required />
        </div>
        <div class="plr-campo">
            <label for="plr-telefono">Teléfono</label>
            <input type="tel" id="plr-telefono" placeholder="600 000 000" />
        </div>
        <div class="plr-campo">
            <label for="plr-direccion">Dirección del servicio *</label>
            <input type="text" id="plr-direccion"
                placeholder="Calle, número, ciudad..." required
                autocomplete="street-address" />
            <span class="plr-campo-ayuda">Introduce la dirección completa donde se realizará el servicio.</span>
            <div id="plr-km-estado" class="plr-km-estado" style="display:none"></div>
        </div>

        <div class="plr-resumen-final">
            <h3>Resumen de tu reserva</h3>
            <div class="plr-linea"><span>Servicio:</span><span id="plr-res-tipo-final">—</span></div>
            <div class="plr-linea"><span>Fecha:</span><span id="plr-res-fecha">—</span></div>
            <div class="plr-linea"><span>Hora:</span><span id="plr-res-hora">—</span></div>
            <div class="plr-linea plr-total"><span>Total (IVA incluido):</span><span id="plr-res-total-final">0,00 €</span></div>
            <!-- Desglose IVA — solo visible si el cliente marcó factura -->
            <div class="plr-iva-desglose" id="plr-iva-desglose" style="display:none">
                <div class="plr-linea plr-iva-linea"><span>Base imponible:</span><span id="plr-res-base-imp">0,00 €</span></div>
                <div class="plr-linea plr-iva-linea"><span>IVA (<?php echo esc_html(get_option('plr_iva_porcentaje',21)) ?>%):</span><span id="plr-res-iva">0,00 €</span></div>
            </div>
            <div class="plr-linea plr-deposito"><span>Pagas ahora (<?php echo esc_html( get_option('plr_porcentaje_deposito',50) ) ?>%):</span><span id="plr-res-deposito-final">0,00 €</span></div>
        </div>
        <!-- Datos de facturación (opcional) -->
        <div class="plr-factura-wrap">
            <label class="plr-factura-toggle">
                <input type="checkbox" id="plr-factura-check">
                <span class="plr-factura-toggle-label">
                    🧾 <strong>¿Necesitas factura?</strong>
                    <small>Opcional — rellena tus datos fiscales</small>
                </span>
            </label>

            <div class="plr-factura-campos" id="plr-factura-campos" style="display:none">
                <div class="plr-campo">
                    <label for="plr-fac-nombre">Nombre fiscal / Razón social *</label>
                    <input type="text" id="plr-fac-nombre" placeholder="Juan García S.L. o Juan García López" />
                </div>
                <div class="plr-campo-grupo">
                    <div class="plr-campo">
                        <label for="plr-fac-nif">NIF / CIF *</label>
                        <input type="text" id="plr-fac-nif" placeholder="12345678A" />
                    </div>
                    <div class="plr-campo">
                        <label for="plr-fac-cp">Código postal *</label>
                        <input type="text" id="plr-fac-cp" placeholder="28001" maxlength="5" />
                    </div>
                </div>
                <div class="plr-campo">
                    <label for="plr-fac-direccion">Dirección fiscal *</label>
                    <input type="text" id="plr-fac-direccion" placeholder="Calle Mayor 1, 2ºA" />
                </div>
                <div class="plr-campo">
                    <label for="plr-fac-ciudad">Ciudad *</label>
                    <input type="text" id="plr-fac-ciudad" placeholder="Madrid" />
                </div>
                <div class="plr-factura-aviso">
                    📧 Recibirás la factura por email una vez completado el pago total del servicio.
                </div>
            </div>
        </div>

        <!-- Observaciones del cliente -->
        <div class="plr-observaciones-wrap">
            <label for="plr-observaciones" class="plr-obs-label">
                💬 <strong>¿Tienes alguna observación?</strong>
                <span>Opcional — cuéntanos si hay algo especial que debamos saber</span>
            </label>
            <textarea id="plr-observaciones" rows="4"
                placeholder="Ej: Hay un perro en casa, la llave estará con el vecino, acceso por el garaje..."
                style="width:100%;padding:10px 12px;border:1px solid var(--plr-borde);border-radius:8px;font-size:14px;font-family:inherit;resize:vertical;color:#2d3748"></textarea>
        </div>

        <div class="plr-pasos-nav">
            <button class="plr-btn plr-btn-volver" data-anterior="5">← Volver</button>
            <button class="plr-btn plr-btn-siguiente" data-siguiente="7" id="plr-btn-paso6">Ir al pago →</button>
        </div>
    </div>

    <!-- PASO 7: Selección de método de pago -->
    <div class="plr-paso" data-paso="7">
        <h2 class="plr-paso-titulo">¿Cómo quieres pagar?</h2>
        <p class="plr-paso-desc">Elige tu método de pago preferido para el depósito.</p>

        <div class="plr-metodos-pago">

            <div class="plr-metodo-card" data-metodo="tarjeta">
                <div class="plr-metodo-icono">💳</div>
                <div class="plr-metodo-info">
                    <span class="plr-metodo-titulo">Tarjeta o Bizum</span>
                    <span class="plr-metodo-desc">Pago inmediato y seguro con Stripe</span>
                </div>
                <div class="plr-metodo-check">✔</div>
            </div>

            <div class="plr-metodo-card" data-metodo="transferencia">
                <div class="plr-metodo-icono">🏦</div>
                <div class="plr-metodo-info">
                    <span class="plr-metodo-titulo">Transferencia bancaria</span>
                    <span class="plr-metodo-desc">Te enviamos el IBAN por email. Tienes <?php echo esc_html(get_option('plr_transferencia_horas',24)) ?>h para realizarla</span>
                </div>
                <div class="plr-metodo-check">✔</div>
            </div>

        </div>

        <div class="plr-pasos-nav">
            <button class="plr-btn plr-btn-volver" data-anterior="6">← Volver</button>
            <button class="plr-btn plr-btn-siguiente" data-siguiente="8" id="plr-btn-paso7" disabled>Continuar →</button>
        </div>
    </div>

    <!-- PASO 8: Pago con Stripe (tarjeta + Bizum) -->
    <div class="plr-paso" data-paso="8">
        <h2 class="plr-paso-titulo">Pago con tarjeta o Bizum</h2>
        <p class="plr-paso-desc">Introduce los datos de pago para confirmar la reserva.</p>
        <div class="plr-pago-resumen">
            <span>Importe a pagar ahora:</span>
            <strong id="plr-pago-importe">0,00 €</strong>
        </div>
        <div id="plr-stripe-elements">
            <div id="plr-payment-element"></div>
            <div id="plr-stripe-error" class="plr-error" style="display:none"></div>
        </div>
        <div class="plr-pasos-nav">
            <button class="plr-btn plr-btn-volver" data-anterior="7">← Volver</button>
            <button class="plr-btn plr-btn-pagar" id="plr-btn-pagar">
                <span id="plr-btn-pagar-texto">Confirmar y pagar</span>
                <span id="plr-btn-pagar-loader" style="display:none">Procesando...</span>
            </button>
        </div>
    </div>

    <!-- PASO 8b: Confirmación transferencia -->
    <div class="plr-paso" data-paso="8b">
        <div class="plr-transferencia-confirmada">
            <div class="plr-exito-icono">🏦</div>
            <h2>¡Reserva recibida!</h2>
            <p>Hemos enviado a tu email las instrucciones para realizar la transferencia.</p>
            <div class="plr-transferencia-datos" id="plr-transferencia-datos"></div>
            <p class="plr-exito-info">Una vez confirmemos el pago te enviaremos la confirmación definitiva de tu reserva.</p>
        </div>
    </div>

    <!-- PASO 9: Éxito tarjeta/Bizum -->
    <div class="plr-paso" data-paso="9">
        <div class="plr-exito">
            <div class="plr-exito-icono">✅</div>
            <h2>¡Reserva confirmada!</h2>
            <p>Hemos enviado todos los detalles a tu email. Nos vemos el <strong id="plr-exito-fecha">—</strong> a las <strong id="plr-exito-hora">—</strong>.</p>
            <p class="plr-exito-info">El resto del pago lo completarás cuando el servicio haya finalizado.</p>
        </div>
    </div>

    <!-- Progreso -->
    <div class="plr-progreso">
        <?php for ( $i = 1; $i <= 7; $i++ ) : ?>
            <div class="plr-progreso-paso <?php echo 1 === $i ? 'activo' : '' ?>" data-paso-indicador="<?php echo $i ?>"></div>
        <?php endfor; ?>
    </div>

</div>
