<?php
require_once 'conexion.php';
requerirAdmin($pdo);

$mensajeExito = '';
$mensajeError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entidad = isset($_POST['entidad']) ? $_POST['entidad'] : '';
    $operacion = isset($_POST['operacion']) ? $_POST['operacion'] : '';

    try {
        // =====================================================================
        // 1. CRUD TABLA PERSONA
        // =====================================================================
        if ($entidad === 'persona') {
            if ($operacion === 'editar') {
                $originalUsername = $_POST['original_username'];
                $nuevoUsername = trim($_POST['username']);
                $nombrePersona = trim($_POST['NombrePersona']);
                $contrasenya = (string)$_POST['contrasenya'];
                $rol = ($_POST['rol'] === 'admin') ? 'admin' : 'jugador';

                $stmt = $pdo->prepare("UPDATE Persona SET username = ?, NombrePersona = ?, contrasenya = ?, rol = ?, inscrito_leaderboard = 1 WHERE username = ?");
                $stmt->execute([$nuevoUsername, $nombrePersona, $contrasenya, $rol, $originalUsername]);

                if ($_SESSION['username'] === $originalUsername) {
                    $_SESSION['username'] = $nuevoUsername;
                    $_SESSION['NombrePersona'] = $nombrePersona;
                    $_SESSION['rol'] = $rol;
                }
                $mensajeExito = "Usuario «{$originalUsername}» actualizado.";
            } elseif ($operacion === 'eliminar') {
                $usernameDel = $_POST['username'];
                if ($usernameDel === $_SESSION['username']) {
                    throw new Exception("No puedes eliminar tu propio usuario administrador mientras tienes la sesión iniciada.");
                }
                $stmt = $pdo->prepare("DELETE FROM Persona WHERE username = ?");
                $stmt->execute([$usernameDel]);
                $mensajeExito = "Usuario «{$usernameDel}» eliminado.";
            }
        }

        // =====================================================================
        // 2. CRUD TABLA JUEGO
        // =====================================================================
        elseif ($entidad === 'juego') {
            if ($operacion === 'editar') {
                $originalNombre = $_POST['original_NombreJuego'];
                $nuevoNombre = trim($_POST['NombreJuego']);
                $urlActual = trim($_POST['Url']);
                $loTenemos = (isset($_POST['lo_tenemos']) && $_POST['lo_tenemos'] === '1') ? 1 : 0;

                if (isset($_FILES['nueva_imagen']) && $_FILES['nueva_imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $errImg = '';
                    $nuevaRuta = procesarSubidaImagen('nueva_imagen', $errImg);
                    if ($nuevaRuta === false) {
                        throw new Exception($errImg);
                    }
                    // Borrar archivo antiguo si estaba en uploads/
                    if (strpos($urlActual, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $urlActual)) {
                        @unlink(__DIR__ . '/' . $urlActual);
                    }
                    $urlActual = $nuevaRuta;
                }

                $stmt = $pdo->prepare("UPDATE Juego SET NombreJuego = ?, Url = ?, lo_tenemos = ? WHERE NombreJuego = ?");
                $stmt->execute([$nuevoNombre, $urlActual, $loTenemos, $originalNombre]);

                // Si pasa a "Ya lo tenemos", limpiamos sus interesados de Persona_Juego
                if ($loTenemos === 1) {
                    $stmtClean = $pdo->prepare("DELETE FROM Persona_Juego WHERE NombreJuego = ?");
                    $stmtClean->execute([$nuevoNombre]);
                }

                $mensajeExito = "Juego «{$originalNombre}» actualizado.";
            } elseif ($operacion === 'eliminar') {
                $nombreDel = $_POST['NombreJuego'];
                $stmtUrl = $pdo->prepare("SELECT Url FROM Juego WHERE NombreJuego = ?");
                $stmtUrl->execute([$nombreDel]);
                $imgAntigua = $stmtUrl->fetchColumn();

                $stmt = $pdo->prepare("DELETE FROM Juego WHERE NombreJuego = ?");
                $stmt->execute([$nombreDel]);

                if ($imgAntigua && strpos($imgAntigua, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $imgAntigua)) {
                    @unlink(__DIR__ . '/' . $imgAntigua);
                }
                $mensajeExito = "Juego «{$nombreDel}» eliminado.";
            }
        }

        // =====================================================================
        // 3. CRUD TABLA PERSONA_JUEGO (INTERESADOS)
        // =====================================================================
        elseif ($entidad === 'persona_juego') {
            if ($operacion === 'crear') {
                $u = trim($_POST['username']);
                $j = trim($_POST['NombreJuego']);
                $stmt = $pdo->prepare("INSERT IGNORE INTO Persona_Juego (username, NombreJuego) VALUES (?, ?)");
                $stmt->execute([$u, $j]);
                $mensajeExito = "Relación añadida para «{$u}» en «{$j}».";
            } elseif ($operacion === 'editar') {
                $origU = $_POST['orig_username'];
                $origJ = $_POST['orig_NombreJuego'];
                $nuevoU = trim($_POST['username']);
                $nuevoJ = trim($_POST['NombreJuego']);
                $stmt = $pdo->prepare("UPDATE Persona_Juego SET username = ?, NombreJuego = ? WHERE username = ? AND NombreJuego = ?");
                $stmt->execute([$nuevoU, $nuevoJ, $origU, $origJ]);
                $mensajeExito = "Relación actualizada.";
            } elseif ($operacion === 'eliminar') {
                $u = $_POST['username'];
                $j = $_POST['NombreJuego'];
                $stmt = $pdo->prepare("DELETE FROM Persona_Juego WHERE username = ? AND NombreJuego = ?");
                $stmt->execute([$u, $j]);
                $mensajeExito = "Relación eliminada.";
            }
        }

        // =====================================================================
        // 4. CRUD TABLA VICTORIA
        // =====================================================================
        elseif ($entidad === 'victoria') {
            if ($operacion === 'editar') {
                $idVic = (int)$_POST['id_victoria'];
                $u = trim($_POST['username']);
                $j = trim($_POST['NombreJuego']);
                $fecha = trim($_POST['fecha_victoria']);
                $stmt = $pdo->prepare("UPDATE Victoria SET username = ?, NombreJuego = ?, fecha_victoria = ? WHERE id_victoria = ?");
                $stmt->execute([$u, $j, $fecha, $idVic]);
                $mensajeExito = "Victoria #{$idVic} actualizada.";
            } elseif ($operacion === 'eliminar') {
                $idVic = (int)$_POST['id_victoria'];
                $stmt = $pdo->prepare("DELETE FROM Victoria WHERE id_victoria = ?");
                $stmt->execute([$idVic]);
                $mensajeExito = "Victoria #{$idVic} eliminada.";
            }
        }

        // =====================================================================
        // 5. CRUD TABLA VOTACION
        // =====================================================================
        elseif ($entidad === 'votacion') {
            if ($operacion === 'editar') {
                $idVot = (int)$_POST['id_votacion'];
                $titulo = trim($_POST['titulo']);
                $creador = trim($_POST['creador_username']);
                $fechaLimite = trim($_POST['fecha_limite']);
                $tipoVoto = ($_POST['tipo_voto'] === 'multiple') ? 'multiple' : 'unico';
                $stmt = $pdo->prepare("UPDATE Votacion SET titulo = ?, creador_username = ?, fecha_limite = ?, tipo_voto = ? WHERE id_votacion = ?");
                $stmt->execute([$titulo, $creador, $fechaLimite, $tipoVoto, $idVot]);
                $mensajeExito = "Votación #{$idVot} actualizada.";
            } elseif ($operacion === 'eliminar') {
                $idVot = (int)$_POST['id_votacion'];
                $stmt = $pdo->prepare("DELETE FROM Votacion WHERE id_votacion = ?");
                $stmt->execute([$idVot]);
                $mensajeExito = "Votación #{$idVot} y sus votos asociados eliminados.";
            }
        }

        // =====================================================================
        // 6. CRUD TABLA VOTO
        // =====================================================================
        elseif ($entidad === 'voto') {
            if ($operacion === 'editar') {
                $idVoto = (int)$_POST['id_voto'];
                $idVotacion = (int)$_POST['id_votacion'];
                $u = trim($_POST['username']);
                $j = trim($_POST['NombreJuego']);
                $stmt = $pdo->prepare("UPDATE Voto SET id_votacion = ?, username = ?, NombreJuego = ? WHERE id_voto = ?");
                $stmt->execute([$idVotacion, $u, $j, $idVoto]);
                $mensajeExito = "Voto individual #{$idVoto} actualizado.";
            } elseif ($operacion === 'eliminar') {
                $idVoto = (int)$_POST['id_voto'];
                $stmt = $pdo->prepare("DELETE FROM Voto WHERE id_voto = ?");
                $stmt->execute([$idVoto]);
                $mensajeExito = "Voto individual #{$idVoto} eliminado.";
            }
        }
    } catch (Exception $e) {
        $mensajeError = "Error en la operación de administración: " . $e->getMessage();
    }
}

