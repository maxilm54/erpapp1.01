-- 023: Cheques de terceros (cobranzas)
-- Guarda los datos del cheque cuando el medio de pago es Cheque y permite
-- seguirlo en cartera, controlar vencimientos y recibir avisos por email.
-- Un cheque por cobro (la UI registra un cobro por cheque, si un cliente paga
-- con varios cheques se registran varios cobros).

CREATE TABLE IF NOT EXISTS `cheques` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pago_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) NOT NULL,
  `cliente_nombre` varchar(150) DEFAULT NULL,
  `tipo` enum('FISICO','ECHEQ') NOT NULL DEFAULT 'FISICO',
  `numero` varchar(50) NOT NULL,
  `banco` varchar(100) NOT NULL,
  `sucursal` varchar(100) DEFAULT NULL,
  `titular` varchar(150) DEFAULT NULL,
  `cuit` varchar(20) DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL,
  `fecha_vencimiento` date NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `estado` enum('EN_CARTERA','DEPOSITADO','COBRADO','RECHAZADO','ANULADO') NOT NULL DEFAULT 'EN_CARTERA',
  `observaciones` text DEFAULT NULL,
  `caja_banco_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `ultimo_aviso_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cheque_pago` (`pago_id`),
  KEY `idx_cheque_cliente` (`cliente_id`),
  KEY `idx_cheque_vencimiento` (`fecha_vencimiento`),
  KEY `idx_cheque_estado` (`estado`),
  KEY `idx_cheque_caja` (`caja_banco_id`),
  CONSTRAINT `fk_cheque_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cheque_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `fk_cheque_caja` FOREIGN KEY (`caja_banco_id`) REFERENCES `cajas_bancos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cheques de terceros recibidos en cobranzas: datos, cartera y vencimientos';

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (23, 'Cheques de terceros: datos del cheque, cartera y vencimientos');
