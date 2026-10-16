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
DROP TABLE IF EXISTS `Voto`;
DROP TABLE IF EXISTS `Votacion`;
DROP TABLE IF EXISTS `Victoria`;
DROP TABLE IF EXISTS `Persona_Juego`;
DROP TABLE IF EXISTS `Juego`;
DROP TABLE IF EXISTS `Persona`;

CREATE TABLE `Persona` (
  `username` VARCHAR(50) NOT NULL,
  `NombrePersona` VARCHAR(120) NOT NULL,
  `contrasenya` VARCHAR(255) NOT NULL,
  `rol` ENUM('jugador', 'admin') NOT NULL DEFAULT 'jugador',
  `inscrito_leaderboard` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- 2. Tabla Juego
-- -------------------------------------------------------------------------
CREATE TABLE `Juego` (
  `NombreJuego` VARCHAR(120) NOT NULL,
  `Url` VARCHAR(255) NOT NULL,
  `lo_tenemos` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`NombreJuego`)
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
  PRIMARY KEY (`id_victoria`),
  CONSTRAINT `fk_victoria_persona`
    FOREIGN KEY (`username`) REFERENCES `Persona` (`username`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_victoria_juego`
    FOREIGN KEY (`NombreJuego`) REFERENCES `Juego` (`NombreJuego`)
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

SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------------------
-- DATOS INICIALES DE PRUEBA
-- -------------------------------------------------------------------------
INSERT INTO `Persona` (`username`, `NombrePersona`, `contrasenya`, `rol`, `inscrito_leaderboard`) VALUES
('admin', 'Tabernero Mayor', 'admin', 'admin', 1),
('rodrigo', 'Rodrigo de Vivar', '1234', 'jugador', 1),
('elena', 'Elena de Montiel', '1234', 'jugador', 1);