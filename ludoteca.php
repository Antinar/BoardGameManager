<?php
require_once 'conexion.php';
requerirLogin($pdo);

$mensajeExito = '';
$mensajeError = '';
$usuarioActual = $_SESSION['username'];

// Mapa oficial de calificaciones (1 a 5 -> valores exactos del ENUM en migration_ludoteca.sql)
$escalaCalificaciones = [
    1 => 'No me gustó',
    2 => 'Meh',
    3 => 'Está Bien',
    4 => 'Me gustó bastante',
    5 => '¿Pa cuando otra?'
];
$valoresEnumValidos = array_values($escalaCalificaciones);

// Juego seleccionado por GET (si el usuario ha clicado en un cuadrado)
$juegoSeleccionadoNombre = isset($_GET['juego']) ? trim($_GET['juego']) : '';

// Procesar guardado o actualización de reseña del usuario actual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_resenya') {
    $nombreJuego = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';
    $juegoSeleccionadoNombre = $nombreJuego; // Mantener en la vista de detalle del juego tras guardar
    $haJugado = (isset($_POST['ha_jugado']) && $_POST['ha_jugado'] === '1') ? 1 : 0;
    $calificacion = isset($_POST['calificacion']) ? trim($_POST['calificacion']) : null;
    $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : null;

    // Verificar que el juego exista y esté en la taberna (lo_tenemos = 1)
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Juego WHERE NombreJuego = ? AND lo_tenemos = 1");
    $stmtCheck->execute([$nombreJuego]);

    if ((int)$stmtCheck->fetchColumn() === 0) {
        $mensajeError = 'El juego indicado no existe o no forma parte de la ludoteca actual.';
    } else {
        if ($haJugado === 0) {
            $calificacion = null;
            $comentario = null;
        } else {
            if ($calificacion === '' || !in_array($calificacion, $valoresEnumValidos, true)) {
                $mensajeError = 'Debes seleccionar una calificación válida del 1 al 5 si has jugado al juego.';
            }
            if ($comentario === '') {
                $comentario = null;
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
            $mensajeExito = "Tu valoración sobre «{$nombreJuego}» se ha guardado correctamente.";
        }
    }
}

// Procesar al momento el botón de "Yo tengo este juego" / "Ya no lo tengo"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'toggle_tenencia_propia') {
    $nombreJuego = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';
    $juegoSeleccionadoNombre = $nombreJuego;

    $stmtCheckJ = $pdo->prepare("SELECT COUNT(*) FROM Juego WHERE NombreJuego = ? AND lo_tenemos = 1");
    $stmtCheckJ->execute([$nombreJuego]);
    if ((int)$stmtCheckJ->fetchColumn() > 0) {
        $stmtTengo = $pdo->prepare("SELECT COUNT(*) FROM Juego_Propietario WHERE username = ? AND NombreJuego = ?");
        $stmtTengo->execute([$usuarioActual, $nombreJuego]);

        if ((int)$stmtTengo->fetchColumn() > 0) {
            $stmtDelProp = $pdo->prepare("DELETE FROM Juego_Propietario WHERE username = ? AND NombreJuego = ?");
            $stmtDelProp->execute([$usuarioActual, $nombreJuego]);
            $mensajeExito = "Te has quitado de la lista de propietarios de «{$nombreJuego}».";
        } else {
            $stmtAddProp = $pdo->prepare("INSERT INTO Juego_Propietario (username, NombreJuego) VALUES (?, ?)");
            $stmtAddProp->execute([$usuarioActual, $nombreJuego]);
            $mensajeExito = "Has indicado que tienes el juego «{$nombreJuego}».";
        }
    }
}

// Comprobar si estamos viendo la ficha detallada de un juego concreto
$juegoDetalle = null;
$propietariosJuego = [];
$yoLoTengo = false;

