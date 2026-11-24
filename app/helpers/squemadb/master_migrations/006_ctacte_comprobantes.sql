-- 006 (master): Comprobantes de CtaCte
-- Agrega el saldo pendiente posterior al pago (snapshot que se imprime en el
-- comprobante de pago) y asegura el email de la empresa, que es el
-- destinatario de los comprobantes.
--
-- MySQL no soporta ADD COLUMN IF NOT EXISTS, asi que se usa la misma guarda
-- portable que la 005 (information_schema + PREPARE).

SET @db_ctacte := DATABASE();

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_ctacte AND TABLE_NAME = 'ctacte_tenant_pagos' AND COLUMN_NAME = 'saldo_pendiente_despues');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `ctacte_tenant_pagos` ADD COLUMN `saldo_pendiente_despues` decimal(12,2) DEFAULT NULL AFTER `monto`',
  'SET @noop_ctacte := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_ctacte AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'email');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `tenants` ADD COLUMN `email` varchar(150) DEFAULT NULL AFTER `cuit`',
  'SET @noop_ctacte := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
