ALTER TABLE `presupuestos` ADD COLUMN `pdf_path` VARCHAR(255) DEFAULT NULL;

INSERT INTO act_bd (id, descripcion) VALUES (13, 'Add pdf_path column to presupuestos');
