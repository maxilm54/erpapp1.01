-- 001 (master): Registro de tareas del panel de administracion
-- Las tareas viven en la BD master porque las administer el superadmin
-- (una tarea puede referenciar un tenant, pero el registro es global).

CREATE TABLE IF NOT EXISTS `tareas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `texto` varchar(500) NOT NULL,
  `estado` enum('PENDIENTE','HECHA') NOT NULL DEFAULT 'PENDIENTE',
  `tenant_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `completada_at` datetime DEFAULT NULL,
  `completada_por` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_tareas_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tareas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tareas_completada_por` FOREIGN KEY (`completada_por`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tareas pendientes y completadas del panel de administracion';
