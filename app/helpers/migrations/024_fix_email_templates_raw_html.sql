-- =====================================================
-- Migration 024: Corregir templates de email con {{>detalle_tabla}}
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

INSERT INTO act_bd (id, descripcion) VALUES (24, 'Corregir templates email: usar {{>detalle_tabla}} para HTML raw');
-- =====================================================
