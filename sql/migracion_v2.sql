-- =========================================================================
-- MIGRACIÓN V2: PROPIETARIOS, CAMPOS DE JUEGO (JUGADORES/DESC/REGLAS) Y EMAIL
-- Proyecto: La Taberna de las Gatitas Jugonas (Antinar/BoardGameManager)
-- =========================================================================

SET NAMES utf8mb4;

-- 1. Añadir campo email opcional a Persona
ALTER TABLE `Persona`
    ADD COLUMN `email` VARCHAR(150) DEFAULT NULL AFTER `NombrePersona`;

-- 2. Añadir min_jugadores, max_jugadores, descripcion y reglas a Juego
ALTER TABLE `Juego`
    ADD COLUMN `min_jugadores` INT NOT NULL DEFAULT 1 AFTER `lo_tenemos`,
    ADD COLUMN `max_jugadores` INT NOT NULL DEFAULT 4 AFTER `min_jugadores`,
    ADD COLUMN `descripcion` TEXT DEFAULT NULL AFTER `max_jugadores`,
    ADD COLUMN `reglas` TEXT DEFAULT NULL AFTER `descripcion`;

-- 3. Crear tabla Juego_Propietario (quiénes tienen físicamente cada juego)
CREATE TABLE IF NOT EXISTS `Juego_Propietario` (
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