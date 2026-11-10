-- 034: Fecha de entrega comprometida en presupuestos, notas de pedido y ordenes de compra
-- Campo OPCIONAL cargado desde los formularios de creacion/edicion. Si esta cargado,
-- el cron app/helpers/notificar_entregas.php avisa por email cuando la fecha se acerca
-- o ya paso (y el documento sigue sin cerrarse).
-- aviso_entrega_at marca el ultimo aviso enviado (una vez por dia, igual que cheques).

ALTER TABLE `presupuestos`
  ADD COLUMN `fecha_entrega` date DEFAULT NULL AFTER `observaciones`,
  ADD COLUMN `aviso_entrega_at` datetime DEFAULT NULL AFTER `fecha_entrega`;

ALTER TABLE `notas_pedido`
  ADD COLUMN `fecha_entrega` date DEFAULT NULL AFTER `observaciones`,
  ADD COLUMN `aviso_entrega_at` datetime DEFAULT NULL AFTER `fecha_entrega`;

ALTER TABLE `ordenes_compra`
  ADD COLUMN `fecha_entrega` date DEFAULT NULL AFTER `observaciones`,
  ADD COLUMN `aviso_entrega_at` datetime DEFAULT NULL AFTER `fecha_entrega`;

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (34, 'Fecha de entrega comprometida en presupuestos, notas de pedido y ordenes de compra');
