-- 036: Unidad de compra y factor de conversion de materias primas
-- Permite comprar una MP en una unidad distinta a la unidad de stock/produccion.
-- id_unidadcompra NULL = se compra en la misma unidad que id_unidadmedida.
-- factor_conversion = unidades de STOCK que entran en 1 unidad de COMPRA.
-- Ejemplo: stock en metros, compra en kilos, 1 kg = 1.0101 m -> factor 1.0101.
-- El stock, las recetas, transferencias y movimientos siguen en la unidad de stock.

ALTER TABLE `materias_primas`
  ADD COLUMN `id_unidadcompra` int(3) DEFAULT NULL AFTER `id_unidadmedida`,
  ADD COLUMN `factor_conversion` decimal(18,6) NOT NULL DEFAULT 1.000000 AFTER `id_unidadcompra`;

ALTER TABLE `materias_primas`
  ADD CONSTRAINT `fk_mp_unidadcompra` FOREIGN KEY (`id_unidadcompra`) REFERENCES `unidad_medida` (`id_medida`) ON DELETE SET NULL;

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (36, 'Unidad de compra y factor de conversion de materias primas');
