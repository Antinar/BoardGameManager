<?php
require_once 'conexion.php';
requerirLogin($pdo);

$mensajeExito = '';
$mensajeError = '';
$usuarioActual = $_SESSION['username'];

// Escala numérica de 1 a 5 para calcular la Calificación Promedio
$mapaValoresCalificacion = [
    'No me gustó'       => 1,
    'Meh'               => 2,
    'Está Bien'         => 3,
    'Me gustó bastante' => 4,
    '¿Pa cuando otra?'  => 5
];

$calificacionesPermitidas = array_keys($mapaValoresCalificacion);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    // Guardar o actualizar la reseña única del usuario para un juego
    if ($accion === 'guardar_resenya') {
        $nombreJuego = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';
        $haJugado = (isset($_POST['ha_jugado']) && $_POST['ha_jugado'] === '1') ? 1 : 0;
        $calificacion = isset($_POST['calificacion']) ? trim($_POST['calificacion']) : null;
        $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';

        // Verificar que el juego exista y esté en posesión física (lo_tenemos = 1)
        $stmtJuego = $pdo->prepare("SELECT NombreJuego FROM Juego WHERE NombreJuego = ? AND lo_tenemos = 1");
        $stmtJuego->execute([$nombreJuego]);
        $juegoValido = $stmtJuego->fetch();

        if (!$juegoValido) {
            $mensajeError = 'El juego indicado no existe o no se encuentra en posesión de la taberna.';
        } else {
            if ($haJugado === 1) {
                if (!in_array($calificacion, $calificacionesPermitidas, true)) {
                    $mensajeError = 'Al marcar que sí lo has jugado, debes seleccionar una calificación válida (1 a 5).';
                }
            } else {
                // Si marca que NO lo ha jugado, la calificación se guarda como NULL
                $calificacion = null;
                if ($comentario === '') {
                    $mensajeError = 'Si aún no has jugado a este juego, escribe un comentario u opinión para registrar tu reseña.';
                }
            }

            if ($mensajeError === '') {
                $comentarioGuardar = ($comentario !== '') ? $comentario : null;

                $stmtUpsert = $pdo->prepare("
                    INSERT INTO Resenya (username, NombreJuego, ha_jugado, calificacion, comentario)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        ha_jugado = VALUES(ha_jugado),
                        calificacion = VALUES(calificacion),
                        comentario = VALUES(comentario),
                        fecha_resenya = CURRENT_TIMESTAMP
                ");
                $stmtUpsert->execute([
                    $usuarioActual,
                    $nombreJuego,
                    $haJugado,
                    $calificacion,
                    $comentarioGuardar
                ]);

                $mensajeExito = "Tu crónica y reseña sobre «{$nombreJuego}» ha sido guardada en la Ludoteca.";
            }
        }
    }
    // Permitir al usuario borrar su propia reseña si lo desea
    elseif ($accion === 'eliminar_mi_resenya') {
        $nombreJuego = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';
        $stmtDel = $pdo->prepare("DELETE FROM Resenya WHERE username = ? AND NombreJuego = ?");
        $stmtDel->execute([$usuarioActual, $nombreJuego]);
        if ($stmtDel->rowCount() > 0) {
            $mensajeExito = "Has eliminado tu reseña de «{$nombreJuego}».";
        }
    }
}

// 1. Obtener únicamente los juegos que la taberna tiene en posesión física (lo_tenemos = 1)
$stmtJuegos = $pdo->query("SELECT NombreJuego, Url FROM Juego WHERE lo_tenemos = 1 ORDER BY NombreJuego ASC");
$juegosLudoteca = $stmtJuegos->fetchAll();

