-- 048: Adjuntos en productos, materias primas y recetas
-- Reutiliza documentos_adjuntos con modulos nuevos. Los adjuntos de PRODUCTO
-- se ven en el ABM del producto y en sus recetas. Los de RECETA son propios
-- de la receta y no se muestran en el ABM del producto.

ALTER TABLE `documentos_adjuntos`
  MODIFY COLUMN `modulo` ENUM('PRESUPUESTO','NOTA_PEDIDO','REMITO_SALIDA','PRODUCCION','ORDEN_COMPRA','PRODUCTO','MATERIA_PRIMA','RECETA') NOT NULL;

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (48, 'Adjuntos en productos, materias primas y recetas');
