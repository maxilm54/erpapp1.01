-- 044: Nota de Pedido que se levanta en una receta para producir
-- Agrega el vinculo entre la Nota de Pedido y la Orden de Produccion generada
-- desde la receta de su producto. La columna permite trazabilidad y evita
-- duplicar la OP de una misma NP.

ALTER TABLE `ordenes_produccion`
  ADD COLUMN `nota_pedido_id` int(11) DEFAULT NULL AFTER `receta_id`,
  ADD UNIQUE KEY `ordenes_produccion_nota_unique` (`nota_pedido_id`),
  ADD CONSTRAINT `ordenes_produccion_nota_fk` FOREIGN KEY (`nota_pedido_id`) REFERENCES `notas_pedido` (`id`) ON DELETE SET NULL;

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (44, 'Vinculo Nota de Pedido - Orden de Produccion');
