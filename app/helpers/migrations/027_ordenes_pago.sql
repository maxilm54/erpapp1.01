-- 027: Ordenes de pago de facturas de compra
-- Documento formal de pago: BORRADOR, APROBADA, PAGADA, ANULADA.
-- Al PAGAR genera el asiento (Debe Proveedores 2100 / Haber Caja) y el
-- egreso en caja o banco. La numeracion usa el numerador ORDEN_PAGO.
-- La anulacion de una orden pagada revierte asiento, caja y queda ANULADA.

CREATE TABLE IF NOT EXISTS `ordenes_pago` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `factura_compra_id` int(11) NOT NULL,
  `monto` decimal(14,2) NOT NULL DEFAULT 0.00,
  `medio_pago` enum('EFECTIVO','TRANSFERENCIA','TARJETA_CREDITO','TARJETA_DEBITO','CHEQUE','OTRO') NOT NULL DEFAULT 'TRANSFERENCIA',
  `fecha` date NOT NULL,
  `fecha_pago` datetime DEFAULT NULL,
  `caja_banco_id` int(11) DEFAULT NULL,
  `estado` enum('BORRADOR','APROBADA','PAGADA','ANULADA') NOT NULL DEFAULT 'BORRADOR',
  `observaciones` text DEFAULT NULL,
  `aprobada_at` datetime DEFAULT NULL,
  `aprobado_por` int(11) DEFAULT NULL,
  `asiento_id` int(11) DEFAULT NULL,
  `movimiento_caja_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_op_numero` (`numero`),
  KEY `idx_op_proveedor` (`proveedor_id`),
  KEY `idx_op_factura` (`factura_compra_id`),
  KEY `idx_op_estado` (`estado`),
  KEY `idx_op_fecha` (`fecha`),
  KEY `idx_op_caja` (`caja_banco_id`),
  KEY `idx_op_usuario` (`usuario_id`),
  CONSTRAINT `fk_op_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_op_factura` FOREIGN KEY (`factura_compra_id`) REFERENCES `facturas_compra` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_op_caja` FOREIGN KEY (`caja_banco_id`) REFERENCES `cajas_bancos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_op_asiento` FOREIGN KEY (`asiento_id`) REFERENCES `asientos_contables` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_op_movimiento` FOREIGN KEY (`movimiento_caja_id`) REFERENCES `movimientos_caja` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_op_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ordenes de pago a proveedores por facturas de compra';

INSERT INTO `numeradores` (`tipo`, `ultimo_numero`, `incremento`, `prefijo`) VALUES
('ORDEN_PAGO', 0, 1, 'OP-');

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (27, 'Ordenes de pago: documento formal de pago de facturas de compra con numerador ORDEN_PAGO');
