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

<?php renderizarNavegacion('inicio'); ?>

<main class="contenedor-principal">
    <section class="pergamino">
        <h2 class="seccion-titulo">Bienvenido al Salón Principal, <?php echo h($_SESSION['NombrePersona']); ?></h2>
        <p style="margin-bottom: 16px;">
            Toma asiento junto a la chimenea. Desde este salón puedes consultar el catálogo de juegos deseados para próximas adquisiciones, revisar las gestas en el Tablón de Campeones o dejar tu voto en el Consejo de la Taberna para elegir a qué jugaremos en la próxima velada.
        </p>

        <div class="grid-tres-columnas" style="margin-top: 20px;">
            <div class="estadistica-caja">
                <span class="estadistica-numero"><?php echo $totalJuegosTenemos; ?></span>
                <strong>Juegos en la Taberna</strong>
                <p style="font-size:0.88rem; margin-top:4px;">Listos para ser votados y jugados</p>
            </div>
            <div class="estadistica-caja">
                <span class="estadistica-numero"><?php echo $totalJuegosDeseados; ?></span>
                <strong>Juegos Deseados (No tenemos)</strong>
                <p style="font-size:0.88rem; margin-top:4px;">Abiertos a inscripción de interesados</p>
            </div>
            <div class="estadistica-caja">
                <span class="estadistica-numero"><?php echo $totalVotacionesActivas; ?></span>
                <strong>Votaciones Activas Hoy</strong>
                <p style="font-size:0.88rem; margin-top:4px;"><?php echo $totalParroquianos; ?> parroquianos registrados</p>
            </div>
        </div>
    </section>

    <div class="grid-dos-columnas">
        <section class="pergamino">
            <h3 class="seccion-subtitulo">Secciones de la Cofradía</h3>
            <div class="pergamino-secundario">
                <h4>1. Libro de Juegos Deseados</h4>
                <p style="margin: 6px 0 10px;">Registra juegos nuevos, consulta los juegos que aún no tenemos en la taberna, apúntate como interesado o márcalos cuando por fin los adquiramos.</p>
                <a href="juegos.php" class="btn btn-bronce btn-pequeno">Ir a Juegos Deseados</a>
            </div>

            <div class="pergamino-secundario">
                <h4>2. Tablón de Campeones (Leaderboard)</h4>
                <p style="margin: 6px 0 10px;">Inscríbete en la clasificación general, anota a los ganadores de los juegos que ya tenemos en la taberna y filtra el ranking por juego.</p>
                <a href="leaderboard.php" class="btn btn-primario btn-pequeno">Ver Clasificación</a>
            </div>

            <div class="pergamino-secundario">
                <h4>3. Consejo de Votaciones</h4>
                <p style="margin: 6px 0 10px;">Abre votaciones con fecha límite (voto único o múltiple) y vota entre los juegos disponibles en nuestra estantería.</p>
                <a href="votaciones.php" class="btn btn-exito btn-pequeno">Ir a Votaciones</a>
            </div>
        </section>

        <section class="pergamino">
            <h3 class="seccion-subtitulo">Última Gesta Registrada</h3>
            <?php if ($ultimaVictoria): ?>
                <div class="pergamino-secundario">
                    <p><strong>Campeón:</strong> <?php echo h($ultimaVictoria['NombrePersona']); ?> (<?php echo h($ultimaVictoria['username']); ?>)</p>
                    <p><strong>Juego Conquistado:</strong> <?php echo h($ultimaVictoria['NombreJuego']); ?></p>
                    <p><strong>Fecha de la Partida:</strong> <?php echo h($ultimaVictoria['fecha_victoria']); ?></p>
                </div>
            <?php else: ?>
                <p style="font-style:italic;">Aún no se ha registrado ninguna partida ganada en los anales de la taberna.</p>
            <?php endif; ?>

            <?php if ($_SESSION['rol'] === 'admin'): ?>
                <div class="pergamino-secundario" style="margin-top:20px; border-color:#8c2d19;">
                    <h4>Acceso de Tabernero Mayor (Administrador)</h4>
                    <p style="margin: 6px 0 10px;">Tienes llaves maestras para ver, editar o eliminar cualquier usuario, juego, inscripción, victoria, votación o voto.</p>
                    <a href="admin.php" class="btn btn-primario btn-pequeno">Abrir Despacho del Tabernero</a>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<script src="app.js"></script>
</body>
</html>