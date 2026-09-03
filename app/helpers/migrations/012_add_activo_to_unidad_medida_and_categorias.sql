-- =====================================================
-- Migration 012: Agregar columna activo a unidad_medida y categorias_mp_id
-- =====================================================
ALTER TABLE `unidad_medida` ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE `categorias_mp_id` ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1;
-- Actualizo registro de migracion de base de datos
INSERT INTO act_bd (id, descripcion) VALUES (12, 'Add activo column to unidad_medida and categorias_mp_id');
-- =====================================================