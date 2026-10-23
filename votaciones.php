<?php
require_once 'conexion.php';
requerirLogin($pdo);

$mensajeExito = '';
$mensajeError = '';
$usuarioActual = $_SESSION['username'];

// Validación CSRF común a toda petición POST: se comprueba antes de leer la acción o tocar la BD.
$csrfOk = true;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = verificarTokenCsrf();
    if (!$csrfOk) {
        $mensajeError = MENSAJE_CSRF_INVALIDO;
    }
}

if ($csrfOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    // 1. Crear una nueva votación
    if ($accion === 'crear_votacion') {
        $titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
        $fechaLimite = isset($_POST['fecha_limite']) ? trim($_POST['fecha_limite']) : '';
        $tipoVoto = (isset($_POST['tipo_voto']) && $_POST['tipo_voto'] === 'multiple') ? 'multiple' : 'unico';

        $hoy = date('Y-m-d');
        if ($titulo === '' || $fechaLimite === '') {
            $mensajeError = 'Debes indicar un título y un día límite para abrir la votación.';
        } elseif ($fechaLimite < $hoy) {
            $mensajeError = 'El día límite no puede ser anterior a la fecha actual.';
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO Votacion (titulo, creador_username, fecha_limite, tipo_voto) VALUES (?, ?, ?, ?)");
            $stmtIns->execute([$titulo, $usuarioActual, $fechaLimite, $tipoVoto]);

            $nombreCreador = isset($_SESSION['NombrePersona']) ? $_SESSION['NombrePersona'] : $usuarioActual;
            $correosEnviados = notificarNuevaVotacionPorEmail($pdo, $titulo, $fechaLimite, $tipoVoto, $nombreCreador);

            $mensajeExito = "La votación «{$titulo}» ha quedado abierta hasta el día {$fechaLimite}.";
            if ($correosEnviados > 0) {
                $mensajeExito .= " Se ha enviado aviso por correo a {$correosEnviados} miembro(s).";
            }
        }
    }

    // 2. Emitir voto(s) en una votación activa
    elseif ($accion === 'emitir_voto') {
        $idVotacion = isset($_POST['id_votacion']) ? (int)$_POST['id_votacion'] : 0;

        $stmtCheckVot = $pdo->prepare("SELECT id_votacion, titulo, tipo_voto, fecha_limite FROM Votacion WHERE id_votacion = ? AND fecha_limite >= CURDATE()");
        $stmtCheckVot->execute([$idVotacion]);
        $votacionActiva = $stmtCheckVot->fetch();

        if (!$votacionActiva) {
            $mensajeError = 'La votación seleccionada no existe o ya ha superado su día límite.';
        } else {
            $juegosElegidos = [];
            if ($votacionActiva['tipo_voto'] === 'unico') {
                if (isset($_POST['juego_unico']) && trim($_POST['juego_unico']) !== '') {
                    $juegosElegidos[] = trim($_POST['juego_unico']);
                }
            } else {
                if (isset($_POST['juegos_multiples']) && is_array($_POST['juegos_multiples'])) {
                    foreach ($_POST['juegos_multiples'] as $jSel) {
                        $jSelLimpio = trim((string)$jSel);
                        if ($jSelLimpio !== '') {
                            $juegosElegidos[] = $jSelLimpio;
                        }
                    }
                }
            }

            // Validar que todos los juegos votados existan y tengan lo_tenemos = 1
            $juegosValidos = [];
            if (count($juegosElegidos) > 0) {
                $placeholders = implode(',', array_fill(0, count($juegosElegidos), '?'));
                $stmtVal = $pdo->prepare("SELECT NombreJuego FROM Juego WHERE lo_tenemos = 1 AND NombreJuego IN ({$placeholders})");
                $stmtVal->execute($juegosElegidos);
                $juegosValidos = $stmtVal->fetchAll(PDO::FETCH_COLUMN);
            }

            // Actualizar los votos del usuario en esta votación dentro de una transacción
            $pdo->beginTransaction();
            try {
                $stmtDel = $pdo->prepare("DELETE FROM Voto WHERE id_votacion = ? AND username = ?");
                $stmtDel->execute([$idVotacion, $usuarioActual]);

                if (count($juegosValidos) > 0) {
                    $stmtInsVoto = $pdo->prepare("INSERT INTO Voto (id_votacion, username, NombreJuego) VALUES (?, ?, ?)");
                    foreach ($juegosValidos as $nombreJuegoValido) {
                        $stmtInsVoto->execute([$idVotacion, $usuarioActual, $nombreJuegoValido]);
                    }
                    $mensajeExito = "Tus votos han sido registrados en la votación «{$votacionActiva['titulo']}».";
                } else {
                    $mensajeExito = "Has retirado tus votos de la votación «{$votacionActiva['titulo']}».";
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $mensajeError = "No se pudo registrar el voto: " . $e->getMessage();
            }
        }
    }

    // 3. Editar título o fecha límite de una votación activa (solo el creador)
    elseif ($accion === 'editar_votacion') {
        $idVotacion = isset($_POST['id_votacion']) ? (int)$_POST['id_votacion'] : 0;
        $nuevoTitulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
        $nuevaFecha = isset($_POST['fecha_limite']) ? trim($_POST['fecha_limite']) : '';
        $hoy = date('Y-m-d');

        $stmtCheckCreador = $pdo->prepare("SELECT titulo FROM Votacion WHERE id_votacion = ? AND creador_username = ? AND fecha_limite >= CURDATE()");
        $stmtCheckCreador->execute([$idVotacion, $usuarioActual]);
        $votPropia = $stmtCheckCreador->fetch();

        if (!$votPropia) {
            $mensajeError = 'No tienes permiso para editar esta votación o ya se encuentra cerrada.';
        } elseif ($nuevoTitulo === '' || $nuevaFecha === '') {
            $mensajeError = 'El título y el día límite son obligatorios.';
        } elseif ($nuevaFecha < $hoy) {
            $mensajeError = 'El nuevo día límite no puede ser anterior a hoy (usa el botón Cerrar Votación si deseas finalizarla ahora).';
        } else {
            $stmtUpdVot = $pdo->prepare("UPDATE Votacion SET titulo = ?, fecha_limite = ? WHERE id_votacion = ? AND creador_username = ?");
            $stmtUpdVot->execute([$nuevoTitulo, $nuevaFecha, $idVotacion, $usuarioActual]);
            $mensajeExito = "La votación «{$nuevoTitulo}» ha sido actualizada correctamente.";
        }
    }

    // 4. Cerrar votación anticipadamente (solo el creador): pasa fecha_limite a ayer para que quede visible 1 día como cerrada
    elseif ($accion === 'cerrar_votacion') {
        $idVotacion = isset($_POST['id_votacion']) ? (int)$_POST['id_votacion'] : 0;

        $stmtCheckCreador = $pdo->prepare("SELECT titulo FROM Votacion WHERE id_votacion = ? AND creador_username = ? AND fecha_limite >= CURDATE()");
        $stmtCheckCreador->execute([$idVotacion, $usuarioActual]);
        $votPropia = $stmtCheckCreador->fetch();

        if (!$votPropia) {
            $mensajeError = 'No tienes permiso para cerrar esta votación o ya está cerrada.';
        } else {
            $stmtCerrar = $pdo->prepare("UPDATE Votacion SET fecha_limite = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id_votacion = ? AND creador_username = ?");
            $stmtCerrar->execute([$idVotacion, $usuarioActual]);
            $mensajeExito = "Has cerrado la votación «{$votPropia['titulo']}». Sus resultados se mostrarán durante 1 día.";
        }
    }
}

// Obtener ÚNICAMENTE los juegos que YA tenemos (lo_tenemos = 1)
$stmtJuegos = $pdo->query("SELECT NombreJuego, Url FROM Juego WHERE lo_tenemos = 1 ORDER BY NombreJuego ASC");
$juegosQueTenemos = $stmtJuegos->fetchAll();

// Obtener las votaciones activas (fecha_limite >= CURDATE()) y las cerradas hace como máximo 1 día (fecha_limite >= CURDATE() - 1 día)
$stmtVotaciones = $pdo->query("
    SELECT v.id_votacion, v.titulo, v.creador_username, p.NombrePersona AS nombre_creador, v.fecha_limite, v.tipo_voto,
           CASE WHEN v.fecha_limite < CURDATE() THEN 1 ELSE 0 END AS esta_cerrada
    FROM Votacion v
    INNER JOIN Persona p ON v.creador_username = p.username
    WHERE v.fecha_limite >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    ORDER BY esta_cerrada ASC, v.fecha_limite ASC, v.id_votacion DESC
");
$votacionesVisibles = $stmtVotaciones->fetchAll();

// Obtener todos los votos de las votaciones visibles (activas y recién cerradas) junto con el nombre del votante
$stmtVotos = $pdo->query("
    SELECT vt.id_votacion, vt.NombreJuego, vt.username, p.NombrePersona
    FROM Voto vt
    INNER JOIN Votacion v ON vt.id_votacion = v.id_votacion
    INNER JOIN Persona p ON vt.username = p.username
    WHERE v.fecha_limite >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    ORDER BY p.NombrePersona ASC
");
$conteoVotos = [];          // $conteoVotos[$id_votacion][$NombreJuego] = total
$votosDeUsuario = [];       // $votosDeUsuario[$id_votacion][$NombreJuego] = true
$votantesPorVotacion = [];  // $votantesPorVotacion[$id_votacion][$username] = NombrePersona

foreach ($stmtVotos->fetchAll() as $filaVoto) {
    $idV = (int)$filaVoto['id_votacion'];
    $nomJ = $filaVoto['NombreJuego'];
    $userV = $filaVoto['username'];

    if (!isset($conteoVotos[$idV][$nomJ])) {
        $conteoVotos[$idV][$nomJ] = 0;
    }
    $conteoVotos[$idV][$nomJ]++;

    $votantesPorVotacion[$idV][$userV] = $filaVoto['NombrePersona'];

    if ($userV === $usuarioActual) {
        $votosDeUsuario[$idV][$nomJ] = true;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consejo de Votos - La Taberna de las Gatitas Jugonas</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php renderizarNavegacion('votaciones'); ?>

<main class="contenedor-principal">

    <?php if ($mensajeError !== ''): ?>
        <div class="alerta alerta-error"><?php echo h($mensajeError); ?></div>
    <?php endif; ?>

    <?php if ($mensajeExito !== ''): ?>
        <div class="alerta alerta-exito"><?php echo h($mensajeExito); ?></div>
    <?php endif; ?>

    <!-- Formulario para abrir una nueva votación -->
    <section class="pergamino">
        <h2 class="seccion-titulo">Convocar Nueva Votación de Juegos</h2>
        <p>
            Desde este panel puedes crear una nueva votación de juegos para ver que se elige en un dia de juegos de mesa. La fecha limite elimina de forma automatica la votación cuando pasa el plazo. También es importante ver si se podrá votar a 1 o más de un juego.
        </p>
        <form method="POST" action="votaciones.php" class="grid-tres-columnas" style="align-items:end;">
            <input type="hidden" name="accion" value="crear_votacion">
            <?php echo campoCsrf(); ?>

            <div class="grupo-form" style="margin-bottom:0;">
                <label for="titulo_votacion">Título o Motivo de la Votación:</label>
                <input type="text" id="titulo_votacion" name="titulo" class="input-taberna" required maxlength="150" placeholder="Ej. Partida del Viernes Noche">
            </div>

            <div class="grupo-form" style="margin-bottom:0;">
                <label for="fecha_limite">Día Límite:</label>
                <input type="date" id="fecha_limite" name="fecha_limite" class="input-taberna" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
            </div>

            <div class="grupo-form" style="margin-bottom:0;">
                <label for="tipo_voto">Modalidad de Voto:</label>
                <select id="tipo_voto" name="tipo_voto" class="select-taberna" required>
                    <option value="unico">Un solo juego por persona (Voto Único)</option>
                    <option value="multiple">Varios juegos por persona (Voto Múltiple)</option>
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-primario" style="width:100%;">Publicar Votación</button>
            </div>
        </form>
    </section>

    <!-- Listado de Votaciones Activas y Recién Cerradas -->
    <section class="pergamino">
        <h2 class="seccion-titulo">Votaciones en la Taberna</h2>
        <p>
            Haz clic en la cabecera de cualquier votación para desplegarla. Las votaciones cerradas permanecen visibles durante 1 día para consultar los juegos votados.
        </p>

        <?php if (count($juegosQueTenemos) === 0): ?>
            <div class="alerta alerta-error">
                No hay ningún juego marcado como «Ya lo tenemos». Registra o cambia el estado de algún juego en la página de Juegos para poder votarlo.
            </div>
        <?php elseif (count($votacionesVisibles) === 0): ?>
            <p style="font-style:italic;">No hay votaciones activas ni recién cerradas en este momento. ¡Convoca una arriba!</p>
        <?php else: ?>
            <?php foreach ($votacionesVisibles as $vot): ?>
                <?php
                $idV = (int)$vot['id_votacion'];
                $esMultiple = ($vot['tipo_voto'] === 'multiple');
                $estaCerrada = ((int)$vot['esta_cerrada'] === 1);
                $soyCreador = ($vot['creador_username'] === $usuarioActual);

                // Calcular V_i sobre todos los juegos disponibles para obtener V_min y V_max
                $votosPorJuego = [];
                foreach ($juegosQueTenemos as $jDisp) {
                    $nJ = $jDisp['NombreJuego'];
                    $votosPorJuego[$nJ] = isset($conteoVotos[$idV][$nJ]) ? (int)$conteoVotos[$idV][$nJ] : 0;
                }
                $vMin = count($votosPorJuego) > 0 ? min($votosPorJuego) : 0;
                $vMax = count($votosPorJuego) > 0 ? max($votosPorJuego) : 0;

                // En votación cerrada: filtrar solo los juegos que recibieron al menos 1 voto
                $juegosMostrar = [];
                foreach ($juegosQueTenemos as $jDisp) {
                    $nJ = $jDisp['NombreJuego'];
                    if ($estaCerrada && $votosPorJuego[$nJ] < 1) {
                        continue;
                    }
                    $juegosMostrar[] = $jDisp;
                }

                // Ordenar siempre por número de votos recibido (mayor a menor) y, en caso de empate, por orden alfabético
                usort($juegosMostrar, function ($a, $b) use ($votosPorJuego) {
                    $vA = $votosPorJuego[$a['NombreJuego']];
                    $vB = $votosPorJuego[$b['NombreJuego']];
                    if ($vB !== $vA) {
                        return $vB - $vA;
                    }
                    return strcasecmp($a['NombreJuego'], $b['NombreJuego']);
                });
                ?>
                <div class="votacion-bloque contraida <?php echo $estaCerrada ? 'votacion-cerrada' : ''; ?>">
                    <div class="votacion-cabecera votacion-plegable" data-toggle-votacion>
                        <div>
                            <h3 style="font-size:1.25rem; color:#23170f;">
                                <span class="indicador-plegado">&#9654;</span>
                                <?php echo h($vot['titulo']); ?>
                            </h3>
                            <p style="font-size:0.88rem;">
                                Por <strong><?php echo h($vot['nombre_creador']); ?></strong>
                            </p>
                        </div>
                        <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                            <span class="etiqueta etiqueta-bronce">
                                <?php echo $esMultiple ? 'Voto Múltiple' : 'Voto Único'; ?>
                            </span>
                            <?php if ($estaCerrada): ?>
                                <span class="etiqueta etiqueta-cerrada">
                                    Votación Cerrada
                                </span>
                            <?php else: ?>
                                <span class="etiqueta etiqueta-pendiente">
                                    Hasta: <?php echo h($vot['fecha_limite']); ?>
                                </span>
                                <?php if ($soyCreador): ?>
                                    <button type="button"
                                            class="btn btn-bronce btn-pequeno"
                                            data-toggle-editar-votacion="panel_editar_vot_<?php echo $idV; ?>"
                                            style="padding:3px 10px; font-size:0.8rem;">
                                        Editar
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="votacion-cuerpo">
                        <?php
                        $listaParticipantes = isset($votantesPorVotacion[$idV]) ? $votantesPorVotacion[$idV] : [];
                        ?>
                        <div class="pergamino-secundario" style="margin-bottom:12px; padding:10px 12px;">
                            <strong>Han votado en esta convocatoria (<?php echo count($listaParticipantes); ?>):</strong>
                            <?php if (count($listaParticipantes) === 0): ?>
                                <em style="color:#5a4634;">Todavía no ha votado nadie.</em>
                            <?php else: ?>
                                <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;">
                                    <?php foreach ($listaParticipantes as $uPart => $nomPart): ?>
                                        <span class="etiqueta etiqueta-disponible">
                                            <?php echo h($nomPart); ?> (<?php echo h($uPart); ?>)
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$estaCerrada && $soyCreador): ?>
                            <!-- Panel de edición / cierre exclusivo para el creador de la votación -->
                            <div id="panel_editar_vot_<?php echo $idV; ?>" class="pergamino-secundario panel-editar-votacion panel-oculto">
                                <h4 style="margin-bottom:8px; color:#23170f;">Editar Votación (El tipo de voto no se puede modificar)</h4>
                                <form method="POST" action="votaciones.php" class="grid-tres-columnas" style="align-items:end;">
                                    <input type="hidden" name="accion" value="editar_votacion">
                                    <?php echo campoCsrf(); ?>
                                    <input type="hidden" name="id_votacion" value="<?php echo $idV; ?>">

                                    <div class="grupo-form" style="margin-bottom:0;">
                                        <label>Título de la Votación:</label>
                                        <input type="text" name="titulo" class="input-taberna" required maxlength="150" value="<?php echo h($vot['titulo']); ?>">
                                    </div>

                                    <div class="grupo-form" style="margin-bottom:0;">
                                        <label>Nuevo Día Límite:</label>
                                        <input type="date" name="fecha_limite" class="input-taberna" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo h($vot['fecha_limite']); ?>">
                                    </div>

                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <button type="submit" class="btn btn-exito btn-pequeno" style="flex:1;">Guardar Cambios</button>
                                    </div>
                                </form>

                                <form method="POST" action="votaciones.php" class="form-confirmar-borrado" data-confirm-msg="¿Cerrar ya esta votación? Ya no se podrá votar más y solo se verán los resultados durante 1 día." style="margin-top:10px; border-top:1px solid #4a3219; padding-top:8px; display:flex; justify-content:flex-end;">
                                    <input type="hidden" name="accion" value="cerrar_votacion">
                                    <?php echo campoCsrf(); ?>
                                    <input type="hidden" name="id_votacion" value="<?php echo $idV; ?>">
                                    <button type="submit" class="btn btn-peligro btn-pequeno">Cerrar Votación Ahora</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <?php if ($estaCerrada): ?>
                            <!-- Vista de Resultados de Votación Cerrada (Solo juegos con votos >= 1) -->
                            <?php if (count($juegosMostrar) === 0): ?>
                                <p style="font-style:italic; padding:10px 0;">Esta votación se cerró sin recibir votos para ningún juego.</p>
                            <?php else: ?>
                                <div class="barra-buscador-votacion">
                                    <?php renderizarBuscador('Buscar juego en los resultados...', ['target_cards' => 'grid_vot_' . $idV]); ?>
                                </div>

                                <div class="grid-juegos-votacion" id="grid_vot_<?php echo $idV; ?>">
                                    <?php foreach ($juegosMostrar as $jDisp): ?>
                                        <?php
                                        $nomJ = $jDisp['NombreJuego'];
                                        $votosActuales = $votosPorJuego[$nomJ];
                                        $estaResaltado = ($votosActuales >= 1 && $votosActuales > $vMin);
                                        $esMasVotado = ($votosActuales >= 1 && $votosActuales === $vMax);
                                        ?>
                                        <div class="tarjeta-juego-voto <?php echo $estaResaltado ? 'juego-resaltado' : ''; ?>"
                                             data-card-item
                                             data-nombre="<?php echo h($nomJ); ?>">
                                            <div>
                                                <?php if ($esMasVotado): ?>
                                                    <div class="insignia-resaltado" style="margin-bottom:8px;">
                                                        Más votado
                                                    </div>
                                                <?php endif; ?>
                                                <img src="<?php echo h($jDisp['Url']); ?>" alt="<?php echo h($nomJ); ?>">
                                                <h4 style="margin-top:8px; font-size:1.05rem;"><?php echo h($nomJ); ?></h4>
                                            </div>

                                            <div>
                                                <p style="margin:6px 0; font-weight:bold; color:#362417;">
                                                    Votos recibidos: <?php echo $votosActuales; ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <!-- Vista de Votación Activa -->
                            <div class="barra-buscador-votacion">
                                <?php renderizarBuscador('Buscar juego en esta votación...', ['target_cards' => 'grid_vot_' . $idV]); ?>
                            </div>

                            <form method="POST" action="votaciones.php">
                                <input type="hidden" name="accion" value="emitir_voto">
                                <?php echo campoCsrf(); ?>
                                <input type="hidden" name="id_votacion" value="<?php echo $idV; ?>">

                                <div class="grid-juegos-votacion" id="grid_vot_<?php echo $idV; ?>">
                                    <?php foreach ($juegosMostrar as $jDisp): ?>
                                        <?php
                                        $nomJ = $jDisp['NombreJuego'];
                                        $votosActuales = $votosPorJuego[$nomJ];
                                        $votadoPorMi = isset($votosDeUsuario[$idV][$nomJ]);

                                        // Resaltado (marco + fondo brillante) para los que tienen V_i >= 1 y V_i > V_min
                                        $estaResaltado = ($votosActuales >= 1 && $votosActuales > $vMin);
                                        // Etiqueta "Más votado" únicamente para los que empaten en el máximo de votos (V_i == V_max y V_max >= 1)
                                        $esMasVotado = ($votosActuales >= 1 && $votosActuales === $vMax);
                                        ?>
                                        <div class="tarjeta-juego-voto <?php echo $estaResaltado ? 'juego-resaltado' : ''; ?>"
                                             data-card-item
                                             data-nombre="<?php echo h($nomJ); ?>">
                                            <div>
                                                <?php if ($esMasVotado): ?>
                                                    <div class="insignia-resaltado" style="margin-bottom:8px;">
                                                        Más votado
                                                    </div>
                                                <?php endif; ?>
                                                <img src="<?php echo h($jDisp['Url']); ?>" alt="<?php echo h($nomJ); ?>">
                                                <h4 style="margin-top:8px; font-size:1.05rem;"><?php echo h($nomJ); ?></h4>
                                            </div>

                                            <div>
                                                <p style="margin:6px 0; font-weight:bold; color:#362417;">
                                                    Votos recibidos: <?php echo $votosActuales; ?>
                                                </p>
                                                <label class="radio-item" style="background-color:#dfd1a7; padding:6px 8px; border:1px solid #4a3219; font-weight:bold;">
                                                    <?php if ($esMultiple): ?>
                                                        <input type="checkbox" name="juegos_multiples[]" value="<?php echo h($nomJ); ?>" <?php echo $votadoPorMi ? 'checked' : ''; ?>>
                                                        Seleccionar este juego
                                                    <?php else: ?>
                                                        <input type="radio" name="juego_unico" value="<?php echo h($nomJ); ?>" <?php echo $votadoPorMi ? 'checked' : ''; ?>>
                                                        Votar por este juego
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <button type="submit" class="btn btn-exito">Guardar mi Voto en «<?php echo h($vot['titulo']); ?>»</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>

<script src="app.js"></script>
</body>
</html>