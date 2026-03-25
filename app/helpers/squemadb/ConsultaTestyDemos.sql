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


SELECT SUM(d.cantidad) AS pedidoTotal , (SELECT SUM(cantidad) AS RemTotal
                FROM remitos_salida_detalle rsd
                LEFT JOIN remitos_salida rs ON rs.id=rsd.remito_id
                LEFT JOIN notas_pedido np ON np.id=rs.nota_pedido_id
                WHERE rs.nota_pedido_id= 44)
                FROM notas_pedido_detalle d
                WHERE d.nota_pedido_id = 44




SELECT r.*,c.razon_social AS cliente,u.nombre AS usuario, c.id AS cliente_id
            FROM remitos_salida r
            JOIN notas_pedido np ON np.id = r.nota_pedido_id
            JOIN clientes c      ON c.id = np.cliente_id
            JOIN users u      ON u.id = r.usuario_id
            WHERE r.id = 50
SELECT np.*, c.razon_social AS cliente_nombre, SUM(npd.precio*npd.cantidad) AS total_precio
            FROM notas_pedido np
            JOIN clientes c ON c.id = np.cliente_id
            LEFT JOIN notas_pedido_detalle npd ON npd.nota_pedido_id = np.id
            WHERE np.id = 46
            
SELECT
                d.producto_id,
                p.nombre,
                d.cantidad AS pedida,
                COALESCE(SUM(rsd.cantidad), 0) AS remitida,
                (d.cantidad - COALESCE(SUM(rsd.cantidad), 0)) AS pendiente,
                d.precio AS precio,
                SUM(d.cantidad * d.precio) AS total_linea
            FROM notas_pedido_detalle d
            JOIN productos p ON p.id = d.producto_id
            LEFT JOIN remitos_salida rs
                ON rs.nota_pedido_id = d.nota_pedido_id
            LEFT JOIN remitos_salida_detalle rsd
                ON rsd.remito_id = rs.id
                AND rsd.producto_id = d.producto_id
            WHERE d.nota_pedido_id = 46
            GROUP BY d.producto_id  
				
				
				
SELECT npd.*,np.*, SUM(npd.cantidad*npd.precio) AS subtotal
FROM notas_pedido_detalle npd
LEFT JOIN notas_pedido np ON np.id=npd.nota_pedido_id
WHERE npd.nota_pedido_id = 46
GROUP BY npd.producto_id				          
            
SELECT npd.*,np.*, SUM(npd.cantidad*npd.precio) AS subtotal
FROM notas_pedido_detalle npd
LEFT JOIN notas_pedido np ON np.id=npd.nota_pedido_id
WHERE npd.nota_pedido_id = 46 AND npd.producto_id=3
                
SELECT COALESCE(MAX(saldo),0)

SELECT SUM(cta.monto)
FROM cuentas_corriente_clientes cta
WHERE cliente_id = 1


SELECT COALESCE(MAX(saldo),0)
FROM cuentas_corriente_clientes
WHERE cliente_id = 4
               
SELECT SUM(cta.monto)
FROM cuentas_corriente_clientes cta
WHERE cliente_id = 4
GROUP BY tipo
                
SELECT SUM(monto)
FROM cuentas_corriente_clientes
WHERE cliente_id = 1 AND tipo = 'DEBITO'
ORDER BY fecha DESC
LIMIT 1;

SELECT SUM(monto)
FROM cuentas_corriente_clientes
WHERE cliente_id = 1 AND tipo = 'CREDITO'
ORDER BY fecha DESC
LIMIT 1;


SELECT
	(SELECT SUM(monto) FROM cuentas_corriente_clientes WHERE cliente_id=1 AND tipo='DEBITO') AS Debito, 
	(SELECT SUM(monto) FROM cuentas_corriente_clientes WHERE cliente_id=1 AND tipo='CREDITO') AS Credito,
	saldo
FROM cuentas_corriente_clientes
WHERE cliente_id = 1
ORDER BY id DESC
LIMIT 1

SELECT d.cantidad, p.nombre,
	npd.precio AS precio_prod,npd.cantidad AS pedida, d.producto_id AS prod_id_a,
	rs.nota_pedido_id,d.producto_id AS id_prodnp, (SELECT precio FROM notas_pedido_detalle npdd WHERE npdd.nota_pedido_id=rs.nota_pedido_id AND npdd.producto_id=d.producto_id) AS preciocalculado
FROM remitos_salida_detalle d
LEFT JOIN remitos_salida rs ON rs.id=d.remito_id
LEFT JOIN notas_pedido_detalle npd ON npd.nota_pedido_id = rs.nota_pedido_id
JOIN productos p ON p.id = d.producto_id
WHERE d.remito_id = 139
GROUP BY d.producto_id

SELECT 
    r.id,
    r.nombre,  -- o los campos que quieras mostrar de la cabecera
    GROUP_CONCAT(rd.materia_prima_id SEPARATOR ' - ') AS id_mat_prim,
    GROUP_CONCAT(mp.nombre SEPARATOR ' - ') AS mat_prim
