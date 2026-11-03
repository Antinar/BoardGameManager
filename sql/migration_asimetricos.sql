-- =========================================================================
-- MIGRACIÓN: HUB DE JUEGOS ASIMÉTRICOS
-- Motor: InnoDB | Codificación: utf8mb4
-- Compatible con MySQL / MariaDB en InfinityFree (phpMyAdmin)
-- =========================================================================
-- Crea las tablas del hub de juegos asimétricos:
--   * Juego_Asimetrico (catálogo de juegos asimétricos)
--   * Personaje_Asimetrico (personajes de cada juego, ampliables)
--   * Partida_Asimetrica (cabecera de partida)
--   * Participante_Asimetrico (participantes y resultado de cada partida)
--   * Participante_Personaje_Asimetrico (personajes elegidos por participante)
--
-- Incluye la clave única (codigo_juego, nombre_personaje) para que los
-- seeds no se dupliquen al ejecutar la migración más de una vez.
--
-- Script idempotente: usa CREATE TABLE IF NOT EXISTS e INSERT IGNORE,
-- por lo que puede ejecutarse varias veces sin errores ni duplicados.
-- No elimina ni modifica datos existentes.
--
-- Ejecutar MANUALMENTE sobre la base de datos antes de desplegar el código
-- del hub de juegos asimétricos.
-- =========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `Juego_Asimetrico` (
  `codigo_juego` VARCHAR(40) NOT NULL,
  `nombre_visible` VARCHAR(100) NOT NULL,
  `num_personajes_por_jugador` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`codigo_juego`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Personaje_Asimetrico` (
  `id_personaje` INT NOT NULL AUTO_INCREMENT,
  `codigo_juego` VARCHAR(40) NOT NULL,
  `nombre_personaje` VARCHAR(100) NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_personaje`),
  UNIQUE KEY `uk_pers_asimetrico_juego_nombre` (`codigo_juego`, `nombre_personaje`),
  CONSTRAINT `fk_pers_asimetrico_juego` FOREIGN KEY (`codigo_juego`)
    REFERENCES `Juego_Asimetrico` (`codigo_juego`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Partida_Asimetrica` (
  `id_partida_asimetrica` INT NOT NULL AUTO_INCREMENT,
  `codigo_juego` VARCHAR(40) NOT NULL,
  `fecha_partida` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `duracion_minutos` INT NULL DEFAULT NULL,
  `notas` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id_partida_asimetrica`),
  CONSTRAINT `fk_part_asimetrico_juego` FOREIGN KEY (`codigo_juego`)
    REFERENCES `Juego_Asimetrico` (`codigo_juego`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Participante_Asimetrico` (
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

CREATE TABLE IF NOT EXISTS `Participante_Personaje_Asimetrico` (
  `id_participante_asimetrico` INT NOT NULL,
  `id_personaje` INT NOT NULL,
  PRIMARY KEY (`id_participante_asimetrico`, `id_personaje`),
  CONSTRAINT `fk_ppa_participante` FOREIGN KEY (`id_participante_asimetrico`)
    REFERENCES `Participante_Asimetrico` (`id_participante_asimetrico`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_ppa_personaje` FOREIGN KEY (`id_personaje`)
    REFERENCES `Personaje_Asimetrico` (`id_personaje`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `Juego_Asimetrico` (`codigo_juego`, `nombre_visible`, `num_personajes_por_jugador`) VALUES
('villainous_disney', 'Villainous: Disney', 1),
('villainous_marvel', 'Villainous: Marvel', 1),
('villainous_star_wars', 'Villainous: Star Wars', 1),
('tag_team', 'Tag Team (Scorpion Masqué)', 2);

-- Personajes iniciales (catálogo ampliable desde la aplicación)
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
