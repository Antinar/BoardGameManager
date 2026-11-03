-- =========================================================================
-- SCRIPT DE BASE DE DATOS: LA TABERNA DEL DADO DE HIERRO
-- Motor: InnoDB | Codificación: utf8mb4
-- Compatible con MySQL / MariaDB en InfinityFree (phpMyAdmin)
-- =========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------------
-- 1. Tabla Persona
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS `Participante_Personaje_Asimetrico`;
DROP TABLE IF EXISTS `Participante_Asimetrico`;
DROP TABLE IF EXISTS `Partida_Asimetrica`;
DROP TABLE IF EXISTS `Personaje_Asimetrico`;
DROP TABLE IF EXISTS `Juego_Asimetrico`;
DROP TABLE IF EXISTS `Participante_Root`;
DROP TABLE IF EXISTS `Partida_Root`;
DROP TABLE IF EXISTS `Root_Mazo`;
DROP TABLE IF EXISTS `Root_Mapa`;
DROP TABLE IF EXISTS `Root_Faccion`;
DROP TABLE IF EXISTS `Resenya`;
DROP TABLE IF EXISTS `Juego_Propietario`;
DROP TABLE IF EXISTS `Voto`;
DROP TABLE IF EXISTS `Votacion`;
DROP TABLE IF EXISTS `Victoria_Participante`;
DROP TABLE IF EXISTS `Victoria`;
DROP TABLE IF EXISTS `Persona_Juego`;
DROP TABLE IF EXISTS `Juego`;
DROP TABLE IF EXISTS `Persona`;

