-- =====================================================
-- Migration 019: Agregar campo descripcion a detalles
-- Para soportar items manuales (servicios) sin producto/materia prima
-- =====================================================
-- remitos_salida_detalle
/*ALTER TABLE `remitos_salida_detalle`
    ADD COLUMN `descripcion` varchar(255) DEFAULT NULL AFTER `producto_id`;

-- movimientos_no_declarados_detalle
ALTER TABLE `movimientos_no_declarados_detalle`
    ADD COLUMN `descripcion` varchar(255) DEFAULT NULL AFTER `materia_prima_id`;

INSERT INTO act_bd (id, descripcion) VALUES (19, 'Discontinuado se agrego en mig 014. Agregar campo descripcion a detalles de remitos y sdcomp para soportar items manuales (servicios)');
-- ===================================================== */