if ($juegoSeleccionadoNombre !== '') {
    $stmtDetalle = $pdo->prepare("
        SELECT NombreJuego, Url, min_jugadores, max_jugadores, descripcion, reglas
        FROM Juego
        WHERE NombreJuego = ? AND lo_tenemos = 1
    ");
    $stmtDetalle->execute([$juegoSeleccionadoNombre]);
    $juegoDetalle = $stmtDetalle->fetch();

    if (!$juegoDetalle) {
        $mensajeError = 'El juego solicitado no se encuentra en la Ludoteca.';
    } else {
        $stmtProps = $pdo->prepare("
            SELECT jp.username, p.NombrePersona
            FROM Juego_Propietario jp
            INNER JOIN Persona p ON jp.username = p.username
            WHERE jp.NombreJuego = ?
            ORDER BY p.NombrePersona ASC
        ");
        $stmtProps->execute([$juegoDetalle['NombreJuego']]);
        $propietariosJuego = $stmtProps->fetchAll();

        foreach ($propietariosJuego as $prop) {
            if ($prop['username'] === $usuarioActual) {
                $yoLoTengo = true;
                break;
            }
        }
    }
}

// Si estamos en la ficha de un juego, cargamos sus reseñas; si no, cargamos la cuadrícula y las estadísticas
if ($juegoDetalle) {
    $stmtRes = $pdo->prepare("
        SELECT r.id_resenya, r.username, p.NombrePersona, r.NombreJuego, r.ha_jugado, r.calificacion, r.comentario, r.fecha_resenya
        FROM Resenya r
        INNER JOIN Persona p ON r.username = p.username
        WHERE r.NombreJuego = ?
        ORDER BY r.fecha_resenya DESC, p.NombrePersona ASC
    ");
    $stmtRes->execute([$juegoDetalle['NombreJuego']]);
    $listaResenyas = $stmtRes->fetchAll();

    $miRes = null;
    foreach ($listaResenyas as $filaR) {
        if ($filaR['username'] === $usuarioActual) {
            $miRes = $filaR;
            break;
        }
    }
} else {
    $stmtJuegos = $pdo->query("SELECT NombreJuego, Url FROM Juego WHERE lo_tenemos = 1 ORDER BY NombreJuego ASC");
    $juegosLudoteca = $stmtJuegos->fetchAll();

    // Obtener todas las reseñas de los juegos de la ludoteca para calcular medias y el estado del usuario actual
    $stmtTodasRes = $pdo->query("
        SELECT r.username, r.NombreJuego, r.ha_jugado, r.calificacion
        FROM Resenya r
        INNER JOIN Juego j ON r.NombreJuego = j.NombreJuego AND j.lo_tenemos = 1
    ");

    $sumaNotasPorJuego = [];
    $totalNotasPorJuego = [];
    $miResenyaPorJuego = [];

    foreach ($stmtTodasRes->fetchAll() as $filaR) {
        $nJ = $filaR['NombreJuego'];
        $jugo = (int)$filaR['ha_jugado'] === 1;
        $numNota = array_search($filaR['calificacion'], $escalaCalificaciones, true);

        // Solo cuentan para la media las reseñas jugadas que tengan calificación válida (1 a 5)
        if ($jugo && $numNota !== false) {
            if (!isset($sumaNotasPorJuego[$nJ])) {
                $sumaNotasPorJuego[$nJ] = 0;
                $totalNotasPorJuego[$nJ] = 0;
            }
            $sumaNotasPorJuego[$nJ] += (int)$numNota;
            $totalNotasPorJuego[$nJ]++;
        }

        if ($filaR['username'] === $usuarioActual) {
            $miResenyaPorJuego[$nJ] = [
                'ha_jugado' => $jugo ? 1 : 0,
                'nota_num'  => ($numNota !== false) ? (int)$numNota : null
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ludoteca - La Taberna de las Gatitas Jugonas</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php renderizarNavegacion('ludoteca'); ?>

<main class="contenedor-principal">
    <?php if ($mensajeError !== ''): ?>
        <div class="alerta alerta-error"><?php echo h($mensajeError); ?></div>
    <?php endif; ?>

    <?php if ($mensajeExito !== ''): ?>
        <div class="alerta alerta-exito"><?php echo h($mensajeExito); ?></div>
    <?php endif; ?>

    <?php if ($juegoDetalle): ?>
        <!-- =================================================================
             VISTA DE DETALLE DEL JUEGO (FORMULARIO + TABLA DE RESEÑAS)
             ================================================================= -->
        <?php
        $nombreJ = $juegoDetalle['NombreJuego'];
        $hashJ = md5($nombreJ);
        $heJugado = ($miRes && (int)$miRes['ha_jugado'] === 1);
        $miCalificacion = $miRes ? (string)$miRes['calificacion'] : '';
        $miComentario = $miRes ? (string)$miRes['comentario'] : '';
        ?>
        <section class="pergamino">
            <div class="barra-titulo-filtro" style="border-bottom: 2px solid #4a3219; padding-bottom: 10px; margin-bottom: 18px;">
                <h2 class="seccion-titulo" style="margin-bottom:0; border-bottom:none; padding-bottom:0;">
                    <?php echo h($nombreJ); ?>
                </h2>
                <a href="ludoteca.php" class="btn btn-bronce btn-pequeno">&larr; Volver a la Ludoteca</a>
            </div>

            <div class="ludoteca-detalle-superior">
                <div class="ludoteca-detalle-imagen">
                    <img src="<?php echo h($juegoDetalle['Url']); ?>" alt="<?php echo h($nombreJ); ?>">
                </div>

                <form method="POST" action="ludoteca.php?juego=<?php echo urlencode($nombreJ); ?>" class="pergamino-secundario form-ludoteca">
                    <h3 class="seccion-subtitulo">Tu Valoración Personal</h3>
                    <input type="hidden" name="accion" value="guardar_resenya">
                    <input type="hidden" name="NombreJuego" value="<?php echo h($nombreJ); ?>">

                    <div class="grupo-form">
                        <label>¿Lo has jugado?</label>
                        <div class="radio-grupo">
                            <label class="radio-item">
                                <input type="radio" name="ha_jugado" value="1"
                                       data-toggle-jugado="bloque_opinion_<?php echo $hashJ; ?>"
                                       <?php echo $heJugado ? 'checked' : ''; ?>>
                                Sí, he jugado
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="ha_jugado" value="0"
                                       data-toggle-jugado="bloque_opinion_<?php echo $hashJ; ?>"
                                       <?php echo !$heJugado ? 'checked' : ''; ?>>
                                No lo he jugado
                            </label>
                        </div>
                    </div>

                    <div id="bloque_opinion_<?php echo $hashJ; ?>" class="bloque-opinion-ludoteca" style="<?php echo $heJugado ? '' : 'display:none;'; ?>">
                        <div class="grupo-form">
                            <label for="cal_<?php echo $hashJ; ?>">¿Cuánto te ha gustado? (1 a 5):</label>
                            <select id="cal_<?php echo $hashJ; ?>" name="calificacion" class="select-taberna" <?php echo $heJugado ? 'required' : ''; ?>>
                                <option value="">-- Selecciona del 1 al 5 --</option>
                                <?php foreach ($escalaCalificaciones as $num => $textoEnum): ?>
                                    <option value="<?php echo h($textoEnum); ?>" <?php echo ($miCalificacion === $textoEnum) ? 'selected' : ''; ?>>
                                        <?php echo $num . ' - ' . h($textoEnum); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grupo-form">
                            <label for="com_<?php echo $hashJ; ?>">Tu opinión (opcional):</label>
                            <textarea id="com_<?php echo $hashJ; ?>" name="comentario" class="input-taberna" rows="3" placeholder="Escribe qué te pareció el juego..."><?php echo h($miComentario); ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primario">
                    Guardar mi Valoración
                </button>
            </form>
        </div>

        <!-- Información técnica del juego y quiénes lo tienen -->
        <div class="pergamino-secundario" style="margin-top: 18px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-bottom:1px solid #4a3219; padding-bottom:10px; margin-bottom:12px;">
                <div>
                    <span class="etiqueta etiqueta-bronce" style="font-size:0.95rem;">
                        Jugadores: <?php echo (int)$juegoDetalle['min_jugadores']; ?> a <?php echo (int)$juegoDetalle['max_jugadores']; ?>
                    </span>
                </div>

                <form method="POST" action="ludoteca.php?juego=<?php echo urlencode($nombreJ); ?>" style="margin:0;">
                    <input type="hidden" name="accion" value="toggle_tenencia_propia">
                    <input type="hidden" name="NombreJuego" value="<?php echo h($nombreJ); ?>">
                    <?php if ($yoLoTengo): ?>
                        <button type="submit" class="btn btn-peligro btn-pequeno">
                            Quitarme de propietarios (Ya no lo tengo)
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-exito btn-pequeno">
                            + Yo tengo este juego
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <div style="margin-bottom: 12px;">
                <strong>Parroquianos que tienen «<?php echo h($nombreJ); ?>»:</strong>
                <?php if (count($propietariosJuego) === 0): ?>
                    <em style="color:#5a4634;">Nadie se ha anotado todavía como propietario de este ejemplar.</em>
                <?php else: ?>
                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;">
                        <?php foreach ($propietariosJuego as $prop): ?>
                            <span class="etiqueta etiqueta-disponible">
                                <?php echo h($prop['NombrePersona']); ?> (<?php echo h($prop['username']); ?>)
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($juegoDetalle['descripcion'])): ?>
                <div style="margin-bottom: 12px;">
                    <strong>Descripción:</strong>
                    <div class="texto-largo-juego"><?php echo nl2br(h($juegoDetalle['descripcion'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($juegoDetalle['reglas'])): ?>
                <div>
                    <strong>Reglas / Notas de partida:</strong>
                    <div class="texto-largo-juego"><?php echo nl2br(h($juegoDetalle['reglas'])); ?></div>
                </div>
            <?php endif; ?>
        </div>
    </section>

        <section class="pergamino">
            <h3 class="seccion-titulo">Reseñas de la Taberna para «<?php echo h($nombreJ); ?>» (<?php echo count($listaResenyas); ?>)</h3>

            <?php if (count($listaResenyas) === 0): ?>
                <p style="font-style:italic;">Nadie ha registrado aún si ha jugado a este juego.</p>
            <?php else: ?>
                <div class="tabla-contenedor">
                    <table class="tabla-taberna">
                        <thead>
                            <tr>
                                <th style="width:200px;">Jugador</th>
                                <th style="width:120px;">¿Jugado?</th>
                                <th style="width:220px;">Calificación (1-5)</th>
                                <th>Opinión</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listaResenyas as $res): ?>
                                <?php
                                $jugo = (int)$res['ha_jugado'] === 1;
                                $numEscala = array_search($res['calificacion'], $escalaCalificaciones, true);
                                $etiquetaNota = ($numEscala !== false) ? ($numEscala . '/5 — ' . $res['calificacion']) : '—';
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
                                        <?php if ($jugo && $res['calificacion'] !== null): ?>
                                            <span class="etiqueta etiqueta-bronce"><?php echo h($etiquetaNota); ?></span>
                                        <?php else: ?>
                                            <span>—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($jugo && $res['comentario'] !== null && trim($res['comentario']) !== ''): ?>
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
             VISTA PRINCIPAL DE CUADRÍCULA DE LA LUDOTECA
             ================================================================= -->
        <section class="pergamino">
            <div class="barra-titulo-filtro">
                <h2 class="seccion-titulo" style="margin-bottom:0; border-bottom:none;">
                    Ludoteca de la Taberna (<?php echo count($juegosLudoteca); ?>)
                </h2>

                <div class="controles-ludoteca">
                    <select id="filtro-estado-ludoteca" class="select-taberna">
                        <option value="todos">Todos los juegos</option>
                        <option value="jugado">Jugados por mí</option>
                        <option value="no_jugado">No jugados por mí</option>
                        <option value="no_resenyado">No reseñados por mí</option>
                    </select>

                    <select id="orden-ludoteca" class="select-taberna">
                        <option value="alfabetico">Orden: Alfabético (A - Z)</option>
                        <option value="preferencias">Orden: Preferencias (Nota media)</option>
                    </select>

                    <?php renderizarBuscador('Buscar juego por nombre...', ['id' => 'buscador-ludoteca']); ?>
                </div>
            </div>

            <p style="margin-bottom: 14px;">
                Catálogo de juegos disponibles en la taberna. <strong>Haz clic sobre cualquier juego</strong> para abrir su ficha, indicar si lo has jugado, puntuarlo del 1 al 5 y consultar las reseñas del resto del grupo.
            </p>

            <?php if (count($juegosLudoteca) === 0): ?>
                <p style="font-style:italic; margin-top:12px;">
                    Todavía no hay juegos marcados como «Ya lo tenemos» en la taberna.
                </p>
            <?php else: ?>
                <div class="grid-ludoteca" id="contenedor-ludoteca">
                    <?php foreach ($juegosLudoteca as $juego): ?>
                        <?php
                        $nombreJ = $juego['NombreJuego'];

                        // 1. Calcular nota media del juego (máximo 2 decimales, sin contar los no votados)
                        $tieneMedia = isset($totalNotasPorJuego[$nombreJ]) && $totalNotasPorJuego[$nombreJ] > 0;
                        if ($tieneMedia) {
                            $mediaRedondeada = round($sumaNotasPorJuego[$nombreJ] / $totalNotasPorJuego[$nombreJ], 2);
                            // Formatear con hasta 2 decimales eliminando ceros innecesarios (ej: 4.50 -> 4.5, 4.00 -> 4)
                            $textoMedia = rtrim(rtrim(number_format($mediaRedondeada, 2, '.', ''), '0'), '.');
                            $valorOrdenMedia = $mediaRedondeada;

                            if ($mediaRedondeada >= 4.5) {
                                $claseColorMedia = 'nota-verde-oscuro';
                            } elseif ($mediaRedondeada >= 3.5) {
                                $claseColorMedia = 'nota-verde-claro';
                            } elseif ($mediaRedondeada >= 2.5) {
                                $claseColorMedia = 'nota-amarillo';
                            } else {
                                $claseColorMedia = 'nota-rojo';
                            }
                        } else {
                            $textoMedia = '-';
                            $valorOrdenMedia = -1; // Para que al ordenar por preferencias quede debajo del todo
                            $claseColorMedia = 'nota-gris';
                        }

                        // 2. Estado personal del usuario actual en este juego
                        if (!isset($miResenyaPorJuego[$nombreJ])) {
                            $miEstadoFiltro = 'no_resenyado';
                            $textoMiEstado = 'No reseñado';
                            $claseMiEstado = 'estado-no-resenyado';
                        } elseif ($miResenyaPorJuego[$nombreJ]['ha_jugado'] === 1) {
                            $miEstadoFiltro = 'jugado';
                            $notaPropia = $miResenyaPorJuego[$nombreJ]['nota_num'];
                            $textoMiEstado = 'Jugado - ' . ($notaPropia !== null ? $notaPropia : '?') . '/5';
                            $claseMiEstado = 'estado-jugado';
                        } else {
                            $miEstadoFiltro = 'no_jugado';
                            $textoMiEstado = 'No jugado';
                            $claseMiEstado = 'estado-no-jugado';
                        }
                        ?>
                        <a href="ludoteca.php?juego=<?php echo urlencode($nombreJ); ?>"
                           class="tarjeta-ludoteca"
                           data-nombre="<?php echo h($nombreJ); ?>"
                           data-mi-estado="<?php echo h($miEstadoFiltro); ?>"
                           data-nota-media="<?php echo h($valorOrdenMedia); ?>">

                            <h3 class="tarjeta-ludoteca-titulo"><?php echo h($nombreJ); ?></h3>

                            <div class="tarjeta-ludoteca-img-contenedor">
                                <img src="<?php echo h($juego['Url']); ?>" alt="<?php echo h($nombreJ); ?>">
                                <span class="insignia-nota-media <?php echo $claseColorMedia; ?>" title="Nota media de la Taberna">
                                    <?php echo h($textoMedia); ?>
                                </span>
                            </div>

                            <div class="tarjeta-ludoteca-estado <?php echo $claseMiEstado; ?>">
                                <?php echo h($textoMiEstado); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <p id="ludoteca-sin-resultados" style="display:none; font-style:italic; margin-top:16px; text-align:center;">
                    No se encontraron juegos en la ludoteca que coincidan con los filtros seleccionados.
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>

<script src="app.js"></script>
</body>
</html>