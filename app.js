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
});