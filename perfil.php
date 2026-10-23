<?php
require_once 'conexion.php';
requerirLogin($pdo);

$mensajeExito = '';
$mensajeError = '';
$usuarioActual = $_SESSION['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    // 1. Actualizar datos del perfil
    if ($accion === 'editar_perfil') {
        $nuevoUsername = isset($_POST['username']) ? trim($_POST['username']) : '';
        $nombrePersona = isset($_POST['NombrePersona']) ? trim($_POST['NombrePersona']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $contrasenya = isset($_POST['contrasenya']) ? trim((string)$_POST['contrasenya']) : '';

        if ($nuevoUsername === '' || $nombrePersona === '' || $contrasenya === '') {
            $mensajeError = 'El nombre de usuario, tu nombre y la contraseña no pueden quedar vacíos.';
        } elseif (mb_strlen($nuevoUsername) > 50 || mb_strlen($nombrePersona) > 120 || mb_strlen($email) > 150) {
            $mensajeError = 'Alguno de los campos supera la longitud máxima permitida.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensajeError = 'El formato del correo electrónico no es válido.';
        } else {
            // Si ha cambiado su username, verificar que no esté ocupado
            if ($nuevoUsername !== $usuarioActual) {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Persona WHERE username = ?");
                $stmtCheck->execute([$nuevoUsername]);
                if ((int)$stmtCheck->fetchColumn() > 0) {
                    $mensajeError = 'Ese nombre de usuario ya está siendo utilizado por otra persona.';
                }
            }

            if ($mensajeError === '') {
                $emailGuardar = ($email !== '') ? $email : null;
                $stmtUpd = $pdo->prepare("
                    UPDATE Persona
                    SET username = ?, NombrePersona = ?, email = ?, contrasenya = ?
                    WHERE username = ?
                ");
                $stmtUpd->execute([$nuevoUsername, $nombrePersona, $emailGuardar, $contrasenya, $usuarioActual]);

                $_SESSION['username']      = $nuevoUsername;
                $_SESSION['NombrePersona'] = $nombrePersona;
                $_SESSION['email']         = $emailGuardar;
                $usuarioActual             = $nuevoUsername;

                $mensajeExito = 'Los datos de tu perfil se han actualizado correctamente.';
            }
        }
    }

    // 2. Eliminar cuenta propia (manteniendo la restricción de que un admin no puede eliminarse a sí mismo)
    elseif ($accion === 'eliminar_cuenta') {
        if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
            $mensajeError = 'No puedes eliminar tu propia cuenta de administrador.';
        } else {
            $stmtDel = $pdo->prepare("DELETE FROM Persona WHERE username = ?");
            $stmtDel->execute([$usuarioActual]);

            session_unset();
            session_destroy();
            header('Location: login.php');
            exit;
        }
    }
}

// Cargar datos actuales del usuario desde la BD
$stmtDatos = $pdo->prepare("SELECT username, NombrePersona, email, contrasenya, rol FROM Persona WHERE username = ?");
$stmtDatos->execute([$usuarioActual]);
$misDatos = $stmtDatos->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - La Taberna de las Gatitas Jugonas</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php renderizarNavegacion('perfil'); ?>

<main class="contenedor-principal">

    <?php if ($mensajeError !== ''): ?>
        <div class="alerta alerta-error"><?php echo h($mensajeError); ?></div>
    <?php endif; ?>

    <?php if ($mensajeExito !== ''): ?>
        <div class="alerta alerta-exito"><?php echo h($mensajeExito); ?></div>
    <?php endif; ?>

    <section class="pergamino" style="max-width: 640px; margin: 0 auto 22px auto;">
        <h2 class="seccion-titulo">Ficha de Parroquiano (Mi Perfil)</h2>
        <p style="margin-bottom: 14px;">
            Aquí puedes consultar y modificar tus datos personales. Si añades tu correo electrónico, recibirás un aviso cuando se convoque una nueva votación en la taberna.
        </p>

        <form method="POST" action="perfil.php">
            <input type="hidden" name="accion" value="editar_perfil">

            <div class="grupo-form">
                <label for="perfil_username">Nombre de Usuario (username):</label>
                <input type="text" id="perfil_username" name="username" class="input-taberna" required maxlength="50" value="<?php echo h($misDatos['username']); ?>">
            </div>

            <div class="grupo-form">
                <label for="perfil_nombre">Nombre y Apellidos (NombrePersona):</label>
                <input type="text" id="perfil_nombre" name="NombrePersona" class="input-taberna" required maxlength="120" value="<?php echo h($misDatos['NombrePersona']); ?>">
            </div>

            <div class="grupo-form">
                <label for="perfil_email">Correo Electrónico (opcional, para avisos de votaciones):</label>
                <input type="email" id="perfil_email" name="email" class="input-taberna" maxlength="150" placeholder="ejemplo@correo.com" value="<?php echo h((string)$misDatos['email']); ?>">
            </div>

            <div class="grupo-form">
                <label for="perfil_contrasenya">Contraseña:</label>
                <input type="text" id="perfil_contrasenya" name="contrasenya" class="input-taberna" required value="<?php echo h($misDatos['contrasenya']); ?>">
            </div>

            <div class="grupo-form">
                <label>Rol en la Taberna:</label>
                <div>
                    <span class="etiqueta etiqueta-bronce"><?php echo h($misDatos['rol']); ?></span>
                </div>
            </div>

            <button type="submit" class="btn btn-exito" style="width:100%;">Guardar Cambios en mi Perfil</button>
        </form>
    </section>

    <section class="pergamino" style="max-width: 640px; margin: 0 auto; border-color: var(--primary-dark);">
        <h3 class="seccion-titulo" style="color: var(--primary-dark);">Abandonar la Taberna (Eliminar Cuenta)</h3>
        <p style="margin-bottom: 12px;">
            Al eliminar tu cuenta se borrarán de forma permanente tu usuario, tus votos, reseñas y registros asociados.
        </p>

        <?php if ($misDatos['rol'] === 'admin'): ?>
            <p style="font-weight:bold; color:var(--primary-dark);">
                Como tienes rol de Administrador, no puedes eliminar tu propia cuenta por seguridad.
            </p>
        <?php else: ?>
            <form method="POST" action="perfil.php" class="form-confirmar-borrado" data-confirm-msg="¿Estás completamente seguro de que deseas eliminar tu cuenta de forma permanente? Esta acción no se puede deshacer.">
                <input type="hidden" name="accion" value="eliminar_cuenta">
                <button type="submit" class="btn btn-peligro" style="width:100%;">Eliminar mi Cuenta Permanentemente</button>
            </form>
        <?php endif; ?>
    </section>

</main>

<script src="app.js"></script>
</body>
</html>