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
            //Validar cuando ingrese la mercaderia enviar a reserva.
            //Ahora solo detecta y muestra que no hay solo en un log, y avanza creando la reserva u la op, 
            //pero no es lo ideal, lo ideal seria que si no hay stock no deje crear la OP ni la reserva, y muestre un mensaje 
            //de error indicando que no hay stock suficiente para esa materia prima. Esto implica que el metodo 
            //reservarMateriaPrima debe lanzar una excepcion si detecta que no hay stock suficiente para alguna de las materias 
            //primas necesarias para esa receta, y el metodo crear debe manejar esa excepcion mostrando el mensaje de 
            //error correspondiente al usuario.
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
            SELECT mp.nombre, r.cantidad,mp.precio_actual AS precio_unitario, um.nombre as unidad_medida
            FROM reservas_materia_prima r
            JOIN materias_primas mp ON mp.id = r.materia_prima_id
            LEFT JOIN unidad_medida um ON um.id_medida=mp.id_unidadmedida
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

    public function avances(array $data){ //llamo desde controlador avances para registrar producciones, aca solo inicio el
                                        //proceso de produccion, luego falta registrar el fin de produccion,
                                        //ahi es cuando hago los mov de stock producto.
        $this->db->beginTransaction();
        try {
            //pasos:
            //1 registrar el avance en la tabla de detalle de OP, esto me permite llevar un historial de los avances registrados
                //con fecha y hora, cantidad producida, observaciones, etc.
            //paso 2,3y4 solo se completan cuanso se confirma el avance.
            //2 actualizar el stock de producto incrementando la cantidad producida,esto debe agregarse en movimientos de stock
                //con el tipo de movimiento "ENTRADA" y la referencia a la OP, esto me permite tener el stock real del producto
                //actualizado a medida que se van registrando los avances, sin necesidad de esperar a que se complete toda la producción.
            //3 actualizar el stock de materia prima consumida, esto me permite tener el stock real, tambien en tabla movimientos
            //4 actualizar el estado de la orden de produccion a EN_PRODUCCION si no estaba en ese estado,
                //esto me permite tener el estado real de la orden actualizado a medida que se van registrando los avances,
                //y poder mostrarlo en la vista de avance.
            //1
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
            //cambiar el estado de la orden si se completo o no, solo para finalizar?
            $dato_opid=(int) $data['orden_id']; //obtengo el id de produccion (tabla orden_produccion_detalle)
            $change_estado= $this->estadoChange($dato_opid); // validar true.
            error_log('Cambio de estado:'.$change_estado.' - '.__FILE__.":".__LINE__);
            //2 debo insertar en la tabla de mov el ingreso de producto que viene de produccion
            error_log('Insertando movimiento de stock para el producto: '.$data['producto_id'].' - '.__FILE__.":".__LINE__);
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
        *SEGUNDO la materia prima virtualmente esta en reserva, a medida que se avanza en la op se inserta la cantidad de mp con estado consumida, cuando reservado es = a (consumido+liberado) se cierra
        *asi se usa en la misma tabla el registro de reserva y el descuento a medida que se produce o si se anula.
        *TERCERO en ambos casos se debe incrementar en stock de producto la cantidad producida.
        */
        error_log("Se hizo un registro de avance para la orden: ".$id_orden." - ".__FILE__.":".__LINE__);
        try{
            $pedidas=$this->find($id_orden);
            $stmt = $this->db->prepare("
                SELECT SUM(cantidad_producida) FROM orden_produccion_detalle
                WHERE orden_id=?
            ");
            $stmt->execute([$id_orden]);
            $producidas= (float)$stmt->fetchColumn();
            $total=(float) $pedidas['cantidad'] - $producidas; // si este valor da 0 la produccion esta completa sino sigue en produccion
            error_log((float)$pedidas['cantidad'].'-'.(float)$producidas.'- Total:'.$total.' - '.__FILE__.":".__LINE__);
            if($total==0){ // aca solo entra si la OP se completo de forma total en este registro de avance
                error_log('If si la producida es = a la pedida, solo si el avance creado es el que completa lo OP'.' - '.__FILE__.":".__LINE__);
                $stmt = $this->db->prepare("
                    UPDATE ordenes_produccion
                    SET estado = ?,finalizada_at=?
                    WHERE id = ?
                ");
                $stmt->execute(['FINALIZADA',FECHA_ACTUAL, $id_orden]);
            }
            return true;
        }catch(Exception $e){
            $this->db->rollBack();
            error_log($e->getMessage().' - '.__FILE__.":".__LINE__);
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
            error_log('Error al ajustar stock: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            $_SESSION['error'] = 'Error al ajustar stock: ' . $e->getMessage();
            $this->db->rollBack();
            throw $e;
        }
    }
    //cancelamos un op, esto implica cambiar el estado de la OP a cancelada, eliminar las reservas de MP y 
    //devolver al stock la cantidad reservada, y si habia avances registrados cambiar el estado de esos avances a cancelados 
    //o eliminarlos segun como se quiera manejar el historial.
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
            error_log("Error al intentar anular/cancelar produccion, error: ".$e->getMessage().' - '.__FILE__.":".__LINE__);
            $this->db->rollBack();
            return false;
        }
    }
    //devolvemos la cantidad producida relacinada a una OP, dentro orden_produccion_detalle.
    private function cantidadProducidaOP(int $op_id){
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(cantidad_producida) FROM orden_produccion_detalle
                WHERE orden_id=?
            ");
            $stmt->execute([$op_id]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error al Consultar la cantidad prodcida de la orden $op_id para ejecutar la cancelacion. ".$e->getMessage().' - '.__FILE__.":".__LINE__);
            $_SESSION['error']="Error al Consultar la cantidad prodcida de la orden $op_id para ejecutar la cancelacion. ".$e->getMessage();
            return;
        }

    }

    //funcion para confirmar el avance registrado en avances. Esto confirma un solo registro por vez
    public function confirmaravance(int $id_avance):bool{
        try {
            $this->db->beginTransaction();
            $stmt=$this->db->prepare("UPDATE orden_produccion_detalle set confirma_produccion=? WHERE id_tbl_ordendetalle=?");//Solo confirmo que se ha cerrado un avance
            $stmt->execute([FECHA_ACTUAL,$id_avance]);
            //debo insertar un registro con la inforacion de lo producido en la tabla reservas_materia_prima
            //debo dar ingreso al producto que se acaba de producir en movimientos de stock
            $stmt=$this->db->prepare("SELECT orden_id,producto_id,cantidad_producida FROM orden_produccion_detalle WHERE id_tbl_ordendetalle=?");
            $stmt->execute([$id_avance]);
            $avance = $stmt->fetchAll(PDO::FETCH_ASSOC);//OP_id y cantidad producida en ese avance
            error_log('$avance:'.print_r($avance,true));
            $stmt=$this->db->prepare("SELECT receta_id FROM ordenes_produccion WHERE id=?");
            $stmt->execute([$avance[0]['orden_id']]);
            $receta_id=$stmt->fetchColumn();//id de receta para buscar las MP y las cantidades de cada MP
            error_log($receta_id);
            $stmt=$this->db->prepare("SELECT materia_prima_id,cantidad FROM recetas_detalle WHERE receta_id=?");
            $stmt->execute([$receta_id]);
            $materia_prima_id=$stmt->fetchAll(PDO::FETCH_ASSOC);// MP y cantidad de cada MP
            error_log(print_r($materia_prima_id,true));
            foreach ($materia_prima_id as $mp) {
                $cantidad_mp = $mp['cantidad'] * $avance[0]['cantidad_producida']; // cantidad de MP a consumir segun lo producido en este avance
                error_log("MP ID: {$mp['materia_prima_id']} - Cantidad a consumir: $cantidad_mp. ".__FILE__.":".__LINE__);
                //inserto el registro en movimientos de stock para descontar la materia prima consumida, con referencia a la OP y al avance, esto me permite tener el stock real actualizado a medida que se van registrando los avances, sin necesidad de esperar a que se complete toda la producción.
                try {
                    $this->db->prepare("
                        INSERT INTO reservas_materia_prima
                        (orden_produccion_id, materia_prima_id, cantidad, estado, procesado_at)
                        VALUES (?, ?, ?, 'CONSUMIDO', ?)
                    ")->execute([
                        $avance[0]['orden_id'],
                        $mp['materia_prima_id'],
                        $cantidad_mp,
                        FECHA_ACTUAL
                    ]);
                    //inserto el registro en la tabla reserva mp para generar el registro de uso de materia prima, con el estado consumido, esto me permite tener el registro de consumo de materia prima a medida que se van registrando los avances, sin necesidad de esperar a que se complete toda la producción.
                    error_log("RegistroOK - ".$avance[0]['orden_id']."-".$mp['materia_prima_id']."-".$cantidad_mp.' - '.__FILE__.":".__LINE__);
                } catch (Exception $e) {
                    error_log(print_r($e->getMessage(), true).'-'.__FILE__.":".__LINE__);
                }
            }
            //llamo a ajustar el ingreso del producto, esto inserta el movimiento de stock con el tipo ENTRADA y la referencia a la OP, esto me permite tener el stock real del producto actualizado a medida que se van registrando los avances, sin necesidad de esperar a que se complete toda la producción.
            $this->ajustarProducto($avance[0]['producto_id'],$avance[0]['cantidad_producida'],"Ingreso por produccion OP ID: ".$avance[0]['orden_id'],$_SESSION['user_id'],'ENTRADA',$avance[0]['orden_id']);

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

    public function producirvsStockMP(int $recetaId, float $cantidad,int $idOP): array //chequeo de receta con la cantidad a producir de esa receta.
    {
        $receta = (new Receta())->detalle($recetaId); //traigo los datos de la receta, que incluye la cantidad de cada materia prima necesaria para producir 1 unidad del producto final. Luego multiplico esa cantidad por la cantidad a producir que me pasan por parametro, y chequeo si hay stock disponible para cada materia prima. Devuelvo un array con el estado general (ok, warning o error) y un detalle de las materias primas faltantes si las hubiera.

        $faltantes = [];
        $ok = 0;
        error_log(print_r($receta, true)." - ".__FILE__.":".__LINE__);
        foreach ($receta as $item) {
            $necesario = $item['cantidad'] * $cantidad; //cantidad a producir * cantidad de ese item que se necesita para producir 1 unidad del producto final, me da la cantidad total necesaria de ese item para producir la cantidad deseada del producto final
            $disponible = $this->stockDisponibleCantidad($item['materia_prima_id']); // disponible en movimientos_stock (stock libre para usar)
            $disponible_reservado = $this->stockReservadoCantidad($item['materia_prima_id'], $idOP); // stock reservado  de mp para una OP especifica
            error_log("MP: {$item['nombre']} - Necesario: $necesario - Disponible: $disponible - Disponible Reservado: $disponible_reservado. ".__FILE__.":".__LINE__);

            if ($disponible_reservado < $necesario) { //comprar lo disponible y mostrar lo faltante, o directamente mostrar que no hay stock suficiente para esa cantidad a producir?
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
    //Devuelvo de la reserva de MP para un OP la cantidad Reservada - Consumida para tener el stock real que me queda para producir
    public function stockReservadoCantidad(int $mpId, int $idOP): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(
                SUM(
                    CASE
                        WHEN estado = 'RESERVADO' THEN cantidad
                        WHEN estado IN ('CONSUMIDO','LIBERADO') THEN -cantidad
                        ELSE 0
                    END
                ), 0
            ) AS saldo
            FROM reservas_materia_prima
            WHERE materia_prima_id = ?
            AND orden_produccion_id = ?;
        ");
        $stmt->execute([$mpId, $idOP]);
        return (float)$stmt->fetchColumn();
    }

}