// 2. Obtener todas las reseñas de los juegos en posesión junto con el nombre de cada parroquiano
$stmtResenyas = $pdo->query("
    SELECT r.id_resenya, r.username, p.NombrePersona, r.NombreJuego,
           r.ha_jugado, r.calificacion, r.comentario, r.fecha_resenya
    FROM Resenya r
    INNER JOIN Persona p ON r.username = p.username
    INNER JOIN Juego j ON r.NombreJuego = j.NombreJuego
    WHERE j.lo_tenemos = 1
    ORDER BY r.fecha_resenya DESC, r.id_resenya DESC
");
$todasLasResenyas = $stmtResenyas->fetchAll();

$resenyasPorJuego = [];
$miResenyaPorJuego = [];

foreach ($todasLasResenyas as $filaR) {
    $nomJ = $filaR['NombreJuego'];
    $resenyasPorJuego[$nomJ][] = $filaR;
    if ($filaR['username'] === $usuarioActual) {
        $miResenyaPorJuego[$nomJ] = $filaR;
    }
}

/**
 * Convierte un promedio numérico (1 a 5) en su veredicto equivalente de la escala.
 */
function obtenerVeredictoPromedio($promedio) {
    $redondeado = (int)round($promedio);
    switch ($redondeado) {
        case 1:  return 'No me gustó';
        case 2:  return 'Meh';
        case 3:  return 'Está Bien';
        case 4:  return 'Me gustó bastante';
        case 5:  return '¿Pa cuando otra?';
        default: return 'Sin calificaciones todavía';
    }
}

/**
 * Devuelve la clase CSS rústica asociada a cada calificación.
 */
function obtenerClaseInsigniaCalificacion($calificacion) {
    switch ($calificacion) {
        case 'No me gustó':       return 'insignia-calificacion-1';
        case 'Meh':               return 'insignia-calificacion-2';
        case 'Está Bien':         return 'insignia-calificacion-3';
        case 'Me gustó bastante': return 'insignia-calificacion-4';
        case '¿Pa cuando otra?':  return 'insignia-calificacion-5';
        default:                  return 'insignia-no-jugado';
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

    <section class="pergamino">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
            <div>
                <h2 class="seccion-titulo" style="margin-bottom:6px; border-bottom:none;">
                    Ludoteca de la Taberna (Juegos en Posesión)
                </h2>
                <p>
                    Catálogo oficial de juegos disponibles en nuestras mesas. Usa el conmutador de cada juego para alternar entre su <strong>Imagen</strong> y las <strong>Estadísticas de la Comunidad</strong>, y registra o edita tu reseña personal.
                </p>
            </div>
            <div style="min-width:270px;">
                <label for="filtro_ludoteca" style="font-weight:bold; display:block; margin-bottom:4px;">Filtrar en la Ludoteca:</label>
                <input type="text" id="filtro_ludoteca" class="input-taberna" placeholder="Buscar por juego, autor o reseña..." data-ludoteca-filter="lista-juegos-ludoteca">
            </div>
        </div>
    </section>

    <?php if (count($juegosLudoteca) === 0): ?>
        <section class="pergamino">
            <p style="font-style:italic;">
                Actualmente no hay juegos registrados con la opción «1 - Ya lo tenemos». Sube o marca un juego como disponible en la sección de Juegos para que forme parte de la Ludoteca.
            </p>
        </section>
    <?php else: ?>
        <div id="lista-juegos-ludoteca">
            <?php foreach ($juegosLudoteca as $idx => $juego): ?>
                <?php
                $nombreJ = $juego['NombreJuego'];
                $slugId = 'lud_' . $idx . '_' . substr(md5($nombreJ), 0, 8);
                $listaResenyas = isset($resenyasPorJuego[$nombreJ]) ? $resenyasPorJuego[$nombreJ] : [];
                $miResenya = isset($miResenyaPorJuego[$nombreJ]) ? $miResenyaPorJuego[$nombreJ] : null;

                // Cálculo de métricas de la comunidad para este juego
                $totalResenyas = count($listaResenyas);
                $jugadoSi = 0;
                $jugadoNo = 0;
                $sumaCalificaciones = 0;
                $totalVotosCalificacion = 0;
                $desgloseCalificaciones = [
                    'No me gustó'       => 0,
                    'Meh'               => 0,
                    'Está Bien'         => 0,
                    'Me gustó bastante' => 0,
                    '¿Pa cuando otra?'  => 0
                ];

                foreach ($listaResenyas as $rItem) {
                    if ((int)$rItem['ha_jugado'] === 1) {
                        $jugadoSi++;
                        $cal = $rItem['calificacion'];
                        if ($cal !== null && isset($mapaValoresCalificacion[$cal])) {
                            $sumaCalificaciones += $mapaValoresCalificacion[$cal];
                            $totalVotosCalificacion++;
                            $desgloseCalificaciones[$cal]++;
                        }
                    } else {
                        $jugadoNo++;
                    }
                }

                $porcentajeSi = ($totalResenyas > 0) ? round(($jugadoSi / $totalResenyas) * 100) : 0;
                $porcentajeNo = ($totalResenyas > 0) ? (100 - $porcentajeSi) : 0;
                $promedio = ($totalVotosCalificacion > 0) ? round($sumaCalificaciones / $totalVotosCalificacion, 2) : null;
                $veredictoMedio = ($promedio !== null) ? obtenerVeredictoPromedio($promedio) : null;

                // Datos precargados del usuario actual si ya dejó reseña
                $miHaJugado = $miResenya ? (int)$miResenya['ha_jugado'] : 1;
                $miCalificacion = ($miResenya && $miResenya['calificacion'] !== null) ? $miResenya['calificacion'] : 'Está Bien';
                $miComentario = ($miResenya && $miResenya['comentario'] !== null) ? $miResenya['comentario'] : '';
                ?>

                <article class="pergamino ludoteca-tarjeta">
                    <div class="ludoteca-cabecera-juego">
                        <h3 class="seccion-titulo" style="margin-bottom:0; border-bottom:none;">
                            <?php echo h($nombreJ); ?>
                        </h3>
                        <span class="etiqueta etiqueta-disponible">1 - Ya lo tenemos</span>
                    </div>

                    <div class="grid-dos-columnas" style="margin-top:14px;">
                        <!-- COLUMNA IZQUIERDA: CONMUTADOR VISUAL (IMAGEN vs ESTADÍSTICAS) -->
                        <div class="visor-juego-contenedor" id="visor_<?php echo $slugId; ?>">
                            <div class="visor-conmutador-barra">
                                <button type="button"
                                        class="btn-conmutador activa"
                                        data-visor-card="visor_<?php echo $slugId; ?>"
                                        data-visor-target="imagen">
                                    Vista A: Imagen del Juego
                                </button>
                                <button type="button"
                                        class="btn-conmutador"
                                        data-visor-card="visor_<?php echo $slugId; ?>"
                                        data-visor-target="estadisticas">
                                    Vista B: Estadísticas (<?php echo $totalResenyas; ?>)
                                </button>
                            </div>

                            <!-- VISTA A: IMAGEN -->
                            <div class="vista-panel vista-imagen-juego" data-visor-panel="imagen">
                                <img src="<?php echo h($juego['Url']); ?>" alt="<?php echo h($nombreJ); ?>" class="img-ludoteca-principal">
                                <p class="visor-pie-nota">
                                    Selecciona <strong>«Vista B: Estadísticas»</strong> arriba para conmutar la imagen por las métricas de la comunidad.
                                </p>
                            </div>

                            <!-- VISTA B: ESTADÍSTICAS DE LA COMUNIDAD -->
                            <div class="vista-panel vista-estadisticas-juego panel-oculto" data-visor-panel="estadisticas">
                                <h4 class="seccion-subtitulo">Pergamino de Métricas de la Comunidad</h4>

                                <div class="estadistica-bloque-detalle">
                                    <p><strong>1. Participación de Parroquianos:</strong></p>
                                    <p style="margin-top:4px;">
                                        <strong>Sí lo han jugado:</strong> <?php echo $jugadoSi; ?> (<?php echo $porcentajeSi; ?>%)
                                        <br>
                                        <strong>No lo han jugado:</strong> <?php echo $jugadoNo; ?> (<?php echo $porcentajeNo; ?>%)
                                    </p>
                                    <div class="barra-progreso-rustica" title="<?php echo $porcentajeSi; ?>% lo han jugado">
                                        <div class="barra-relleno-si" style="width: <?php echo $porcentajeSi; ?>%;"></div>
                                    </div>
                                </div>

                                <div class="estadistica-bloque-detalle" style="margin-top:12px;">
                                    <p><strong>2. Calificación Promedio (Escala 1 a 5):</strong></p>
                                    <?php if ($promedio === null): ?>
                                        <p style="font-style:italic; color:#5a4634; margin-top:6px;">
                                            Sin calificaciones todavía
                                        </p>
                                    <?php else: ?>
                                        <div style="margin-top:8px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                            <span class="promedio-numero-grande"><?php echo number_format($promedio, 2); ?> / 5.00</span>
                                            <span class="etiqueta <?php echo obtenerClaseInsigniaCalificacion($veredictoMedio); ?>">
                                                <?php echo h($veredictoMedio); ?>
                                            </span>
                                        </div>
                                        <p style="font-size:0.85rem; margin-top:4px;">
                                            Basado en <?php echo $totalVotosCalificacion; ?> calificación(es) de quienes lo han jugado.
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div class="estadistica-bloque-detalle" style="margin-top:12px;">
                                    <p><strong>3. Total de reseñas escritas en el juego:</strong> <?php echo $totalResenyas; ?></p>
                                    <ul class="lista-desglose-votos" style="margin-top:6px;">
                                        <li><span>5 - ¿Pa cuando otra?:</span> <strong><?php echo $desgloseCalificaciones['¿Pa cuando otra?']; ?> voto(s)</strong></li>
                                        <li><span>4 - Me gustó bastante:</span> <strong><?php echo $desgloseCalificaciones['Me gustó bastante']; ?> voto(s)</strong></li>
                                        <li><span>3 - Está Bien:</span> <strong><?php echo $desgloseCalificaciones['Está Bien']; ?> voto(s)</strong></li>
                                        <li><span>2 - Meh:</span> <strong><?php echo $desgloseCalificaciones['Meh']; ?> voto(s)</strong></li>
                                        <li><span>1 - No me gustó:</span> <strong><?php echo $desgloseCalificaciones['No me gustó']; ?> voto(s)</strong></li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- COLUMNA DERECHA: FORMULARIO DE RESEÑA POR PARROQUIANO -->
                        <div class="pergamino-secundario" style="margin-bottom:0;">
                            <h4 class="seccion-subtitulo">
                                <?php echo $miResenya ? 'Mi Reseña en este Juego (Modo Edición)' : 'Registrar mi Reseña en este Juego'; ?>
                            </h4>

                            <form method="POST" action="ludoteca.php">
                                <input type="hidden" name="accion" value="guardar_resenya">
                                <input type="hidden" name="NombreJuego" value="<?php echo h($nombreJ); ?>">

                                <div class="grupo-form">
                                    <label>¿Lo has jugado alguna vez?</label>
                                    <div class="radio-grupo">
                                        <label class="radio-item">
                                            <input type="radio"
                                                   name="ha_jugado"
                                                   value="1"
                                                   data-ha-jugado-input="bloque_cal_<?php echo $slugId; ?>"
                                                   <?php echo ($miHaJugado === 1) ? 'checked' : ''; ?>>
                                            <strong>Sí</strong> (1)
                                        </label>
                                        <label class="radio-item">
                                            <input type="radio"
                                                   name="ha_jugado"
                                                   value="0"
                                                   data-ha-jugado-input="bloque_cal_<?php echo $slugId; ?>"
                                                   <?php echo ($miHaJugado === 0) ? 'checked' : ''; ?>>
                                            <strong>No</strong> (0)
                                        </label>
                                    </div>
                                </div>

                                <div id="bloque_cal_<?php echo $slugId; ?>"
                                     class="grupo-form <?php echo ($miHaJugado === 1) ? '' : 'panel-oculto'; ?>">
                                    <label for="cal_<?php echo $slugId; ?>">Calificación (1 a 5):</label>
                                    <select id="cal_<?php echo $slugId; ?>" name="calificacion" class="select-taberna">
                                        <option value="No me gustó" <?php echo ($miCalificacion === 'No me gustó') ? 'selected' : ''; ?>>
                                            1. No me gustó
                                        </option>
                                        <option value="Meh" <?php echo ($miCalificacion === 'Meh') ? 'selected' : ''; ?>>
                                            2. Meh
                                        </option>
                                        <option value="Está Bien" <?php echo ($miCalificacion === 'Está Bien') ? 'selected' : ''; ?>>
                                            3. Está Bien
                                        </option>
                                        <option value="Me gustó bastante" <?php echo ($miCalificacion === 'Me gustó bastante') ? 'selected' : ''; ?>>
                                            4. Me gustó bastante
                                        </option>
                                        <option value="¿Pa cuando otra?" <?php echo ($miCalificacion === '¿Pa cuando otra?') ? 'selected' : ''; ?>>
                                            5. ¿Pa cuando otra?
                                        </option>
                                    </select>
                                </div>

                                <div class="grupo-form">
                                    <label for="com_<?php echo $slugId; ?>">Comentario / Opinión libre (Tanto si lo has jugado como si no):</label>
                                    <textarea id="com_<?php echo $slugId; ?>"
                                              name="comentario"
                                              rows="4"
                                              class="input-taberna"
                                              placeholder="Deja aquí tus impresiones de la partida o comenta si tienes ganas de probarlo..."><?php echo h($miComentario); ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-primario">
                                    <?php echo $miResenya ? 'Actualizar mi Reseña' : 'Publicar mi Reseña'; ?>
                                </button>
                            </form>

                            <?php if ($miResenya): ?>
                                <form method="POST" action="ludoteca.php" class="form-confirmar-borrado" data-confirm-msg="¿Deseas eliminar tu reseña de este juego?" style="margin-top:8px;">
                                    <input type="hidden" name="accion" value="eliminar_mi_resenya">
                                    <input type="hidden" name="NombreJuego" value="<?php echo h($nombreJ); ?>">
                                    <button type="submit" class="btn btn-peligro btn-pequeno">Eliminar mi reseña</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- MURO PÚBLICO DE CRÓNICAS -->
                    <div class="muro-resenyas">
                        <h4 class="seccion-subtitulo" style="margin-bottom:10px;">
                            Muro Público de Crónicas (<?php echo $totalResenyas; ?>)
                        </h4>

                        <?php if ($totalResenyas === 0): ?>
                            <p style="font-style:italic; color:#5a4634;">
                                Aún no hay crónicas escritas para «<?php echo h($nombreJ); ?>».
                            </p>
                        <?php else: ?>
                            <div class="lista-tarjetas-resenya">
                                <?php foreach ($listaResenyas as $res): ?>
                                    <?php
                                    $loJugo = ((int)$res['ha_jugado'] === 1);
                                    $calTxt = $res['calificacion'];
                                    $claseInsignia = $loJugo ? obtenerClaseInsigniaCalificacion($calTxt) : 'insignia-no-jugado';
                                    ?>
                                    <div class="resenya-item">
                                        <div class="resenya-cabecera">
                                            <div>
                                                <strong><?php echo h($res['NombrePersona']); ?></strong>
                                                <span style="font-size:0.88rem; color:#4a3219;">(<?php echo h($res['username']); ?>)</span>
                                            </div>
                                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                                <?php if ($loJugo && $calTxt !== null): ?>
                                                    <span class="etiqueta <?php echo $claseInsignia; ?>">
                                                        Jugado: <?php echo h($calTxt); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="etiqueta insignia-no-jugado">
                                                        Aún no lo ha jugado
                                                    </span>
                                                <?php endif; ?>
                                                <span class="resenya-fecha"><?php echo h($res['fecha_resenya']); ?></span>
                                            </div>
                                        </div>

                                        <?php if ($res['comentario'] !== null && trim($res['comentario']) !== ''): ?>
                                            <p class="resenya-texto"><?php echo nl2br(h($res['comentario'])); ?></p>
                                        <?php else: ?>
                                            <p class="resenya-texto-vacio"><em>(Valoración registrada sin comentario adicional)</em></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

<script src="app.js"></script>
</body>
</html>