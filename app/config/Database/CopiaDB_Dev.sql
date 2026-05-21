-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         10.4.32-MariaDB - mariadb.org binary distribution
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.11.0.7065
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para app
DROP DATABASE IF EXISTS `app`;
CREATE DATABASE IF NOT EXISTS `app` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `app`;

-- Volcando estructura para tabla app.categorias_mp_id
DROP TABLE IF EXISTS `categorias_mp_id`;
CREATE TABLE IF NOT EXISTS `categorias_mp_id` (
  `id_categoria` int(3) NOT NULL AUTO_INCREMENT,
  `categoria_nombre` varchar(50) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.clientes
DROP TABLE IF EXISTS `clientes`;
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `razon_social` varchar(150) NOT NULL,
  `cuit` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `es_Distribuidor` enum('Si','No') NOT NULL DEFAULT 'No',
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `localidad` varchar(100) NOT NULL,
  `observaciones_gral` text DEFAULT NULL,
  `obs_financieras` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cuit` (`cuit`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.compras
DROP TABLE IF EXISTS `compras`;
CREATE TABLE IF NOT EXISTS `compras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) NOT NULL,
  `estado` enum('CARGADA','CONFIRMADA') DEFAULT 'CONFIRMADA',
  PRIMARY KEY (`id`),
  KEY `proveedor_id` (`proveedor_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `compras_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  CONSTRAINT `compras_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.compras_detalle
DROP TABLE IF EXISTS `compras_detalle`;
CREATE TABLE IF NOT EXISTS `compras_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compra_id` int(11) NOT NULL,
  `materia_prima_id` int(11) NOT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `compra_id` (`compra_id`),
  KEY `materia_prima_id` (`materia_prima_id`),
  CONSTRAINT `compras_detalle_ibfk_1` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `compras_detalle_ibfk_2` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.conversiones
DROP TABLE IF EXISTS `conversiones`;
CREATE TABLE IF NOT EXISTS `conversiones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_base_id` int(11) NOT NULL,
  `producto_presentacion_id` int(11) NOT NULL,
  `factor` decimal(10,4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.cuentas_corriente_clientes
DROP TABLE IF EXISTS `cuentas_corriente_clientes`;
CREATE TABLE IF NOT EXISTS `cuentas_corriente_clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `tipo` enum('DEBITO','CREDITO') NOT NULL,
  `origen` varchar(50) NOT NULL,
  `referencia_id` int(11) NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `saldo` decimal(14,2) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.historial_cambio_precios
DROP TABLE IF EXISTS `historial_cambio_precios`;
CREATE TABLE IF NOT EXISTS `historial_cambio_precios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL DEFAULT 0,
  `precio` decimal(12,3) NOT NULL DEFAULT 0.000,
  `change_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_change` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.ingresos_mercaderia
DROP TABLE IF EXISTS `ingresos_mercaderia`;
CREATE TABLE IF NOT EXISTS `ingresos_mercaderia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_compra_id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `remito` varchar(20) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `ing_num_indicador` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proveedor_id` (`proveedor_id`,`remito`),
  KEY `orden_compra_id` (`orden_compra_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `ingresos_mercaderia_ibfk_1` FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`),
  CONSTRAINT `ingresos_mercaderia_ibfk_2` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  CONSTRAINT `ingresos_mercaderia_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.ingresos_mercaderia_detalle
DROP TABLE IF EXISTS `ingresos_mercaderia_detalle`;
CREATE TABLE IF NOT EXISTS `ingresos_mercaderia_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ingreso_id` int(11) NOT NULL,
  `materia_prima_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `oc_id` int(11) NOT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ingreso_id` (`ingreso_id`),
  KEY `materia_prima_id` (`materia_prima_id`),
  KEY `prodid_vs_idprod_FK3` (`producto_id`),
  CONSTRAINT `ingresos_mercaderia_detalle_ibfk_1` FOREIGN KEY (`ingreso_id`) REFERENCES `ingresos_mercaderia` (`id`),
  CONSTRAINT `ingresos_mercaderia_detalle_ibfk_2` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`),
  CONSTRAINT `prodid_vs_idprod_FK3` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.mails_log
DROP TABLE IF EXISTS `mails_log`;
CREATE TABLE IF NOT EXISTS `mails_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('REMITO','PAGO') NOT NULL,
  `referencia_id` int(11) NOT NULL,
  `email_destino` varchar(255) NOT NULL,
  `asunto` varchar(255) DEFAULT NULL,
  `enviado_at` datetime DEFAULT current_timestamp(),
  `estado` enum('ENVIADO','ERROR') NOT NULL,
  `error` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.materiaprima_codigos
DROP TABLE IF EXISTS `materiaprima_codigos`;
CREATE TABLE IF NOT EXISTS `materiaprima_codigos` (
  `id_mpcodigos` int(11) NOT NULL AUTO_INCREMENT,
  `materiaprima_id` int(11) NOT NULL,
  `codigo` varchar(100) NOT NULL,
  `tipo` enum('EAN','CODE39','CODE128','UPC','INTERNO') NOT NULL,
  PRIMARY KEY (`id_mpcodigos`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `FK1_MP_ID_VS_MP_TABLA` (`materiaprima_id`),
  CONSTRAINT `FK1_MP_ID_VS_MP_TABLA` FOREIGN KEY (`materiaprima_id`) REFERENCES `materias_primas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='manejo del/los barcode de materia prima';

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.materias_primas
DROP TABLE IF EXISTS `materias_primas`;
CREATE TABLE IF NOT EXISTS `materias_primas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `categoria` int(3) DEFAULT NULL,
  `id_unidadmedida` int(3) NOT NULL,
  `stock_actual` decimal(10,3) DEFAULT 0.000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `precio_actual` decimal(10,3) NOT NULL,
  `stock_minimo` decimal(10,2) DEFAULT 0.00,
  `stock_maximo` decimal(10,2) DEFAULT 0.00,
  `stock_critico` decimal(10,2) DEFAULT 0.00,
  `imagen` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `categoria_vs_cate_mpFK1` (`categoria`),
  CONSTRAINT `categoria_vs_cate_mpFK1` FOREIGN KEY (`categoria`) REFERENCES `categorias_mp_id` (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.monedas
DROP TABLE IF EXISTS `monedas`;
CREATE TABLE IF NOT EXISTS `monedas` (
  `id_monedas` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(20) NOT NULL,
  `logo` enum('$','U$D','€') NOT NULL DEFAULT '$',
  KEY `id_monedas` (`id_monedas`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.movimientos_stock
DROP TABLE IF EXISTS `movimientos_stock`;
CREATE TABLE IF NOT EXISTS `movimientos_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('ENTRADA','SALIDA','PRODUCIDO','RESERVA') NOT NULL,
  `origen` varchar(50) NOT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `materia_prima_id` int(11) DEFAULT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `motivo` text NOT NULL,
  `observaciones` text DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de referencia principal del stock, todo se refleja en base a lo que se vea en esta tabla, los otros stock que estan de forma individuales son no creibles';

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.notas_pedido
DROP TABLE IF EXISTS `notas_pedido`;
CREATE TABLE IF NOT EXISTS `notas_pedido` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `presupuesto_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `estado` enum('BORRADOR','APROBADA','ANULADA') DEFAULT 'BORRADOR',
  `remitido` enum('SinRemitir','RemitidoParcial','RemitidoCompleto') DEFAULT 'SinRemitir',
  `observaciones` text DEFAULT NULL,
  `motivo_anulacion` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `anulado_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `notas_pedido_ibfk_2` (`presupuesto_id`),
  CONSTRAINT `notas_pedido_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `notas_pedido_ibfk_2` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`),
  CONSTRAINT `notas_pedido_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.notas_pedido_detalle
DROP TABLE IF EXISTS `notas_pedido_detalle`;
CREATE TABLE IF NOT EXISTS `notas_pedido_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nota_pedido_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `nota_pedido_id` (`nota_pedido_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `notas_pedido_detalle_ibfk_1` FOREIGN KEY (`nota_pedido_id`) REFERENCES `notas_pedido` (`id`),
  CONSTRAINT `notas_pedido_detalle_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.numeradores
DROP TABLE IF EXISTS `numeradores`;
CREATE TABLE IF NOT EXISTS `numeradores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(30) DEFAULT NULL,
  `ultimo_numero` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tipo` (`tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.ordenes_compra
DROP TABLE IF EXISTS `ordenes_compra`;
CREATE TABLE IF NOT EXISTS `ordenes_compra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) DEFAULT 0,
  `usuario_id` int(11) NOT NULL,
  `estado` enum('PENDIENTE','APROBADA','RECIBIDA','PARCIAL','ANULADA') DEFAULT 'PENDIENTE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proveedor_id` (`proveedor_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `ordenes_compra_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  CONSTRAINT `ordenes_compra_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.ordenes_compra_detalle
DROP TABLE IF EXISTS `ordenes_compra_detalle`;
CREATE TABLE IF NOT EXISTS `ordenes_compra_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_compra_id` int(11) NOT NULL,
  `materia_prima_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  `precio_unitario` decimal(10,3) NOT NULL DEFAULT 0.000,
  `moneda` int(2) NOT NULL DEFAULT 1,
  `referencia_oc` enum('STOCKLEVEL','STOCK_OP') DEFAULT 'STOCKLEVEL',
  `referencia_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orden_compra_id` (`orden_compra_id`),
  KEY `moneda_id_fk3` (`moneda`),
  KEY `ordenes_compra_detalle_ibfk_2` (`materia_prima_id`),
  KEY `oc_producto_id_vs_idproductofk_4` (`producto_id`),
  CONSTRAINT `moneda_id_fk3` FOREIGN KEY (`moneda`) REFERENCES `monedas` (`id_monedas`),
  CONSTRAINT `oc_producto_id_vs_idproductofk_4` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `ordenes_compra_detalle_ibfk_1` FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`),
  CONSTRAINT `ordenes_compra_detalle_ibfk_2` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.ordenes_produccion
DROP TABLE IF EXISTS `ordenes_produccion`;
CREATE TABLE IF NOT EXISTS `ordenes_produccion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_entrega` datetime DEFAULT NULL,
  `estado` enum('PENDIENTE','EN_PRODUCCION','FINALIZADA','CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
  `observaciones` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `receta_id` int(11) NOT NULL,
  `finalizada_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `producto_id` (`producto_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `receta_prod_if_fk3` (`receta_id`),
  CONSTRAINT `ordenes_produccion_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `ordenes_produccion_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`),
  CONSTRAINT `receta_prod_if_fk3` FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.orden_produccion_detalle
DROP TABLE IF EXISTS `orden_produccion_detalle`;
CREATE TABLE IF NOT EXISTS `orden_produccion_detalle` (
  `id_tbl_ordendetalle` int(11) NOT NULL AUTO_INCREMENT,
  `orden_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `registred_at` datetime NOT NULL,
  `cantidad_producida` decimal(10,3) NOT NULL DEFAULT 0.000,
  `user_id` int(11) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `confirma_produccion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_tbl_ordendetalle`),
  KEY `orden_id_vs_idORden_FK1` (`orden_id`),
  KEY `producto_id_vsidProducto_FK2` (`producto_id`),
  KEY `user_id_vs_idUser_FK3` (`user_id`),
  CONSTRAINT `orden_id_vs_idORden_FK1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_produccion` (`id`),
  CONSTRAINT `producto_id_vsidProducto_FK2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `user_id_vs_idUser_FK3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.pagos
DROP TABLE IF EXISTS `pagos`;
CREATE TABLE IF NOT EXISTS `pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `medio_pago` varchar(50) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.presupuestos
DROP TABLE IF EXISTS `presupuestos`;
CREATE TABLE IF NOT EXISTS `presupuestos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `estado` enum('BORRADOR','APROBADO','CANCELADO','LIBRE','ASIGNADO','ANULADO') DEFAULT 'BORRADOR',
  `usuario_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pre_asign` enum('LIBRE','ASIGNADO','ANULADO') NOT NULL DEFAULT 'LIBRE',
  `observaciones` varchar(255) DEFAULT '" "',
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `presupuestos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `presupuestos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.presupuestos_detalle
DROP TABLE IF EXISTS `presupuestos_detalle`;
CREATE TABLE IF NOT EXISTS `presupuestos_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `presupuesto_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `presupuesto_id` (`presupuesto_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `presupuestos_detalle_ibfk_1` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `presupuestos_detalle_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.productos
DROP TABLE IF EXISTS `productos`;
CREATE TABLE IF NOT EXISTS `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tipo` enum('BASE','PRESENTACION') NOT NULL,
  `producto_base_id` int(11) DEFAULT NULL,
  `unidad_medida` int(11) NOT NULL DEFAULT 0,
  `stock` decimal(12,3) NOT NULL DEFAULT 0.000,
  `user_create` int(11) NOT NULL,
  `last_user_updated` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` decimal(10,2) DEFAULT 0.00,
  `stock_maximo` decimal(10,2) DEFAULT 0.00,
  `stock_critico` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.producto_codigos
DROP TABLE IF EXISTS `producto_codigos`;
CREATE TABLE IF NOT EXISTS `producto_codigos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `codigo` varchar(100) NOT NULL,
  `tipo` enum('EAN','CODE39','CODE128','UPC','interno') NOT NULL DEFAULT 'EAN',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `producto_codigos_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.proveedores
DROP TABLE IF EXISTS `proveedores`;
CREATE TABLE IF NOT EXISTS `proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `razon_social` varchar(150) NOT NULL,
  `cuit` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `contacto` varchar(100) NOT NULL,
  `rubro` enum('Mani','Envases','Etiquetas','Maquinas','Suplementos','Limpieza') NOT NULL DEFAULT 'Mani',
  `localidad` varchar(100) NOT NULL,
  `direccion` varchar(100) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cuit` (`cuit`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.recetas
DROP TABLE IF EXISTS `recetas`;
CREATE TABLE IF NOT EXISTS `recetas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL,
  `proceso_fabrica` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `producto_id` (`producto_id`,`nombre`) USING BTREE,
  CONSTRAINT `recetas_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.recetas_detalle
DROP TABLE IF EXISTS `recetas_detalle`;
CREATE TABLE IF NOT EXISTS `recetas_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receta_id` int(11) NOT NULL,
  `materia_prima_id` int(11) NOT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  `unidad` enum('UNIDAD','KILO','GR') DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receta_id_materia_prima_id` (`receta_id`,`materia_prima_id`),
  KEY `materia_prima_id` (`materia_prima_id`),
  CONSTRAINT `recetas_detalle_ibfk_1` FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recetas_detalle_ibfk_2` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.remitos_salida
DROP TABLE IF EXISTS `remitos_salida`;
CREATE TABLE IF NOT EXISTS `remitos_salida` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nota_pedido_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `estado` enum('CONFIRMADO') DEFAULT 'CONFIRMADO',
  `observaciones` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `numero` int(11) NOT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `pdf_hash` char(64) DEFAULT NULL,
  `firmado` tinyint(1) DEFAULT 0,
  `firmado_por` int(11) DEFAULT NULL,
  `firmado_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `nota_pedido_id` (`nota_pedido_id`),
  CONSTRAINT `remitos_salida_ibfk_1` FOREIGN KEY (`nota_pedido_id`) REFERENCES `notas_pedido` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=168 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.remitos_salida_detalle
DROP TABLE IF EXISTS `remitos_salida_detalle`;
CREATE TABLE IF NOT EXISTS `remitos_salida_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `remito_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `remito_id` (`remito_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `remitos_salida_detalle_ibfk_1` FOREIGN KEY (`remito_id`) REFERENCES `remitos_salida` (`id`),
  CONSTRAINT `remitos_salida_detalle_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.reservas_materia_prima
DROP TABLE IF EXISTS `reservas_materia_prima`;
CREATE TABLE IF NOT EXISTS `reservas_materia_prima` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_produccion_id` int(11) NOT NULL,
  `materia_prima_id` int(11) NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `estado` enum('RESERVADO','CONSUMIDO','LIBERADO') DEFAULT 'RESERVADO',
  `created_at` datetime DEFAULT current_timestamp(),
  `procesado_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orden_vs_tblordenid_FK1` (`orden_produccion_id`),
  KEY `materiprima_vs_tblmateria_FK2` (`materia_prima_id`),
  CONSTRAINT `materiprima_vs_tblmateria_FK2` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `orden_vs_tblordenid_FK1` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.stock_mp_transito
DROP TABLE IF EXISTS `stock_mp_transito`;
CREATE TABLE IF NOT EXISTS `stock_mp_transito` (
  `materia_prima_id` int(11) NOT NULL,
  `cantidad` decimal(12,3) NOT NULL DEFAULT 0.000,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`materia_prima_id`),
  CONSTRAINT `stock_mp_transito_ibfk_1` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.tbl_historico_reserva_mp
DROP TABLE IF EXISTS `tbl_historico_reserva_mp`;
CREATE TABLE IF NOT EXISTS `tbl_historico_reserva_mp` (
  `id` int(11) NOT NULL,
  `orden_produccion_id` int(11) NOT NULL,
  `materia_prima_id` int(11) NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `estado` enum('RESERVADO','CONSUMIDO','LIBERADO') DEFAULT 'CONSUMIDO',
  `created_at` datetime DEFAULT NULL,
  `procesado_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `orden_vs_tblordenid_FK12` (`orden_produccion_id`) USING BTREE,
  KEY `materiprima_vs_tblmateria_FK22` (`materia_prima_id`) USING BTREE,
  CONSTRAINT `materiprima_vs_tblmateria_FK22` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `orden_vs_tblordenid_FK12` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.unidad_medida
DROP TABLE IF EXISTS `unidad_medida`;
CREATE TABLE IF NOT EXISTS `unidad_medida` (
  `id_medida` int(3) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(20) NOT NULL DEFAULT '0',
  `detalle` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_medida`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla app.users
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email_verificado` tinyint(1) DEFAULT 0,
  `token_verificacion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para vista app.vststock_movstock_materiaprima
DROP VIEW IF EXISTS `vststock_movstock_materiaprima`;
-- Creando tabla temporal para superar errores de dependencia de VIEW
CREATE TABLE `vststock_movstock_materiaprima` (
	`materia_prima_id` INT(11) NULL,
	`nombre` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`stock` DECIMAL(34,3) NULL
);

-- Volcando estructura para vista app.vststock_movstock_producto
DROP VIEW IF EXISTS `vststock_movstock_producto`;
-- Creando tabla temporal para superar errores de dependencia de VIEW
CREATE TABLE `vststock_movstock_producto` (
	`producto_id` INT(11) NULL,
	`nombre` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
	`stock` DECIMAL(34,3) NULL
);

-- Volcando estructura para disparador app.hitorico_reserva_mp_trig
DROP TRIGGER IF EXISTS `hitorico_reserva_mp_trig`;
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER `hitorico_reserva_mp_trig` AFTER DELETE ON `reservas_materia_prima` FOR EACH ROW BEGIN
    INSERT INTO tbl_historico_reserva_mp (
        id,
        orden_produccion_id,
        materia_prima_id,
        cantidad,
        estado,
        created_at,
        procesado_at
    )
    VALUES (
        OLD.id,
        OLD.orden_produccion_id,
        OLD.materia_prima_id,
        OLD.cantidad,
        OLD.estado,
        OLD.created_at,
        OLD.procesado_at
    );
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Eliminando tabla temporal y crear estructura final de VIEW
DROP TABLE IF EXISTS `vststock_movstock_materiaprima`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vststock_movstock_materiaprima` AS -- vista para traer el stock de MP
SELECT msmp.materia_prima_id,mp.nombre,
COALESCE(SUM(
  CASE
      WHEN msmp.tipo IN ('ENTRADA','AJUSTE') THEN msmp.cantidad
      WHEN msmp.tipo = 'SALIDA' THEN - msmp.cantidad
      WHEN msmp.tipo = 'RESERVA' THEN + 0
  END
),0) AS stock
FROM movimientos_stock msmp
LEFT JOIN materias_primas mp ON mp.id= msmp.materia_prima_id
WHERE msmp.materia_prima_id IS NOT NULL
GROUP BY msmp.materia_prima_id 
;

-- Eliminando tabla temporal y crear estructura final de VIEW
DROP TABLE IF EXISTS `vststock_movstock_producto`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vststock_movstock_producto` AS -- vista para traer el stock de Prductos
SELECT msp.producto_id,pd.nombre,
COALESCE(SUM(
  CASE
      WHEN msp.tipo IN ('ENTRADA','AJUSTE') THEN msp.cantidad
      WHEN msp.tipo = 'SALIDA' THEN -msp.cantidad
      WHEN msp.tipo = 'RESERVA' THEN + 0
  END
),0) AS stock
FROM movimientos_stock msp
LEFT JOIN productos pd ON pd.id=msp.producto_id
WHERE producto_id IS NOT NULL
GROUP BY msp.producto_id 
;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
