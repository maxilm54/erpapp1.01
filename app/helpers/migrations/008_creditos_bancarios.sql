-- =====================================================
-- Migracion 008: Creditos Bancarios
-- Prestamos, creditos, desembolsos y cuotas
-- =====================================================

CREATE TABLE IF NOT EXISTS `creditos_bancarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `caja_banco_id` int(11) NOT NULL COMMENT 'Cuenta donde se recibe el desembolso',
  `entidad` varchar(150) NOT NULL COMMENT 'Nombre del banco/entidad financiera',
  `monto_original` decimal(14,2) NOT NULL,
  `tasa_interes` decimal(5,2) DEFAULT 0.00 COMMENT 'Tasa anual %',
  `cantidad_cuotas` int(11) NOT NULL DEFAULT 1,
  `monto_cuota` decimal(14,2) NOT NULL DEFAULT 0.00,
  `fecha_desembolso` date NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL COMMENT 'Fecha de vencimiento ultima cuota',
  `tipo` enum('FIJO','VARIABLE') NOT NULL DEFAULT 'FIJO',
  `moneda` enum('ARS','USD') NOT NULL DEFAULT 'ARS',
  `estado` enum('ACTIVO','PAGADO','CANCELADO') NOT NULL DEFAULT 'ACTIVO',
  `saldo_actual` decimal(14,2) NOT NULL DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `credito_caja_fk` (`caja_banco_id`),
  KEY `credito_estado` (`estado`),
  KEY `credito_fecha` (`fecha_desembolso`),
  CONSTRAINT `credito_caja_fk` FOREIGN KEY (`caja_banco_id`) REFERENCES `cajas_bancos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `creditos_pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `credito_id` int(11) NOT NULL,
  `numero_cuota` int(11) NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `capital` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Porcion que va a capital',
  `interes` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Porcion que va a interes',
  `fecha_vencimiento` date NOT NULL,
  `fecha_pago` date DEFAULT NULL,
  `estado` enum('PENDIENTE','PAGADO','VENCIDO') NOT NULL DEFAULT 'PENDIENTE',
  `caja_banco_id` int(11) DEFAULT NULL COMMENT 'Cuenta donde se pago',
  `asiento_contable_id` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pago_credito_fk` (`credito_id`),
  KEY `pago_estado` (`estado`),
  KEY `pago_fecha_venc` (`fecha_vencimiento`),
  CONSTRAINT `pago_credito_fk` FOREIGN KEY (`credito_id`) REFERENCES `creditos_bancarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Actualizo registro de migracion de base de datos
INSERT INTO act_bd (id, descripcion) VALUES (8, 'Creditos bancarios - prestamos, desembolsos y cuotas');
-- =====================================================