-- =====================================================
-- Migración 005: Agregar caja_banco_id a gastos
-- =====================================================
-- BEGIN;
INSERT INTO act_bd (id,descripcion) VALUES (5,'Agregar caja_banco_id a gastos');
-- COMMIT;
-- ROLLBACK;
-- -----------------------------------------------------
-- Agregar columna de caja/banco para rastrear de dónde se paga
-- -----------------------------------------------------
ALTER TABLE `gastos`
  ADD COLUMN `caja_banco_id` int(11) DEFAULT NULL AFTER `monto_impuesto`;

ALTER TABLE `gastos`
  ADD CONSTRAINT `gastos_caja_banco_fk` FOREIGN KEY (`caja_banco_id`) REFERENCES `cajas_bancos` (`id`) ON DELETE SET NULL;
