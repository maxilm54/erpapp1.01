-- =====================================================
-- Migración 006: Agregar caja_banco_id y anulado a pagos
-- caja_banco_id: referencia a la caja/banco donde se registró el ingreso
-- anulado: flag para marcar pagos anulados (soft delete)
-- =====================================================
-- BEGIN;
-- COMMIT;
-- ROLLBACK;
ALTER TABLE `pagos`
ADD COLUMN `caja_banco_id` int(11) DEFAULT NULL AFTER `pdf_path`,
ADD COLUMN `anulado` tinyint(1) NOT NULL DEFAULT 0 AFTER `caja_banco_id`;

-- Actualizo registro de migracion de base de datos
INSERT INTO act_bd (id,descripcion) VALUES (6,'Agregar caja_banco_id y anulado a tbl pagos');

-- =====================================================
