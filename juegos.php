<?php
require_once 'conexion.php';
requerirLogin($pdo);

$mensajeExito = '';
$mensajeError = '';
$usuarioActual = $_SESSION['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    // 2. Apuntarse o desapuntarse como interesado en un juego que NO tenemos
    if ($accion === 'toggle_interes') {
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
                // Limpiar interesados previos ya que el juego se ha adquirido
                $stmtLimpiar = $pdo->prepare("DELETE FROM Persona_Juego WHERE NombreJuego = ?");
                $stmtLimpiar->execute([$nombreJuego]);
                $mensajeExito = "«{$nombreJuego}» marcado como 'Ya lo tenemos'.";
            } else {
                $mensajeExito = "«{$nombreJuego}» marcado como 'No lo tenemos'.";
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

    <!-- Tabla exclusiva de juegos que NO tenemos -->
    <section class="pergamino">
        <div class="barra-titulo-filtro">

            <h2 class="seccion-titulo">Juegos que NO Tenemos</h2>
            <?php renderizarBuscador('Filtrar juegos o interesados...', ['target_table' => 'tabla-juegos-deseados']); ?>
        </div>
        <p>
            Lista de juegos de interes para el grupo. Puedes apuntarte en los que te interese.
        </p>

        <?php if (count($juegosNoTenemos) === 0): ?>
            <p style="font-style:italic; padding:12px 0;">
                No hay juegos pendientes en la lista de deseados. Registra uno en la pestaña de «Subir Juego».
            </p>
        <?php else: ?>
            <div class="tabla-contenedor">
                <table class="tabla-taberna" id="tabla-juegos-deseados">
                    <thead>
                        <tr>
                            <th style="width:110px;">Imagen</th>
                            <th>Nombre del Juego</th>
                            <th>Miembros Interesados</th>
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

    <section class="pergamino" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <h2 class="seccion-titulo">Poner juego en interes</h2>
        <p>
            Si algun juego que tenemos por algun motivo queremos que esté en interesado en vez, se puede <strong>cambiar</strong> desde aqui.
        </p>
        <!-- <a href="alta_juego.php" class="btn btn-primario">+ Subir Nuevo Juego</a> -->

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
</main>

<script src="app.js"></script>
</body>
</html>