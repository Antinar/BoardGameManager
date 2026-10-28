<?php
require_once 'conexion.php';
requerirLogin($pdo);

$mensajeExito = '';
$mensajeError = '';
$usuarioActual = $_SESSION['username'];
$escalaCalificaciones = obtenerEscalaCalificaciones();
$juegoSeleccionadoNombre = isset($_GET['juego']) ? trim($_GET['juego']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    // 1. Apuntarse o desapuntarse como interesado en un juego que NO tenemos
    if ($accion === 'toggle_interes') {
        $nombreJuego = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';
        if (isset($_POST['mantener_detalle']) && $_POST['mantener_detalle'] === '1') {
            $juegoSeleccionadoNombre = $nombreJuego;
        }

        $stmtJuego = $pdo->prepare("SELECT lo_tenemos FROM Juego WHERE NombreJuego = ?");
        $stmtJuego->execute([$nombreJuego]);
        $juegoRow = $stmtJuego->fetch();

        if (!$juegoRow) {
            $mensajeError = 'El juego seleccionado no existe.';
        } elseif ((int)$juegoRow['lo_tenemos'] === 1) {
            $mensajeError = 'Este juego ya lo tenemos en la taberna; solo puedes apuntarte como interesado a juegos que no tenemos.';
        } else {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Persona_Juego WHERE username = ? AND NombreJuego = ?");
            $stmtCheck->execute([$usuarioActual, $nombreJuego]);
            $yaApuntado = (int)$stmtCheck->fetchColumn() > 0;

            if ($yaApuntado) {
                $stmtDel = $pdo->prepare("DELETE FROM Persona_Juego WHERE username = ? AND NombreJuego = ?");
                $stmtDel->execute([$usuarioActual, $nombreJuego]);
                $mensajeExito = "Te has desapuntado de la lista de interesados en «{$nombreJuego}».";
            } else {
                $stmtAdd = $pdo->prepare("INSERT INTO Persona_Juego (username, NombreJuego) VALUES (?, ?)");
                $stmtAdd->execute([$usuarioActual, $nombreJuego]);
                $mensajeExito = "Te has apuntado como interesado en «{$nombreJuego}».";
            }
        }
    }

    // 2. Cualquier usuario puede cambiar el estado de "No lo tenemos" a "Ya lo tenemos" (o viceversa)
    elseif ($accion === 'cambiar_estado_tenencia') {
        $nombreJuego = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';
        $nuevoEstado = (isset($_POST['nuevo_estado']) && $_POST['nuevo_estado'] === '1') ? 1 : 0;

        $stmtUpd = $pdo->prepare("UPDATE Juego SET lo_tenemos = ? WHERE NombreJuego = ?");
        $stmtUpd->execute([$nuevoEstado, $nombreJuego]);

        if ($stmtUpd->rowCount() > 0) {
            if ($nuevoEstado === 1) {
                // Limpiar interesados previos ya que el juego se ha adquirido y volver a la cuadrícula
                $stmtLimpiar = $pdo->prepare("DELETE FROM Persona_Juego WHERE NombreJuego = ?");
                $stmtLimpiar->execute([$nombreJuego]);
                $juegoSeleccionadoNombre = '';
                $mensajeExito = "«{$nombreJuego}» marcado como 'Ya lo tenemos' y enviado a la Ludoteca.";
            } else {
                $stmtCleanProp = $pdo->prepare("DELETE FROM Juego_Propietario WHERE NombreJuego = ?");
                $stmtCleanProp->execute([$nombreJuego]);
                $mensajeExito = "«{$nombreJuego}» marcado como 'No lo tenemos'.";
            }
        }
    }

    // 3. Guardar valoración / comentario en un juego deseado (por si ya lo han jugado fuera o quieren dejar opinión previa)
    elseif ($accion === 'guardar_resenya') {
        $nombreJuego = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';
        $juegoSeleccionadoNombre = $nombreJuego;
        $haJugado = (isset($_POST['ha_jugado']) && $_POST['ha_jugado'] === '1') ? 1 : 0;
        $calificacionRaw = isset($_POST['calificacion']) ? trim((string)$_POST['calificacion']) : '';
        $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';
        $comentario = ($comentario !== '') ? $comentario : null;
        $calificacion = null;

        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Juego WHERE NombreJuego = ? AND lo_tenemos = 0");
        $stmtCheck->execute([$nombreJuego]);

        if ((int)$stmtCheck->fetchColumn() === 0) {
            $mensajeError = 'El juego indicado no existe o ya no está en la lista de deseados.';
        } else {
            if ($haJugado === 1) {
                $notaValida = filter_var($calificacionRaw, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0, 'max_range' => 10]
                ]);
                if ($notaValida === false || !array_key_exists((int)$notaValida, $escalaCalificaciones)) {
                    $mensajeError = 'Debes seleccionar una calificación válida del 0 al 10 si ya has probado el juego.';
                } else {
                    $calificacion = (int)$notaValida;
                }
            }

            if ($mensajeError === '') {
                $stmtUpsert = $pdo->prepare("
                    INSERT INTO Resenya (username, NombreJuego, ha_jugado, calificacion, comentario)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        ha_jugado = VALUES(ha_jugado),
                        calificacion = VALUES(calificacion),
                        comentario = VALUES(comentario)
                ");
                $stmtUpsert->execute([$usuarioActual, $nombreJuego, $haJugado, $calificacion, $comentario]);
                $mensajeExito = "Tu comentario o valoración sobre «{$nombreJuego}» se ha guardado correctamente.";
            }
        }
    }
}

