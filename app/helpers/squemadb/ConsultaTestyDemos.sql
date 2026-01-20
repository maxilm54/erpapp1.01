SELECT mp.nombre,oc.cantidad AS pedida,SUM(idt.cantidad) AS ingresada,(oc.cantidad - SUM(idt.cantidad)) AS faltante
   FROM ingresos_mercaderia_detalle idt
   JOIN materias_primas mp ON mp.id = idt.materia_prima_id
   JOIN ordenes_compra_detalle oc ON oc.materia_prima_id = idt.materia_prima_id
AND oc.orden_compra_id = ?
WHERE idt.ingreso_id = ?
GROUP BY idt.materia_prima_id



SELECT 
                mp.nombre,
                oc.cantidad AS pedida,
                SUM(idt.cantidad) AS ingresada,
                (oc.cantidad - SUM(idt.cantidad)) AS faltante
            FROM ingresos_mercaderia_detalle idt
            JOIN materias_primas mp ON mp.id = idt.materia_prima_id
            JOIN ordenes_compra_detalle oc
                ON oc.materia_prima_id = idt.materia_prima_id
                AND oc.orden_compra_id = 7
            WHERE idt.ingreso_id = 
            GROUP BY idt.materia_prima_id
            
SELECT  (SUM(oc_det.cantidad)-SUM(ing_det.cantidad)) AS cantidad, SUM(oc_det.cantidad),SUM(ing_det.cantidad)
FROM ingresos_mercaderia_detalle ing_det
LEFT JOIN ordenes_compra_detalle oc_det ON oc_det.orden_compra_id = ing_det.oc_id
WHERE ing_det.oc_id=16 AND oc_det.orden_compra_id=16


SELECT i.*, p.razon_social AS proveedor
FROM ingresos_mercaderia i
JOIN proveedores p ON p.id = i.proveedor_id
WHERE i.orden_compra_id = ?
ORDER BY i.fecha DESC

SELECT ing_det.*,ing_cab.*, p.razon_social AS proveedor
SELECT ing_det.materia_prima_id ,COALESCE(SUM(ing_det.cantidad), 0)
FROM ingresos_mercaderia_detalle ing_det
LEFT JOIN ingresos_mercaderia ing_cab ON ing_cab.id= ing_det.ingreso_id
JOIN proveedores p ON p.id = ing_cab.proveedor_id
WHERE ing_det.oc_id = 31 AND ing_cab.ing_num_indicador < 3
GROUP BY ing_det.materia_prima_id
ORDER BY ing_cab.fecha DESC


SELECT COALESCE(SUM(total_cantidad), 0) AS total_cantidad 
            FROM ( SELECT SUM(ing_det.cantidad) AS total_cantidad 
            FROM ingresos_mercaderia_detalle ing_det 
            LEFT JOIN ingresos_mercaderia ing_cab ON ing_cab.id = ing_det.ingreso_id 
            JOIN proveedores p ON p.id = ing_cab.proveedor_id 
            WHERE ing_det.oc_id = 31 AND ing_cab.ing_num_indicador < 2 
            GROUP BY ing_det.materia_prima_id ) t
				;
            
            SELECT SUM(ing_det.cantidad) AS total_cantidad 
            FROM ingresos_mercaderia_detalle ing_det 
            LEFT JOIN ingresos_mercaderia ing_cab ON ing_cab.id = ing_det.ingreso_id 
            JOIN proveedores p ON p.id = ing_cab.proveedor_id 
            WHERE ing_det.oc_id = 31 AND ing_cab.ing_num_indicador < 2 
            GROUP BY ing_det.materia_prima_id



SELECT mp.id, COALESCE(SUM(ing_det.cantidad), 0) AS total_cantidad 
FROM materias_primas mp 
LEFT JOIN ingresos_mercaderia_detalle ing_det ON ing_det.materia_prima_id = mp.id AND ing_det.oc_id = 31 
LEFT JOIN ingresos_mercaderia ing_cab ON ing_cab.id = ing_det.ingreso_id  
WHERE ing_cab.ing_num_indicador <3
GROUP BY mp.id,ing_det.materia_prima_id;


SELECT ing.materia_prima_id, SUM(ing.cantidad)
FROM ingresos_mercaderia_detalle ing
GROUP BY ing.materia_prima_id


SELECT i.id, i.fecha, i.remito, u.nombre AS usuario, mp.nombre AS materia_prima, idt.cantidad
FROM ingresos_mercaderia i
JOIN ingresos_mercaderia_detalle idt ON idt.ingreso_id = i.id
JOIN materias_primas mp ON mp.id = idt.materia_prima_id
JOIN users u ON u.id = i.usuario_id
/* WHERE i.orden_compra_id = ?*/
ORDER BY i.fecha DESC, i.id DESC


SELECT
    d.producto_id,
    p.nombre,
    d.cantidad AS pedida,
    COALESCE(SUM(rsd.cantidad), 0) AS remitida,
    (d.cantidad - COALESCE(SUM(rsd.cantidad), 0)) AS pendiente
FROM notas_pedido_detalle d
JOIN productos p ON p.id = d.producto_id
LEFT JOIN remitos_salida rs ON rs.nota_pedido_id = d.nota_pedido_id
LEFT JOIN remitos_salida_detalle rsd
    ON rsd.remito_id = rs.id
    AND rsd.producto_id = d.producto_id
WHERE d.nota_pedido_id = 29
GROUP BY d.producto_id;

SELECT remdet.remito_id AS NumRem, remdet.producto_id AS idProdRem, remdet.cantidad AS CantRem, 
	c.razon_social,np.id AS idNpRem, np.presupuesto_id AS PresNpRem,np.observaciones AS obsNpRem, 
	remsal.usuario_id AS UserRem,remsal.observaciones AS obsRemRem, remsal.created_at AS fecha
FROM remitos_salida_detalle remdet
LEFT JOIN remitos_salida remsal ON remsal.id = remdet.remito_id
LEFT JOIN notas_pedido np ON np.id = remsal.nota_pedido_id
JOIN clientes c ON c.id = np.cliente_id
WHERE remdet.remito_id = 11


/*SELECT SUM(d.cantidad) AS pedida, COALESCE(SUM(rsd.cantidad), 0) AS remitida*/
SELECT SUM(d.cantidad) AS pedidoTotal , (SELECT SUM(cantidad) AS RemTotal
FROM remitos_salida_detalle rsd
LEFT JOIN remitos_salida rs ON rs.id=rsd.remito_id
LEFT JOIN notas_pedido np ON np.id=rs.nota_pedido_id
WHERE rs.nota_pedido_id= 30)
FROM notas_pedido_detalle d
WHERE d.nota_pedido_id = 30

SELECT SUM(cantidad) AS RemTotal
FROM remitos_salida_detalle rsd
LEFT JOIN remitos_salida rs ON rs.id=rsd.remito_id
LEFT JOIN notas_pedido np ON np.id=rs.nota_pedido_id
WHERE rs.nota_pedido_id= 29








