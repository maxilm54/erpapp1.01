-- Migración 009: Cliente genérico OCASIONAL + nullable en cuit
-- Permite clientes ocasionales sin CUIT propio

-- Hacer cuit nullable (el genérico no tiene CUIT real)
ALTER TABLE `clientes` MODIFY COLUMN `cuit` varchar(20) DEFAULT NULL;

-- Insertar cliente genérico OCASIONAL si no existe
INSERT IGNORE INTO `clientes` (`id`, `razon_social`, `cuit`, `email`, `telefono`, `direccion`, `contacto`, `es_distribuidor`, `activo`, `localidad`)
VALUES (9999, 'CLIENTE OCASIONAL', NULL, 'contacto@alimentostriba.com.ar', NULL, NULL, NULL, 'No', 1, '-');
