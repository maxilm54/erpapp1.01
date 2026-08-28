-- =====================================================
-- Migración 012: Agregar número de transacción a conciliaciones
-- =====================================================
-- Campo para almacenar el número de transacción del banco
-- Permite rastrear a qué movimiento del extracto corresponde cada registro
-- BEGIN;
-- COMMIT;
-- ROLLBACK;
/*
ALTER TABLE `conciliaciones_detalle` 
ADD COLUMN `numero_transaccion` varchar(100) DEFAULT NULL AFTER `conciliado`;
INSERT INTO act_bd (id,descripcion) VALUES (12,'Se agrega en la migracion 003,Agregar número de transacción a conciliaciones');
*/
