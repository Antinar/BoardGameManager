<?php
require_once 'conexion.php';
requerirLogin($pdo);

$mensajeExito = '';
$mensajeError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_juego') {
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
            $mensajeError = 'Ya existe un juego registrado con ese nombre.';
        } else {
            $errorImg = '';
            $rutaImagen = procesarSubidaImagen('imagen_juego', $errorImg);
            if ($rutaImagen === false) {
                $mensajeError = $errorImg;
            } else {
                $stmtIns = $pdo->prepare("INSERT INTO Juego (NombreJuego, Url, lo_tenemos) VALUES (?, ?, ?)");
                $stmtIns->execute([$nombreJuego, $rutaImagen, $loTenemos]);
                $mensajeExito = "Juego «{$nombreJuego}» registrado correctamente.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Juego - La Taberna del Dado de Hierro</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php renderizarNavegacion('alta_juego'); ?>

<main class="contenedor-principal">
    <section class="pergamino" style="max-width: 680px; margin: 0 auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
            <h2 class="seccion-titulo" style="margin-bottom:0; border-bottom:none;">Alta de Juego de Mesa</h2>
            <a href="juegos.php" class="btn btn-bronce btn-pequeno">Ver Juegos Deseados</a>
        </div>

        <?php if ($mensajeError !== ''): ?>
            <div class="alerta alerta-error"><?php echo h($mensajeError); ?></div>
        <?php endif; ?>

        <?php if ($mensajeExito !== ''): ?>
            <div class="alerta alerta-exito"><?php echo h($mensajeExito); ?></div>
        <?php endif; ?>

        <form method="POST" action="alta_juego.php" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="crear_juego">

            <div class="grupo-form">
                <label for="NombreJuego">Nombre del Juego:</label>
                <input type="text" id="NombreJuego" name="NombreJuego" class="input-taberna" required maxlength="120" placeholder="Ej. Catan, Carcassonne...">
            </div>

            <div class="grupo-form">
                <label for="imagen_juego">Imagen (Máx 5 MB):</label>
                <input type="file" id="imagen_juego" name="imagen_juego" class="input-taberna" accept="image/*" required data-preview-target="preview-nuevo-juego">
                <div id="preview-nuevo-juego" class="preview-contenedor">
                    <img src="" alt="Previsualización">
                </div>
            </div>

            <div class="grupo-form">
                <label>Estado en la Taberna:</label>
                <div class="radio-grupo">
                    <label class="radio-item">
                        <input type="radio" name="lo_tenemos" value="0" checked>
                        No lo tenemos (Para interesados)
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="lo_tenemos" value="1">
                        Ya lo tenemos (Para votar y Leaderboard)
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primario" style="width:100%;">Guardar Juego</button>
        </form>
    </section>
</main>

<script src="app.js"></script>
</body>
</html>