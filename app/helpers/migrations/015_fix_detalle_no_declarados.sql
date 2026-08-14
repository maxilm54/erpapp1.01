-- =====================================================
-- Migración 015: Fix detalle movimientos no declarados
-- Agregar columna materia_prima_id y hacer producto_id nullable
-- =====================================================
INSERT INTO act_bd (id, descripcion) VALUES (15, 'Fix detalle movimientos no declarados - agregar materia_prima_id');

-- Hacer producto_id nullable (era NOT NULL con FK estricta)
ALTER TABLE `movimientos_no_declarados_detalle`
    DROP FOREIGN KEY `mndd_producto_fk`;

ALTER TABLE `movimientos_no_declarados_detalle`
    MODIFY COLUMN `producto_id` int(11) DEFAULT NULL;

ALTER TABLE `movimientos_no_declarados_detalle`
    ADD CONSTRAINT `mndd_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL;

-- Agregar columna materia_prima_id
ALTER TABLE `movimientos_no_declarados_detalle`
    ADD COLUMN `materia_prima_id` int(11) DEFAULT NULL AFTER `producto_id`,
    ADD KEY `materia_prima_id` (`materia_prima_id`),
    ADD CONSTRAINT `mndd_mp_fk` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`) ON DELETE SET NULL;
