-- =========================================================================
-- MIGRACIÓN: MÓDULO ROOT
-- Motor: InnoDB | Codificación: utf8mb4
-- Compatible con MySQL / MariaDB en InfinityFree (phpMyAdmin)
-- =========================================================================
-- Crea las tablas del módulo Root:
--   * Root_Faccion, Root_Mapa, Root_Mazo (catálogos extensibles)
--   * Partida_Root (cabecera de partida)
--   * Participante_Root (participantes y resultado de cada partida)
--
-- Script idempotente: usa CREATE TABLE IF NOT EXISTS e INSERT IGNORE,
-- por lo que puede ejecutarse varias veces sin errores ni duplicados.
-- No elimina ni modifica datos existentes.
--
-- Ejecutar MANUALMENTE sobre la base de datos antes de desplegar el código
-- del módulo Root.
-- =========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `Root_Faccion` (
  `id_faccion` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL UNIQUE,
  `activa` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_faccion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Root_Mapa` (
  `id_mapa` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL UNIQUE,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_mapa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Root_Mazo` (
  `id_mazo` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL UNIQUE,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_mazo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Partida_Root` (
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

CREATE TABLE IF NOT EXISTS `Participante_Root` (
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

SET FOREIGN_KEY_CHECKS = 1;
