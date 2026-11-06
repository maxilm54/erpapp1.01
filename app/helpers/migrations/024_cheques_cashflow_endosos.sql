-- 024: Cheques: cartera como activo, acreditacion bancaria y endosos
-- Un cheque en cartera NO suma a la disponibilidad de caja/banco. Se acredita
-- recien cuando el usuario lo marca como COBRADO. Tambien puede ENDOSARSE a un
-- proveedor para cancelar deuda sin pasar por el banco.
-- Se agrega la cuenta contable 1350 (Cheques en cartera) y el tipo ENDOSO en
-- la cuenta corriente de la empresa.

ALTER TABLE `cheques`
  MODIFY COLUMN `estado` ENUM('EN_CARTERA','DEPOSITADO','COBRADO','ENDOSADO','RECHAZADO','ANULADO') NOT NULL DEFAULT 'EN_CARTERA',
  ADD COLUMN `proveedor_id` int(11) DEFAULT NULL AFTER `cliente_nombre`,
  ADD COLUMN `fecha_acreditacion` date DEFAULT NULL AFTER `fecha_vencimiento`,
  ADD COLUMN `endosado_at` datetime DEFAULT NULL AFTER `fecha_acreditacion`,
  ADD COLUMN `movimiento_caja_id` int(11) DEFAULT NULL AFTER `caja_banco_id`,
  ADD COLUMN `cuenta_corriente_empresa_id` int(11) DEFAULT NULL AFTER `movimiento_caja_id`,
  ADD COLUMN `asiento_cartera_id` int(11) DEFAULT NULL AFTER `cuenta_corriente_empresa_id`,
  ADD COLUMN `asiento_cobro_id` int(11) DEFAULT NULL AFTER `asiento_cartera_id`,
  ADD COLUMN `asiento_endoso_id` int(11) DEFAULT NULL AFTER `asiento_cobro_id`,
  ADD KEY `idx_cheque_proveedor` (`proveedor_id`),
  ADD KEY `idx_cheque_movimiento` (`movimiento_caja_id`),
  ADD KEY `idx_cheque_cce` (`cuenta_corriente_empresa_id`);

ALTER TABLE `cheques`
  ADD CONSTRAINT `fk_cheque_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cheque_movimiento` FOREIGN KEY (`movimiento_caja_id`) REFERENCES `movimientos_caja` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cheque_cce` FOREIGN KEY (`cuenta_corriente_empresa_id`) REFERENCES `cuentas_corrientes_empresa` (`id`) ON DELETE SET NULL;

ALTER TABLE `cuentas_corrientes_empresa`
  MODIFY COLUMN `tipo` ENUM('INGRESO','GASTO','ENDOSO') NOT NULL;

INSERT IGNORE INTO `cuentas_contables` (`codigo`, `nombre`, `tipo`, `padre_id`, `nivel`, `acepta_movimiento`)
SELECT '1350', 'Cheques en cartera', 'ACTIVO', id, 2, 1
FROM `cuentas_contables`
WHERE `codigo` = '1000';

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (24, 'Cheques: cartera como activo, acreditacion bancaria, endosos a proveedores y cuenta contable 1350');
