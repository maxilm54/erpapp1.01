-- =====================================================
-- Migration 011: Templates de email por defecto
-- Se insertan los templates base para cada tipo de email
-- =====================================================

-- REMITO
INSERT INTO `email_templates` (`tipo`, `asunto`, `cuerpo_html`, `activo`, `es_default`) VALUES
('REMITO', 'Remito de Salida N° {{numero}}', '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;color:#333;margin:0;padding:0;}
.box{border:1px solid #ddd;padding:20px;max-width:600px;margin:0 auto;}
.header{border-bottom:2px solid #1a3a5c;margin-bottom:15px;padding-bottom:10px;}
.footer{font-size:12px;color:#777;margin-top:20px;border-top:1px solid #ddd;padding-top:10px;}
.total{font-size:16px;font-weight:bold;color:#1a3a5c;}
table{width:100%;border-collapse:collapse;margin:10px 0;}
th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;}
th{background:#f5f5f5;}
</style></head>
<body>
<div class="box">
<img src="{{logo}}" width="120">
<div class="header"><h2>Remito de Salida</h2></div>
<p>Estimado/a <strong>{{cliente_nombre}}</strong>,</p>
<p>Le enviamos adjunto el <strong>Remito N° {{numero}}</strong>,
   correspondiente a la entrega realizada el día {{fecha}}.</p>
<table>
<tr><th>Concepto</th><th>Cantidad</th><th>P. Unitario</th><th>Subtotal</th></tr>
{{>detalle_tabla}}
</table>
<p class="total">Total: ${{total}}</p>
<p>Ante cualquier consulta, no dude en contactarnos.</p>
<p>Saludos cordiales.</p>
<div class="footer">{{empresa_nombre}} &middot; {{empresa_email}}</div>
</div></body></html>', 1, 1);

-- PAGO
INSERT INTO `email_templates` (`tipo`, `asunto`, `cuerpo_html`, `activo`, `es_default`) VALUES
('PAGO', 'Comprobante de Pago N° {{pago_id}}', '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;color:#333;margin:0;padding:0;}
.box{border:1px solid #ddd;padding:20px;max-width:600px;margin:0 auto;}
.header{border-bottom:2px solid #1a3a5c;margin-bottom:15px;padding-bottom:10px;}
.footer{font-size:12px;color:#777;margin-top:20px;border-top:1px solid #ddd;padding-top:10px;}
.total{font-size:18px;font-weight:bold;color:#1a3a5c;}
</style></head>
<body>
<div class="box">
<img src="{{logo}}" width="120">
<div class="header"><h2>Comprobante de Pago</h2></div>
<p>Estimado/a <strong>{{cliente_nombre}}</strong>,</p>
<p>Registramos un pago realizado el día {{fecha}}.</p>
<p class="total">Monto: ${{monto}}</p>
<p>Medio de pago: {{medio_pago}}</p>
{{#observaciones}}<p><strong>Observaciones:</strong><br>{{observaciones}}</p>{{/observaciones}}
<p>Muchas gracias por su pago.</p>
<p>Atentamente.</p>
<div class="footer">{{empresa_nombre}} &middot; {{empresa_email}}</div>
</div></body></html>', 1, 1);

-- PRESUPUESTO
INSERT INTO `email_templates` (`tipo`, `asunto`, `cuerpo_html`, `activo`, `es_default`) VALUES
('PRESUPUESTO', 'Presupuesto N° {{numero}}', '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;color:#333;margin:0;padding:0;}
.box{border:1px solid #ddd;padding:20px;max-width:600px;margin:0 auto;}
.header{border-bottom:2px solid #1a3a5c;margin-bottom:15px;padding-bottom:10px;}
.footer{font-size:12px;color:#777;margin-top:20px;border-top:1px solid #ddd;padding-top:10px;}
.total{font-size:16px;font-weight:bold;color:#1a3a5c;}
table{width:100%;border-collapse:collapse;margin:10px 0;}
th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;}
th{background:#f5f5f5;}
</style></head>
<body>
<div class="box">
<img src="{{logo}}" width="120">
<div class="header"><h2>Presupuesto</h2></div>
<p>Estimado/a <strong>{{cliente_nombre}}</strong>,</p>
<p>Le enviamos el presupuesto N° <strong>{{numero}}</strong> con fecha {{fecha}}.</p>
<table>
<tr><th>Concepto</th><th>Cantidad</th><th>P. Unitario</th><th>Subtotal</th></tr>
{{>detalle_tabla}}
</table>
<p class="total">Total: ${{total}}</p>
<p>Válido por {{dias_validez}} días.</p>
<p>Saludos cordiales.</p>
<div class="footer">{{empresa_nombre}} &middot; {{empresa_email}}</div>
</div></body></html>', 1, 1);

-- NOTA DE PEDIDO
INSERT INTO `email_templates` (`tipo`, `asunto`, `cuerpo_html`, `activo`, `es_default`) VALUES
('NOTA_PEDIDO', 'Nota de Pedido N° {{numero}}', '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;color:#333;margin:0;padding:0;}
.box{border:1px solid #ddd;padding:20px;max-width:600px;margin:0 auto;}
.header{border-bottom:2px solid #1a3a5c;margin-bottom:15px;padding-bottom:10px;}
.footer{font-size:12px;color:#777;margin-top:20px;border-top:1px solid #ddd;padding-top:10px;}
.total{font-size:16px;font-weight:bold;color:#1a3a5c;}
table{width:100%;border-collapse:collapse;margin:10px 0;}
th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;}
th{background:#f5f5f5;}
</style></head>
<body>
<div class="box">
<img src="{{logo}}" width="120">
<div class="header"><h2>Nota de Pedido</h2></div>
<p>Estimado/a <strong>{{cliente_nombre}}</strong>,</p>
<p>Se ha registrado la Nota de Pedido N° <strong>{{numero}}</strong> con fecha {{fecha}}.</p>
<table>
<tr><th>Concepto</th><th>Cantidad</th><th>P. Unitario</th><th>Subtotal</th></tr>
{{>detalle_tabla}}
</table>
<p class="total">Total: ${{total}}</p>
<p>Saludos cordiales.</p>
<div class="footer">{{empresa_nombre}} &middot; {{empresa_email}}</div>
</div></body></html>', 1, 1);

-- ORDEN DE COMPRA
INSERT INTO `email_templates` (`tipo`, `asunto`, `cuerpo_html`, `activo`, `es_default`) VALUES
('ORDEN_COMPRA', 'Orden de Compra N° {{numero}}', '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;color:#333;margin:0;padding:0;}
.box{border:1px solid #ddd;padding:20px;max-width:600px;margin:0 auto;}
.header{border-bottom:2px solid #1a3a5c;margin-bottom:15px;padding-bottom:10px;}
.footer{font-size:12px;color:#777;margin-top:20px;border-top:1px solid #ddd;padding-top:10px;}
.total{font-size:16px;font-weight:bold;color:#1a3a5c;}
table{width:100%;border-collapse:collapse;margin:10px 0;}
th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;}
th{background:#f5f5f5;}
</style></head>
<body>
<div class="box">
<img src="{{logo}}" width="120">
<div class="header"><h2>Orden de Compra</h2></div>
<p>Estimado/a <strong>{{proveedor_nombre}}</strong>,</p>
<p>Le enviamos la Orden de Compra N° <strong>{{numero}}</strong> con fecha {{fecha}}.</p>
<table>
<tr><th>Concepto</th><th>Cantidad</th><th>P. Unitario</th><th>Subtotal</th></tr>
{{>detalle_tabla}}
</table>
<p class="total">Total: ${{total}}</p>
<p>Saludos cordiales.</p>
<div class="footer">{{empresa_nombre}} &middot; {{empresa_email}}</div>
</div></body></html>', 1, 1);

-- GENÉRICO (template base para otros usos)
INSERT INTO `email_templates` (`tipo`, `asunto`, `cuerpo_html`, `activo`, `es_default`) VALUES
('GENERIC', '{{asunto}}', '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;color:#333;margin:0;padding:0;}
.box{border:1px solid #ddd;padding:20px;max-width:600px;margin:0 auto;}
.header{border-bottom:2px solid #1a3a5c;margin-bottom:15px;padding-bottom:10px;}
.footer{font-size:12px;color:#777;margin-top:20px;border-top:1px solid #ddd;padding-top:10px;}
</style></head>
<body>
<div class="box">
<img src="{{logo}}" width="120">
<div class="header"><h2>{{titulo}}</h2></div>
{{contenido}}
<div class="footer">{{empresa_nombre}} &middot; {{empresa_email}}</div>
</div></body></html>', 1, 1);


-- =====================================================
-- Migration 24: Corregir templates de email con {{>detalle_tabla}}
-- Los templates de la migración 023 tenían {{detalle_tabla}} (escaped)
-- en vez de {{>detalle_tabla}} (HTML raw)
-- =====================================================

UPDATE `email_templates` SET `cuerpo_html` = REPLACE(`cuerpo_html`, '{{detalle_tabla}}', '{{>detalle_tabla}}')
WHERE `tipo` = 'REMITO' AND `cuerpo_html` LIKE '%{{detalle_tabla}}%';

UPDATE `email_templates` SET `cuerpo_html` = REPLACE(`cuerpo_html`, '{{detalle_tabla}}', '{{>detalle_tabla}}')
WHERE `tipo` = 'PRESUPUESTO' AND `cuerpo_html` LIKE '%{{detalle_tabla}}%';

UPDATE `email_templates` SET `cuerpo_html` = REPLACE(`cuerpo_html`, '{{detalle_tabla}}', '{{>detalle_tabla}}')
WHERE `tipo` = 'NOTA_PEDIDO' AND `cuerpo_html` LIKE '%{{detalle_tabla}}%';

UPDATE `email_templates` SET `cuerpo_html` = REPLACE(`cuerpo_html`, '{{detalle_tabla}}', '{{>detalle_tabla}}')
WHERE `tipo` = 'ORDEN_COMPRA' AND `cuerpo_html` LIKE '%{{detalle_tabla}}%';


-- Actualizo registro de migracion de base de datos
INSERT INTO act_bd (id,descripcion) VALUES (11,'Insertar templates de email por defecto (REMITO, PAGO, PRESUPUESTO, NP, OC, GENERIC), se agrupa la mig 24 aqui para menor mantenimiento');
-- =====================================================
