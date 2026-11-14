-- 038: Procesos de fabricacion por receta y seguimiento por OP
-- Cada receta tiene al menos un proceso (nombre + horas estimadas por pieza).
-- Al crear una OP se copian los procesos y las piezas arrancan en el proceso 1.
-- Los movimientos entre procesos y las finalizaciones parciales quedan en el historial.

CREATE TABLE IF NOT EXISTS `recetas_procesos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receta_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `horas_por_unidad` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'Horas estimadas por pieza en este proceso',
  `secuencia` int(11) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_receta_secuencia` (`receta_id`, `secuencia`, `activo`),
  CONSTRAINT `fk_receta_proceso_receta` FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Procesos de fabricacion de cada receta';

INSERT INTO `recetas_procesos` (`receta_id`, `nombre`, `horas_por_unidad`, `secuencia`, `activo`)
SELECT r.id, 'Proceso 1', 0.0000, 1, 1
FROM `recetas` r
WHERE NOT EXISTS (
  SELECT 1 FROM `recetas_procesos` p WHERE p.receta_id = r.id
);

CREATE TABLE IF NOT EXISTS `ordenes_produccion_procesos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_produccion_id` int(11) NOT NULL,
  `receta_proceso_id` int(11) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `horas_por_unidad` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `secuencia` int(11) NOT NULL,
  `cantidad_en_proceso` decimal(12,3) NOT NULL DEFAULT 0.000,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_op_secuencia` (`orden_produccion_id`, `secuencia`),
  KEY `idx_op` (`orden_produccion_id`),
  CONSTRAINT `fk_opp_op` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_opp_receta_proceso` FOREIGN KEY (`receta_proceso_id`) REFERENCES `recetas_procesos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Estado actual de las piezas en cada proceso de una OP';

CREATE TABLE IF NOT EXISTS `ordenes_produccion_procesos_movimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_produccion_id` int(11) NOT NULL,
  `proceso_origen_id` int(11) DEFAULT NULL,
  `proceso_destino_id` int(11) DEFAULT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'MOVER' COMMENT 'MOVER | FINALIZAR',
  `usuario_id` int(11) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_op` (`orden_produccion_id`),
  CONSTRAINT `fk_oppm_op` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oppm_origen` FOREIGN KEY (`proceso_origen_id`) REFERENCES `ordenes_produccion_procesos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_oppm_destino` FOREIGN KEY (`proceso_destino_id`) REFERENCES `ordenes_produccion_procesos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de piezas movidas entre procesos o finalizadas';

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (38, 'Procesos de fabricacion por receta y seguimiento por OP');
