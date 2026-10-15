
document.addEventListener('DOMContentLoaded', () => {
    // ELEMENTOS DEL LISTADO Y LOS FILTROS
    const formulario = document.getElementById('form-filtros');
    const tabla = document.getElementById('tabla-reservas');
    const mensaje = document.getElementById('mensaje-reservas');

    // ELEMENTOS DEL FORMULARIO DE CREACIÓN
    const formularioCrear = document.getElementById('form-crear-reserva');
    const mensajeCrear = document.getElementById('mensaje-crear');
    const botonCrear = document.getElementById('btn-crear-reserva');

    let controladorPeticion = null;

    // FORMATO DE LOS DATOS

    function formatearFecha(fecha) {
        const [anio, mes, dia] = fecha.split('-');
        return `${dia}/${mes}/${anio}`;
    }

    function formatearImporte(importe) {
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: 'EUR'
        }).format(Number(importe));
    }

    function crearCelda(fila, valor) {
        const celda = document.createElement('td');
        celda.textContent = valor;
        fila.appendChild(celda);
        return celda;
    }

    // MOSTRAR RESERVAS EN LA TABLA

    function mostrarReservas(reservas) {
        tabla.replaceChildren();

        if (reservas.length === 0) {
            const fila = document.createElement('tr');
            const celda = crearCelda(
                fila,
                'No se encontraron reservas con esos filtros.'
            );

            celda.colSpan = 8;
            tabla.appendChild(fila);
            return;
        }

        reservas.forEach((reserva) => {
            const fila = document.createElement('tr');

            crearCelda(fila, reserva.id);
            crearCelda(fila, reserva.nombre_huesped);
            crearCelda(fila, reserva.nombre_alojamiento);
            crearCelda(fila, formatearFecha(reserva.fecha_entrada));
            crearCelda(fila, formatearFecha(reserva.fecha_salida));
            crearCelda(fila, reserva.estado);
            crearCelda(fila, formatearImporte(reserva.importe));

            const celdaAcciones = document.createElement('td');
            const enlace = document.createElement('a');

            enlace.href = `/reservas/${encodeURIComponent(reserva.id)}`;
            enlace.textContent = 'Ver detalle';

            celdaAcciones.appendChild(enlace);
            fila.appendChild(celdaAcciones);
            tabla.appendChild(fila);
        });
    }

    // FILTRAR RESERVAS MEDIANTE GET

    async function cargarReservas() {
        if (controladorPeticion) {
            controladorPeticion.abort();
        }

        const peticionActual = new AbortController();
        controladorPeticion = peticionActual;

        const parametros = new URLSearchParams();
        const datos = new FormData(formulario);

        for (const [clave, valor] of datos.entries()) {
            const texto = valor.trim();

            if (texto !== '') {
                parametros.append(clave, texto);
            }
        }

        const fechaDesde = datos.get('fecha_desde');
        const fechaHasta = datos.get('fecha_hasta');

        if (fechaDesde && fechaHasta && fechaDesde > fechaHasta) {
            controladorPeticion = null;
            mensaje.textContent =
                'La fecha inicial no puede ser posterior a la final.';
            return;
        }

        mensaje.textContent = 'Cargando reservas...';

        try {
            const respuesta = await fetch(
                `/api/reservas?${parametros.toString()}`,
                { signal: peticionActual.signal }
            );

            const resultado = await respuesta.json();

            if (!respuesta.ok) {
                throw new Error(
                    resultado.error || 'No se pudieron cargar las reservas.'
                );
            }

            if (!Array.isArray(resultado)) {
                throw new Error('La respuesta de la API no es válida.');
            }

            // Ignorar respuestas que ya no corresponden
            // a la última búsqueda.
            if (controladorPeticion !== peticionActual) {
                return;
            }

            mostrarReservas(resultado);

            mensaje.textContent =
                `${resultado.length} reserva(s) encontrada(s).`;

        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            if (controladorPeticion === peticionActual) {
                mensaje.textContent = `Error: ${error.message}`;
            }

        } finally {
            if (controladorPeticion === peticionActual) {
                controladorPeticion = null;
            }
        }
    }

    // CREAR UNA NUEVA RESERVA MEDIANTE POST

    async function crearReserva(evento) {
        evento.preventDefault();

        const datosFormulario = new FormData(formularioCrear);

        const datos = {
            nombre_huesped: datosFormulario.get('nombre_huesped').trim(),
            email_huesped: datosFormulario.get('email_huesped').trim(),
            nombre_alojamiento: datosFormulario.get('nombre_alojamiento').trim(),
            fecha_entrada: datosFormulario.get('fecha_entrada'),
            fecha_salida: datosFormulario.get('fecha_salida'),
            importe: datosFormulario.get('importe'),
            notas: datosFormulario.get('notas').trim()
        };

        // Validación adicional a las restricciones HTML.
        if (
            !datos.nombre_huesped ||
            !datos.email_huesped ||
            !datos.nombre_alojamiento ||
            !datos.fecha_entrada ||
            !datos.fecha_salida ||
            !datos.importe
        ) {
            mensajeCrear.textContent =
                'Debes completar todos los campos obligatorios.';
            return;
        }

        if (datos.fecha_salida <= datos.fecha_entrada) {
            mensajeCrear.textContent =
                'La fecha de salida debe ser posterior a la de entrada.';
            return;
        }

        if (
            !/^\d{1,8}(\.\d{1,2})?$/.test(datos.importe)
        ) {
            mensajeCrear.textContent =
                'El importe debe ser válido y tener como máximo dos decimales.';
            return;
        }

        // Evitar envíos duplicados.
        botonCrear.disabled = true;
        mensajeCrear.textContent = 'Creando reserva...';

        try {
            const respuesta = await fetch('/api/reservas', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(datos)
            });

            const resultado = await respuesta.json();

            if (!respuesta.ok) {
                throw new Error(
                    resultado.error || 'No se pudo crear la reserva.'
                );
            }

            // Limpiar el formulario de creación.
            formularioCrear.reset();

            mensajeCrear.textContent =
                `Reserva #${resultado.id} creada correctamente.`;

            // Actualizar el listado respetando los filtros actuales.
            await cargarReservas();

        } catch (error) {
            mensajeCrear.textContent =
                `Error: ${error.message}`;

        } finally {
            botonCrear.disabled = false;
        }
    }

    // EVENTOS

    formulario.addEventListener('submit', (evento) => {
        evento.preventDefault();
        cargarReservas();
    });

    formulario.addEventListener('reset', () => {
        requestAnimationFrame(() => {
            cargarReservas();
        });
    });

    formularioCrear.addEventListener('submit', crearReserva);
});
