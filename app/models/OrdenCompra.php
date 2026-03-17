<?php

class OrdenCompra extends Model
{
    protected string $table = 'ordenes_compra';
    public function all()
    {
        return $this->db->query(
            "SELECT oc.*, p.razon_social
             FROM ordenes_compra oc
             JOIN proveedores p ON p.id = oc.proveedor_id
             ORDER BY oc.created_at DESC"
        )->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ordenes_compra (proveedor_id, usuario_id)
             VALUES (:p, :u)"
        );
        $stmt->execute([
            'p' => $data['proveedor_id'],
            'u' => $data['usuario_id']
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function addDetalle(int $ocId, int $mpId, float $cantidad,float $precioUnitario,int $moneda): void // chequear si agrego detalle individualmente o en lote -> respndido en el controlador
    {
        $this->db->prepare(
            "INSERT INTO ordenes_compra_detalle
             (orden_compra_id, materia_prima_id, cantidad, precio_unitario, moneda)
             VALUES (:o,:m,:c,:p,:mon)"
        )->execute([
            'o' => $ocId,
            'm' => $mpId,
            'c' => $cantidad,
            'p' => $precioUnitario,
            'mon' => $moneda
        ]);
    }

    public function find(int $id)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM ordenes_compra WHERE id = :id"
        );
        $stmt->execute(['id'=>$id]);
        return $stmt->fetch();
    }

