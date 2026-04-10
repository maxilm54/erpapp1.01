<?php

use function Safe\error_log;

require_once BASE_PATH.'/app/models/OrdenCompra.php';
class IngresoMercaderia extends Model
{
    public function all()
    {
        return $this->db->query("
            SELECT i.*, p.razon_social proveedor
            FROM ingresos_mercaderia i
            JOIN proveedores p ON p.id = i.proveedor_id
            ORDER BY i.fecha DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    // devulevo si o no si existe el remito para el proveedor
    public function findByRemitoProveedor(int $proveedorId, string $remito)
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as remito_existe FROM ingresos_mercaderia
             WHERE proveedor_id = :p AND remito = :r"
        );
        $stmt->execute([
            'p'=>$proveedorId,
            'r'=>$remito
        ]);
        $existente = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$existente['remito_existe'] > 0;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ingresos_mercaderia
            (orden_compra_id, proveedor_id, remito, usuario_id)
            VALUES (:o,:p,:r,:u)"
        );
        $stmt->execute([
            'o'=>$data['orden_compra_id'],
            'p'=>$data['proveedor_id'],
            'r'=>$data['remito'],
            'u'=>$data['usuario_id']
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function addDetalle(int $ingresoId, int $mpId, float $cantidad): void
    {
        $this->db->prepare(
            "INSERT INTO ingresos_mercaderia_detalle
             (ingreso_id, materia_prima_id, cantidad)
             VALUES (:i,:m,:c)"
        )->execute([
            'i'=>$ingresoId,
            'm'=>$mpId,
            'c'=>$cantidad
        ]);
    }
    //metodo completo para registrar el ingreso de mercaderia, ya sea Materia o Productos de reventa
    public function registrar(int $orden_id, int $proveedor_id, int $usuario_id, array $data): int
    {
        try {
            $this->db->beginTransaction();
            // 1️⃣ Validar remito único por proveedor, por segunda vez. Seguridad. OK
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM ingresos_mercaderia im
                 JOIN ordenes_compra oc ON oc.id = im.orden_compra_id
                 WHERE oc.proveedor_id = (
                     SELECT proveedor_id FROM ordenes_compra WHERE id = ?
                 )
                 AND im.remito = ?"
            );
            $stmt->execute([$orden_id, $data['remito']]);

            if ($stmt->fetchColumn() > 0) {
                error_log('error creado a proposito para revalidad el remito exixtente para el proveedor');
                throw new Exception('El número de remito ya fue ingresado para este proveedor. OC: '.$orden_id.', RE: '.$data['remito']);
            }

            // 2️⃣ Insertar cabecera ingreso 
            // buscamos agregar un ingreso para un mismo OC con un numero indicador secuencial
            // voy a tener uno o muchos ingresos de mercaderia para la misma oc, eso no es un problema es una de las tantas formas de trabajo que tienen los clientes.
            $stmt = $this->db->prepare(
                "INSERT INTO ingresos_mercaderia
                    (orden_compra_id, ing_num_indicador, proveedor_id, usuario_id, remito)
                SELECT ?, COALESCE(MAX(ing_num_indicador), 0) + 1, ?, ?, ?
                FROM ingresos_mercaderia
                WHERE orden_compra_id = ?"
            );
            $stmt->execute([$orden_id, $proveedor_id, $usuario_id, $data['remito'], $orden_id]);
            $ingreso_id = (int)$this->db->lastInsertId(); // con este numero de ingreso identifico para agergar el movimiento de stock

            // 3️⃣ Detalle + movimiento de stock
            foreach ($data['items'] as $materiaPrimaId => $cantidad) {//recorro cada items des ingreso
                $cantidad = (float)$cantidad;
                if($cantidad <= 0){
                    continue;// salto al siguiente si no tiene ingreso de cantidad
                }
                //consulto el metodo para comprbar que se este entregandp no mas de lo que falta en la orden
                $faltante = $this->faltantePorMateria($orden_id,$materiaPrimaId);

                if ($cantidad > $faltante) {
                    error_log("Se intento ingresar mas de la cuenta. No se puede ingresar $cantidad. Faltante: $faltante".__FILE__.' - '.__LINE__);
                    throw new Exception("Error al ingresar mercadería oc: $orden_id. No se puede ingresar $cantidad. Faltante: $faltante");
                }

                // preparo para insertar el Detalle ingreso
                $this->db->prepare(
                    "INSERT INTO ingresos_mercaderia_detalle
                     (ingreso_id, materia_prima_id, oc_id,cantidad)
                     VALUES (?, ?, ?, ?)"
                )->execute([
                    $ingreso_id,
                    $materiaPrimaId,
                    $orden_id,
                    $cantidad
                ]);
                //insertar el ingreso en tabla movimientos_stock para tener un historico de movimientos, 
                //con el numero de ingreso puedo identificar el movimiento con este ingreso.
                //la tabla movimientos_stock es la que maneja el stock de productos y MP
                $this->db->prepare(
                    "INSERT INTO movimientos_stock
                     (materia_prima_id, origen, tipo, cantidad, referencia_id,usuario_id,observaciones)
                     VALUES (?,'OC', 'ENTRADA', ?, ?, ?, 'Ingreso mercadería')"
                )->execute([
                    $materiaPrimaId,
                    $cantidad,
                    $ingreso_id,
                    $usuario_id
                ]);
                error_log("(post inster mov_stock)Ingreso mercadería: Ingreso #$ingreso_id - Materia Prima ID: $materiaPrimaId - Cantidad: $cantidad");
            }

            // 4️⃣ Commit
            $this->db->commit();
            $this->actualizarEstadoOrden($orden_id); // aca actiualizo para saber si la orden sigue parcial o se recibio completa
            return $ingreso_id;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log($e->getMessage() . ' in ' . __FILE__ . ' on line ' . __LINE__);
            $_SESSION['error'] = 'Error al registrar el ingreso: ' . $e->getMessage();
            header('Location: '.BASE_URL.'/ingresosmercaderia/create/'.$orden_id);
            exit;
        }
    }

    public function findWithDetalle($id)
    {
        // 1️⃣ Cabecera del ingreso
        $stmt = $this->db->prepare("
            SELECT i.*, p.razon_social AS proveedor
            FROM ingresos_mercaderia i
            JOIN proveedores p ON p.id = i.proveedor_id
            JOIN ordenes_compra oc ON oc.id = i.orden_compra_id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $ingreso = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ingreso) {
            return null;
        }

        // 2️⃣ Detalle con control de pedida / ingresada / faltante
        $stmt = $this->db->prepare("
            SELECT 
                mp.nombre,
                oc.cantidad AS pedida,idt.materia_prima_id,
                SUM(idt.cantidad) AS ingresada,
                (oc.cantidad - SUM(idt.cantidad)) AS faltante
            FROM ingresos_mercaderia_detalle idt
            JOIN materias_primas mp ON mp.id = idt.materia_prima_id
            JOIN ordenes_compra_detalle oc
                ON oc.materia_prima_id = idt.materia_prima_id
                AND oc.orden_compra_id = ?
            WHERE idt.ingreso_id = ?
            GROUP BY idt.materia_prima_id
        ");
        $stmt->execute([
            $ingreso['orden_compra_id'],
            $id
        ]);

        $ingreso['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $ingreso;
    }

    private function actualizarEstadoOrden($orden_id) // con el numero de orden debo calcular lo recibido contra lo pedido para emitir el parcial o total
    {
        $stmt = $this->db->prepare("
            SELECT SUM(ing_det.cantidad) AS cantidad
            FROM ingresos_mercaderia_detalle ing_det
            WHERE ing_det.oc_id=?
            
        ");
        $stmt->execute([$orden_id]);
        $ingreso_oc_total = (float)$stmt->fetchColumn();
        try{
            $stmt = $this->db->prepare("
            SELECT SUM(oc.cantidad) AS cantidad
            FROM ordenes_compra_detalle oc
            WHERE oc.orden_compra_id=?
            
            ");
            $stmt->execute([$orden_id]);
            $oc_total = (float)$stmt->fetchColumn();
        }catch(Exception $e){
            $oc_total = 0;
        }
        
        
        $faltante = $oc_total - $ingreso_oc_total;
        error_log("2-orden #$orden_id: $oc_total - $ingreso_oc_total = $faltante");
        if ($faltante <= 0) {
            $this->db->prepare(
                "UPDATE ordenes_compra SET estado = 'RECIBIDA' WHERE id = ?"
            )->execute([$orden_id]);
        } else {
            $this->db->prepare(
                "UPDATE ordenes_compra SET estado = 'PARCIAL' WHERE id = ?"
            )->execute([$orden_id]);
        }
    }

    public function historicoIngresosPorOrden(int $ordenCompraId, int $ingreso_num): array
    { // en esta consulta traigo todos los ingresos anteriores a este numero de ingreso para mostrar en el detalle del ingreso actual
        //error_log("historicoIngresosPorOrden - orden: $ordenCompraId , ingreso num: $ingreso_num");
        $stmt = $this->db->prepare("
            SELECT mp.id, COALESCE(SUM(ing_det.cantidad), 0) AS total_cantidad 
            FROM materias_primas mp 
            LEFT JOIN ingresos_mercaderia_detalle ing_det ON ing_det.materia_prima_id = mp.id AND ing_det.oc_id = ? 
            LEFT JOIN ingresos_mercaderia ing_cab ON ing_cab.id = ing_det.ingreso_id  
            WHERE ing_cab.ing_num_indicador < ?
            GROUP BY mp.id,ing_det.materia_prima_id;
        ");
        $stmt->execute([$ordenCompraId, $ingreso_num]);
        $faltantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $faltantes;

    }
    //funcion que devuleve el faltante de ingreso de cada MP en una OC
    public function faltantePorMateria(int $ocId, int $mpId): float
    {
        $stmt = $this->db->prepare("
            SELECT 
                d.cantidad - COALESCE(SUM(idt.cantidad),0) AS faltante
            FROM ordenes_compra_detalle d
            LEFT JOIN ingresos_mercaderia i
                ON i.orden_compra_id = d.orden_compra_id
            LEFT JOIN ingresos_mercaderia_detalle idt
                ON idt.ingreso_id = i.id
                AND idt.materia_prima_id = d.materia_prima_id
            WHERE d.orden_compra_id = ?
            AND d.materia_prima_id = ?
            GROUP BY d.cantidad
        ");
        $stmt->execute([$ocId, $mpId]);

        return (float)($stmt->fetchColumn() ?? 0);
    }

    public function historialPorOrden(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                i.id,
                i.fecha,
                i.remito,
                u.nombre AS usuario,
                mp.nombre AS materia_prima,
                idt.cantidad
            FROM ingresos_mercaderia i
            JOIN ingresos_mercaderia_detalle idt ON idt.ingreso_id = i.id
            JOIN materias_primas mp ON mp.id = idt.materia_prima_id
            JOIN users u ON u.id = i.usuario_id
            ORDER BY i.fecha DESC, i.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}