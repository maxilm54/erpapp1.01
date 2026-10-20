CREATE TABLE IF NOT EXISTS `transferencias_depositos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` int(11) NOT NULL,
  `deposito_origen_id` int(11) NOT NULL,
  `deposito_destino_id` int(11) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` enum('COMPLETADA','ANULADA') DEFAULT 'COMPLETADA',
  `usuario_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `deposito_origen_id` (`deposito_origen_id`),
  KEY `deposito_destino_id` (`deposito_destino_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Transferencias de stock entre depósitos';

CREATE TABLE IF NOT EXISTS `transferencias_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transferencia_id` int(11) NOT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `materia_prima_id` int(11) DEFAULT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `lote_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transferencia_id` (`transferencia_id`),
  KEY `producto_id` (`producto_id`),
  KEY `materia_prima_id` (`materia_prima_id`),
  KEY `lote_id` (`lote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalle de transferencias entre depósitos';

INSERT INTO `numeradores` (`tipo`, `ultimo_numero`, `incremento`, `prefijo`) VALUES
('TRANSFERENCIA', 0, 1, 'TR-');