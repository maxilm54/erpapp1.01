-- =====================================================
-- Migration 010: Tablas de configuración de email por tenant
-- email_config: configuración SMTP por empresa
-- email_templates: plantillas de email por empresa
-- =====================================================

-- Configuración SMTP por tenant
CREATE TABLE IF NOT EXISTS `email_config` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `smtp_host` varchar(255) NOT NULL DEFAULT 'mail.dmtech.com.ar',
    `smtp_port` int(11) NOT NULL DEFAULT 465,
    `smtp_secure` enum('ssl','tls','none') NOT NULL DEFAULT 'ssl',
    `smtp_user` varchar(255) NOT NULL,
    `smtp_pass` varchar(255) NOT NULL,
    `smtp_from_email` varchar(255) NOT NULL,
    `smtp_from_name` varchar(255) NOT NULL DEFAULT '',
    `bcc_email` varchar(255) DEFAULT NULL,
    `activo` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plantillas de email por tenant
CREATE TABLE IF NOT EXISTS `email_templates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tipo` enum('REMITO','PAGO','PRESUPUESTO','NOTA_PEDIDO','FACTURA','ORDEN_COMPRA','GENERIC') NOT NULL,
    `asunto` varchar(255) NOT NULL,
    `cuerpo_html` longtext NOT NULL,
    `activo` tinyint(1) NOT NULL DEFAULT 1,
    `es_default` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_tipo` (`tipo`),
    KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Actualizo registro de migracion de base de datos
INSERT INTO act_bd (id, descripcion) VALUES (10, 'Crear tablas email_config y email_templates para configuración SMTP y plantillas por tenant');
-- =====================================================
