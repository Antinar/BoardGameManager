<?php
require_once 'conexion.php';
requerirLogin($pdo);

$mensajeExito = '';
$mensajeError = '';

// Obtener todos los usuarios para el selector multiselección de propietarios
$stmtPersonas = $pdo->query("SELECT username, NombrePersona FROM Persona ORDER BY NombrePersona ASC");
$listaPersonas = $stmtPersonas->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_juego') {
    $nombreJuego = isset($_POST['NombreJuego']) ? trim($_POST['NombreJuego']) : '';
    $loTenemos = (isset($_POST['lo_tenemos']) && $_POST['lo_tenemos'] === '1') ? 1 : 0;
    $minJugadores = isset($_POST['min_jugadores']) ? (int)$_POST['min_jugadores'] : 0;
    $maxJugadores = isset($_POST['max_jugadores']) ? (int)$_POST['max_jugadores'] : 0;
    $descripcion = obtenerTextoPostSeguro('descripcion');
    $reglas = obtenerTextoPostSeguro('reglas');
    $propietarios = (isset($_POST['propietarios']) && is_array($_POST['propietarios'])) ? $_POST['propietarios'] : [];

    if ($nombreJuego === '') {
        $mensajeError = 'El nombre del juego es obligatorio.';
    } elseif (mb_strlen($nombreJuego) > 120) {
        $mensajeError = 'El nombre del juego no puede superar los 120 caracteres.';
    } elseif ($minJugadores < 1 || $maxJugadores < 1 || $maxJugadores < $minJugadores) {
        $mensajeError = 'Indica un número de jugadores válido (el máximo debe ser mayor o igual que el mínimo).';
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
                $descGuardar = ($descripcion !== '') ? $descripcion : null;
                $reglasGuardar = ($reglas !== '') ? $reglas : null;

                $pdo->beginTransaction();
                try {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO Juego (NombreJuego, Url, lo_tenemos, min_jugadores, max_jugadores, descripcion, reglas)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtIns->execute([$nombreJuego, $rutaImagen, $loTenemos, $minJugadores, $maxJugadores, $descGuardar, $reglasGuardar]);

                    // Si "Ya lo tenemos", guardar los propietarios seleccionados
                    if ($loTenemos === 1 && count($propietarios) > 0) {
                        $stmtInsProp = $pdo->prepare("INSERT IGNORE INTO Juego_Propietario (username, NombreJuego) VALUES (?, ?)");
                        foreach ($propietarios as $userProp) {
                            $userPropLimpio = trim((string)$userProp);
                            if ($userPropLimpio !== '') {
                                $stmtInsProp->execute([$userPropLimpio, $nombreJuego]);
                            }
                        }
                    }

                    $pdo->commit();
                    $mensajeExito = "Juego «{$nombreJuego}» registrado correctamente.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $mensajeError = "Error al guardar el juego: " . $e->getMessage();
                }
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
    <title>Subir Juego - La Taberna de las Gatitas Jugonas</title>
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

        <form method="POST" action="alta_juego.php" enctype="multipart/form-data" data-form-juego-seguro>
            <input type="hidden" name="accion" value="crear_juego">

            <div class="grupo-form">
                <label for="NombreJuego">Nombre del Juego:</label>
                <input type="text" id="NombreJuego" name="NombreJuego" class="input-taberna" required maxlength="120" placeholder="Ej. Catan, Carcassonne...">
            </div>

            <div class="grid-dos-columnas" style="gap:14px;">
                <div class="grupo-form">
                    <label for="min_jugadores">Mínimo de Jugadores (obligatorio):</label>
                    <input type="number" id="min_jugadores" name="min_jugadores" class="input-taberna" required min="1" max="99" value="2">
                </div>
                <div class="grupo-form">
                    <label for="max_jugadores">Máximo de Jugadores (obligatorio):</label>
                    <input type="number" id="max_jugadores" name="max_jugadores" class="input-taberna" required min="1" max="99" value="4">
                </div>
            </div>

            <div class="grupo-form">
                <label for="descripcion">Descripción del Juego (opcional):</label>
                <textarea id="descripcion" name="descripcion" class="input-taberna" rows="3" placeholder="De qué trata el juego, ambientación, duración aproximada..."></textarea>
                <input type="hidden" name="descripcion_b64" value="">
            </div>

            <div class="grupo-form">
                <label for="reglas">Resumen de Reglas / Notas de Partida (opcional):</label>
                <textarea id="reglas" name="reglas" class="input-taberna" rows="4" placeholder="Reglas principales, preparación de la mesa o enlaces a manual..."></textarea>
                <input type="hidden" name="reglas_b64" value="">
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
                        <input type="radio" name="lo_tenemos" value="0" data-toggle-propietarios="bloque-propietarios-alta" checked>
                        No lo tenemos (Para interesados)
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="lo_tenemos" value="1" data-toggle-propietarios="bloque-propietarios-alta">
                        Ya lo tenemos (Para votar y Ludoteca)
                    </label>
                </div>
            </div>

            <!-- Desplegable con buscador multiselección de propietarios (visible solo cuando "Ya lo tenemos") -->
            <div id="bloque-propietarios-alta" class="grupo-form pergamino-secundario" style="display:none;">
                <label>¿Qué miembros tienen este juego?</label>
                <div class="multiselect-taberna" data-multiselect>
                    <input type="text" class="input-taberna" placeholder="Buscar jugador para marcar..." data-multiselect-search style="margin-bottom:8px;">
                    <div class="multiselect-lista">
                        <?php foreach ($listaPersonas as $per): ?>
                            <label class="multiselect-opcion" data-multiselect-item data-nombre="<?php echo h($per['NombrePersona'] . ' ' . $per['username']); ?>">
                                <input type="checkbox" name="propietarios[]" value="<?php echo h($per['username']); ?>">
                                <strong><?php echo h($per['NombrePersona']); ?></strong> (<?php echo h($per['username']); ?>)
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primario" style="width:100%;">Guardar Juego</button>
        </form>
    </section>
</main>

<script src="app.js"></script>
</body>
</html>