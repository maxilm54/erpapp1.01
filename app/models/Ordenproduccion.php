<?php
class OrdenProduccion extends Model
{
    public function all(): array
    {
        return $this->db->query("
            SELECT op.*, p.nombre AS producto
            FROM ordenes_produccion op
            JOIN productos p ON p.id = op.producto_id
            ORDER BY op.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function crear(array $data): int //creo OP y reservo MP, pero si no hay stock de MP lanzo excepcion y rollback para no crear nada.
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                INSERT INTO ordenes_produccion
                (producto_id,receta_id,cantidad,fecha_entrega,observaciones,usuario_id)
                VALUES (?,?,?,?,?,?)
            ");
            $stmt->execute([
                $data['producto_id'],
                $data['receta_id'],
                $data['cantidad'],
                $data['f_entrega'],
                $data['observaciones'],
                $data['usuario_id']
            ]);

            $ordenId = $this->db->lastInsertId();

            $this->reservarMateriaPrima($ordenId); // hago la reserva de MP, si falla se lanza excepcion y hace rollback

            $this->db->commit();
            return $ordenId;

        } catch (Exception $e) {
            $this->db->rollBack(); //revierto los cambios si hubo error
            error_log(__FILE__ . ':' . __LINE__ . ' - Error al crear la OP.'.$e->getMessage());
            throw $e;
        }
    }
    