    public function detalle(int $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*, mp.nombre, mp.unidad_medida
             FROM ordenes_compra_detalle d
             JOIN materias_primas mp ON mp.id = d.materia_prima_id
             WHERE d.orden_compra_id = :id"
        );
        $stmt->execute(['id'=>$id]);
        return $stmt->fetchAll();
    }

    public function aprobar(int $id): void
    {
        $this->db->prepare(
            "UPDATE ordenes_compra
             SET estado = 'APROBADA'
             WHERE id = :id"
        )->execute(['id'=>$id]);
    }

    public function findWithDetalle(int $id): ?array
    {
        // 1️⃣ CABECERA
        $stmt = $this->db->prepare(
            "SELECT 
                oc.*,
                p.razon_social
            FROM ordenes_compra oc
            JOIN proveedores p ON p.id = oc.proveedor_id
            WHERE oc.id = ?"
        );
        $stmt->execute([$id]);
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$orden) {
            return null;
        }

        // 2️⃣ DETALLE con recibidas / faltantes
        $stmt = $this->db->prepare(
            "SELECT 
                d.materia_prima_id,d.precio_unitario,mon.logo AS moneda,
                mp.nombre,
                mp.unidad_medida,
                d.cantidad AS pedida,
                COALESCE(SUM(idt.cantidad), 0) AS recibida,
                (d.cantidad - COALESCE(SUM(idt.cantidad), 0)) AS faltante
            FROM ordenes_compra_detalle d
            JOIN materias_primas mp 
                ON mp.id = d.materia_prima_id
            LEFT JOIN ingresos_mercaderia i
                ON i.orden_compra_id = d.orden_compra_id
            LEFT JOIN ingresos_mercaderia_detalle idt
                ON idt.ingreso_id = i.id
                AND idt.materia_prima_id = d.materia_prima_id
            LEFT JOIN monedas mon
                ON mon.id_monedas = d.moneda
            WHERE d.orden_compra_id = ?
            GROUP BY d.materia_prima_id, mp.nombre, mp.unidad_medida, d.cantidad"
        );
        $stmt->execute([$id]);
        $orden['detalle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $orden;
    }
    public function update(int $id, array $data): bool
    {
        try {
            $this->db->beginTransaction();

            // 1️⃣ Actualizar cabecera
            $this->db->prepare(
                "UPDATE ordenes_compra
                SET proveedor_id = :p
                WHERE id = :id AND estado = 'PENDIENTE'"
            )->execute([
                'p'  => $data['proveedor_id'],
                'id' => $id
            ]);

            // 2️⃣ Borrar detalle actual
            $this->db->prepare(
                "DELETE FROM ordenes_compra_detalle
                WHERE orden_compra_id = ?"
            )->execute([$id]);

            // 3️⃣ Insertar nuevo detalle
            foreach ($data['items'] as $item) {
                if ($item['cantidad'] <= 0) continue;

                $this->db->prepare(
                    "INSERT INTO ordenes_compra_detalle
                    (orden_compra_id, materia_prima_id, cantidad,precio_unitario)
                    VALUES (?, ?, ?, ?)"
                )->execute([
                    $id,
                    $item['materia_prima_id'],
                    $item['cantidad'],
                    $item['precio_unitario']
                ]);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            die('Error al actualizar OC: '.$e->getMessage());
        }
    }

    public function materiasPrimas(): array
    {
        return $this->db
            ->query("SELECT id, nombre FROM materias_primas ORDER BY nombre")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function proveedores(): array
    {
        return $this->db
            ->query("SELECT id, razon_social FROM proveedores ORDER BY razon_social")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function anular(int $id): void
    {
        $this->db->prepare(
            "UPDATE ordenes_compra
             SET estado = 'ANULADA'
             WHERE id = :id"
        )->execute(['id'=>$id]);
    }

    public function calcularStockProyectado(int $materiaId) {
        try {
            $stmt = $this->db->prepare("
                    SELECT
                        mp.stock_actual,

                        IFNULL((
                            SELECT SUM(r.cantidad)
                            FROM reservas_materia_prima r
                            WHERE r.materia_prima_id = mp.id
                            AND r.estado = 'RESERVADO'
                        ),0) AS reservado,

                        IFNULL((
                            SELECT SUM(ocd.cantidad)
                            FROM ordenes_compra_detalle ocd
                            JOIN ordenes_compra oc
                                ON oc.id = ocd.orden_compra_id
                            WHERE ocd.materia_prima_id = mp.id
                            AND oc.estado IN ('BORRADOR','PENDIENTE')
                        ),0) AS en_compra

                    FROM materias_primas mp
                    WHERE mp.id = ?
                ");
                $stmt->execute([$materiaId]);
                $stockData = $stmt->fetch(PDO::FETCH_ASSOC);
                return $stockData;
        } catch (Exception $e) {
            error_log('Error al calcular el sotck proyectado: '.$e->getMessage());
        }

    }

    public function ocExistente(int $mp_id){
        error_log('funcion ocExistente con mp: '.$mp_id.' ok');
        $stmt = $this->db->prepare("
                        SELECT oc.id
                        FROM ordenes_compra oc
                        LEFT JOIN ordenes_compra_detalle ocd ON ocd.orden_compra_id=oc.id
                        WHERE oc.estado IN ('BORRADOR','PENDIENTE') AND ocd.materia_prima_id=?
                        ORDER BY id DESC
                        LIMIT 1
                    ");
        $stmt->execute([$mp_id]);
        $ocExistente = $stmt->fetch(PDO::FETCH_COLUMN);
        error_log('funcion ocExistente devuelve: '.print_r($ocExistente,true));
        return (int)$ocExistente;
    }

    public function crearOc(){ //funcion para crear una cabecera de un oc desde el controloador que es llamado para crear OC desde faltantes
        try{
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("
                                INSERT INTO ordenes_compra
                                (proveedor_id, estado, usuario_id, created_at)
                                VALUES (0, 'PENDIENTE', ?, NOW())
                            ");
            $stmt->execute([$_SESSION['user_id']]);
            $ordenCompraId = $this->db->lastInsertId(); // devuelvo el id de una oc que no existia y se creo nueva
            $this->db->commit();
            error_log('Funcion crearoc desde faltantes devuelve id de oc nueva: '.$ordenCompraId);
            return (int)$ordenCompraId;
        }catch(Exception $e){
            $this->db->rollBack();
            error_log('Error al crear la cabecera de OC para ordenes automaticas al momento de generar produccion, '.$e->getMessage());
            return (int)0;
        }
    }

    public function updateMpOc(int $id_oc, int $id_mp, $cant_add): void{
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("
                            UPDATE ordenes_compra_detalle
                            SET cantidad = cantidad + ?
                            WHERE orden_compra_id = ? and materia_prima_id =?
                            ");
            $stmt->execute([$cant_add,$id_oc,$id_mp]);
            $this->db->commit();
            error_log('Funcion actualizar cant de mp '.$id_mp.' en oc '.$id_oc.', desde proceso oc faltantes');
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error al actualizar la cantidad de una mp existente en una oc(proceso desde creacion con faltantes), '.$e->getMessage());
        }
    }

    public function addMpOc($id_oc,$id_mp,$cant_add): void{
        try {
            $this->db->beginTransaction();
            $stmt=$this->db->prepare("SELECT MAX(id) FROM ordenes_produccion");
            $stmt->execute();
            $oc_id=(int) $stmt->fetch(PDO::FETCH_COLUMN);
            $oc_id ++;
            $stmt = $this->db->prepare("
                        INSERT INTO ordenes_compra_detalle
                        (orden_compra_id, materia_prima_id, cantidad, precio_unitario, moneda,referencia_oc,referencia_id)
                        VALUES (?, ?, ?, 0, 1,'STOCK_OP',?)
                    ");
            $stmt->execute([$id_oc,$id_mp,$cant_add,$oc_id]);
            $this->db->commit();
            error_log('Funcion actualizar cant de mp '.$id_mp.' en oc '.$id_oc.', desde proceso oc faltantes');
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error al agregar la cantidad de una mp en una oc(proceso desde creacion con faltantes), '.$e->getMessage());
        }
        
    }
    
}