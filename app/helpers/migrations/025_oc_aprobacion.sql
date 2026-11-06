-- 025: Registro de aprobacion de ordenes de compra
-- La tabla solo tenia created_at y updated_at, y updated_at se pisa con cualquier
-- update. Queda quien aprobo y cuando, escrito por OrdenCompra::aprobar().

ALTER TABLE `ordenes_compra`
  ADD COLUMN `aprobada_at` datetime DEFAULT NULL AFTER `estado`,
  ADD COLUMN `aprobado_por` int(11) DEFAULT NULL AFTER `aprobada_at`,
  ADD KEY `idx_oc_aprobado_por` (`aprobado_por`);

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (25, 'OC: registro de aprobacion (aprobada_at, aprobado_por)');
