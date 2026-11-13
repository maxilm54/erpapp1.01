-- 035: Tipo de gestion y ficha de especificaciones tecnicas de materias primas
-- tipo_gestion define como se mide/consume la MP: UNIDAD (default), LINEAL (metros),
-- SUPERFICIE (m2) o GRANEL (kg/L).
-- La ficha tecnica es OPCIONAL y 1:1 con la MP. materia_prima_id queda NULL para
-- fichas que todavia no tienen MP asignada.

ALTER TABLE `materias_primas`
  ADD COLUMN `tipo_gestion` varchar(30) NOT NULL DEFAULT 'UNIDAD' AFTER `usa_serie`;

CREATE TABLE IF NOT EXISTS `materias_primas_especificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materia_prima_id` int(11) DEFAULT NULL,
  `parametro_texto_1` varchar(50) DEFAULT NULL COMMENT 'Ej: Forma (Redondo) o Tipo de Ligamento',
  `parametro_numerico_1` decimal(10,4) DEFAULT NULL COMMENT 'Ej: Diametro (12.70) o Ancho de tela (1.50)',
  `parametro_numerico_2` decimal(10,4) DEFAULT NULL COMMENT 'Ej: Peso por metro (0.99) o Gramaje (240)',
  `presentacion` decimal(10,2) DEFAULT NULL COMMENT 'Ej: 6 metros (barra) o 50 metros (rollo)',
  `obs` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mp_especificacion` (`materia_prima_id`),
  CONSTRAINT `fk_mp_especificacion_mp` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Ficha tecnica de especificaciones de la materia prima (opcional, 1:1)';

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (35, 'Tipo de gestion y especificaciones tecnicas de materias primas');
