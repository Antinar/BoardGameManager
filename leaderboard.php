<?php
require_once 'conexion.php';
requerirLogin($pdo);

$mensajeExito = '';
$mensajeError = '';
$usuarioActual = $_SESSION['username'];
$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';

// Obtener todas las personas de la base de datos para ganador, participantes y formularios
$stmtPersonas = $pdo->query("SELECT username, NombrePersona FROM Persona ORDER BY NombrePersona ASC");
$todasLasPersonas = $stmtPersonas->fetchAll();

// Mapa username => NombrePersona para mostrar participantes
$nombresPorUsuario = [];
$usuariosValidos = [];
foreach ($todasLasPersonas as $per) {
    $nombresPorUsuario[$per['username']] = $per['NombrePersona'];
    $usuariosValidos[$per['username']] = true;
}

// Normaliza la lista de participantes recibida por POST:
// filtra contra usuarios reales, elimina duplicados y añade siempre al ganador.
function normalizarParticipantes($entrada, $usuariosValidos, $ganador)
{
    $resultado = [];
    if (is_array($entrada)) {
        foreach ($entrada as $usuario) {
            if (!is_string($usuario)) {
                continue;
            }
            $usuario = trim($usuario);
            if ($usuario !== '' && isset($usuariosValidos[$usuario]) && !in_array($usuario, $resultado, true)) {
                $resultado[] = $usuario;
            }
        }
    }
    if ($ganador !== '' && !in_array($ganador, $resultado, true)) {
        $resultado[] = $ganador;
    }
    return $resultado;
}

// Normaliza la duración opcional (entero entre 1 y 1440) y devuelve null si está vacía.
function normalizarDuracion($valor, &$error)
{
    if (!isset($valor) || trim((string)$valor) === '') {
        return null;
    }
    $texto = trim((string)$valor);
    $duracion = filter_var($texto, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 1440]
    ]);
    if ($duracion === false) {
        $error = 'La duración debe ser un número entero de minutos entre 1 y 1440.';
        return null;
    }
    return (int)$duracion;
}

// Normaliza la puntuación opcional (texto libre, máximo 100 caracteres).
function normalizarPuntuacion($valor, &$error)
{
    if (!isset($valor)) {
        return null;
    }
    $texto = trim((string)$valor);
    if ($texto === '') {
        return null;
    }
    if (mb_strlen($texto, 'UTF-8') > 100) {
        $error = 'La puntuación no puede superar los 100 caracteres.';
        return null;
    }
    return $texto;
}

/**
 * Sustituye los participantes de una victoria (usa la conexión recibida, ya dentro de transacción).
 */
function reemplazarParticipantes(PDO $pdo, $idVictoria, array $participantes)
{
    $stmtDel = $pdo->prepare("DELETE FROM Victoria_Participante WHERE id_victoria = ?");
    $stmtDel->execute([$idVictoria]);

    $stmtIns = $pdo->prepare("INSERT INTO Victoria_Participante (id_victoria, username) VALUES (?, ?)");
    foreach ($participantes as $usuario) {
        $stmtIns->execute([$idVictoria, $usuario]);
    }
}

/**
 * Normaliza la fecha opcional de una partida de Root.
 * Acepta el formato de datetime-local (Y-m-d\TH:i) o el de la base de datos (Y-m-d H:i:s).
 * Devuelve la fecha ya formateada como Y-m-d H:i:s, o NULL y rellena $error si no es válida.
 */
function normalizarFechaRoot($valor, &$error)
{
    if (!isset($valor) || trim((string)$valor) === '') {
        return date('Y-m-d H:i:s');
    }
    $texto = trim((string)$valor);
    $formatos = ['Y-m-d\TH:i', 'Y-m-d H:i:s'];
    foreach ($formatos as $formato) {
        $fecha = DateTime::createFromFormat($formato, $texto);
        if ($fecha !== false && $fecha->format($formato) === $texto) {
            return $fecha->format('Y-m-d H:i:s');
        }
    }
    $error = 'La fecha de la partida no es válida.';
    return null;
}

// ---------------------------------------------------------------------------
// Módulo Root: carga tolerante a fallos de catálogos (facciones, mapas, mazos)
// ---------------------------------------------------------------------------
$rootDisponible = true;
$rootError = '';
$rootFacciones = [];
$rootMapas = [];
$rootMazos = [];
$rootFaccionesActivas = [];
$rootMapasActivos = [];
$rootMazosActivos = [];
$rootRankingJugadores = [];
$rootRankingFacciones = [];
$rootPartidas = [];
$rootParticipantesPorPartida = [];
$rootTotalPartidas = 0;
$rootFilasForm = [];

try {
    $stmtRootFacciones = $pdo->query("SELECT id_faccion, nombre FROM Root_Faccion WHERE activa = 1 ORDER BY nombre ASC");
    $rootFacciones = $stmtRootFacciones->fetchAll();
    foreach ($rootFacciones as $filaFaccion) {
        $rootFaccionesActivas[(int)$filaFaccion['id_faccion']] = $filaFaccion['nombre'];
    }

    $stmtRootMapas = $pdo->query("SELECT id_mapa, nombre FROM Root_Mapa WHERE activo = 1 ORDER BY nombre ASC");
    $rootMapas = $stmtRootMapas->fetchAll();
    foreach ($rootMapas as $filaMapa) {
        $rootMapasActivos[(int)$filaMapa['id_mapa']] = $filaMapa['nombre'];
    }

    $stmtRootMazos = $pdo->query("SELECT id_mazo, nombre FROM Root_Mazo WHERE activo = 1 ORDER BY nombre ASC");
    $rootMazos = $stmtRootMazos->fetchAll();
    foreach ($rootMazos as $filaMazo) {
        $rootMazosActivos[(int)$filaMazo['id_mazo']] = $filaMazo['nombre'];
    }
} catch (PDOException $e) {
    $rootDisponible = false;
    $rootError = 'El módulo de Root no está disponible. Ejecuta sql/migration_root.sql antes de usarlo.';
    error_log('Error al cargar catálogos de Root: ' . $e->getMessage());
    $rootFacciones = [];
    $rootMapas = [];
    $rootMazos = [];
    $rootFaccionesActivas = [];
    $rootMapasActivos = [];
    $rootMazosActivos = [];
}

$rootFormularioDisponible = $rootDisponible
    && count($rootFacciones) > 0
    && count($rootMapas) > 0
    && count($rootMazos) > 0;

// ---------------------------------------------------------------------------
// Módulo Juegos Asimétricos: carga tolerante a fallos de catálogos
// ---------------------------------------------------------------------------
$asimetricosDisponible = true;
$asimetricosError = '';
$juegosAsimetricosLista = [];
$juegosAsimetricos = [];
$personajesAsimetricosPorJuego = [];
$personajesAsimetricosPorId = [];
$codigoAsimetricoSeleccionado = '';
$asimetricoJuegoActual = null;
$asimetricoNumPersonajesPorJugador = 1;
$asimetricoDuracionObligatoria = false;
$asimetricoPersonajes = [];
$asimetricoPersonajesPorId = [];
$asimetricoRankingJugadores = [];
$asimetricoRankingPersonajes = [];
$asimetricoPartidas = [];
$asimetricoParticipantesPorPartida = [];
$asimetricoTotalPartidas = 0;
$asimetricoTotalJugadores = 0;
$asimetricoTotalPersonajes = 0;
$asimetricoFilasForm = [];

try {
    $stmtAsimetricosJuegos = $pdo->query("SELECT codigo_juego, nombre_visible, num_personajes_por_jugador FROM Juego_Asimetrico ORDER BY nombre_visible ASC");
    $juegosAsimetricosLista = $stmtAsimetricosJuegos->fetchAll();

    // Orden del enunciado para el selector; los códigos futuros van al final por nombre
    $prioridadJuegosAsimetricos = [
        'villainous_disney' => 0,
        'villainous_marvel' => 1,
        'villainous_star_wars' => 2,
        'tag_team' => 3,
    ];
    usort($juegosAsimetricosLista, function ($a, $b) use ($prioridadJuegosAsimetricos) {
        $prioridadA = $prioridadJuegosAsimetricos[$a['codigo_juego']] ?? PHP_INT_MAX;
        $prioridadB = $prioridadJuegosAsimetricos[$b['codigo_juego']] ?? PHP_INT_MAX;
        if ($prioridadA !== $prioridadB) {
            return $prioridadA <=> $prioridadB;
        }
        return strcmp((string)$a['nombre_visible'], (string)$b['nombre_visible']);
    });

    foreach ($juegosAsimetricosLista as $filaJuegoAsimetrico) {
        $juegosAsimetricos[$filaJuegoAsimetrico['codigo_juego']] = $filaJuegoAsimetrico;
    }

    $stmtAsimetricosPersonajes = $pdo->query("SELECT id_personaje, codigo_juego, nombre_personaje FROM Personaje_Asimetrico WHERE activo = 1 ORDER BY nombre_personaje ASC");
    foreach ($stmtAsimetricosPersonajes->fetchAll() as $filaPersonajeAsimetrico) {
        $personajesAsimetricosPorJuego[$filaPersonajeAsimetrico['codigo_juego']][] = $filaPersonajeAsimetrico;
        $personajesAsimetricosPorId[$filaPersonajeAsimetrico['codigo_juego']][(int)$filaPersonajeAsimetrico['id_personaje']] = $filaPersonajeAsimetrico['nombre_personaje'];
    }
} catch (PDOException $e) {
    $asimetricosDisponible = false;
    $asimetricosError = 'El módulo de Juegos Asimétricos no está disponible. Ejecuta sql/migration_asimetricos.sql antes de usarlo.';
    error_log('Error al cargar catálogos de Juegos Asimétricos: ' . $e->getMessage());
    $juegosAsimetricosLista = [];
    $juegosAsimetricos = [];
    $personajesAsimetricosPorJuego = [];
    $personajesAsimetricosPorId = [];
}

