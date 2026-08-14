-- =====================================================
-- Migration 025: Agregar columnas empresa a tenants (MASTER DB)
-- Ejecutar en app_master, NO en la BD del tenant
-- =====================================================

USE `app_master`;

-- Solo agregar si no existen (idempotente)
-- cuit
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_master' AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'cuit');
SET @s = IF(@c = 0, 'ALTER TABLE `tenants` ADD COLUMN `cuit` VARCHAR(20) DEFAULT NULL AFTER `schema_version`', 'SELECT "cuit ya existe"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- email
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_master' AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'email');
SET @s = IF(@c = 0, 'ALTER TABLE `tenants` ADD COLUMN `email` VARCHAR(150) DEFAULT NULL AFTER `cuit`', 'SELECT "email ya existe"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- telefono
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_master' AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'telefono');
SET @s = IF(@c = 0, 'ALTER TABLE `tenants` ADD COLUMN `telefono` VARCHAR(50) DEFAULT NULL AFTER `email`', 'SELECT "telefono ya existe"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- direccion
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_master' AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'direccion');
SET @s = IF(@c = 0, 'ALTER TABLE `tenants` ADD COLUMN `direccion` VARCHAR(255) DEFAULT NULL AFTER `telefono`', 'SELECT "direccion ya existe"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- logo
SET @c = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_master' AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'logo');
SET @s = IF(@c = 0, 'ALTER TABLE `tenants` ADD COLUMN `logo` VARCHAR(255) DEFAULT NULL AFTER `direccion`', 'SELECT "logo ya existe"');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Verificar columnas finales
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'app_master' AND TABLE_NAME = 'tenants' 
ORDER BY ORDINAL_POSITION;

-- =====================================================
