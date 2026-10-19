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

    if ($accion === 'login') {
        $pestanaActiva = 'login';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $contrasenya = isset($_POST['contrasenya']) ? trim((string)$_POST['contrasenya']) : '';

        if ($username === '' || $contrasenya === '') {
            $mensajeError = 'Por favor, introduce tu nombre de usuario y tu contraseña.';
        } else {
            $stmt = $pdo->prepare("SELECT username, NombrePersona, contrasenya, rol, inscrito_leaderboard FROM Persona WHERE username = ?");
            $stmt->execute([$username]);
            $usuario = $stmt->fetch();

            if ($usuario && $usuario['contrasenya'] === $contrasenya) {
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
        $contrasenya = isset($_POST['contrasenya']) ? (string)trim($_POST['contrasenya']) : '';

        if ($username === '' || $nombrePersona === '' || $contrasenya === '') {
            $mensajeError = 'Todos los campos son obligatorios para inscribirse en el libro de la taberna.';
        } elseif (mb_strlen($username) > 50 || mb_strlen($nombrePersona) > 120) {
            $mensajeError = 'El usuario o el nombre superan la longitud máxima permitida.';
        } else {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Persona WHERE username = ?");
            $stmtCheck->execute([$username]);
            if ((int)$stmtCheck->fetchColumn() > 0) {
                $mensajeError = 'Ese nombre de usuario ya pertenece a otro parroquiano. Elige otro.';
            } else {
                $stmtIns = $pdo->prepare("INSERT INTO Persona (username, NombrePersona, contrasenya, rol, inscrito_leaderboard) VALUES (?, ?, ?, 'jugador', 1)");
                $stmtIns->execute([$username, $nombrePersona, $contrasenya]);

                $_SESSION['username']             = $username;
                $_SESSION['NombrePersona']        = $nombrePersona;
                $_SESSION['rol']                  = 'jugador';
                $_SESSION['inscrito_leaderboard'] = 1;

                header('Location: index.php');
                exit;
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
                <div class="grupo-form">
                    <label for="reg_username">Nombre de Usuario (único):</label>
                    <input type="text" id="reg_username" name="username" class="input-taberna" required maxlength="50">
                </div>
                <div class="grupo-form">
                    <label for="reg_nombre">Nombre y Apellidos (NombrePersona):</label>
                    <input type="text" id="reg_nombre" name="NombrePersona" class="input-taberna" required maxlength="120">
                </div>
                <div class="grupo-form">
                    <label for="reg_contrasenya">Contraseña (sin restricciones):</label>
                    <input type="text" id="reg_contrasenya" name="contrasenya" class="input-taberna" required>
                </div>
                <button type="submit" class="btn btn-exito" style="width:100%;">Firmar Alta y Entrar</button>
            </form>
        </div>
    </div>
</div>

<script src="app.js"></script>
</body>
</html>