CREATE TABLE IF NOT EXISTS `depositos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Depósitos/almacenes';

INSERT INTO `depositos` (`nombre`, `descripcion`) VALUES ('Depósito Principal', 'Depósito general por defecto');

CREATE TABLE IF NOT EXISTS `lotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_lote` varchar(100) NOT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `materia_prima_id` int(11) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `fecha_produccion` date DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `producto_id` (`producto_id`),
  KEY `materia_prima_id` (`materia_prima_id`),
  KEY `codigo_lote` (`codigo_lote`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lotes/números de lote con vencimiento';

ALTER TABLE `movimientos_stock`
  ADD COLUMN `deposito_id` int(11) DEFAULT NULL AFTER `materia_prima_id`,
  ADD COLUMN `lote_id` int(11) DEFAULT NULL AFTER `deposito_id`,
  ADD KEY `deposito_id` (`deposito_id`),
  ADD KEY `lote_id` (`lote_id`);
-- -----------------------------------------------------
-- Registro de migración
-- -----------------------------------------------------
INSERT INTO act_bd (id, descripcion) VALUES (16, 'Agregado de tablas depositos y lotes, agregado de columnas deposito_id y lote_id a movimientos_stock');