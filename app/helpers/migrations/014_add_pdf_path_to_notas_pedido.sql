ALTER TABLE `notas_pedido` ADD COLUMN `pdf_path` VARCHAR(255) DEFAULT NULL;

INSERT INTO act_bd (id, descripcion) VALUES (14, 'Add pdf_path to notas_pedido');
