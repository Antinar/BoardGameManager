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

// Procesar guardado o actualización de reseña del usuario actual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_resenya') {
    $nombreJuego = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';
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
            // Si no lo ha jugado, calificación y comentario quedan nulos
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
            $mensajeExito = "Tu registro sobre «{$nombreJuego}» se ha guardado en la Ludoteca.";
        }
    }
}

// Obtener ÚNICAMENTE los juegos que YA tenemos en la taberna (lo_tenemos = 1)
$stmtJuegos = $pdo->query("SELECT NombreJuego, Url FROM Juego WHERE lo_tenemos = 1 ORDER BY NombreJuego ASC");
$juegosLudoteca = $stmtJuegos->fetchAll();

// Obtener todas las reseñas asociadas a los juegos que tenemos
$stmtResenyas = $pdo->query("
    SELECT r.id_resenya, r.username, p.NombrePersona, r.NombreJuego, r.ha_jugado, r.calificacion, r.comentario, r.fecha_resenya
    FROM Resenya r
    INNER JOIN Persona p ON r.username = p.username
    INNER JOIN Juego j ON r.NombreJuego = j.NombreJuego AND j.lo_tenemos = 1
    ORDER BY r.fecha_resenya DESC, p.NombrePersona ASC
");

$resenyasPorJuego = [];
$miResenyaPorJuego = [];

foreach ($stmtResenyas->fetchAll() as $filaR) {
    $nJ = $filaR['NombreJuego'];
    $resenyasPorJuego[$nJ][] = $filaR;
    if ($filaR['username'] === $usuarioActual) {
        $miResenyaPorJuego[$nJ] = $filaR;
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
        <div class="barra-titulo-filtro">
            <h2 class="seccion-titulo" style="margin-bottom:0; border-bottom:none;">
                Ludoteca de la Taberna (<?php echo count($juegosLudoteca); ?>)
            </h2>
            <div class="buscador-bloque">
                <input type="text" id="buscador-ludoteca" class="input-taberna" placeholder="Buscar juego por nombre...">
            </div>
        </div>

        <?php if (count($juegosLudoteca) === 0): ?>
            <p style="font-style:italic; margin-top:12px;">
                Todavía no hay juegos marcados como «Ya lo tenemos» en la taberna.
            </p>
        <?php else: ?>
            <div class="grid-ludoteca" id="contenedor-ludoteca">
                <?php foreach ($juegosLudoteca as $juego): ?>
                    <?php
                    $nombreJ = $juego['NombreJuego'];
                    $hashJ = md5($nombreJ);
                    $miRes = isset($miResenyaPorJuego[$nombreJ]) ? $miResenyaPorJuego[$nombreJ] : null;
                    $heJugado = ($miRes && (int)$miRes['ha_jugado'] === 1);
                    $miCalificacion = $miRes ? (string)$miRes['calificacion'] : '';
                    $miComentario = $miRes ? (string)$miRes['comentario'] : '';
                    $listaResenyas = isset($resenyasPorJuego[$nombreJ]) ? $resenyasPorJuego[$nombreJ] : [];
                    ?>
                    <article class="tarjeta-ludoteca" data-nombre="<?php echo h($nombreJ); ?>">
                        <div class="tarjeta-ludoteca-top">
                            <img src="<?php echo h($juego['Url']); ?>" alt="<?php echo h($nombreJ); ?>">
                            <h3 class="tarjeta-ludoteca-titulo"><?php echo h($nombreJ); ?></h3>
                        </div>

                        <!-- Formulario de valoración personal -->
                        <form method="POST" action="ludoteca.php" class="pergamino-secundario form-ludoteca">
                            <input type="hidden" name="accion" value="guardar_resenya">
                            <input type="hidden" name="NombreJuego" value="<?php echo h($nombreJ); ?>">

                            <div class="grupo-form" style="margin-bottom:10px;">
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
                                <div class="grupo-form" style="margin-bottom:10px;">
                                    <label for="cal_<?php echo $hashJ; ?>">¿Cuánto te ha gustado? (1 a 5):</label>
                                    <select id="cal_<?php echo $hashJ; ?>" name="calificacion" class="select-taberna">
                                        <option value="">-- Selecciona del 1 al 5 --</option>
                                        <?php foreach ($escalaCalificaciones as $num => $textoEnum): ?>
                                            <option value="<?php echo h($textoEnum); ?>" <?php echo ($miCalificacion === $textoEnum) ? 'selected' : ''; ?>>
                                                <?php echo $num . ' - ' . h($textoEnum); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="grupo-form" style="margin-bottom:10px;">
                                    <label for="com_<?php echo $hashJ; ?>">Tu opinión (opcional):</label>
                                    <textarea id="com_<?php echo $hashJ; ?>" name="comentario" class="input-taberna" rows="2" placeholder="Escribe qué te pareció la partida..."><?php echo h($miComentario); ?></textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primario btn-pequeno" style="width:100%;">
                                Guardar mi Valoración
                            </button>
                        </form>

                        <!-- Tabla de reseñas de todos los miembros para este juego -->
                        <div class="ludoteca-resenyas-wrapper">
                            <h4 style="font-size:0.95rem; color:#23170f; margin-bottom:6px; border-bottom:1px solid #4a3219; padding-bottom:4px;">
                                Reseñas de la Taberna (<?php echo count($listaResenyas); ?>)
                            </h4>
                            <?php if (count($listaResenyas) === 0): ?>
                                <p style="font-style:italic; font-size:0.86rem;">Nadie ha registrado aún si ha jugado a este juego.</p>
                            <?php else: ?>
                                <div class="tabla-contenedor">
                                    <table class="tabla-taberna">
                                        <thead>
                                            <tr>
                                                <th>Jugador</th>
                                                <th>¿Jugado?</th>
                                                <th>Nota (1-5)</th>
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
                                                    <td><strong><?php echo h($res['NombrePersona']); ?></strong></td>
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
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <p id="ludoteca-sin-resultados" style="display:none; font-style:italic; margin-top:16px; text-align:center;">
                No se encontraron juegos en la ludoteca con ese nombre.
            </p>
        <?php endif; ?>
    </section>
</main>

<script src="app.js"></script>
</body>
</html>