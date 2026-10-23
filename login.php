<?php
require_once 'conexion.php';

if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

$mensajeExito = '';
$mensajeError = '';
$pestanaActiva = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    $pestanaActiva = ($accion === 'registro') ? 'registro' : 'login';

    if (!verificarTokenCsrf()) {
        $mensajeError = MENSAJE_CSRF_INVALIDO;
    } elseif ($accion === 'login') {
        $pestanaActiva = 'login';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $contrasenya = isset($_POST['contrasenya']) ? trim((string)$_POST['contrasenya']) : '';

        if ($username === '' || $contrasenya === '') {
            $mensajeError = 'Por favor, introduce tu nombre de usuario y tu contraseña.';
        } else {
            $stmt = $pdo->prepare("SELECT username, NombrePersona, contrasenya, rol, inscrito_leaderboard FROM Persona WHERE username = ?");
            $stmt->execute([$username]);
            $usuario = $stmt->fetch();

            $loginCorrecto = false;

            if ($usuario) {
                $hashActual = (string)$usuario['contrasenya'];
                $coincideHash = password_verify($contrasenya, $hashActual);
                $coincideTextoPlano = false;
                $infoHashActual = password_get_info($hashActual);

                // Se evita pass-the-hash: el fallback de texto plano solo se permite
                // si lo almacenado no es un hash reconocido (bcrypt/argon/etc.).
                if (!$coincideHash && $hashActual !== '' && $infoHashActual['algoName'] === 'unknown') {
                    $coincideTextoPlano = hash_equals($hashActual, $contrasenya);
                }

                $loginCorrecto = $coincideHash || $coincideTextoPlano;

                if ($loginCorrecto) {
                    // Migración progresiva: solo se rehashea un hash válido verificado
                    // o una contraseña heredada en texto plano.
                    $necesitaRehash = $coincideTextoPlano
                        || ($coincideHash && password_needs_rehash($hashActual, PASSWORD_DEFAULT));

                    if ($necesitaRehash) {
                        $nuevoHash = password_hash($contrasenya, PASSWORD_DEFAULT);
                        if ($nuevoHash !== false) {
                            try {
                                $stmtUpd = $pdo->prepare("UPDATE Persona SET contrasenya = ? WHERE username = ?");
                                $stmtUpd->execute([$nuevoHash, $usuario['username']]);
                            } catch (Throwable $e) {
                                // El login no debe fallar si la migración del hash no se puede guardar.
                                error_log('No se pudo migrar la contraseña a hash: ' . $e->getMessage());
                            }
                        }
                    }
                }
            }

            if ($loginCorrecto) {
                session_regenerate_id(true);
                $_SESSION['username']             = $usuario['username'];
                $_SESSION['NombrePersona']        = $usuario['NombrePersona'];
                $_SESSION['rol']                  = $usuario['rol'];
                $_SESSION['inscrito_leaderboard'] = (int)$usuario['inscrito_leaderboard'];
                header('Location: index.php');
                exit;
            } else {
                $mensajeError = 'Credenciales incorrectas. El guardián de la puerta no te reconoce.';
            }
        }
    } elseif ($accion === 'registro') {
        $pestanaActiva = 'registro';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $nombrePersona = isset($_POST['NombrePersona']) ? trim($_POST['NombrePersona']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $contrasenya = isset($_POST['contrasenya']) ? trim((string)$_POST['contrasenya']) : '';

        if ($username === '' || $nombrePersona === '' || $contrasenya === '') {
            $mensajeError = 'El usuario, nombre y contraseña son obligatorios para inscribirse en el libro de la taberna.';
        } elseif (mb_strlen($username) > 50 || mb_strlen($nombrePersona) > 120 || mb_strlen($email) > 150) {
            $mensajeError = 'Alguno de los campos supera la longitud máxima permitida.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensajeError = 'El formato del correo electrónico introducido no es válido.';
        } else {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Persona WHERE username = ?");
            $stmtCheck->execute([$username]);

            if ((int)$stmtCheck->fetchColumn() > 0) {
                $mensajeError = 'Ese nombre de usuario ya pertenece a otro parroquiano. Elige otro.';
            } else {
                $hash = password_hash($contrasenya, PASSWORD_DEFAULT);

                if ($hash === false) {
                    $mensajeError = 'No se pudo completar el registro en este momento. Inténtalo de nuevo más tarde.';
                } else {
                    $emailGuardar = ($email !== '') ? $email : null;
                    $stmtIns = $pdo->prepare("INSERT INTO Persona (username, NombrePersona, email, contrasenya, rol, inscrito_leaderboard) VALUES (?, ?, ?, ?, 'jugador', 1)");
                    $stmtIns->execute([$username, $nombrePersona, $emailGuardar, $hash]);

                    session_regenerate_id(true);
                    $_SESSION['username']             = $username;
                    $_SESSION['NombrePersona']        = $nombrePersona;
                    $_SESSION['email']                = $emailGuardar;
                    $_SESSION['rol']                  = 'jugador';
                    $_SESSION['inscrito_leaderboard'] = 1;

                    header('Location: index.php');
                    exit;
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
    <title>Entrada a la Taberna - La Taberna de las Gatitas Jugonas</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="login-envoltorio">
    <div class="pergamino">
        <h1 class="seccion-titulo" style="text-align:center;">La Taberna de las Gatitas Jugonas</h1>

        <?php if ($mensajeError !== ''): ?>
            <div class="alerta alerta-error"><?php echo h($mensajeError); ?></div>
        <?php endif; ?>

        <?php if ($mensajeExito !== ''): ?>
            <div class="alerta alerta-exito"><?php echo h($mensajeExito); ?></div>
        <?php endif; ?>

        <div class="pestanas-barra">
            <button type="button"
                    class="pestana-btn <?php echo $pestanaActiva === 'login' ? 'activa' : ''; ?>"
                    data-tab-group="auth"
                    data-tab-target="#panel-login">
                Entrar a la Taberna (Login)
            </button>
            <button type="button"
                    class="pestana-btn <?php echo $pestanaActiva === 'registro' ? 'activa' : ''; ?>"
                    data-tab-group="auth"
                    data-tab-target="#panel-registro">
                Nuevo Miembro (Alta)
            </button>
        </div>

        <!-- Formulario de Login -->
        <div id="panel-login" data-tab-content-group="auth" class="<?php echo $pestanaActiva === 'login' ? '' : 'panel-oculto'; ?>">
            <form method="POST" action="login.php">
                <input type="hidden" name="accion" value="login">
                <?php echo campoCsrf(); ?>
                <div class="grupo-form">
                    <label for="login_username">Nombre de Usuario (username):</label>
                    <input type="text" id="login_username" name="username" class="input-taberna" required maxlength="50">
                </div>
                <div class="grupo-form">
                    <label for="login_contrasenya">Contraseña:</label>
                    <input type="password" id="login_contrasenya" name="contrasenya" class="input-taberna" required>
                </div>
                <button type="submit" class="btn btn-primario" style="width:100%;">Cruzar el Umbral</button>
            </form>
        </div>

        <!-- Formulario de Alta Sencilla -->
        <div id="panel-registro" data-tab-content-group="auth" class="<?php echo $pestanaActiva === 'registro' ? '' : 'panel-oculto'; ?>">
            <form method="POST" action="login.php">
                <input type="hidden" name="accion" value="registro">
                <?php echo campoCsrf(); ?>
                <div class="grupo-form">
                    <label for="reg_username">Nombre de Usuario (único):</label>
                    <input type="text" id="reg_username" name="username" class="input-taberna" required maxlength="50">
                </div>
                <div class="grupo-form">
                    <label for="reg_nombre">Nombre y Apellidos (NombrePersona):</label>
                    <input type="text" id="reg_nombre" name="NombrePersona" class="input-taberna" required maxlength="120">
                </div>

                <div class="grupo-form">
                    <label for="reg_email">Correo Electrónico (opcional, para avisos de votaciones):</label>
                    <input type="email" id="reg_email" name="email" class="input-taberna" maxlength="150" placeholder="ejemplo@correo.com">
                </div>

                <div class="grupo-form">
                    <label for="reg_contrasenya">Contraseña (sin restricciones):</label>
                    <input type="password" id="reg_contrasenya" name="contrasenya" class="input-taberna" required>
                </div>
                <button type="submit" class="btn btn-exito" style="width:100%;">Firmar Alta y Entrar</button>
            </form>
        </div>
    </div>
</div>

<script src="app.js"></script>
</body>
</html>