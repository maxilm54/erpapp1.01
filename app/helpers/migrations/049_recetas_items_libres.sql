-- 049: Recetas para items libres de presupuesto y nota de pedido
-- Una receta libre no pertenece a un producto de la base: se ata a la linea
-- libre del presupuesto o de la nota de pedido. Sirve como cotizador del item
-- y luego como receta de produccion. El item no genera stock de producto
-- terminado, solo consume materias primas.

SET @db_libre := DATABASE();

-- recetas.producto_id pasa a ser opcional (recetas libres)
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db_libre AND TABLE_NAME = 'recetas'
    AND CONSTRAINT_NAME = 'recetas_producto_fk' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql := IF(@fk > 0,
  'ALTER TABLE `recetas` DROP FOREIGN KEY `recetas_producto_fk`',
  'SET @noop_libre := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `recetas` MODIFY COLUMN `producto_id` int(11) NULL DEFAULT NULL;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db_libre AND TABLE_NAME = 'recetas'
    AND CONSTRAINT_NAME = 'recetas_producto_fk' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql := IF(@fk = 0,
  'ALTER TABLE `recetas` ADD CONSTRAINT `recetas_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE',
  'SET @noop_libre := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_libre AND TABLE_NAME = 'recetas' AND COLUMN_NAME = 'descripcion_libre');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `recetas` ADD COLUMN `descripcion_libre` varchar(255) DEFAULT NULL AFTER `producto_id`',
  'SET @noop_libre := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Linea del presupuesto a la que se ata la receta libre
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_libre AND TABLE_NAME = 'presupuestos_detalle' AND COLUMN_NAME = 'receta_id');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `presupuestos_detalle` ADD COLUMN `receta_id` int(11) DEFAULT NULL AFTER `producto_id`',
  'SET @noop_libre := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db_libre AND TABLE_NAME = 'presupuestos_detalle'
    AND CONSTRAINT_NAME = 'presupuestos_detalle_receta_fk' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql := IF(@fk = 0,
  'ALTER TABLE `presupuestos_detalle` ADD CONSTRAINT `presupuestos_detalle_receta_fk` FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`id`) ON DELETE SET NULL',
  'SET @noop_libre := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_libre AND TABLE_NAME = 'presupuestos_detalle' AND INDEX_NAME = 'receta_id');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `presupuestos_detalle` ADD KEY `receta_id` (`receta_id`)',
  'SET @noop_libre := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Linea de la nota de pedido a la que se hereda la receta libre
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_libre AND TABLE_NAME = 'notas_pedido_detalle' AND COLUMN_NAME = 'receta_id');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `notas_pedido_detalle` ADD COLUMN `receta_id` int(11) DEFAULT NULL AFTER `producto_id`',
  'SET @noop_libre := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db_libre AND TABLE_NAME = 'notas_pedido_detalle'
    AND CONSTRAINT_NAME = 'notas_pedido_detalle_receta_fk' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql := IF(@fk = 0,
  'ALTER TABLE `notas_pedido_detalle` ADD CONSTRAINT `notas_pedido_detalle_receta_fk` FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`id`) ON DELETE SET NULL',
  'SET @noop_libre := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_libre AND TABLE_NAME = 'notas_pedido_detalle' AND INDEX_NAME = 'receta_id');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `notas_pedido_detalle` ADD KEY `receta_id` (`receta_id`)',
  'SET @noop_libre := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- La orden de produccion puede fabricar un item libre (sin producto en la base)
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db_libre AND TABLE_NAME = 'ordenes_produccion'
    AND CONSTRAINT_NAME = 'ordenes_produccion_producto_fk' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql := IF(@fk > 0,
  'ALTER TABLE `ordenes_produccion` DROP FOREIGN KEY `ordenes_produccion_producto_fk`',
  'SET @noop_libre := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `ordenes_produccion` MODIFY COLUMN `producto_id` int(11) NULL DEFAULT NULL;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db_libre AND TABLE_NAME = 'ordenes_produccion'
    AND CONSTRAINT_NAME = 'ordenes_produccion_producto_fk' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql := IF(@fk = 0,
  'ALTER TABLE `ordenes_produccion` ADD CONSTRAINT `ordenes_produccion_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)',
  'SET @noop_libre := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_libre AND TABLE_NAME = 'ordenes_produccion' AND COLUMN_NAME = 'producto_libre');
SET @sql := IF(@existe = 0,
  'ALTER TABLE `ordenes_produccion` ADD COLUMN `producto_libre` varchar(255) DEFAULT NULL AFTER `producto_id`',
  'SET @noop_libre := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- El detalle de produccion tambien puede quedar sin producto
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db_libre AND TABLE_NAME = 'orden_produccion_detalle'
    AND CONSTRAINT_NAME = 'orden_detalle_producto_fk' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql := IF(@fk > 0,
  'ALTER TABLE `orden_produccion_detalle` DROP FOREIGN KEY `orden_detalle_producto_fk`',
  'SET @noop_libre := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `orden_produccion_detalle` MODIFY COLUMN `producto_id` int(11) NULL DEFAULT NULL;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db_libre AND TABLE_NAME = 'orden_produccion_detalle'
    AND CONSTRAINT_NAME = 'orden_detalle_producto_fk' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql := IF(@fk = 0,
  'ALTER TABLE `orden_produccion_detalle` ADD CONSTRAINT `orden_detalle_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)',
  'SET @noop_libre := 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO configuraciones (clave, valor) VALUES ('usar_recetas_libres', '0');

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (49, 'Recetas libres para items de presupuesto y nota de pedido');
