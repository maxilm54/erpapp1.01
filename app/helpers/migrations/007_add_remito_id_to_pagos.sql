-- =====================================================
-- Migración 007: Agregar remito_id a pagos para trazabilidad
-- =====================================================
-- Permite vincular cada cobro con el remito de venta que está pagando
-- BEGIN;
-- COMMIT;
-- ROLLBACK;
ALTER TABLE `pagos` 
ADD COLUMN `remito_id` int(11) DEFAULT NULL AFTER `caja_banco_id`;

ALTER TABLE `pagos`
ADD KEY `pagos_remito_fk` (`remito_id`);
-- Actualizo registro de migracion de base de datos
INSERT INTO act_bd (id,descripcion) VALUES (7,'Agregar remito_id a tbl pagos y su key, para trazabilidad');
-- =====================================================