-- 030: Adjuntos (comprobantes) en Ordenes de Compra
-- Misma mecanica de PR/NP: panel de adjuntos en la OC, opcional
-- (si no se adjunta nada, el flujo sigue normal). Config propia
-- usar_adjuntos_compra para activarlo/desactivarlo por empresa.

ALTER TABLE `documentos_adjuntos`
  MODIFY COLUMN `modulo` ENUM('PRESUPUESTO','NOTA_PEDIDO','REMITO_SALIDA','PRODUCCION','ORDEN_COMPRA') NOT NULL;

INSERT IGNORE INTO configuraciones (clave, valor) VALUES ('usar_adjuntos_compra', '0');

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (30, 'Adjuntos en Ordenes de Compra + config usar_adjuntos_compra');
