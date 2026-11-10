-- 032: Alicuota IVA 0% para comprobantes X (no discriminan IVA)
-- La tabla impuestos ya trae las alicuotas comunes (004): IVA21, IVA105,
-- IVA27, IVA25, IVA5, EXENTO y NOGRAV. Se agrega una alicuota 0% explicita
-- con nombre claro para comprobantes X / C, para elegirla en facturas de
-- compra que no discriminan IVA.

INSERT INTO impuestos (nombre, codigo, porcentaje, activo)
SELECT 'IVA 0% (Comp. X / C)', 'IVA0X', 0.00, 1
WHERE NOT EXISTS (SELECT 1 FROM impuestos WHERE codigo = 'IVA0X');

INSERT IGNORE INTO act_bd (id, descripcion) VALUES (32, 'Alicuota IVA 0% para comprobantes X/C + IVA automatico en facturas de compra');
