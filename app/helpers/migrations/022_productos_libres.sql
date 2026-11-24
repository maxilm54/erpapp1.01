-- 022: Productos libres en Presupuestos y Notas de Pedido
-- Permite agregar lineas con un producto que NO existe en la base de productos.
-- En ese caso producto_id queda NULL y se guarda el nombre en `descripcion`.
-- El usuario puede, opcionalmente, convertir la linea libre en un producto real
-- (boton "Agregar a Productos" en la vista del documento).
--
-- MySQL no soporta ADD COLUMN IF NOT EXISTS (MariaDB si), asi que la columna
-- `descripcion` se agrega solo si falta, consultando information_schema + PREPARE.
-- Sirve para instalaciones nuevas (tenant_schema.sql ya trae la columna en las dos
-- tablas) y para instalaciones viejas (la agrega aca).

SET @db_libres := DATABASE();

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_libres AND TABLE_NAME = 'presupuestos_detalle' AND COLUMN_NAME = 'descripcion');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `presupuestos_detalle` ADD COLUMN `descripcion` VARCHAR(255) DEFAULT NULL AFTER `producto_id`',
  'SET @noop_libres := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `presupuestos_detalle`
  MODIFY COLUMN `producto_id` INT(11) DEFAULT NULL;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_libres AND TABLE_NAME = 'notas_pedido_detalle' AND COLUMN_NAME = 'descripcion');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `notas_pedido_detalle` ADD COLUMN `descripcion` VARCHAR(255) DEFAULT NULL AFTER `producto_id`',
  'SET @noop_libres := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `notas_pedido_detalle`
  MODIFY COLUMN `producto_id` INT(11) DEFAULT NULL;

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (22, 'Productos libres (sin producto_id) en presupuestos y notas de pedido');
