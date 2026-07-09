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
  `id`         INT NOT NULL AUTO_INCREMENT,
  `nombre`     VARCHAR(150) NOT NULL,
  `dbname`     VARCHAR(100) NOT NULL COMMENT 'Nombre de la BD de este tenant',
  `host`       VARCHAR(100) NOT NULL DEFAULT 'localhost',
  `activo`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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
  `rol`               ENUM('ADMIN','USUARIO','VISITOR') NOT NULL DEFAULT 'USUARIO',
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
-- DATOS INICIALES: Migrar el tenant actual
-- -----------------------------------------------------------
-- El tenant "default" es la BD 'app' que ya existe
INSERT INTO `tenants` (`nombre`, `dbname`, `host`)
VALUES ('DmTech', 'app', 'localhost');

-- Migrar el usuario admin existente (ajustar email/password si es necesario)
-- IMPORTANTE: Cambiar el password_hash por el real de tu usuario admin
INSERT INTO `users` (`nombre`, `email`, `password_hash`, `email_verificado`, `rol`)
VALUES (
  'Maxi Favaro',
  'maximiliano.favaro@gmail.com',
  '$2y$10$zgZeptGdtX3cdLUq1Bf/AuquBgmR7RP7BZnN8ih4ToxeqDRCYRYay',
  1,
  'ADMIN'
);

-- Asociar el usuario admin al tenant default
INSERT INTO `user_tenant` (`user_id`, `tenant_id`)
VALUES (1, 1);
