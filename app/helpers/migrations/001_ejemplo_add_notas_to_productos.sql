-- Migración 001: Ejemplo - Agregar campo 'notas' a productos
-- Esta es una migración de ejemplo para demostrar el patrón.
-- Cuando hagas un cambio real en la BD, creá un archivo similar.

ALTER TABLE productos
ADD COLUMN notas TEXT NULL AFTER descripcion;
