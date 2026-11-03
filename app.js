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

    // 1.1. Rotación suave de las imágenes de fondo del header (cada 2 segundos)
    var capasFondoHeader = document.querySelectorAll('.header-fondo-img');
    if (capasFondoHeader.length > 1) {
        var movimientoReducidoHeader = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!movimientoReducidoHeader) {
            var indiceFondoHeader = 0;
            window.setInterval(function () {
                if (document.hidden) return;
                capasFondoHeader[indiceFondoHeader].classList.remove('activa');
                indiceFondoHeader = (indiceFondoHeader + 1) % capasFondoHeader.length;
                capasFondoHeader[indiceFondoHeader].classList.add('activa');
            }, 2000);
        }
    }

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
    // 5. Desplegar / Contraer bloques de votación (con animación) y panel de edición del creador
    var prefiereMovimientoReducido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function alternarVotacion(bloque) {
        var cuerpo = bloque.querySelector('.votacion-cuerpo');
        if (!cuerpo || prefiereMovimientoReducido) {
            bloque.classList.toggle('contraida');
            return;
        }
        if (bloque.getAttribute('data-animando') === '1') return;
        bloque.setAttribute('data-animando', '1');

        var estabaContraida = bloque.classList.contains('contraida');
        var alturaInicial = estabaContraida ? 0 : cuerpo.scrollHeight;

        cuerpo.style.overflow = 'hidden';
        cuerpo.style.height = alturaInicial + 'px';
        cuerpo.style.opacity = estabaContraida ? '0' : '1';
        cuerpo.style.transition = 'height 0.32s ease, opacity 0.26s ease';

        if (estabaContraida) {
            bloque.classList.remove('contraida');
        }

        // Forzar un reflow para que el navegador registre el punto de partida.
        void cuerpo.offsetHeight;

        var alturaFinal = estabaContraida ? cuerpo.scrollHeight : 0;
        cuerpo.style.height = alturaFinal + 'px';
        cuerpo.style.opacity = estabaContraida ? '1' : '0';

        var terminado = false;
        function finalizarAnimacion() {
            if (terminado) return;
            terminado = true;
            cuerpo.removeEventListener('transitionend', alTerminarTransicion);
            if (!estabaContraida) {
                bloque.classList.add('contraida');
            }
            cuerpo.style.height = '';
            cuerpo.style.opacity = '';
            cuerpo.style.overflow = '';
            cuerpo.style.transition = '';
            bloque.removeAttribute('data-animando');
        }
        function alTerminarTransicion(ev) {
            if (ev.target === cuerpo && ev.propertyName === 'height') {
                finalizarAnimacion();
            }
        }
        cuerpo.addEventListener('transitionend', alTerminarTransicion);
        // Respaldo por si transitionend no se dispara.
        window.setTimeout(finalizarAnimacion, 500);
    }

    var cabecerasVotacion = document.querySelectorAll('[data-toggle-votacion]');
    cabecerasVotacion.forEach(function (cabecera) {
        cabecera.addEventListener('click', function (e) {
            if (e.target.closest('[data-toggle-editar-votacion]')) {
                return;
            }
            var bloque = cabecera.closest('.votacion-bloque');
            if (!bloque) return;
            alternarVotacion(bloque);
        });
    });

    var botonesEditarVotacion = document.querySelectorAll('[data-toggle-editar-votacion]');
    botonesEditarVotacion.forEach(function (boton) {
        boton.addEventListener('click', function (e) {
            e.stopPropagation();
            var idPanel = boton.getAttribute('data-toggle-editar-votacion');
            var panel = document.getElementById(idPanel);
            var bloque = boton.closest('.votacion-bloque');

            // Si la votación estaba contraída al pulsar Editar, la desplegamos y abrimos el panel
            if (bloque && bloque.classList.contains('contraida')) {
                if (panel) panel.classList.remove('panel-oculto');
                alternarVotacion(bloque);
                return;
            }
            if (panel) {
                panel.classList.toggle('panel-oculto');
            }
        });
    });

    // 5.B. Componente universal de buscador para cuadrículas de tarjetas ([data-card-filter])
    var buscadoresTarjetas = document.querySelectorAll('[data-card-filter]');
    buscadoresTarjetas.forEach(function (inputBusqueda) {
        inputBusqueda.addEventListener('input', function () {
            var idContenedor = inputBusqueda.getAttribute('data-card-filter');
            var contenedor = document.getElementById(idContenedor);
            if (!contenedor) return;

            var termino = inputBusqueda.value.toLowerCase().trim();
            var tarjetas = contenedor.querySelectorAll('[data-card-item]');

            tarjetas.forEach(function (tarjeta) {
                var nombre = (tarjeta.getAttribute('data-nombre') || tarjeta.textContent || '').toLowerCase();
                if (termino === '' || nombre.indexOf(termino) !== -1) {
                    tarjeta.style.display = '';
                } else {
                    tarjeta.style.display = 'none';
                }
            });
        });
    });
    
    // 6. Mostrar / ocultar selector de calificación (0-10) según "¿Lo has jugado?"
    var radiosJugado = document.querySelectorAll('input[type="radio"][data-toggle-jugado]');
    radiosJugado.forEach(function (radio) {
        radio.addEventListener('change', function () {
            var targetId = radio.getAttribute('data-toggle-jugado');
            var bloque = document.getElementById(targetId);
            if (!bloque) return;

            var selectCal = bloque.querySelector('select[name="calificacion"]');
            if (radio.value === '1' && radio.checked) {
                bloque.style.display = '';
                if (selectCal) {
                    selectCal.disabled = false;
                    selectCal.required = true;
                }
            } else {
                bloque.style.display = 'none';
                if (selectCal) {
                    selectCal.disabled = true;
                    selectCal.required = false;
                    selectCal.value = '';
                }
            }
        });
    });

    // 7. Panel Avanzado de Filtros Dinámicos (AND/OR secuencial, Negación, Reordenación y Redimensionado vertical)
    var DEFINICION_FILTROS = {
        ludoteca: [
            { tipo: 'mio',       etiqueta: 'En mi colección (Lo tengo)', requiereNumero: false },
            { tipo: 'jugado',    etiqueta: 'Jugado por mí',              requiereNumero: false },
            { tipo: 'resenyado', etiqueta: 'Reseñado por mí',            requiereNumero: false },
            { tipo: 'jugadores', etiqueta: 'Válido para N jugadores',    requiereNumero: true }
        ],
        deseados: [
            { tipo: 'interesado', etiqueta: 'Me interesa (Estoy apuntado)', requiereNumero: false },
            { tipo: 'jugadores',  etiqueta: 'Válido para N jugadores',      requiereNumero: true }
        ]
    };

    var controladoresPanelesFiltro = {};

    document.querySelectorAll('[data-panel-filtros]').forEach(function (panel) {
        var modo = panel.getAttribute('data-panel-filtros') || 'ludoteca';
        var idGrid = panel.getAttribute('data-target-grid');
        var idSearch = panel.getAttribute('data-search-input');
        var idEmpty = panel.getAttribute('data-empty-msg');

        var contenedorGrid = document.getElementById(idGrid);
        var inputBuscar = document.getElementById(idSearch);
        var msgVacio = document.getElementById(idEmpty);
        if (!contenedorGrid) return;

        var tarjetas = Array.prototype.slice.call(contenedorGrid.querySelectorAll('.tarjeta-ludoteca'));
        var cabeceraToggle = panel.querySelector('[data-toggle-panel-filtros]');
        var badgeResumen = panel.querySelector('[data-resumen-filtros]');
        var selectOrden = panel.querySelector('[data-select-orden-avanzado]');
        var btnAddFiltro = panel.querySelector('[data-btn-add-filtro]');
        var btnAplicar = panel.querySelector('[data-btn-aplicar-filtros]');
        var btnLimpiar = panel.querySelector('[data-btn-limpiar-filtros]');
        var scrollArea = panel.querySelector('[data-filtros-scroll-area]');
        var listaFiltros = panel.querySelector('[data-filtros-lista]');
        var msgFiltrosVacio = panel.querySelector('[data-filtros-vacio]');
        var resizeHandle = panel.querySelector('[data-resize-handle]');

        var opcionesDisponibles = DEFINICION_FILTROS[modo] || DEFINICION_FILTROS.ludoteca;

        // Estado actualmente aplicado (solo cambia al pulsar "Aplicar", "Limpiar" o al restaurar sesión)
        var filtrosAplicados = [];
        var ordenAplicado = selectOrden ? selectOrden.value : 'alfabetico';

        // 1. Desplegar / Contraer el panel avanzado
        if (cabeceraToggle) {
            cabeceraToggle.addEventListener('click', function () {
                panel.classList.toggle('contraido');
            });
        }

        // 2. Actualizar visualmente qué fila es la primera (oculta el selector AND/OR en la fila 1)
        function sincronizarFilasDOM() {
            var filas = listaFiltros.querySelectorAll('.filtro-fila');
            if (msgFiltrosVacio) {
                msgFiltrosVacio.style.display = (filas.length === 0) ? 'block' : 'none';
            }
            filas.forEach(function (fila, idx) {
                var selectOp = fila.querySelector('[data-filtro-operador]');
                var etqInicio = fila.querySelector('[data-filtro-inicio-label]');
                var btnSubir = fila.querySelector('[data-filtro-subir]');
                var btnBajar = fila.querySelector('[data-filtro-bajar]');

                if (idx === 0) {
                    if (selectOp) selectOp.style.display = 'none';
                    if (etqInicio) etqInicio.style.display = 'inline-block';
                } else {
                    if (selectOp) selectOp.style.display = '';
                    if (etqInicio) etqInicio.style.display = 'none';
                }

                if (btnSubir) btnSubir.disabled = (idx === 0);
                if (btnBajar) btnBajar.disabled = (idx === filas.length - 1);
            });
        }

        // 3. Crear una fila de filtro en el DOM
        function crearFilaFiltro(datosIniciales) {
            var cfg = datosIniciales || {};
            var operador = (cfg.operador === 'OR') ? 'OR' : 'AND';
            var tipo = cfg.tipo || opcionesDisponibles[0].tipo;
            var negado = !!cfg.negado;
            var valorNum = (typeof cfg.valor === 'number' && !isNaN(cfg.valor)) ? cfg.valor : 4;

            var fila = document.createElement('div');
            fila.className = 'filtro-fila';

            // Botones para reordenar el filtro (Subir / Bajar)
            var divOrden = document.createElement('div');
            divOrden.className = 'filtro-reordenar-grupo';

            var btnUp = document.createElement('button');
            btnUp.type = 'button';
            btnUp.className = 'btn btn-pequeno btn-mover-filtro';
            btnUp.setAttribute('data-filtro-subir', '1');
            btnUp.title = 'Subir este filtro';
            btnUp.innerHTML = '&#9650;';

            var btnDown = document.createElement('button');
            btnDown.type = 'button';
            btnDown.className = 'btn btn-pequeno btn-mover-filtro';
            btnDown.setAttribute('data-filtro-bajar', '1');
            btnDown.title = 'Bajar este filtro';
            btnDown.innerHTML = '&#9660;';

            divOrden.appendChild(btnUp);
            divOrden.appendChild(btnDown);

            // Puerta lógica (AND por defecto / OR) o etiqueta "Donde" en la primera fila
            var spanInicio = document.createElement('span');
            spanInicio.className = 'filtro-etiqueta-inicio';
            spanInicio.setAttribute('data-filtro-inicio-label', '1');
            spanInicio.textContent = 'Condición:';

            var selectOperador = document.createElement('select');
            selectOperador.className = 'select-taberna filtro-select-operador';
            selectOperador.setAttribute('data-filtro-operador', '1');
            selectOperador.innerHTML =
                '<option value="AND"' + (operador === 'AND' ? ' selected' : '') + '>AND (Y)</option>' +
                '<option value="OR"' + (operador === 'OR' ? ' selected' : '') + '>OR (O)</option>';

            // Selector de tipo de filtro
            var selectTipo = document.createElement('select');
            selectTipo.className = 'select-taberna filtro-select-tipo';
            selectTipo.setAttribute('data-filtro-tipo', '1');
            opcionesDisponibles.forEach(function (op) {
                var opt = document.createElement('option');
                opt.value = op.tipo;
                opt.textContent = op.etiqueta;
                if (op.tipo === tipo) opt.selected = true;
                selectTipo.appendChild(opt);
            });

            // Input numérico adicional cuando se elige "Válido para N jugadores"
            var inputNum = document.createElement('input');
            inputNum.type = 'number';
            inputNum.min = '1';
            inputNum.max = '99';
            inputNum.value = String(valorNum);
            inputNum.className = 'input-taberna filtro-input-numero';
            inputNum.setAttribute('data-filtro-valor', '1');
            inputNum.title = 'Número de jugadores';

            function actualizarVisibilidadNumero() {
                var tActual = selectTipo.value;
                var requiere = false;
                opcionesDisponibles.forEach(function (op) {
                    if (op.tipo === tActual && op.requiereNumero) requiere = true;
                });
                inputNum.style.display = requiere ? '' : 'none';
            }
            selectTipo.addEventListener('change', actualizarVisibilidadNumero);
            actualizarVisibilidadNumero();

            // Checkbox para usar el filtro en negado (NO)
            var labelNegar = document.createElement('label');
            labelNegar.className = 'filtro-check-negar';
            var checkNegar = document.createElement('input');
            checkNegar.type = 'checkbox';
            checkNegar.checked = negado;
            checkNegar.setAttribute('data-filtro-negado', '1');
            var spanTxtNegar = document.createElement('span');
            spanTxtNegar.textContent = 'Negar (NO)';
            labelNegar.appendChild(checkNegar);
            labelNegar.appendChild(spanTxtNegar);

            // Botón eliminar fila
            var btnEliminar = document.createElement('button');
            btnEliminar.type = 'button';
            btnEliminar.className = 'btn btn-peligro btn-pequeno filtro-btn-eliminar';
            btnEliminar.title = 'Quitar este filtro';
            btnEliminar.innerHTML = '&#10005;';

            // Eventos de subir, bajar y eliminar fila
            btnUp.addEventListener('click', function () {
                var prev = fila.previousElementSibling;
                if (prev) {
                    listaFiltros.insertBefore(fila, prev);
                    sincronizarFilasDOM();
                }
            });

            btnDown.addEventListener('click', function () {
                var next = fila.nextElementSibling;
                if (next) {
                    listaFiltros.insertBefore(next, fila);
                    sincronizarFilasDOM();
                }
            });

            btnEliminar.addEventListener('click', function () {
                fila.remove();
                sincronizarFilasDOM();
            });

            fila.appendChild(divOrden);
            fila.appendChild(spanInicio);
            fila.appendChild(selectOperador);
            fila.appendChild(selectTipo);
            fila.appendChild(inputNum);
            fila.appendChild(labelNegar);
            fila.appendChild(btnEliminar);

            listaFiltros.appendChild(fila);
            sincronizarFilasDOM();
        }

        // 4. Leer las filas actuales del DOM
        function leerFiltrosDesdeDOM() {
            var resultado = [];
            var filas = listaFiltros.querySelectorAll('.filtro-fila');
            filas.forEach(function (fila, idx) {
                var selectOp = fila.querySelector('[data-filtro-operador]');
                var selectTipo = fila.querySelector('[data-filtro-tipo]');
                var inputVal = fila.querySelector('[data-filtro-valor]');
                var checkNeg = fila.querySelector('[data-filtro-negado]');

                var numVal = inputVal ? parseInt(inputVal.value, 10) : 4;
                if (isNaN(numVal) || numVal < 1) numVal = 1;

                resultado.push({
                    operador: idx === 0 ? 'AND' : (selectOp ? selectOp.value : 'AND'),
                    tipo: selectTipo ? selectTipo.value : opcionesDisponibles[0].tipo,
                    negado: checkNeg ? !!checkNeg.checked : false,
                    valor: numVal
                });
            });
            return resultado;
        }

        // 5. Evaluar una condición individual sobre una tarjeta
        function evaluarCondicionTarjeta(tarjeta, f) {
            var cumple = true;
            var miEstado = tarjeta.getAttribute('data-mi-estado') || '';

            if (f.tipo === 'mio') {
                cumple = (tarjeta.getAttribute('data-es-mio') === '1');
            } else if (f.tipo === 'jugado') {
                cumple = (miEstado === 'jugado');
            } else if (f.tipo === 'resenyado') {
                cumple = (miEstado !== 'no_resenyado');
            } else if (f.tipo === 'interesado') {
                cumple = (tarjeta.getAttribute('data-interesado') === '1');
            } else if (f.tipo === 'jugadores') {
                var minJ = parseInt(tarjeta.getAttribute('data-min-jugadores') || '1', 10);
                var maxJ = parseInt(tarjeta.getAttribute('data-max-jugadores') || '99', 10);
                cumple = (f.valor >= minJ && f.valor <= maxJ);
            }

            return f.negado ? !cumple : cumple;
        }

        // 6. Ejecutar el filtrado secuencial (AND / OR de arriba abajo) + buscador externo + ordenación
        function ejecutarFiltradoYOrden() {
            var textoBusqueda = inputBuscar ? inputBuscar.value.toLowerCase().trim() : '';

            // Actualizar insignia resumen en la cabecera del panel
            if (badgeResumen) {
                if (filtrosAplicados.length > 0) {
                    badgeResumen.textContent = filtrosAplicados.length + (filtrosAplicados.length === 1 ? ' filtro activo' : ' filtros activos');
                    badgeResumen.style.display = 'inline-block';
                } else {
                    badgeResumen.style.display = 'none';
                }
            }

            // 1. Ordenar tarjetas según el orden aplicado
            tarjetas.sort(function (a, b) {
                var nombreA = (a.getAttribute('data-nombre') || '').toLowerCase();
                var nombreB = (b.getAttribute('data-nombre') || '').toLowerCase();

                if (ordenAplicado === 'preferencias') {
                    var notaA = parseFloat(a.getAttribute('data-nota-media'));
                    var notaB = parseFloat(b.getAttribute('data-nota-media'));
                    if (isNaN(notaA)) notaA = -1;
                    if (isNaN(notaB)) notaB = -1;
                    if (notaB !== notaA) return notaB - notaA;
                } else if (ordenAplicado === 'mi_nota') {
                    var miNotaA = parseFloat(a.getAttribute('data-mi-nota'));
                    var miNotaB = parseFloat(b.getAttribute('data-mi-nota'));
                    if (isNaN(miNotaA)) miNotaA = -1;
                    if (isNaN(miNotaB)) miNotaB = -1;
                    if (miNotaB !== miNotaA) return miNotaB - miNotaA;
                } else if (ordenAplicado === 'interesados') {
                    var intA = parseInt(a.getAttribute('data-num-interesados') || '0', 10);
                    var intB = parseInt(b.getAttribute('data-num-interesados') || '0', 10);
                    if (intB !== intA) return intB - intA;
                }

                return nombreA.localeCompare(nombreB, 'es');
            });

            // 2. Reinsertar en el DOM y evaluar buscador externo + cadena secuencial de filtros
            var visibles = 0;
            tarjetas.forEach(function (tarjeta) {
                contenedorGrid.appendChild(tarjeta);

                var nombre = (tarjeta.getAttribute('data-nombre') || '').toLowerCase();
                var coincideTexto = (textoBusqueda === '' || nombre.indexOf(textoBusqueda) !== -1);

                var pasaFiltros = true;
                if (filtrosAplicados.length > 0) {
                    // Precedencia lógica estándar: se evalúan primero los grupos AND y luego se unen con OR
                    pasaFiltros = false;
                    var grupoAndActual = evaluarCondicionTarjeta(tarjeta, filtrosAplicados[0]);

                    for (var i = 1; i < filtrosAplicados.length; i++) {
                        var evalActual = evaluarCondicionTarjeta(tarjeta, filtrosAplicados[i]);
                        if (filtrosAplicados[i].operador === 'OR') {
                            // Cerramos el bloque AND anterior: si ya era true, basta para el OR general
                            if (grupoAndActual) {
                                pasaFiltros = true;
                                break;
                            }
                            // Iniciamos un nuevo término para el siguiente bloque de ANDs
                            grupoAndActual = evalActual;
                        } else {
                            // Operador AND: se resuelve primero dentro del término actual
                            grupoAndActual = grupoAndActual && evalActual;
                        }
                    }

                    if (grupoAndActual) {
                        pasaFiltros = true;
                    }
                }

                if (coincideTexto && pasaFiltros) {
                    tarjeta.style.display = '';
                    visibles++;
                } else {
                    tarjeta.style.display = 'none';
                }
            });

            if (msgVacio) {
                msgVacio.style.display = (visibles === 0) ? 'block' : 'none';
            }
        }

        // Eventos de botones del panel
        if (btnAddFiltro) {
            btnAddFiltro.addEventListener('click', function () {
                crearFilaFiltro();
                if (scrollArea) {
                    scrollArea.scrollTop = scrollArea.scrollHeight;
                }
            });
        }

        if (btnAplicar) {
            btnAplicar.addEventListener('click', function () {
                filtrosAplicados = leerFiltrosDesdeDOM();
                ordenAplicado = selectOrden ? selectOrden.value : 'alfabetico';
                ejecutarFiltradoYOrden();
            });
        }

        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', function () {
                listaFiltros.innerHTML = '';
                sincronizarFilasDOM();
                if (selectOrden) selectOrden.value = 'alfabetico';
                filtrosAplicados = [];
                ordenAplicado = 'alfabetico';
                ejecutarFiltradoYOrden();
            });
        }

        // El buscador externo por nombre sigue actuando en tiempo real combinado con los filtros aplicados
        if (inputBuscar) {
            inputBuscar.addEventListener('input', ejecutarFiltradoYOrden);
        }

        // 7. Tirador en la esquina inferior izquierda para redimensionar verticalmente (con altura mínima de 110px)
        var ALTURA_MINIMA_SCROLL = 110;
        var ALTURA_MAXIMA_SCROLL = 520;

        if (resizeHandle && scrollArea) {
            var arrastrandoResize = false;
            var startY = 0;
            var startHeight = 0;

            function iniciarResize(clientY) {
                arrastrandoResize = true;
                startY = clientY;
                startHeight = scrollArea.offsetHeight;
                document.body.style.userSelect = 'none';
            }

            function moverResize(clientY) {
                if (!arrastrandoResize) return;
                var nuevaAltura = startHeight + (clientY - startY);
                if (nuevaAltura < ALTURA_MINIMA_SCROLL) nuevaAltura = ALTURA_MINIMA_SCROLL;
                if (nuevaAltura > ALTURA_MAXIMA_SCROLL) nuevaAltura = ALTURA_MAXIMA_SCROLL;
                scrollArea.style.height = nuevaAltura + 'px';
                scrollArea.style.maxHeight = nuevaAltura + 'px';
            }

            function finalizarResize() {
                if (arrastrandoResize) {
                    arrastrandoResize = false;
                    document.body.style.userSelect = '';
                }
            }

            resizeHandle.addEventListener('mousedown', function (e) {
                e.preventDefault();
                iniciarResize(e.clientY);
            });
            window.addEventListener('mousemove', function (e) {
                moverResize(e.clientY);
            });
            window.addEventListener('mouseup', finalizarResize);

            resizeHandle.addEventListener('touchstart', function (e) {
                if (e.touches && e.touches[0]) {
                    e.preventDefault();
                    iniciarResize(e.touches[0].clientY);
                }
            }, { passive: false });
            window.addEventListener('touchmove', function (e) {
                if (arrastrandoResize && e.touches && e.touches[0]) {
                    moverResize(e.touches[0].clientY);
                }
            }, { passive: true });
            window.addEventListener('touchend', finalizarResize);
        }

        // Registrar controlador para guardar/restaurar su estado al navegar a un juego y volver atrás
        controladoresPanelesFiltro[modo] = {
            obtenerEstado: function () {
                return {
                    abierto: !panel.classList.contains('contraido'),
                    buscar: inputBuscar ? inputBuscar.value : '',
                    ordenDOM: selectOrden ? selectOrden.value : 'alfabetico',
                    ordenAplicado: ordenAplicado,
                    filtrosDOM: leerFiltrosDesdeDOM(),
                    filtrosAplicados: filtrosAplicados,
                    alturaScroll: scrollArea ? scrollArea.style.height : ''
                };
            },
            restaurarEstado: function (est) {
                if (!est) return;
                if (est.abierto) {
                    panel.classList.remove('contraido');
                } else {
                    panel.classList.add('contraido');
                }
                if (inputBuscar && typeof est.buscar === 'string') {
                    inputBuscar.value = est.buscar;
                }
                if (selectOrden && est.ordenDOM) {
                    selectOrden.value = est.ordenDOM;
                }
                if (scrollArea && est.alturaScroll) {
                    scrollArea.style.height = est.alturaScroll;
                    scrollArea.style.maxHeight = est.alturaScroll;
                }
                listaFiltros.innerHTML = '';
                if (Array.isArray(est.filtrosDOM)) {
                    est.filtrosDOM.forEach(function (f) {
                        crearFilaFiltro(f);
                    });
                }
                sincronizarFilasDOM();
                filtrosAplicados = Array.isArray(est.filtrosAplicados) ? est.filtrosAplicados : [];
                ordenAplicado = est.ordenAplicado || 'alfabetico';
                ejecutarFiltradoYOrden();
            }
        };
    });

    // 8. Mostrar / ocultar selector de propietarios en alta_juego.php según "Ya lo tenemos"
    var radiosPropietarios = document.querySelectorAll('input[type="radio"][data-toggle-propietarios]');
    radiosPropietarios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            var idBloque = radio.getAttribute('data-toggle-propietarios');
            var bloque = document.getElementById(idBloque);
            if (!bloque) return;

            if (radio.value === '1' && radio.checked) {
                bloque.style.display = 'block';
            } else {
                bloque.style.display = 'none';
            }
        });
    });

    // 9. Buscador interno para componentes multiselección de propietarios ([data-multiselect])
    var contenedoresMultiselect = document.querySelectorAll('[data-multiselect]');
    contenedoresMultiselect.forEach(function (contenedor) {
        var inputSearch = contenedor.querySelector('[data-multiselect-search]');
        var opciones = contenedor.querySelectorAll('[data-multiselect-item]');
        if (!inputSearch) return;

        inputSearch.addEventListener('input', function () {
            var termino = inputSearch.value.toLowerCase().trim();
            opciones.forEach(function (opcion) {
                var nombre = (opcion.getAttribute('data-nombre') || opcion.textContent || '').toLowerCase();
                if (termino === '' || nombre.indexOf(termino) !== -1) {
                    opcion.style.display = '';
                } else {
                    opcion.style.display = 'none';
                }
            });
        });
    });

    // 9.B. Empaquetado seguro en Hexadecimal de textos e imágenes (evita 100% el 403 Forbidden de openresty en InfinityFree)
    function textoAHexUtf8(texto) {
        var bytes;
        if (typeof TextEncoder !== 'undefined') {
            bytes = new TextEncoder().encode(texto);
        } else {
            var utf8 = unescape(encodeURIComponent(texto));
            bytes = [];
            for (var j = 0; j < utf8.length; j++) {
                bytes.push(utf8.charCodeAt(j));
            }
        }
        var hex = '';
        for (var i = 0; i < bytes.length; i++) {
            var h = bytes[i].toString(16);
            hex += (h.length === 1 ? '0' + h : h);
        }
        return hex;
    }

    function dataUrlAHex(dataUrl) {
        var partes = dataUrl.split(',');
        var b64 = partes.length > 1 ? partes[1] : '';
        var binario = window.atob(b64);
        var hex = '';
        for (var i = 0; i < binario.length; i++) {
            var h = binario.charCodeAt(i).toString(16);
            hex += (h.length === 1 ? '0' + h : h);
        }
        return hex;
    }

    var formsJuegoSeguro = document.querySelectorAll('form[data-form-juego-seguro]');
    formsJuegoSeguro.forEach(function (form) {
        // Crear inputs ocultos para transportar la imagen en hexadecimal sin usar multipart/form-data
        var inputImgHex = document.createElement('input');
        inputImgHex.type = 'hidden';
        inputImgHex.name = 'imagen_cliente_hex';
        form.appendChild(inputImgHex);

        var inputImgNom = document.createElement('input');
        inputImgNom.type = 'hidden';
        inputImgNom.name = 'imagen_cliente_nombre';
        form.appendChild(inputImgNom);

        var inputFile = form.querySelector('input[type="file"]');
        var btnSubmit = form.querySelector('button[type="submit"]');
        var procesandoImg = false;

        if (inputFile) {
            inputFile.addEventListener('change', function () {
                inputImgHex.value = '';
                inputImgNom.value = '';

                if (!inputFile.files || !inputFile.files[0]) {
                    return;
                }

                var archivo = inputFile.files[0];
                if (archivo.size > 5 * 1024 * 1024) {
                    return;
                }

                procesandoImg = true;
                if (btnSubmit) btnSubmit.disabled = true;

                var lector = new FileReader();
                lector.onload = function (ev) {
                    var imgObj = new Image();
                    imgObj.onload = function () {
                        var maxDim = 800;
                        var w = imgObj.width;
                        var h = imgObj.height;
                        if (w > maxDim || h > maxDim) {
                            if (w > h) {
                                h = Math.round((h * maxDim) / w);
                                w = maxDim;
                            } else {
                                w = Math.round((w * maxDim) / h);
                                h = maxDim;
                            }
                        }

                        var canvas = document.createElement('canvas');
                        canvas.width = w;
                        canvas.height = h;
                        var ctx = canvas.getContext('2d');
                        ctx.fillStyle = '#23170f';
                        ctx.fillRect(0, 0, w, h);
                        ctx.drawImage(imgObj, 0, 0, w, h);

                        var jpegDataUrl = canvas.toDataURL('image/jpeg', 0.85);
                        inputImgHex.value = dataUrlAHex(jpegDataUrl);
                        inputImgNom.value = archivo.name || 'juego.jpg';
                        inputFile.removeAttribute('required');

                        procesandoImg = false;
                        if (btnSubmit) btnSubmit.disabled = false;
                    };
                    imgObj.onerror = function () {
                        procesandoImg = false;
                        if (btnSubmit) btnSubmit.disabled = false;
                    };
                    imgObj.src = ev.target.result;
                };
                lector.readAsDataURL(archivo);
            });
        }

        form.addEventListener('submit', function (e) {
            if (procesandoImg) {
                e.preventDefault();
                return;
            }

            ['descripcion', 'reglas'].forEach(function (campo) {
                var txtArea = form.querySelector('textarea[data-campo-seguro="' + campo + '"], textarea[name="' + campo + '"]');
                var inputHex = form.querySelector('input[name="' + campo + '_hex"]');
                if (txtArea && inputHex) {
                    var val = txtArea.value.trim();
                    inputHex.value = val !== '' ? textoAHexUtf8(val) : '';
                    txtArea.removeAttribute('name');
                }
            });

            // Deshabilitar siempre el input file binario y forzar envío estándar x-www-form-urlencoded
            if (inputFile) {
                inputFile.disabled = true;
                inputFile.removeAttribute('name');
            }
            form.setAttribute('enctype', 'application/x-www-form-urlencoded');
            form.enctype = 'application/x-www-form-urlencoded';
        });
    });

    // 9.C. Gráfico temporal acumulativo de victorias en Leaderboard (leaderboard.php)
    var panelGraficoVictorias = document.getElementById('panel-grafico-victorias');
    if (panelGraficoVictorias) {
        var selectRangoRapido = document.getElementById('rango-rapido-grafico');
        var inputFechaDesde = document.getElementById('fecha-desde-grafico');
        var inputFechaHasta = document.getElementById('fecha-hasta-grafico');
        var contenedorLeyenda = document.getElementById('leyenda-grafico-victorias');
        var canvasGrafico = document.getElementById('canvas-grafico-victorias');
        var msgSinVictoriasGrafico = document.getElementById('grafico-sin-victorias');

        var datosVictoriasRaw = [];
        try {
            datosVictoriasRaw = JSON.parse(panelGraficoVictorias.getAttribute('data-victorias') || '[]');
        } catch (e) {
            datosVictoriasRaw = [];
        }
        var fechaHoyStr = panelGraficoVictorias.getAttribute('data-hoy') || new Date().toISOString().slice(0, 10);

        // Paleta rústica de alto contraste para distinguir a cada jugador
        var PALETA_JUGADORES = [
            '#8c2d19', '#2e5a27', '#1f4e78', '#a67c2e', '#5b2c6f',
            '#b05b18', '#116a65', '#78281f', '#34495e', '#6e2c00',
            '#1e8449', '#2874a6'
        ];
        var jugadoresOcultos = {};

        function restarMeses(fechaStr, meses) {
            var p = fechaStr.split('-');
            var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
            d.setMonth(d.getMonth() - meses);
            var y = d.getFullYear();
            var m = String(d.getMonth() + 1);
            if (m.length < 2) m = '0' + m;
            var day = String(d.getDate());
            if (day.length < 2) day = '0' + day;
            return y + '-' + m + '-' + day;
        }

        function aplicarPlazoRapido(plazo) {
            if (!inputFechaDesde || !inputFechaHasta) return;
            inputFechaHasta.value = fechaHoyStr;

            if (plazo === '1m') {
                inputFechaDesde.value = restarMeses(fechaHoyStr, 1);
            } else if (plazo === '3m') {
                inputFechaDesde.value = restarMeses(fechaHoyStr, 3);
            } else if (plazo === '6m') {
                inputFechaDesde.value = restarMeses(fechaHoyStr, 6);
            } else if (plazo === '1y') {
                inputFechaDesde.value = restarMeses(fechaHoyStr, 12);
            } else if (plazo === 'all') {
                if (datosVictoriasRaw.length > 0 && datosVictoriasRaw[0].fecha_dia) {
                    inputFechaDesde.value = datosVictoriasRaw[0].fecha_dia;
                } else {
                    inputFechaDesde.value = restarMeses(fechaHoyStr, 12);
                }
            }
        }

        function formatearFechaCorta(fechaIso) {
            if (!fechaIso) return '';
            var partes = fechaIso.split('-');
            if (partes.length !== 3) return fechaIso;
            return partes[2] + '/' + partes[1] + '/' + partes[0].slice(2);
        }

        function fechaIsoADiasUtc(fechaIso) {
            var p = fechaIso.split('-');
            return Math.round(Date.UTC(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10)) / 86400000);
        }

        function diasUtcAFechaIso(diasUtc) {
            var d = new Date(diasUtc * 86400000);
            var y = d.getUTCFullYear();
            var m = String(d.getUTCMonth() + 1);
            if (m.length < 2) m = '0' + m;
            var day = String(d.getUTCDate());
            if (day.length < 2) day = '0' + day;
            return y + '-' + m + '-' + day;
        }

        function renderizarGraficoVictorias() {
            if (!canvasGrafico) return;
            var desde = inputFechaDesde && inputFechaDesde.value ? inputFechaDesde.value : restarMeses(fechaHoyStr, 12);
            var hasta = inputFechaHasta && inputFechaHasta.value ? inputFechaHasta.value : fechaHoyStr;
            if (desde > hasta) {
                var tmp = desde;
                desde = hasta;
                hasta = tmp;
            }

            // Filtrar solo victorias dentro del rango [desde, hasta] (acumulado exclusivo del periodo)
            var victoriasRango = datosVictoriasRaw.filter(function (v) {
                var d = v.fecha_dia || (v.fecha_victoria ? v.fecha_victoria.slice(0, 10) : '');
                return d >= desde && d <= hasta;
            });

            // Identificar jugadores con al menos 1 victoria en este rango
            var mapaJugadores = {};
            var ordenJugadores = [];
            victoriasRango.forEach(function (v) {
                var u = v.username;
                if (!mapaJugadores[u]) {
                    mapaJugadores[u] = {
                        username: u,
                        nombre: v.NombrePersona || u,
                        totalPeriodo: 0,
                        porDia: {}
                    };
                    ordenJugadores.push(u);
                }
                mapaJugadores[u].totalPeriodo++;
                var dia = v.fecha_dia || v.fecha_victoria.slice(0, 10);
                mapaJugadores[u].porDia[dia] = (mapaJugadores[u].porDia[dia] || 0) + 1;
            });

            ordenJugadores.sort(function (a, b) {
                if (mapaJugadores[b].totalPeriodo !== mapaJugadores[a].totalPeriodo) {
                    return mapaJugadores[b].totalPeriodo - mapaJugadores[a].totalPeriodo;
                }
                return mapaJugadores[a].nombre.localeCompare(mapaJugadores[b].nombre, 'es');
            });

            // Renderizar leyenda interactiva
            if (contenedorLeyenda) {
                contenedorLeyenda.innerHTML = '';
                ordenJugadores.forEach(function (u, idx) {
                    var info = mapaJugadores[u];
                    var color = PALETA_JUGADORES[idx % PALETA_JUGADORES.length];
                    info.color = color;

                    var btnLeyenda = document.createElement('button');
                    btnLeyenda.type = 'button';
                    btnLeyenda.className = 'item-leyenda-grafico' + (jugadoresOcultos[u] ? ' leyenda-oculta' : '');
                    btnLeyenda.innerHTML =
                        '<span class="color-leyenda-muestra" style="background-color:' + color + ';"></span>' +
                        '<span>' + info.nombre + ' (' + info.totalPeriodo + ')</span>';

                    btnLeyenda.addEventListener('click', function () {
                        jugadoresOcultos[u] = !jugadoresOcultos[u];
                        renderizarGraficoVictorias();
                    });
                    contenedorLeyenda.appendChild(btnLeyenda);
                });
            }

            if (ordenJugadores.length === 0) {
                canvasGrafico.style.display = 'none';
                if (msgSinVictoriasGrafico) msgSinVictoriasGrafico.style.display = 'block';
                return;
            }

            canvasGrafico.style.display = 'block';
            if (msgSinVictoriasGrafico) msgSinVictoriasGrafico.style.display = 'none';

            // Escala temporal lineal real día a día entre [desde, hasta]
            var diaInicio = fechaIsoADiasUtc(desde);
            var diaFin = fechaIsoADiasUtc(hasta);
            var totalDiasRango = Math.max(0, diaFin - diaInicio);

            // Calcular series acumulativas reales día a día empezando en 0
            var maxAcumulado = 1;
            ordenJugadores.forEach(function (u) {
                var info = mapaJugadores[u];
                var acum = 0;
                var puntosJugador = [];
                var puntosVictoria = [];

                // Si el primer día ya tuvo victorias, pintamos el arranque en 0 para que se vea el salto inicial
                var fInicio = diasUtcAFechaIso(diaInicio);
                if (info.porDia[fInicio]) {
                    puntosJugador.push({ offset: 0, valor: 0 });
                }

                for (var dOffset = 0; dOffset <= totalDiasRango; dOffset++) {
                    var fActual = diasUtcAFechaIso(diaInicio + dOffset);
                    var ganadasHoy = info.porDia[fActual] || 0;
                    if (ganadasHoy > 0 && dOffset > 0) {
                        // Mantener el nivel anterior hasta el día exacto anterior a la subida
                        puntosJugador.push({ offset: dOffset - 1, valor: acum });
                    }
                    acum += ganadasHoy;
                    if (dOffset === 0 || dOffset === totalDiasRango || ganadasHoy > 0) {
                        puntosJugador.push({ offset: dOffset, valor: acum });
                    }
                    if (ganadasHoy > 0) {
                        puntosVictoria.push({ offset: dOffset, valor: acum });
                    }
                }

                info.puntosLinea = puntosJugador;
                info.puntosVictoria = puntosVictoria;
                if (!jugadoresOcultos[u] && acum > maxAcumulado) {
                    maxAcumulado = acum;
                }
            });

            // Ajustar dimensiones reales del canvas al contenedor
            var anchoContenedor = canvasGrafico.parentElement.clientWidth || 720;
            var altoCanvas = 340;
            canvasGrafico.width = anchoContenedor;
            canvasGrafico.height = altoCanvas;

            var ctx = canvasGrafico.getContext('2d');
            ctx.clearRect(0, 0, anchoContenedor, altoCanvas);

            // Fondo pergamino claro
            ctx.fillStyle = '#f7f1df';
            ctx.fillRect(0, 0, anchoContenedor, altoCanvas);

            var padIzq = 48;
            var padDer = 24;
            var padArr = 22;
            var padAba = 46;
            var wUtil = Math.max(100, anchoContenedor - padIzq - padDer);
            var hUtil = Math.max(80, altoCanvas - padArr - padAba);

            // Rejilla horizontal y etiquetas del eje Y (victorias enteras)
            var pasosY = Math.min(maxAcumulado, 6);
            ctx.font = 'bold 12px Georgia, serif';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';

            for (var i = 0; i <= pasosY; i++) {
                var valY = Math.round((maxAcumulado / pasosY) * i);
                var posY = padArr + hUtil - (valY / maxAcumulado) * hUtil;

                ctx.strokeStyle = '#d5c496';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(padIzq, posY);
                ctx.lineTo(padIzq + wUtil, posY);
                ctx.stroke();

                ctx.fillStyle = '#362417';
                ctx.fillText(String(valY), padIzq - 8, posY);
            }

            function calcularPosX(offsetDias) {
                if (totalDiasRango === 0) return padIzq + wUtil / 2;
                return padIzq + (offsetDias / totalDiasRango) * wUtil;
            }

            // Marcas temporales distribuidas uniformemente en el eje X
            var numMarcasX = totalDiasRango === 0 ? 1 : Math.min(7, totalDiasRango + 1);
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';

            for (var m = 0; m < numMarcasX; m++) {
                var offsetMarca = numMarcasX === 1 ? 0 : Math.round((m / (numMarcasX - 1)) * totalDiasRango);
                var posXMarca = calcularPosX(offsetMarca);
                var fechaMarcaIso = diasUtcAFechaIso(diaInicio + offsetMarca);

                // Pequeña marca vertical en el eje X
                ctx.strokeStyle = '#362417';
                ctx.lineWidth = 1.5;
                ctx.beginPath();
                ctx.moveTo(posXMarca, padArr + hUtil);
                ctx.lineTo(posXMarca, padArr + hUtil + 5);
                ctx.stroke();

                ctx.fillStyle = '#362417';
                ctx.fillText(formatearFechaCorta(fechaMarcaIso), posXMarca, padArr + hUtil + 9);
            }

            // Ejes principales en madera oscura
            ctx.strokeStyle = '#362417';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(padIzq, padArr);
            ctx.lineTo(padIzq, padArr + hUtil);
            ctx.lineTo(padIzq + wUtil, padArr + hUtil);
            ctx.stroke();

            // Dibujar líneas acumulativas reales día a día y vértices por cada jugador visible
            ordenJugadores.forEach(function (u) {
                if (jugadoresOcultos[u]) return;
                var info = mapaJugadores[u];
                ctx.strokeStyle = info.color;
                ctx.lineWidth = 3;
                ctx.beginPath();

                info.puntosLinea.forEach(function (pt, idx) {
                    var x = calcularPosX(pt.offset);
                    var y = padArr + hUtil - (pt.valor / maxAcumulado) * hUtil;
                    if (idx === 0) {
                        ctx.moveTo(x, y);
                    } else {
                        ctx.lineTo(x, y);
                    }
                });
                ctx.stroke();

                // Puntos en los días en que hubo victoria (y extremo final)
                ctx.fillStyle = info.color;
                info.puntosVictoria.forEach(function (pt) {
                    var x = calcularPosX(pt.offset);
                    var y = padArr + hUtil - (pt.valor / maxAcumulado) * hUtil;
                    ctx.beginPath();
                    ctx.arc(x, y, 4, 0, Math.PI * 2);
                    ctx.fill();
                });
            });
        }

        // Inicializar con el plazo por defecto (Último año: 1y)
        aplicarPlazoRapido(selectRangoRapido ? selectRangoRapido.value : '1y');
        renderizarGraficoVictorias();

        if (selectRangoRapido) {
            selectRangoRapido.addEventListener('change', function () {
                if (selectRangoRapido.value !== 'custom') {
                    aplicarPlazoRapido(selectRangoRapido.value);
                    renderizarGraficoVictorias();
                }
            });
        }

        if (inputFechaDesde) {
            inputFechaDesde.addEventListener('change', function () {
                if (selectRangoRapido) selectRangoRapido.value = 'custom';
                renderizarGraficoVictorias();
            });
        }

        if (inputFechaHasta) {
            inputFechaHasta.addEventListener('change', function () {
                if (selectRangoRapido) selectRangoRapido.value = 'custom';
                renderizarGraficoVictorias();
            });
        }

        window.addEventListener('resize', renderizarGraficoVictorias);
    }

    // =========================================================================
    // 10. Persistencia universal de posición de scroll y estado (Ludoteca, Admin, Votaciones, Juegos, etc.)
    // =========================================================================
    (function () {
        if ('scrollRestoration' in window.history) {
            window.history.scrollRestoration = 'manual';
        }

        // Clave única por archivo y subpágina (ej: "ludoteca.php" vs "ludoteca.php?juego=Catan")
        var archivoActual = window.location.pathname.split('/').pop() || 'index.php';
        var claveVista = 'taberna_estado_' + archivoActual + window.location.search;

        function guardarEstadoVistaActual() {
            try {
                // 1. Posición de scroll de cada contenedor de tabla (.tabla-contenedor)
                var scrollsTablas = [];
                document.querySelectorAll('.tabla-contenedor').forEach(function (cont) {
                    scrollsTablas.push(cont.scrollTop);
                });

                // 2. Votaciones desplegadas (guardamos cuáles NO tienen la clase .contraida)
                var votacionesAbiertas = [];
                document.querySelectorAll('.votacion-bloque').forEach(function (bloque, idx) {
                    var inputId = bloque.querySelector('input[name="id_votacion"]');
                    var claveVot = inputId ? ('id_' + inputId.value) : ('idx_' + idx);
                    if (!bloque.classList.contains('contraida')) {
                        votacionesAbiertas.push(claveVot);
                    }
                });

                // 3. Valores de buscadores de tablas y cuadrículas
                var filtrosTablas = {};
                document.querySelectorAll('[data-table-filter]').forEach(function (inp, idx) {
                    var k = inp.getAttribute('data-table-filter') || ('t_' + idx);
                    filtrosTablas[k] = inp.value;
                });

                var filtrosCards = {};
                document.querySelectorAll('[data-card-filter]').forEach(function (inp, idx) {
                    var k = inp.getAttribute('data-card-filter') || ('c_' + idx);
                    filtrosCards[k] = inp.value;
                });

                var estadoPanelesAvanzados = {};
                Object.keys(controladoresPanelesFiltro).forEach(function (kModo) {
                    estadoPanelesAvanzados[kModo] = controladoresPanelesFiltro[kModo].obtenerEstado();
                });

                var estado = {
                    scrollY: window.scrollY || window.pageYOffset || 0,
                    scrollsTablas: scrollsTablas,
                    votacionesAbiertas: votacionesAbiertas,
                    filtrosTablas: filtrosTablas,
                    filtrosCards: filtrosCards,
                    panelesAvanzados: estadoPanelesAvanzados,
                    ts: Date.now()
                };

                sessionStorage.setItem(claveVista, JSON.stringify(estado));
            } catch (e) {
                // Ignorar si sessionStorage no está disponible
            }
        }

        function restaurarEstadoVistaActual() {
            try {
                var raw = sessionStorage.getItem(claveVista);
                if (!raw) return;

                var estado = JSON.parse(raw);
                if (!estado) return;

                // 1. Restaurar Paneles Avanzados de Filtros y Ordenación (Ludoteca y Juegos Deseados)
                if (estado.panelesAvanzados) {
                    Object.keys(estado.panelesAvanzados).forEach(function (kModo) {
                        if (controladoresPanelesFiltro[kModo]) {
                            controladoresPanelesFiltro[kModo].restaurarEstado(estado.panelesAvanzados[kModo]);
                        }
                    });
                }

                // 2. Restaurar buscadores de tablas ([data-table-filter])
                if (estado.filtrosTablas) {
                    document.querySelectorAll('[data-table-filter]').forEach(function (inp, idx) {
                        var k = inp.getAttribute('data-table-filter') || ('t_' + idx);
                        if (estado.filtrosTablas[k]) {
                            inp.value = estado.filtrosTablas[k];
                            inp.dispatchEvent(new Event('input'));
                        }
                    });
                }

                // 3. Restaurar buscadores de tarjetas ([data-card-filter])
                if (estado.filtrosCards) {
                    document.querySelectorAll('[data-card-filter]').forEach(function (inp, idx) {
                        var k = inp.getAttribute('data-card-filter') || ('c_' + idx);
                        if (estado.filtrosCards[k]) {
                            inp.value = estado.filtrosCards[k];
                            inp.dispatchEvent(new Event('input'));
                        }
                    });
                }

                // 4. Restaurar bloques de votación desplegados en votaciones.php
                if (Array.isArray(estado.votacionesAbiertas) && estado.votacionesAbiertas.length > 0) {
                    document.querySelectorAll('.votacion-bloque').forEach(function (bloque, idx) {
                        var inputId = bloque.querySelector('input[name="id_votacion"]');
                        var claveVot = inputId ? ('id_' + inputId.value) : ('idx_' + idx);
                        if (estado.votacionesAbiertas.indexOf(claveVot) !== -1) {
                            bloque.classList.remove('contraida');
                        }
                    });
                }

                // 5. Restaurar scroll interno de tablas (.tabla-contenedor) y scroll principal de ventana
                window.requestAnimationFrame(function () {
                    if (Array.isArray(estado.scrollsTablas)) {
                        var contenedores = document.querySelectorAll('.tabla-contenedor');
                        contenedores.forEach(function (cont, idx) {
                            if (typeof estado.scrollsTablas[idx] === 'number') {
                                cont.scrollTop = estado.scrollsTablas[idx];
                            }
                        });
                    }
                    if (typeof estado.scrollY === 'number') {
                        window.scrollTo(0, estado.scrollY);
                    }
                });
            } catch (e) {
                // Ignorar errores de lectura de sessionStorage
            }
        }

        // Guardar estado automáticamente al enviar cualquier formulario (Admin, Votaciones, Juegos, Ludoteca, etc.)
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', guardarEstadoVistaActual);
        });

        // Guardar estado antes de abandonar o recargar la página (incluye clic en tarjetas de Ludoteca o botón Atrás)
        window.addEventListener('pagehide', guardarEstadoVistaActual);
        window.addEventListener('beforeunload', guardarEstadoVistaActual);

        // Si el usuario pulsa directamente en la barra de navegación principal (.taberna-nav),
        // limpiamos el scroll de la página de destino para entrar desde arriba de forma limpia
        document.querySelectorAll('.taberna-nav .nav-enlace').forEach(function (enlace) {
            enlace.addEventListener('click', function () {
                try {
                    var href = enlace.getAttribute('href');
                    if (href) {
                        sessionStorage.removeItem('taberna_estado_' + href);
                    }
                } catch (e) {}
            });
        });

        // Ejecutar restauración al cargar el DOM
        restaurarEstadoVistaActual();
    })();
});
