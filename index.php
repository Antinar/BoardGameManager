<?php
require_once 'conexion.php';
requerirLogin($pdo);

// Estadísticas rápidas de la taberna
$totalJuegosTenemos = (int)$pdo->query("SELECT COUNT(*) FROM Juego WHERE lo_tenemos = 1")->fetchColumn();
$totalJuegosDeseados = (int)$pdo->query("SELECT COUNT(*) FROM Juego WHERE lo_tenemos = 0")->fetchColumn();
$totalVotacionesActivas = (int)$pdo->query("SELECT COUNT(*) FROM Votacion WHERE fecha_limite >= CURDATE()")->fetchColumn();
$totalParroquianos = (int)$pdo->query("SELECT COUNT(*) FROM Persona")->fetchColumn();

// Última victoria registrada
$stmtUltima = $pdo->query("
    SELECT v.NombreJuego, v.fecha_victoria, p.NombrePersona, p.username
    FROM Victoria v
    INNER JOIN Persona p ON v.username = p.username
    ORDER BY v.fecha_victoria DESC, v.id_victoria DESC
    LIMIT 1
");
$ultimaVictoria = $stmtUltima->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salón Principal - La Taberna del Dado de Hierro</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php renderizarNavegacion('index'); ?>

<main class="contenedor-principal">
    <section class="pergamino">
        <h2 class="seccion-titulo">Salón Principal — <?php echo h($_SESSION['NombrePersona']); ?></h2>

        <div class="grid-tres-columnas">
            <div class="estadistica-caja">
                <span class="estadistica-numero"><?php echo $totalJuegosTenemos; ?></span>
                <strong>Juegos en la Taberna</strong>
            </div>
            <div class="estadistica-caja">
                <span class="estadistica-numero"><?php echo $totalJuegosDeseados; ?></span>
                <strong>Juegos Deseados</strong>
            </div>
            <div class="estadistica-caja">
                <span class="estadistica-numero"><?php echo $totalVotacionesActivas; ?></span>
                <strong>Votaciones Activas</strong>
            </div>
        </div>
    </section>

    <div class="grid-dos-columnas">
        <section class="pergamino">
            <h3 class="seccion-subtitulo">Accesos Rápidos</h3>
            <div style="display:grid; gap:10px;">
                <a href="alta_juego.php" class="btn btn-primario">Subir Nuevo Juego</a>
                <a href="juegos.php" class="btn btn-bronce">Ver Juegos Deseados (Interesados)</a>
                <a href="leaderboard.php" class="btn">Tablón de Campeones (Leaderboard)</a>
                <a href="votaciones.php" class="btn btn-exito">Consejo de Votaciones</a>
                <?php if ($_SESSION['rol'] === 'admin'): ?>
                    <a href="admin.php" class="btn btn-peligro">Panel de Administrador</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="pergamino">
            <h3 class="seccion-subtitulo">Última Partida Ganada</h3>
            <?php if ($ultimaVictoria): ?>
                <div class="pergamino-secundario">
                    <p><strong>Ganador:</strong> <?php echo h($ultimaVictoria['NombrePersona']); ?> (<?php echo h($ultimaVictoria['username']); ?>)</p>
                    <p><strong>Juego:</strong> <?php echo h($ultimaVictoria['NombreJuego']); ?></p>
                    <p><strong>Fecha:</strong> <?php echo h($ultimaVictoria['fecha_victoria']); ?></p>
                </div>
            <?php else: ?>
                <p style="font-style:italic;">Sin victorias registradas todavía.</p>
            <?php endif; ?>
        </section>
    </div>
</main>

<script src="app.js"></script>
</body>
</html>