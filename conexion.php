<?php
session_start();

// =========================================================================
// CONFIGURACIÓN DE CONEXIÓN A BASE DE DATOS (AJUSTAR EN INFINITYFREE)
// =========================================================================
$db_host = 'sql209.infinityfree.com';               // En InfinityFree ej: sqlXXX.infinityfree.com
$db_name = 'if0_43192957_gatarras';     // Nombre de tu base de datos en phpMyAdmin
$db_user = 'if0_43192957';                    // En InfinityFree ej: if0_XXXXXXX
$db_pass = 'oxDMlEqYAbP';                        // Contraseña de tu cuenta de hosting

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
} catch (PDOException $e) {
    die("Error al conectar con los archivos de la Taberna (Base de Datos): " . htmlspecialchars($e->getMessage()));
}

/**
 * Escapa texto de forma segura para imprimir en HTML.
 */
function h($cadena) {
    return htmlspecialchars((string)$cadena, ENT_QUOTES, 'UTF-8');
}

/**
 * Verifica que el usuario tenga sesión iniciada y sincroniza sus datos actuales con la BD.
 */
function requerirLogin(PDO $pdo) {
    if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
        header('Location: login.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT username, NombrePersona, rol, inscrito_leaderboard FROM Persona WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $_SESSION['username']             = $usuario['username'];
    $_SESSION['NombrePersona']        = $usuario['NombrePersona'];
    $_SESSION['rol']                  = $usuario['rol'];
    $_SESSION['inscrito_leaderboard'] = (int)$usuario['inscrito_leaderboard'];
}

/**
 * Verifica que el usuario sea administrador.
 */
function requerirAdmin(PDO $pdo) {
    requerirLogin($pdo);
    if ($_SESSION['rol'] !== 'admin') {
        header('Location: index.php');
        exit;
    }
}

/**
 * Procesa la subida de una imagen de juego a la carpeta uploads/ respetando el límite de 5 MB.
 */
function procesarSubidaImagen($campoArchivo, &$errorMsg) {
    if (!isset($_FILES[$campoArchivo]) || $_FILES[$campoArchivo]['error'] === UPLOAD_ERR_NO_FILE) {
        $errorMsg = "Debes seleccionar un archivo de imagen para el juego.";
        return false;
    }

    $archivo = $_FILES[$campoArchivo];
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = "Error al subir el archivo (Código: " . (int)$archivo['error'] . ").";
        return false;
    }

    $maxBytes = 5 * 1024 * 1024; // 5 MB máximo
    if ($archivo['size'] > $maxBytes) {
        $errorMsg = "La imagen supera el tamaño máximo permitido de 5 MB.";
        return false;
    }

    $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $extensionesPermitidas, true)) {
        $errorMsg = "Formato de imagen no válido. Usa JPG, JPEG, PNG, GIF o WEBP.";
        return false;
    }

    $infoImagen = @getimagesize($archivo['tmp_name']);
    if ($infoImagen === false) {
        $errorMsg = "El archivo subido no es una imagen válida.";
        return false;
    }

    $directorio = __DIR__ . '/uploads';
    if (!is_dir($directorio)) {
        if (!mkdir($directorio, 0755, true)) {
            $errorMsg = "No se pudo crear el directorio uploads/ en el servidor.";
            return false;
        }
    }

    $nombreLimpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($archivo['name'], PATHINFO_FILENAME));
    $nombreLimpiado = substr($nombreLimpio, 0, 40);
    if ($nombreLimpiado === '') {
        $nombreLimpiado = 'juego';
    }

    $nombreFinal = $nombreLimpiado . '_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
    $rutaDestinoServidor = $directorio . '/' . $nombreFinal;
    $rutaRelativaBD = 'uploads/' . $nombreFinal;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestinoServidor)) {
        $errorMsg = "No se pudo guardar la imagen en la carpeta uploads/.";
        return false;
    }

    return $rutaRelativaBD;
}

/**
 * Renderiza la barra superior de navegación de la taberna.
 */
function renderizarNavegacion($paginaActiva = '') {
    $esAdmin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');
    $nombreMostrar = isset($_SESSION['NombrePersona']) ? $_SESSION['NombrePersona'] : '';
    $userMostrar = isset($_SESSION['username']) ? $_SESSION['username'] : '';
    ?>
    <header class="taberna-header">
        <div class="taberna-titulo-bloque">
            <h1 class="taberna-titulo">La Taberna del Dado de Hierro</h1>
            <p class="taberna-subtitulo">Cofradía de Juegos de Mesa, Crónicas de Victoria y Consejo de Votaciones</p>
        </div>
        <div class="taberna-usuario-bloque">
            <span class="usuario-etiqueta">
                Parroquiano: <strong><?php echo h($nombreMostrar); ?></strong> (<?php echo h($userMostrar); ?>)
                <?php if ($esAdmin): ?>
                    <span class="insignia-rol">[Tabernero / Admin]</span>
                <?php endif; ?>
            </span>
            <a href="logout.php" class="btn btn-peligro btn-pequeno">Salir de la Taberna</a>
        </div>
    </header>

    <nav class="taberna-nav">
        <a href="index.php" class="nav-enlace <?php echo $paginaActiva === 'index' ? 'activo' : ''; ?>">Salón Principal</a>
        <a href="juegos.php" class="nav-enlace <?php echo $paginaActiva === 'juegos' ? 'activo' : ''; ?>">Juegos Deseados</a>
        <a href="leaderboard.php" class="nav-enlace <?php echo $paginaActiva === 'leaderboard' ? 'activo' : ''; ?>">Tablón de Campeones</a>
        <a href="votaciones.php" class="nav-enlace <?php echo $paginaActiva === 'votaciones' ? 'activo' : ''; ?>">Consejo de Votos</a>
        <?php if ($esAdmin): ?>
            <a href="admin.php" class="nav-enlace nav-admin <?php echo $paginaActiva === 'admin' ? 'activo' : ''; ?>">Despacho del Tabernero (Admin)</a>
        <?php endif; ?>
    </nav>
    <?php
}
?>
