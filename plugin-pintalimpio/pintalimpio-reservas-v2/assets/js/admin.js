/**
 * Admin JS — Pintalimpio Reservas
 * Autor: Jhon Bastidas Rodrigues | controlhorariowp.com
 */
( function () {
    // Confirmar cambio de estado antes de enviar el form
    document.querySelectorAll( '.plr-form-estado' ).forEach( form => {
        form.addEventListener( 'submit', e => {
            const select = form.querySelector( 'select[name="plr_cambiar_estado"]' );
            if ( select && select.value === 'cancelado' ) {
                if ( ! confirm( '¿Estás seguro de que quieres CANCELAR esta reserva? Se notificará al cliente.' ) ) {
                    e.preventDefault();
                }
            }
        } );
    } );
} )();