// Consultas de todas las tablas instanciadas en la base de datos
$personas = $pdo->query("SELECT * FROM Persona ORDER BY username ASC")->fetchAll();
$juegos = $pdo->query("SELECT * FROM Juego ORDER BY NombreJuego ASC")->fetchAll();
$relacionesPJ = $pdo->query("SELECT * FROM Persona_Juego ORDER BY NombreJuego ASC, username ASC")->fetchAll();
$victorias = $pdo->query("SELECT * FROM Victoria ORDER BY id_victoria DESC")->fetchAll();
$votaciones = $pdo->query("SELECT * FROM Votacion ORDER BY id_votacion DESC")->fetchAll();
$votos = $pdo->query("SELECT * FROM Voto ORDER BY id_voto DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Despacho del Tabernero (Admin) - La Taberna del Dado de Hierro</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php renderizarNavegacion('admin'); ?>

<main class="contenedor-principal">

    <?php if ($mensajeError !== ''): ?>
        <div class="alerta alerta-error"><?php echo h($mensajeError); ?></div>
    <?php endif; ?>

    <?php if ($mensajeExito !== ''): ?>
        <div class="alerta alerta-exito"><?php echo h($mensajeExito); ?></div>
    <?php endif; ?>

    <section class="pergamino">
        <h2 class="seccion-titulo">Panel de Administración Total (CRUD Completo)</h2>
        <p>
            Desde este panel puedes inspeccionar, modificar o eliminar cualquier registro de las 6 tablas del sistema. Gracias a las restricciones <code>ON UPDATE CASCADE</code> y <code>ON DELETE CASCADE</code>, cualquier cambio en el <code>username</code> de una persona o en el <code>NombreJuego</code> se actualiza automáticamente en toda la base de datos.
        </p>
    </section>

    <!-- 1. GESTIÓN DE PERSONAS -->
    <section class="pergamino">
        <h3 class="seccion-titulo">1. Usuarios (<?php echo count($personas); ?>)</h3>
        <div class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
                        <th>Username (PK)</th>
                        <th>Nombre y Apellidos</th>
                        <th>Contraseña</th>
                        <th>Rol</th>
                        <th style="width:190px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($personas as $p): ?>
                        <?php $formIdP = 'form_p_' . md5($p['username']); ?>
                        <tr>
                            <td>
                                <form id="<?php echo $formIdP; ?>" method="POST" action="admin.php">
                                    <input type="hidden" name="entidad" value="persona">
                                    <input type="hidden" name="operacion" value="editar">
                                    <input type="hidden" name="original_username" value="<?php echo h($p['username']); ?>">
                                </form>
                                <input type="text" form="<?php echo $formIdP; ?>" name="username" value="<?php echo h($p['username']); ?>" class="input-taberna" required maxlength="50">
                            </td>
                            <td>
                                <input type="text" form="<?php echo $formIdP; ?>" name="NombrePersona" value="<?php echo h($p['NombrePersona']); ?>" class="input-taberna" required maxlength="120">
                            </td>
                            <td>
                                <input type="text" form="<?php echo $formIdP; ?>" name="contrasenya" value="<?php echo h($p['contrasenya']); ?>" class="input-taberna" required>
                            </td>
                            <td>
                                <select form="<?php echo $formIdP; ?>" name="rol" class="select-taberna">
                                    <option value="jugador" <?php echo $p['rol'] === 'jugador' ? 'selected' : ''; ?>>jugador</option>
                                    <option value="admin" <?php echo $p['rol'] === 'admin' ? 'selected' : ''; ?>>admin</option>
                                </select>
                            </td>
                            <td>
                                <div class="acciones-admin-celda">
                                    <button type="submit" form="<?php echo $formIdP; ?>" class="btn btn-exito btn-pequeno">Guardar</button>
                                    <form method="POST" action="admin.php" class="form-confirmar-borrado" data-confirm-msg="¿Eliminar al usuario <?php echo h($p['username']); ?>?">
                                        <input type="hidden" name="entidad" value="persona">
                                        <input type="hidden" name="operacion" value="eliminar">
                                        <input type="hidden" name="username" value="<?php echo h($p['username']); ?>">
                                        <button type="submit" class="btn btn-peligro btn-pequeno">Borrar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    
    <!-- 2. GESTIÓN DE JUEGOS -->
    <section class="pergamino">
        <h3 class="seccion-titulo">2. Tabla Juego (Juegos Registrados: <?php echo count($juegos); ?>)</h3>
        <div class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
                        <th style="width:95px;">Miniatura</th>
                        <th>NombreJuego (PK)</th>
                        <th>Ruta Imagen (Url) / Subir Nueva</th>
                        <th>¿Lo tenemos?</th>
                        <th style="width:210px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($juegos as $j): ?>
                        <?php $formIdJ = 'form_j_' . md5($j['NombreJuego']); ?>
                        <tr>
                            <td>
                                <img src="<?php echo h($j['Url']); ?>" alt="<?php echo h($j['NombreJuego']); ?>" class="img-juego-tabla">
                            </td>
                            <td>
                                <form id="<?php echo $formIdJ; ?>" method="POST" action="admin.php" enctype="multipart/form-data">
                                    <input type="hidden" name="entidad" value="juego">
                                    <input type="hidden" name="operacion" value="editar">
                                    <input type="hidden" name="original_NombreJuego" value="<?php echo h($j['NombreJuego']); ?>">
                                </form>
                                <input type="text" form="<?php echo $formIdJ; ?>" name="NombreJuego" value="<?php echo h($j['NombreJuego']); ?>" class="input-taberna" required maxlength="120">
                            </td>
                            <td>
                                <input type="text" form="<?php echo $formIdJ; ?>" name="Url" value="<?php echo h($j['Url']); ?>" class="input-taberna" required style="margin-bottom:6px;">
                                <input type="file" form="<?php echo $formIdJ; ?>" name="nueva_imagen" class="input-taberna" accept="image/*">
                            </td>
                            <td>
                                <select form="<?php echo $formIdJ; ?>" name="lo_tenemos" class="select-taberna">
                                    <option value="1" <?php echo (int)$j['lo_tenemos'] === 1 ? 'selected' : ''; ?>>1 - Ya lo tenemos</option>
                                    <option value="0" <?php echo (int)$j['lo_tenemos'] === 0 ? 'selected' : ''; ?>>0 - No lo tenemos</option>
                                </select>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="submit" form="<?php echo $formIdJ; ?>" class="btn btn-exito btn-pequeno">Guardar</button>
                                    <form method="POST" action="admin.php" class="form-confirmar-borrado" data-confirm-msg="¿Eliminar el juego <?php echo h($j['NombreJuego']); ?> y sus votos/victorias?">
                                        <input type="hidden" name="entidad" value="juego">
                                        <input type="hidden" name="operacion" value="eliminar">
                                        <input type="hidden" name="NombreJuego" value="<?php echo h($j['NombreJuego']); ?>">
                                        <button type="submit" class="btn btn-peligro btn-pequeno">Borrar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 3. GESTIÓN DE PERSONA_JUEGO (INTERESADOS) -->
    <section class="pergamino">
        <h3 class="seccion-titulo">3. Tabla Persona_Juego (Interesados en Juegos: <?php echo count($relacionesPJ); ?>)</h3>

        <form method="POST" action="admin.php" class="pergamino-secundario" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="entidad" value="persona_juego">
            <input type="hidden" name="operacion" value="crear">
            <div>
                <label>Usuario:</label>
                <select name="username" class="select-taberna" required>
                    <?php foreach ($personas as $p): ?>
                        <option value="<?php echo h($p['username']); ?>"><?php echo h($p['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Juego:</label>
                <select name="NombreJuego" class="select-taberna" required>
                    <?php foreach ($juegos as $j): ?>
                        <option value="<?php echo h($j['NombreJuego']); ?>"><?php echo h($j['NombreJuego']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-bronce btn-pequeno">Añadir Relación de Interés</button>
        </form>

        <div class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
                        <th>Username (FK)</th>
                        <th>NombreJuego (FK)</th>
                        <th style="width:210px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($relacionesPJ as $idx => $rel): ?>
                        <?php $formIdPJ = 'form_pj_' . $idx; ?>
                        <tr>
                            <td>
                                <form id="<?php echo $formIdPJ; ?>" method="POST" action="admin.php">
                                    <input type="hidden" name="entidad" value="persona_juego">
                                    <input type="hidden" name="operacion" value="editar">
                                    <input type="hidden" name="orig_username" value="<?php echo h($rel['username']); ?>">
                                    <input type="hidden" name="orig_NombreJuego" value="<?php echo h($rel['NombreJuego']); ?>">
                                </form>
                                <select form="<?php echo $formIdPJ; ?>" name="username" class="select-taberna">
                                    <?php foreach ($personas as $p): ?>
                                        <option value="<?php echo h($p['username']); ?>" <?php echo $p['username'] === $rel['username'] ? 'selected' : ''; ?>>
                                            <?php echo h($p['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select form="<?php echo $formIdPJ; ?>" name="NombreJuego" class="select-taberna">
                                    <?php foreach ($juegos as $j): ?>
                                        <option value="<?php echo h($j['NombreJuego']); ?>" <?php echo $j['NombreJuego'] === $rel['NombreJuego'] ? 'selected' : ''; ?>>
                                            <?php echo h($j['NombreJuego']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="submit" form="<?php echo $formIdPJ; ?>" class="btn btn-exito btn-pequeno">Guardar</button>
                                    <form method="POST" action="admin.php" class="form-confirmar-borrado">
                                        <input type="hidden" name="entidad" value="persona_juego">
                                        <input type="hidden" name="operacion" value="eliminar">
                                        <input type="hidden" name="username" value="<?php echo h($rel['username']); ?>">
                                        <input type="hidden" name="NombreJuego" value="<?php echo h($rel['NombreJuego']); ?>">
                                        <button type="submit" class="btn btn-peligro btn-pequeno">Borrar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 4. GESTIÓN DE VICTORIAS -->
    <section class="pergamino">
        <h3 class="seccion-titulo">4. Tabla Victoria (Partidas Ganadas: <?php echo count($victorias); ?>)</h3>
        <div class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Ganador (username)</th>
                        <th>Juego (NombreJuego)</th>
                        <th>Fecha y Hora (YYYY-MM-DD HH:MM:SS)</th>
                        <th style="width:210px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($victorias as $v): ?>
                        <?php $formIdV = 'form_vic_' . (int)$v['id_victoria']; ?>
                        <tr>
                            <td>
                                #<?php echo (int)$v['id_victoria']; ?>
                                <form id="<?php echo $formIdV; ?>" method="POST" action="admin.php">
                                    <input type="hidden" name="entidad" value="victoria">
                                    <input type="hidden" name="operacion" value="editar">
                                    <input type="hidden" name="id_victoria" value="<?php echo (int)$v['id_victoria']; ?>">
                                </form>
                            </td>
                            <td>
                                <select form="<?php echo $formIdV; ?>" name="username" class="select-taberna">
                                    <?php foreach ($personas as $p): ?>
                                        <option value="<?php echo h($p['username']); ?>" <?php echo $p['username'] === $v['username'] ? 'selected' : ''; ?>>
                                            <?php echo h($p['username']); ?> (<?php echo h($p['NombrePersona']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select form="<?php echo $formIdV; ?>" name="NombreJuego" class="select-taberna">
                                    <?php foreach ($juegos as $j): ?>
                                        <option value="<?php echo h($j['NombreJuego']); ?>" <?php echo $j['NombreJuego'] === $v['NombreJuego'] ? 'selected' : ''; ?>>
                                            <?php echo h($j['NombreJuego']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" form="<?php echo $formIdV; ?>" name="fecha_victoria" value="<?php echo h($v['fecha_victoria']); ?>" class="input-taberna" required>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="submit" form="<?php echo $formIdV; ?>" class="btn btn-exito btn-pequeno">Guardar</button>
                                    <form method="POST" action="admin.php" class="form-confirmar-borrado">
                                        <input type="hidden" name="entidad" value="victoria">
                                        <input type="hidden" name="operacion" value="eliminar">
                                        <input type="hidden" name="id_victoria" value="<?php echo (int)$v['id_victoria']; ?>">
                                        <button type="submit" class="btn btn-peligro btn-pequeno">Borrar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 5. GESTIÓN DE VOTACIONES -->
    <section class="pergamino">
        <h3 class="seccion-titulo">5. Tabla Votacion (Todas las Votaciones: <?php echo count($votaciones); ?>)</h3>
        <div class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Título</th>
                        <th>Creador</th>
                        <th>Fecha Límite</th>
                        <th>Tipo Voto</th>
                        <th style="width:210px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($votaciones as $vot): ?>
                        <?php $formIdVot = 'form_vot_' . (int)$vot['id_votacion']; ?>
                        <tr>
                            <td>
                                #<?php echo (int)$vot['id_votacion']; ?>
                                <form id="<?php echo $formIdVot; ?>" method="POST" action="admin.php">
                                    <input type="hidden" name="entidad" value="votacion">
                                    <input type="hidden" name="operacion" value="editar">
                                    <input type="hidden" name="id_votacion" value="<?php echo (int)$vot['id_votacion']; ?>">
                                </form>
                            </td>
                            <td>
                                <input type="text" form="<?php echo $formIdVot; ?>" name="titulo" value="<?php echo h($vot['titulo']); ?>" class="input-taberna" required>
                            </td>
                            <td>
                                <select form="<?php echo $formIdVot; ?>" name="creador_username" class="select-taberna">
                                    <?php foreach ($personas as $p): ?>
                                        <option value="<?php echo h($p['username']); ?>" <?php echo $p['username'] === $vot['creador_username'] ? 'selected' : ''; ?>>
                                            <?php echo h($p['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="date" form="<?php echo $formIdVot; ?>" name="fecha_limite" value="<?php echo h($vot['fecha_limite']); ?>" class="input-taberna" required>
                            </td>
                            <td>
                                <select form="<?php echo $formIdVot; ?>" name="tipo_voto" class="select-taberna">
                                    <option value="unico" <?php echo $vot['tipo_voto'] === 'unico' ? 'selected' : ''; ?>>unico</option>
                                    <option value="multiple" <?php echo $vot['tipo_voto'] === 'multiple' ? 'selected' : ''; ?>>multiple</option>
                                </select>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="submit" form="<?php echo $formIdVot; ?>" class="btn btn-exito btn-pequeno">Guardar</button>
                                    <form method="POST" action="admin.php" class="form-confirmar-borrado">
                                        <input type="hidden" name="entidad" value="votacion">
                                        <input type="hidden" name="operacion" value="eliminar">
                                        <input type="hidden" name="id_votacion" value="<?php echo (int)$vot['id_votacion']; ?>">
                                        <button type="submit" class="btn btn-peligro btn-pequeno">Borrar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 6. GESTIÓN DE VOTOS INDIVIDUALES -->
    <section class="pergamino">
        <h3 class="seccion-titulo">6. Tabla Voto (Votos Emitidos: <?php echo count($votos); ?>)</h3>
        <div class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
                        <th style="width:70px;">ID Voto</th>
                        <th>Votación (ID)</th>
                        <th>Usuario (username)</th>
                        <th>Juego Votado</th>
                        <th style="width:210px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($votos as $vt): ?>
                        <?php $formIdVt = 'form_vt_' . (int)$vt['id_voto']; ?>
                        <tr>
                            <td>
                                #<?php echo (int)$vt['id_voto']; ?>
                                <form id="<?php echo $formIdVt; ?>" method="POST" action="admin.php">
                                    <input type="hidden" name="entidad" value="voto">
                                    <input type="hidden" name="operacion" value="editar">
                                    <input type="hidden" name="id_voto" value="<?php echo (int)$vt['id_voto']; ?>">
                                </form>
                            </td>
                            <td>
                                <select form="<?php echo $formIdVt; ?>" name="id_votacion" class="select-taberna">
                                    <?php foreach ($votaciones as $vot): ?>
                                        <option value="<?php echo (int)$vot['id_votacion']; ?>" <?php echo (int)$vot['id_votacion'] === (int)$vt['id_votacion'] ? 'selected' : ''; ?>>
                                            #<?php echo (int)$vot['id_votacion']; ?> - <?php echo h($vot['titulo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select form="<?php echo $formIdVt; ?>" name="username" class="select-taberna">
                                    <?php foreach ($personas as $p): ?>
                                        <option value="<?php echo h($p['username']); ?>" <?php echo $p['username'] === $vt['username'] ? 'selected' : ''; ?>>
                                            <?php echo h($p['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select form="<?php echo $formIdVt; ?>" name="NombreJuego" class="select-taberna">
                                    <?php foreach ($juegos as $j): ?>
                                        <option value="<?php echo h($j['NombreJuego']); ?>" <?php echo $j['NombreJuego'] === $vt['NombreJuego'] ? 'selected' : ''; ?>>
                                            <?php echo h($j['NombreJuego']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="submit" form="<?php echo $formIdVt; ?>" class="btn btn-exito btn-pequeno">Guardar</button>
                                    <form method="POST" action="admin.php" class="form-confirmar-borrado">
                                        <input type="hidden" name="entidad" value="voto">
                                        <input type="hidden" name="operacion" value="eliminar">
                                        <input type="hidden" name="id_voto" value="<?php echo (int)$vt['id_voto']; ?>">
                                        <button type="submit" class="btn btn-peligro btn-pequeno">Borrar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<script src="app.js"></script>
</body>
</html>