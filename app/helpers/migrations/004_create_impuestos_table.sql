-- =====================================================
-- Migración 004: Sistema de Impuestos (IVA configurable)
-- =====================================================
-- BEGIN;
-- COMMIT;
-- ROLLBACK;
-- -----------------------------------------------------
-- Tabla de impuestos parametrizable
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `impuestos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `porcentaje` decimal(5,2) NOT NULL DEFAULT 0.00,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Impuestos iniciales (los más comunes en Argentina)
INSERT INTO `impuestos` (`nombre`, `codigo`, `porcentaje`) VALUES
('IVA 21%', 'IVA21', 21.00),
('IVA 10.5%', 'IVA105', 10.50),
('IVA 27%', 'IVA27', 27.00),
('IVA 2.5%', 'IVA25', 2.50),
('IVA 5%', 'IVA5', 5.00),
('Exento', 'EXENTO', 0.00),
('No Gravado', 'NOGRAV', 0.00);

-- -----------------------------------------------------
-- Agregar tabla gastos con columnas de impuestos
-- -----------------------------------------------------
CREATE TABLE `gastos` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`fecha` DATE NOT NULL,
	`categoria` ENUM('PROVEEDORES','SUELDOS','SERVICIOS','ALQUILER','IMPUESTOS','OTROS') NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`descripcion` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`orden_compra_id` INT(11) NULL DEFAULT NULL,
	`monto_total` DECIMAL(14,2) NOT NULL DEFAULT '0.00',
	`medio_pago` ENUM('EFECTIVO','TRANSFERENCIA','TARJETA_CREDITO','TARJETA_DEBITO','CHEQUE','OTRO') NOT NULL DEFAULT 'TRANSFERENCIA' COLLATE 'utf8mb4_unicode_ci',
	`impuesto_id` INT(11) NULL DEFAULT NULL,
	`monto_base` DECIMAL(14,2) NULL DEFAULT NULL,
	`monto_impuesto` DECIMAL(14,2) NULL DEFAULT NULL,
	`caja_banco_id` INT(11) NULL DEFAULT NULL,
	`comprobante` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
	`estado` ENUM('BORRADOR','APROBADO','PAGADO','ANULADO') NOT NULL DEFAULT 'BORRADOR' COLLATE 'utf8mb4_unicode_ci',
	`usuario_id` INT(11) NOT NULL,
	`observaciones` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
	`created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
	`updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE current_timestamp(),
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `gastos_orden_compra_fk` (`orden_compra_id`) USING BTREE,
	INDEX `gastos_usuario_fk` (`usuario_id`) USING BTREE,
	INDEX `gastos_categoria` (`categoria`) USING BTREE,
	INDEX `gastos_estado` (`estado`) USING BTREE,
	INDEX `gastos_fecha` (`fecha`) USING BTREE,
	INDEX `gastos_impuesto_fk` (`impuesto_id`) USING BTREE,
	INDEX `gastos_caja_banco_fk` (`caja_banco_id`) USING BTREE,
	CONSTRAINT `gastos_caja_banco_fk` FOREIGN KEY (`caja_banco_id`) REFERENCES `cajas_bancos` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL,
	CONSTRAINT `gastos_impuesto_fk` FOREIGN KEY (`impuesto_id`) REFERENCES `impuestos` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL,
	CONSTRAINT `gastos_orden_compra_fk` FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL,
	CONSTRAINT `gastos_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB;
INSERT INTO act_bd (id,descripcion) VALUES (4,'Sistema de Impuestos (IVA configurable) + tabla gastos');
-- Viejo no esta la creacion de tabla gastos asi que migro completo desde sistema
/*ALTER TABLE `gastos`
  ADD COLUMN `impuesto_id` int(11) DEFAULT NULL AFTER `medio_pago`,
  ADD COLUMN `monto_base` decimal(14,2) DEFAULT NULL AFTER `impuesto_id`,
  ADD COLUMN `monto_impuesto` decimal(14,2) DEFAULT NULL AFTER `monto_base`;

ALTER TABLE `gastos`
  ADD CONSTRAINT `gastos_impuesto_fk` FOREIGN KEY (`impuesto_id`) REFERENCES `impuestos` (`id`) ON DELETE SET NULL;
-- FIN Viejo no esta la creacion de tabla gastos asi que migro completo desde sistema
/*
