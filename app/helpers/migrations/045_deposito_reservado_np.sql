-- 045: Deposito de productos reservados por Nota de Pedido
-- Los productos producidos por una OP vinculada a una NP se guardan en este
-- deposito especial. Tambien permite reservar productos para una NP, tenga o no OP.
-- El deposito se puede recrear automaticamente desde Deposito::ensureReservadoNp().

ALTER TABLE `depositos`
  ADD COLUMN `es_reservado_np` tinyint(1) NOT NULL DEFAULT 0 AFTER `es_sobrante`;

UPDATE `depositos`
SET `es_reservado_np` = 1
WHERE `nombre` = 'Deposito Productos Reservados (NP)'
  AND `es_reservado_np` = 0;

INSERT INTO `depositos` (`nombre`, `descripcion`, `es_reservado_np`, `activo`)
SELECT 'Deposito Productos Reservados (NP)', 'Productos reservados para Notas de Pedido', 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM depositos WHERE es_reservado_np = 1 LIMIT 1);

CREATE TABLE IF NOT EXISTS `reservas_producto_np` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nota_pedido_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `orden_produccion_id` int(11) DEFAULT NULL,
  `deposito_origen_id` int(11) DEFAULT NULL,
  `deposito_id` int(11) NOT NULL,
  `cantidad` decimal(10,3) NOT NULL,
  `cantidad_consumida` decimal(10,3) NOT NULL DEFAULT 0.000,
  `estado` enum('RESERVADO','CONSUMIDO','LIBERADO') NOT NULL DEFAULT 'RESERVADO',
  `origen` enum('PRODUCCION','MANUAL') NOT NULL DEFAULT 'MANUAL',
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `nota_pedido_id` (`nota_pedido_id`),
  KEY `producto_id` (`producto_id`),
  KEY `orden_produccion_id` (`orden_produccion_id`),
  KEY `deposito_id` (`deposito_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (45, 'Deposito de productos reservados por Nota de Pedido');
