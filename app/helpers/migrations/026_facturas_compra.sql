-- 026: Facturas de compra de proveedores
-- Registra la factura que llega del proveedor contra una OC (o sin OC, para
-- servicios). Al registrarse genera el asiento de compra (Debe gasto + IVA
-- / Haber Proveedores 2100) y queda pendiente de pago por ordenes de pago.
-- La deuda del proveedor se calcula como total facturado menos ordenes de pago.

CREATE TABLE IF NOT EXISTS `facturas_compra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `orden_compra_id` int(11) DEFAULT NULL,
  `tipo_fiscal` varchar(5) DEFAULT NULL,
  `numero` varchar(50) NOT NULL,
  `fecha_factura` date NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `moneda` int(11) NOT NULL DEFAULT 1,
  `monto_base` decimal(14,2) NOT NULL DEFAULT 0.00,
  `monto_iva` decimal(14,2) NOT NULL DEFAULT 0.00,
  `monto_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `estado` enum('REGISTRADA','ANULADA') NOT NULL DEFAULT 'REGISTRADA',
  `observaciones` text DEFAULT NULL,
  `asiento_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_factura_proveedor` (`proveedor_id`),
  KEY `idx_factura_oc` (`orden_compra_id`),
  KEY `idx_factura_estado` (`estado`),
  KEY `idx_factura_fecha` (`fecha_factura`),
  KEY `idx_factura_usuario` (`usuario_id`),
  CONSTRAINT `fk_factura_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_factura_oc` FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_factura_asiento` FOREIGN KEY (`asiento_id`) REFERENCES `asientos_contables` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_factura_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Facturas de compra de proveedores';

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (26, 'Facturas de compra: registro de facturas de proveedor con asiento de compra');
