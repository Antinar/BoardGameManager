/**
 * Lógica de interfaz de cliente para La Taberna de las Gatitas Jugonas
 */
document.addEventListener('DOMContentLoaded', function () {

    // 1. Cambio de pestañas en login.php y admin.php
    var botonesPestana = document.querySelectorAll('[data-tab-target]');
    botonesPestana.forEach(function (boton) {
        boton.addEventListener('click', function () {
            var grupo = boton.getAttribute('data-tab-group') || 'default';
            var destinoSelector = boton.getAttribute('data-tab-target');

            document.querySelectorAll('[data-tab-group="' + grupo + '"]').forEach(function (b) {
                b.classList.remove('activa');
            });

            document.querySelectorAll('[data-tab-content-group="' + grupo + '"]').forEach(function (panel) {
                panel.classList.add('panel-oculto');
            });

            boton.classList.add('activa');
            var panelDestino = document.querySelector(destinoSelector);
            if (panelDestino) {
                panelDestino.classList.remove('panel-oculto');
            }
        });
    });

    // 2. Previsualización de imagen antes de subirla
    var inputsImagen = document.querySelectorAll('input[type="file"][data-preview-target]');
    inputsImagen.forEach(function (input) {
        input.addEventListener('change', function () {
            var idContenedor = input.getAttribute('data-preview-target');
            var contenedor = document.getElementById(idContenedor);
            if (!contenedor) return;

            var imgTag = contenedor.querySelector('img');
            if (input.files && input.files[0]) {
                var archivo = input.files[0];
                if (archivo.size > 5 * 1024 * 1024) {
                    alert('Atención: El archivo supera el límite de 5 MB permitido por el servidor.');
                    input.value = '';
                    contenedor.style.display = 'none';
                    return;
                }
                var lector = new FileReader();
                lector.onload = function (e) {
                    if (imgTag) {
                        imgTag.src = e.target.result;
                        contenedor.style.display = 'block';
                    }
                };
                lector.readAsDataURL(archivo);
            } else {
                contenedor.style.display = 'none';
            }
        });
    });

    // 3. Filtro instantáneo por texto para tablas extensas
    var buscadoresTabla = document.querySelectorAll('[data-table-filter]');
    buscadoresTabla.forEach(function (inputBusqueda) {
        inputBusqueda.addEventListener('input', function () {
            var idTabla = inputBusqueda.getAttribute('data-table-filter');
            var tabla = document.getElementById(idTabla);
            if (!tabla) return;

            var termino = inputBusqueda.value.toLowerCase().trim();
            var filas = tabla.querySelectorAll('tbody tr');

            filas.forEach(function (fila) {
                var textoFila = fila.textContent.toLowerCase();
                if (textoFila.indexOf(termino) !== -1) {
                    fila.style.display = '';
                } else {
                    fila.style.display = 'none';
                }
            });
        });
    });

    // 4. Confirmación en formularios de borrado
    var formsPeligro = document.querySelectorAll('form.form-confirmar-borrado');
    formsPeligro.forEach(function (formulario) {
        formulario.addEventListener('submit', function (e) {
            var mensaje = formulario.getAttribute('data-confirm-msg') || '¿Estás seguro de que deseas eliminar este registro de forma permanente?';
            if (!window.confirm(mensaje)) {
                e.preventDefault();
            }
        });
    });
    // 5. Desplegar / Contraer bloques de votación
    var cabecerasVotacion = document.querySelectorAll('[data-toggle-votacion]');
    cabecerasVotacion.forEach(function (cabecera) {
        cabecera.addEventListener('click', function () {
            var bloque = cabecera.closest('.votacion-bloque');
            if (!bloque) return;

            var estaContraida = bloque.classList.toggle('contraida');
            var btnTexto = cabecera.querySelector('.btn-toggle-texto');
            if (btnTexto) {
                btnTexto.textContent = estaContraida ? 'Desplegar' : 'Contraer';
            }
        });
    });
    
    // 6. Mostrar / ocultar campos de calificación (1-5) y opinión en Ludoteca según "¿Lo has jugado?"
    var radiosJugado = document.querySelectorAll('input[type="radio"][data-toggle-jugado]');
    radiosJugado.forEach(function (radio) {
        radio.addEventListener('change', function () {
            var targetId = radio.getAttribute('data-toggle-jugado');
            var bloque = document.getElementById(targetId);
            if (!bloque) return;

            var selectCal = bloque.querySelector('select[name="calificacion"]');
            if (radio.value === '1' && radio.checked) {
                bloque.style.display = '';
                if (selectCal) selectCal.required = true;
            } else {
                bloque.style.display = 'none';
                if (selectCal) selectCal.required = false;
            }
        });
    });

    // 7. Buscador, filtro de estado personal y ordenación por preferencias en Ludoteca (ludoteca.php)
    var contenedorLudoteca = document.getElementById('contenedor-ludoteca');
    var inputBuscarLudoteca = document.getElementById('buscador-ludoteca');
    var selectFiltroLudoteca = document.getElementById('filtro-estado-ludoteca');
    var selectOrdenLudoteca = document.getElementById('orden-ludoteca');
    var msgSinResultadosLudoteca = document.getElementById('ludoteca-sin-resultados');

    if (contenedorLudoteca) {
        var tarjetasLudoteca = Array.prototype.slice.call(contenedorLudoteca.querySelectorAll('.tarjeta-ludoteca'));

        function actualizarVistaLudoteca() {
            var texto = inputBuscarLudoteca ? inputBuscarLudoteca.value.toLowerCase().trim() : '';
            var estadoFiltro = selectFiltroLudoteca ? selectFiltroLudoteca.value : 'todos';
            var criterioOrden = selectOrdenLudoteca ? selectOrdenLudoteca.value : 'alfabetico';

            // 1. Ordenar las tarjetas en memoria según el criterio elegido
            tarjetasLudoteca.sort(function (a, b) {
                var nombreA = (a.getAttribute('data-nombre') || '').toLowerCase();
                var nombreB = (b.getAttribute('data-nombre') || '').toLowerCase();

                if (criterioOrden === 'preferencias') {
                    var notaA = parseFloat(a.getAttribute('data-nota-media'));
                    var notaB = parseFloat(b.getAttribute('data-nota-media'));
                    if (isNaN(notaA)) notaA = -1;
                    if (isNaN(notaB)) notaB = -1;

                    // De mayor a menor nota media (los de -1 sin nota quedan al final del todo)
                    if (notaB !== notaA) {
                        return notaB - notaA;
                    }
                }
                // Desempate o criterio alfabético por defecto
                return nombreA.localeCompare(nombreB, 'es');
            });

            // 2. Reinsertar en el DOM en el nuevo orden y aplicar filtros de visibilidad
            var visibles = 0;
            tarjetasLudoteca.forEach(function (tarjeta) {
                contenedorLudoteca.appendChild(tarjeta);

                var nombre = (tarjeta.getAttribute('data-nombre') || '').toLowerCase();
                var miEstado = tarjeta.getAttribute('data-mi-estado') || '';

                var coincideNombre = (texto === '' || nombre.indexOf(texto) !== -1);
                var coincideEstado = (estadoFiltro === 'todos' || miEstado === estadoFiltro);

                if (coincideNombre && coincideEstado) {
                    tarjeta.style.display = '';
                    visibles++;
                } else {
                    tarjeta.style.display = 'none';
                }
            });

            if (msgSinResultadosLudoteca) {
                msgSinResultadosLudoteca.style.display = (visibles === 0) ? 'block' : 'none';
            }
        }

        if (inputBuscarLudoteca) {
            inputBuscarLudoteca.addEventListener('input', actualizarVistaLudoteca);
        }
        if (selectFiltroLudoteca) {
            selectFiltroLudoteca.addEventListener('change', actualizarVistaLudoteca);
        }
        if (selectOrdenLudoteca) {
            selectOrdenLudoteca.addEventListener('change', actualizarVistaLudoteca);
        }
    }
});