-- 028: Nomenclador por cliente + tabla de configuraciones del tenant
-- Cada cliente puede tener un "nomenclador" (sufijo de codigo, ej: PIST).
-- Los productos creados/variantes para ese cliente usan SKU = SKU_BASE-NOMENCLADOR.
-- La tabla `configuraciones` guarda claves/valores del tenant para el menu
-- Configuraciones (hoy: usar_nomenclador, luego emails, facturacion, etc).
--
-- MySQL no soporta ADD COLUMN IF NOT EXISTS (MariaDB si), asi que la columna se
-- agrega solo si falta, consultando information_schema + PREPARE. Sirve para
-- instalaciones nuevas (tenant_schema.sql ya trae `nomenclador`) y para las viejas.

SET @db_nomenclador := DATABASE();

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_nomenclador AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'nomenclador');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `clientes` ADD COLUMN `nomenclador` VARCHAR(20) DEFAULT NULL COMMENT ''Sufijo de codigo para variantes de producto del cliente (ej: PIST)'' AFTER `es_distribuidor`',
  'SET @noop_nomenclador := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `configuraciones` (
  `clave` varchar(50) NOT NULL,
  `valor` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configuraciones del tenant (clave/valor) administradas desde el menu Empresa';

INSERT IGNORE INTO configuraciones (clave, valor) VALUES ('usar_nomenclador', '1');

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (28, 'Nomenclador por cliente + tabla de configuraciones del tenant');
