-- 020: Control de números de serie por unidad (producto/materia prima)
-- Un producto o MP con usa_serie=1 se recepciona y remite UNIDAD A UNIDAD.

ALTER TABLE `productos`
  ADD COLUMN `usa_serie` tinyint(1) NOT NULL DEFAULT 0 AFTER `precio_venta`;

ALTER TABLE `materias_primas`
  ADD COLUMN `usa_serie` tinyint(1) NOT NULL DEFAULT 0 AFTER `precio_actual`;

CREATE TABLE IF NOT EXISTS `series` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) DEFAULT NULL,
  `materia_prima_id` int(11) DEFAULT NULL,
  `numero_serie` varchar(100) NOT NULL,
  `lote_id` int(11) DEFAULT NULL,
  `deposito_id` int(11) DEFAULT NULL,
  `estado` enum('DISPONIBLE','REMITIDO','EN_DEVOLUCION','BAJA') NOT NULL DEFAULT 'DISPONIBLE',
  `remito_salida_id` int(11) DEFAULT NULL,
  `devolucion_id` int(11) DEFAULT NULL,
  `ingreso_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_serie` (`numero_serie`),
  KEY `idx_producto` (`producto_id`),
  KEY `idx_mp` (`materia_prima_id`),
  KEY `idx_deposito` (`deposito_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_lote` (`lote_id`),
  CONSTRAINT `fk_serie_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_serie_mp` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_serie_deposito` FOREIGN KEY (`deposito_id`) REFERENCES `depositos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_serie_lote` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Unidades individuales con numero de serie (productos/MP que controlan serie)';