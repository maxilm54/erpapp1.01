-- 004 (master): Modulo CtaCte de las empresas (panel superadmin)
-- Vive en la BD master porque lo administra el superadmin, igual que tareas.
--
-- ctacte_tenant: un registro por empresa y por mes (periodo YYYY-MM).
--   sistema / soporte / desarrollo son los conceptos facturados del mes.
--   pago es lo cobrado acumulado sobre ese registro (NULL hasta el primer pago).
--   saldo_pendiente = sistema + soporte + desarrollo - pago.
--   estado: PENDIENTE (sin pagos), PARCIAL (pagos parciales) o SALDADO (saldo 0).
--
-- ctacte_tenant_pagos: historial de cada pago registrado.
-- ctacte_tenant_pago_aplicaciones: reparto del pago entre los registros
--   elegidos, siempre del vencimiento mas viejo al mas nuevo.

CREATE TABLE IF NOT EXISTS `ctacte_tenant` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `periodo` char(7) NOT NULL,
  `fecha_alta` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `obs` varchar(500) DEFAULT NULL,
  `sistema` decimal(12,2) NOT NULL DEFAULT 0.00,
  `soporte` decimal(12,2) NOT NULL DEFAULT 0.00,
  `desarrollo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pago` decimal(12,2) DEFAULT NULL,
  `saldo_pendiente` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('PENDIENTE','PARCIAL','SALDADO') NOT NULL DEFAULT 'PENDIENTE',
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ctacte_tenant_periodo` (`tenant_id`, `periodo`),
  KEY `idx_ctacte_estado` (`estado`),
  KEY `idx_ctacte_vencimiento` (`fecha_vencimiento`),
  CONSTRAINT `fk_ctacte_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ctacte_tenant_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cuenta corriente mensual de cada empresa (panel superadmin)';

CREATE TABLE IF NOT EXISTS `ctacte_tenant_pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `obs` varchar(500) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ctacte_pago_tenant` (`tenant_id`, `fecha`),
  CONSTRAINT `fk_ctacte_pago_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ctacte_pago_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pagos registrados en la cuenta corriente de las empresas';

CREATE TABLE IF NOT EXISTS `ctacte_tenant_pago_aplicaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pago_id` int(11) NOT NULL,
  `registro_id` int(11) NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ctacte_aplic_pago` (`pago_id`),
  KEY `idx_ctacte_aplic_registro` (`registro_id`),
  CONSTRAINT `fk_ctacte_aplic_pago` FOREIGN KEY (`pago_id`) REFERENCES `ctacte_tenant_pagos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ctacte_aplic_registro` FOREIGN KEY (`registro_id`) REFERENCES `ctacte_tenant` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Reparto de cada pago entre los registros de cuenta corriente';
