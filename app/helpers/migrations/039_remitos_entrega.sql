-- 039: Confirmacion de entrega de remitos de salida
-- El remito descuenta stock y se emite al remitir, pero la entrega queda
-- "pendiente de confirmacion" hasta que alguien la confirma desde el listado
-- o el detalle. Una entrega pendiente bloquea la baja del producto asociado.
-- Los remitos que ya existian se consideran entregados (el documento se emite
-- en el momento de la entrega).

ALTER TABLE `remitos_salida`
  ADD COLUMN `entregado` tinyint(1) NOT NULL DEFAULT 0 AFTER `firmado_at`,
  ADD COLUMN `entregado_por` int(11) DEFAULT NULL AFTER `entregado`,
  ADD COLUMN `entregado_at` datetime DEFAULT NULL AFTER `entregado_por`,
  ADD KEY `idx_remitos_entregado` (`entregado`);

UPDATE `remitos_salida`
SET `entregado` = 1,
    `entregado_at` = COALESCE(`firmado_at`, `created_at`)
WHERE `entregado` = 0;

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (39, 'Confirmacion de entrega de remitos de salida');
