-- =====================================================
-- Migración 009: Cliente genérico OCASIONAL + nullable en cuit
-- Permite clientes ocasionales sin CUIT propio
-- =====================================================
--BEGIN;
-- COMMIT;
-- ROLLBACK;
-- Hacer cuit nullable (el genérico no tiene CUIT real)
ALTER TABLE `clientes` MODIFY COLUMN `cuit` varchar(20) DEFAULT NULL;

-- Insertar cliente genérico OCASIONAL si no existe
INSERT IGNORE INTO `clientes` (`id`, `razon_social`, `cuit`, `email`, `telefono`, `direccion`, `contacto`, `es_distribuidor`, `activo`, `localidad`)
VALUES (9999, 'CLIENTE OCASIONAL', NULL, 'soporte@dmtech.com.ar', NULL, NULL, NULL, 'No', 1, '-');
INSERT INTO act_bd (id,descripcion) VALUES (9,'Cliente genérico OCASIONAL + nullable en cuit');
