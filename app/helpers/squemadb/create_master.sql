/*
  ============================================================
  BASE DE DATOS MASTER - Multi-Tenant
  ============================================================
  Esta BD contiene:
    - users: usuarios que inician sesión (fuente de verdad)
    - tenants: cada cliente/empresa es un tenant con su propia BD
    - user_tenant: qué usuario puede acceder a qué tenant(s)
  
  IMPORTANTE: Ejecutar este SQL PRIMERO antes de usar el sistema.
  ============================================================
*/

-- Crear la base de datos master
CREATE DATABASE IF NOT EXISTS `app_master`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `app_master`;

-- -----------------------------------------------------------
-- TABLA: tenants (cada cliente es un tenant con su propia BD)
-- -----------------------------------------------------------
DROP TABLE IF EXISTS `user_tenant`;
DROP TABLE IF EXISTS `tenants`;

CREATE TABLE `tenants` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `nombre`          VARCHAR(150) NOT NULL,
  `dbname`          VARCHAR(100) NOT NULL COMMENT 'Nombre de la BD de este tenant',
  `host`            VARCHAR(100) NOT NULL DEFAULT 'localhost',
  `activo`          TINYINT(1) NOT NULL DEFAULT 1,
  `schema_version`  INT NOT NULL DEFAULT 0,
  `cuit`            VARCHAR(20) DEFAULT NULL,
  `email`           VARCHAR(150) DEFAULT NULL,
  `telefono`        VARCHAR(50) DEFAULT NULL,
  `direccion`       VARCHAR(255) DEFAULT NULL,
  `logo`            VARCHAR(255) DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dbname` (`dbname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cada fila es un cliente con su propia base de datos';

-- -----------------------------------------------------------
-- TABLA: users (fuente de verdad para login)
-- -----------------------------------------------------------
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id`                INT NOT NULL AUTO_INCREMENT,
  `nombre`            VARCHAR(100) NOT NULL,
  `email`             VARCHAR(150) NOT NULL,
  `password_hash`     VARCHAR(255) NOT NULL,
  `email_verificado`  TINYINT(1) DEFAULT 0,
  `token_verificacion` VARCHAR(255) DEFAULT NULL,
  `rol`               ENUM('SUPERADMIN','ADMIN','USUARIO','VISITOR','GERENTE_FINANCIERO') NOT NULL DEFAULT 'USUARIO',
  `activo`            TINYINT(1) DEFAULT 1,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuarios del sistema (login centralizado)';

-- -----------------------------------------------------------
-- TABLA: user_tenant (asociación usuario ↔ tenant)
-- -----------------------------------------------------------
CREATE TABLE `user_tenant` (
  `id`        INT NOT NULL AUTO_INCREMENT,
  `user_id`   INT NOT NULL,
  `tenant_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_tenant_unique` (`user_id`, `tenant_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_ut_user`   FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ut_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Un usuario puede acceder a uno o mas tenants';

-- -----------------------------------------------------------
-- TABLA: tareas (registro de tareas del panel de administracion)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tareas` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `texto`         VARCHAR(500) NOT NULL,
  `estado`        ENUM('PENDIENTE','HECHA') NOT NULL DEFAULT 'PENDIENTE',
  `tenant_id`     INT DEFAULT NULL,
  `usuario_id`    INT DEFAULT NULL,
  `completada_at` DATETIME DEFAULT NULL,
  `completada_por` INT DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_tareas_tenant`       FOREIGN KEY (`tenant_id`)      REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tareas_usuario`      FOREIGN KEY (`usuario_id`)     REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tareas_completada_por` FOREIGN KEY (`completada_por`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tareas pendientes y completadas del panel de administracion';

-- -----------------------------------------------------------
-- TABLA: master_migrations (control de migraciones del schema master)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `master_migrations` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `version`    INT NOT NULL,
  `filename`   VARCHAR(255) NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Migraciones aplicadas sobre la BD master';

-- -----------------------------------------------------------
-- DATOS INICIALES
-- -----------------------------------------------------------

-- Usuario SuperAdmin (para panel de administracion global)
-- Pass: Tucuman#1588
INSERT INTO `users` (`nombre`, `email`, `password_hash`, `email_verificado`, `rol`)
VALUES (
  'Soporte DmTech',
  'soporte@dmtech.com.ar',
  '$2y$10$wFq/eZHIodlEFwXS3l/XJup5/6aJ9FgL1C7OLoPjQBb2T3MjO6n.m',
  1,
  'SUPERADMIN'
);

-- Tenant default (si existe la BD app)
-- INSERT INTO `tenants` (`nombre`, `dbname`, `host`)
-- VALUES ('Default - App', 'app', 'localhost');
