ALTER TABLE `depositos`
  ADD COLUMN `es_transito` tinyint(1) NOT NULL DEFAULT 0 AFTER `descripcion`;

INSERT INTO `depositos` (`nombre`, `descripcion`, `es_transito`, `activo`)
SELECT 'En Tránsito', 'Depósito virtual para mercadería en tránsito entre depósitos', 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM depositos WHERE es_transito = 1 LIMIT 1);

ALTER TABLE `transferencias_depositos`
  MODIFY COLUMN `estado` enum('EN_TRANSITO','COMPLETADA','ANULADA') NOT NULL DEFAULT 'EN_TRANSITO',
  ADD COLUMN `recibida_at` datetime DEFAULT NULL AFTER `estado`,
  ADD COLUMN `recibida_por` int(11) DEFAULT NULL AFTER `recibida_at`;