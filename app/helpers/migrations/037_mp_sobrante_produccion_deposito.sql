-- 037: Deposito de MP sobrante y deposito de origen en reservas de produccion
-- Los restos de materia prima (barras, rollos, etc.) se guardan en el deposito
-- "MP sobrante" para no mezclarlos con las unidades enteras del deposito principal.
-- Las reservas de produccion registran de que deposito se toma cada MP: el
-- operario lo elige manualmente en la orden de produccion.

ALTER TABLE `depositos`
  ADD COLUMN `es_sobrante` tinyint(1) NOT NULL DEFAULT 0 AFTER `es_transito`;

INSERT INTO `depositos` (`nombre`, `descripcion`, `es_sobrante`, `activo`)
SELECT 'MP sobrante', 'Restos de materia prima que no completan una presentacion', 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM depositos WHERE es_sobrante = 1 LIMIT 1);

ALTER TABLE `reservas_materia_prima`
  ADD COLUMN `deposito_id` int(11) DEFAULT NULL AFTER `materia_prima_id`,
  ADD KEY `deposito_id` (`deposito_id`);

ALTER TABLE `reservas_materia_prima`
  ADD CONSTRAINT `fk_reserva_deposito` FOREIGN KEY (`deposito_id`) REFERENCES `depositos` (`id`) ON DELETE SET NULL;

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (37, 'Deposito de MP sobrante y deposito de origen en reservas de produccion');
