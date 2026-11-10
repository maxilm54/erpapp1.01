-- 033: Campos personalizados por modulo (menu Empresa -> Configuraciones)
-- Cada empresa define campos adicionales (nombre + tipo: texto/numero/fecha) que
-- aparecen en los formularios de Presupuestos, Notas de Pedido, Remitos de Salida
-- y Ordenes de Compra. Los valores se guardan por documento en
-- campos_personalizados_valores con snapshot del nombre, asi el historial sigue
-- visible aunque el campo se borre o se renombre. Al generar una Nota de Pedido
-- desde un Presupuesto, los valores del presupuesto se copian a la NP
-- (aunque la NP no tenga ese campo configurado).

CREATE TABLE IF NOT EXISTS `campos_personalizados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modulo` varchar(30) NOT NULL COMMENT 'PRESUPUESTO | NOTA_PEDIDO | REMITO_SALIDA | ORDEN_COMPRA',
  `nombre` varchar(150) NOT NULL COMMENT 'Etiqueta que se muestra en el formulario',
  `tipo` varchar(10) NOT NULL DEFAULT 'texto' COMMENT 'texto | numero | fecha',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_modulo` (`modulo`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Campos extra configurables por modulo (Configuraciones)';

CREATE TABLE IF NOT EXISTS `campos_personalizados_valores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campo_id` int(11) NOT NULL COMMENT 'FK a campos_personalizados',
  `modulo` varchar(30) NOT NULL COMMENT 'Modulo del documento que guarda el valor (puede heredarse, ej NOTA_PEDIDO con campo de PRESUPUESTO)',
  `documento_id` int(11) NOT NULL,
  `campo_nombre` varchar(150) DEFAULT NULL COMMENT 'Snapshot del nombre al guardar (historial)',
  `valor` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doc_campo` (`modulo`, `documento_id`, `campo_id`),
  KEY `idx_campo` (`campo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Valores de los campos personalizados por documento';

INSERT INTO act_bd (id, descripcion) VALUES (33, 'Campos personalizados por modulo con valores por documento');
