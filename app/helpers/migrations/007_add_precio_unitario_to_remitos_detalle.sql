-- Migración 007: Agregar precio_unitario a remitos_salida_detalle
-- Permite guardar el precio unitario personalizado por producto en cada remito

ALTER TABLE `remitos_salida_detalle`
ADD COLUMN `precio_unitario` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `cantidad`;