CREATE TABLE `Persona` (
  `username` VARCHAR(50) NOT NULL,
  `NombrePersona` VARCHAR(120) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `contrasenya` VARCHAR(255) NOT NULL,
  `rol` ENUM('jugador', 'admin') NOT NULL DEFAULT 'jugador',
  `inscrito_leaderboard` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 2. Tabla Juego
-- -------------------------------------------------------------------------
CREATE TABLE `Juego` (
  `NombreJuego` VARCHAR(120) NOT NULL,
  `Url` VARCHAR(255) NOT NULL,
  `lo_tenemos` TINYINT(1) NOT NULL DEFAULT 0,
  `min_jugadores` INT NOT NULL DEFAULT 1,
  `max_jugadores` INT NOT NULL DEFAULT 4,
  `descripcion` TEXT DEFAULT NULL,
  `reglas` TEXT DEFAULT NULL,
  PRIMARY KEY (`NombreJuego`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 2.B. Tabla Juego_Propietario (Personas que tienen el juego en la taberna)
-- -------------------------------------------------------------------------
CREATE TABLE `Juego_Propietario` (
  `username` VARCHAR(50) NOT NULL,
  `NombreJuego` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`username`, `NombreJuego`),
  CONSTRAINT `fk_prop_persona`
    FOREIGN KEY (`username`) REFERENCES `Persona` (`username`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_prop_juego`
    FOREIGN KEY (`NombreJuego`) REFERENCES `Juego` (`NombreJuego`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 3. Tabla Persona_Juego (Relación N:N de interesados en juegos que NO tenemos)
-- -------------------------------------------------------------------------
CREATE TABLE `Persona_Juego` (
  `username` VARCHAR(50) NOT NULL,
  `NombreJuego` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`username`, `NombreJuego`),
  CONSTRAINT `fk_pj_persona`
    FOREIGN KEY (`username`) REFERENCES `Persona` (`username`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_pj_juego`
    FOREIGN KEY (`NombreJuego`) REFERENCES `Juego` (`NombreJuego`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 4. Tabla Victoria (Historial de partidas ganadas para el Leaderboard)
-- -------------------------------------------------------------------------
CREATE TABLE `Victoria` (
  `id_victoria` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `NombreJuego` VARCHAR(120) NOT NULL,
  `fecha_victoria` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `duracion_minutos` INT NULL DEFAULT NULL,
  `puntuacion` VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id_victoria`),
  CONSTRAINT `fk_victoria_persona`
    FOREIGN KEY (`username`) REFERENCES `Persona` (`username`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_victoria_juego`
    FOREIGN KEY (`NombreJuego`) REFERENCES `Juego` (`NombreJuego`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 4.B. Tabla Victoria_Participante (Participantes de cada partida)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Victoria_Participante` (
  `id_victoria` INT NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id_victoria`, `username`),
  CONSTRAINT `fk_vp_victoria`
    FOREIGN KEY (`id_victoria`) REFERENCES `Victoria` (`id_victoria`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vp_persona`
    FOREIGN KEY (`username`) REFERENCES `Persona` (`username`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 5. Tabla Votacion (Cabecera de votaciones)
-- -------------------------------------------------------------------------
CREATE TABLE `Votacion` (
  `id_votacion` INT NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(150) NOT NULL,
  `creador_username` VARCHAR(50) NOT NULL,
  `fecha_limite` DATE NOT NULL,
  `tipo_voto` ENUM('unico', 'multiple') NOT NULL DEFAULT 'unico',
  PRIMARY KEY (`id_votacion`),
  CONSTRAINT `fk_votacion_creador`
    FOREIGN KEY (`creador_username`) REFERENCES `Persona` (`username`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 6. Tabla Voto (Votos emitidos en cada votación)
-- -------------------------------------------------------------------------
CREATE TABLE `Voto` (
  `id_voto` INT NOT NULL AUTO_INCREMENT,
  `id_votacion` INT NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `NombreJuego` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id_voto`),
  UNIQUE KEY `voto_unico_por_juego` (`id_votacion`, `username`, `NombreJuego`),
  CONSTRAINT `fk_voto_votacion`
    FOREIGN KEY (`id_votacion`) REFERENCES `Votacion` (`id_votacion`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_voto_persona`
    FOREIGN KEY (`username`) REFERENCES `Persona` (`username`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_voto_juego`
    FOREIGN KEY (`NombreJuego`) REFERENCES `Juego` (`NombreJuego`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 6.B. Tablas del módulo Root (catálogos y partidas)
-- -------------------------------------------------------------------------
CREATE TABLE `Root_Faccion` (
  `id_faccion` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL UNIQUE,
  `activa` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_faccion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Root_Mapa` (
  `id_mapa` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL UNIQUE,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_mapa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Root_Mazo` (
  `id_mazo` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL UNIQUE,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_mazo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Partida_Root` (
  `id_partida_root` INT NOT NULL AUTO_INCREMENT,
  `fecha_partida` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_mapa` INT NOT NULL,
  `id_mazo` INT NOT NULL,
  `condicion_victoria` ENUM('30_puntos', 'dominancia') NOT NULL DEFAULT '30_puntos',
  `con_secuaces` TINYINT(1) NOT NULL DEFAULT 0,
  `duracion_minutos` INT NULL DEFAULT NULL,
  `notas` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id_partida_root`),
  CONSTRAINT `fk_proot_mapa` FOREIGN KEY (`id_mapa`) REFERENCES `Root_Mapa` (`id_mapa`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_proot_mazo` FOREIGN KEY (`id_mazo`) REFERENCES `Root_Mazo` (`id_mazo`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Participante_Root` (
  `id_partida_root` INT NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `id_faccion` INT NOT NULL,
  `ha_ganado` TINYINT(1) NOT NULL DEFAULT 0,
  `puntos` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id_partida_root`, `username`),
  CONSTRAINT `fk_partroot_partida` FOREIGN KEY (`id_partida_root`) REFERENCES `Partida_Root` (`id_partida_root`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_partroot_persona` FOREIGN KEY (`username`) REFERENCES `Persona` (`username`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_partroot_faccion` FOREIGN KEY (`id_faccion`) REFERENCES `Root_Faccion` (`id_faccion`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogos iniciales del módulo Root (ampliables desde la aplicación)
INSERT IGNORE INTO `Root_Faccion` (`nombre`) VALUES
('Marquesado'),
('Nido de Águilas'),
('Alianza del Bosque'),
('Vagabundo (1)'),
('Vagabundo (2)'),
('Culto de los Lagartos'),
('Compañía del Río'),
('Ducado Subterráneo'),
('Conspiración Córvida'),
('Señor de los Cientos'),
('Guardianes de Hierro');

INSERT IGNORE INTO `Root_Mapa` (`nombre`) VALUES
('Otoño'),
('Invierno'),
('Lago'),
('Montaña');

INSERT IGNORE INTO `Root_Mazo` (`nombre`) VALUES
('Estándar (Base)'),
('Exiliados y Partisanos (Militantes)');

-- -------------------------------------------------------------------------
-- 6.C. Tablas del hub de juegos asimétricos (catálogos y partidas)
-- -------------------------------------------------------------------------
CREATE TABLE `Juego_Asimetrico` (
  `codigo_juego` VARCHAR(40) NOT NULL,
  `nombre_visible` VARCHAR(100) NOT NULL,
  `num_personajes_por_jugador` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`codigo_juego`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Personaje_Asimetrico` (
  `id_personaje` INT NOT NULL AUTO_INCREMENT,
  `codigo_juego` VARCHAR(40) NOT NULL,
  `nombre_personaje` VARCHAR(100) NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_personaje`),
  UNIQUE KEY `uk_pers_asimetrico_juego_nombre` (`codigo_juego`, `nombre_personaje`),
  CONSTRAINT `fk_pers_asimetrico_juego` FOREIGN KEY (`codigo_juego`)
    REFERENCES `Juego_Asimetrico` (`codigo_juego`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Partida_Asimetrica` (
  `id_partida_asimetrica` INT NOT NULL AUTO_INCREMENT,
  `codigo_juego` VARCHAR(40) NOT NULL,
  `fecha_partida` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `duracion_minutos` INT NULL DEFAULT NULL,
  `notas` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id_partida_asimetrica`),
  CONSTRAINT `fk_part_asimetrico_juego` FOREIGN KEY (`codigo_juego`)
    REFERENCES `Juego_Asimetrico` (`codigo_juego`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Participante_Asimetrico` (
  `id_participante_asimetrico` INT NOT NULL AUTO_INCREMENT,
  `id_partida_asimetrica` INT NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `ha_ganado` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_participante_asimetrico`),
  UNIQUE KEY `uk_partida_usuario` (`id_partida_asimetrica`, `username`),
  CONSTRAINT `fk_partic_asimetrico_cabecera` FOREIGN KEY (`id_partida_asimetrica`)
    REFERENCES `Partida_Asimetrica` (`id_partida_asimetrica`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_partic_asimetrico_persona` FOREIGN KEY (`username`)
    REFERENCES `Persona` (`username`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Participante_Personaje_Asimetrico` (
  `id_participante_asimetrico` INT NOT NULL,
  `id_personaje` INT NOT NULL,
  PRIMARY KEY (`id_participante_asimetrico`, `id_personaje`),
  CONSTRAINT `fk_ppa_participante` FOREIGN KEY (`id_participante_asimetrico`)
    REFERENCES `Participante_Asimetrico` (`id_participante_asimetrico`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_ppa_personaje` FOREIGN KEY (`id_personaje`)
    REFERENCES `Personaje_Asimetrico` (`id_personaje`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogos iniciales del hub de juegos asimétricos (ampliables desde la aplicación)
INSERT IGNORE INTO `Juego_Asimetrico` (`codigo_juego`, `nombre_visible`, `num_personajes_por_jugador`) VALUES
('villainous_disney', 'Villainous: Disney', 1),
('villainous_marvel', 'Villainous: Marvel', 1),
('villainous_star_wars', 'Villainous: Star Wars', 1),
('tag_team', 'Tag Team (Scorpion Masqué)', 2);

INSERT IGNORE INTO `Personaje_Asimetrico` (`codigo_juego`, `nombre_personaje`) VALUES
-- Villainous: Disney (22)
('villainous_disney', 'Maléfica'),
('villainous_disney', 'Úrsula'),
('villainous_disney', 'Jafar'),
('villainous_disney', 'Príncipe Juan'),
('villainous_disney', 'Reina de Corazones'),
('villainous_disney', 'Capitán Garfio'),
('villainous_disney', 'Hades'),
('villainous_disney', 'Dr. Facilier'),
('villainous_disney', 'Reina Malvada'),
('villainous_disney', 'Scar'),
('villainous_disney', 'Yzma'),
('villainous_disney', 'Ratigan'),
('villainous_disney', 'Cruella de Vil'),
('villainous_disney', 'Madre Gothel'),
('villainous_disney', 'Pete'),
('villainous_disney', 'Lady Tremaine'),
('villainous_disney', 'El Rey del Mal'),
('villainous_disney', 'Gastón'),
('villainous_disney', 'Síndrome'),
('villainous_disney', 'Madam Mim'),
('villainous_disney', 'Lotso'),
('villainous_disney', 'Oogie Boogie'),
-- Villainous: Marvel (12)
('villainous_marvel', 'Thanos'),
('villainous_marvel', 'Hela'),
('villainous_marvel', 'Killmonger'),
('villainous_marvel', 'Ultrón'),
('villainous_marvel', 'Taskmaster'),
('villainous_marvel', 'Loki'),
('villainous_marvel', 'M.O.D.O.K.'),
('villainous_marvel', 'Madame Máscara'),
('villainous_marvel', 'Venom'),
('villainous_marvel', 'Doctor Octopus'),
('villainous_marvel', 'Titania'),
('villainous_marvel', 'Kang el Conquistador'),
-- Villainous: Star Wars (10)
('villainous_star_wars', 'Darth Vader'),
('villainous_star_wars', 'Kylo Ren'),
('villainous_star_wars', 'General Grievous'),
('villainous_star_wars', 'Moff Gideon'),
('villainous_star_wars', 'Asajj Ventress'),
('villainous_star_wars', 'Boba Fett'),
('villainous_star_wars', 'Cad Bane'),
('villainous_star_wars', 'Séptima Hermana'),
('villainous_star_wars', 'Darth Maul'),
('villainous_star_wars', 'Capitana Phasma'),
-- Tag Team (6)
('tag_team', 'Luchador Alpha'),
('tag_team', 'Luchador Beta'),
('tag_team', 'Luchador Gamma'),
('tag_team', 'Luchador Delta'),
('tag_team', 'Luchador Épsilon'),
('tag_team', 'Luchador Zeta');

SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------------------
-- 7. Tabla Resenya (Valoraciones 0-10 y Opiniones Previas con NULL)
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS `Resenya`;

CREATE TABLE `Resenya` (
    `id_resenya` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `NombreJuego` VARCHAR(120) NOT NULL,
    `ha_jugado` TINYINT(1) NOT NULL DEFAULT 0,
    `calificacion` TINYINT UNSIGNED NULL DEFAULT NULL,
    `comentario` TEXT NULL DEFAULT NULL,
    `fecha_resenya` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_resenya`),
    UNIQUE KEY `resenya_unica_usuario_juego` (`username`, `NombreJuego`),
    CONSTRAINT `fk_resenya_persona`
        FOREIGN KEY (`username`) REFERENCES `Persona` (`username`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_resenya_juego`
        FOREIGN KEY (`NombreJuego`) REFERENCES `Juego` (`NombreJuego`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- DATOS INICIALES DE PRUEBA
-- -------------------------------------------------------------------------
INSERT INTO `Persona` (`username`, `NombrePersona`, `contrasenya`, `rol`, `inscrito_leaderboard`) VALUES
('admin', 'Tabernero Mayor', 'admin', 'admin', 1),
('rodrigo', 'Rodrigo de Vivar', '1234', 'jugador', 1),
('elena', 'Elena de Montiel', '1234', 'jugador', 1);
