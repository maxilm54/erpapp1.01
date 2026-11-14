-- 041: Consumo de productos componentes de recetas en produccion
-- Cuando una receta incluye productos (semielaborados/kits), al confirmar la
-- produccion se descuentan del stock como una salida mas. Las series de esos
-- productos pasan a CONSUMIDO y quedan vinculadas a la OP.

ALTER TABLE `series`
  MODIFY COLUMN `estado` enum('DISPONIBLE','REMITIDO','EN_DEVOLUCION','BAJA','CONSUMIDO') NOT NULL DEFAULT 'DISPONIBLE',
  ADD COLUMN `orden_produccion_id` int(11) DEFAULT NULL AFTER `devolucion_id`,
  ADD KEY `idx_series_orden_produccion` (`orden_produccion_id`);

ALTER TABLE `series`
  ADD CONSTRAINT `series_orden_produccion_fk` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`);

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (41, 'Consumo de productos componentes de recetas en produccion');
