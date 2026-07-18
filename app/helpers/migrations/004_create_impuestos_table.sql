-- =====================================================
-- Migración 004: Sistema de Impuestos (IVA configurable)
-- =====================================================
-- BEGIN;
INSERT INTO act_bd (id,descripcion) VALUES (4,'Sistema de Impuestos (IVA configurable)');
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
-- Agregar columnas de impuestos a la tabla gastos
-- -----------------------------------------------------
ALTER TABLE `gastos`
  ADD COLUMN `impuesto_id` int(11) DEFAULT NULL AFTER `medio_pago`,
  ADD COLUMN `monto_base` decimal(14,2) DEFAULT NULL AFTER `impuesto_id`,
  ADD COLUMN `monto_impuesto` decimal(14,2) DEFAULT NULL AFTER `monto_base`;

ALTER TABLE `gastos`
  ADD CONSTRAINT `gastos_impuesto_fk` FOREIGN KEY (`impuesto_id`) REFERENCES `impuestos` (`id`) ON DELETE SET NULL;
