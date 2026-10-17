<?php
require_once 'conexion.php';
requerirAdmin($pdo);

$mensajeExito = '';
$mensajeError = '';

$calificacionesValidas = [
    'No me gustó',
    'Meh',
    'Está Bien',
    'Me gustó bastante',
    '¿Pa cuando otra?'
];

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
                $inscrito = (isset($_POST['inscrito_leaderboard']) && $_POST['inscrito_leaderboard'] === '1') ? 1 : 0;

                $stmt = $pdo->prepare("UPDATE Persona SET username = ?, NombrePersona = ?, contrasenya = ?, rol = ?, inscrito_leaderboard = ? WHERE username = ?");
                $stmt->execute([$nuevoUsername, $nombrePersona, $contrasenya, $rol, $inscrito, $originalUsername]);

                if ($_SESSION['username'] === $originalUsername) {
                    $_SESSION['username'] = $nuevoUsername;
                    $_SESSION['NombrePersona'] = $nombrePersona;
                    $_SESSION['rol'] = $rol;
                }
                $mensajeExito = "Usuario «{$originalUsername}» actualizado correctamente.";
            } elseif ($operacion === 'eliminar') {
                $usernameDel = $_POST['username'];
                $stmt = $pdo->prepare("DELETE FROM Persona WHERE username = ?");
                $stmt->execute([$usernameDel]);
                $mensajeExito = "Usuario «{$usernameDel}» eliminado junto con sus registros asociados.";
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
                    $urlActual = $nuevaRuta;
                }

                $stmt = $pdo->prepare("UPDATE Juego SET NombreJuego = ?, Url = ?, lo_tenemos = ? WHERE NombreJuego = ?");
                $stmt->execute([$nuevoNombre, $urlActual, $loTenemos, $originalNombre]);
                $mensajeExito = "Juego «{$originalNombre}» actualizado correctamente.";
            } elseif ($operacion === 'eliminar') {
                $nombreDel = $_POST['NombreJuego'];
                $stmt = $pdo->prepare("DELETE FROM Juego WHERE NombreJuego = ?");
                $stmt->execute([$nombreDel]);
                $mensajeExito = "Juego «{$nombreDel}» eliminado de la base de datos.";
            }
        }

        // =====================================================================
        // 3. CRUD TABLA PERSONA_JUEGO (INTERESADOS)
        // =====================================================================
        elseif ($entidad === 'persona_juego') {
            if ($operacion === 'crear') {
                $u = trim($_POST['username']);
                $j = trim($_POST['NombreJuego']);
                $stmt = $pdo->prepare("INSERT INTO Persona_Juego (username, NombreJuego) VALUES (?, ?)");
                $stmt->execute([$u, $j]);
                $mensajeExito = "Relación de interés creada para «{$u}» en «{$j}».";
            } elseif ($operacion === 'editar') {
                $origU = $_POST['orig_username'];
                $origJ = $_POST['orig_NombreJuego'];
                $nuevoU = trim($_POST['username']);
                $nuevoJ = trim($_POST['NombreJuego']);
                $stmt = $pdo->prepare("UPDATE Persona_Juego SET username = ?, NombreJuego = ? WHERE username = ? AND NombreJuego = ?");
                $stmt->execute([$nuevoU, $nuevoJ, $origU, $origJ]);
                $mensajeExito = "Inscripción de interés actualizada correctamente.";
            } elseif ($operacion === 'eliminar') {
                $u = $_POST['username'];
                $j = $_POST['NombreJuego'];
                $stmt = $pdo->prepare("DELETE FROM Persona_Juego WHERE username = ? AND NombreJuego = ?");
                $stmt->execute([$u, $j]);
                $mensajeExito = "Inscripción de interés eliminada.";
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

        // =====================================================================
        // 7. CRUD TABLA RESENYA (SECCIÓN 7: LUDOTECA)
        // =====================================================================
        elseif ($entidad === 'resenya') {
            if ($operacion === 'editar') {
                $idRes = (int)$_POST['id_resenya'];
                $u = trim($_POST['username']);
                $j = trim($_POST['NombreJuego']);
                $haJugado = (isset($_POST['ha_jugado']) && $_POST['ha_jugado'] === '1') ? 1 : 0;
                $cal = isset($_POST['calificacion']) ? trim($_POST['calificacion']) : null;
                $com = isset($_POST['comentario']) ? trim($_POST['comentario']) : null;

                if ($haJugado === 0 || !in_array($cal, $calificacionesValidas, true)) {
                    $cal = null;
                }
                if ($com === '') {
                    $com = null;
                }

                $stmt = $pdo->prepare("
                    UPDATE Resenya
                    SET username = ?, NombreJuego = ?, ha_jugado = ?, calificacion = ?, comentario = ?
                    WHERE id_resenya = ?
                ");
                $stmt->execute([$u, $j, $haJugado, $cal, $com, $idRes]);
                $mensajeExito = "Reseña #{$idRes} actualizada correctamente.";
            } elseif ($operacion === 'eliminar') {
                $idRes = (int)$_POST['id_resenya'];
                $stmt = $pdo->prepare("DELETE FROM Resenya WHERE id_resenya = ?");
                $stmt->execute([$idRes]);
                $mensajeExito = "Reseña #{$idRes} eliminada de la Ludoteca.";
            }
        }
    } catch (Exception $e) {
        $mensajeError = "Error en la operación de administración: " . $e->getMessage();
    }
}

// Consultas de todas las tablas del sistema
$personas = $pdo->query("SELECT * FROM Persona ORDER BY username ASC")->fetchAll();
$juegos = $pdo->query("SELECT * FROM Juego ORDER BY NombreJuego ASC")->fetchAll();
$relacionesPJ = $pdo->query("SELECT * FROM Persona_Juego ORDER BY NombreJuego ASC, username ASC")->fetchAll();
$victorias = $pdo->query("SELECT * FROM Victoria ORDER BY id_victoria DESC")->fetchAll();
$votaciones = $pdo->query("SELECT * FROM Votacion ORDER BY id_votacion DESC")->fetchAll();
$votos = $pdo->query("SELECT * FROM Voto ORDER BY id_voto DESC")->fetchAll();

$resenyas = [];
$tablaResenyaActiva = true;
try {
    $resenyas = $pdo->query("SELECT * FROM Resenya ORDER BY fecha_resenya DESC, id_resenya DESC")->fetchAll();
} catch (Exception $e) {
    $tablaResenyaActiva = false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Despacho del Tabernero (Admin) - La Taberna de las Gatitas Jugonas</title>
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
        <h2 class="seccion-titulo">Panel de Administración Total (CRUD Completo - 7 Secciones)</h2>
        <p>
            Control total de lectura, edición y borrado sobre las 7 tablas del sistema: Personas, Juegos, Interesados (<code>Persona_Juego</code>), Victorias, Votaciones, Votos y Reseñas de la Ludoteca (<code>Resenya</code>).
        </p>
    </section>

    <!-- 1. GESTIÓN DE PERSONAS -->
    <section class="pergamino">
        <h3 class="seccion-titulo">1. Tabla Persona (Usuarios Registrados: <?php echo count($personas); ?>)</h3>
        <div class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
                        <th>Username (PK)</th>
                        <th>Nombre y Apellidos</th>
                        <th>Contraseña (Texto Plano)</th>
                        <th>Rol</th>
                        <th>En Leaderboard</th>
                        <th style="width:210px;">Acciones</th>
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
                                <select form="<?php echo $formIdP; ?>" name="inscrito_leaderboard" class="select-taberna">
                                    <option value="1" <?php echo (int)$p['inscrito_leaderboard'] === 1 ? 'selected' : ''; ?>>Sí (1)</option>
                                    <option value="0" <?php echo (int)$p['inscrito_leaderboard'] === 0 ? 'selected' : ''; ?>>No (0)</option>
                                </select>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="submit" form="<?php echo $formIdP; ?>" class="btn btn-exito btn-pequeno">Guardar</button>
                                    <form method="POST" action="admin.php" class="form-confirmar-borrado" data-confirm-msg="¿Eliminar al usuario <?php echo h($p['username']); ?> y todos sus datos?">
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
                                    <form method="POST" action="admin.php" class="form-confirmar-borrado" data-confirm-msg="¿Eliminar el juego <?php echo h($j['NombreJuego']); ?> y sus votos/victorias/reseñas?">
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

    <!-- 7. SECCIÓN 7: GESTIÓN DE RESEÑAS DE LA LUDOTECA -->
    <section class="pergamino">
        <h3 class="seccion-titulo">7. Tabla Resenya (Reseñas de la Ludoteca: <?php echo count($resenyas); ?>)</h3>

        <?php if (!$tablaResenyaActiva): ?>
            <div class="alerta alerta-error">
                La tabla <code>Resenya</code> aún no ha sido creada. Ejecuta el archivo <code>migration_ludoteca.sql</code> en phpMyAdmin para habilitar esta sección.
            </div>
        <?php else: ?>
            <div class="tabla-contenedor">
                <table class="tabla-taberna">
                    <thead>
                        <tr>
                            <th style="width:65px;">ID</th>
                            <th>Usuario (username)</th>
                            <th>Juego (NombreJuego)</th>
                            <th>¿Lo ha jugado?</th>
                            <th>Calificación (1 a 5)</th>
                            <th>Comentario / Crónica</th>
                            <th style="width:200px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($resenyas) === 0): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; font-style:italic;">
                                    Todavía no existen reseñas registradas en la Ludoteca.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($resenyas as $r): ?>
                                <?php $formIdRes = 'form_res_' . (int)$r['id_resenya']; ?>
                                <tr>
                                    <td>
                                        #<?php echo (int)$r['id_resenya']; ?>
                                        <form id="<?php echo $formIdRes; ?>" method="POST" action="admin.php">
                                            <input type="hidden" name="entidad" value="resenya">
                                            <input type="hidden" name="operacion" value="editar">
                                            <input type="hidden" name="id_resenya" value="<?php echo (int)$r['id_resenya']; ?>">
                                        </form>
                                    </td>
                                    <td>
                                        <select form="<?php echo $formIdRes; ?>" name="username" class="select-taberna">
                                            <?php foreach ($personas as $p): ?>
                                                <option value="<?php echo h($p['username']); ?>" <?php echo $p['username'] === $r['username'] ? 'selected' : ''; ?>>
                                                    <?php echo h($p['username']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select form="<?php echo $formIdRes; ?>" name="NombreJuego" class="select-taberna">
                                            <?php foreach ($juegos as $j): ?>
                                                <option value="<?php echo h($j['NombreJuego']); ?>" <?php echo $j['NombreJuego'] === $r['NombreJuego'] ? 'selected' : ''; ?>>
                                                    <?php echo h($j['NombreJuego']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select form="<?php echo $formIdRes; ?>" name="ha_jugado" class="select-taberna">
                                            <option value="1" <?php echo (int)$r['ha_jugado'] === 1 ? 'selected' : ''; ?>>Sí (1)</option>
                                            <option value="0" <?php echo (int)$r['ha_jugado'] === 0 ? 'selected' : ''; ?>>No (0)</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select form="<?php echo $formIdRes; ?>" name="calificacion" class="select-taberna">
                                            <option value="" <?php echo $r['calificacion'] === null ? 'selected' : ''; ?>>-- NULL (Sin calificar) --</option>
                                            <?php foreach ($calificacionesValidas as $cVal): ?>
                                                <option value="<?php echo h($cVal); ?>" <?php echo $r['calificacion'] === $cVal ? 'selected' : ''; ?>>
                                                    <?php echo h($cVal); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <textarea form="<?php echo $formIdRes; ?>" name="comentario" rows="2" class="input-taberna"><?php echo h((string)$r['comentario']); ?></textarea>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                            <button type="submit" form="<?php echo $formIdRes; ?>" class="btn btn-exito btn-pequeno">Guardar</button>
                                            <form method="POST" action="admin.php" class="form-confirmar-borrado" data-confirm-msg="¿Eliminar definitivamente la reseña #<?php echo (int)$r['id_resenya']; ?>?">
                                                <input type="hidden" name="entidad" value="resenya">
                                                <input type="hidden" name="operacion" value="eliminar">
                                                <input type="hidden" name="id_resenya" value="<?php echo (int)$r['id_resenya']; ?>">
                                                <button type="submit" class="btn btn-peligro btn-pequeno">Borrar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</main>

<script src="app.js"></script>
</body>
</html>