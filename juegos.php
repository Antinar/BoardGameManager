<?php
require_once 'conexion.php';
requerirLogin($pdo);

$mensajeExito = '';
$mensajeError = '';
$usuarioActual = $_SESSION['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    // 1. Subir y registrar un nuevo juego
    if ($accion === 'crear_juego') {
        $nombreJuego = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';
        $loTenemos = (isset($_POST['lo_tenemos']) && $_POST['lo_tenemos'] === '1') ? 1 : 0;

        if ($nombreJuego === '') {
            $mensajeError = 'El nombre del juego es obligatorio.';
        } elseif (mb_strlen($nombreJuego) > 120) {
            $mensajeError = 'El nombre del juego no puede superar los 120 caracteres.';
        } else {
            $stmtExiste = $pdo->prepare("SELECT COUNT(*) FROM Juego WHERE NombreJuego = ?");
            $stmtExiste->execute([$nombreJuego]);
            if ((int)$stmtExiste->fetchColumn() > 0) {
                $mensajeError = 'Ya existe un juego registrado con ese exacto nombre en la base de datos.';
            } else {
                $errorImg = '';
                $rutaImagen = procesarSubidaImagen('imagen_juego', $errorImg);
                if ($rutaImagen === false) {
                    $mensajeError = $errorImg;
                } else {
                    $stmtIns = $pdo->prepare("INSERT INTO Juego (NombreJuego, Url, lo_tenemos) VALUES (?, ?, ?)");
                    $stmtIns->execute([$nombreJuego, $rutaImagen, $loTenemos]);
                    if ($loTenemos === 1) {
                        $mensajeExito = "El juego «{$nombreJuego}» se ha guardado como 'Ya lo tenemos'. Ya está disponible en las Votaciones y en el Leaderboard.";
                    } else {
                        $mensajeExito = "El juego «{$nombreJuego}» se ha registrado en la lista de Juegos que NO tenemos para que la cofradía pueda apuntarse como interesada.";
                    }
                }
            }
        }
    }

    // 2. Apuntarse o desapuntarse como interesado en un juego que NO tenemos
    elseif ($accion === 'toggle_interes') {
        $nombreJuego = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';

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

    // 3. Cualquier usuario puede cambiar el estado de "No lo tenemos" a "Ya lo tenemos" (o viceversa)
    elseif ($accion === 'cambiar_estado_tenencia') {
        $nombreJuego = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';
        $nuevoEstado = (isset($_POST['nuevo_estado']) && $_POST['nuevo_estado'] === '1') ? 1 : 0;

        $stmtUpd = $pdo->prepare("UPDATE Juego SET lo_tenemos = ? WHERE NombreJuego = ?");
        $stmtUpd->execute([$nuevoEstado, $nombreJuego]);

        if ($stmtUpd->rowCount() > 0) {
            if ($nuevoEstado === 1) {
                $mensajeExito = "¡Excelente noticia! «{$nombreJuego}» ha pasado a 'Ya lo tenemos' y ahora aparece en las Votaciones y en el Leaderboard.";
            } else {
                $mensajeExito = "El estado de «{$nombreJuego}» ha cambiado a 'No lo tenemos' y ahora aparece en esta tabla de interesados.";
            }
        }
    }
}

// Obtener ÚNICAMENTE los juegos que NO tenemos (lo_tenemos = 0) para la tabla principal
$stmtNoTenemos = $pdo->query("SELECT NombreJuego, Url, lo_tenemos FROM Juego WHERE lo_tenemos = 0 ORDER BY NombreJuego ASC");
$juegosNoTenemos = $stmtNoTenemos->fetchAll();

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

// Obtener lista de juegos que sí tenemos por si cualquier usuario necesita revertir su estado
$stmtSiTenemos = $pdo->query("SELECT NombreJuego FROM Juego WHERE lo_tenemos = 1 ORDER BY NombreJuego ASC");
$juegosSiTenemos = $stmtSiTenemos->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Juegos Deseados - La Taberna del Dado de Hierro</title>
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

    <div class="grid-dos-columnas">
        <!-- Formulario para registrar un nuevo juego -->
        <section class="pergamino">
            <h2 class="seccion-titulo">Registrar Nuevo Juego de Mesa</h2>
            <form method="POST" action="juegos.php" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="crear_juego">

                <div class="grupo-form">
                    <label for="NombreJuego">Nombre del Juego (Único):</label>
                    <input type="text" id="NombreJuego" name="NombreJuego" class="input-taberna" required maxlength="120" placeholder="Ej. Catan, Carcassonne, Gloomhaven...">
                </div>

                <div class="grupo-form">
                    <label for="imagen_juego">Imagen de la Caja / Tablero (Máx 5 MB):</label>
                    <input type="file" id="imagen_juego" name="imagen_juego" class="input-taberna" accept="image/*" required data-preview-target="preview-nuevo-juego">
                    <div id="preview-nuevo-juego" class="preview-contenedor">
                        <p style="font-size:0.85rem; margin-bottom:4px;">Previsualización:</p>
                        <img src="" alt="Previsualización">
                    </div>
                </div>

                <div class="grupo-form">
                    <label>¿Lo tenemos ya en la Taberna?</label>
                    <div class="radio-grupo">
                        <label class="radio-item">
                            <input type="radio" name="lo_tenemos" value="0" checked>
                            No lo tenemos (Aparecerá aquí para apuntarse como interesado)
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="lo_tenemos" value="1">
                            Ya lo tenemos (Aparecerá en Votaciones y Leaderboard)
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primario">Inscribir Juego en el Registro</button>
            </form>
        </section>

        <!-- Cambio rápido de estado disponible para cualquier usuario -->
        <section class="pergamino">
            <h2 class="seccion-titulo">Gestión de Disponibilidad</h2>
            <p style="margin-bottom:12px;">
                En la tabla inferior se muestran <strong>únicamente los juegos que NO tenemos</strong> para que cualquiera pueda apuntarse como interesado.
            </p>
            <p style="margin-bottom:14px;">
                Cualquier miembro de la taberna puede cambiar el estado de un juego en cualquier momento: si habéis adquirido un juego de la tabla inferior, pulsa <em>«¡Ya lo tenemos!»</em> en su fila. Si necesitas devolver un juego de la estantería a la lista de deseados, selecciónalo aquí:
            </p>

            <?php if (count($juegosSiTenemos) > 0): ?>
                <form method="POST" action="juegos.php" class="pergamino-secundario">
                    <input type="hidden" name="accion" value="cambiar_estado_tenencia">
                    <input type="hidden" name="nuevo_estado" value="0">
                    <div class="grupo-form">
                        <label for="juego_revertir">Pasar juego de «Ya lo tenemos» a «No lo tenemos»:</label>
                        <select id="juego_revertir" name="NombreJuego" class="select-taberna" required>
                            <?php foreach ($juegosSiTenemos as $jst): ?>
                                <option value="<?php echo h($jst['NombreJuego']); ?>"><?php echo h($jst['NombreJuego']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-bronce btn-pequeno">Cambiar a «No lo tenemos»</button>
                </form>
            <?php else: ?>
                <div class="pergamino-secundario">
                    <em>Actualmente no hay juegos marcados como «Ya lo tenemos».</em>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Tabla exclusiva de juegos que NO tenemos -->
    <section class="pergamino">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
            <h2 class="seccion-titulo" style="margin-bottom:0; border-bottom:none;">
                Tabla de Juegos que NO Tenemos (Interesados)
            </h2>
            <div style="min-width:260px;">
                <input type="text" class="input-taberna" placeholder="Filtrar juegos o interesados..." data-table-filter="tabla-juegos-deseados">
            </div>
        </div>

        <?php if (count($juegosNoTenemos) === 0): ?>
            <p style="font-style:italic; padding:12px 0;">
                No hay juegos pendientes en la lista de deseados. Registra uno arriba indicando «No lo tenemos».
            </p>
        <?php else: ?>
            <div class="tabla-contenedor">
                <table class="tabla-taberna" id="tabla-juegos-deseados">
                    <thead>
                        <tr>
                            <th style="width:110px;">Imagen</th>
                            <th>Nombre del Juego</th>
                            <th>Parroquianos Interesados</th>
                            <th style="width:220px;">Interés</th>
                            <th style="width:210px;">Disponibilidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($juegosNoTenemos as $juego): ?>
                            <?php
                            $nombreJ = $juego['NombreJuego'];
                            $listaInt = isset($mapaInteresados[$nombreJ]) ? $mapaInteresados[$nombreJ] : [];
                            $estoyApuntado = false;
                            foreach ($listaInt as $intUser) {
                                if ($intUser['username'] === $usuarioActual) {
                                    $estoyApuntado = true;
                                    break;
                                }
                            }
                            ?>
                            <tr>
                                <td>
                                    <img src="<?php echo h($juego['Url']); ?>" alt="<?php echo h($nombreJ); ?>" class="img-juego-tabla">
                                </td>
                                <td>
                                    <strong style="font-size:1.1rem;"><?php echo h($nombreJ); ?></strong>
                                    <div style="margin-top:4px;">
                                        <span class="etiqueta etiqueta-pendiente">No lo tenemos</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if (count($listaInt) === 0): ?>
                                        <em style="color:#5a4634;">Nadie se ha apuntado todavía.</em>
                                    <?php else: ?>
                                        <ul class="lista-interesados">
                                            <?php foreach ($listaInt as $intUser): ?>
                                                <li>
                                                    <strong><?php echo h($intUser['NombrePersona']); ?></strong>
                                                    (<?php echo h($intUser['username']); ?>)
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" action="juegos.php">
                                        <input type="hidden" name="accion" value="toggle_interes">
                                        <input type="hidden" name="NombreJuego" value="<?php echo h($nombreJ); ?>">
                                        <?php if ($estoyApuntado): ?>
                                            <button type="submit" class="btn btn-peligro btn-pequeno" style="width:100%;">
                                                Desapuntarme del Juego
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-exito btn-pequeno" style="width:100%;">
                                                Apuntarme como Interesado
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="juegos.php">
                                        <input type="hidden" name="accion" value="cambiar_estado_tenencia">
                                        <input type="hidden" name="NombreJuego" value="<?php echo h($nombreJ); ?>">
                                        <input type="hidden" name="nuevo_estado" value="1">
                                        <button type="submit" class="btn btn-bronce btn-pequeno" style="width:100%;">
                                            Marcar «Ya lo tenemos»
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<script src="app.js"></script>
</body>
</html>