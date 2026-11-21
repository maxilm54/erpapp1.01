-- 005 (master): Anulacion logica de pagos de CtaCte
-- Un pago anulado NO se borra: queda en el historial con activo = 0 y se
-- guardan la fecha/hora, el usuario y el motivo de la anulacion. El reparto
-- entre registros (ctacte_tenant_pago_aplicaciones) tambien se conserva como
-- historial, pero los saldos de los registros se revierten al anular.
--
-- MySQL no soporta ADD COLUMN IF NOT EXISTS (MariaDB si), asi que cada
-- columna se agrega solo si falta, consultando information_schema + PREPARE.
-- Sirve para instalaciones nuevas (create_master.sql ya trae las columnas)
-- y para instalaciones viejas (las agrega aca).

SET @db_ctacte := DATABASE();

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_ctacte AND TABLE_NAME = 'ctacte_tenant_pagos' AND COLUMN_NAME = 'activo');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `ctacte_tenant_pagos` ADD COLUMN `activo` tinyint(1) NOT NULL DEFAULT 1 AFTER `usuario_id`',
  'SET @noop_ctacte := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_ctacte AND TABLE_NAME = 'ctacte_tenant_pagos' AND COLUMN_NAME = 'anulado_at');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `ctacte_tenant_pagos` ADD COLUMN `anulado_at` datetime DEFAULT NULL AFTER `activo`',
  'SET @noop_ctacte := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_ctacte AND TABLE_NAME = 'ctacte_tenant_pagos' AND COLUMN_NAME = 'anulado_por');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `ctacte_tenant_pagos` ADD COLUMN `anulado_por` int(11) DEFAULT NULL AFTER `anulado_at`',
  'SET @noop_ctacte := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_ctacte AND TABLE_NAME = 'ctacte_tenant_pagos' AND COLUMN_NAME = 'motivo_anulacion');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `ctacte_tenant_pagos` ADD COLUMN `motivo_anulacion` varchar(500) DEFAULT NULL AFTER `anulado_por`',
  'SET @noop_ctacte := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
