-- 043: Inicio y fin reales de produccion
-- Registra cuando se cargan las primeras piezas al proceso 1 y cuando la OP
-- termina, para poder mostrar la duracion total de produccion.
-- La fecha de fin reutiliza la columna existente finalizada_at.

ALTER TABLE `ordenes_produccion`
  ADD COLUMN `produccion_inicio_at` datetime DEFAULT NULL AFTER `receta_id`;

UPDATE `ordenes_produccion`
SET `produccion_inicio_at` = `created_at`
WHERE `produccion_inicio_at` IS NULL
  AND `estado` IN ('EN_PRODUCCION', 'FINALIZADA');

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (43, 'Inicio y fin reales de produccion');
