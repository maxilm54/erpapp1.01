-- 002 (master): Observaciones de seguimiento para las tareas del panel admin.
-- Cada tarea puede tener muchas observaciones, cada una con fecha y hora.
-- Se pueden seguir agregando aunque la tarea este HECHA (cerrada).

CREATE TABLE IF NOT EXISTS `tareas_observaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tarea_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `observacion` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tarea` (`tarea_id`),
  CONSTRAINT `fk_tobs_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tareas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tobs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Observaciones con fecha y hora de cada tarea del panel admin';
