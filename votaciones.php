<?php
require_once 'conexion.php';
requerirLogin($pdo);

$mensajeExito = '';
$mensajeError = '';
$usuarioActual = $_SESSION['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    // 1. Crear una nueva votación
    if ($accion === 'crear_votacion') {
        $titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
        $fechaLimite = isset($_POST['fecha_limite']) ? trim($_POST['fecha_limite']) : '';
        $tipoVoto = (isset($_POST['tipo_voto']) && $_POST['tipo_voto'] === 'multiple') ? 'multiple' : 'unico';

        $hoy = date('Y-m-d');
        if ($titulo === '' || $fechaLimite === '') {
            $mensajeError = 'Debes indicar un título y un día límite para abrir la votación.';
        } elseif ($fechaLimite < $hoy) {
            $mensajeError = 'El día límite no puede ser anterior a la fecha actual.';
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO Votacion (titulo, creador_username, fecha_limite, tipo_voto) VALUES (?, ?, ?, ?)");
            $stmtIns->execute([$titulo, $usuarioActual, $fechaLimite, $tipoVoto]);
            $mensajeExito = "La votación «{$titulo}» ha quedado abierta hasta el día {$fechaLimite}.";
        }
    }

    // 2. Emitir voto(s) en una votación activa
    elseif ($accion === 'emitir_voto') {
        $idVotacion = isset($_POST['id_votacion']) ? (int)$_POST['id_votacion'] : 0;

        $stmtCheckVot = $pdo->prepare("SELECT id_votacion, titulo, tipo_voto, fecha_limite FROM Votacion WHERE id_votacion = ? AND fecha_limite >= CURDATE()");
        $stmtCheckVot->execute([$idVotacion]);
        $votacionActiva = $stmtCheckVot->fetch();

        if (!$votacionActiva) {
            $mensajeError = 'La votación seleccionada no existe o ya ha superado su día límite.';
        } else {
            $juegosElegidos = [];
            if ($votacionActiva['tipo_voto'] === 'unico') {
                if (isset($_POST['juego_unico']) && trim($_POST['juego_unico']) !== '') {
                    $juegosElegidos[] = trim($_POST['juego_unico']);
                }
            } else {
                if (isset($_POST['juegos_multiples']) && is_array($_POST['juegos_multiples'])) {
                    foreach ($_POST['juegos_multiples'] as $jSel) {
                        $jSelLimpio = trim((string)$jSel);
                        if ($jSelLimpio !== '') {
                            $juegosElegidos[] = $jSelLimpio;
                        }
                    }
                }
            }

            // Validar que todos los juegos votados existan y tengan lo_tenemos = 1
            $juegosValidos = [];
            if (count($juegosElegidos) > 0) {
                $placeholders = implode(',', array_fill(0, count($juegosElegidos), '?'));
                $stmtVal = $pdo->prepare("SELECT NombreJuego FROM Juego WHERE lo_tenemos = 1 AND NombreJuego IN ({$placeholders})");
                $stmtVal->execute($juegosElegidos);
                $juegosValidos = $stmtVal->fetchAll(PDO::FETCH_COLUMN);
            }

            // Actualizar los votos del usuario en esta votación dentro de una transacción
            $pdo->beginTransaction();
            try {
                $stmtDel = $pdo->prepare("DELETE FROM Voto WHERE id_votacion = ? AND username = ?");
                $stmtDel->execute([$idVotacion, $usuarioActual]);

                if (count($juegosValidos) > 0) {
                    $stmtInsVoto = $pdo->prepare("INSERT INTO Voto (id_votacion, username, NombreJuego) VALUES (?, ?, ?)");
                    foreach ($juegosValidos as $nombreJuegoValido) {
                        $stmtInsVoto->execute([$idVotacion, $usuarioActual, $nombreJuegoValido]);
                    }
                    $mensajeExito = "Tus votos han sido registrados en la votación «{$votacionActiva['titulo']}».";
                } else {
                    $mensajeExito = "Has retirado tus votos de la votación «{$votacionActiva['titulo']}».";
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $mensajeError = "No se pudo registrar el voto: " . $e->getMessage();
            }
        }
    }
}

// Obtener ÚNICAMENTE los juegos que YA tenemos (lo_tenemos = 1)
$stmtJuegos = $pdo->query("SELECT NombreJuego, Url FROM Juego WHERE lo_tenemos = 1 ORDER BY NombreJuego ASC");
$juegosQueTenemos = $stmtJuegos->fetchAll();

// Obtener las votaciones activas (fecha_limite >= CURDATE())
$stmtVotaciones = $pdo->query("
    SELECT v.id_votacion, v.titulo, v.creador_username, p.NombrePersona AS nombre_creador, v.fecha_limite, v.tipo_voto
    FROM Votacion v
    INNER JOIN Persona p ON v.creador_username = p.username
    WHERE v.fecha_limite >= CURDATE()
    ORDER BY v.fecha_limite ASC, v.id_votacion DESC
");
$votacionesActivas = $stmtVotaciones->fetchAll();

// Obtener todos los votos de las votaciones activas
$stmtVotos = $pdo->query("
    SELECT vt.id_votacion, vt.NombreJuego, vt.username
    FROM Voto vt
    INNER JOIN Votacion v ON vt.id_votacion = v.id_votacion
    WHERE v.fecha_limite >= CURDATE()
");
$conteoVotos = [];      // $conteoVotos[$id_votacion][$NombreJuego] = total
$votosDeUsuario = [];   // $votosDeUsuario[$id_votacion][$NombreJuego] = true

foreach ($stmtVotos->fetchAll() as $filaVoto) {
    $idV = (int)$filaVoto['id_votacion'];
    $nomJ = $filaVoto['NombreJuego'];
    if (!isset($conteoVotos[$idV][$nomJ])) {
        $conteoVotos[$idV][$nomJ] = 0;
    }
    $conteoVotos[$idV][$nomJ]++;

    if ($filaVoto['username'] === $usuarioActual) {
        $votosDeUsuario[$idV][$nomJ] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consejo de Votos - La Taberna del Dado de Hierro</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php renderizarNavegacion('votaciones'); ?>

<main class="contenedor-principal">

    <?php if ($mensajeError !== ''): ?>
        <div class="alerta alerta-error"><?php echo h($mensajeError); ?></div>
    <?php endif; ?>

    <?php if ($mensajeExito !== ''): ?>
        <div class="alerta alerta-exito"><?php echo h($mensajeExito); ?></div>
    <?php endif; ?>

    <!-- Formulario para abrir una nueva votación -->
    <section class="pergamino">
        <h2 class="seccion-titulo">Convocar Nueva Votación de Juegos</h2>
        <form method="POST" action="votaciones.php" class="grid-tres-columnas" style="align-items:end;">
            <input type="hidden" name="accion" value="crear_votacion">

            <div class="grupo-form" style="margin-bottom:0;">
                <label for="titulo_votacion">Título o Motivo de la Votación:</label>
                <input type="text" id="titulo_votacion" name="titulo" class="input-taberna" required maxlength="150" placeholder="Ej. Partida del Viernes Noche">
            </div>

            <div class="grupo-form" style="margin-bottom:0;">
                <label for="fecha_limite">Día Límite (Desaparece tras esta fecha):</label>
                <input type="date" id="fecha_limite" name="fecha_limite" class="input-taberna" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
            </div>

            <div class="grupo-form" style="margin-bottom:0;">
                <label for="tipo_voto">Modalidad de Voto:</label>
                <select id="tipo_voto" name="tipo_voto" class="select-taberna" required>
                    <option value="unico">Un solo juego por persona (Voto Único)</option>
                    <option value="multiple">Varios juegos por persona (Voto Múltiple)</option>
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-primario" style="width:100%;">Publicar Votación</button>
            </div>
        </form>
    </section>

    <!-- Listado de Votaciones Activas -->
    <section class="pergamino">
        <h2 class="seccion-titulo">Votaciones Activas en la Taberna</h2>

        <?php if (count($juegosQueTenemos) === 0): ?>
            <div class="alerta alerta-error">
                No hay ningún juego marcado como «Ya lo tenemos». Registra o cambia el estado de algún juego en la página de Juegos para poder votarlo.
            </div>
        <?php elseif (count($votacionesActivas) === 0): ?>
            <p style="font-style:italic;">No hay votaciones activas en este momento. ¡Convoca una arriba!</p>
        <?php else: ?>
            <?php foreach ($votacionesActivas as $vot): ?>
                <?php
                $idV = (int)$vot['id_votacion'];
                $esMultiple = ($vot['tipo_voto'] === 'multiple');

                // Calcular V_i para cada juego elegible y calcular el mínimo actual V_min
                $votosPorJuego = [];
                foreach ($juegosQueTenemos as $jDisp) {
                    $nJ = $jDisp['NombreJuego'];
                    $votosPorJuego[$nJ] = isset($conteoVotos[$idV][$nJ]) ? (int)$conteoVotos[$idV][$nJ] : 0;
                }
                $vMin = count($votosPorJuego) > 0 ? min($votosPorJuego) : 0;
                ?>
                <div class="votacion-bloque">
                    <div class="votacion-cabecera">
                        <div>
                            <h3 style="font-size:1.25rem; color:#23170f;"><?php echo h($vot['titulo']); ?></h3>
                            <p style="font-size:0.88rem;">
                                Por <strong><?php echo h($vot['nombre_creador']); ?></strong>
                            </p>
                        </div>
                        <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                            <span class="etiqueta etiqueta-bronce">
                                <?php echo $esMultiple ? 'Voto Múltiple' : 'Voto Único'; ?>
                            </span>
                            <span class="etiqueta etiqueta-pendiente">
                                Hasta: <?php echo h($vot['fecha_limite']); ?>
                            </span>
                        </div>
                    </div>

                    <form method="POST" action="votaciones.php">
                        <input type="hidden" name="accion" value="emitir_voto">
                        <input type="hidden" name="id_votacion" value="<?php echo $idV; ?>">

                        <div class="grid-juegos-votacion">
                            <?php foreach ($juegosQueTenemos as $jDisp): ?>
                                <?php
                                $nomJ = $jDisp['NombreJuego'];
                                $votosActuales = $votosPorJuego[$nomJ];
                                $votadoPorMi = isset($votosDeUsuario[$idV][$nomJ]);

                                // Regla estricta: Resaltado SI Y SOLO SI V_i >= 1 Y V_i > V_min
                                $estaResaltado = ($votosActuales >= 1 && $votosActuales > $vMin);
                                ?>
                                <div class="tarjeta-juego-voto <?php echo $estaResaltado ? 'juego-resaltado' : ''; ?>">
                                    <div>
                                        <?php if ($estaResaltado): ?>
                                            <div class="insignia-resaltado" style="margin-bottom:8px;">
                                                Destacado en Votación
                                            </div>
                                        <?php endif; ?>
                                        <img src="<?php echo h($jDisp['Url']); ?>" alt="<?php echo h($nomJ); ?>">
                                        <h4 style="margin-top:8px; font-size:1.05rem;"><?php echo h($nomJ); ?></h4>
                                    </div>

                                    <div>
                                        <p style="margin:6px 0; font-weight:bold; color:#362417;">
                                            Votos recibidos: <?php echo $votosActuales; ?>
                                        </p>
                                        <label class="radio-item" style="background-color:#dfd1a7; padding:6px 8px; border:1px solid #4a3219; font-weight:bold;">
                                            <?php if ($esMultiple): ?>
                                                <input type="checkbox" name="juegos_multiples[]" value="<?php echo h($nomJ); ?>" <?php echo $votadoPorMi ? 'checked' : ''; ?>>
                                                Seleccionar este juego
                                            <?php else: ?>
                                                <input type="radio" name="juego_unico" value="<?php echo h($nomJ); ?>" <?php echo $votadoPorMi ? 'checked' : ''; ?>>
                                                Votar por este juego
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn btn-exito">Guardar mi Voto en «<?php echo h($vot['titulo']); ?>»</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>

<script src="app.js"></script>
</body>
</html>