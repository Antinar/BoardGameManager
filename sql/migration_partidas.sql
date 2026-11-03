-- =========================================================================
-- MIGRACIÓN: enriquecimiento de partidas (Victoria)
-- Motor: InnoDB | Codificación: utf8mb4
-- Portable e idempotente: MariaDB (InfinityFree) y MySQL 8
-- Ejecutable varias veces sobre una base con datos sin errores.
-- =========================================================================

SET NAMES utf8mb4;

SET @taberna_db = DATABASE();

-- -------------------------------------------------------------------------
-- 1. Columna duracion_minutos en Victoria (solo si no existe)
-- -------------------------------------------------------------------------
SET @taberna_sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `Victoria` ADD COLUMN `duracion_minutos` INT NULL DEFAULT NULL AFTER `fecha_victoria`',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @taberna_db
    AND TABLE_NAME = 'Victoria'
    AND COLUMN_NAME = 'duracion_minutos'
);
PREPARE taberna_stmt FROM @taberna_sql;
EXECUTE taberna_stmt;
DEALLOCATE PREPARE taberna_stmt;

-- -------------------------------------------------------------------------
-- 2. Columna puntuacion en Victoria (solo si no existe)
-- -------------------------------------------------------------------------
SET @taberna_sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `Victoria` ADD COLUMN `puntuacion` VARCHAR(100) NULL DEFAULT NULL AFTER `duracion_minutos`',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @taberna_db
    AND TABLE_NAME = 'Victoria'
    AND COLUMN_NAME = 'puntuacion'
);
PREPARE taberna_stmt FROM @taberna_sql;
EXECUTE taberna_stmt;
DEALLOCATE PREPARE taberna_stmt;

-- -------------------------------------------------------------------------
-- 3. Tabla Victoria_Participante (participantes de cada partida)
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
-- 4. Backfill: el ganador histórico queda como participante mínimo
--    (INSERT IGNORE lo hace idempotente)
-- -------------------------------------------------------------------------
INSERT IGNORE INTO `Victoria_Participante` (`id_victoria`, `username`)
SELECT `id_victoria`, `username` FROM `Victoria`;
