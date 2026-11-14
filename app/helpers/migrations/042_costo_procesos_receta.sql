-- 042: Costo fijo por proceso de receta
-- Cada proceso de fabricacion puede tener un costo fijo por pieza. No se
-- multiplica por la cantidad producida: es un costo del proceso en si.

ALTER TABLE `recetas_procesos`
  ADD COLUMN `costo` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `horas_por_unidad`;

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (42, 'Costo fijo por proceso de receta');
