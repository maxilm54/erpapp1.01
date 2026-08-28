/*
  ============================================================
  SCHEMA PARA TENANT (copia limpia para nueva BD de cliente)
  ============================================================
  - Sin INSERTs de datos de prueba
  - Sin DELIMITER (compatible con PDO)
  - Tabla users incluida como stub para FK (auth vive en master)
  ============================================================
*/

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `act_bd`;
CREATE TABLE `act_bd` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `descripcion` VARCHAR(150) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `categorias_mp_id`;
CREATE TABLE IF NOT EXISTS `categorias_mp_id` (
  `id_categoria` int(3) NOT NULL AUTO_INCREMENT,
  `categoria_nombre` varchar(50) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `clientes`;
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `razon_social` varchar(150) NOT NULL,
  `cuit` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `es_distribuidor` enum('Si','No') NOT NULL DEFAULT 'No',
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `localidad` varchar(100) NOT NULL,
  `observaciones_gral` text DEFAULT NULL,
  `obs_financieras` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cuit` (`cuit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `compras`;
CREATE TABLE IF NOT EXISTS `compras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) NOT NULL,
  `estado` enum('CARGADA','CONFIRMADA') DEFAULT 'CONFIRMADA',
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `proveedor_id` (`proveedor_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `compras_detalle`;
CREATE TABLE IF NOT EXISTS `compras_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compra_id` int(11) NOT NULL,
  `materia_prima_id` int(11) NOT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  `precio_unitario` decimal(12,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`id`),
  KEY `compra_id` (`compra_id`),
  KEY `materia_prima_id` (`materia_prima_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `conversiones`;
CREATE TABLE IF NOT EXISTS `conversiones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_base_id` int(11) NOT NULL,
  `producto_presentacion_id` int(11) NOT NULL,
  `factor` decimal(10,4) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `producto_base_id` (`producto_base_id`),
  KEY `producto_presentacion_id` (`producto_presentacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cuentas_corriente_clientes`;
CREATE TABLE IF NOT EXISTS `cuentas_corriente_clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `cliente_nombre` varchar(150) DEFAULT NULL,
  `fecha` date NOT NULL,
  `tipo` enum('DEBITO','CREDITO') NOT NULL,
  `origen` varchar(50) NOT NULL,
  `referencia_id` int(11) NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `saldo` decimal(14,2) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  KEY `fecha` (`fecha`),
  KEY `cta_cte_usuario_fk` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `historial_cambio_precios`;
CREATE TABLE IF NOT EXISTS `historial_cambio_precios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL DEFAULT 0,
  `precio` decimal(12,3) NOT NULL DEFAULT 0.000,
  `change_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_change` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `producto_id` (`producto_id`),
  KEY `change_at` (`change_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ingresos_mercaderia`;
CREATE TABLE IF NOT EXISTS `ingresos_mercaderia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_compra_id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `remito` varchar(20) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `ing_num_indicador` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `proveedor_id_remito` (`proveedor_id`,`remito`),
  KEY `orden_compra_id` (`orden_compra_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  KEY `producto_id` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  PRIMARY KEY (`id`),
  KEY `referencia_id` (`referencia_id`),
  KEY `email_destino` (`email_destino`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `materiaprima_codigos`;
CREATE TABLE IF NOT EXISTS `materiaprima_codigos` (
  `id_mpcodigos` int(11) NOT NULL AUTO_INCREMENT,
  `materiaprima_id` int(11) NOT NULL,
  `codigo` varchar(100) NOT NULL,
  `tipo` enum('EAN','CODE39','CODE128','UPC','INTERNO') NOT NULL,
  PRIMARY KEY (`id_mpcodigos`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `FK1_MP_ID_VS_MP_TABLA` (`materiaprima_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `materias_primas`;
CREATE TABLE IF NOT EXISTS `materias_primas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `categoria` int(3) DEFAULT NULL,
  `id_unidadmedida` int(3) NOT NULL,
  `stock_actual` decimal(10,3) DEFAULT 0.000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `precio_actual` decimal(10,3) NOT NULL,
  `stock_minimo` decimal(10,2) DEFAULT 0.00,
  `stock_maximo` decimal(10,2) DEFAULT 0.00,
  `stock_critico` decimal(10,2) DEFAULT 0.00,
  `imagen` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `categoria_vs_cate_mpFK1` (`categoria`),
  KEY `id_unidadmedida` (`id_unidadmedida`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `monedas`;
CREATE TABLE IF NOT EXISTS `monedas` (
  `id_moned` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(20) NOT NULL,
  `simbolo` varchar(10) NOT NULL DEFAULT '$',
  PRIMARY KEY (`id_moned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `movimientos_stock`;
CREATE TABLE IF NOT EXISTS `movimientos_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('ENTRADA','SALIDA','AJUSTE','PRODUCIDO','RESERVA','CONSUMO') NOT NULL,
  `origen` varchar(50) NOT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `materia_prima_id` int(11) DEFAULT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `motivo` text NOT NULL,
  `observaciones` text DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `producto_id` (`producto_id`),
  KEY `materia_prima_id` (`materia_prima_id`),
  KEY `created_at` (`created_at`),
  KEY `tipo` (`tipo`),
  KEY `movimientos_stock_usuario_fk` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `anulado_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `presupuesto_id` (`presupuesto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notas_pedido_detalle`;
CREATE TABLE IF NOT EXISTS `notas_pedido_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nota_pedido_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  `precio` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `nota_pedido_id` (`nota_pedido_id`),
  KEY `producto_id` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `numeradores`;
CREATE TABLE IF NOT EXISTS `numeradores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(30) DEFAULT NULL,
  `ultimo_numero` int(11) NOT NULL,
  `incremento` int(11) NOT NULL DEFAULT 1,
  `prefijo` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ordenes_compra`;
CREATE TABLE IF NOT EXISTS `ordenes_compra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `estado` enum('PENDIENTE','APROBADA','RECIBIDA','PARCIAL','ANULADA') DEFAULT 'PENDIENTE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proveedor_id` (`proveedor_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ordenes_compra_detalle`;
CREATE TABLE IF NOT EXISTS `ordenes_compra_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_compra_id` int(11) NOT NULL,
  `materia_prima_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  `precio_unitario` decimal(12,3) NOT NULL DEFAULT 0.000,
  `moneda` int(11) NOT NULL DEFAULT 1,
  `referencia_oc` enum('STOCKLEVEL','STOCK_OP') DEFAULT 'STOCKLEVEL',
  `referencia_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orden_compra_id` (`orden_compra_id`),
  KEY `moneda` (`moneda`),
  KEY `materia_prima_id` (`materia_prima_id`),
  KEY `producto_id` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `receta_id` int(11) NOT NULL,
  `finalizada_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `producto_id` (`producto_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `receta_id` (`receta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  KEY `orden_id` (`orden_id`),
  KEY `producto_id` (`producto_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `pagos`;
CREATE TABLE IF NOT EXISTS `pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `cliente_nombre` varchar(150) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `medio_pago` varchar(50) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `caja_banco_id` int(11) DEFAULT NULL,
  `anulado` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  KEY `pagos_usuario_fk` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `presupuestos`;
CREATE TABLE IF NOT EXISTS `presupuestos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `estado` enum('BORRADOR','APROBADO','CANCELADO','LIBRE','ASIGNADO','ANULADO') DEFAULT 'BORRADOR',
  `usuario_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `pre_asign` enum('LIBRE','ASIGNADO','ANULADO') NOT NULL DEFAULT 'LIBRE',
  `observaciones` text DEFAULT NULL,
  `vencimiento` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `presupuestos_detalle`;
CREATE TABLE IF NOT EXISTS `presupuestos_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `presupuesto_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  `precio` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `presupuesto_id` (`presupuesto_id`),
  KEY `producto_id` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `productos`;
CREATE TABLE IF NOT EXISTS `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio_venta` decimal(12,2) NOT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
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
  UNIQUE KEY `sku` (`sku`),
  KEY `producto_base_id` (`producto_base_id`),
  KEY `unidad_medida` (`unidad_medida`),
  KEY `user_create` (`user_create`),
  KEY `last_user_updated` (`last_user_updated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `producto_codigos`;
CREATE TABLE IF NOT EXISTS `producto_codigos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `codigo` varchar(100) NOT NULL,
  `tipo` enum('EAN','CODE39','CODE128','UPC','INTERNO') NOT NULL DEFAULT 'EAN',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `producto_id` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `productos_costos`;
CREATE TABLE IF NOT EXISTS `productos_costos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `precio_compra` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_fijo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_variable_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `margen_ganancia_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `producto_id` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `proveedores`;
CREATE TABLE IF NOT EXISTS `proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `razon_social` varchar(150) NOT NULL,
  `cuit` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `contacto` varchar(100) NOT NULL,
  `rubro` varchar(50) NOT NULL DEFAULT 'Mani',
  `localidad` varchar(100) NOT NULL,
  `direccion` varchar(100) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cuit` (`cuit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `recetas`;
CREATE TABLE IF NOT EXISTS `recetas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `user_id` int(11) NOT NULL,
  `proceso_fabrica` text DEFAULT NULL,
  `activa` int(2) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `producto_id_nombre` (`producto_id`,`nombre`),
  KEY `producto_id` (`producto_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `recetas_detalle`;
CREATE TABLE IF NOT EXISTS `recetas_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receta_id` int(11) NOT NULL,
  `materia_prima_id` int(11) NOT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  `unidad` int(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receta_id_materia_prima_id` (`receta_id`,`materia_prima_id`),
  KEY `materia_prima_id` (`materia_prima_id`),
  KEY `unidad` (`unidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `remitos_salida`;
CREATE TABLE IF NOT EXISTS `remitos_salida` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nota_pedido_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `estado` enum('CONFIRMADO') DEFAULT 'CONFIRMADO',
  `observaciones` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `numero` int(11) NOT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `pdf_hash` char(64) DEFAULT NULL,
  `firmado` tinyint(1) DEFAULT 0,
  `firmado_por` int(11) DEFAULT NULL,
  `firmado_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `nota_pedido_id` (`nota_pedido_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `remitos_salida_detalle`;
CREATE TABLE IF NOT EXISTS `remitos_salida_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `remito_id` int(11) NOT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `remito_id` (`remito_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `rsd_remito_fk` FOREIGN KEY (`remito_id`) REFERENCES `remitos_salida` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rsd_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `reservas_materia_prima`;
CREATE TABLE IF NOT EXISTS `reservas_materia_prima` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_produccion_id` int(11) NOT NULL,
  `materia_prima_id` int(11) NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `cantidad_asignada` decimal(12,3) NOT NULL,
  `estado` enum('RESERVADO','CONSUMIDO','LIBERADO') DEFAULT 'RESERVADO',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `procesado_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orden_produccion_id` (`orden_produccion_id`),
  KEY `materia_prima_id` (`materia_prima_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stock_mp_transito`;
CREATE TABLE IF NOT EXISTS `stock_mp_transito` (
  `materia_prima_id` int(11) NOT NULL,
  `cantidad` decimal(12,3) NOT NULL DEFAULT 0.000,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`materia_prima_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tbl_historico_reserva_mp`;
CREATE TABLE IF NOT EXISTS `tbl_historico_reserva_mp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_produccion_id` int(11) NOT NULL,
  `materia_prima_id` int(11) NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `estado` enum('RESERVADO','CONSUMIDO','LIBERADO') DEFAULT 'CONSUMIDO',
  `created_at` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `procesado_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orden_produccion_id` (`orden_produccion_id`),
  KEY `materia_prima_id` (`materia_prima_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `unidad_medida`;
CREATE TABLE IF NOT EXISTS `unidad_medida` (
  `id_medida` int(3) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(20) NOT NULL DEFAULT '0',
  `detalle` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_medida`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla users stub (auth vive en master, esto es solo para FK)
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email_verificado` tinyint(1) DEFAULT 0,
  `token_verificacion` varchar(255) DEFAULT NULL,
  `rol` enum('SUPERADMIN','ADMIN','USUARIO','VISITOR','GERENTE_FINANCIERO') NOT NULL DEFAULT 'USUARIO',
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Foreign keys
ALTER TABLE `compras` ADD CONSTRAINT `compras_proveedor_fk` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`);
ALTER TABLE `compras` ADD CONSTRAINT `compras_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`);
ALTER TABLE `compras_detalle` ADD CONSTRAINT `compras_detalle_compra_fk` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE;
ALTER TABLE `compras_detalle` ADD CONSTRAINT `compras_detalle_mp_fk` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`);
ALTER TABLE `conversiones` ADD CONSTRAINT `conversiones_base_fk` FOREIGN KEY (`producto_base_id`) REFERENCES `productos` (`id`);
ALTER TABLE `conversiones` ADD CONSTRAINT `conversiones_presentacion_fk` FOREIGN KEY (`producto_presentacion_id`) REFERENCES `productos` (`id`);
ALTER TABLE `cuentas_corriente_clientes` ADD CONSTRAINT `cta_cte_cliente_fk` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`);
ALTER TABLE `cuentas_corriente_clientes` ADD CONSTRAINT `cta_cte_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`);
ALTER TABLE `historial_cambio_precios` ADD CONSTRAINT `historial_precios_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);
ALTER TABLE `ingresos_mercaderia` ADD CONSTRAINT `ingresos_mercaderia_oc_fk` FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`);
ALTER TABLE `ingresos_mercaderia` ADD CONSTRAINT `ingresos_mercaderia_proveedor_fk` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`);
ALTER TABLE `ingresos_mercaderia` ADD CONSTRAINT `ingresos_mercaderia_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`);
ALTER TABLE `ingresos_mercaderia_detalle` ADD CONSTRAINT `ingresos_detalle_ingreso_fk` FOREIGN KEY (`ingreso_id`) REFERENCES `ingresos_mercaderia` (`id`);
ALTER TABLE `ingresos_mercaderia_detalle` ADD CONSTRAINT `ingresos_detalle_mp_fk` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`);
ALTER TABLE `ingresos_mercaderia_detalle` ADD CONSTRAINT `ingresos_detalle_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);
ALTER TABLE `materiaprima_codigos` ADD CONSTRAINT `FK1_MP_ID_VS_MP_TABLA` FOREIGN KEY (`materiaprima_id`) REFERENCES `materias_primas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `materias_primas` ADD CONSTRAINT `categorias_mp_fk` FOREIGN KEY (`categoria`) REFERENCES `categorias_mp_id` (`id_categoria`);
ALTER TABLE `materias_primas` ADD CONSTRAINT `materias_primas_um_fk` FOREIGN KEY (`id_unidadmedida`) REFERENCES `unidad_medida` (`id_medida`);
ALTER TABLE `movimientos_stock` ADD CONSTRAINT `movimientos_stock_materia_fk` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`);
ALTER TABLE `movimientos_stock` ADD CONSTRAINT `movimientos_stock_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);
ALTER TABLE `movimientos_stock` ADD CONSTRAINT `movimientos_stock_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`);
ALTER TABLE `notas_pedido` ADD CONSTRAINT `notas_pedido_cliente_fk` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`);
ALTER TABLE `notas_pedido` ADD CONSTRAINT `notas_pedido_presupuesto_fk` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`);
ALTER TABLE `notas_pedido` ADD CONSTRAINT `notas_pedido_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`);
ALTER TABLE `notas_pedido_detalle` ADD CONSTRAINT `notas_pedido_detalle_nota_fk` FOREIGN KEY (`nota_pedido_id`) REFERENCES `notas_pedido` (`id`);
ALTER TABLE `notas_pedido_detalle` ADD CONSTRAINT `notas_pedido_detalle_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);
ALTER TABLE `ordenes_compra` ADD CONSTRAINT `ordenes_compra_proveedor_fk` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`);
ALTER TABLE `ordenes_compra` ADD CONSTRAINT `ordenes_compra_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`);
ALTER TABLE `ordenes_compra_detalle` ADD CONSTRAINT `orden_compra_detalle_moneda_fk` FOREIGN KEY (`moneda`) REFERENCES `monedas` (`id_moned`);
ALTER TABLE `ordenes_compra_detalle` ADD CONSTRAINT `orden_compra_detalle_mp_fk` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`);
ALTER TABLE `ordenes_compra_detalle` ADD CONSTRAINT `orden_compra_detalle_oc_fk` FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`);
ALTER TABLE `ordenes_compra_detalle` ADD CONSTRAINT `orden_compra_detalle_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);
ALTER TABLE `ordenes_produccion` ADD CONSTRAINT `ordenes_produccion_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);
ALTER TABLE `ordenes_produccion` ADD CONSTRAINT `ordenes_produccion_receta_fk` FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`id`);
ALTER TABLE `ordenes_produccion` ADD CONSTRAINT `ordenes_produccion_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`);
ALTER TABLE `orden_produccion_detalle` ADD CONSTRAINT `orden_detalle_orden_fk` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_produccion` (`id`);
ALTER TABLE `orden_produccion_detalle` ADD CONSTRAINT `orden_detalle_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);
ALTER TABLE `orden_produccion_detalle` ADD CONSTRAINT `orden_detalle_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `pagos` ADD CONSTRAINT `pagos_cliente_fk` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`);
ALTER TABLE `pagos` ADD CONSTRAINT `pagos_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`);
ALTER TABLE `presupuestos` ADD CONSTRAINT `presupuestos_cliente_fk` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`);
ALTER TABLE `presupuestos` ADD CONSTRAINT `presupuestos_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`);
ALTER TABLE `presupuestos_detalle` ADD CONSTRAINT `presupuestos_detalle_presupuesto_fk` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`) ON DELETE CASCADE;
ALTER TABLE `presupuestos_detalle` ADD CONSTRAINT `presupuestos_detalle_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);
ALTER TABLE `productos` ADD CONSTRAINT `productos_producto_base_fk` FOREIGN KEY (`producto_base_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL;
ALTER TABLE `productos` ADD CONSTRAINT `productos_unidad_medida_fk` FOREIGN KEY (`unidad_medida`) REFERENCES `unidad_medida` (`id_medida`);
ALTER TABLE `productos` ADD CONSTRAINT `productos_user_create_fk` FOREIGN KEY (`user_create`) REFERENCES `users` (`id`);
ALTER TABLE `productos` ADD CONSTRAINT `productos_user_updated_fk` FOREIGN KEY (`last_user_updated`) REFERENCES `users` (`id`);
ALTER TABLE `producto_codigos` ADD CONSTRAINT `producto_codigos_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE;
ALTER TABLE `productos_costos` ADD CONSTRAINT `productos_costos_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE;
ALTER TABLE `recetas` ADD CONSTRAINT `recetas_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE;
ALTER TABLE `recetas` ADD CONSTRAINT `recetas_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `recetas_detalle` ADD CONSTRAINT `recetas_detalle_mp_fk` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`);
ALTER TABLE `recetas_detalle` ADD CONSTRAINT `recetas_detalle_receta_fk` FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`id`) ON DELETE CASCADE;
ALTER TABLE `recetas_detalle` ADD CONSTRAINT `recetas_detalle_um_fk` FOREIGN KEY (`unidad`) REFERENCES `unidad_medida` (`id_medida`);
ALTER TABLE `remitos_salida` ADD CONSTRAINT `remitos_salida_nota_fk` FOREIGN KEY (`nota_pedido_id`) REFERENCES `notas_pedido` (`id`);
ALTER TABLE `remitos_salida_detalle` ADD CONSTRAINT `remitos_salida_detalle_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL;
ALTER TABLE `remitos_salida_detalle` ADD CONSTRAINT `remitos_salida_detalle_remito_fk` FOREIGN KEY (`remito_id`) REFERENCES `remitos_salida` (`id`);
ALTER TABLE `reservas_materia_prima` ADD CONSTRAINT `reservas_mp_materia_fk` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`) ON UPDATE CASCADE;
ALTER TABLE `reservas_materia_prima` ADD CONSTRAINT `reservas_mp_orden_fk` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON UPDATE CASCADE;
ALTER TABLE `stock_mp_transito` ADD CONSTRAINT `stock_mp_transito_ibfk_1` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`);
ALTER TABLE `tbl_historico_reserva_mp` ADD CONSTRAINT `historico_mp_materia_fk` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`) ON UPDATE CASCADE;
ALTER TABLE `tbl_historico_reserva_mp` ADD CONSTRAINT `historico_mp_orden_fk` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON UPDATE CASCADE;
ALTER TABLE `gastos` ADD CONSTRAINT `gastos_orden_compra_fk` FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`) ON DELETE SET NULL;
ALTER TABLE `gastos` ADD CONSTRAINT `gastos_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

-- Trigger (PDO-safe, sin DELIMITER)
DROP TRIGGER IF EXISTS `historico_reserva_mp_trig`;
CREATE TRIGGER `historico_reserva_mp_trig` AFTER DELETE ON `reservas_materia_prima` FOR EACH ROW
BEGIN
    INSERT INTO tbl_historico_reserva_mp (
        orden_produccion_id,
        materia_prima_id,
        cantidad,
        estado,
        created_at,
        procesado_at
    )
    VALUES (
        OLD.orden_produccion_id,
        OLD.materia_prima_id,
        OLD.cantidad,
        OLD.estado,
        OLD.created_at,
        OLD.procesado_at
    );
END;

-- Vistas
CREATE OR REPLACE VIEW `vststock_movstock_materiaprima` AS
SELECT msmp.materia_prima_id, mp.nombre,
COALESCE(SUM(
  CASE
      WHEN msmp.tipo IN ('ENTRADA','AJUSTE') THEN msmp.cantidad
      WHEN msmp.tipo = 'SALIDA' THEN -msmp.cantidad
      WHEN msmp.tipo = 'RESERVA' THEN 0
  END
),0) AS stock
FROM movimientos_stock msmp
LEFT JOIN materias_primas mp ON mp.id = msmp.materia_prima_id
WHERE msmp.materia_prima_id IS NOT NULL
GROUP BY msmp.materia_prima_id;

CREATE OR REPLACE VIEW `vststock_movstock_producto` AS
SELECT msp.producto_id, pd.nombre,
COALESCE(SUM(
  CASE
      WHEN msp.tipo IN ('ENTRADA','AJUSTE') THEN msp.cantidad
      WHEN msp.tipo = 'SALIDA' THEN -msp.cantidad
      WHEN msp.tipo = 'RESERVA' THEN 0
  END
),0) AS stock
FROM movimientos_stock msp
LEFT JOIN productos pd ON pd.id = msp.producto_id
WHERE msp.producto_id IS NOT NULL
GROUP BY msp.producto_id;

-- migration 000 8/7/26

-- =====================================================
-- Agregado de datos inciales, tablas moneda, unidad_medida y categorias_mp_id
-- =====================================================

INSERT INTO `monedas` (`id_moned`, `nombre`,`simbolo`) VALUES (4, 'Peso Argentino','$');
INSERT INTO `monedas` (`id_moned`, `nombre`,`simbolo`) VALUES (5, 'Dolar', 'U$D');
INSERT INTO `monedas` (`id_moned`, `nombre`,`simbolo`) VALUES (6, 'Euro', '€');

INSERT INTO `categorias_mp_id` (`categoria_nombre`) VALUES ('Alimenticia');
INSERT INTO `categorias_mp_id` (`categoria_nombre`) VALUES ('Metalmecanica');
INSERT INTO `categorias_mp_id` (`categoria_nombre`) VALUES ('Agroindustria');
INSERT INTO `categorias_mp_id` (`categoria_nombre`) VALUES ('Ferreteria');

INSERT INTO `unidad_medida` (`nombre`, `detalle`) VALUES ('Unidad', 'Producto que se maneja en unodad sin decimales');
INSERT INTO `unidad_medida` (`nombre`, `detalle`) VALUES ('Kg', 'Producto que se maneja en kilos, con decimales');
INSERT INTO `unidad_medida` (`nombre`, `detalle`) VALUES ('L', 'Producto que se maneja en Litros, con decimales');
INSERT INTO `unidad_medida` (`nombre`, `detalle`) VALUES ('M', 'Producto que se maneja en Metros, con decimales');

INSERT IGNORE INTO `clientes` (`id`, `razon_social`, `cuit`, `email`, `telefono`, `direccion`, `contacto`, `es_distribuidor`, `activo`, `localidad`)
VALUES (9999, 'CLIENTE OCASIONAL', NULL, 'soporte@dmtech.com.ar', NULL, NULL, NULL, 'No', 1, '-');

INSERT INTO act_bd (id,descripcion) VALUES (1,'Datos Predefinidos');

-- =====================================================
-- FIN Agregado de datos
-- =====================================================