    private function reservarMateriaPrima(int $ordenId): void
    {
        $orden = $this->find($ordenId);
        $receta = (new Receta())->detalle($orden['receta_id']);

        foreach ($receta as $item) {
            $cantidadNecesaria = $item['cantidad'] * $orden['cantidad']; //orden cantidad es la cantidad a producir, e tiem cantidad es la cantidad de ese item

            // validar stock disponible si no esta disponible reservo en (--)?
            if (!$this->stockDisponible($item['materia_prima_id'], $cantidadNecesaria)) {
                error_log(__FILE__.':'.__LINE__.'Se detecta una reserva de MP sin stock');
                /*throw new Exception(
                    "Stock insuficiente de {$item['nombre']}"
                );*/
            }
            //inserto en reserva y descuento en stock, real!
            $this->db->prepare("
                INSERT INTO reservas_materia_prima
                (orden_produccion_id, materia_prima_id, cantidad)
                VALUES (?, ?, ?)
            ")->execute([$ordenId,$item['materia_prima_id'],$cantidadNecesaria]);
            $this->db->prepare("
                INSERT INTO movimientos_stock
                (tipo,origen,referencia_id,materia_prima_id,cantidad,observaciones,usuario_id,created_at)
                values ('SALIDA','RESERVA',?,?,?,'Reserva de MP para Prden de Produccion',?,?)
            ")->execute([$ordenId,$item['materia_prima_id'],$cantidadNecesaria,$_SESSION['user_id'],FECHA_ACTUAL]);
        }
    }

    public function find(int $id): ?array //devuelve la info de la orden de produccion
    {
        $stmt = $this->db->prepare("
            SELECT op.*, p.nombre AS producto,u.nombre AS nombre_user,p.id AS producto_id
            FROM ordenes_produccion op
            JOIN productos p ON p.id = op.producto_id
            LEFT JOIN users u ON u.id=op.usuario_id
            WHERE op.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function findopdetalle(int $id): ?array //devuelve el detalle de la info
    {
        $stmt = $this->db->prepare("
            SELECT opd.*,p.nombre AS producto,u.nombre AS nombre_user
            FROM orden_produccion_detalle opd
            LEFT JOIN users u ON u.id=opd.user_id
            LEFT JOIN productos p ON p.id=opd.producto_id
            WHERE opd.orden_id = ?
            ORDER BY opd.registred_at ASC
        ");
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findreservas(int $ordenId): array
    {
        $stmt = $this->db->prepare("
            SELECT mp.nombre, r.cantidad,mp.precio_actual AS precio_unitario, mp.unidad_medida
            FROM reservas_materia_prima r
            JOIN materias_primas mp ON mp.id = r.materia_prima_id
            WHERE r.orden_produccion_id = ?
        ");
        $stmt->execute([$ordenId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function stockDisponible(int $mpId, float $cantidad): bool
    {
        $stmt = $this->db->prepare("
            SELECT
              SUM(CASE WHEN tipo='ENTRADA' THEN cantidad ELSE 0 END) -
              SUM(CASE WHEN tipo='SALIDA' THEN cantidad ELSE 0 END) -
              IFNULL((SELECT SUM(cantidad) FROM reservas_materia_prima WHERE materia_prima_id=?),0)
            FROM movimientos_stock
            WHERE materia_prima_id=?
        ");
        $stmt->execute([$mpId, $mpId]);
        return (float)$stmt->fetchColumn() >= $cantidad;
    }

    public function stockDisponibleCantidad(int $mpId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(
                    CASE 
                        WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                        WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                    END
                ),0) AS stock
            FROM materias_primas mp
            LEFT JOIN movimientos_stock m ON m.materia_prima_id = mp.id
            WHERE mp.id=? LIMIT 1
        ");
        $stmt->execute([$mpId]);
        return (float)$stmt->fetchColumn();
    }

    public function chequearStockReceta(int $recetaId, float $cantidad): array //chequeo de receta con la cantidad a producir de esa receta.
    {
        $receta = (new Receta())->detalle($recetaId); //traigo los datos de la receta, que incluye la cantidad de cada materia prima necesaria para producir 1 unidad del producto final. Luego multiplico esa cantidad por la cantidad a producir que me pasan por parametro, y chequeo si hay stock disponible para cada materia prima. Devuelvo un array con el estado general (ok, warning o error) y un detalle de las materias primas faltantes si las hubiera.

        $faltantes = [];
        $ok = 0;
        error_log(print_r($receta, true));
        foreach ($receta as $item) {
            $necesario = $item['cantidad'] * $cantidad; //cantidad a producir * cantidad de ese item que se necesita para producir 1 unidad del producto final, me da la cantidad total necesaria de ese item para producir la cantidad deseada del producto final
            $disponible = $this->stockDisponibleCantidad($item['materia_prima_id']);

            if ($disponible < $necesario) { //comprar lo disponible y mostrar lo faltante, o directamente mostrar que no hay stock suficiente para esa cantidad a producir?
                $faltantes[] = [
                    'materia_prima_id' => $item['materia_prima_id'],
                    'materia_prima' => $item['nombre'],
                    'necesario'     => $necesario,
                    'disponible'    => $disponible,
                    'faltante'      => $necesario - $disponible
                ];
            } else {
                $ok++;
            }
            
        }

        $estado = 'ok'; // normalmente devuelve ok salvo no haya stock o si hay varios items y alguno no tiene stocks muestra warning.
        if (count($faltantes) === count($receta)) {
            $estado = 'error';
        } elseif (count($faltantes) > 0) {
            $estado = 'warning';
        }

        return [
            'estado'    => $estado,
            'faltantes' => $faltantes
        ];
    }
    public function actualizarEstado(int $id, string $estado): void // aca tengo que cambiar para corregir cuando se tomas las reservas o se devuelven
    {
        error_log($id.'-'.$estado);
        $stmt = $this->db->prepare("
            UPDATE ordenes_produccion
            SET estado = ?
            WHERE id = ?
        ");
        $stmt->execute([$estado, $id]);
    }

    public function avances(array $data){ //llamo desve avances para regstrar producciones, total o parcial
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO orden_produccion_detalle
                (orden_id,producto_id,registred_at,cantidad_producida,observaciones,user_id)
                VALUES (?,?,?,?,?,?)
            ");
            $stmt->execute([
                $data['orden_id'],
                $data['producto_id'],
                $data['f_registro'],
                $data['cantidad_producida'],
                $data['observaciones'],
                $data['usuario_id']
            ]);
            //cambiar el estado de la orden si se completo o no
            $dato_opid=(int) $data['orden_id'];
            $change_estado= $this->estadoChange($dato_opid); // validar true.
            error_log('Cambio de estado:'.$change_estado);
            //cambiar el estado de la materia prima solo si lo producido alcanza lo pedido
            //si esta completa eliminar el dato de la tabla reserva MP antes cambiando el estado reservado a consumido.
            //a medida que se va agregando producccion se debe ir aumentando el stock del producto.
            if($change_estado === true){
                $this->ajustarProducto($data['producto_id'],$data['cantidad_producida'],$data['observaciones'],$data['usuario_id'],'ENTRADA',$data['orden_id']);
            }
            $this->db->commit();
            $_SESSION['success']="Se ha registrado la Produccion, Descontado Materia Prima e Ingresado el Stock del Producto.";
            return (int) $data['orden_id'];
        }catch(Exception $e){
            $this->db->rollBack();
            error_log($e->getMessage());
            $_SESSION['error']="Ocurrio un error al generar el registro de produccion. E: ".$e->getMessage();
            return (int) $data['orden_id'];
        }
    }

    public function estadoChange($id_orden): bool{
        /* TODO: cambiar el estado de una orden de produccion implica lo soguiente:
        *PRIMERO comprobar si la cantidad que se produce es la misma que se pide para cerrar la orden de un solo paso.
        *SEGUNDO la materia prima virtualmente esta en reserva, a medida que se avanza en la op se debe ir eliminando la cantidad de stock reservado, esto
        *implica que si se produce total se elimina completa pero antes se pone el estado de reserva en "CONSUMIDO" para que al borrar se ejecuta el trigger y manda a la tabla de hisotrico
        *si la produccion va en etapas entonces debe ir descontando en reserva y aumentando en historico con la leyenda "PRODUCIDO"
        *TERCERO en ambos casos se debe incrementar en stock de producto la cantidad producida.
        */
        error_log("Se hizo un registro de avance para la orden: ".$id_orden);
        try{
            $pedidas=$this->find($id_orden);
            $stmt = $this->db->prepare("
                SELECT SUM(cantidad_producida) FROM orden_produccion_detalle
                WHERE orden_id=?
            ");
            $stmt->execute([$id_orden]);
            $producidas= (float)$stmt->fetchColumn();
            $total=(float) $pedidas['cantidad'] - $producidas; // si este valor da 0 la produccion esta completa sino sigue en produccion
            error_log((float)$pedidas['cantidad'].'-'.(float)$producidas.'- Total:'.$total);
            if($total==0){ // aca solo entra si la OP se completo de forma total en este registro de avance
                error_log('If si la producida es = a la pedida');
                $stmt = $this->db->prepare("
                    UPDATE ordenes_produccion
                    SET estado = ?,finalizada_at=?
                    WHERE id = ?
                ");
                $stmt->execute(['FINALIZADA',FECHA_ACTUAL, $id_orden]);
                $stmt = $this->db->prepare("
                    UPDATE reservas_materia_prima
                    SET estado = ?,procesado_at=?
                    WHERE orden_produccion_id = ?
                ");
                $stmt->execute(['CONSUMIDO',FECHA_ACTUAL, $id_orden]);
                $stmt = $this->db->prepare("
                    DELETE FROM reservas_materia_prima
                    WHERE orden_produccion_id = ?
                ");
                $stmt->execute([ $id_orden]);
            }
            return true;
        }catch(Exception $e){
            $this->db->rollBack();
            error_log($e->getMessage());
            $_SESSION['error']="Ocurrio un error al generar el cambio de estado en orden de produccion. E: ".$e->getMessage();
            //redirigir para mostrar el error
            return false;
        }

    }

    // esta funcion ajusta el stock de productos impactando en movimientos de stock.
    public function ajustarProducto(int $productoId,float $cantidad,string $motivo,int $usuarioId,string $tipo,int $orden_id): void {
        try {
            if ($cantidad == 0) {
            throw new Exception('La cantidad no puede ser 0');
            }
            $this->db->prepare("
                INSERT INTO movimientos_stock
                (tipo, origen, producto_id, cantidad, observaciones, usuario_id,referencia_id)
                VALUES (?, 'OP', ?, ?, ?, ?,?)
            ")->execute([
                $tipo,
                $productoId,
                $cantidad,
                $motivo,
                $usuarioId,
                $orden_id
            ]);
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al ajustar stock: ' . $e->getMessage();
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cancelarProduccion(int $id):bool{
        error_log("Entro a la funcion de cancelaion");
        try {
            $this->db->beginTransaction();
            //en tabla reserva mp cambio estado de cada mp de la OP a liberado y la borro, luego esas cantidades las sumo al stock de mP
            //consulto lo producido:
            $producido=$this->cantidadProducidaOP($id);
            $stmt=$this->db->prepare("SELECT cantidad FROM ordenes_produccion where id=?");
            $stmt->execute([$id]);
            $pedido=(int)$stmt->fetchColumn();
            $faltante=$pedido-$producido;
            $stmt=$this->db->prepare("SELECT receta_id FROM ordenes_produccion where id=?");
            $stmt->execute([$id]);
            $receta=(int)$stmt->fetchColumn();
            error_log("Pedido: ".print_r($pedido,true)." Producida: ".print_r($producido,true)." Faltante: ".print_r($faltante,true)." Receta id: ".print_r($receta,true));
            $stmt=$this->db->prepare("SELECT * FROM recetas_detalle WHERE receta_id= ?");
            $stmt->execute([$receta]);
            $recetadetalle=$stmt->fetchALL(PDO::FETCH_ASSOC);
            error_log(print_r($recetadetalle,true));
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            //throw $th;
            error_log("Error al intentar anular/cancelar produccion, error: ".$e->getMessage());
            $this->db->rollBack();
            return false;
        }
    }

    private function cantidadProducidaOP(int $op_id){
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(cantidad_producida) FROM orden_produccion_detalle
                WHERE orden_id=?
            ");
            $stmt->execute([$op_id]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error al Consultar la cantidad prodcida de la orden $op_id para ejecutar la cancelacion. ".$e->getMessage());
            $_SESSION['error']="Error al Consultar la cantidad prodcida de la orden $op_id para ejecutar la cancelacion. ".$e->getMessage();
            return;
        }

    }

    //funcion para confirmar el avance registrado en avances. Esto confirma un solo registro por vez
    public function confirmaravance(int $id_avance):bool{
        try {
            $this->db->beginTransaction();
            $stmt=$this->db->prepare("UPDATE orden_produccion_detalle set confirma_produccion=? WHERE id_tbl_ordendetalle=?");
            $stmt->execute([FECHA_ACTUAL,$id_avance]);
            $this->db->commit();
            error_log("Regitrar el cierre del avance $id_avance. ".__FILE__.":".__LINE__);
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al intentar regitraar el cierre del avance $id_avance.". $e->getMessage().". ".__FILE__.":".__LINE__);
            return false;
        }
    }
    //devuelvo el id de una op luego de hacer un registro de avance
    public function numeroOP(int $id_avance):int {
        try {
            $stmt=$this->db->prepare("SELECT orden_id FROM orden_produccion_detalle WHERE id_tbl_ordendetalle=?");
            $stmt->execute([$id_avance]);
            $id_op=(int)$stmt->fetchColumn();
            return $id_op;
        } catch (Exception $e) {
            error_log("Error al intentar devolver el id de OP para el avance: $id_avance. ".$e->getMessage()."-".__FILE__.":".__LINE__);
            return 0;
        }
    } 

}