// Obtener las personas interesadas en cada juego
$stmtInteresados = $pdo->query("
    SELECT pj.NombreJuego, p.username, p.NombrePersona
    FROM Persona_Juego pj
    INNER JOIN Persona p ON pj.username = p.username
    ORDER BY p.NombrePersona ASC
");
$mapaInteresados = [];
foreach ($stmtInteresados->fetchAll() as $fila) {
    $mapaInteresados[$fila['NombreJuego']][] = $fila;
}

// Comprobar si estamos viendo la ficha detallada de un juego deseado concreto
$juegoDetalle = null;
$listaResenyas = [];
$miRes = null;

if ($juegoSeleccionadoNombre !== '') {
    $stmtDetalle = $pdo->prepare("
        SELECT NombreJuego, Url, min_jugadores, max_jugadores, descripcion, reglas
        FROM Juego
        WHERE NombreJuego = ? AND lo_tenemos = 0
    ");
    $stmtDetalle->execute([$juegoSeleccionadoNombre]);
    $juegoDetalle = $stmtDetalle->fetch();

    if (!$juegoDetalle) {
        if ($mensajeExito === '') {
            $mensajeError = 'El juego solicitado no se encuentra en la lista de Juegos Deseados.';
        }
    } else {
        $stmtRes = $pdo->prepare("
            SELECT r.id_resenya, r.username, p.NombrePersona, r.NombreJuego, r.ha_jugado, r.calificacion, r.comentario, r.fecha_resenya
            FROM Resenya r
            INNER JOIN Persona p ON r.username = p.username
            WHERE r.NombreJuego = ?
            ORDER BY r.fecha_resenya DESC, p.NombrePersona ASC
        ");
        $stmtRes->execute([$juegoDetalle['NombreJuego']]);
        $listaResenyas = $stmtRes->fetchAll();

        foreach ($listaResenyas as $filaR) {
            if ($filaR['username'] === $usuarioActual) {
                $miRes = $filaR;
                break;
            }
        }
    }
}

if (!$juegoDetalle) {
    // Obtener ÚNICAMENTE los juegos que NO tenemos (lo_tenemos = 0) para la cuadrícula principal
    $stmtNoTenemos = $pdo->query("SELECT NombreJuego, Url, lo_tenemos, min_jugadores, max_jugadores FROM Juego WHERE lo_tenemos = 0 ORDER BY NombreJuego ASC");
    $juegosNoTenemos = $stmtNoTenemos->fetchAll();

    // Obtener lista de juegos que sí tenemos por si cualquier usuario necesita revertir su estado
    $stmtSiTenemos = $pdo->query("SELECT NombreJuego FROM Juego WHERE lo_tenemos = 1 ORDER BY NombreJuego ASC");
    $juegosSiTenemos = $stmtSiTenemos->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Juegos Deseados - La Taberna de las Gatitas Jugonas</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php renderizarNavegacion('juegos'); ?>

<main class="contenedor-principal">

    <?php if ($mensajeError !== ''): ?>
        <div class="alerta alerta-error"><?php echo h($mensajeError); ?></div>
    <?php endif; ?>

    <?php if ($mensajeExito !== ''): ?>
        <div class="alerta alerta-exito"><?php echo h($mensajeExito); ?></div>
    <?php endif; ?>

    <?php if ($juegoDetalle): ?>
        <!-- =================================================================
             VISTA DE DETALLE DEL JUEGO DESEADO (INTERESADOS + DETALLES + RESEÑAS)
             ================================================================= -->
        <?php
        $nombreJ = $juegoDetalle['NombreJuego'];
        $hashJ = md5($nombreJ);
        $listaIntDetalle = isset($mapaInteresados[$nombreJ]) ? $mapaInteresados[$nombreJ] : [];
        $estoyApuntadoDetalle = false;
        foreach ($listaIntDetalle as $intU) {
            if ($intU['username'] === $usuarioActual) {
                $estoyApuntadoDetalle = true;
                break;
            }
        }
        $heJugado = ($miRes && (int)$miRes['ha_jugado'] === 1);
        $miCalificacion = $miRes ? (string)$miRes['calificacion'] : '';
        $miComentario = $miRes ? (string)$miRes['comentario'] : '';
        ?>
        <section class="pergamino">
            <div class="barra-titulo-filtro" style="border-bottom: 2px solid #4a3219; padding-bottom: 10px; margin-bottom: 18px;">
                <h2 class="seccion-titulo" style="margin-bottom:0; border-bottom:none; padding-bottom:0;">
                    <?php echo h($nombreJ); ?>
                </h2>
                <a href="juegos.php" class="btn btn-bronce btn-pequeno">&larr; Volver a Juegos Deseados</a>
            </div>

            <div class="ludoteca-detalle-superior">
                <div class="ludoteca-detalle-imagen">
                    <img src="<?php echo h($juegoDetalle['Url']); ?>" alt="<?php echo h($nombreJ); ?>">
                </div>

                <!-- A la derecha de la foto: Jugadores, Acciones rápidas, Miembros Interesados, Descripción y Reglas -->
                <div class="pergamino-secundario" style="margin-bottom: 0;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border-bottom:1px solid #4a3219; padding-bottom:10px; margin-bottom:12px;">
                        <div>
                            <span class="etiqueta etiqueta-bronce" style="font-size:0.95rem;">
                                Jugadores: <?php echo (int)$juegoDetalle['min_jugadores']; ?> a <?php echo (int)$juegoDetalle['max_jugadores']; ?>
                            </span>
                        </div>

                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <form method="POST" action="juegos.php?juego=<?php echo urlencode($nombreJ); ?>" style="margin:0;">
                                <input type="hidden" name="accion" value="toggle_interes">
                                <input type="hidden" name="NombreJuego" value="<?php echo h($nombreJ); ?>">
                                <input type="hidden" name="mantener_detalle" value="1">
                                <?php if ($estoyApuntadoDetalle): ?>
                                    <button type="submit" class="btn btn-peligro btn-pequeno">
                                        Desapuntarme del Juego
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-exito btn-pequeno">
                                        + Apuntarme como Interesado
                                    </button>
                                <?php endif; ?>
                            </form>

                            <form method="POST" action="juegos.php" style="margin:0;">
                                <input type="hidden" name="accion" value="cambiar_estado_tenencia">
                                <input type="hidden" name="NombreJuego" value="<?php echo h($nombreJ); ?>">
                                <input type="hidden" name="nuevo_estado" value="1">
                                <button type="submit" class="btn btn-bronce btn-pequeno">
                                    Pasar a «Ya lo tenemos»
                                </button>
                            </form>
                        </div>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <strong>Miembros Interesados en «<?php echo h($nombreJ); ?>» (<?php echo count($listaIntDetalle); ?>):</strong>
                        <?php if (count($listaIntDetalle) === 0): ?>
                            <em style="color:#5a4634;">Nadie se ha apuntado todavía como interesado.</em>
                        <?php else: ?>
                            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;">
                                <?php foreach ($listaIntDetalle as $intU): ?>
                                    <span class="etiqueta etiqueta-disponible">
                                        <?php echo h($intU['NombrePersona']); ?> (<?php echo h($intU['username']); ?>)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <strong>Descripción:</strong>
                        <?php if (!empty($juegoDetalle['descripcion'])): ?>
                            <div class="texto-largo-juego"><?php echo nl2br(h($juegoDetalle['descripcion'])); ?></div>
                        <?php else: ?>
                            <p style="font-style:italic; color:#5a4634; margin-top:4px;">Sin descripción registrada.</p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <strong>Reglas / Notas de partida:</strong>
                        <?php if (!empty($juegoDetalle['reglas'])): ?>
                            <div class="texto-largo-juego"><?php echo nl2br(h($juegoDetalle['reglas'])); ?></div>
                        <?php else: ?>
                            <p style="font-style:italic; color:#5a4634; margin-top:4px;">Sin reglas especificadas.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Justo debajo: Formulario de Comentarios / Valoración Personal (por si ya lo han jugado o quieren opinar) -->
            <form method="POST" action="juegos.php?juego=<?php echo urlencode($nombreJ); ?>" class="pergamino-secundario form-ludoteca" style="margin-top: 18px;">
                <h3 class="seccion-subtitulo">Tu Comentario o Valoración Personal</h3>
                <input type="hidden" name="accion" value="guardar_resenya">
                <input type="hidden" name="NombreJuego" value="<?php echo h($nombreJ); ?>">

                <div class="grupo-form">
                    <label>¿Ya lo has probado o jugado antes?</label>
                    <div class="radio-grupo">
                        <label class="radio-item">
                            <input type="radio" name="ha_jugado" value="1"
                                   data-toggle-jugado="bloque_opinion_deseado_<?php echo $hashJ; ?>"
                                   <?php echo $heJugado ? 'checked' : ''; ?>>
                            Sí, ya lo he jugado
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="ha_jugado" value="0"
                                   data-toggle-jugado="bloque_opinion_deseado_<?php echo $hashJ; ?>"
                                   <?php echo !$heJugado ? 'checked' : ''; ?>>
                            No lo he jugado (Opinión previa)
                        </label>
                    </div>
                </div>

                <div id="bloque_opinion_deseado_<?php echo $hashJ; ?>" class="bloque-opinion-ludoteca" style="<?php echo $heJugado ? '' : 'display:none;'; ?>">
                    <div class="grupo-form">
                        <label for="cal_des_<?php echo $hashJ; ?>">¿Cuánto te gustó? (0 a 10):</label>
                        <select id="cal_des_<?php echo $hashJ; ?>" name="calificacion" class="select-taberna" <?php echo $heJugado ? 'required' : 'disabled'; ?>>
                            <option value="">-- Selecciona del 0 al 10 --</option>
                            <?php foreach ($escalaCalificaciones as $num => $textoEscala): ?>
                                <option value="<?php echo (int)$num; ?>" <?php echo ($miCalificacion !== '' && (int)$miCalificacion === (int)$num) ? 'selected' : ''; ?>>
                                    <?php echo (int)$num . ' - ' . h($textoEscala); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grupo-form">
                    <label for="com_des_<?php echo $hashJ; ?>">Tu comentario u opinión previa (opcional):</label>
                    <textarea id="com_des_<?php echo $hashJ; ?>" name="comentario" class="input-taberna" rows="3" placeholder="Escribe por qué te llama la atención o qué te pareció si ya lo has probado..."><?php echo h($miComentario); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primario">
                    Guardar Comentario / Valoración
                </button>
            </form>
        </section>

        <section class="pergamino">
            <h3 class="seccion-titulo">Comentarios y Reseñas sobre «<?php echo h($nombreJ); ?>» (<?php echo count($listaResenyas); ?>)</h3>

            <?php if (count($listaResenyas) === 0): ?>
                <p style="font-style:italic;">Nadie ha dejado comentarios ni valoraciones sobre este juego todavía.</p>
            <?php else: ?>
                <div class="tabla-contenedor">
                    <table class="tabla-taberna">
                        <thead>
                            <tr>
                                <th style="width:200px;">Jugador</th>
                                <th style="width:120px;">¿Jugado?</th>
                                <th style="width:220px;">Calificación (0-10)</th>
                                <th>Opinión / Comentario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listaResenyas as $res): ?>
                                <?php
                                $jugo = (int)$res['ha_jugado'] === 1;
                                $tieneNota = ($jugo && $res['calificacion'] !== null && $res['calificacion'] !== '');
                                $notaInt = $tieneNota ? (int)$res['calificacion'] : null;
                                $textoNota = ($notaInt !== null && isset($escalaCalificaciones[$notaInt])) ? $escalaCalificaciones[$notaInt] : '';
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo h($res['NombrePersona']); ?></strong>
                                        (<?php echo h($res['username']); ?>)
                                    </td>
                                    <td>
                                        <?php if ($jugo): ?>
                                            <span class="etiqueta etiqueta-disponible">Sí</span>
                                        <?php else: ?>
                                            <span class="etiqueta etiqueta-pendiente">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($tieneNota): ?>
                                            <span class="etiqueta etiqueta-bronce"><?php echo (int)$notaInt . '/10 — ' . h($textoNota); ?></span>
                                        <?php else: ?>
                                            <span class="etiqueta">Opinión previa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($res['comentario'] !== null && trim((string)$res['comentario']) !== ''): ?>
                                            <?php echo nl2br(h($res['comentario'])); ?>
                                        <?php else: ?>
                                            <span style="font-style:italic; color:#5a4632;">Sin comentario</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    <?php else: ?>
        <!-- =================================================================
             VISTA PRINCIPAL EN CUADRÍCULA DE JUEGOS DESEADOS
             ================================================================= -->
        <section class="pergamino">
            <div class="barra-titulo-filtro">
                <h2 class="seccion-titulo" style="margin-bottom:0; border-bottom:none;">
                    Juegos que NO Tenemos (<?php echo count($juegosNoTenemos); ?>)
                </h2>

                <div class="controles-ludoteca">
                    <?php renderizarBuscador('Buscar juego deseado...', ['id' => 'buscador-deseados']); ?>
                </div>
            </div>

            <?php renderizarPanelAvanzadoFiltros('deseados', 'contenedor-deseados', 'buscador-deseados', 'deseados-sin-resultados'); ?>

            <p style="margin-bottom: 14px;">
                Lista de juegos de interés para el grupo. <strong>Haz clic sobre la imagen o el título de cualquier juego</strong> para ver sus detalles, quiénes están interesados y dejar tus comentarios, o usa los botones inferiores de cada tarjeta.
            </p>

            <?php if (count($juegosNoTenemos) === 0): ?>
                <p style="font-style:italic; padding:12px 0;">
                    No hay juegos pendientes en la lista de deseados. Registra uno en la pestaña de «Subir Juego».
                </p>
            <?php else: ?>
                <div class="grid-ludoteca" id="contenedor-deseados">
                    <?php foreach ($juegosNoTenemos as $juego): ?>
                        <?php
                        $nombreJ = $juego['NombreJuego'];
                        $listaInt = isset($mapaInteresados[$nombreJ]) ? $mapaInteresados[$nombreJ] : [];
                        $numInteresados = count($listaInt);
                        $estoyApuntado = false;
                        foreach ($listaInt as $intUser) {
                            if ($intUser['username'] === $usuarioActual) {
                                $estoyApuntado = true;
                                break;
                            }
                        }
                        ?>
                        <article class="tarjeta-ludoteca"
                                 style="cursor:default;"
                                 data-nombre="<?php echo h($nombreJ); ?>"
                                 data-interesado="<?php echo $estoyApuntado ? '1' : '0'; ?>"
                                 data-num-interesados="<?php echo (int)$numInteresados; ?>"
                                 data-min-jugadores="<?php echo (int)$juego['min_jugadores']; ?>"
                                 data-max-jugadores="<?php echo (int)$juego['max_jugadores']; ?>">

                            <a href="juegos.php?juego=<?php echo urlencode($nombreJ); ?>"
                               style="text-decoration:none; color:inherit; display:flex; flex-direction:column; gap:10px;">
                                <h3 class="tarjeta-ludoteca-titulo"><?php echo h($nombreJ); ?></h3>

                                <div class="tarjeta-ludoteca-img-contenedor">
                                    <img src="<?php echo h($juego['Url']); ?>" alt="<?php echo h($nombreJ); ?>">
                                    <span class="insignia-nota-media <?php echo $numInteresados > 0 ? 'nota-verde-claro' : 'nota-gris'; ?>"
                                          title="Miembros interesados: <?php echo (int)$numInteresados; ?>">
                                        <?php echo (int)$numInteresados; ?>
                                    </span>
                                </div>
                            </a>

                            <div style="display:flex; flex-direction:column; gap:6px; margin-top:auto;">
                                <form method="POST" action="juegos.php" style="margin:0;">
                                    <input type="hidden" name="accion" value="toggle_interes">
                                    <input type="hidden" name="NombreJuego" value="<?php echo h($nombreJ); ?>">
                                    <?php if ($estoyApuntado): ?>
                                        <button type="submit" class="btn btn-peligro btn-pequeno" style="width:100%;">
                                            Desapuntarme
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-exito btn-pequeno" style="width:100%;">
                                            Apuntarme como Interesado
                                        </button>
                                    <?php endif; ?>
                                </form>

                                <form method="POST" action="juegos.php" style="margin:0;">
                                    <input type="hidden" name="accion" value="cambiar_estado_tenencia">
                                    <input type="hidden" name="NombreJuego" value="<?php echo h($nombreJ); ?>">
                                    <input type="hidden" name="nuevo_estado" value="1">
                                    <button type="submit" class="btn btn-bronce btn-pequeno" style="width:100%;">
                                        Pasar a «Ya lo tenemos»
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <p id="deseados-sin-resultados" style="display:none; font-style:italic; margin-top:16px; text-align:center;">
                    No se encontraron juegos deseados que coincidan con los filtros seleccionados.
                </p>
            <?php endif; ?>
        </section>

        <section class="pergamino">
            <h2 class="seccion-titulo">Poner juego en interes</h2>
            <p>
                Si algun juego que tenemos por algun motivo queremos que esté en interesado en vez, se puede <strong>cambiar</strong> desde aqui.
            </p>

            <?php if (count($juegosSiTenemos) > 0): ?>
                <form method="POST" action="juegos.php" class="form-inline-movil" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <input type="hidden" name="accion" value="cambiar_estado_tenencia">
                    <input type="hidden" name="nuevo_estado" value="0">
                    <label for="juego_revertir" style="font-weight:bold;">Pasar a «No lo tenemos»:</label>
                    <select id="juego_revertir" name="NombreJuego" class="select-taberna" style="width:auto;" required>
                        <?php foreach ($juegosSiTenemos as $jst): ?>
                            <option value="<?php echo h($jst['NombreJuego']); ?>"><?php echo h($jst['NombreJuego']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-bronce btn-pequeno">Cambiar</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

</main>

<script src="app.js?v=<?php echo @filemtime(__DIR__ . '/app.js') ?: time(); ?>"></script>
</body>
</html>