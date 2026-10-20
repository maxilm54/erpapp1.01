ALTER TABLE `lotes`
  ADD COLUMN `numero_serie` varchar(100) DEFAULT NULL AFTER `codigo_lote`;