FROM recetas r
LEFT JOIN recetas_detalle rd 
    ON r.id = rd.receta_id
LEFT JOIN materias_primas mp ON mp.id=rd.materia_prima_id
GROUP BY r.id, r.nombre;

SELECT
              SUM(CASE WHEN tipo='ENTRADA' THEN cantidad ELSE 0 END) -
              SUM(CASE WHEN tipo='SALIDA' THEN cantidad ELSE 0 END) -
              IFNULL((SELECT SUM(cantidad) FROM reservas_materia_prima WHERE materia_prima_id=12),0)
            FROM movimientos_stock
            WHERE materia_prima_id=12

SELECT COALESCE(SUM(
                    CASE 
                        WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                        WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                    END
                ),0) AS stock
FROM materias_primas mp
LEFT JOIN movimientos_stock m ON m.materia_prima_id = mp.id
WHERE mp.id=12 LIMIT 1

SELECT rd.*, mp.nombre, mp.unidad_medida
            FROM recetas_detalle rd
            JOIN materias_primas mp ON mp.id = rd.materia_prima_id
            WHERE rd.receta_id = 24


SELECT
              SUM(CASE WHEN tipo='ENTRADA' THEN cantidad ELSE 0 END) -
              SUM(CASE WHEN tipo='SALIDA' THEN cantidad ELSE 0 END) -
              IFNULL((SELECT SUM(cantidad) FROM reservas_materia_prima WHERE materia_prima_id=4),0)
            FROM movimientos_stock
            WHERE materia_prima_id=4
SELECT * FROM movimientos_stock ms WHERE ms.materia_prima_id=4            


SELECT mp.id,mp.nombre,mp.unidad_medida,
   COALESCE(SUM(
        CASE
            WHEN m.tipo = 'ENTRADA' THEN m.cantidad
            WHEN m.tipo = 'SALIDA' THEN -m.cantidad
        END
   ),0) AS stock
FROM materias_primas mp
LEFT JOIN movimientos_stock m ON m.materia_prima_id = mp.id
GROUP BY mp.id
ORDER BY mp.nombre

SELECT 
            m.created_at,
            m.tipo,
            m.origen,
            m.referencia_id,
            m.cantidad,
            m.observaciones,
            u.nombre AS usuario,
            p.nombre AS producto,
            mp.nombre AS materia_prima
            FROM movimientos_stock m
            JOIN users u ON u.id = m.usuario_id
            LEFT JOIN productos p ON p.id = m.producto_id
            LEFT JOIN materias_primas mp ON mp.id = m.materia_prima_id
            ORDER BY m.created_at DESC
            
SHOW PROCESSLIST;

SELECT
    mp.stock_actual,

    IFNULL((
        SELECT SUM(cantidad)
        FROM reservas_materia_prima r
        WHERE r.materia_prima_id = mp.id
        AND r.estado = 'RESERVADO'
    ),0) AS reservado,

    IFNULL((
        SELECT SUM(ocd.cantidad)
        FROM ordenes_compra_detalle ocd
        JOIN ordenes_compra oc ON oc.id = ocd.orden_compra_id
        WHERE ocd.materia_prima_id = mp.id
        AND oc.estado IN ('BORRADOR','PENDIENTE')
    ),0) AS en_compra

FROM materias_primas mp
WHERE mp.id = 11

SELECT
    mp.id,
    mp.nombre,
    mp.unidad_medida,
    COALESCE(SUM(
        CASE
            WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
            WHEN m.tipo = 'SALIDA' THEN -m.cantidad
        END
    ),0) AS stock,
   (SELECT SUM(cantidad) FROM reservas_materia_prima WHERE materia_prima_id=mp.id) AS stockreserva
FROM materias_primas mp
LEFT JOIN movimientos_stock m ON m.materia_prima_id = mp.id
GROUP BY mp.id
ORDER BY mp.nombre

SELECT mp.nombre, r.cantidad,mp.precio_actual AS precio_unitario, mp.unidad_medida
FROM reservas_materia_prima r
JOIN materias_primas mp ON mp.id = r.materia_prima_id
WHERE r.orden_produccion_id = 23

SELECT opd.*,p.nombre AS producto,u.nombre AS nombre_user
FROM orden_produccion_detalle opd
LEFT JOIN users u ON u.id=opd.user_id
LEFT JOIN productos p ON p.id=opd.producto_id
WHERE opd.orden_id = 15
ORDER BY opd.registred_at ASC;

SELECT SUM(cantidad_producida) FROM orden_produccion_detalle
                WHERE orden_id=23


SELECT opd.*,p.nombre AS producto,u.nombre AS nombre_user
            FROM orden_produccion_detalle opd
            LEFT JOIN users u ON u.id=opd.user_id
            LEFT JOIN productos p ON p.id=opd.producto_id
            WHERE opd.orden_id = 41
            ORDER BY opd.registred_at ASC

SELECT * FROM recetas_detalle WHERE id= 27
SELECT MAX(PROD.ID) FROM ordenes_produccion PROD


