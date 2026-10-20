-- 021: Cuenta corriente de la empresa
-- Reemplaza el código muerto CuentacorrienteempresaController (nunca funciono: usaba namespaces
-- inexistentes y las tablas no existian en la BD).
-- INGRESO = entra dinero a la empresa. GASTO = sale dinero de la empresa.
-- Con proveedor_id cargado, el saldo por proveedor es la deuda que la empresa tiene con el.

CREATE TABLE IF NOT EXISTS `categorias_gastos_ingresos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('INGRESO','GASTO') NOT NULL DEFAULT 'GASTO',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categoria` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Categorias de ingresos y gastos de la cuenta corriente de la empresa';

INSERT IGNORE INTO `categorias_gastos_ingresos` (`nombre`, `tipo`) VALUES
('PROVEEDORES','GASTO'),
('SUELDOS','GASTO'),
('SERVICIOS','GASTO'),
('ALQUILER','GASTO'),
('IMPUESTOS','GASTO'),
('OTROS','GASTO'),
('VENTAS','INGRESO'),
('COBRO CLIENTES','INGRESO'),
('OTROS INGRESOS','INGRESO');

CREATE TABLE IF NOT EXISTS `cuentas_corrientes_empresa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('INGRESO','GASTO') NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `fecha` datetime NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `referencia_tipo` varchar(50) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_categoria` (`categoria_id`),
  KEY `idx_proveedor` (`proveedor_id`),
  KEY `idx_usuario` (`usuario_id`),
  CONSTRAINT `fk_cce_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_gastos_ingresos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cce_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cuenta corriente de la empresa: movimientos de ingresos y gastos, con deuda por proveedor';