$codigoAsimetricoPost = isset($_POST['codigo_juego']) ? trim((string)$_POST['codigo_juego']) : '';
$codigoAsimetricoGet = isset($_GET['asimetrico']) ? trim((string)$_GET['asimetrico']) : '';
if ($codigoAsimetricoPost !== '' && isset($juegosAsimetricos[$codigoAsimetricoPost])) {
    $codigoAsimetricoSeleccionado = $codigoAsimetricoPost;
} elseif ($codigoAsimetricoGet !== '' && isset($juegosAsimetricos[$codigoAsimetricoGet])) {
    $codigoAsimetricoSeleccionado = $codigoAsimetricoGet;
} elseif (count($juegosAsimetricosLista) > 0) {
    $codigoAsimetricoSeleccionado = $juegosAsimetricosLista[0]['codigo_juego'];
}
$asimetricoJuegoActual = $codigoAsimetricoSeleccionado !== '' ? $juegosAsimetricos[$codigoAsimetricoSeleccionado] : null;
$asimetricoNumPersonajesPorJugador = $asimetricoJuegoActual ? max(1, (int)$asimetricoJuegoActual['num_personajes_por_jugador']) : 1;
$asimetricoDuracionObligatoria = false;
$asimetricoPersonajes = $personajesAsimetricosPorJuego[$codigoAsimetricoSeleccionado] ?? [];
$asimetricoPersonajesPorId = $personajesAsimetricosPorId[$codigoAsimetricoSeleccionado] ?? [];
$asimetricoFormularioDisponible = $asimetricosDisponible
    && $asimetricoJuegoActual !== null
    && count($asimetricoPersonajes) >= (2 * $asimetricoNumPersonajesPorJugador);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    // 1. Registrar partida (juego que YA tenemos)
    if ($accion === 'registrar_victoria') {
        $juegoSeleccionado = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';
        $personaGanadora = isset($_POST['username']) ? trim($_POST['username']) : '';
        $errorCampo = '';
        $duracionMinutos = normalizarDuracion(isset($_POST['duracion_minutos']) ? $_POST['duracion_minutos'] : '', $errorCampo);
        $errorDuracion = $errorCampo;
        $errorCampo = '';
        $puntuacion = normalizarPuntuacion(isset($_POST['puntuacion']) ? $_POST['puntuacion'] : '', $errorCampo);
        $errorPuntuacion = $errorCampo;
        $participantes = normalizarParticipantes(
            isset($_POST['participantes']) ? $_POST['participantes'] : [],
            $usuariosValidos,
            $personaGanadora
        );

        if ($juegoSeleccionado === '' || $personaGanadora === '') {
            $mensajeError = 'Debes seleccionar tanto el juego disputado como la persona ganadora.';
        } elseif ($errorDuracion !== '') {
            $mensajeError = $errorDuracion;
        } elseif ($errorPuntuacion !== '') {
            $mensajeError = $errorPuntuacion;
        } else {
            // Validar que el juego exista y tenga lo_tenemos = 1
            $stmtValJuego = $pdo->prepare("SELECT lo_tenemos FROM Juego WHERE NombreJuego = ?");
            $stmtValJuego->execute([$juegoSeleccionado]);
            $juegoRow = $stmtValJuego->fetch();

            $stmtValPersona = $pdo->prepare("SELECT username, NombrePersona FROM Persona WHERE username = ?");
            $stmtValPersona->execute([$personaGanadora]);
            $personaRow = $stmtValPersona->fetch();

            if (!$juegoRow || (int)$juegoRow['lo_tenemos'] !== 1) {
                $mensajeError = 'Solo se pueden registrar victorias de juegos que ya tenemos en la taberna.';
            } elseif (!$personaRow) {
                $mensajeError = 'La persona seleccionada no está registrada en la base de datos.';
            } else {
                // Canonicalizar el username tal y como está guardado en la BD
                $personaGanadora = (string)$personaRow['username'];
                $participantes = normalizarParticipantes(
                    isset($_POST['participantes']) ? $_POST['participantes'] : [],
                    $usuariosValidos,
                    $personaGanadora
                );

                try {
                    $pdo->beginTransaction();

                    $stmtIns = $pdo->prepare("
                        INSERT INTO Victoria (username, NombreJuego, fecha_victoria, duracion_minutos, puntuacion)
                        VALUES (?, ?, NOW(), ?, ?)
                    ");
                    $stmtIns->execute([$personaGanadora, $juegoSeleccionado, $duracionMinutos, $puntuacion]);
                    $idVictoria = (int)$pdo->lastInsertId();

                    reemplazarParticipantes($pdo, $idVictoria, $participantes);

                    $pdo->commit();

                    $mensajeExito = "Partida registrada para «{$personaRow['NombrePersona']}» en «{$juegoSeleccionado}».";
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Error al registrar partida: ' . $e->getMessage());
                    $mensajeError = 'No se pudo registrar la partida. Inténtalo de nuevo más tarde.';
                }
            }
        }
    }

    // 2. Edición rápida de una partida (solo administradores)
    if ($accion === 'editar_victoria') {
        if (!$esAdmin) {
            $mensajeError = 'Solo el Tabernero Mayor puede editar partidas.';
        } else {
            $idVictoriaEdit = isset($_POST['id_victoria']) ? (int)$_POST['id_victoria'] : 0;
            $juegoSeleccionado = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';
            $personaGanadora = isset($_POST['username']) ? trim($_POST['username']) : '';
            $fechaTexto = isset($_POST['fecha_victoria']) ? trim($_POST['fecha_victoria']) : '';
            $errorCampo = '';
            $duracionMinutos = normalizarDuracion(isset($_POST['duracion_minutos']) ? $_POST['duracion_minutos'] : '', $errorCampo);
            $errorDuracion = $errorCampo;
            $errorCampo = '';
            $puntuacion = normalizarPuntuacion(isset($_POST['puntuacion']) ? $_POST['puntuacion'] : '', $errorCampo);
            $errorPuntuacion = $errorCampo;
            $participantes = normalizarParticipantes(
                isset($_POST['participantes']) ? $_POST['participantes'] : [],
                $usuariosValidos,
                $personaGanadora
            );

            $fechaObjeto = DateTime::createFromFormat('Y-m-d H:i:s', $fechaTexto);
            $fechaValida = ($fechaObjeto !== false && $fechaObjeto->format('Y-m-d H:i:s') === $fechaTexto);

            if ($idVictoriaEdit <= 0) {
                $mensajeError = 'La partida indicada no es válida.';
            } elseif ($juegoSeleccionado === '' || $personaGanadora === '') {
                $mensajeError = 'Debes indicar el juego y la persona ganadora.';
            } elseif (!$fechaValida) {
                $mensajeError = 'La fecha debe tener el formato AAAA-MM-DD HH:MM:SS.';
            } elseif ($errorDuracion !== '') {
                $mensajeError = $errorDuracion;
            } elseif ($errorPuntuacion !== '') {
                $mensajeError = $errorPuntuacion;
            } else {
                $stmtValJuego = $pdo->prepare("SELECT lo_tenemos FROM Juego WHERE NombreJuego = ?");
                $stmtValJuego->execute([$juegoSeleccionado]);
                $juegoRow = $stmtValJuego->fetch();

                $stmtValPersona = $pdo->prepare("SELECT username, NombrePersona FROM Persona WHERE username = ?");
                $stmtValPersona->execute([$personaGanadora]);
                $personaRow = $stmtValPersona->fetch();

                $stmtValVictoria = $pdo->prepare("SELECT id_victoria FROM Victoria WHERE id_victoria = ?");
                $stmtValVictoria->execute([$idVictoriaEdit]);
                $victoriaExiste = $stmtValVictoria->fetch();

                $stmtJuegoOriginal = $pdo->prepare("SELECT NombreJuego FROM Victoria WHERE id_victoria = ?");
                $stmtJuegoOriginal->execute([$idVictoriaEdit]);
                $juegoOriginal = (string)$stmtJuegoOriginal->fetchColumn();

                if (!$victoriaExiste) {
                    $mensajeError = 'La partida indicada ya no existe.';
                } elseif (!$juegoRow) {
                    $mensajeError = 'El juego seleccionado no está registrado en la base de datos.';
                } elseif (!$personaRow) {
                    $mensajeError = 'La persona seleccionada no está registrada en la base de datos.';
                } elseif ($juegoSeleccionado !== $juegoOriginal && (int)$juegoRow['lo_tenemos'] !== 1) {
                    $mensajeError = 'Solo puedes mover una partida a un juego que ya tenemos en la taberna.';
                } else {
                    // Canonicalizar el username tal y como está guardado en la BD
                    $personaGanadora = (string)$personaRow['username'];
                    $participantes = normalizarParticipantes(
                        isset($_POST['participantes']) ? $_POST['participantes'] : [],
                        $usuariosValidos,
                        $personaGanadora
                    );

                    try {
                        $pdo->beginTransaction();

                        $stmtUpd = $pdo->prepare("
                            UPDATE Victoria
                            SET username = ?, NombreJuego = ?, fecha_victoria = ?, duracion_minutos = ?, puntuacion = ?
                            WHERE id_victoria = ?
                        ");
                        $stmtUpd->execute([
                            $personaGanadora,
                            $juegoSeleccionado,
                            $fechaObjeto->format('Y-m-d H:i:s'),
                            $duracionMinutos,
                            $puntuacion,
                            $idVictoriaEdit
                        ]);

                        reemplazarParticipantes($pdo, $idVictoriaEdit, $participantes);

                        $pdo->commit();

                        $mensajeExito = "Partida #{$idVictoriaEdit} actualizada para «{$personaRow['NombrePersona']}» en «{$juegoSeleccionado}».";
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log('Error al editar partida: ' . $e->getMessage());
                        $mensajeError = 'No se pudo actualizar la partida. Inténtalo de nuevo más tarde.';
                    }
                }
            }
        }
    }

    // 3. Registrar partida de Root (módulo aislado: no escribe en Victoria)
    if ($accion === 'registrar_partida_root') {
        // Repoblar filas del formulario con lo recibido, para no perder datos al fallar
        $rootFilasForm = [];
        if (isset($_POST['participantes']) && is_array($_POST['participantes'])) {
            foreach ($_POST['participantes'] as $filaRecibida) {
                if (!is_array($filaRecibida)) {
                    continue;
                }
                $rootFilasForm[] = [
                    'username' => isset($filaRecibida['username']) ? trim((string)$filaRecibida['username']) : '',
                    'id_faccion' => isset($filaRecibida['id_faccion']) ? trim((string)$filaRecibida['id_faccion']) : '',
                    'puntos' => isset($filaRecibida['puntos']) ? trim((string)$filaRecibida['puntos']) : '',
                    'ha_ganado' => (!empty($filaRecibida['ha_ganado'])) ? '1' : '',
                ];
            }
        }
        while (count($rootFilasForm) < 2) {
            $rootFilasForm[] = ['username' => '', 'id_faccion' => '', 'puntos' => '', 'ha_ganado' => ''];
        }

        if (!$rootDisponible) {
            $mensajeError = $rootError;
        } elseif (!$rootFormularioDisponible) {
            $mensajeError = 'No hay facciones, mapas y mazos activos suficientes para registrar una partida de Root.';
        } else {
            $rootErrores = [];

            // Mapa y mazo
            $rootIdMapa = isset($_POST['id_mapa']) && $_POST['id_mapa'] !== ''
                ? filter_var(trim((string)$_POST['id_mapa']), FILTER_VALIDATE_INT)
                : false;
            if ($rootIdMapa === false || !isset($rootMapasActivos[(int)$rootIdMapa])) {
                $rootErrores[] = 'Debes seleccionar un mapa activo.';
            }

            $rootIdMazo = isset($_POST['id_mazo']) && $_POST['id_mazo'] !== ''
                ? filter_var(trim((string)$_POST['id_mazo']), FILTER_VALIDATE_INT)
                : false;
            if ($rootIdMazo === false || !isset($rootMazosActivos[(int)$rootIdMazo])) {
                $rootErrores[] = 'Debes seleccionar un mazo activo.';
            }

            // Condición de victoria
            $rootCondicion = isset($_POST['condicion_victoria']) ? trim((string)$_POST['condicion_victoria']) : '';
            if ($rootCondicion === '') {
                $rootCondicion = '30_puntos';
            }
            if ($rootCondicion !== '30_puntos' && $rootCondicion !== 'dominancia') {
                $rootErrores[] = 'La condición de victoria no es válida.';
            }

            // Secuaces
            $rootConSecuaces = (isset($_POST['con_secuaces']) && (string)$_POST['con_secuaces'] === '1') ? 1 : 0;

            // Fecha
            $rootErrorFecha = '';
            $rootFecha = normalizarFechaRoot(isset($_POST['fecha_partida']) ? $_POST['fecha_partida'] : '', $rootErrorFecha);
            if ($rootErrorFecha !== '') {
                $rootErrores[] = $rootErrorFecha;
            }

            // Duración (reutiliza normalizarDuracion)
            $rootErrorDuracion = '';
            $rootDuracion = normalizarDuracion(isset($_POST['duracion_minutos']) ? $_POST['duracion_minutos'] : '', $rootErrorDuracion);
            if ($rootErrorDuracion !== '') {
                $rootErrores[] = $rootErrorDuracion;
            }

            // Notas
            $rootNotas = isset($_POST['notas']) ? trim((string)$_POST['notas']) : '';
            if ($rootNotas === '') {
                $rootNotas = null;
            } elseif (mb_strlen($rootNotas, 'UTF-8') > 255) {
                $rootErrores[] = 'Las notas no pueden superar los 255 caracteres.';
            }

            // Participantes
            $rootParticipantesValidos = [];
            $rootUsernamesVistos = [];
            $rootFaccionesVistas = [];
            $rootGanadores = 0;
            if (isset($_POST['participantes']) && is_array($_POST['participantes'])) {
                foreach ($_POST['participantes'] as $filaParticipante) {
                    if (!is_array($filaParticipante)) {
                        continue;
                    }
                    $filaUsername = isset($filaParticipante['username']) ? trim((string)$filaParticipante['username']) : '';
                    $filaFaccion = isset($filaParticipante['id_faccion']) ? trim((string)$filaParticipante['id_faccion']) : '';
                    $filaPuntos = isset($filaParticipante['puntos']) ? trim((string)$filaParticipante['puntos']) : '';
                    $filaGanado = (!empty($filaParticipante['ha_ganado'])) ? 1 : 0;

                    // Ignorar filas totalmente vacías
                    if ($filaUsername === '' && $filaFaccion === '' && $filaPuntos === '' && $filaGanado === 0) {
                        continue;
                    }
                    if ($filaUsername === '' && $filaFaccion === '') {
                        continue;
                    }

                    if ($filaUsername === '' || !isset($usuariosValidos[$filaUsername])) {
                        $rootErrores[] = 'Hay un participante sin usuario válido.';
                        continue;
                    }
                    if (isset($rootUsernamesVistos[$filaUsername])) {
                        $rootErrores[] = 'No se puede repetir el mismo usuario en la partida.';
                        continue;
                    }
                    $rootUsernamesVistos[$filaUsername] = true;

                    $filaIdFaccion = $filaFaccion !== '' ? filter_var($filaFaccion, FILTER_VALIDATE_INT) : false;
                    if ($filaIdFaccion === false || !isset($rootFaccionesActivas[(int)$filaIdFaccion])) {
                        $rootErrores[] = 'Hay un participante sin facción válida.';
                        continue;
                    }
                    $filaIdFaccion = (int)$filaIdFaccion;
                    if (isset($rootFaccionesVistas[$filaIdFaccion])) {
                        $rootErrores[] = 'No se puede repetir la misma facción en la partida.';
                        continue;
                    }
                    $rootFaccionesVistas[$filaIdFaccion] = true;

                    $filaPuntosNormalizados = null;
                    if ($filaPuntos !== '') {
                        $filaPuntosNormalizados = filter_var($filaPuntos, FILTER_VALIDATE_INT, [
                            'options' => ['min_range' => 0, 'max_range' => 30]
                        ]);
                        if ($filaPuntosNormalizados === false) {
                            $rootErrores[] = 'Los puntos deben ser un número entero entre 0 y 30.';
                            continue;
                        }
                        $filaPuntosNormalizados = (int)$filaPuntosNormalizados;
                    }

                    if ($filaGanado === 1) {
                        $rootGanadores++;
                    }

                    $rootParticipantesValidos[] = [
                        'username' => $filaUsername,
                        'id_faccion' => $filaIdFaccion,
                        'ha_ganado' => $filaGanado,
                        'puntos' => $filaPuntosNormalizados,
                    ];
                }
            }

            if (count($rootParticipantesValidos) < 2) {
                $rootErrores[] = 'Una partida de Root necesita al menos 2 participantes.';
            }
            if ($rootGanadores < 1) {
                $rootErrores[] = 'Debes marcar al menos un ganador.';
            }

            $rootErrores = array_values(array_unique($rootErrores));

            if (count($rootErrores) > 0) {
                $mensajeError = implode(' ', $rootErrores);
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmtInsPartidaRoot = $pdo->prepare("
                        INSERT INTO Partida_Root (fecha_partida, id_mapa, id_mazo, condicion_victoria, con_secuaces, duracion_minutos, notas)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtInsPartidaRoot->execute([
                        $rootFecha,
                        (int)$rootIdMapa,
                        (int)$rootIdMazo,
                        $rootCondicion,
                        $rootConSecuaces,
                        $rootDuracion,
                        $rootNotas
                    ]);
                    $idPartidaRoot = (int)$pdo->lastInsertId();

                    $stmtInsParticipanteRoot = $pdo->prepare("
                        INSERT INTO Participante_Root (id_partida_root, username, id_faccion, ha_ganado, puntos)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    foreach ($rootParticipantesValidos as $participanteRoot) {
                        $stmtInsParticipanteRoot->execute([
                            $idPartidaRoot,
                            $participanteRoot['username'],
                            $participanteRoot['id_faccion'],
                            $participanteRoot['ha_ganado'],
                            $participanteRoot['puntos']
                        ]);
                    }

                    $pdo->commit();

                    $mensajeExito = "Partida de Root registrada correctamente (mapa: «{$rootMapasActivos[(int)$rootIdMapa]}», fecha: {$rootFecha}).";
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Error al registrar partida de Root: ' . $e->getMessage());
                    $mensajeError = 'No se pudo registrar la partida de Root. Inténtalo de nuevo.';
                }
            }
        }
    }

    // 4. Registrar partida de Juegos Asimétricos (no escribe en Victoria)
    if ($accion === 'registrar_partida_asimetrica') {
        // Repoblar filas del formulario con lo recibido, para no perder datos al fallar
        $asimetricoFilasForm = [];
        if (isset($_POST['participantes']) && is_array($_POST['participantes'])) {
            foreach ($_POST['participantes'] as $filaRecibida) {
                if (!is_array($filaRecibida)) {
                    continue;
                }
                $personajesRecibidos = [];
                if (isset($filaRecibida['personajes']) && is_array($filaRecibida['personajes'])) {
                    foreach ($filaRecibida['personajes'] as $personajeRecibido) {
                        if (!is_array($personajeRecibido)) {
                            $personajesRecibidos[] = trim((string)$personajeRecibido);
                        }
                    }
                }
                $asimetricoFilasForm[] = [
                    'username' => isset($filaRecibida['username']) ? trim((string)$filaRecibida['username']) : '',
                    'personajes' => $personajesRecibidos,
                    'ha_ganado' => (!empty($filaRecibida['ha_ganado'])) ? '1' : '',
                ];
            }
        }
        foreach ($asimetricoFilasForm as $indiceFilaForm => $filaForm) {
            while (count($asimetricoFilasForm[$indiceFilaForm]['personajes']) < $asimetricoNumPersonajesPorJugador) {
                $asimetricoFilasForm[$indiceFilaForm]['personajes'][] = '';
            }
        }
        while (count($asimetricoFilasForm) < 2) {
            $asimetricoFilasForm[] = [
                'username' => '',
                'personajes' => array_fill(0, $asimetricoNumPersonajesPorJugador, ''),
                'ha_ganado' => '',
            ];
        }

        if (!$asimetricosDisponible) {
            $mensajeError = $asimetricosError;
        } elseif (!$asimetricoFormularioDisponible) {
            $mensajeError = 'No hay personajes activos suficientes para este juego.';
        } else {
            $asimetricoErrores = [];

            $codigoJuegoPost = trim((string)($_POST['codigo_juego'] ?? ''));
            if ($codigoJuegoPost === '' || !isset($juegosAsimetricos[$codigoJuegoPost])) {
                $asimetricoErrores[] = 'Debes seleccionar un juego asimétrico válido.';
                $codigoJuegoPost = '';
            }

            $numPersonajes = $codigoJuegoPost !== ''
                ? (int)$juegosAsimetricos[$codigoJuegoPost]['num_personajes_por_jugador']
                : 0;
            $personajesValidosJuego = $personajesAsimetricosPorId[$codigoJuegoPost] ?? [];

            // Fecha
            $asimetricoErrorFecha = '';
            $asimetricoFecha = normalizarFechaRoot($_POST['fecha_partida'] ?? '', $asimetricoErrorFecha);
            if ($asimetricoErrorFecha !== '') {
                $asimetricoErrores[] = $asimetricoErrorFecha;
            }

            // Duración
            $asimetricoErrorDuracion = '';
            $asimetricoDuracion = normalizarDuracion($_POST['duracion_minutos'] ?? '', $asimetricoErrorDuracion);
            if ($asimetricoErrorDuracion !== '') {
                $asimetricoErrores[] = $asimetricoErrorDuracion;
            }

            // Notas
            $asimetricoNotas = isset($_POST['notas']) ? trim((string)$_POST['notas']) : '';
            if ($asimetricoNotas === '') {
                $asimetricoNotas = null;
            } elseif (mb_strlen($asimetricoNotas, 'UTF-8') > 255) {
                $asimetricoErrores[] = 'Las notas no pueden superar los 255 caracteres.';
            }

            // Participantes
            $asimetricoParticipantesValidos = [];
            $asimetricoUsernamesVistos = [];
            $asimetricoPersonajesVistos = [];
            $asimetricoGanadores = 0;
            if (isset($_POST['participantes']) && is_array($_POST['participantes'])) {
                foreach ($_POST['participantes'] as $filaParticipante) {
                    if (!is_array($filaParticipante)) {
                        continue;
                    }
                    $filaUsername = isset($filaParticipante['username']) ? trim((string)$filaParticipante['username']) : '';
                    $filaGanado = !empty($filaParticipante['ha_ganado']) ? 1 : 0;
                    $filaPersonajesBrutos = [];
                    if (isset($filaParticipante['personajes']) && is_array($filaParticipante['personajes'])) {
                        foreach ($filaParticipante['personajes'] as $personajeBruto) {
                            if (is_array($personajeBruto)) {
                                continue;
                            }
                            $filaPersonajesBrutos[] = trim((string)$personajeBruto);
                        }
                    }
                    $filaPersonajesNoVacios = array_values(array_filter($filaPersonajesBrutos, function ($valor) {
                        return $valor !== '';
                    }));

                    // Ignorar filas totalmente vacías
                    if ($filaUsername === '' && count($filaPersonajesNoVacios) === 0 && $filaGanado === 0) {
                        continue;
                    }

                    if ($filaUsername === '' || !isset($usuariosValidos[$filaUsername])) {
                        $asimetricoErrores[] = 'Hay un participante sin usuario válido.';
                        continue;
                    }
                    if (isset($asimetricoUsernamesVistos[$filaUsername])) {
                        $asimetricoErrores[] = 'No se puede repetir el mismo usuario en la partida.';
                        continue;
                    }
                    $asimetricoUsernamesVistos[$filaUsername] = true;

                    if (count($filaPersonajesNoVacios) !== $numPersonajes) {
                        $asimetricoErrores[] = $numPersonajes === 1
                            ? 'Cada participante debe elegir exactamente 1 personaje.'
                            : "Cada participante debe elegir exactamente {$numPersonajes} personajes.";
                        continue;
                    }

                    $filaPersonajesIds = [];
                    $filaPersonajesDuplicados = false;
                    $filaPersonajeInvalido = false;
                    foreach ($filaPersonajesNoVacios as $personajeTexto) {
                        $idPersonaje = filter_var($personajeTexto, FILTER_VALIDATE_INT);
                        if ($idPersonaje === false || !isset($personajesValidosJuego[(int)$idPersonaje])) {
                            $filaPersonajeInvalido = true;
                            break;
                        }
                        $idPersonaje = (int)$idPersonaje;
                        if (in_array($idPersonaje, $filaPersonajesIds, true)) {
                            $filaPersonajesDuplicados = true;
                            break;
                        }
                        $filaPersonajesIds[] = $idPersonaje;
                    }
                    if ($filaPersonajeInvalido) {
                        $asimetricoErrores[] = 'Hay un participante con un personaje no válido.';
                        continue;
                    }
                    if ($filaPersonajesDuplicados) {
                        $asimetricoErrores[] = 'Un participante no puede repetir el mismo personaje.';
                        continue;
                    }
                    $personajesRepetidosEnPartida = false;
                    foreach ($filaPersonajesIds as $idPersonaje) {
                        if (isset($asimetricoPersonajesVistos[$idPersonaje])) {
                            $personajesRepetidosEnPartida = true;
                            break;
                        }
                    }
                    if ($personajesRepetidosEnPartida) {
                        $asimetricoErrores[] = 'No se puede usar el mismo personaje dos veces en la misma partida.';
                        continue;
                    }
                    foreach ($filaPersonajesIds as $idPersonaje) {
                        $asimetricoPersonajesVistos[$idPersonaje] = true;
                    }

                    if ($filaGanado === 1) {
                        $asimetricoGanadores++;
                    }

                    $asimetricoParticipantesValidos[] = [
                        'username' => $filaUsername,
                        'personajes' => $filaPersonajesIds,
                        'ha_ganado' => $filaGanado,
                    ];
                }
            }

            if (count($asimetricoParticipantesValidos) < 2) {
                $asimetricoErrores[] = 'Una partida asimétrica necesita al menos 2 participantes.';
            }
            if ($asimetricoGanadores !== 1) {
                $asimetricoErrores[] = 'Debes marcar exactamente un ganador.';
            }

            $asimetricoErrores = array_values(array_unique($asimetricoErrores));

            if (count($asimetricoErrores) > 0) {
                $mensajeError = implode(' ', $asimetricoErrores);
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmtInsPartidaAsimetrica = $pdo->prepare("
                        INSERT INTO Partida_Asimetrica (codigo_juego, fecha_partida, duracion_minutos, notas)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmtInsPartidaAsimetrica->execute([
                        $codigoJuegoPost,
                        $asimetricoFecha,
                        $asimetricoDuracion,
                        $asimetricoNotas
                    ]);
                    $idPartida = (int)$pdo->lastInsertId();

                    $stmtInsParticipanteAsimetrico = $pdo->prepare("
                        INSERT INTO Participante_Asimetrico (id_partida_asimetrica, username, ha_ganado)
                        VALUES (?, ?, ?)
                    ");
                    $stmtInsParticipantePersonajeAsimetrico = $pdo->prepare("
                        INSERT INTO Participante_Personaje_Asimetrico (id_participante_asimetrico, id_personaje)
                        VALUES (?, ?)
                    ");
                    foreach ($asimetricoParticipantesValidos as $participanteAsimetrico) {
                        $stmtInsParticipanteAsimetrico->execute([
                            $idPartida,
                            $participanteAsimetrico['username'],
                            $participanteAsimetrico['ha_ganado']
                        ]);
                        $idParticipante = (int)$pdo->lastInsertId();
                        foreach ($participanteAsimetrico['personajes'] as $idPersonaje) {
                            $stmtInsParticipantePersonajeAsimetrico->execute([$idParticipante, (int)$idPersonaje]);
                        }
                    }

                    $pdo->commit();

                    $nombreVisibleAsimetrico = $juegosAsimetricos[$codigoJuegoPost]['nombre_visible'];
                    $mensajeExito = "Partida de «{$nombreVisibleAsimetrico}» registrada correctamente.";
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Error al registrar partida asimétrica: ' . $e->getMessage());
                    $mensajeError = 'No se pudo registrar la partida. Inténtalo de nuevo.';
                }
            }
        }
    }
}

// Garantizar filas mínimas para el formulario asimétrico
foreach ($asimetricoFilasForm as $indiceFilaForm => $filaForm) {
    while (count($asimetricoFilasForm[$indiceFilaForm]['personajes']) < $asimetricoNumPersonajesPorJugador) {
        $asimetricoFilasForm[$indiceFilaForm]['personajes'][] = '';
    }
}
while (count($asimetricoFilasForm) < 2) {
    $asimetricoFilasForm[] = [
        'username' => '',
        'personajes' => array_fill(0, $asimetricoNumPersonajesPorJugador, ''),
        'ha_ganado' => '',
    ];
}

// Juegos que YA tenemos (lo_tenemos = 1) para el alta y el filtro
$stmtJuegosTenemos = $pdo->query("SELECT NombreJuego FROM Juego WHERE lo_tenemos = 1 ORDER BY NombreJuego ASC");
$juegosDisponibles = $stmtJuegosTenemos->fetchAll();

// Todos los juegos para el selector de edición de administradores
$stmtTodosJuegos = $pdo->query("SELECT NombreJuego FROM Juego ORDER BY NombreJuego ASC");
$todosLosJuegos = $stmtTodosJuegos->fetchAll();

// Filtro de juego activo (por GET)
$filtroJuego = isset($_GET['juego']) ? trim($_GET['juego']) : '';

// Construir consulta del Leaderboard (Todos los usuarios aparecen automáticamente)
if ($filtroJuego !== '') {
    $stmtRanking = $pdo->prepare("
        SELECT p.username, p.NombrePersona, COUNT(v.id_victoria) AS total_victorias
        FROM Persona p
        LEFT JOIN Victoria v ON p.username = v.username AND v.NombreJuego = ?
        GROUP BY p.username, p.NombrePersona
        ORDER BY total_victorias DESC, p.NombrePersona ASC
    ");
    $stmtRanking->execute([$filtroJuego]);
    $ranking = $stmtRanking->fetchAll();
} else {
    $stmtRanking = $pdo->query("
        SELECT p.username, p.NombrePersona, COUNT(j.NombreJuego) AS total_victorias
        FROM Persona p
        LEFT JOIN Victoria v ON p.username = v.username
        LEFT JOIN Juego j ON v.NombreJuego = j.NombreJuego AND j.lo_tenemos = 1
        GROUP BY p.username, p.NombrePersona
        ORDER BY total_victorias DESC, p.NombrePersona ASC
    ");
    $ranking = $stmtRanking->fetchAll();
}

// Máximo de victorias para escalar el gráfico de barras (mínimo 1 para evitar divisiones por cero)
$maxVictorias = 1;
foreach ($ranking as $filaR) {
    if ((int)$filaR['total_victorias'] > $maxVictorias) {
        $maxVictorias = (int)$filaR['total_victorias'];
    }
}

// Obtener el historial de últimas partidas ganadas (historial enriquecido)
if ($filtroJuego !== '') {
    $stmtUltimasVictorias = $pdo->prepare("
        SELECT v.id_victoria, v.username, p.NombrePersona, v.NombreJuego, v.fecha_victoria,
               v.duracion_minutos, v.puntuacion
        FROM Victoria v
        INNER JOIN Persona p ON v.username = p.username
        WHERE v.NombreJuego = ?
        ORDER BY v.fecha_victoria DESC, v.id_victoria DESC
        LIMIT 25
    ");
    $stmtUltimasVictorias->execute([$filtroJuego]);
    $ultimasVictorias = $stmtUltimasVictorias->fetchAll();
} else {
    $stmtUltimasVictorias = $pdo->query("
        SELECT v.id_victoria, v.username, p.NombrePersona, v.NombreJuego, v.fecha_victoria,
               v.duracion_minutos, v.puntuacion
        FROM Victoria v
        INNER JOIN Persona p ON v.username = p.username
        INNER JOIN Juego j ON v.NombreJuego = j.NombreJuego AND j.lo_tenemos = 1
        ORDER BY v.fecha_victoria DESC, v.id_victoria DESC
        LIMIT 25
    ");
    $ultimasVictorias = $stmtUltimasVictorias->fetchAll();
}

// Obtener el historial completo de victorias para el gráfico temporal acumulativo (respetando el filtro de juego)
if ($filtroJuego !== '') {
    $stmtHistorial = $pdo->prepare("
        SELECT v.id_victoria, v.username, p.NombrePersona, v.NombreJuego, DATE(v.fecha_victoria) AS fecha_dia, v.fecha_victoria
        FROM Victoria v
        INNER JOIN Persona p ON v.username = p.username
        WHERE v.NombreJuego = ?
        ORDER BY v.fecha_victoria ASC, v.id_victoria ASC
    ");
    $stmtHistorial->execute([$filtroJuego]);
    $victoriasGrafico = $stmtHistorial->fetchAll();
} else {
    $stmtHistorial = $pdo->query("
        SELECT v.id_victoria, v.username, p.NombrePersona, v.NombreJuego, DATE(v.fecha_victoria) AS fecha_dia, v.fecha_victoria
        FROM Victoria v
        INNER JOIN Persona p ON v.username = p.username
        INNER JOIN Juego j ON v.NombreJuego = j.NombreJuego AND j.lo_tenemos = 1
        ORDER BY v.fecha_victoria ASC, v.id_victoria ASC
    ");
    $victoriasGrafico = $stmtHistorial->fetchAll();
}

// Cargar en una sola consulta los participantes de las victorias mostradas
$participantesPorVictoria = [];
if (count($ultimasVictorias) > 0) {
    $idsVictorias = [];
    foreach ($ultimasVictorias as $uv) {
        $idsVictorias[] = (int)$uv['id_victoria'];
    }
    $placeholders = implode(',', array_fill(0, count($idsVictorias), '?'));
    $stmtParticipantes = $pdo->prepare("
        SELECT vp.id_victoria, vp.username
        FROM Victoria_Participante vp
        WHERE vp.id_victoria IN ($placeholders)
        ORDER BY vp.username ASC
    ");
    $stmtParticipantes->execute($idsVictorias);
    foreach ($stmtParticipantes->fetchAll() as $filaP) {
        $participantesPorVictoria[(int)$filaP['id_victoria']][] = $filaP['username'];
    }
}

// ---------------------------------------------------------------------------
// Módulo Root: estadísticas e historial (solo si el módulo está disponible)
// ---------------------------------------------------------------------------
if ($rootDisponible) {
    try {
        $rootTotalPartidas = (int)$pdo->query("SELECT COUNT(*) FROM Partida_Root")->fetchColumn();

        $stmtRootRankingJugadores = $pdo->query("
            SELECT p.username, p.NombrePersona,
                   COUNT(*) AS partidas,
                   SUM(pr.ha_ganado) AS victorias,
                   AVG(pr.puntos) AS media_puntos
            FROM Participante_Root pr
            INNER JOIN Persona p ON pr.username = p.username
            GROUP BY p.username, p.NombrePersona
            ORDER BY victorias DESC, media_puntos DESC, partidas ASC, p.NombrePersona ASC
        ");
        $rootRankingJugadores = $stmtRootRankingJugadores->fetchAll();

        $stmtRootRankingFacciones = $pdo->query("
            SELECT f.id_faccion, f.nombre,
                   COUNT(*) AS partidas,
                   SUM(pr.ha_ganado) AS victorias,
                   AVG(pr.puntos) AS media_puntos
            FROM Participante_Root pr
            INNER JOIN Root_Faccion f ON pr.id_faccion = f.id_faccion
            GROUP BY f.id_faccion, f.nombre
            ORDER BY victorias DESC, media_puntos DESC, partidas ASC, f.nombre ASC
        ");
        $rootRankingFacciones = $stmtRootRankingFacciones->fetchAll();

        $stmtRootPartidas = $pdo->query("
            SELECT pr.id_partida_root, pr.fecha_partida, pr.condicion_victoria, pr.con_secuaces,
                   pr.duracion_minutos, pr.notas, m.nombre AS mapa, z.nombre AS mazo
            FROM Partida_Root pr
            INNER JOIN Root_Mapa m ON pr.id_mapa = m.id_mapa
            INNER JOIN Root_Mazo z ON pr.id_mazo = z.id_mazo
            ORDER BY pr.fecha_partida DESC, pr.id_partida_root DESC
            LIMIT 25
        ");
        $rootPartidas = $stmtRootPartidas->fetchAll();

        $rootParticipantesPorPartida = [];
        if (count($rootPartidas) > 0) {
            $idsPartidasRoot = [];
            foreach ($rootPartidas as $partidaRoot) {
                $idsPartidasRoot[] = (int)$partidaRoot['id_partida_root'];
            }
            $placeholdersRoot = implode(',', array_fill(0, count($idsPartidasRoot), '?'));
            $stmtParticipantesRoot = $pdo->prepare("
                SELECT pr.id_partida_root, pr.username, p.NombrePersona, pr.id_faccion,
                       f.nombre AS faccion, pr.ha_ganado, pr.puntos
                FROM Participante_Root pr
                INNER JOIN Persona p ON pr.username = p.username
                INNER JOIN Root_Faccion f ON pr.id_faccion = f.id_faccion
                WHERE pr.id_partida_root IN ($placeholdersRoot)
                ORDER BY pr.id_partida_root ASC, pr.ha_ganado DESC, pr.puntos DESC, p.NombrePersona ASC
            ");
            $stmtParticipantesRoot->execute($idsPartidasRoot);
            foreach ($stmtParticipantesRoot->fetchAll() as $filaRoot) {
                $rootParticipantesPorPartida[(int)$filaRoot['id_partida_root']][] = $filaRoot;
            }
        }
    } catch (PDOException $e) {
        $rootDisponible = false;
        $rootError = 'No se pudieron cargar las batallas de Root.';
        error_log('Error al cargar batallas de Root: ' . $e->getMessage());
        $rootRankingJugadores = [];
        $rootRankingFacciones = [];
        $rootPartidas = [];
        $rootParticipantesPorPartida = [];
        $rootTotalPartidas = 0;
    }
}

// ---------------------------------------------------------------------------
// Módulo Juegos Asimétricos: estadísticas e historial (juego seleccionado)
// ---------------------------------------------------------------------------
if ($asimetricosDisponible && $asimetricoJuegoActual !== null) {
    try {
        $stmtAsimetricoTotalPartidas = $pdo->prepare("SELECT COUNT(*) FROM Partida_Asimetrica WHERE codigo_juego = ?");
        $stmtAsimetricoTotalPartidas->execute([$codigoAsimetricoSeleccionado]);
        $asimetricoTotalPartidas = (int)$stmtAsimetricoTotalPartidas->fetchColumn();

        $stmtAsimetricoRankingJugadores = $pdo->prepare("
            SELECT p.username, p.NombrePersona,
                   COUNT(*) AS partidas,
                   SUM(pa.ha_ganado) AS victorias
            FROM Participante_Asimetrico pa
            INNER JOIN Partida_Asimetrica part ON pa.id_partida_asimetrica = part.id_partida_asimetrica
            INNER JOIN Persona p ON pa.username = p.username
            WHERE part.codigo_juego = ?
            GROUP BY p.username, p.NombrePersona
            ORDER BY victorias DESC, partidas ASC, p.NombrePersona ASC
        ");
        $stmtAsimetricoRankingJugadores->execute([$codigoAsimetricoSeleccionado]);
        $asimetricoRankingJugadores = $stmtAsimetricoRankingJugadores->fetchAll();

        $stmtAsimetricoRankingPersonajes = $pdo->prepare("
            SELECT per.id_personaje, per.nombre_personaje,
                   COUNT(*) AS apariciones,
                   SUM(pa.ha_ganado) AS victorias
            FROM Participante_Personaje_Asimetrico ppa
            INNER JOIN Participante_Asimetrico pa ON ppa.id_participante_asimetrico = pa.id_participante_asimetrico
            INNER JOIN Partida_Asimetrica part ON pa.id_partida_asimetrica = part.id_partida_asimetrica
            INNER JOIN Personaje_Asimetrico per ON ppa.id_personaje = per.id_personaje AND per.codigo_juego = part.codigo_juego
            WHERE part.codigo_juego = ?
            GROUP BY per.id_personaje, per.nombre_personaje
            ORDER BY victorias DESC, apariciones DESC, per.nombre_personaje ASC
        ");
        $stmtAsimetricoRankingPersonajes->execute([$codigoAsimetricoSeleccionado]);
        $asimetricoRankingPersonajes = $stmtAsimetricoRankingPersonajes->fetchAll();

        $stmtAsimetricoPartidas = $pdo->prepare("
            SELECT part.id_partida_asimetrica, part.fecha_partida, part.duracion_minutos, part.notas, j.nombre_visible
            FROM Partida_Asimetrica part
            INNER JOIN Juego_Asimetrico j ON part.codigo_juego = j.codigo_juego
            WHERE part.codigo_juego = ?
            ORDER BY part.fecha_partida DESC, part.id_partida_asimetrica DESC
            LIMIT 25
        ");
        $stmtAsimetricoPartidas->execute([$codigoAsimetricoSeleccionado]);
        $asimetricoPartidas = $stmtAsimetricoPartidas->fetchAll();

        $asimetricoParticipantesPorPartida = [];
        if (count($asimetricoPartidas) > 0) {
            $idsPartidasAsimetricas = [];
            foreach ($asimetricoPartidas as $partidaAsimetrica) {
                $idsPartidasAsimetricas[] = (int)$partidaAsimetrica['id_partida_asimetrica'];
            }
            $placeholdersAsimetricos = implode(',', array_fill(0, count($idsPartidasAsimetricas), '?'));

            $stmtParticipantesAsimetricos = $pdo->prepare("
                SELECT pa.id_partida_asimetrica, pa.username, p.NombrePersona, pa.ha_ganado,
                       per.id_personaje, per.nombre_personaje
                FROM Participante_Asimetrico pa
                INNER JOIN Partida_Asimetrica part ON pa.id_partida_asimetrica = part.id_partida_asimetrica
                INNER JOIN Persona p ON pa.username = p.username
                LEFT JOIN Participante_Personaje_Asimetrico ppa ON ppa.id_participante_asimetrico = pa.id_participante_asimetrico
                LEFT JOIN Personaje_Asimetrico per ON ppa.id_personaje = per.id_personaje AND per.codigo_juego = part.codigo_juego
                WHERE part.codigo_juego = ? AND pa.id_partida_asimetrica IN ($placeholdersAsimetricos)
                ORDER BY pa.id_partida_asimetrica ASC, pa.ha_ganado DESC, p.NombrePersona ASC, per.nombre_personaje ASC
            ");
            $stmtParticipantesAsimetricos->execute(array_merge([$codigoAsimetricoSeleccionado], $idsPartidasAsimetricas));

            // Agrupa por partida y username; los nombres de personaje pueden repetirse por filas
            $participantesTemporales = [];
            foreach ($stmtParticipantesAsimetricos->fetchAll() as $filaAsimetrica) {
                $idPartidaFila = (int)$filaAsimetrica['id_partida_asimetrica'];
                $claveTemporal = $idPartidaFila . '|' . $filaAsimetrica['username'];
                if (!isset($participantesTemporales[$claveTemporal])) {
                    $participantesTemporales[$claveTemporal] = [
                        'id_partida_asimetrica' => $idPartidaFila,
                        'username' => $filaAsimetrica['username'],
                        'NombrePersona' => $filaAsimetrica['NombrePersona'],
                        'ha_ganado' => $filaAsimetrica['ha_ganado'],
                        'personajes' => [],
                    ];
                }
                if ($filaAsimetrica['nombre_personaje'] !== null) {
                    $participantesTemporales[$claveTemporal]['personajes'][] = $filaAsimetrica['nombre_personaje'];
                }
            }
            $asimetricoParticipantesPorPartida = [];
            foreach ($participantesTemporales as $participanteTemporal) {
                $asimetricoParticipantesPorPartida[(int)$participanteTemporal['id_partida_asimetrica']][] = [
                    'username' => $participanteTemporal['username'],
                    'NombrePersona' => $participanteTemporal['NombrePersona'],
                    'ha_ganado' => $participanteTemporal['ha_ganado'],
                    'personajes' => array_values($participanteTemporal['personajes']),
                ];
            }
        }

        $asimetricoTotalJugadores = count($asimetricoRankingJugadores);
        $asimetricoTotalPersonajes = count($asimetricoPersonajes);
    } catch (PDOException $e) {
        $asimetricosDisponible = false;
        $asimetricosError = 'No se pudieron cargar las partidas de los juegos asimétricos.';
        error_log('Error al cargar Juegos Asimétricos: ' . $e->getMessage());
        $asimetricoRankingJugadores = [];
        $asimetricoRankingPersonajes = [];
        $asimetricoPartidas = [];
        $asimetricoParticipantesPorPartida = [];
        $asimetricoTotalPartidas = 0;
        $asimetricoTotalJugadores = 0;
        $asimetricoTotalPersonajes = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablón de Campeones - La Taberna del Dado de Hierro</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php renderizarNavegacion('leaderboard'); ?>

<main class="contenedor-principal">

    <?php if ($mensajeError !== ''): ?>
        <div class="alerta alerta-error"><?php echo h($mensajeError); ?></div>
    <?php endif; ?>

    <?php if ($mensajeExito !== ''): ?>
        <div class="alerta alerta-exito"><?php echo h($mensajeExito); ?></div>
    <?php endif; ?>

    <div class="pestanas-barra leaderboard-pestanas">
        <button type="button" class="pestana-btn activa" data-tab-group="leaderboard" data-tab-target="#panel-leaderboard-general">Tablón General</button>
        <button type="button" class="pestana-btn" data-tab-group="leaderboard" data-tab-target="#panel-leaderboard-root">Batallas de Root</button>
        <button type="button" class="pestana-btn" data-tab-group="leaderboard" data-tab-target="#panel-leaderboard-asimetricos">Juegos Asimétricos</button>
    </div>

    <div id="panel-leaderboard-general" data-tab-content-group="leaderboard">

    <!-- Asignar Ganador de una Partida -->
    <section class="pergamino">
        <h2 class="seccion-titulo">Anotar Ganador de Partida</h2>
        <?php if (count($juegosDisponibles) === 0): ?>
            <p style="font-style:italic;">No hay juegos marcados como «Ya lo tenemos».</p>
        <?php else: ?>
            <form method="POST" action="leaderboard.php<?php echo $filtroJuego !== '' ? '?juego=' . urlencode($filtroJuego) : ''; ?>" class="form-partida">
                <input type="hidden" name="accion" value="registrar_victoria">

                <div class="grid-tres-columnas" style="align-items:end;">
                    <div class="grupo-form" style="margin-bottom:0;">
                        <label for="select_juego_victoria">Juego:</label>
                        <select id="select_juego_victoria" name="NombreJuego" class="select-taberna" required>
                            <option value="">-- Selecciona un juego --</option>
                            <?php foreach ($juegosDisponibles as $jd): ?>
                                <option value="<?php echo h($jd['NombreJuego']); ?>"><?php echo h($jd['NombreJuego']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grupo-form" style="margin-bottom:0;">
                        <label for="select_persona_victoria">Ganador:</label>
                        <select id="select_persona_victoria" name="username" class="select-taberna" required>
                            <option value="">-- Selecciona al ganador --</option>
                            <?php foreach ($todasLasPersonas as $per): ?>
                                <option value="<?php echo h($per['username']); ?>">
                                    <?php echo h($per['NombrePersona']); ?> (<?php echo h($per['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primario" style="width:100%;">Asignar como Ganador</button>
                    </div>
                </div>

                <div class="grid-tres-columnas" style="margin-top:14px; align-items:end;">
                    <div class="grupo-form" style="margin-bottom:0;">
                        <label for="input_duracion_alta">Duración (minutos, opcional):</label>
                        <input type="number" id="input_duracion_alta" name="duracion_minutos" class="input-taberna" min="1" max="1440" step="1" placeholder="Ej. 90">
                    </div>

                    <div class="grupo-form" style="margin-bottom:0;">
                        <label for="input_puntuacion_alta">Puntuación (opcional):</label>
                        <input type="text" id="input_puntuacion_alta" name="puntuacion" class="input-taberna" maxlength="100" placeholder="Ej. 142 puntos">
                    </div>

                    <div class="grupo-form" style="margin-bottom:0;">
                        <label>Participantes (además del ganador):</label>
                        <div class="multiselect-taberna" data-multiselect>
                            <input type="text" class="input-taberna" placeholder="Buscar participante..." data-multiselect-search style="margin-bottom:8px;">
                            <div class="multiselect-lista">
                                <?php foreach ($todasLasPersonas as $per): ?>
                                    <label class="multiselect-opcion" data-multiselect-item data-nombre="<?php echo h($per['NombrePersona'] . ' ' . $per['username']); ?>">
                                        <input type="checkbox" name="participantes[]" value="<?php echo h($per['username']); ?>">
                                        <strong><?php echo h($per['NombrePersona']); ?></strong> (<?php echo h($per['username']); ?>)
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <!-- Clasificación y Filtro por Juego -->
    <section class="pergamino">
        <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:14px; margin-bottom:16px; border-bottom:2px solid #4a3219; padding-bottom:12px;">
            <div>
                <h2 class="seccion-titulo" style="border-bottom:none; margin-bottom:0; padding-bottom:0;">
                    <?php if ($filtroJuego !== ''): ?>
                        Clasificación del Juego: <?php echo h($filtroJuego); ?>
                    <?php else: ?>
                        Clasificación General (Todos los Juegos)
                    <?php endif; ?>
                </h2>
            </div>

            <form method="GET" action="leaderboard.php" class="barra-filtro-leaderboard">
                <label for="filtro_juego" style="font-weight:bold;">Juego:</label>
                <select id="filtro_juego" name="juego" class="select-taberna" onchange="this.form.submit()">
                    <option value="">-- Todos los juegos (Global) --</option>
                    <?php foreach ($juegosDisponibles as $jd): ?>
                        <option value="<?php echo h($jd['NombreJuego']); ?>" <?php echo ($filtroJuego === $jd['NombreJuego']) ? 'selected' : ''; ?>>
                            <?php echo h($jd['NombreJuego']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-bronce btn-pequeno">Filtrar</button>
                <?php if ($filtroJuego !== ''): ?>
                    <a href="leaderboard.php" class="btn btn-pequeno">Ver Todos</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="selector-vista-leaderboard">
            <label for="vista_leaderboard" style="font-weight:bold;">Vista:</label>
            <select id="vista_leaderboard" class="select-taberna">
                <option value="tabla">Tabla</option>
                <option value="grafico">Gráfico</option>
            </select>
        </div>

        <div id="vista-tabla-leaderboard" class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
                        <th style="width:90px;">Puesto</th>
                        <th>Jugador</th>
                        <th>Usuario</th>
                        <th style="width:180px;">Victorias Acumuladas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($ranking) === 0): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; font-style:italic;">
                                Todavía no hay jugadores inscritos en el Leaderboard.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $posicion = 1; ?>
                        <?php foreach ($ranking as $filaR): ?>
                            <tr>
                                <td><strong>#<?php echo $posicion++; ?></strong></td>
                                <td><strong><?php echo h($filaR['NombrePersona']); ?></strong></td>
                                <td><?php echo h($filaR['username']); ?></td>
                                <td>
                                    <span class="etiqueta etiqueta-bronce" style="font-size:0.95rem;">
                                        <?php echo (int)$filaR['total_victorias']; ?> victorias
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="vista-grafico-leaderboard" class="panel-oculto grafico-barras-taberna">
            <?php if (count($ranking) === 0): ?>
                <p style="text-align:center; font-style:italic;">Todavía no hay jugadores inscritos en el Leaderboard.</p>
            <?php else: ?>
                <?php foreach ($ranking as $filaG): ?>
                    <?php
                        $victoriasG = (int)$filaG['total_victorias'];
                        $anchoBarra = (int)round(($victoriasG / $maxVictorias) * 100);
                    ?>
                    <div class="grafico-fila">
                        <span class="grafico-etiqueta" title="<?php echo h($filaG['NombrePersona'] . ' (' . $filaG['username'] . ')'); ?>"><?php echo h($filaG['NombrePersona']); ?></span>
                        <div class="grafico-pista">
                            <div class="grafico-barra" style="width: <?php echo $anchoBarra; ?>%;"></div>
                        </div>
                        <span class="grafico-valor"><?php echo $victoriasG; ?> victorias</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Gráfico Temporal Acumulativo de Victorias -->
    <section class="pergamino">
        <div class="barra-titulo-filtro" style="border-bottom:2px solid #4a3219; padding-bottom:10px; margin-bottom:14px;">
            <h2 class="seccion-titulo" style="border-bottom:none; margin-bottom:0; padding-bottom:0;">
                <?php if ($filtroJuego !== ''): ?>
                    Evolución Temporal de Victorias: <?php echo h($filtroJuego); ?>
                <?php else: ?>
                    Evolución Temporal de Victorias (Global)
                <?php endif; ?>
            </h2>
        </div>

        <div class="controles-grafico-leaderboard pergamino-secundario" id="panel-grafico-victorias"
             data-victorias="<?php echo h(json_encode($victoriasGrafico, JSON_UNESCAPED_UNICODE)); ?>"
             data-hoy="<?php echo date('Y-m-d'); ?>">

            <div class="controles-grafico-fila">
                <div class="grupo-control-grafico">
                    <label for="rango-rapido-grafico">Plazo rápido:</label>
                    <select id="rango-rapido-grafico" class="select-taberna">
                        <option value="1m">Último mes</option>
                        <option value="3m">Últimos 3 meses</option>
                        <option value="6m">Últimos 6 meses</option>
                        <option value="1y" selected>Último año (Por defecto)</option>
                        <option value="all">Todo el historial</option>
                        <option value="custom">Personalizado</option>
                    </select>
                </div>

                <div class="grupo-control-grafico">
                    <label for="fecha-desde-grafico">Desde:</label>
                    <input type="date" id="fecha-desde-grafico" class="input-taberna">
                </div>

                <div class="grupo-control-grafico">
                    <label for="fecha-hasta-grafico">Hasta:</label>
                    <input type="date" id="fecha-hasta-grafico" class="input-taberna">
                </div>
            </div>

            <div id="leyenda-grafico-victorias" class="leyenda-grafico-taberna"></div>

            <div class="lienzo-grafico-wrapper">
                <canvas id="canvas-grafico-victorias" height="340"></canvas>
                <p id="grafico-sin-victorias" style="display:none; font-style:italic; text-align:center; padding:34px 12px; margin:0; color:#5a4634;">
                    No hay victorias registradas en este rango de fechas para el filtro actual.
                </p>
            </div>
        </div>
    </section>

    <!-- Últimas Partidas Ganadas -->
    <section class="pergamino">
        <h2 class="seccion-titulo">Últimas Partidas Ganadas</h2>
        <div class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
                        <th>Ganador</th>
                        <th>Juego de Mesa</th>
                        <th style="width:210px;">Fecha y Hora</th>
                        <th style="width:110px;">Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($ultimasVictorias) === 0): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; font-style:italic;">
                                No hay victorias registradas para el filtro seleccionado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ultimasVictorias as $uv): ?>
                            <?php
                                $idVictoriaFila = (int)$uv['id_victoria'];
                                $participantesFila = isset($participantesPorVictoria[$idVictoriaFila])
                                    ? $participantesPorVictoria[$idVictoriaFila]
                                    : [];
                                // Respaldo: victorias históricas sin filas en Victoria_Participante
                                if (count($participantesFila) === 0) {
                                    $participantesFila = [$uv['username']];
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo h($uv['NombrePersona']); ?></strong>
                                    (<?php echo h($uv['username']); ?>)
                                </td>
                                <td><?php echo h($uv['NombreJuego']); ?></td>
                                <td><?php echo h($uv['fecha_victoria']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-pequeno" data-toggle-panel="detalle-partida-<?php echo $idVictoriaFila; ?>" aria-expanded="false" aria-controls="detalle-partida-<?php echo $idVictoriaFila; ?>">
                                        Detalles
                                    </button>
                                </td>
                            </tr>
                            <tr id="detalle-partida-<?php echo $idVictoriaFila; ?>" class="fila-detalle-partida panel-oculto">
                                <td colspan="4">
                                    <div class="detalle-partida">
                                        <p>
                                            <strong>Duración:</strong>
                                            <?php echo ($uv['duracion_minutos'] !== null && $uv['duracion_minutos'] !== '') ? (int)$uv['duracion_minutos'] . ' minutos' : 'No indicada'; ?>
                                        </p>
                                        <p>
                                            <strong>Puntuación:</strong>
                                            <?php echo ($uv['puntuacion'] !== null && $uv['puntuacion'] !== '') ? h($uv['puntuacion']) : 'No indicada'; ?>
                                        </p>
                                        <p><strong>Participantes:</strong></p>
                                        <div class="lista-participantes">
                                            <?php foreach ($participantesFila as $usuarioP): ?>
                                                <span class="etiqueta etiqueta-bronce">
                                                    <?php echo h(isset($nombresPorUsuario[$usuarioP]) ? $nombresPorUsuario[$usuarioP] : $usuarioP); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>

                                        <?php if ($esAdmin): ?>
                                            <button type="button" class="btn btn-bronce btn-pequeno" data-toggle-panel="editar-partida-<?php echo $idVictoriaFila; ?>" aria-expanded="false" aria-controls="editar-partida-<?php echo $idVictoriaFila; ?>">
                                                Editar Partida
                                            </button>

                                            <div class="panel-editar-partida">
                                                <div id="editar-partida-<?php echo $idVictoriaFila; ?>" class="panel-oculto panel-editar-partida-form">
                                                    <form method="POST" action="leaderboard.php<?php echo $filtroJuego !== '' ? '?juego=' . urlencode($filtroJuego) : ''; ?>" class="form-editar-partida">
                                                        <input type="hidden" name="accion" value="editar_victoria">
                                                        <input type="hidden" name="id_victoria" value="<?php echo $idVictoriaFila; ?>">

                                                        <div class="grupo-form">
                                                            <label for="edit_juego_<?php echo $idVictoriaFila; ?>">Juego:</label>
                                                            <select id="edit_juego_<?php echo $idVictoriaFila; ?>" name="NombreJuego" class="select-taberna" required>
                                                                <?php foreach ($todosLosJuegos as $je): ?>
                                                                    <option value="<?php echo h($je['NombreJuego']); ?>" <?php echo ($je['NombreJuego'] === $uv['NombreJuego']) ? 'selected' : ''; ?>>
                                                                        <?php echo h($je['NombreJuego']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                                <?php if (!in_array($uv['NombreJuego'], array_column($todosLosJuegos, 'NombreJuego'), true)): ?>
                                                                    <option value="<?php echo h($uv['NombreJuego']); ?>" selected><?php echo h($uv['NombreJuego']); ?></option>
                                                                <?php endif; ?>
                                                            </select>
                                                        </div>

                                                        <div class="grupo-form">
                                                            <label for="edit_ganador_<?php echo $idVictoriaFila; ?>">Ganador:</label>
                                                            <select id="edit_ganador_<?php echo $idVictoriaFila; ?>" name="username" class="select-taberna" required>
                                                                <?php foreach ($todasLasPersonas as $per): ?>
                                                                    <option value="<?php echo h($per['username']); ?>" <?php echo ($per['username'] === $uv['username']) ? 'selected' : ''; ?>>
                                                                        <?php echo h($per['NombrePersona']); ?> (<?php echo h($per['username']); ?>)
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <div class="grupo-form">
                                                            <label for="edit_fecha_<?php echo $idVictoriaFila; ?>">Fecha y hora:</label>
                                                            <input type="text" id="edit_fecha_<?php echo $idVictoriaFila; ?>" name="fecha_victoria" class="input-taberna" placeholder="AAAA-MM-DD HH:MM:SS" value="<?php echo h($uv['fecha_victoria']); ?>" required>
                                                        </div>

                                                        <div class="grupo-form">
                                                            <label for="edit_duracion_<?php echo $idVictoriaFila; ?>">Duración (minutos):</label>
                                                            <input type="number" id="edit_duracion_<?php echo $idVictoriaFila; ?>" name="duracion_minutos" class="input-taberna" min="1" max="1440" step="1" value="<?php echo ($uv['duracion_minutos'] !== null && $uv['duracion_minutos'] !== '') ? (int)$uv['duracion_minutos'] : ''; ?>">
                                                        </div>

                                                        <div class="grupo-form">
                                                            <label for="edit_puntuacion_<?php echo $idVictoriaFila; ?>">Puntuación:</label>
                                                            <input type="text" id="edit_puntuacion_<?php echo $idVictoriaFila; ?>" name="puntuacion" class="input-taberna" maxlength="100" value="<?php echo h((string)$uv['puntuacion']); ?>">
                                                        </div>

                                                        <div class="grupo-form">
                                                            <label for="edit_participantes_<?php echo $idVictoriaFila; ?>">Participantes (Ctrl/Cmd para selección múltiple):</label>
                                                            <?php
                                                            $seleccionadosFila = [];
                                                            foreach ($participantesFila as $uPartFila) {
                                                                $seleccionadosFila[(string)$uPartFila] = true;
                                                            }
                                                            $seleccionadosFila[(string)$uv['username']] = true;
                                                            ?>
                                                            <select id="edit_participantes_<?php echo $idVictoriaFila; ?>" name="participantes[]" class="select-taberna select-participantes" multiple size="4">
                                                                <?php foreach ($todasLasPersonas as $per): ?>
                                                                    <option value="<?php echo h($per['username']); ?>" <?php echo isset($seleccionadosFila[(string)$per['username']]) ? 'selected' : ''; ?>>
                                                                        <?php echo h($per['NombrePersona']); ?> (<?php echo h($per['username']); ?>)<?php echo $per['username'] === $uv['username'] ? ' — Ganador' : ''; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <button type="submit" class="btn btn-primario btn-pequeno">Guardar cambios</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    </div><!-- /#panel-leaderboard-general -->

    <div id="panel-leaderboard-root" data-tab-content-group="leaderboard" class="panel-oculto">
        <?php if (!$rootDisponible): ?>
            <section class="pergamino">
                <h2 class="seccion-titulo">Batallas de Root</h2>
                <div class="alerta alerta-error"><?php echo h($rootError); ?></div>
            </section>
        <?php else: ?>
            <?php
            $rootPostActivo = (
                $_SERVER['REQUEST_METHOD'] === 'POST'
                && isset($_POST['accion'])
                && $_POST['accion'] === 'registrar_partida_root'
                && $mensajeError !== ''
            );
            $rootFilasRender = $rootPostActivo ? $rootFilasForm : [];
            while (count($rootFilasRender) < 2) {
                $rootFilasRender[] = ['username' => '', 'id_faccion' => '', 'puntos' => '', 'ha_ganado' => ''];
            }
            ?>

            <!-- A) Alta de partida de Root -->
            <section class="pergamino">
                <h2 class="seccion-titulo">Anotar Partida de Root</h2>

                <?php if (!$rootFormularioDisponible): ?>
                    <div class="alerta alerta-error">
                        No hay facciones, mapas y mazos activos suficientes para registrar una partida de Root.
                        Añádelos desde el panel de administración.
                    </div>
                <?php else: ?>
                    <form id="form-root-partida" method="POST" action="leaderboard.php<?php echo $filtroJuego !== '' ? '?juego=' . urlencode($filtroJuego) : ''; ?>" class="form-partida">
                        <input type="hidden" name="accion" value="registrar_partida_root">

                        <div class="grid-tres-columnas" style="align-items:end;">
                            <div class="grupo-form" style="margin-bottom:0;">
                                <label for="root_fecha_partida">Fecha y hora (opcional):</label>
                                <input type="datetime-local" id="root_fecha_partida" name="fecha_partida" class="input-taberna"
                                       value="<?php echo h($rootPostActivo ? ($_POST['fecha_partida'] ?? '') : date('Y-m-d\TH:i')); ?>">
                            </div>

                            <div class="grupo-form" style="margin-bottom:0;">
                                <label for="root_mapa">Mapa:</label>
                                <select id="root_mapa" name="id_mapa" class="select-taberna" required>
                                    <option value="">-- Selecciona un mapa --</option>
                                    <?php foreach ($rootMapas as $mapaFila): ?>
                                        <option value="<?php echo (int)$mapaFila['id_mapa']; ?>"
                                            <?php echo ($rootPostActivo && (string)($_POST['id_mapa'] ?? '') === (string)$mapaFila['id_mapa']) ? 'selected' : ''; ?>>
                                            <?php echo h($mapaFila['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="grupo-form" style="margin-bottom:0;">
                                <label for="root_mazo">Mazo:</label>
                                <select id="root_mazo" name="id_mazo" class="select-taberna" required>
                                    <option value="">-- Selecciona un mazo --</option>
                                    <?php foreach ($rootMazos as $mazoFila): ?>
                                        <option value="<?php echo (int)$mazoFila['id_mazo']; ?>"
                                            <?php echo ($rootPostActivo && (string)($_POST['id_mazo'] ?? '') === (string)$mazoFila['id_mazo']) ? 'selected' : ''; ?>>
                                            <?php echo h($mazoFila['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="grid-tres-columnas" style="margin-top:14px; align-items:end;">
                            <div class="grupo-form" style="margin-bottom:0;">
                                <label for="root_condicion">Condición de victoria:</label>
                                <?php $rootCondicionPost = $rootPostActivo ? (string)($_POST['condicion_victoria'] ?? '30_puntos') : '30_puntos'; ?>
                                <select id="root_condicion" name="condicion_victoria" class="select-taberna">
                                    <option value="30_puntos" <?php echo $rootCondicionPost === '30_puntos' ? 'selected' : ''; ?>>30 puntos</option>
                                    <option value="dominancia" <?php echo $rootCondicionPost === 'dominancia' ? 'selected' : ''; ?>>Dominancia</option>
                                </select>
                            </div>

                            <div class="grupo-form" style="margin-bottom:0;">
                                <label for="root_duracion">Duración (minutos, opcional):</label>
                                <input type="number" id="root_duracion" name="duracion_minutos" class="input-taberna" min="1" max="1440" step="1"
                                       value="<?php echo h($rootPostActivo ? ($_POST['duracion_minutos'] ?? '') : ''); ?>">
                            </div>

                            <div class="grupo-form" style="margin-bottom:0;">
                                <label for="root_secuaces" style="display:flex; align-items:center; gap:6px;">
                                    <input type="checkbox" id="root_secuaces" name="con_secuaces" value="1"
                                        <?php echo ($rootPostActivo && !empty($_POST['con_secuaces'])) ? 'checked' : ''; ?>>
                                    Con secuaces
                                </label>
                            </div>
                        </div>

                        <div class="grupo-form" style="margin-top:14px;">
                            <label for="root_notas">Notas (opcional):</label>
                            <input type="text" id="root_notas" name="notas" class="input-taberna" maxlength="255"
                                   value="<?php echo h($rootPostActivo ? ($_POST['notas'] ?? '') : ''); ?>">
                        </div>

                        <h3 style="margin:18px 0 8px; color:#4a3219;">Participantes</h3>
                        <p style="font-style:italic; margin:0 0 10px; font-size:0.93rem;">
                            Mínimo 2 participantes y al menos un ganador. Se puede marcar más de un ganador si la partida lo requiere.
                        </p>

                        <div id="root-participantes" data-next-index="<?php echo count($rootFilasRender); ?>">
                            <?php foreach ($rootFilasRender as $i => $filaRoot): ?>
                                <div class="root-participante-fila" data-root-participante-row>
                                    <div class="grupo-form">
                                        <label>Jugador:</label>
                                        <select name="participantes[<?php echo $i; ?>][username]" class="select-taberna">
                                            <option value="">-- Jugador --</option>
                                            <?php foreach ($todasLasPersonas as $per): ?>
                                                <option value="<?php echo h($per['username']); ?>"
                                                    <?php echo ($filaRoot['username'] === $per['username']) ? 'selected' : ''; ?>>
                                                    <?php echo h($per['NombrePersona']); ?> (<?php echo h($per['username']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="grupo-form">
                                        <label>Facción:</label>
                                        <select name="participantes[<?php echo $i; ?>][id_faccion]" class="select-taberna">
                                            <option value="">-- Facción --</option>
                                            <?php foreach ($rootFacciones as $faccionFila): ?>
                                                <option value="<?php echo (int)$faccionFila['id_faccion']; ?>"
                                                    <?php echo ((string)$filaRoot['id_faccion'] === (string)$faccionFila['id_faccion']) ? 'selected' : ''; ?>>
                                                    <?php echo h($faccionFila['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="grupo-form">
                                        <label>Puntos:</label>
                                        <input type="number" name="participantes[<?php echo $i; ?>][puntos]" class="input-taberna" min="0" max="30" step="1" value="<?php echo h($filaRoot['puntos']); ?>">
                                    </div>

                                    <div class="grupo-form">
                                        <label style="display:flex; align-items:center; gap:6px;">
                                            <input type="checkbox" name="participantes[<?php echo $i; ?>][ha_ganado]" value="1"
                                                <?php echo (!empty($filaRoot['ha_ganado'])) ? 'checked' : ''; ?>>
                                            Ganador
                                        </label>
                                    </div>

                                    <div class="root-participante-acciones">
                                        <button type="button" class="btn btn-pequeno" data-root-eliminar>Quitar</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <template id="root-participante-template">
                            <div class="root-participante-fila" data-root-participante-row>
                                <div class="grupo-form">
                                    <label>Jugador:</label>
                                    <select name="participantes[__INDEX__][username]" class="select-taberna">
                                        <option value="">-- Jugador --</option>
                                        <?php foreach ($todasLasPersonas as $per): ?>
                                            <option value="<?php echo h($per['username']); ?>">
                                                <?php echo h($per['NombrePersona']); ?> (<?php echo h($per['username']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="grupo-form">
                                    <label>Facción:</label>
                                    <select name="participantes[__INDEX__][id_faccion]" class="select-taberna">
                                        <option value="">-- Facción --</option>
                                        <?php foreach ($rootFacciones as $faccionFila): ?>
                                            <option value="<?php echo (int)$faccionFila['id_faccion']; ?>">
                                                <?php echo h($faccionFila['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="grupo-form">
                                    <label>Puntos:</label>
                                    <input type="number" name="participantes[__INDEX__][puntos]" class="input-taberna" min="0" max="30" step="1" value="">
                                </div>

                                <div class="grupo-form">
                                    <label style="display:flex; align-items:center; gap:6px;">
                                        <input type="checkbox" name="participantes[__INDEX__][ha_ganado]" value="1">
                                        Ganador
                                    </label>
                                </div>

                                <div class="root-participante-acciones">
                                    <button type="button" class="btn btn-pequeno" data-root-eliminar>Quitar</button>
                                </div>
                            </div>
                        </template>

                        <div style="margin:10px 0 16px;">
                            <button type="button" id="root-agregar-participante" class="btn btn-bronce btn-pequeno">+ Añadir participante</button>
                        </div>

                        <button type="submit" class="btn btn-primario">Registrar Partida de Root</button>
                    </form>
                <?php endif; ?>
            </section>

            <!-- B) Estadísticas de Root -->
            <section class="pergamino">
                <h2 class="seccion-titulo">Batallas de Root</h2>

                <div class="grid-tres-columnas root-resumen">
                    <div class="estadistica-caja">
                        <span class="estadistica-numero"><?php echo (int)$rootTotalPartidas; ?></span>
                        Partidas registradas
                    </div>
                    <div class="estadistica-caja">
                        <span class="estadistica-numero"><?php echo count($rootRankingJugadores); ?></span>
                        Jugadores con partidas
                    </div>
                    <div class="estadistica-caja">
                        <span class="estadistica-numero"><?php echo count($rootRankingFacciones); ?></span>
                        Facciones con partidas
                    </div>
                </div>

                <?php if ($rootTotalPartidas === 0): ?>
                    <p style="font-style:italic;">Todavía no hay partidas de Root registradas.</p>
                <?php else: ?>
                    <div style="margin-top:18px; display:flex; flex-direction:column; gap:24px;">
                        <div>
                            <h3 style="margin:0 0 8px; color:#4a3219;">Ranking de Jugadores</h3>
                            <div class="tabla-contenedor tabla-root-scroll">
                                <table class="tabla-taberna">
                                    <thead>
                                        <tr>
                                            <th style="width:70px;">Puesto</th>
                                            <th>Jugador</th>
                                            <th style="width:90px;">Partidas</th>
                                            <th style="width:90px;">Victorias</th>
                                            <th style="width:100px;">% Victorias</th>
                                            <th style="width:110px;">Media puntos</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $puestoRoot = 1; ?>
                                        <?php foreach ($rootRankingJugadores as $filaRJ): ?>
                                            <?php
                                            $partidasRJ = (int)$filaRJ['partidas'];
                                            $victoriasRJ = (int)$filaRJ['victorias'];
                                            $porcentajeRJ = $partidasRJ > 0 ? round(($victoriasRJ / $partidasRJ) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td><strong>#<?php echo $puestoRoot++; ?></strong></td>
                                                <td><strong><?php echo h($filaRJ['NombrePersona']); ?></strong> (<?php echo h($filaRJ['username']); ?>)</td>
                                                <td><?php echo $partidasRJ; ?></td>
                                                <td><?php echo $victoriasRJ; ?></td>
                                                <td><?php echo h((string)$porcentajeRJ); ?> %</td>
                                                <td><?php echo $filaRJ['media_puntos'] !== null ? h(number_format((float)$filaRJ['media_puntos'], 1, ',', '.')) : '—'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div>
                            <h3 style="margin:0 0 8px; color:#4a3219;">Ranking de Facciones</h3>
                            <div class="tabla-contenedor tabla-root-scroll">
                                <table class="tabla-taberna">
                                    <thead>
                                        <tr>
                                            <th>Facción</th>
                                            <th style="width:90px;">Partidas</th>
                                            <th style="width:90px;">Victorias</th>
                                            <th style="width:100px;">% Victorias</th>
                                            <th style="width:110px;">Media puntos</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rootRankingFacciones as $filaRF): ?>
                                            <?php
                                            $partidasRF = (int)$filaRF['partidas'];
                                            $victoriasRF = (int)$filaRF['victorias'];
                                            $porcentajeRF = $partidasRF > 0 ? round(($victoriasRF / $partidasRF) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td><strong><?php echo h($filaRF['nombre']); ?></strong></td>
                                                <td><?php echo $partidasRF; ?></td>
                                                <td><?php echo $victoriasRF; ?></td>
                                                <td><?php echo h((string)$porcentajeRF); ?> %</td>
                                                <td><?php echo $filaRF['media_puntos'] !== null ? h(number_format((float)$filaRF['media_puntos'], 1, ',', '.')) : '—'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <!-- C) Historial de partidas de Root -->
            <section class="pergamino">
                <h2 class="seccion-titulo">Últimas Partidas de Root</h2>

                <?php if (count($rootPartidas) === 0): ?>
                    <p style="font-style:italic;">Todavía no hay partidas de Root registradas.</p>
                <?php else: ?>
                    <div class="tabla-contenedor tabla-root-scroll">
                        <table class="tabla-taberna">
                            <thead>
                                <tr>
                                    <th style="width:150px;">Fecha</th>
                                    <th>Mapa / Mazo</th>
                                    <th style="width:130px;">Condición</th>
                                    <th>Ganador(es)</th>
                                    <th style="width:110px;">Detalles</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rootPartidas as $partidaRoot): ?>
                                    <?php
                                    $idRoot = (int)$partidaRoot['id_partida_root'];
                                    $participantesRootFila = isset($rootParticipantesPorPartida[$idRoot])
                                        ? $rootParticipantesPorPartida[$idRoot]
                                        : [];
                                    $nombresGanadores = [];
                                    foreach ($participantesRootFila as $participanteRoot) {
                                        if ((int)$participanteRoot['ha_ganado'] === 1) {
                                            $nombresGanadores[] = $participanteRoot['NombrePersona'] !== null && $participanteRoot['NombrePersona'] !== ''
                                                ? $participanteRoot['NombrePersona']
                                                : $participanteRoot['username'];
                                        }
                                    }
                                    $textoGanadores = count($nombresGanadores) > 0 ? implode(', ', $nombresGanadores) : 'Sin ganador registrado';
                                    $textoCondicion = $partidaRoot['condicion_victoria'] === 'dominancia' ? 'Dominancia' : '30 puntos';
                                    ?>
                                    <tr>
                                        <td><?php echo h((string)$partidaRoot['fecha_partida']); ?></td>
                                        <td>
                                            <?php echo h((string)$partidaRoot['mapa']); ?>
                                            <?php if (!empty($partidaRoot['mazo'])): ?>
                                                / <?php echo h((string)$partidaRoot['mazo']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo h($textoCondicion); ?></td>
                                        <td><?php echo h($textoGanadores); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-pequeno" data-toggle-panel="detalle-root-<?php echo $idRoot; ?>" aria-expanded="false" aria-controls="detalle-root-<?php echo $idRoot; ?>">
                                                Detalles
                                            </button>
                                        </td>
                                    </tr>
                                    <tr id="detalle-root-<?php echo $idRoot; ?>" class="fila-detalle-partida panel-oculto">
                                        <td colspan="5">
                                            <div class="detalle-partida">
                                                <p><strong>Participantes:</strong></p>
                                                <div class="lista-participantes">
                                                    <?php if (count($participantesRootFila) === 0): ?>
                                                        <span class="etiqueta etiqueta-bronce">Sin participantes registrados</span>
                                                    <?php else: ?>
                                                        <?php foreach ($participantesRootFila as $participanteRoot): ?>
                                                            <span class="etiqueta <?php echo ((int)$participanteRoot['ha_ganado'] === 1) ? 'etiqueta-disponible' : 'etiqueta-bronce'; ?>">
                                                                <?php
                                                                echo h($participanteRoot['NombrePersona'] !== null && $participanteRoot['NombrePersona'] !== ''
                                                                    ? $participanteRoot['NombrePersona']
                                                                    : $participanteRoot['username']);
                                                                if (!empty($participanteRoot['faccion'])) {
                                                                    echo ' — ' . h((string)$participanteRoot['faccion']);
                                                                }
                                                                if ($participanteRoot['puntos'] !== null && $participanteRoot['puntos'] !== '') {
                                                                    echo ' — ' . (int)$participanteRoot['puntos'] . ' pts';
                                                                }
                                                                if ((int)$participanteRoot['ha_ganado'] === 1) {
                                                                    echo ' — Ganador';
                                                                }
                                                                ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <p><strong>Condición:</strong> <?php echo h($textoCondicion); ?></p>
                                                <p><strong>Con secuaces:</strong> <?php echo ((int)$partidaRoot['con_secuaces'] === 1) ? 'Sí' : 'No'; ?></p>
                                                <p>
                                                    <strong>Duración:</strong>
                                                    <?php echo ($partidaRoot['duracion_minutos'] !== null && $partidaRoot['duracion_minutos'] !== '') ? (int)$partidaRoot['duracion_minutos'] . ' minutos' : 'No indicada'; ?>
                                                </p>
                                                <p>
                                                    <strong>Notas:</strong>
                                                    <?php echo ($partidaRoot['notas'] !== null && $partidaRoot['notas'] !== '') ? h((string)$partidaRoot['notas']) : 'Sin notas'; ?>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div><!-- /#panel-leaderboard-root -->

    <div id="panel-leaderboard-asimetricos" data-tab-content-group="leaderboard" class="panel-oculto">
        <?php if (!$asimetricosDisponible): ?>
            <section class="pergamino">
                <h2 class="seccion-titulo">Juegos Asimétricos</h2>
                <div class="alerta alerta-error"><?php echo h($asimetricosError); ?></div>
            </section>
        <?php elseif ($asimetricoJuegoActual === null): ?>
            <section class="pergamino">
                <h2 class="seccion-titulo">Juegos Asimétricos</h2>
                <p style="font-style:italic;">No hay juegos asimétricos configurados todavía. Añádelos desde el panel de administración.</p>
            </section>
        <?php else: ?>
            <?php
            $asimetricoFormularioRender = $asimetricoFormularioDisponible
                && count($asimetricoPersonajes) >= (2 * $asimetricoNumPersonajesPorJugador);
            $asimetricoPostActivo = (
                $_SERVER['REQUEST_METHOD'] === 'POST'
                && isset($_POST['accion'])
                && $_POST['accion'] === 'registrar_partida_asimetrica'
                && $mensajeError !== ''
            );
            $asimetricoFilasRender = $asimetricoPostActivo ? $asimetricoFilasForm : [];
            foreach ($asimetricoFilasRender as $indiceFilaRender => $filaRender) {
                while (count($asimetricoFilasRender[$indiceFilaRender]['personajes']) < $asimetricoNumPersonajesPorJugador) {
                    $asimetricoFilasRender[$indiceFilaRender]['personajes'][] = '';
                }
            }
            while (count($asimetricoFilasRender) < 2) {
                $asimetricoFilasRender[] = [
                    'username' => '',
                    'personajes' => array_fill(0, $asimetricoNumPersonajesPorJugador, ''),
                    'ha_ganado' => '',
                ];
            }
            $paramsAccionAsim = [];
            if ($filtroJuego !== '') {
                $paramsAccionAsim['juego'] = $filtroJuego;
            }
            $paramsAccionAsim['asimetrico'] = $codigoAsimetricoSeleccionado;
            $accionAsimetrica = 'leaderboard.php?' . http_build_query($paramsAccionAsim);
            ?>

            <!-- A) Cabecera y selector de juego asimétrico -->
            <section class="pergamino">
                <h2 class="seccion-titulo">Juegos Asimétricos</h2>

                <form method="GET" action="leaderboard.php" class="barra-filtro-leaderboard selector-asimetrico">
                    <?php if ($filtroJuego !== ''): ?>
                        <input type="hidden" name="juego" value="<?php echo h($filtroJuego); ?>">
                    <?php endif; ?>
                    <label for="selector_asimetrico">Juego:</label>
                    <select id="selector_asimetrico" name="asimetrico" class="select-taberna" onchange="this.form.submit()">
                        <?php foreach ($juegosAsimetricosLista as $jA): ?>
                            <option value="<?php echo h($jA['codigo_juego']); ?>" <?php echo $jA['codigo_juego'] === $codigoAsimetricoSeleccionado ? 'selected' : ''; ?>>
                                <?php echo h($jA['nombre_visible']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit" class="btn btn-pequeno">Cambiar</button></noscript>
                </form>

                <p class="asimetrico-info">
                    <strong><?php echo h($asimetricoJuegoActual['nombre_visible']); ?></strong> —
                    <?php echo $asimetricoNumPersonajesPorJugador === 1 ? 'Cada jugador elige 1 personaje.' : 'Cada jugador elige 2 personajes.'; ?>
                </p>
            </section>

            <!-- B) Alta de partida asimétrica -->
            <section class="pergamino">
                <h2 class="seccion-titulo">Anotar Partida de <?php echo h($asimetricoJuegoActual['nombre_visible']); ?></h2>

                <?php if (!$asimetricoFormularioRender): ?>
                    <div class="alerta alerta-error">
                        No hay personajes activos suficientes para al menos 2 participantes
                        (se necesitan <?php echo (int)(2 * $asimetricoNumPersonajesPorJugador); ?> como mínimo).
                        Añade o activa personajes desde el panel de administración.
                    </div>
                <?php else: ?>
                    <form id="form-asimetrico" method="POST" action="<?php echo h($accionAsimetrica); ?>" class="form-partida">
                        <input type="hidden" name="accion" value="registrar_partida_asimetrica">
                        <input type="hidden" name="codigo_juego" value="<?php echo h($codigoAsimetricoSeleccionado); ?>">

                        <div class="grid-tres-columnas" style="align-items:end;">
                            <div class="grupo-form" style="margin-bottom:0;">
                                <label for="asimetrico_fecha_partida">Fecha y hora (opcional):</label>
                                <input type="datetime-local" id="asimetrico_fecha_partida" name="fecha_partida" class="input-taberna"
                                       value="<?php echo $asimetricoPostActivo ? h($_POST['fecha_partida'] ?? '') : date('Y-m-d\TH:i'); ?>">
                            </div>

                            <div class="grupo-form" style="margin-bottom:0;">
                                <label for="asimetrico_duracion">
                                    Duración (minutos) <?php echo $asimetricoDuracionObligatoria ? '(obligatoria)' : '(opcional)'; ?>:
                                </label>
                                <input type="number" id="asimetrico_duracion" name="duracion_minutos" class="input-taberna"
                                       min="1" max="1440" step="1"
                                       value="<?php echo $asimetricoPostActivo ? h($_POST['duracion_minutos'] ?? '') : ''; ?>"
                                       <?php echo $asimetricoDuracionObligatoria ? 'required' : ''; ?>>
                            </div>

                            <div class="grupo-form" style="margin-bottom:0;">
                                <label for="asimetrico_notas">Notas (opcional):</label>
                                <input type="text" id="asimetrico_notas" name="notas" class="input-taberna" maxlength="255"
                                       value="<?php echo $asimetricoPostActivo ? h($_POST['notas'] ?? '') : ''; ?>">
                            </div>
                        </div>

                        <h3 style="margin:18px 0 8px; color:#4a3219;">Participantes</h3>
                        <p style="font-style:italic; margin:0 0 10px; font-size:0.93rem;">
                            Mínimo 2 participantes. Exactamente un ganador. No se puede repetir un personaje en la misma partida;
                            en Tag Team cada jugador debe elegir 2 personajes distintos.
                        </p>

                        <div id="asimetrico-participantes" data-next-index="<?php echo count($asimetricoFilasRender); ?>">
                            <?php foreach ($asimetricoFilasRender as $i => $filaAsim): ?>
                                <div class="asimetrico-participante-fila <?php echo $asimetricoNumPersonajesPorJugador === 1 ? 'con-1-personaje' : 'con-2-personajes'; ?>" data-asimetrico-participante-row>
                                    <div class="grupo-form">
                                        <label>Jugador:</label>
                                        <select name="participantes[<?php echo $i; ?>][username]" class="select-taberna">
                                            <option value="">-- Jugador --</option>
                                            <?php foreach ($todasLasPersonas as $per): ?>
                                                <option value="<?php echo h($per['username']); ?>"
                                                    <?php echo ($filaAsim['username'] === $per['username']) ? 'selected' : ''; ?>>
                                                    <?php echo h($per['NombrePersona']); ?> (<?php echo h($per['username']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <?php for ($slot = 0; $slot < $asimetricoNumPersonajesPorJugador; $slot++): ?>
                                        <div class="grupo-form">
                                            <label><?php echo $asimetricoNumPersonajesPorJugador === 1 ? 'Personaje' : 'Personaje ' . ($slot + 1); ?>:</label>
                                            <select name="participantes[<?php echo $i; ?>][personajes][<?php echo $slot; ?>]" class="select-taberna">
                                                <option value="">-- Personaje --</option>
                                                <?php foreach ($asimetricoPersonajes as $pjAsim): ?>
                                                    <option value="<?php echo (int)$pjAsim['id_personaje']; ?>"
                                                        <?php echo ((string)($filaAsim['personajes'][$slot] ?? '') === (string)$pjAsim['id_personaje']) ? 'selected' : ''; ?>>
                                                        <?php echo h($pjAsim['nombre_personaje']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endfor; ?>

                                    <div class="grupo-form">
                                        <label style="display:flex; align-items:center; gap:6px;">
                                            <input type="checkbox" name="participantes[<?php echo $i; ?>][ha_ganado]" value="1"
                                                <?php echo (!empty($filaAsim['ha_ganado'])) ? 'checked' : ''; ?>>
                                            Ganador
                                        </label>
                                    </div>

                                    <div class="asimetrico-participante-acciones">
                                        <button type="button" class="btn btn-pequeno" data-asimetrico-eliminar>Quitar</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <template id="asimetrico-participante-template">
                            <div class="asimetrico-participante-fila <?php echo $asimetricoNumPersonajesPorJugador === 1 ? 'con-1-personaje' : 'con-2-personajes'; ?>" data-asimetrico-participante-row>
                                <div class="grupo-form">
                                    <label>Jugador:</label>
                                    <select name="participantes[__INDEX__][username]" class="select-taberna">
                                        <option value="">-- Jugador --</option>
                                        <?php foreach ($todasLasPersonas as $per): ?>
                                            <option value="<?php echo h($per['username']); ?>">
                                                <?php echo h($per['NombrePersona']); ?> (<?php echo h($per['username']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <?php for ($slot = 0; $slot < $asimetricoNumPersonajesPorJugador; $slot++): ?>
                                    <div class="grupo-form">
                                        <label><?php echo $asimetricoNumPersonajesPorJugador === 1 ? 'Personaje' : 'Personaje ' . ($slot + 1); ?>:</label>
                                        <select name="participantes[__INDEX__][personajes][<?php echo $slot; ?>]" class="select-taberna">
                                            <option value="">-- Personaje --</option>
                                            <?php foreach ($asimetricoPersonajes as $pjAsim): ?>
                                                <option value="<?php echo (int)$pjAsim['id_personaje']; ?>">
                                                    <?php echo h($pjAsim['nombre_personaje']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endfor; ?>

                                <div class="grupo-form">
                                    <label style="display:flex; align-items:center; gap:6px;">
                                        <input type="checkbox" name="participantes[__INDEX__][ha_ganado]" value="1">
                                        Ganador
                                    </label>
                                </div>

                                <div class="asimetrico-participante-acciones">
                                    <button type="button" class="btn btn-pequeno" data-asimetrico-eliminar>Quitar</button>
                                </div>
                            </div>
                        </template>

                        <div style="margin:10px 0 16px;">
                            <button type="button" id="asimetrico-agregar-participante" class="btn btn-bronce btn-pequeno">+ Añadir participante</button>
                        </div>

                        <button type="submit" class="btn btn-primario">Registrar Partida</button>
                    </form>
                <?php endif; ?>
            </section>

            <!-- C) Estadísticas asimétricas -->
            <section class="pergamino">
                <h2 class="seccion-titulo">Batallas de <?php echo h($asimetricoJuegoActual['nombre_visible']); ?></h2>

                <div class="grid-tres-columnas" style="margin-bottom:18px;">
                    <div class="estadistica-caja">
                        <span class="estadistica-numero"><?php echo (int)$asimetricoTotalPartidas; ?></span>
                        Partidas registradas
                    </div>
                    <div class="estadistica-caja">
                        <span class="estadistica-numero"><?php echo (int)$asimetricoTotalJugadores; ?></span>
                        Jugadores con partidas
                    </div>
                    <div class="estadistica-caja">
                        <span class="estadistica-numero"><?php echo (int)$asimetricoTotalPersonajes; ?></span>
                        Personajes activos
                    </div>
                </div>

                <?php if ($asimetricoTotalPartidas === 0): ?>
                    <p style="font-style:italic;">Todavía no hay partidas registradas para este juego.</p>
                <?php else: ?>
                    <div style="margin-top:18px; display:flex; flex-direction:column; gap:24px;">
                        <div>
                            <h3 style="margin:0 0 8px; color:#4a3219;">Ranking de Jugadores</h3>
                            <div class="tabla-contenedor tabla-asimetrica-scroll">
                                <table class="tabla-taberna">
                                    <thead>
                                        <tr>
                                            <th style="width:70px;">Puesto</th>
                                            <th>Jugador</th>
                                            <th style="width:80px;">Partidas</th>
                                            <th style="width:80px;">Victorias</th>
                                            <th style="width:100px;">% Victorias</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $puestoAsim = 1; ?>
                                        <?php foreach ($asimetricoRankingJugadores as $filaRA): ?>
                                            <?php
                                            $partidasRA = (int)$filaRA['partidas'];
                                            $victoriasRA = (int)$filaRA['victorias'];
                                            $porcentajeRA = $partidasRA > 0 ? round(($victoriasRA / $partidasRA) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td><strong>#<?php echo $puestoAsim++; ?></strong></td>
                                                <td>
                                                    <strong><?php echo h($filaRA['NombrePersona'] !== null && $filaRA['NombrePersona'] !== '' ? $filaRA['NombrePersona'] : $filaRA['username']); ?></strong>
                                                    (<?php echo h($filaRA['username']); ?>)
                                                </td>
                                                <td><?php echo $partidasRA; ?></td>
                                                <td><?php echo $victoriasRA; ?></td>
                                                <td><?php echo h((string)$porcentajeRA); ?> %</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div>
                            <h3 style="margin:0 0 8px; color:#4a3219;">Ranking de Personajes</h3>
                            <div class="tabla-contenedor tabla-asimetrica-scroll">
                                <table class="tabla-taberna">
                                    <thead>
                                        <tr>
                                            <th>Personaje</th>
                                            <th style="width:90px;">Apariciones</th>
                                            <th style="width:80px;">Victorias</th>
                                            <th style="width:100px;">% Victorias</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($asimetricoRankingPersonajes as $filaRP): ?>
                                            <?php
                                            $aparicionesRP = (int)$filaRP['apariciones'];
                                            $victoriasRP = (int)$filaRP['victorias'];
                                            $porcentajeRP = $aparicionesRP > 0 ? round(($victoriasRP / $aparicionesRP) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td><strong><?php echo h($filaRP['nombre_personaje']); ?></strong></td>
                                                <td><?php echo $aparicionesRP; ?></td>
                                                <td><?php echo $victoriasRP; ?></td>
                                                <td><?php echo h((string)$porcentajeRP); ?> %</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <!-- D) Historial de partidas asimétricas -->
            <section class="pergamino">
                <h2 class="seccion-titulo">Últimas Partidas de <?php echo h($asimetricoJuegoActual['nombre_visible']); ?></h2>

                <?php if (count($asimetricoPartidas) === 0): ?>
                    <p style="font-style:italic;">Todavía no hay partidas registradas para este juego.</p>
                <?php else: ?>
                    <div class="tabla-contenedor tabla-asimetrica-scroll">
                        <table class="tabla-taberna">
                            <thead>
                                <tr>
                                    <th style="width:150px;">Fecha</th>
                                    <th style="width:100px;">Duración</th>
                                    <th>Ganador</th>
                                    <th style="width:110px;">Detalles</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($asimetricoPartidas as $partidaAsim): ?>
                                    <?php
                                    $idAsim = (int)$partidaAsim['id_partida_asimetrica'];
                                    $participantesAsimFila = $asimetricoParticipantesPorPartida[$idAsim] ?? [];
                                    $nombresGanadoresAsim = [];
                                    foreach ($participantesAsimFila as $participanteAsim) {
                                        if ((int)$participanteAsim['ha_ganado'] === 1) {
                                            $nombresGanadoresAsim[] = $participanteAsim['NombrePersona'] !== null && $participanteAsim['NombrePersona'] !== ''
                                                ? $participanteAsim['NombrePersona']
                                                : $participanteAsim['username'];
                                        }
                                    }
                                    $textoGanadoresAsim = count($nombresGanadoresAsim) > 0 ? implode(', ', $nombresGanadoresAsim) : 'Sin ganador registrado';
                                    ?>
                                    <tr>
                                        <td><?php echo h((string)$partidaAsim['fecha_partida']); ?></td>
                                        <td><?php echo ($partidaAsim['duracion_minutos'] !== null && $partidaAsim['duracion_minutos'] !== '') ? (int)$partidaAsim['duracion_minutos'] . ' minutos' : 'No indicada'; ?></td>
                                        <td><?php echo h($textoGanadoresAsim); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-pequeno" data-toggle-panel="detalle-asim-<?php echo $idAsim; ?>" aria-expanded="false" aria-controls="detalle-asim-<?php echo $idAsim; ?>">
                                                Detalles
                                            </button>
                                        </td>
                                    </tr>
                                    <tr id="detalle-asim-<?php echo $idAsim; ?>" class="fila-detalle-partida panel-oculto">
                                        <td colspan="4">
                                            <div class="detalle-partida">
                                                <p><strong>Participantes:</strong></p>
                                                <div class="lista-participantes">
                                                    <?php if (count($participantesAsimFila) === 0): ?>
                                                        <span class="etiqueta etiqueta-bronce">Sin participantes registrados</span>
                                                    <?php else: ?>
                                                        <?php foreach ($participantesAsimFila as $participanteAsim): ?>
                                                            <span class="etiqueta <?php echo ((int)$participanteAsim['ha_ganado'] === 1) ? 'etiqueta-disponible' : 'etiqueta-bronce'; ?>">
                                                                <?php
                                                                echo h($participanteAsim['NombrePersona'] !== null && $participanteAsim['NombrePersona'] !== ''
                                                                    ? $participanteAsim['NombrePersona']
                                                                    : $participanteAsim['username']);
                                                                if (count($participanteAsim['personajes']) > 0) {
                                                                    echo ' — ' . h(implode(' + ', $participanteAsim['personajes']));
                                                                }
                                                                if ((int)$participanteAsim['ha_ganado'] === 1) {
                                                                    echo ' — Ganador';
                                                                }
                                                                ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <p>
                                                    <strong>Notas:</strong>
                                                    <?php echo ($partidaAsim['notas'] !== null && $partidaAsim['notas'] !== '') ? h((string)$partidaAsim['notas']) : 'Sin notas'; ?>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div><!-- /#panel-leaderboard-asimetricos -->
</main>

<script src="app.js?v=<?php echo @filemtime(__DIR__ . '/app.js') ?: time(); ?>"></script>
</body>
</html>
