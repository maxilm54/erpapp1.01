-- =====================================================
-- Migration 021: Permitir producto_id NULL en remitos_salida_detalle
-- Para soportar items manuales (servicios) sin producto asociado
-- =====================================================
/*
-- Cambiar producto_id a nullable (si aún es NOT NULL)
ALTER TABLE `remitos_salida_detalle`
    MODIFY COLUMN `producto_id` int(11) DEFAULT NULL;

-- Eliminar FK anterior si existe para recrearla con ON DELETE SET NULL
SET @fk_name = (
    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_NAME = 'remitos_salida_detalle'
      AND COLUMN_NAME = 'producto_id'
      AND REFERENCED_TABLE_NAME = 'productos'
    LIMIT 1
);
SET @sql = IF(@fk_name IS NOT NULL,
    CONCAT('ALTER TABLE `remitos_salida_detalle` DROP FOREIGN KEY `', @fk_name, '`'),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Recrear la FK con ON DELETE SET NULL
ALTER TABLE `remitos_salida_detalle`
    ADD CONSTRAINT `rsd_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL;
*/
INSERT INTO act_bd (id, descripcion) VALUES (21, 'DISCONTINUADO, SE CORRIGE EN SHEMA. Permitir producto_id NULL en remitos_salida_detalle para items manuales (servicios)');
-- =====================================================
