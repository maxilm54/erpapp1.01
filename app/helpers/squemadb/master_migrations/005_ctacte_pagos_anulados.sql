-- 005 (master): Anulacion logica de pagos de CtaCte
-- Un pago anulado NO se borra: queda en el historial con activo = 0 y se
-- guardan la fecha/hora, el usuario y el motivo de la anulacion. El reparto
-- entre registros (ctacte_tenant_pago_aplicaciones) tambien se conserva como
-- historial, pero los saldos de los registros se revierten al anular.

ALTER TABLE `ctacte_tenant_pagos`
  ADD COLUMN IF NOT EXISTS `activo` tinyint(1) NOT NULL DEFAULT 1 AFTER `usuario_id`,
  ADD COLUMN IF NOT EXISTS `anulado_at` datetime DEFAULT NULL AFTER `activo`,
  ADD COLUMN IF NOT EXISTS `anulado_por` int(11) DEFAULT NULL AFTER `anulado_at`,
  ADD COLUMN IF NOT EXISTS `motivo_anulacion` varchar(500) DEFAULT NULL AFTER `anulado_por`;
