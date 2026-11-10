-- 029: Adjuntos en documentos del tenant (PR, NP, Remito, Produccion)
-- Activable desde el menu Empresa -> Configuraciones (clave usar_adjuntos).
-- Al aprobar un presupuesto con adjuntos se genera automaticamente una NP
-- que hereda los adjuntos (y puede agregar mas).

CREATE TABLE IF NOT EXISTS `documentos_adjuntos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modulo` enum('PRESUPUESTO','NOTA_PEDIDO','REMITO_SALIDA','PRODUCCION') NOT NULL,
  `documento_id` int(11) NOT NULL,
  `nombre_original` varchar(255) NOT NULL COMMENT 'Nombre con el que subio el usuario',
  `nombre_guardado` varchar(255) NOT NULL COMMENT 'Nombre fisico en storage/empresa_N/adjuntos',
  `tamano` int(11) NOT NULL DEFAULT 0,
  `mime` varchar(100) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_doc` (`modulo`, `documento_id`),
  CONSTRAINT `fk_adj_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Archivos adjuntos de presupuestos, notas de pedido, remitos y ordenes de produccion';

INSERT IGNORE INTO configuraciones (clave, valor) VALUES ('usar_adjuntos', '0');

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (29, 'Adjuntos en documentos (PR, NP, Remito, Produccion) + config usar_adjuntos');
