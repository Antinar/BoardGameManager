<?php
date_default_timezone_set('Europe/Madrid');

// Configuración segura de la sesión (debe aplicarse antes de session_start()).
if (session_status() === PHP_SESSION_NONE) {
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    $proto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $puerto = (string)($_SERVER['SERVER_PORT'] ?? '');
    $esHttps = in_array($https, ['on', '1', 'true'], true)
        || $proto === 'https'
        || $puerto === '443';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $esHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    session_start();
}

// Cargar configuración generada por GitHub Actions (BD y SMTP)
if (file_exists(__DIR__ . '/smtp_config.php')) {
    require_once __DIR__ . '/smtp_config.php';
}

// =========================================================================
// CONFIGURACIÓN DE CONEXIÓN A BASE DE DATOS (DESDE SECRETOS DE GITHUB)
// =========================================================================
$db_host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost');
$db_name = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: '');
$db_user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: '');
$db_pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');

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
    // Sincronizar zona horaria de MySQL con Europe/Madrid (+01:00 o +02:00 según horario de verano/invierno)
    $offsetMadrid = (new DateTime('now', new DateTimeZone('Europe/Madrid')))->format('P');
    $pdo->exec("SET time_zone = '{$offsetMadrid}'");
} catch (PDOException $e) {
    error_log("Error de conexión BD: " . $e->getMessage());
    die("Error al conectar con los archivos de la Taberna (Base de Datos).");
}

/**
 * Escapa texto de forma segura para imprimir en HTML.
 */
function h($cadena) {
    return htmlspecialchars((string)$cadena, ENT_QUOTES, 'UTF-8');
}

// Mensaje común para peticiones bloqueadas por CSRF.
define('MENSAJE_CSRF_INVALIDO', 'Acción bloqueada: el sello de seguridad de la petición no es válido o ha caducado.');

/**
 * Devuelve el token CSRF de la sesión, generándolo la primera vez.
 * No se rota en cada petición para no invalidar otras pestañas abiertas.
 */
