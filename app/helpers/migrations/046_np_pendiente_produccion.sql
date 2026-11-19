-- 046: Notas de Pedido derivadas a produccion
-- Al aprobar una NP se puede elegir el camino normal de remitado o dejarla
-- pendiente para producir desde una receta.

ALTER TABLE `notas_pedido`
  ADD COLUMN `produccion_pendiente` tinyint(1) NOT NULL DEFAULT 0 AFTER `remitido`,
  ADD KEY `produccion_pendiente` (`produccion_pendiente`, `estado`);

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (46, 'Notas de Pedido pendientes de producir');
