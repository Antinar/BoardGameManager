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

    // 7. Buscador, filtro de propiedad, filtro de estado personal y ordenación en Ludoteca (ludoteca.php)
    var contenedorLudoteca = document.getElementById('contenedor-ludoteca');
    var inputBuscarLudoteca = document.getElementById('buscador-ludoteca');
    var selectPropiedadLudoteca = document.getElementById('filtro-propiedad-ludoteca');
    var selectFiltroLudoteca = document.getElementById('filtro-estado-ludoteca');
    var selectOrdenLudoteca = document.getElementById('orden-ludoteca');
    var msgSinResultadosLudoteca = document.getElementById('ludoteca-sin-resultados');

    if (contenedorLudoteca) {
        var tarjetasLudoteca = Array.prototype.slice.call(contenedorLudoteca.querySelectorAll('.tarjeta-ludoteca'));

        function actualizarVistaLudoteca() {
            var texto = inputBuscarLudoteca ? inputBuscarLudoteca.value.toLowerCase().trim() : '';
            var propiedadFiltro = selectPropiedadLudoteca ? selectPropiedadLudoteca.value : 'todos';
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

                    if (notaB !== notaA) {
                        return notaB - notaA;
                    }
                } else if (criterioOrden === 'mi_nota') {
                    var miNotaA = parseFloat(a.getAttribute('data-mi-nota'));
                    var miNotaB = parseFloat(b.getAttribute('data-mi-nota'));
                    if (isNaN(miNotaA)) miNotaA = -1;
                    if (isNaN(miNotaB)) miNotaB = -1;

                    // De mayor a menor según la nota personal del usuario (-1 sin nota queda al final)
                    if (miNotaB !== miNotaA) {
                        return miNotaB - miNotaA;
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
                var esMio = tarjeta.getAttribute('data-es-mio') === '1';

                var coincideNombre = (texto === '' || nombre.indexOf(texto) !== -1);
                var coincideEstado = (estadoFiltro === 'todos' || miEstado === estadoFiltro);
                var coincidePropiedad = (
                    propiedadFiltro === 'todos' ||
                    (propiedadFiltro === 'mios' && esMio) ||
                    (propiedadFiltro === 'no_mios' && !esMio)
                );

                if (coincideNombre && coincideEstado && coincidePropiedad) {
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
        if (selectPropiedadLudoteca) {
            selectPropiedadLudoteca.addEventListener('change', actualizarVistaLudoteca);
        }
        if (selectFiltroLudoteca) {
            selectFiltroLudoteca.addEventListener('change', actualizarVistaLudoteca);
        }
        if (selectOrdenLudoteca) {
            selectOrdenLudoteca.addEventListener('change', actualizarVistaLudoteca);
        }
    }

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

                var estado = {
                    scrollY: window.scrollY || window.pageYOffset || 0,
                    scrollsTablas: scrollsTablas,
                    votacionesAbiertas: votacionesAbiertas,
                    filtrosTablas: filtrosTablas,
                    filtrosCards: filtrosCards,
                    ludoteca: {
                        buscar: inputBuscarLudoteca ? inputBuscarLudoteca.value : '',
                        propiedad: selectPropiedadLudoteca ? selectPropiedadLudoteca.value : 'todos',
                        estado: selectFiltroLudoteca ? selectFiltroLudoteca.value : 'todos',
                        orden: selectOrdenLudoteca ? selectOrdenLudoteca.value : 'alfabetico'
                    },
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

                // 1. Restaurar filtros y ordenación de la Ludoteca antes de medir el scroll
                if (contenedorLudoteca && estado.ludoteca) {
                    var huboCambioLudoteca = false;
                    if (inputBuscarLudoteca && typeof estado.ludoteca.buscar === 'string' && estado.ludoteca.buscar !== '') {
                        inputBuscarLudoteca.value = estado.ludoteca.buscar;
                        huboCambioLudoteca = true;
                    }
                    if (selectPropiedadLudoteca && estado.ludoteca.propiedad && estado.ludoteca.propiedad !== 'todos') {
                        selectPropiedadLudoteca.value = estado.ludoteca.propiedad;
                        huboCambioLudoteca = true;
                    }
                    if (selectFiltroLudoteca && estado.ludoteca.estado && estado.ludoteca.estado !== 'todos') {
                        selectFiltroLudoteca.value = estado.ludoteca.estado;
                        huboCambioLudoteca = true;
                    }
                    if (selectOrdenLudoteca && estado.ludoteca.orden && estado.ludoteca.orden !== 'alfabetico') {
                        selectOrdenLudoteca.value = estado.ludoteca.orden;
                        huboCambioLudoteca = true;
                    }
                    if (huboCambioLudoteca && typeof actualizarVistaLudoteca === 'function') {
                        actualizarVistaLudoteca();
                    }
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
