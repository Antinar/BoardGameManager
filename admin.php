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

                // Duración opcional: entero entre 1 y 1440 minutos
                $durRaw = isset($_POST['duracion_minutos']) ? trim((string)$_POST['duracion_minutos']) : '';
                $duracion = null;
                if ($durRaw !== '') {
                    $durValida = filter_var($durRaw, FILTER_VALIDATE_INT, [
                        'options' => ['min_range' => 1, 'max_range' => 1440]
                    ]);
                    if ($durValida === false) {
                        throw new Exception("La duración de la victoria #{$idVic} debe ser un número entero entre 1 y 1440 minutos.");
                    }
                    $duracion = (int)$durValida;
                }

                // Puntuación opcional: máximo 100 caracteres
                $puntRaw = isset($_POST['puntuacion']) ? trim((string)$_POST['puntuacion']) : '';
                $puntuacion = null;
                if ($puntRaw !== '') {
                    if (mb_strlen($puntRaw) > 100) {
                        throw new Exception("La puntuación de la victoria #{$idVic} no puede superar los 100 caracteres.");
                    }
                    $puntuacion = $puntRaw;
                }

                // Fecha con formato estricto YYYY-MM-DD HH:MM:SS
                $fechaDt = DateTime::createFromFormat('Y-m-d H:i:s', $fecha);
                if ($fechaDt === false || $fechaDt->format('Y-m-d H:i:s') !== $fecha) {
                    throw new Exception("La fecha de la victoria #{$idVic} no tiene el formato válido AAAA-MM-DD HH:MM:SS.");
                }

                // Participantes: solo usuarios reales, sin duplicados y con el ganador siempre incluido
                $participantesSel = (isset($_POST['participantes']) && is_array($_POST['participantes'])) ? $_POST['participantes'] : [];
                $usuariosReales = [];
                foreach ($pdo->query("SELECT username FROM Persona")->fetchAll() as $filaUsu) {
                    $usuariosReales[(string)$filaUsu['username']] = true;
                }
                $participantesFinales = [];
                foreach ($participantesSel as $uPart) {
                    $uPartLimpio = trim((string)$uPart);
                    if ($uPartLimpio !== '' && isset($usuariosReales[$uPartLimpio])) {
                        $participantesFinales[$uPartLimpio] = true;
                    }
                }
                if ($u !== '' && isset($usuariosReales[$u])) {
                    $participantesFinales[$u] = true;
                } else {
                    throw new Exception("El ganador seleccionado en la victoria #{$idVic} no es un usuario válido.");
                }
                $participantesFinales = array_keys($participantesFinales);

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("UPDATE Victoria SET username = ?, NombreJuego = ?, fecha_victoria = ?, duracion_minutos = ?, puntuacion = ? WHERE id_victoria = ?");
                    $stmt->execute([$u, $j, $fecha, $duracion, $puntuacion, $idVic]);

                    $stmtDelPart = $pdo->prepare("DELETE FROM Victoria_Participante WHERE id_victoria = ?");
                    $stmtDelPart->execute([$idVic]);

                    $stmtInsPart = $pdo->prepare("INSERT IGNORE INTO Victoria_Participante (id_victoria, username) VALUES (?, ?)");
                    foreach ($participantesFinales as $uPartIns) {
                        $stmtInsPart->execute([$idVic, $uPartIns]);
                    }

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                $mensajeExito = "Victoria #{$idVic} actualizada (fecha, duración, puntuación y participantes guardados).";
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
// =====================================================================
        // 8. CRUD CATÁLOGOS EXTENSIBLES DE ROOT (FACCIONES, MAPAS, MAZOS)
        // =====================================================================
        elseif (in_array($entidad, ['root_faccion', 'root_mapa', 'root_mazo'], true)) {
            $configRoot = [
                'root_faccion' => ['tabla' => 'Root_Faccion', 'pk' => 'id_faccion', 'activo' => 'activa', 'etiqueta' => 'facción'],
                'root_mapa'    => ['tabla' => 'Root_Mapa',    'pk' => 'id_mapa',    'activo' => 'activo', 'etiqueta' => 'mapa'],
                'root_mazo'    => ['tabla' => 'Root_Mazo',    'pk' => 'id_mazo',    'activo' => 'activo', 'etiqueta' => 'mazo'],
            ];
            $cfg = $configRoot[$entidad];
            $tabla = $cfg['tabla'];
            $pk = $cfg['pk'];
            $campoActivo = $cfg['activo'];
            $etiqueta = $cfg['etiqueta'];

            try {
                if ($operacion === 'crear' || $operacion === 'editar') {
                    $nombreRoot = isset($_POST['nombre']) ? trim((string)$_POST['nombre']) : '';
                    if ($nombreRoot === '') {
                        throw new Exception("El nombre de la {$etiqueta} es obligatorio.");
                    }
                    if (mb_strlen($nombreRoot) > 100) {
                        throw new Exception("El nombre de la {$etiqueta} no puede superar los 100 caracteres.");
                    }
                    $activoRoot = (isset($_POST['activo']) && $_POST['activo'] === '1') ? 1 : 0;

                    if ($operacion === 'crear') {
                        $stmt = $pdo->prepare("INSERT INTO {$tabla} (nombre, {$campoActivo}) VALUES (?, ?)");
                        $stmt->execute([$nombreRoot, $activoRoot]);
                        $mensajeExito = ucfirst($etiqueta) . " «{$nombreRoot}» añadido al catálogo.";
                    } else {
                        $idRoot = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                        if ($idRoot <= 0) {
                            throw new Exception("Identificador de {$etiqueta} no válido.");
                        }
                        $stmt = $pdo->prepare("UPDATE {$tabla} SET nombre = ?, {$campoActivo} = ? WHERE {$pk} = ?");
                        $stmt->execute([$nombreRoot, $activoRoot, $idRoot]);
                        $mensajeExito = ucfirst($etiqueta) . " #{$idRoot} actualizado correctamente.";
                    }
                } elseif ($operacion === 'eliminar') {
                    $idRoot = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    if ($idRoot <= 0) {
                        throw new Exception("Identificador de {$etiqueta} no válido.");
                    }
                    $stmt = $pdo->prepare("DELETE FROM {$tabla} WHERE {$pk} = ?");
                    $stmt->execute([$idRoot]);
                    $mensajeExito = ucfirst($etiqueta) . " #{$idRoot} eliminado del catálogo.";
                }
            } catch (PDOException $eRoot) {
                if (isset($eRoot->errorInfo[0]) && $eRoot->errorInfo[0] === '42S02') {
                    $mensajeError = 'Los catálogos de Root no están disponibles. Ejecuta sql/migration_root.sql para crear las tablas necesarias.';
                } elseif (isset($eRoot->errorInfo[0]) && $eRoot->errorInfo[0] === '23000') {
                    if ($operacion === 'eliminar') {
                        $mensajeError = "No se puede borrar porque está en uso; desactívalo en su lugar.";
                    } else {
                        $mensajeError = "Ya existe un registro con ese nombre.";
                    }
                } else {
                    throw $eRoot;
                }
            }
        }
