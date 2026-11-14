-- 040: Productos como componentes de recetas
-- Una receta puede incluir materias primas y, si la empresa lo habilita en
-- Configuraciones, tambien productos terminados o semielaborados.
-- En recetas_detalle cada fila representa una materia prima O un producto.

ALTER TABLE `recetas_detalle`
  MODIFY COLUMN `materia_prima_id` int(11) DEFAULT NULL,
  ADD COLUMN `producto_id` int(11) DEFAULT NULL AFTER `materia_prima_id`,
  ADD KEY `idx_recetas_detalle_producto` (`producto_id`),
  ADD UNIQUE KEY `receta_id_producto_id` (`receta_id`,`producto_id`);

ALTER TABLE `recetas_detalle`
  ADD CONSTRAINT `recetas_detalle_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (40, 'Productos como componentes de recetas');
