-- Migración 011: Agregar cliente_nombre a pagos
-- Permite guardar el nombre real del cliente en el pago (útil para clientes ocasionales)

ALTER TABLE `pagos`
ADD COLUMN `cliente_nombre` varchar(150) DEFAULT NULL AFTER `cliente_id`;
