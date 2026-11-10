-- 003 (master): Modulo de notificaciones por usuario
-- notificaciones: alertas operativas por empresa (cheques por vencer, presupuestos
-- sin aprobar con fecha de entrega, notas de pedido aprobadas con fecha de entrega
-- y ordenes de produccion con fecha de entrega, regeneradas desde la BD del tenant
-- con una clave estable) + novedades globales que publica el superadmin desde
-- Admin -> Novedades, con vigencia_hasta como fecha limite de visualizacion.
-- Vive en la BD master para que un solo esquema sirva a superadmin y a los
-- usuarios de todas las empresas.
-- notificaciones_leidos: estado leido / no leido POR USUARIO (users.id de master).

CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `clave` varchar(120) DEFAULT NULL,
  `tipo` varchar(40) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `mensaje` text DEFAULT NULL,
  `fecha_referencia` date DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `vigencia_hasta` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clave` (`clave`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_fecha_ref` (`fecha_referencia`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notificaciones_leidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notificacion_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leido_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notif_user` (`notificacion_id`, `user_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_notif_leido_notif` FOREIGN KEY (`notificacion_id`) REFERENCES `notificaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
