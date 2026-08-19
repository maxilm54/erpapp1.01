-- =====================================================
-- Migración 010: Agregar cliente_nombre a cuentas_corriente_clientes
-- Permite guardar el nombre real del cliente en ctacte (útil para clientes ocasionales)
-- =====================================================
-- BEGIN;
-- COMMIT;
-- ROLLBACK;
ALTER TABLE `cuentas_corriente_clientes`
ADD COLUMN `cliente_nombre` varchar(150) DEFAULT NULL AFTER `cliente_id`;
INSERT INTO act_bd (id,descripcion) VALUES (10,'Agregar cliente_nombre a cuentas_corriente_clientes');