function generarTokenCsrf() {
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Campo oculto listo para insertar en un formulario.
 */
function campoCsrf() {
    return '<input type="hidden" name="csrf_token" value="' . h(generarTokenCsrf()) . '">';
}

/**
 * Valida el token CSRF recibido por POST contra el almacenado en sesión.
 */
function verificarTokenCsrf() {
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        return false;
    }
    if (!isset($_POST['csrf_token']) || !is_string($_POST['csrf_token']) || $_POST['csrf_token'] === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/**
 * Decodifica campos de texto largo enviados en Base64 desde el cliente
 * para evitar falsos positivos 403 Forbidden del WAF openresty en InfinityFree.
 */
function obtenerTextoPostSeguro($nombreCampo) {
    $campoHex = $nombreCampo . '_hex';
    if (isset($_POST[$campoHex]) && $_POST[$campoHex] !== '') {
        $hex = preg_replace('/[^0-9a-fA-F]/', '', (string)$_POST[$campoHex]);
        if ($hex !== '' && strlen($hex) % 2 === 0) {
            $bin = @hex2bin($hex);
            if ($bin !== false) {
                return trim($bin);
            }
        }
    }
    $campoB64 = $nombreCampo . '_b64';
    if (isset($_POST[$campoB64]) && $_POST[$campoB64] !== '') {
        $decodificado = base64_decode((string)$_POST[$campoB64], true);
        if ($decodificado !== false) {
            return trim($decodificado);
        }
    }
    return isset($_POST[$nombreCampo]) ? trim((string)$_POST[$nombreCampo]) : '';
}

/**
 * Devuelve la escala oficial de calificaciones (0 a 10).
 */
function obtenerEscalaCalificaciones() {
    return [
        0  => 'Injugable',
        1  => 'Malo malo malo',
        2  => 'Malo',
        3  => 'Meh',
        4  => 'No funciona bien',
        5  => 'Not my cup of tea but...',
        6  => 'Tiene sus cosas',
        7  => 'Ta bien',
        8  => 'Lowkey',
        9  => 'This is my shit',
        10 => '¿Pa cuando otra?'
    ];
}

/**
 * Verifica que el usuario tenga sesión iniciada y sincroniza sus datos actuales con la BD.
 */
function requerirLogin(PDO $pdo) {
    if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
        header('Location: login.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT username, NombrePersona, email, rol FROM Persona WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $_SESSION['username']      = $usuario['username'];
    $_SESSION['NombrePersona'] = $usuario['NombrePersona'];
    $_SESSION['email']         = $usuario['email'];
    $_SESSION['rol']           = $usuario['rol'];
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
    $directorio = __DIR__ . '/uploads';
    if (!is_dir($directorio)) {
        if (!@mkdir($directorio, 0755, true)) {
            $errorMsg = "No se pudo crear el directorio uploads/ en el servidor.";
            return false;
        }
    }

    // 1. Vía principal anti-403: Imagen procesada en cliente y enviada en Hexadecimal por POST estándar
    if (isset($_POST['imagen_cliente_hex']) && $_POST['imagen_cliente_hex'] !== '') {
        $hex = preg_replace('/[^0-9a-fA-F]/', '', (string)$_POST['imagen_cliente_hex']);
        if ($hex === '' || strlen($hex) % 2 !== 0) {
            $errorMsg = "Los datos de la imagen enviada están incompletos.";
            return false;
        }

        $binario = @hex2bin($hex);
        if ($binario === false || strlen($binario) < 32) {
            $errorMsg = "No se pudo decodificar la imagen enviada.";
            return false;
        }

        $maxBytes = 5 * 1024 * 1024;
        if (strlen($binario) > $maxBytes) {
            $errorMsg = "La imagen supera el tamaño máximo permitido de 5 MB.";
            return false;
        }

        if (function_exists('getimagesizefromstring')) {
            $infoImg = @getimagesizefromstring($binario);
            if ($infoImg === false) {
                $errorMsg = "El archivo seleccionado no es una imagen válida.";
                return false;
            }
        }

        $nombreOrig = isset($_POST['imagen_cliente_nombre']) ? (string)$_POST['imagen_cliente_nombre'] : 'juego.jpg';
        $nombreLimpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($nombreOrig, PATHINFO_FILENAME));
        $nombreLimpiado = substr($nombreLimpio, 0, 40);
        if ($nombreLimpiado === '') {
            $nombreLimpiado = 'juego';
        }

        $nombreFinal = $nombreLimpiado . '_' . time() . '_' . mt_rand(100, 999) . '.jpg';
        $rutaDestinoServidor = $directorio . '/' . $nombreFinal;
        $rutaRelativaBD = 'uploads/' . $nombreFinal;

        if (@file_put_contents($rutaDestinoServidor, $binario) === false) {
            $errorMsg = "No se pudo guardar la imagen en la carpeta uploads/.";
            return false;
        }

        return $rutaRelativaBD;
    }

    // 2. Respaldo clásico por $_FILES
    if (!isset($_FILES[$campoArchivo]) || $_FILES[$campoArchivo]['error'] === UPLOAD_ERR_NO_FILE) {
        $errorMsg = "Debes seleccionar un archivo de imagen para el juego.";
        return false;
    }

    $archivo = $_FILES[$campoArchivo];

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = "Error al subir el archivo (Código: " . (int)$archivo['error'] . ").";
        return false;
    }

    $maxBytes = 5 * 1024 * 1024;
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
    <script>
    if (!document.querySelector('link[rel="icon"]')) {
        var fav = document.createElement('link');
        fav.rel = 'icon';
        fav.type = 'image/png';
        fav.href = 'img/logo.png';
        document.head.appendChild(fav);
    }
    </script>
    <header class="taberna-header">
    <div class="taberna-titulo-bloque">
    <a href="index.php" class="header-logo-enlace" title="Ir al Inicio">
    <img src="img/logo.png" alt="Logo La Taberna de las Gatitas Jugonas" class="header-logo-img">
    </a>
    <a href="index.php" class="header-titulo-enlace">
    <h1 class="taberna-titulo">La Taberna de las Gatitas Jugonas</h1>
    </a>
    </div>
        <div class="taberna-usuario-bloque">
            <span class="usuario-etiqueta">
                <strong><?php echo h($nombreMostrar); ?></strong> (<?php echo h($userMostrar); ?>)
                <?php if ($esAdmin): ?>
                    <span class="insignia-rol">[Admin]</span>
                <?php endif; ?>
            </span>
            <a href="perfil.php" class="btn btn-bronce btn-pequeno <?php echo $paginaActiva === 'perfil' ? 'activo' : ''; ?>">Mi Perfil</a>
            <a href="logout.php" class="btn btn-peligro btn-pequeno">Salir</a>
        </div>
    </header>

    <nav class="taberna-nav">
        <a href="index.php" class="nav-enlace <?php echo $paginaActiva === 'index' ? 'activo' : ''; ?>">Inicio</a>
        <a href="ludoteca.php" class="nav-enlace <?php echo $paginaActiva === 'ludoteca' ? 'activo' : ''; ?>">Ludoteca</a>
        <a href="juegos.php" class="nav-enlace <?php echo $paginaActiva === 'juegos' ? 'activo' : ''; ?>">Juegos Deseados</a>
        <a href="alta_juego.php" class="nav-enlace <?php echo $paginaActiva === 'alta_juego' ? 'activo' : ''; ?>">Subir Juego</a>
        <a href="leaderboard.php" class="nav-enlace <?php echo $paginaActiva === 'leaderboard' ? 'activo' : ''; ?>">Leaderboard</a>
        <a href="votaciones.php" class="nav-enlace <?php echo $paginaActiva === 'votaciones' ? 'activo' : ''; ?>">Votaciones</a>
        <?php if ($esAdmin): ?>
            <a href="admin.php" class="nav-enlace nav-admin <?php echo $paginaActiva === 'admin' ? 'activo' : ''; ?>">Admin</a>
        <?php endif; ?>
    </nav>
    <?php
}

/**
 * Componente reutilizable de buscador para tablas y cuadrículas de tarjetas.
 *
 * @param string $placeholder Texto de ayuda del input.
 * @param array  $opciones    Opciones: 'id', 'target_table' (id de tabla), 'target_cards' (id contenedor de tarjetas), 'clase_contenedor'.
 */
function renderizarBuscador($placeholder = 'Buscar...', array $opciones = []) {
    $idInput = isset($opciones['id']) && $opciones['id'] !== '' ? ' id="' . h($opciones['id']) . '"' : '';
    $attrTabla = isset($opciones['target_table']) && $opciones['target_table'] !== '' ? ' data-table-filter="' . h($opciones['target_table']) . '"' : '';
    $attrCards = isset($opciones['target_cards']) && $opciones['target_cards'] !== '' ? ' data-card-filter="' . h($opciones['target_cards']) . '"' : '';
    $claseCont = isset($opciones['clase_contenedor']) && $opciones['clase_contenedor'] !== '' ? ' ' . h($opciones['clase_contenedor']) : '';
    ?>
    <div class="buscador-bloque<?php echo $claseCont; ?>">
        <input type="text"<?php echo $idInput; ?> class="input-taberna" placeholder="<?php echo h($placeholder); ?>"<?php echo $attrTabla . $attrCards; ?>>
    </div>
    <?php
}


/**
 * Lee la respuesta del servidor SMTP.
 */
function leerRespuestaSMTP($socket) {
    $respuesta = '';
    while ($linea = fgets($socket, 515)) {
        $respuesta .= $linea;
        if (isset($linea[3]) && $linea[3] === ' ') {
            break;
        }
    }
    return $respuesta;
}

/**
 * Envía un comando SMTP y verifica el código de estado esperado.
 */
function comandoSMTP($socket, $comando, array $codigosValidos = [250]) {
    if ($comando !== null) {
        fwrite($socket, $comando . "\r\n");
    }
    $respuesta = leerRespuestaSMTP($socket);
    $codigo = (int)substr($respuesta, 0, 3);
    return in_array($codigo, $codigosValidos, true);
}

/**
 * Envía un correo electrónico mediante conexión directa SMTP + STARTTLS (Gmail).
 */
function enviarCorreoSMTP($destinatarioEmail, $destinatarioNombre, $asunto, $cuerpoHtml) {
    $host = defined('SMTP_HOST') ? SMTP_HOST : (getenv('SMTP_HOST') ?: 'smtp.gmail.com');
    $port = defined('SMTP_PORT') ? (int)SMTP_PORT : (int)(getenv('SMTP_PORT') ?: 587);
    $user = defined('SMTP_USER') ? SMTP_USER : (getenv('SMTP_USER') ?: '');
    $pass = str_replace(' ', '', defined('SMTP_PASS') ? SMTP_PASS : (getenv('SMTP_PASS') ?: ''));
    $from = defined('SMTP_FROM') ? SMTP_FROM : (getenv('SMTP_FROM') ?: $user);
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'La Taberna de las Gatitas Jugonas';

    if ($user === '' || $pass === '' || $from === '' || $destinatarioEmail === '') {
        return false;
    }

    $contexto = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $contexto);
    if (!$socket) {
        return false;
    }
    stream_set_timeout($socket, 10);

    if (!comandoSMTP($socket, null, [220])) { fclose($socket); return false; }
    if (!comandoSMTP($socket, "EHLO gatitasjugonas.gt.tc", [250])) { fclose($socket); return false; }

    if ($port === 587) {
        if (!comandoSMTP($socket, "STARTTLS", [220])) { fclose($socket); return false; }
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        if (!comandoSMTP($socket, "EHLO gatitasjugonas.gt.tc", [250])) { fclose($socket); return false; }
    }

    if (!comandoSMTP($socket, "AUTH LOGIN", [334])) { fclose($socket); return false; }
    if (!comandoSMTP($socket, base64_encode($user), [334])) { fclose($socket); return false; }
    if (!comandoSMTP($socket, base64_encode($pass), [235])) { fclose($socket); return false; }

    if (!comandoSMTP($socket, "MAIL FROM:<{$from}>", [250])) { fclose($socket); return false; }
    if (!comandoSMTP($socket, "RCPT TO:<{$destinatarioEmail}>", [250, 251])) { fclose($socket); return false; }
    if (!comandoSMTP($socket, "DATA", [354])) { fclose($socket); return false; }

    $asuntoCodificado = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
    $remitenteCodificado = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $destCodificado = '=?UTF-8?B?' . base64_encode($destinatarioNombre) . '?=';

    $cabeceras  = "Date: " . date('r') . "\r\n";
    $cabeceras .= "From: {$remitenteCodificado} <{$from}>\r\n";
    $cabeceras .= "To: {$destCodificado} <{$destinatarioEmail}>\r\n";
    $cabeceras .= "Subject: {$asuntoCodificado}\r\n";
    $cabeceras .= "MIME-Version: 1.0\r\n";
    $cabeceras .= "Content-Type: text/html; charset=UTF-8\r\n";
    $cabeceras .= "Content-Transfer-Encoding: base64\r\n\r\n";

    $mensajeFinal = $cabeceras . chunk_split(base64_encode($cuerpoHtml)) . "\r\n.";
    $exito = comandoSMTP($socket, $mensajeFinal, [250]);

    comandoSMTP($socket, "QUIT", [221, 250]);
    fclose($socket);
    return $exito;
}

