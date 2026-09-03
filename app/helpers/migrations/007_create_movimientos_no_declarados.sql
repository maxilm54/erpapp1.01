-- =====================================================
-- Migración 007: Movimientos No Declarados
-- Sistema paralelo para ventas/compras sin factura
-- =====================================================

-- Tabla cabecera
CREATE TABLE IF NOT EXISTS `movimientos_no_declarados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('VENTA','COMPRA') NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `razon_social` varchar(150) DEFAULT NULL,
  `cuit` varchar(20) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `monto_total` decimal(12,2) DEFAULT 0.00,
  `estado` enum('PENDIENTE','COBRADO','PARCIAL','ANULADO') DEFAULT 'PENDIENTE',
  `saldo_pendiente` decimal(12,2) DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tipo` (`tipo`),
  KEY `estado` (`estado`),
  KEY `cliente_id` (`cliente_id`),
  KEY `proveedor_id` (`proveedor_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `mnd_cliente_fk` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mnd_proveedor_fk` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mnd_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla detalle
CREATE TABLE IF NOT EXISTS `movimientos_no_declarados_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mov_no_declarado_id` int(11) NOT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `materia_prima_id` int(11) DEFAULT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `precio_unitario` decimal(12,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `mov_no_declarado_id` (`mov_no_declarado_id`),
  KEY `producto_id` (`producto_id`),
  KEY `materia_prima_id` (`materia_prima_id`),
  CONSTRAINT `mndd_mov_fk` FOREIGN KEY (`mov_no_declarado_id`) REFERENCES `movimientos_no_declarados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mndd_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mndd_mp_fk` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- movimientos_no_declarados_detalle
-- Agregar campo descripcion a detalles
-- Para soportar items manuales (servicios) sin producto/materia prima
ALTER TABLE `movimientos_no_declarados_detalle`
    ADD COLUMN `descripcion` varchar(255) DEFAULT NULL AFTER `materia_prima_id`;

-- Tabla de cobros/pagos
CREATE TABLE IF NOT EXISTS `movimientos_no_declarados_pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mov_no_declarado_id` int(11) NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `descripcion` text DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `mov_no_declarado_id` (`mov_no_declarado_id`),
  CONSTRAINT `mndp_mov_fk` FOREIGN KEY (`mov_no_declarado_id`) REFERENCES `movimientos_no_declarados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mndp_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Actualizo registro de migracion de base de datos
INSERT INTO act_bd (id, descripcion) VALUES (7, 'Movimientos No Declarados - tabla cabecera, detalle y pagos');
-- =====================================================