-- 022: Productos libres en Presupuestos y Notas de Pedido
-- Permite agregar lineas con un producto que NO existe en la base de productos.
-- En ese caso producto_id queda NULL y se guarda el nombre en `descripcion`.
-- El usuario puede, opcionalmente, convertir la linea libre en un producto real
-- (boton "Agregar a Productos" en la vista del documento).

ALTER TABLE `presupuestos_detalle`
  ADD COLUMN `descripcion` VARCHAR(255) DEFAULT NULL AFTER `producto_id`;

ALTER TABLE `presupuestos_detalle`
  MODIFY COLUMN `producto_id` INT(11) DEFAULT NULL;

ALTER TABLE `notas_pedido_detalle`
  ADD COLUMN `descripcion` VARCHAR(255) DEFAULT NULL AFTER `producto_id`;

ALTER TABLE `notas_pedido_detalle`
  MODIFY COLUMN `producto_id` INT(11) DEFAULT NULL;

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (22, 'Productos libres (sin producto_id) en presupuestos y notas de pedido');
