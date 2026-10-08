-- =====================================================
-- Migración 015: Módulo de Devoluciones
-- =====================================================

-- -----------------------------------------------------
-- Tabla: devoluciones (cabecera)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `devoluciones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `numero` INT NOT NULL UNIQUE,
  `remito_id` INT NOT NULL,
  `cliente_id` INT NOT NULL,
  `usuario_id` INT NOT NULL,
  `motivo` TEXT NOT NULL,
  `estado` ENUM('PENDIENTE','PROCESADA','ANULADA') DEFAULT 'PENDIENTE',
  `motivo_anulacion` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  `anulado_at` DATETIME DEFAULT NULL,
  `pdf_path` VARCHAR(255) DEFAULT NULL,
  `pdf_hash` CHAR(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Tabla: devoluciones_detalle (ítems devueltos)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `devoluciones_detalle` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `devolucion_id` INT NOT NULL,
  `producto_id` INT NOT NULL,
  `cantidad_total` DECIMAL(12,3) NOT NULL,
  `cantidad_reingresa` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `cantidad_descarta` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `condicion` ENUM('NUEVO','BUEN_ESTADO','ESTADO_REGULAR','DANADO','INSERVIBLE') NOT NULL,
  `precio_unitario` DECIMAL(12,2) NOT NULL,
  `observaciones` TEXT DEFAULT NULL,
  FOREIGN KEY (`devolucion_id`) REFERENCES `devoluciones`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Tabla: devoluciones_reembolsos (método de reembolso)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `devoluciones_reembolsos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `devolucion_id` INT NOT NULL,
  `metodo` ENUM('EFECTIVO','TRANSFERENCIA','NOTA_CREDITO') NOT NULL,
  `monto` DECIMAL(12,2) NOT NULL,
  `caja_banco_id` INT DEFAULT NULL,
  `observaciones` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`devolucion_id`) REFERENCES `devoluciones`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`caja_banco_id`) REFERENCES `cajas_bancos`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Cuentas contables nuevas para devoluciones
-- -----------------------------------------------------
INSERT INTO `cuentas_contables` (`codigo`, `nombre`, `tipo`, `padre_id`, `nivel`, `acepta_movimiento`) VALUES
('4400', 'Devoluciones de Ventas', 'INGRESO', 4, 2, 1),
('5110', 'Costo de Mercadería Devuelta', 'EGRESO', 5, 2, 1);

-- -----------------------------------------------------
-- Numerador para devoluciones
-- -----------------------------------------------------
INSERT INTO `numeradores` (`tipo`, `ultimo_numero`, `incremento`, `prefijo`) VALUES
('DEVOLUCION', 0, 1, 'DEV-');

-- -----------------------------------------------------
-- Registro de migración
-- -----------------------------------------------------
INSERT INTO act_bd (id, descripcion) VALUES (15, 'Módulo de Devoluciones - Tablas devoluciones, devoluciones_detalle, devoluciones_reembolsos - Cuentas contables 4400 y 5110 - Numerador DEVOLUCION');
