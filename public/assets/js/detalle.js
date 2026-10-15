
document.addEventListener('DOMContentLoaded', () => {
    const acciones = document.getElementById('acciones-reserva');
    const estadoReserva = document.getElementById('estado-reserva');
    const mensaje = document.getElementById('mensaje-estado');
    const historial = document.getElementById('historial-reserva');

    const idReserva = acciones.dataset.id;

    function formatearFecha(fecha) {
        const [fechaDia, hora] = fecha.split(' ');
        const [anio, mes, dia] = fechaDia.split('-');

        return `${dia}/${mes}/${anio} ${hora.slice(0, 5)}`;
    }

    // Actualiza los botones según el estado recibido.
    function actualizarAcciones(estado) {
        acciones.replaceChildren();

        if (estado === 'cancelada') {
            const texto = document.createElement('p');
            texto.textContent =
                'Esta reserva está cancelada y no puede modificarse.';
            acciones.appendChild(texto);
            return;
        }

        if (estado === 'pendiente') {
            const confirmar = document.createElement('button');
            confirmar.type = 'button';
            confirmar.id = 'btn-confirmar';
            confirmar.textContent = 'Confirmar reserva';

            confirmar.addEventListener('click', () => {
                cambiarEstado('confirmada');
            });

            acciones.appendChild(confirmar);
        }

        const cancelar = document.createElement('button');
        cancelar.type = 'button';
        cancelar.id = 'btn-cancelar';
        cancelar.textContent = 'Cancelar reserva';

        cancelar.addEventListener('click', () => {
            cambiarEstado('cancelada');
        });

        acciones.appendChild(cancelar);
    }

    // Actualiza el historial con los datos de la API.
    function actualizarHistorial(eventos) {
        historial.replaceChildren();

        if (eventos.length === 0) {
            const elemento = document.createElement('li');
            elemento.textContent = 'No hay eventos registrados.';
            historial.appendChild(elemento);
            return;
        }

        eventos.forEach((evento) => {
            const elemento = document.createElement('li');

            const tipo = document.createElement('strong');
            tipo.textContent =
                evento.tipo.charAt(0).toUpperCase() +
                evento.tipo.slice(1);

            const descripcion = document.createElement('p');
            descripcion.textContent = evento.descripcion;

            const fecha = document.createElement('time');
            fecha.dateTime = evento.fecha_creacion;
            fecha.textContent = formatearFecha(evento.fecha_creacion);

            elemento.append(tipo, descripcion, fecha);
            historial.appendChild(elemento);
        });
    }

    // Envía el cambio de estado a la API.
    async function cambiarEstado(nuevoEstado) {
        if (nuevoEstado === 'cancelada') {
            const confirmado = window.confirm(
                '¿Seguro que quieres cancelar esta reserva?'
            );

            if (!confirmado) {
                return;
            }
        }

        const botones = acciones.querySelectorAll('button');

        botones.forEach((boton) => {
            boton.disabled = true;
        });

        mensaje.textContent = 'Actualizando reserva...';

        try {
            const respuesta = await fetch(
                `/api/reservas/${encodeURIComponent(idReserva)}/estado`,
                {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        estado: nuevoEstado
                    })
                }
            );

            const resultado = await respuesta.json();

            if (!respuesta.ok) {
                throw new Error(
                    resultado.error ||
                    'No se pudo actualizar el estado.'
                );
            }

            // Obtener la información actualizada del servidor.
            const respuestaDetalle = await fetch(
                `/api/reservas/${encodeURIComponent(idReserva)}`
            );

            const detalle = await respuestaDetalle.json();

            if (!respuestaDetalle.ok) {
                throw new Error(
                    detalle.error ||
                    'No se pudo actualizar el detalle.'
                );
            }

            estadoReserva.textContent = detalle.estado;
            acciones.dataset.estado = detalle.estado;

            actualizarAcciones(detalle.estado);
            actualizarHistorial(detalle.historial);

            mensaje.textContent =
                `Estado actualizado correctamente: ${detalle.estado}.`;

        } catch (error) {
            mensaje.textContent = `Error: ${error.message}`;

            // Consultar el estado real por si el cambio se guardó
            // pero falló la actualización visual.
            try {
                const respuesta = await fetch(
                    `/api/reservas/${encodeURIComponent(idReserva)}`
                );

                if (respuesta.ok) {
                    const detalle = await respuesta.json();

                    estadoReserva.textContent = detalle.estado;
                    acciones.dataset.estado = detalle.estado;

                    actualizarAcciones(detalle.estado);
                    actualizarHistorial(detalle.historial);
                    return;
                }
            } catch {
                // Mantener el mensaje de error original.
            }

            // Si no se pudo consultar el servidor,
            // permitir reintentar sin recargar la página.
            botones.forEach((boton) => {
                boton.disabled = false;
            });
        }
    }

    // Conectar los botones creados inicialmente por Twig.
    const botonConfirmar = document.getElementById('btn-confirmar');
    const botonCancelar = document.getElementById('btn-cancelar');

    if (botonConfirmar) {
        botonConfirmar.addEventListener('click', () => {
            cambiarEstado('confirmada');
        });
    }

    if (botonCancelar) {
        botonCancelar.addEventListener('click', () => {
            cambiarEstado('cancelada');
        });
    }
});
