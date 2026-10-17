/**
 * Lógica de interfaz de cliente para La Taberna de las Gatitas Jugonas
 */
document.addEventListener('DOMContentLoaded', function () {

    // 1. Cambio de pestañas en login.php y paneles agrupados
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

    // 2. Previsualización de imagen antes de subirla y validación de 5 MB
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

    // 3. Filtro instantáneo por texto para tablas
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

    // 5. Conmutador interactivo entre Vista A (Imagen) y Vista B (Estadísticas) en ludoteca.php
    var botonesVisor = document.querySelectorAll('[data-visor-card][data-visor-target]');
    botonesVisor.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var cardId = btn.getAttribute('data-visor-card');
            var vistaObjetivo = btn.getAttribute('data-visor-target');
            var contenedorVisor = document.getElementById(cardId);
            if (!contenedorVisor) return;

            contenedorVisor.querySelectorAll('[data-visor-target]').forEach(function (b) {
                b.classList.remove('activa');
            });
            btn.classList.add('activa');

            contenedorVisor.querySelectorAll('[data-visor-panel]').forEach(function (panel) {
                if (panel.getAttribute('data-visor-panel') === vistaObjetivo) {
                    panel.classList.remove('panel-oculto');
                } else {
                    panel.classList.add('panel-oculto');
                }
            });
        });
    });

    // 6. Mostrar u ocultar el selector de calificación según si ha jugado ("1") o no ("0")
    var radiosHaJugado = document.querySelectorAll('input[type="radio"][data-ha-jugado-input]');
    radiosHaJugado.forEach(function (radio) {
        radio.addEventListener('change', function () {
            var idBloqueCal = radio.getAttribute('data-ha-jugado-input');
            var bloqueCal = document.getElementById(idBloqueCal);
            if (!bloqueCal) return;

            if (radio.value === '1' && radio.checked) {
                bloqueCal.classList.remove('panel-oculto');
            } else if (radio.value === '0' && radio.checked) {
                bloqueCal.classList.add('panel-oculto');
            }
        });
    });

    // 7. Filtro instantáneo de tarjetas de juegos y reseñas en ludoteca.php
    var buscadoresLudoteca = document.querySelectorAll('[data-ludoteca-filter]');
    buscadoresLudoteca.forEach(function (inputFiltro) {
        inputFiltro.addEventListener('input', function () {
            var idContenedor = inputFiltro.getAttribute('data-ludoteca-filter');
            var contenedor = document.getElementById(idContenedor);
            if (!contenedor) return;

            var termino = inputFiltro.value.toLowerCase().trim();
            var tarjetas = contenedor.querySelectorAll('.ludoteca-tarjeta');

            tarjetas.forEach(function (tarjeta) {
                var textoTarjeta = tarjeta.textContent.toLowerCase();
                if (textoTarjeta.indexOf(termino) !== -1) {
                    tarjeta.style.display = '';
                } else {
                    tarjeta.style.display = 'none';
                }
            });
        });
    });
});