/**
 * Envía un correo a todos los usuarios con email registrado cuando se abre una nueva votación.
 */
function notificarNuevaVotacionPorEmail(PDO $pdo, $tituloVotacion, $fechaLimite, $tipoVoto, $creadorNombre) {
    $stmt = $pdo->query("SELECT NombrePersona, email FROM Persona WHERE email IS NOT NULL AND TRIM(email) <> ''");
    $destinatarios = $stmt->fetchAll();

    if (count($destinatarios) === 0) {
        return 0;
    }

    $tipoTexto = ($tipoVoto === 'multiple') ? 'Voto Múltiple (varios juegos)' : 'Voto Único (un solo juego)';
    $asunto = "Nueva votación en la Taberna: «{$tituloVotacion}»";
    $enviados = 0;

    foreach ($destinatarios as $dest) {
        $email = trim($dest['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $nombre = $dest['NombrePersona'];
        $cuerpo = "
        <div style='background-color:#e8dcb8; border:3px solid #362417; padding:20px; color:#23170f; font-family:Georgia, serif;'>
            <h2 style='color:#362417; border-bottom:2px solid #4a3219; padding-bottom:8px; margin-top:0;'>
                La Taberna de las Gatitas Jugonas
            </h2>
            <p>¡Hola, <strong>" . h($nombre) . "</strong>!</p>
            <p><strong>" . h($creadorNombre) . "</strong> acaba de convocar una nueva votación de juegos en la taberna:</p>
            <div style='background-color:#dfd1a7; border:2px solid #4a3219; padding:12px; margin:14px 0;'>
                <p style='margin:4px 0;'><strong>Motivo / Título:</strong> " . h($tituloVotacion) . "</p>
                <p style='margin:4px 0;'><strong>Modalidad:</strong> " . h($tipoTexto) . "</p>
                <p style='margin:4px 0;'><strong>Fecha límite para votar:</strong> " . h($fechaLimite) . "</p>
            </div>
            <p>Entra en la taberna para dejar tu voto:</p>
            <p>
                <a href='https://gatitasjugonas.gt.tc/votaciones.php'
                   style='display:inline-block; background-color:#8c2d19; color:#f7f1df; padding:10px 16px; text-decoration:none; font-weight:bold; border:2px solid #1a110b;'>
                    Ir al Consejo de Votos
                </a>
            </p>
        </div>";

        if (enviarCorreoSMTP($email, $nombre, $asunto, $cuerpo)) {
            $enviados++;
        }
    }

    return $enviados;
}
?>
