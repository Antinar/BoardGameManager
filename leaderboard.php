<?php
require_once 'conexion.php';
requerirLogin($pdo);

$mensajeExito = '';
$mensajeError = '';
$usuarioActual = $_SESSION['username'];

// Validación CSRF común a toda petición POST: se comprueba antes de leer la acción o tocar la BD.
$csrfOk = true;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = verificarTokenCsrf();
    if (!$csrfOk) {
        $mensajeError = MENSAJE_CSRF_INVALIDO;
    }
}

if ($csrfOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    // 2. Registrar ganador de una partida (Solo de juegos que YA tenemos)
    if ($accion === 'registrar_victoria') {
        $juegoSeleccionado = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';
        $personaGanadora = isset($_POST['username']) ? trim($_POST['username']) : '';

        if ($juegoSeleccionado === '' || $personaGanadora === '') {
            $mensajeError = 'Debes seleccionar tanto el juego disputado como la persona ganadora.';
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
                $stmtIns = $pdo->prepare("INSERT INTO Victoria (username, NombreJuego, fecha_victoria) VALUES (?, ?, NOW())");
                $stmtIns->execute([$personaGanadora, $juegoSeleccionado]);

                $mensajeExito = "Victoria registrada para «{$personaRow['NombrePersona']}» en «{$juegoSeleccionado}».";
            }
        }
    }
}

// Obtener ÚNICAMENTE los juegos que YA tenemos (lo_tenemos = 1) para el desplegable y filtro
$stmtJuegosTenemos = $pdo->query("SELECT NombreJuego FROM Juego WHERE lo_tenemos = 1 ORDER BY NombreJuego ASC");
$juegosDisponibles = $stmtJuegosTenemos->fetchAll();

// Obtener todas las personas de la base de datos para poder asignar ganador
$stmtPersonas = $pdo->query("SELECT username, NombrePersona FROM Persona ORDER BY NombrePersona ASC");
$todasLasPersonas = $stmtPersonas->fetchAll();

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

// Obtener el historial de últimas partidas ganadas
if ($filtroJuego !== '') {
    $stmtHistorial = $pdo->prepare("
        SELECT v.id_victoria, v.username, p.NombrePersona, v.NombreJuego, v.fecha_victoria
        FROM Victoria v
        INNER JOIN Persona p ON v.username = p.username
        WHERE v.NombreJuego = ?
        ORDER BY v.fecha_victoria DESC, v.id_victoria DESC
        LIMIT 25
    ");
    $stmtHistorial->execute([$filtroJuego]);
    $ultimasVictorias = $stmtHistorial->fetchAll();
} else {
    $stmtHistorial = $pdo->query("
        SELECT v.id_victoria, v.username, p.NombrePersona, v.NombreJuego, v.fecha_victoria
        FROM Victoria v
        INNER JOIN Persona p ON v.username = p.username
        ORDER BY v.fecha_victoria DESC, v.id_victoria DESC
        LIMIT 25
    ");
    $ultimasVictorias = $stmtHistorial->fetchAll();
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

    <!-- Asignar Ganador de una Partida -->
    <section class="pergamino">
        <h2 class="seccion-titulo">Anotar Ganador de Partida</h2>
        <?php if (count($juegosDisponibles) === 0): ?>
            <p style="font-style:italic;">No hay juegos marcados como «Ya lo tenemos».</p>
        <?php else: ?>
            <form method="POST" action="leaderboard.php" class="grid-tres-columnas" style="align-items:end;">
                <input type="hidden" name="accion" value="registrar_victoria">
                <?php echo campoCsrf(); ?>

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

        <div class="tabla-contenedor">
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
    </section>

    <!-- Historial de Últimas Partidas Ganadas -->
    <section class="pergamino">
        <h2 class="seccion-titulo">Últimas Partidas Ganadas</h2>
        <div class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
                        <th>Ganador</th>
                        <th>Juego de Mesa</th>
                        <th style="width:210px;">Fecha y Hora</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($ultimasVictorias) === 0): ?>
                        <tr>
                            <td colspan="3" style="text-align:center; font-style:italic;">
                                No hay victorias registradas para el filtro seleccionado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ultimasVictorias as $uv): ?>
                            <tr>
                                <td>
                                    <strong><?php echo h($uv['NombrePersona']); ?></strong>
                                    (<?php echo h($uv['username']); ?>)
                                </td>
                                <td><?php echo h($uv['NombreJuego']); ?></td>
                                <td><?php echo h($uv['fecha_victoria']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script src="app.js"></script>
</body>
</html>