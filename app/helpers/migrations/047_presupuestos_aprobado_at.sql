-- 047: Fecha de aprobacion de presupuestos
-- Permite registrar cuando se aprobo un presupuesto y comparar con su
-- vencimiento para mostrar si se aprobo vencido. La columna vencimiento
-- ya existe en la tabla presupuestos.

ALTER TABLE `presupuestos`
  ADD COLUMN `aprobado_at` datetime DEFAULT NULL AFTER `vencimiento`;

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (47, 'Fecha de aprobacion de presupuestos y control de vencimiento');
