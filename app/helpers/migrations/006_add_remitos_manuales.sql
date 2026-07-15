-- =====================================================
-- Migración 006: Remitos manuales (sin NP)
-- =====================================================

-- Hacer nullable la FK de nota_pedido (para remitos manuales)
ALTER TABLE `remitos_salida`
  MODIFY COLUMN `nota_pedido_id` int(11) DEFAULT NULL;

-- Agregar columnas para datos de cliente ocasional/ad-hoc
ALTER TABLE `remitos_salida`
  ADD COLUMN `cliente_id` int(11) DEFAULT NULL AFTER `nota_pedido_id`,
  ADD COLUMN `cliente_nombre` varchar(150) DEFAULT NULL AFTER `cliente_id`,
  ADD COLUMN `cliente_cuit` varchar(20) DEFAULT NULL AFTER `cliente_nombre`,
  ADD COLUMN `cliente_direccion` varchar(255) DEFAULT NULL AFTER `cliente_cuit`,
  ADD COLUMN `cliente_email` varchar(150) DEFAULT NULL AFTER `cliente_direccion`,
  ADD COLUMN `cliente_telefono` varchar(50) DEFAULT NULL AFTER `cliente_email`,
  ADD COLUMN `cliente_localidad` varchar(100) DEFAULT NULL AFTER `cliente_telefono`;

-- FK opcional a clientes
ALTER TABLE `remitos_salida`
  ADD CONSTRAINT `remitos_cliente_fk` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL;

-- Index para búsquedas por cliente
ALTER TABLE `remitos_salida`
  ADD KEY `cliente_id` (`cliente_id`);
