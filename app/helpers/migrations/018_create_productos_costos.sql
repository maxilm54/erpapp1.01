-- Migration 018: Tabla de costos y precios de productos
-- Almacena precio de compra, costos fijos/variables y margen de ganancia por producto

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

ALTER TABLE `productos_costos`
  ADD CONSTRAINT `productos_costos_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE;

INSERT INTO act_bd (id, descripcion) VALUES (18, 'Tbl Prod costos para visualizar los margenes del precio de un producto');
