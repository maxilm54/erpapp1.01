-- =====================================================
-- Migración 003: Módulo Contable - Partida Doble
-- =====================================================
-- BEGIN;
INSERT INTO act_bd (id,descripcion) VALUES (3,'Contable-Plan de Cuentas-Asientos Contables(cabecera)-Detalle de Asientos (debe/haber)-Cajas, Bancos y Fondos-Movimientos de Caja/Banco-Conciliación Bancaria-Detalle de Conciliación-Insert');
-- COMMIT;
-- ROLLBACK;
-- -----------------------------------------------------
-- 1. Plan de Cuentas
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cuentas_contables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `tipo` enum('ACTIVO','PASIVO','PATRIMONIO','INGRESO','EGRESO') NOT NULL,
  `padre_id` int(11) DEFAULT NULL,
  `nivel` int(11) NOT NULL DEFAULT 1,
  `acepta_movimiento` tinyint(1) NOT NULL DEFAULT 1,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `tipo` (`tipo`),
  KEY `padre_id` (`padre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. Asientos Contables (cabecera)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `asientos_contables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `descripcion` varchar(500) NOT NULL,
  `tipo` enum('OPERACION','APERTURA','CIERRE','AJUSTE') NOT NULL DEFAULT 'OPERACION',
  `origen_modulo` varchar(50) DEFAULT NULL,
  `origen_tipo` varchar(50) DEFAULT NULL,
  `origen_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `fecha` (`fecha`),
  KEY `origen` (`origen_modulo`, `origen_tipo`, `origen_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 3. Detalle de Asientos (debe/haber)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `asientos_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asiento_id` int(11) NOT NULL,
  `cuenta_contable_id` int(11) NOT NULL,
  `debe` decimal(14,2) NOT NULL DEFAULT 0.00,
  `haber` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `asiento_id` (`asiento_id`),
  KEY `cuenta_contable_id` (`cuenta_contable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 4. Cajas, Bancos y Fondos
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cajas_bancos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('CAJA','BANCO','FONDO') NOT NULL,
  `banco` varchar(100) DEFAULT NULL,
  `numero_cuenta` varchar(50) DEFAULT NULL,
  `cbu` varchar(30) DEFAULT NULL,
  `saldo_inicial` decimal(14,2) NOT NULL DEFAULT 0.00,
  `saldo_actual` decimal(14,2) NOT NULL DEFAULT 0.00,
  `moneda` varchar(10) NOT NULL DEFAULT 'ARS',
  `cuenta_contable_id` int(11) DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tipo` (`tipo`),
  KEY `cuenta_contable_id` (`cuenta_contable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 5. Movimientos de Caja/Banco
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `movimientos_caja` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `caja_banco_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `tipo` enum('INGRESO','EGRESO','TRANSFERENCIA') NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `saldo_anterior` decimal(14,2) NOT NULL DEFAULT 0.00,
  `saldo_posterior` decimal(14,2) NOT NULL DEFAULT 0.00,
  `descripcion` varchar(255) DEFAULT NULL,
  `asiento_contable_id` int(11) DEFAULT NULL,
  `referencia_modulo` varchar(50) DEFAULT NULL,
  `referencia_tipo` varchar(50) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `caja_banco_id` (`caja_banco_id`),
  KEY `fecha` (`fecha`),
  KEY `asiento_contable_id` (`asiento_contable_id`),
  KEY `referencia` (`referencia_modulo`, `referencia_tipo`, `referencia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 6. Conciliación Bancaria
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `conciliaciones_bancarias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `caja_banco_id` int(11) NOT NULL,
  `fecha_conciliacion` date NOT NULL,
  `saldo_segun_banco` decimal(14,2) NOT NULL DEFAULT 0.00,
  `saldo_segun_sistema` decimal(14,2) NOT NULL DEFAULT 0.00,
  `diferencia` decimal(14,2) NOT NULL DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  `estado` enum('PENDIENTE','CONCILIADA') NOT NULL DEFAULT 'PENDIENTE',
  `usuario_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `caja_banco_id` (`caja_banco_id`),
  KEY `estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 7. Detalle de Conciliación
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `conciliaciones_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conciliacion_id` int(11) NOT NULL,
  `movimiento_caja_id` int(11) DEFAULT NULL,
  `fecha_movimiento` date NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `conciliado` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `conciliacion_id` (`conciliacion_id`),
  KEY `movimiento_caja_id` (`movimiento_caja_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Foreign Keys
-- -----------------------------------------------------
ALTER TABLE `cuentas_contables` ADD CONSTRAINT `cuentas_padre_fk` FOREIGN KEY (`padre_id`) REFERENCES `cuentas_contables` (`id`) ON DELETE SET NULL;
ALTER TABLE `asientos_contables` ADD CONSTRAINT `asientos_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;
ALTER TABLE `asientos_detalle` ADD CONSTRAINT `asiento_detalle_asiento_fk` FOREIGN KEY (`asiento_id`) REFERENCES `asientos_contables` (`id`) ON DELETE CASCADE;
ALTER TABLE `asientos_detalle` ADD CONSTRAINT `asiento_detalle_cuenta_fk` FOREIGN KEY (`cuenta_contable_id`) REFERENCES `cuentas_contables` (`id`) ON DELETE RESTRICT;
ALTER TABLE `cajas_bancos` ADD CONSTRAINT `cajas_cuenta_contable_fk` FOREIGN KEY (`cuenta_contable_id`) REFERENCES `cuentas_contables` (`id`) ON DELETE SET NULL;
ALTER TABLE `movimientos_caja` ADD CONSTRAINT `movcaja_caja_fk` FOREIGN KEY (`caja_banco_id`) REFERENCES `cajas_bancos` (`id`) ON DELETE RESTRICT;
ALTER TABLE `movimientos_caja` ADD CONSTRAINT `movcaja_asiento_fk` FOREIGN KEY (`asiento_contable_id`) REFERENCES `asientos_contables` (`id`) ON DELETE SET NULL;
ALTER TABLE `movimientos_caja` ADD CONSTRAINT `movcaja_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;
ALTER TABLE `conciliaciones_bancarias` ADD CONSTRAINT `concilia_caja_fk` FOREIGN KEY (`caja_banco_id`) REFERENCES `cajas_bancos` (`id`) ON DELETE RESTRICT;
ALTER TABLE `conciliaciones_bancarias` ADD CONSTRAINT `concilia_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;
ALTER TABLE `conciliaciones_detalle` ADD CONSTRAINT `concilia_det_concilia_fk` FOREIGN KEY (`conciliacion_id`) REFERENCES `conciliaciones_bancarias` (`id`) ON DELETE CASCADE;
ALTER TABLE `conciliaciones_detalle` ADD CONSTRAINT `concilia_det_movimiento_fk` FOREIGN KEY (`movimiento_caja_id`) REFERENCES `movimientos_caja` (`id`) ON DELETE SET NULL;

-- -----------------------------------------------------
-- Plan de Cuentas Inicial (Genérico Simplificado)
-- -----------------------------------------------------
INSERT INTO `cuentas_contables` (`codigo`, `nombre`, `tipo`, `padre_id`, `nivel`, `acepta_movimiento`) VALUES
-- Nivel 1: Categorías
('1000', 'ACTIVO', 'ACTIVO', NULL, 1, 0),
('2000', 'PASIVO', 'PASIVO', NULL, 1, 0),
('3000', 'PATRIMONIO', 'PATRIMONIO', NULL, 1, 0),
('4000', 'INGRESOS', 'INGRESO', NULL, 1, 0),
('5000', 'EGRESOS / COSTOS', 'EGRESO', NULL, 1, 0),

-- Nivel 2: Activo
('1100', 'Caja', 'ACTIVO', 1, 2, 0),
('1101', 'Caja General', 'ACTIVO', 6, 3, 1),
('1102', 'Caja Chica', 'ACTIVO', 6, 3, 1),
('1200', 'Bancos', 'ACTIVO', 1, 2, 0),
('1201', 'Banco Nación', 'ACTIVO', 9, 3, 1),
('1202', 'Banco Galicia', 'ACTIVO', 9, 3, 1),
('1300', 'Fondos', 'ACTIVO', 1, 2, 0),
('1301', 'Fondo de Maniobra', 'ACTIVO', 11, 3, 1),
('1400', 'Cuentas Corrientes Clientes', 'ACTIVO', 1, 2, 1),
('1500', 'Stock - Materias Primas', 'ACTIVO', 1, 2, 1),
('1600', 'Stock - Productos', 'ACTIVO', 1, 2, 1),
('1700', 'Bienes de Uso', 'ACTIVO', 1, 2, 1),

-- Nivel 2: Pasivo
('2100', 'Cuentas Corrientes Proveedores', 'PASIVO', 2, 2, 1),
('2200', 'Impuestos a Pagar', 'PASIVO', 2, 2, 1),
('2300', 'Sueldos a Pagar', 'PASIVO', 2, 2, 1),
('2400', 'Deudas Financieras', 'PASIVO', 2, 2, 1),

-- Nivel 2: Patrimonio
('3100', 'Capital Social', 'PATRIMONIO', 3, 2, 1),
('3200', 'Resultados Acumulados', 'PATRIMONIO', 3, 2, 1),
('3300', 'Resultado del Ejercicio', 'PATRIMONIO', 3, 2, 1),

-- Nivel 2: Ingresos
('4100', 'Ventas de Productos', 'INGRESO', 4, 2, 1),
('4200', 'Prestación de Servicios', 'INGRESO', 4, 2, 1),
('4300', 'Otros Ingresos', 'INGRESO', 4, 2, 1),

-- Nivel 2: Egresos / Costos
('5100', 'Costo de Mercadería Vendida', 'EGRESO', 5, 2, 1),
('5200', 'Compras de Materia Prima', 'EGRESO', 5, 2, 1),
('5300', 'Sueldos y Cargas Sociales', 'EGRESO', 5, 2, 1),
('5400', 'Servicios (luz, gas, internet)', 'EGRESO', 5, 2, 1),
('5500', 'Alquileres', 'EGRESO', 5, 2, 1),
('5600', 'Impuestos y Tasas', 'EGRESO', 5, 2, 1),
('5700', 'Gastos Varios', 'EGRESO', 5, 2, 1),
('5800', 'Gastos Bancarios', 'EGRESO', 5, 2, 1);
