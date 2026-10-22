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
                $email = isset($_POST['email']) ? trim($_POST['email']) : '';
                $emailGuardar = ($email !== '') ? $email : null;
                $contrasenya = (string)$_POST['contrasenya'];
                $rol = ($_POST['rol'] === 'admin') ? 'admin' : 'jugador';

                $stmt = $pdo->prepare("UPDATE Persona SET username = ?, NombrePersona = ?, email = ?, contrasenya = ?, rol = ?, inscrito_leaderboard = 1 WHERE username = ?");
                $stmt->execute([$nuevoUsername, $nombrePersona, $emailGuardar, $contrasenya, $rol, $originalUsername]);

                if ($_SESSION['username'] === $originalUsername) {
                    $_SESSION['username'] = $nuevoUsername;
                    $_SESSION['NombrePersona'] = $nombrePersona;
                    $_SESSION['email'] = $emailGuardar;
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
        // 2. CRUD TABLA JUEGO (INCLUYENDO PROPIETARIOS Y NUEVOS CAMPOS)
        // =====================================================================
        elseif ($entidad === 'juego') {
            if ($operacion === 'editar') {
                $originalNombre = $_POST['original_NombreJuego'];
                $nuevoNombre = trim($_POST['NombreJuego']);
                $stmtUrlOrig = $pdo->prepare("SELECT Url FROM Juego WHERE NombreJuego = ?");
                $stmtUrlOrig->execute([$originalNombre]);
                $urlActual = (string)$stmtUrlOrig->fetchColumn();
                $loTenemos = (isset($_POST['lo_tenemos']) && $_POST['lo_tenemos'] === '1') ? 1 : 0;
                $minJug = max(1, (int)(isset($_POST['min_jugadores']) ? $_POST['min_jugadores'] : 1));
                $maxJug = max($minJug, (int)(isset($_POST['max_jugadores']) ? $_POST['max_jugadores'] : $minJug));
                $descTexto = obtenerTextoPostSeguro('descripcion');
                $reglasTexto = obtenerTextoPostSeguro('reglas');
                $descJ = ($descTexto !== '') ? $descTexto : null;
                $reglasJ = ($reglasTexto !== '') ? $reglasTexto : null;
                $propietariosSel = (isset($_POST['propietarios']) && is_array($_POST['propietarios'])) ? $_POST['propietarios'] : [];

                $hayNuevaImagen = (isset($_POST['imagen_cliente_hex']) && $_POST['imagen_cliente_hex'] !== '')
                    || (isset($_FILES['nueva_imagen']) && $_FILES['nueva_imagen']['error'] !== UPLOAD_ERR_NO_FILE);

                if ($hayNuevaImagen) {
                    $errImg = '';
                    $nuevaRuta = procesarSubidaImagen('nueva_imagen', $errImg);
                    if ($nuevaRuta === false) {
                        throw new Exception($errImg);
                    }
                    if ($urlActual !== '' && strpos($urlActual, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $urlActual)) {
                        @unlink(__DIR__ . '/' . $urlActual);
                    }
                    $urlActual = $nuevaRuta;
                }

                $stmt = $pdo->prepare("
                    UPDATE Juego
                    SET NombreJuego = ?, Url = ?, lo_tenemos = ?, min_jugadores = ?, max_jugadores = ?, descripcion = ?, reglas = ?
                    WHERE NombreJuego = ?
                ");
                $stmt->execute([$nuevoNombre, $urlActual, $loTenemos, $minJug, $maxJug, $descJ, $reglasJ, $originalNombre]);

                // Sincronizar propietarios del juego
                $stmtDelProps = $pdo->prepare("DELETE FROM Juego_Propietario WHERE NombreJuego = ?");
                $stmtDelProps->execute([$nuevoNombre]);

                if ($loTenemos === 1) {
                    $stmtClean = $pdo->prepare("DELETE FROM Persona_Juego WHERE NombreJuego = ?");
                    $stmtClean->execute([$nuevoNombre]);

                    if (count($propietariosSel) > 0) {
                        $stmtInsProp = $pdo->prepare("INSERT IGNORE INTO Juego_Propietario (username, NombreJuego) VALUES (?, ?)");
                        foreach ($propietariosSel as $uProp) {
                            $uPropLimpio = trim((string)$uProp);
                            if ($uPropLimpio !== '') {
                                $stmtInsProp->execute([$uPropLimpio, $nuevoNombre]);
                            }
                        }
                    }
                }

                $_GET['editar_juego'] = $nuevoNombre;
                $mensajeExito = "Juego «{$nuevoNombre}» actualizado correctamente.";
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
// =====================================================================
        // 7. CRUD TABLA RESENYA (LUDOTECA 0-10 Y OPINIÓN PREVIA)
        // =====================================================================
        elseif ($entidad === 'resenya') {
            $escalaPermitida = obtenerEscalaCalificaciones();

            if ($operacion === 'crear' || $operacion === 'editar') {
                $userR = trim($_POST['username']);
                $juegoR = trim($_POST['NombreJuego']);
                $haJugadoR = (isset($_POST['ha_jugado']) && $_POST['ha_jugado'] === '1') ? 1 : 0;
                $calRaw = isset($_POST['calificacion']) ? trim((string)$_POST['calificacion']) : '';
                $comR = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';
                $comR = ($comR !== '') ? $comR : null;
                $calR = null;

                if ($haJugadoR === 1 && $calRaw !== '') {
                    $notaValida = filter_var($calRaw, FILTER_VALIDATE_INT, [
                        'options' => ['min_range' => 0, 'max_range' => 10]
                    ]);
                    if ($notaValida !== false && array_key_exists((int)$notaValida, $escalaPermitida)) {
                        $calR = (int)$notaValida;
                    }
                }

                if ($operacion === 'crear') {
                    $stmt = $pdo->prepare("
                        INSERT INTO Resenya (username, NombreJuego, ha_jugado, calificacion, comentario)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            ha_jugado = VALUES(ha_jugado),
                            calificacion = VALUES(calificacion),
                            comentario = VALUES(comentario)
                    ");
                    $stmt->execute([$userR, $juegoR, $haJugadoR, $calR, $comR]);
                    $mensajeExito = "Reseña de «{$userR}» sobre «{$juegoR}» guardada.";
                } else {
                    $idRes = (int)$_POST['id_resenya'];
                    $stmt = $pdo->prepare("
                        UPDATE Resenya
                        SET username = ?, NombreJuego = ?, ha_jugado = ?, calificacion = ?, comentario = ?
                        WHERE id_resenya = ?
                    ");
                    $stmt->execute([$userR, $juegoR, $haJugadoR, $calR, $comR, $idRes]);
                    $mensajeExito = "Reseña #{$idRes} actualizada correctamente.";
                }
            } elseif ($operacion === 'eliminar') {
                $idRes = (int)$_POST['id_resenya'];
                $stmt = $pdo->prepare("DELETE FROM Resenya WHERE id_resenya = ?");
                $stmt->execute([$idRes]);
                $mensajeExito = "Reseña #{$idRes} eliminada.";
            }
        }
    } catch (Exception $e) {
        $mensajeError = "Error en la operación de administración: " . $e->getMessage();
    }
}

// Consultas de todas las tablas instanciadas en la base de datos
$personas = $pdo->query("SELECT * FROM Persona ORDER BY username ASC")->fetchAll();
$juegos = $pdo->query("SELECT * FROM Juego ORDER BY NombreJuego ASC")->fetchAll();
$propietariosPorJuego = [];
foreach ($pdo->query("SELECT username, NombreJuego FROM Juego_Propietario")->fetchAll() as $fp) {
    $propietariosPorJuego[$fp['NombreJuego']][$fp['username']] = true;
}
$relacionesPJ = $pdo->query("SELECT * FROM Persona_Juego ORDER BY NombreJuego ASC, username ASC")->fetchAll();
$victorias = $pdo->query("SELECT * FROM Victoria ORDER BY id_victoria DESC")->fetchAll();
$votaciones = $pdo->query("SELECT * FROM Votacion ORDER BY id_votacion DESC")->fetchAll();
$votos = $pdo->query("SELECT * FROM Voto ORDER BY id_voto DESC")->fetchAll();
$resenyas = $pdo->query("SELECT * FROM Resenya ORDER BY fecha_resenya DESC, id_resenya DESC")->fetchAll();
$escalaAdminResenyas = obtenerEscalaCalificaciones();

// Comprobar si estamos en la vista dedicada de edición de un juego concreto
$juegoEditarNombre = isset($_GET['editar_juego']) ? trim($_GET['editar_juego']) : '';
$juegoEditarDetalle = null;
if ($juegoEditarNombre !== '') {
    foreach ($juegos as $jItem) {
        if ($jItem['NombreJuego'] === $juegoEditarNombre) {
            $juegoEditarDetalle = $jItem;
            break;
        }
    }
    if (!$juegoEditarDetalle && $mensajeError === '') {
        $mensajeError = 'El juego solicitado para editar no existe.';
    }
}
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

    <?php if ($juegoEditarDetalle): ?>
        <!-- =================================================================
             VISTA DEDICADA DE EDICIÓN DE JUEGO EN ADMIN (ESTILO DETALLE LUDOTECA)
             ================================================================= -->
        <?php
        $nomJEdit = $juegoEditarDetalle['NombreJuego'];
        $loTenEdit = ((int)$juegoEditarDetalle['lo_tenemos'] === 1);
        ?>
        <section class="pergamino">
            <div class="barra-titulo-filtro" style="border-bottom: 2px solid #4a3219; padding-bottom: 10px; margin-bottom: 18px;">
                <h2 class="seccion-titulo" style="margin-bottom:0; border-bottom:none; padding-bottom:0;">
                    Editar Juego: «<?php echo h($nomJEdit); ?>»
                </h2>
                <a href="admin.php" class="btn btn-bronce btn-pequeno">&larr; Volver al Panel de Administración</a>
            </div>

            <div class="ludoteca-detalle-superior">
                <div class="ludoteca-detalle-imagen">
                    <img src="<?php echo h($juegoEditarDetalle['Url']); ?>" alt="<?php echo h($nomJEdit); ?>">
                </div>

                <form method="POST" action="admin.php" enctype="multipart/form-data" class="pergamino-secundario form-ludoteca" data-form-juego-seguro>
                    <input type="hidden" name="entidad" value="juego">
                    <input type="hidden" name="operacion" value="editar">
                    <input type="hidden" name="original_NombreJuego" value="<?php echo h($nomJEdit); ?>">

                    <div class="grid-dos-columnas" style="gap:14px;">
                        <div class="grupo-form">
                            <label>Nombre del Juego:</label>
                            <input type="text" name="NombreJuego" value="<?php echo h($nomJEdit); ?>" class="input-taberna" required maxlength="120">
                        </div>

                        <div class="grupo-form">
                            <label>¿Lo tenemos en la Taberna?:</label>
                            <select name="lo_tenemos" class="select-taberna">
                                <option value="0" <?php echo !$loTenEdit ? 'selected' : ''; ?>>No lo tenemos (En Juegos Deseados)</option>
                                <option value="1" <?php echo $loTenEdit ? 'selected' : ''; ?>>Ya lo tenemos (En Ludoteca)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-dos-columnas" style="gap:14px;">
                        <div class="grupo-form">
                            <label>Mínimo de Jugadores:</label>
                            <input type="number" name="min_jugadores" value="<?php echo (int)$juegoEditarDetalle['min_jugadores']; ?>" class="input-taberna" required min="1" max="99">
                        </div>
                        <div class="grupo-form">
                            <label>Máximo de Jugadores:</label>
                            <input type="number" name="max_jugadores" value="<?php echo (int)$juegoEditarDetalle['max_jugadores']; ?>" class="input-taberna" required min="1" max="99">
                        </div>
                    </div>

                    <div class="grupo-form">
                        <label>Subir una imagen nueva (opcional, Máx 5 MB):</label>
                        <input type="file" name="nueva_imagen" class="input-taberna" accept="image/*">
                    </div>

                    <div class="grupo-form">
                        <label>Propietarios del juego (si ya lo tenemos):</label>
                        <div class="multiselect-taberna" data-multiselect>
                            <input type="text" class="input-taberna" placeholder="Buscar propietario..." data-multiselect-search style="margin-bottom:6px;">
                            <div class="multiselect-lista">
                                <?php foreach ($personas as $per): ?>
                                    <?php $esProp = isset($propietariosPorJuego[$nomJEdit][$per['username']]); ?>
                                    <label class="multiselect-opcion" data-multiselect-item data-nombre="<?php echo h($per['NombrePersona'] . ' ' . $per['username']); ?>">
                                        <input type="checkbox" name="propietarios[]" value="<?php echo h($per['username']); ?>" <?php echo $esProp ? 'checked' : ''; ?>>
                                        <strong><?php echo h($per['NombrePersona']); ?></strong> (<?php echo h($per['username']); ?>)
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="grupo-form">
                        <label>Descripción:</label>
                        <textarea data-campo-seguro="descripcion" class="input-taberna" rows="3" placeholder="Descripción del juego..."><?php echo h((string)$juegoEditarDetalle['descripcion']); ?></textarea>
                        <input type="hidden" name="descripcion_hex" value="">
                    </div>

                    <div class="grupo-form">
                        <label>Reglas / Notas de partida:</label>
                        <textarea data-campo-seguro="reglas" class="input-taberna" rows="4" placeholder="Reglas o resumen de partida..."><?php echo h((string)$juegoEditarDetalle['reglas']); ?></textarea>
                        <input type="hidden" name="reglas_hex" value="">
                    </div>

                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button type="submit" class="btn btn-exito">Guardar Cambios del Juego</button>
                        <a href="admin.php" class="btn btn-bronce">Volver al Panel Admin</a>
                    </div>
                </form>
            </div>
        </section>

    <?php else: ?>

    <section class="pergamino">
        <h2 class="seccion-titulo">Panel de Administración</h2>
        <p>
            Desde este panel puedes inspeccionar, modificar o eliminar cualquier registro de las tablas del sistema.
        </p>
    </section>

    <!-- 1. GESTIÓN DE PERSONAS -->
    <section class="pergamino">
        <h3 class="seccion-titulo">1. Usuarios (<?php echo count($personas); ?>)</h3>
        <div class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Nombre y Apellidos</th>
                        <th>Email</th>
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
                                <input type="email" form="<?php echo $formIdP; ?>" name="email" value="<?php echo h((string)$p['email']); ?>" class="input-taberna" maxlength="150" placeholder="Sin email">
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

    <!-- 2. GESTIÓN DE JUEGOS (SOLO MINIATURA, NOMBRE Y ACCIONES) -->
    <section class="pergamino">
        <div class="barra-titulo-filtro">
            <h3 class="seccion-titulo" style="margin-bottom:0; border-bottom:none;">2. Juegos Registrados (<?php echo count($juegos); ?>)</h3>
            <?php renderizarBuscador('Buscar juego...', ['target_table' => 'tabla-admin-juegos']); ?>
        </div>
        <div class="tabla-contenedor">
            <table class="tabla-taberna" id="tabla-admin-juegos">
                <thead>
                    <tr>
                        <th style="width:110px;">Miniatura</th>
                        <th>Nombre del Juego</th>
                        <th style="width:210px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($juegos as $j): ?>
                        <?php
                        $nomJ = $j['NombreJuego'];
                        $loTen = ((int)$j['lo_tenemos'] === 1);
                        ?>
                        <tr>
                            <td>
                                <img src="<?php echo h($j['Url']); ?>" alt="<?php echo h($nomJ); ?>" class="img-juego-tabla">
                            </td>
                            <td>
                                <strong style="font-size:1.08rem;"><?php echo h($nomJ); ?></strong>
                                <div style="margin-top:4px;">
                                    <?php if ($loTen): ?>
                                        <span class="etiqueta etiqueta-disponible">Ya lo tenemos</span>
                                    <?php else: ?>
                                        <span class="etiqueta etiqueta-pendiente">No lo tenemos</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <a href="admin.php?editar_juego=<?php echo urlencode($nomJ); ?>" class="btn btn-bronce btn-pequeno">Editar</a>
                                    <form method="POST" action="admin.php" class="form-confirmar-borrado" data-confirm-msg="¿Eliminar el juego <?php echo h($nomJ); ?> y sus votos/victorias?">
                                        <input type="hidden" name="entidad" value="juego">
                                        <input type="hidden" name="operacion" value="eliminar">
                                        <input type="hidden" name="NombreJuego" value="<?php echo h($nomJ); ?>">
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
        <h3 class="seccion-titulo">3. Interesados en Juegos (<?php echo count($relacionesPJ); ?>)</h3>
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
                        <th>Username</th>
                        <th>NombreJuego</th>
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

    <!-- 4. GESTIÓN DE VICTORIAS (SIN COLUMNA ID) -->
    <section class="pergamino">
        <h3 class="seccion-titulo">4. Partidas Ganadas (<?php echo count($victorias); ?>)</h3>
        <div class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
                        <th>Ganador</th>
                        <th>Juego</th>
                        <th>Fecha y Hora</th>
                        <th style="width:210px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($victorias as $v): ?>
                        <?php $formIdV = 'form_vic_' . (int)$v['id_victoria']; ?>
                        <tr>
                            <td>
                                <form id="<?php echo $formIdV; ?>" method="POST" action="admin.php">
                                    <input type="hidden" name="entidad" value="victoria">
                                    <input type="hidden" name="operacion" value="editar">
                                    <input type="hidden" name="id_victoria" value="<?php echo (int)$v['id_victoria']; ?>">
                                </form>
                                <select form="<?php echo $formIdV; ?>" name="username" class="select-taberna">
                                    <?php foreach ($personas as $p): ?>
                                        <option value="<?php echo h($p['username']); ?>" <?php echo $p['username'] === $v['username'] ? 'selected' : ''; ?>>
                                            <?php echo h($p['NombrePersona']); ?> (<?php echo h($p['username']); ?>)
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

    <!-- 5. GESTIÓN DE VOTACIONES (SIN COLUMNA ID) -->
    <section class="pergamino">
        <h3 class="seccion-titulo">5. Tabla Votacion (Todas las Votaciones: <?php echo count($votaciones); ?>)</h3>
        <div class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
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
                                <form id="<?php echo $formIdVot; ?>" method="POST" action="admin.php">
                                    <input type="hidden" name="entidad" value="votacion">
                                    <input type="hidden" name="operacion" value="editar">
                                    <input type="hidden" name="id_votacion" value="<?php echo (int)$vot['id_votacion']; ?>">
                                </form>
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

    <!-- 6. GESTIÓN DE VOTOS INDIVIDUALES (SIN COLUMNA ID) -->
    <section class="pergamino">
        <h3 class="seccion-titulo">6. Tabla Voto (Votos Emitidos: <?php echo count($votos); ?>)</h3>
        <div class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
                        <th>Votación</th>
                        <th>Usuario</th>
                        <th>Juego Votado</th>
                        <th style="width:210px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($votos as $vt): ?>
                        <?php $formIdVt = 'form_vt_' . (int)$vt['id_voto']; ?>
                        <tr>
                            <td>
                                <form id="<?php echo $formIdVt; ?>" method="POST" action="admin.php">
                                    <input type="hidden" name="entidad" value="voto">
                                    <input type="hidden" name="operacion" value="editar">
                                    <input type="hidden" name="id_voto" value="<?php echo (int)$vt['id_voto']; ?>">
                                </form>
                                <select form="<?php echo $formIdVt; ?>" name="id_votacion" class="select-taberna">
                                    <?php foreach ($votaciones as $vot): ?>
                                        <option value="<?php echo (int)$vot['id_votacion']; ?>" <?php echo (int)$vot['id_votacion'] === (int)$vt['id_votacion'] ? 'selected' : ''; ?>>
                                            <?php echo h($vot['titulo']); ?>
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

    <!-- 7. GESTIÓN DE RESEÑAS (SIN COLUMNA ID NI COLUMNA FECHA) -->
    <section class="pergamino">
        <h3 class="seccion-titulo">7. Reseñas de la Ludoteca (<?php echo count($resenyas); ?>)</h3>

        <form method="POST" action="admin.php" class="pergamino-secundario" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:12px;">
            <input type="hidden" name="entidad" value="resenya">
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

            <div>
                <label>¿Jugado?:</label>
                <select name="ha_jugado" class="select-taberna">
                    <option value="1">1 - Sí</option>
                    <option value="0">0 - No</option>
                </select>
            </div>

            <div>
                <label>Calificación (0-10):</label>
                <select name="calificacion" class="select-taberna">
                    <option value="">-- Ninguna (NULL) --</option>
                    <?php foreach ($escalaAdminResenyas as $num => $txtCal): ?>
                        <option value="<?php echo (int)$num; ?>"><?php echo (int)$num . ' - ' . h($txtCal); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex:1; min-width:180px;">
                <label>Comentario:</label>
                <input type="text" name="comentario" class="input-taberna" placeholder="Opinión...">
            </div>

            <button type="submit" class="btn btn-bronce btn-pequeno">Guardar Reseña</button>
        </form>

        <div class="tabla-contenedor">
            <table class="tabla-taberna">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Juego</th>
                        <th style="width:110px;">¿Jugado?</th>
                        <th>Calificación (0-10)</th>
                        <th>Comentario</th>
                        <th style="width:190px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resenyas as $r): ?>
                        <?php $formIdR = 'form_res_' . (int)$r['id_resenya']; ?>
                        <tr>
                            <td>
                                <form id="<?php echo $formIdR; ?>" method="POST" action="admin.php">
                                    <input type="hidden" name="entidad" value="resenya">
                                    <input type="hidden" name="operacion" value="editar">
                                    <input type="hidden" name="id_resenya" value="<?php echo (int)$r['id_resenya']; ?>">
                                </form>
                                <select form="<?php echo $formIdR; ?>" name="username" class="select-taberna">
                                    <?php foreach ($personas as $p): ?>
                                        <option value="<?php echo h($p['username']); ?>" <?php echo $p['username'] === $r['username'] ? 'selected' : ''; ?>>
                                            <?php echo h($p['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select form="<?php echo $formIdR; ?>" name="NombreJuego" class="select-taberna">
                                    <?php foreach ($juegos as $j): ?>
                                        <option value="<?php echo h($j['NombreJuego']); ?>" <?php echo $j['NombreJuego'] === $r['NombreJuego'] ? 'selected' : ''; ?>>
                                            <?php echo h($j['NombreJuego']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select form="<?php echo $formIdR; ?>" name="ha_jugado" class="select-taberna">
                                    <option value="1" <?php echo (int)$r['ha_jugado'] === 1 ? 'selected' : ''; ?>>Sí (1)</option>
                                    <option value="0" <?php echo (int)$r['ha_jugado'] === 0 ? 'selected' : ''; ?>>No (0)</option>
                                </select>
                            </td>
                            <td>
                                <select form="<?php echo $formIdR; ?>" name="calificacion" class="select-taberna">
                                    <option value="">-- NULL (Opinión previa) --</option>
                                    <?php foreach ($escalaAdminResenyas as $num => $txtCal): ?>
                                        <option value="<?php echo (int)$num; ?>" <?php echo ($r['calificacion'] !== null && $r['calificacion'] !== '' && (int)$r['calificacion'] === (int)$num) ? 'selected' : ''; ?>>
                                            <?php echo (int)$num . ' - ' . h($txtCal); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" form="<?php echo $formIdR; ?>" name="comentario" value="<?php echo h((string)$r['comentario']); ?>" class="input-taberna">
                            </td>
                            <td>
                                <div class="acciones-admin-celda">
                                    <button type="submit" form="<?php echo $formIdR; ?>" class="btn btn-exito btn-pequeno">Guardar</button>
                                    <form method="POST" action="admin.php" class="form-confirmar-borrado" data-confirm-msg="¿Eliminar esta reseña?">
                                        <input type="hidden" name="entidad" value="resenya">
                                        <input type="hidden" name="operacion" value="eliminar">
                                        <input type="hidden" name="id_resenya" value="<?php echo (int)$r['id_resenya']; ?>">
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

    <?php endif; ?>
</main>

<script src="app.js?v=<?php echo @filemtime(__DIR__ . '/app.js') ?: time(); ?>"></script>
</body>
</html>