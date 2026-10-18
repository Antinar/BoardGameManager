-- =========================================================================
-- MIGRACIÓN INCREMENTAL: SECCIÓN LUDOTECA Y RESEÑAS
-- Proyecto: La Taberna de las Gatitas Jugonas (Antinar/BoardGameManager)
-- Motor: InnoDB | Codificación: utf8mb4
-- Seguro para ejecutar en phpMyAdmin sin borrar tablas ni perder datos
-- =========================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `Resenya` (
  `id_resenya` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `NombreJuego` VARCHAR(120) NOT NULL,
  `ha_jugado` TINYINT(1) NOT NULL DEFAULT 0,
  `calificacion` ENUM(
    'No me gustó',
    'Meh',
    'Está Bien',
    'Me gustó bastante',
    '¿Pa cuando otra?'
  ) NULL DEFAULT NULL,
  `comentario` TEXT NULL,
  `fecha_resenya` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_resenya`),
  UNIQUE KEY `uk_persona_juego_resenya` (`username`, `NombreJuego`),
  CONSTRAINT `fk_resenya_persona`
    FOREIGN KEY (`username`) REFERENCES `Persona` (`username`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_resenya_juego`
    FOREIGN KEY (`NombreJuego`) REFERENCES `Juego` (`NombreJuego`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;