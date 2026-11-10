-- 031: Facturas de compra que cubren una o mas Ordenes de Compra
-- Tabla de relacion factura <-> OC con el monto asignado a cada OC.
-- Con esto:
--   - una factura puede cubrir varias OCs (caso: el proveedor manda una
--     factura con muchos pedidos),
--   - se bloquea facturar de mas sobre una OC ya cubierta,
--   - anular la factura libera el saldo y permite volver a registrar.
-- La columna legacy facturas_compra.orden_compra_id se mantiene apuntando
-- a la primera OC (compatibilidad con consultas existentes).

CREATE TABLE IF NOT EXISTS `facturas_compra_ocs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `factura_id` int(11) NOT NULL,
  `oc_id` int(11) NOT NULL,
  `monto_asignado` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Parte del total de la factura que cubre esta OC',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_factura_oc` (`factura_id`, `oc_id`),
  KEY `idx_oc` (`oc_id`),
  CONSTRAINT `fk_fcocs_factura` FOREIGN KEY (`factura_id`) REFERENCES `facturas_compra` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fcocs_oc` FOREIGN KEY (`oc_id`) REFERENCES `ordenes_compra` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Relacion facturas de compra con ordenes de compra (una factura puede cubrir varias OCs)';

-- Backfill: las facturas registradas con OC unica pasan a la tabla de relacion
INSERT IGNORE INTO facturas_compra_ocs (factura_id, oc_id, monto_asignado)
SELECT id, orden_compra_id, monto_total
FROM facturas_compra
WHERE orden_compra_id IS NOT NULL AND estado = 'REGISTRADA';

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (31, 'Facturas de compra con multiples OCs (facturas_compra_ocs) + control de facturado por OC');
