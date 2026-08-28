-- =====================================================
-- Migration 010: Agregar columnas pdf_path y pdf_hash a movimientos_no_declarados
-- Para almacenar el PDF generado de los comprobantes SDCOMP
-- =====================================================
ALTER TABLE `movimientos_no_declarados`
    ADD COLUMN `pdf_path` varchar(500) DEFAULT NULL AFTER `observaciones`,
    ADD COLUMN `pdf_hash` varchar(64) DEFAULT NULL AFTER `pdf_path`;
-- Actualizo registro de migracion de base de datos
INSERT INTO act_bd (id, descripcion) VALUES (10, 'Agregar columnas pdf_path y pdf_hash a movimientos_no_declarados para almacenar PDFs');
-- =====================================================