// =====================================================================
        // 9. CRUD PERSONAJES DE JUEGOS ASIMÉTRICOS
        // =====================================================================
        elseif ($entidad === 'asimetrico_personaje') {
            $codigoJuego = isset($_POST['codigo_juego']) ? trim((string)$_POST['codigo_juego']) : '';

            try {
                $stmtJuegoAsim = $pdo->prepare("SELECT codigo_juego, nombre_visible, num_personajes_por_jugador FROM Juego_Asimetrico WHERE codigo_juego = ?");
                $stmtJuegoAsim->execute([$codigoJuego]);
                $juegoAsim = $stmtJuegoAsim->fetch();
                if (!$juegoAsim) {
                    throw new Exception('Juego asimétrico no válido.');
                }
                $_GET['admin_asimetrico'] = $codigoJuego; // conservar selector tras POST

                if ($operacion === 'crear' || $operacion === 'editar') {
                    $nombreAsim = isset($_POST['nombre']) ? trim((string)$_POST['nombre']) : '';
                    if ($nombreAsim === '') {
                        throw new Exception('El nombre del personaje es obligatorio.');
                    }
                    if (mb_strlen($nombreAsim) > 100) {
                        throw new Exception('El nombre del personaje no puede superar los 100 caracteres.');
                    }
                    $activoAsim = (isset($_POST['activo']) && $_POST['activo'] === '1') ? 1 : 0;

                    if ($operacion === 'crear') {
                        $stmt = $pdo->prepare("INSERT INTO Personaje_Asimetrico (codigo_juego, nombre_personaje, activo) VALUES (?, ?, ?)");
                        $stmt->execute([$codigoJuego, $nombreAsim, $activoAsim]);
                        $mensajeExito = "Personaje «{$nombreAsim}» añadido al juego «{$juegoAsim['nombre_visible']}».";
                    } else {
                        $idPersonaje = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                        if ($idPersonaje <= 0) {
                            throw new Exception('Identificador de personaje no válido.');
                        }
                        $stmtExisteAsim = $pdo->prepare("SELECT COUNT(*) FROM Personaje_Asimetrico WHERE id_personaje = ? AND codigo_juego = ?");
                        $stmtExisteAsim->execute([$idPersonaje, $codigoJuego]);
                        if ((int)$stmtExisteAsim->fetchColumn() === 0) {
                            throw new Exception('El personaje no existe en el juego seleccionado.');
                        }
                        $stmt = $pdo->prepare("UPDATE Personaje_Asimetrico SET nombre_personaje = ?, activo = ? WHERE id_personaje = ? AND codigo_juego = ?");
                        $stmt->execute([$nombreAsim, $activoAsim, $idPersonaje, $codigoJuego]);
                        $mensajeExito = "Personaje «{$nombreAsim}» actualizado correctamente.";
                    }
                } elseif ($operacion === 'eliminar') {
                    $idPersonaje = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    if ($idPersonaje <= 0) {
                        throw new Exception('Identificador de personaje no válido.');
                    }
                    $stmtUso = $pdo->prepare("SELECT COUNT(*) FROM Participante_Personaje_Asimetrico WHERE id_personaje = ?");
                    $stmtUso->execute([$idPersonaje]);
                    if ((int)$stmtUso->fetchColumn() > 0) {
                        $mensajeError = 'No se puede borrar el personaje porque está en uso en partidas. Desactívalo en su lugar.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM Personaje_Asimetrico WHERE id_personaje = ? AND codigo_juego = ?");
                        $stmt->execute([$idPersonaje, $codigoJuego]);
                        if ($stmt->rowCount() === 0) {
                            $mensajeError = 'El personaje no existe en el juego seleccionado.';
                        } else {
                            $mensajeExito = "Personaje #{$idPersonaje} eliminado del juego «{$juegoAsim['nombre_visible']}».";
                        }
                    }
                }
            } catch (PDOException $eAsim) {
                if (isset($eAsim->errorInfo[0]) && $eAsim->errorInfo[0] === '42S02') {
                    $mensajeError = 'Los catálogos asimétricos no están disponibles. Ejecuta sql/migration_asimetricos.sql para crear las tablas necesarias.';
                } elseif (isset($eAsim->errorInfo[0]) && $eAsim->errorInfo[0] === '23000') {
                    if ($operacion === 'eliminar') {
                        $mensajeError = 'No se puede borrar el personaje porque está en uso. Desactívalo en su lugar.';
                    } else {
                        $mensajeError = 'Ya existe un personaje con ese nombre en este juego.';
                    }
                } else {
                    throw $eAsim;
                }
            }
        }
    } catch (Throwable $e) {
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
$participantesPorVictoria = [];
if (count($victorias) > 0) {
    $idsVictorias = array_map(function ($v) { return (int)$v['id_victoria']; }, $victorias);
    $placeholders = implode(',', array_fill(0, count($idsVictorias), '?'));
    $stmtPart = $pdo->prepare("
        SELECT vp.id_victoria, vp.username, p.NombrePersona
        FROM Victoria_Participante vp
        INNER JOIN Persona p ON vp.username = p.username
        WHERE vp.id_victoria IN ($placeholders)
        ORDER BY p.NombrePersona ASC
    ");
    $stmtPart->execute($idsVictorias);
    foreach ($stmtPart->fetchAll() as $filaPart) {
        $participantesPorVictoria[(int)$filaPart['id_victoria']][] = $filaPart;
    }
}
$votaciones = $pdo->query("SELECT * FROM Votacion ORDER BY id_votacion DESC")->fetchAll();
$votos = $pdo->query("SELECT * FROM Voto ORDER BY id_voto DESC")->fetchAll();
$resenyas = $pdo->query("SELECT * FROM Resenya ORDER BY fecha_resenya DESC, id_resenya DESC")->fetchAll();
$escalaAdminResenyas = obtenerEscalaCalificaciones();

$rootCatalogsDisponibles = true;
$rootFaccionesAdmin = [];
$rootMapasAdmin = [];
$rootMazosAdmin = [];
try {
  $rootFaccionesAdmin = $pdo->query("SELECT id_faccion, nombre, activa FROM Root_Faccion ORDER BY nombre ASC")->fetchAll();
  $rootMapasAdmin = $pdo->query("SELECT id_mapa, nombre, activo FROM Root_Mapa ORDER BY nombre ASC")->fetchAll();
  $rootMazosAdmin = $pdo->query("SELECT id_mazo, nombre, activo FROM Root_Mazo ORDER BY nombre ASC")->fetchAll();
} catch (PDOException $e) {
  $rootCatalogsDisponibles = false;
  error_log('Catálogos Root no disponibles: ' . $e->getMessage());
}

$asimetricosAdminDisponibles = true;
$juegosAsimetricosAdmin = [];
$personajesAsimetricosAdmin = [];
$codigoAsimetricoAdminSeleccionado = '';
$juegoAsimetricoAdminActual = null;
try {
    $juegosAsimetricosAdmin = $pdo->query("SELECT codigo_juego, nombre_visible, num_personajes_por_jugador FROM Juego_Asimetrico ORDER BY nombre_visible ASC")->fetchAll();
    // Orden fijo del enunciado; los juegos futuros quedan al final por nombre visible.
    $ordenJuegosAsimetricos = ['villainous_disney' => 0, 'villainous_marvel' => 1, 'villainous_star_wars' => 2, 'tag_team' => 3];
    usort($juegosAsimetricosAdmin, function ($a, $b) use ($ordenJuegosAsimetricos) {
        $prioA = array_key_exists($a['codigo_juego'], $ordenJuegosAsimetricos) ? $ordenJuegosAsimetricos[$a['codigo_juego']] : PHP_INT_MAX;
        $prioB = array_key_exists($b['codigo_juego'], $ordenJuegosAsimetricos) ? $ordenJuegosAsimetricos[$b['codigo_juego']] : PHP_INT_MAX;
        if ($prioA !== $prioB) {
            return $prioA <=> $prioB;
        }
        return strcmp((string)$a['nombre_visible'], (string)$b['nombre_visible']);
    });
    $codigoGet = isset($_GET['admin_asimetrico']) ? trim((string)$_GET['admin_asimetrico']) : '';
    foreach ($juegosAsimetricosAdmin as $jA) {
        if ($codigoGet !== '' && $jA['codigo_juego'] === $codigoGet) { $codigoAsimetricoAdminSeleccionado = $codigoGet; $juegoAsimetricoAdminActual = $jA; break; }
    }
    if ($codigoAsimetricoAdminSeleccionado === '' && count($juegosAsimetricosAdmin) > 0) {
        $codigoAsimetricoAdminSeleccionado = $juegosAsimetricosAdmin[0]['codigo_juego'];
        $juegoAsimetricoAdminActual = $juegosAsimetricosAdmin[0];
    }
    if ($codigoAsimetricoAdminSeleccionado !== '') {
        $stmtPersAsim = $pdo->prepare("SELECT id_personaje, nombre_personaje, activo FROM Personaje_Asimetrico WHERE codigo_juego = ? ORDER BY nombre_personaje ASC");
        $stmtPersAsim->execute([$codigoAsimetricoAdminSeleccionado]);
        $personajesAsimetricosAdmin = $stmtPersAsim->fetchAll();
    }
} catch (PDOException $e) {
    $asimetricosAdminDisponibles = false;
    error_log('Catálogos asimétricos no disponibles: ' . $e->getMessage());
}

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
                                    <?php if ($loTen): ?>
                                        <a href="ludoteca.php?juego=<?php echo urlencode($nomJ); ?>" class="btn btn-bronce btn-pequeno">Ver ficha</a>
                                    <?php endif; ?>
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
                        <th>Duración (min)</th>
                        <th>Puntuación</th>
                        <th>Participantes</th>
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
                                <input type="number" form="<?php echo $formIdV; ?>" name="duracion_minutos" min="1" max="1440" value="<?php echo h((string)$v['duracion_minutos']); ?>" class="input-taberna" placeholder="(Opcional)">
                            </td>
                            <td>
                                <input type="text" form="<?php echo $formIdV; ?>" name="puntuacion" maxlength="100" value="<?php echo h((string)$v['puntuacion']); ?>" class="input-taberna" placeholder="(Opcional)">
                            </td>
                            <td>
                                <?php
                                $participantesV = isset($participantesPorVictoria[(int)$v['id_victoria']]) ? $participantesPorVictoria[(int)$v['id_victoria']] : [];
                                $usernamesPartV = [];
                                foreach ($participantesV as $filaPartV) {
                                    $usernamesPartV[(string)$filaPartV['username']] = true;
                                }
                                // Respaldo para victorias históricas sin participantes registrados
                                if (count($usernamesPartV) === 0 && (string)$v['username'] !== '') {
                                    $usernamesPartV[(string)$v['username']] = true;
                                }
                                ?>
                                <select form="<?php echo $formIdV; ?>" name="participantes[]" multiple size="4" class="select-taberna select-participantes">
                                    <?php foreach ($personas as $p): ?>
                                        <option value="<?php echo h($p['username']); ?>" <?php echo isset($usernamesPartV[(string)$p['username']]) ? 'selected' : ''; ?>>
                                            <?php echo h($p['NombrePersona']); ?> (<?php echo h($p['username']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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

    <!-- 8. CATÁLOGOS EXTENSIBLES DE ROOT -->
    <section class="pergamino">
        <h3 class="seccion-titulo">8. Catálogos de Root</h3>

        <?php if (!$rootCatalogsDisponibles): ?>
            <div class="alerta alerta-error">
                Los catálogos de Root no están disponibles. Ejecuta <code>sql/migration_root.sql</code> para crear las tablas necesarias.
            </div>
        <?php else: ?>

            <!-- FACCIONES -->
            <h4 style="margin-top:0;">Facciones (<?php echo count($rootFaccionesAdmin); ?>)</h4>
            <form method="POST" action="admin.php" class="pergamino-secundario" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:12px;">
                <input type="hidden" name="entidad" value="root_faccion">
                <input type="hidden" name="operacion" value="crear">
                <div>
                    <label>Nueva facción:</label>
                    <input type="text" name="nombre" class="input-taberna" required maxlength="100" placeholder="Ej. Homeland...">
                </div>
                <label style="font-weight:bold;"><input type="checkbox" name="activo" value="1" checked> Activa</label>
                <button type="submit" class="btn btn-bronce btn-pequeno">Añadir</button>
            </form>

            <div class="tabla-contenedor" style="max-height:420px; overflow-y:auto;">
                <table class="tabla-taberna">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th style="width:110px;">Activo</th>
                            <th style="width:190px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rootFaccionesAdmin as $rf): ?>
                            <?php $formIdRf = 'form_root_fac_' . (int)$rf['id_faccion']; ?>
                            <tr>
                                <td>
                                    <form id="<?php echo h($formIdRf); ?>" method="POST" action="admin.php">
                                        <input type="hidden" name="entidad" value="root_faccion">
                                        <input type="hidden" name="operacion" value="editar">
                                        <input type="hidden" name="id" value="<?php echo (int)$rf['id_faccion']; ?>">
                                    </form>
                                    <input type="text" form="<?php echo h($formIdRf); ?>" name="nombre" value="<?php echo h((string)$rf['nombre']); ?>" class="input-taberna" maxlength="100">
                                </td>
                                <td>
                                    <input type="checkbox" form="<?php echo h($formIdRf); ?>" name="activo" value="1" <?php echo (int)$rf['activa'] === 1 ? 'checked' : ''; ?>>
                                </td>
                                <td>
                                    <div class="acciones-admin-celda">
                                        <button type="submit" form="<?php echo h($formIdRf); ?>" class="btn btn-exito btn-pequeno">Guardar</button>
                                        <form method="POST" action="admin.php" class="form-confirmar-borrado" data-confirm-msg="¿Eliminar la facción <?php echo h((string)$rf['nombre']); ?>?">
                                            <input type="hidden" name="entidad" value="root_faccion">
                                            <input type="hidden" name="operacion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo (int)$rf['id_faccion']; ?>">
                                            <button type="submit" class="btn btn-peligro btn-pequeno">Borrar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- MAPAS -->
            <h4>Mapas (<?php echo count($rootMapasAdmin); ?>)</h4>
            <form method="POST" action="admin.php" class="pergamino-secundario" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:12px;">
                <input type="hidden" name="entidad" value="root_mapa">
                <input type="hidden" name="operacion" value="crear">
                <div>
                    <label>Nuevo mapa:</label>
                    <input type="text" name="nombre" class="input-taberna" required maxlength="100" placeholder="Ej. Bosque de Otoño...">
                </div>
                <label style="font-weight:bold;"><input type="checkbox" name="activo" value="1" checked> Activo</label>
                <button type="submit" class="btn btn-bronce btn-pequeno">Añadir</button>
            </form>

            <div class="tabla-contenedor" style="max-height:420px; overflow-y:auto;">
                <table class="tabla-taberna">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th style="width:110px;">Activo</th>
                            <th style="width:190px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rootMapasAdmin as $rm): ?>
                            <?php $formIdRm = 'form_root_map_' . (int)$rm['id_mapa']; ?>
                            <tr>
                                <td>
                                    <form id="<?php echo h($formIdRm); ?>" method="POST" action="admin.php">
                                        <input type="hidden" name="entidad" value="root_mapa">
                                        <input type="hidden" name="operacion" value="editar">
                                        <input type="hidden" name="id" value="<?php echo (int)$rm['id_mapa']; ?>">
                                    </form>
                                    <input type="text" form="<?php echo h($formIdRm); ?>" name="nombre" value="<?php echo h((string)$rm['nombre']); ?>" class="input-taberna" maxlength="100">
                                </td>
                                <td>
                                    <input type="checkbox" form="<?php echo h($formIdRm); ?>" name="activo" value="1" <?php echo (int)$rm['activo'] === 1 ? 'checked' : ''; ?>>
                                </td>
                                <td>
                                    <div class="acciones-admin-celda">
                                        <button type="submit" form="<?php echo h($formIdRm); ?>" class="btn btn-exito btn-pequeno">Guardar</button>
                                        <form method="POST" action="admin.php" class="form-confirmar-borrado" data-confirm-msg="¿Eliminar el mapa <?php echo h((string)$rm['nombre']); ?>?">
                                            <input type="hidden" name="entidad" value="root_mapa">
                                            <input type="hidden" name="operacion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo (int)$rm['id_mapa']; ?>">
                                            <button type="submit" class="btn btn-peligro btn-pequeno">Borrar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- MAZOS -->
            <h4>Mazos (<?php echo count($rootMazosAdmin); ?>)</h4>
            <form method="POST" action="admin.php" class="pergamino-secundario" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:12px;">
                <input type="hidden" name="entidad" value="root_mazo">
                <input type="hidden" name="operacion" value="crear">
                <div>
                    <label>Nuevo mazo:</label>
                    <input type="text" name="nombre" class="input-taberna" required maxlength="100" placeholder="Ej. Mazo de los Nidos...">
                </div>
                <label style="font-weight:bold;"><input type="checkbox" name="activo" value="1" checked> Activo</label>
                <button type="submit" class="btn btn-bronce btn-pequeno">Añadir</button>
            </form>

            <div class="tabla-contenedor" style="max-height:420px; overflow-y:auto;">
                <table class="tabla-taberna">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th style="width:110px;">Activo</th>
                            <th style="width:190px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rootMazosAdmin as $rz): ?>
                            <?php $formIdRz = 'form_root_maz_' . (int)$rz['id_mazo']; ?>
                            <tr>
                                <td>
                                    <form id="<?php echo h($formIdRz); ?>" method="POST" action="admin.php">
                                        <input type="hidden" name="entidad" value="root_mazo">
                                        <input type="hidden" name="operacion" value="editar">
                                        <input type="hidden" name="id" value="<?php echo (int)$rz['id_mazo']; ?>">
                                    </form>
                                    <input type="text" form="<?php echo h($formIdRz); ?>" name="nombre" value="<?php echo h((string)$rz['nombre']); ?>" class="input-taberna" maxlength="100">
                                </td>
                                <td>
                                    <input type="checkbox" form="<?php echo h($formIdRz); ?>" name="activo" value="1" <?php echo (int)$rz['activo'] === 1 ? 'checked' : ''; ?>>
                                </td>
                                <td>
                                    <div class="acciones-admin-celda">
                                        <button type="submit" form="<?php echo h($formIdRz); ?>" class="btn btn-exito btn-pequeno">Guardar</button>
                                        <form method="POST" action="admin.php" class="form-confirmar-borrado" data-confirm-msg="¿Eliminar el mazo <?php echo h((string)$rz['nombre']); ?>?">
                                            <input type="hidden" name="entidad" value="root_mazo">
                                            <input type="hidden" name="operacion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo (int)$rz['id_mazo']; ?>">
                                            <button type="submit" class="btn btn-peligro btn-pequeno">Borrar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>

    <!-- 9. CATÁLOGOS DE JUEGOS ASIMÉTRICOS -->
    <section class="pergamino">
        <h3 class="seccion-titulo">9. Catálogos de Juegos Asimétricos</h3>

        <?php if (!$asimetricosAdminDisponibles): ?>
            <div class="alerta alerta-error">
                Los catálogos de juegos asimétricos no están disponibles. Ejecuta <code>sql/migration_asimetricos.sql</code> para crear las tablas necesarias.
            </div>
        <?php elseif (count($juegosAsimetricosAdmin) === 0): ?>
            <div class="alerta">
                No hay juegos asimétricos configurados.
            </div>
        <?php else: ?>

            <form method="GET" action="admin.php" class="pergamino-secundario" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:12px;">
                <div>
                    <label>Juego:</label>
                    <select name="admin_asimetrico" class="select-taberna" onchange="this.form.submit()">
                        <?php foreach ($juegosAsimetricosAdmin as $jA): ?>
                            <option value="<?php echo h($jA['codigo_juego']); ?>" <?php echo $jA['codigo_juego'] === $codigoAsimetricoAdminSeleccionado ? 'selected' : ''; ?>>
                                <?php echo h($jA['nombre_visible']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <noscript><button type="submit" class="btn btn-pequeno">Cambiar</button></noscript>
            </form>

            <?php if ($juegoAsimetricoAdminActual !== null): ?>
                <p class="pergamino-secundario">
                    <strong><?php echo h($juegoAsimetricoAdminActual['nombre_visible']); ?></strong>:
                    <?php echo (int)$juegoAsimetricoAdminActual['num_personajes_por_jugador'] === 1 ? '1 personaje por jugador' : (int)$juegoAsimetricoAdminActual['num_personajes_por_jugador'] . ' personajes por jugador'; ?>
                </p>
            <?php endif; ?>

            <form method="POST" action="admin.php" class="pergamino-secundario" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:12px;">
                <input type="hidden" name="entidad" value="asimetrico_personaje">
                <input type="hidden" name="operacion" value="crear">
                <input type="hidden" name="codigo_juego" value="<?php echo h($codigoAsimetricoAdminSeleccionado); ?>">
                <div>
                    <label>Nuevo personaje:</label>
                    <input type="text" name="nombre" class="input-taberna" required maxlength="100" placeholder="Nombre del personaje...">
                </div>
                <label style="font-weight:bold;"><input type="checkbox" name="activo" value="1" checked> Activo</label>
                <button type="submit" class="btn btn-bronce btn-pequeno">Añadir</button>
            </form>

            <div class="tabla-contenedor" style="max-height:420px; overflow-y:auto;">
                <table class="tabla-taberna">
                    <thead>
                        <tr>
                            <th>Personaje</th>
                            <th style="width:110px;">Activo</th>
                            <th style="width:190px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personajesAsimetricosAdmin as $ap): ?>
                            <?php $formIdAp = 'form_asim_pers_' . (int)$ap['id_personaje']; ?>
                            <tr>
                                <td>
                                    <form id="<?php echo h($formIdAp); ?>" method="POST" action="admin.php">
                                        <input type="hidden" name="entidad" value="asimetrico_personaje">
                                        <input type="hidden" name="operacion" value="editar">
                                        <input type="hidden" name="id" value="<?php echo (int)$ap['id_personaje']; ?>">
                                        <input type="hidden" name="codigo_juego" value="<?php echo h($codigoAsimetricoAdminSeleccionado); ?>">
                                    </form>
                                    <input type="text" form="<?php echo h($formIdAp); ?>" name="nombre" value="<?php echo h((string)$ap['nombre_personaje']); ?>" class="input-taberna" required maxlength="100">
                                </td>
                                <td>
                                    <input type="checkbox" form="<?php echo h($formIdAp); ?>" name="activo" value="1" <?php echo (int)$ap['activo'] === 1 ? 'checked' : ''; ?>>
                                </td>
                                <td>
                                    <div class="acciones-admin-celda">
                                        <button type="submit" form="<?php echo h($formIdAp); ?>" class="btn btn-exito btn-pequeno">Guardar</button>
                                        <form method="POST" action="admin.php" class="form-confirmar-borrado" data-confirm-msg="¿Eliminar el personaje <?php echo h((string)$ap['nombre_personaje']); ?>?">
                                            <input type="hidden" name="entidad" value="asimetrico_personaje">
                                            <input type="hidden" name="operacion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo (int)$ap['id_personaje']; ?>">
                                            <input type="hidden" name="codigo_juego" value="<?php echo h($codigoAsimetricoAdminSeleccionado); ?>">
                                            <button type="submit" class="btn btn-peligro btn-pequeno">Borrar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p style="font-size:0.9em; margin-top:8px;">
                Los personajes usados en partidas no se pueden borrar; desactívalos para ocultarlos del formulario sin perder el historial.
            </p>

        <?php endif; ?>
    </section>

    <?php endif; ?>
</main>

<script src="app.js?v=<?php echo @filemtime(__DIR__ . '/app.js') ?: time(); ?>"></script>
</body>
</html>