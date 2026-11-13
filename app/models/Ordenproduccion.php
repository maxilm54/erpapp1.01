<?php
require_once BASE_PATH . '/app/core/Model.php';
require_once BASE_PATH . '/app/models/Receta.php';
require_once BASE_PATH . '/app/models/Deposito.php';
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

            $this->reservarMateriaPrima($ordenId, $data['deposito_items'] ?? []); // hago la reserva de MP, si falla se lanza excepcion y hace rollback

            $this->db->commit();
            return $ordenId;

        } catch (Exception $e) {
            $this->db->rollBack(); //revierto los cambios si hubo error
            error_log(__FILE__ . ':' . __LINE__ . ' - Error al crear la OP.'.$e->getMessage());
            throw $e;
        }
    }
    
    private function reservarMateriaPrima(int $ordenId, array $depositosSeleccionados = []): void
    {
        $orden = $this->find($ordenId);
        $receta = (new Receta())->detalle($orden['receta_id']);

        foreach ($receta as $item) {
            $mpId = (int)$item['materia_prima_id'];
            $cantidadNecesaria = (float)$item['cantidad'] * (float)$orden['cantidad'];

            // validar stock disponible si no esta disponible reservo en (--)?
            //Validar cuando ingrese la mercaderia enviar a reserva.
            //Ahora solo detecta y muestra que no hay solo en un log, y avanza creando la reserva u la op, 
            //pero no es lo ideal, lo ideal seria que si no hay stock no deje crear la OP ni la reserva, y muestre un mensaje 
            //de error indicando que no hay stock suficiente para esa materia prima. Esto implica que el metodo 
            //reservarMateriaPrima debe lanzar una excepcion si detecta que no hay stock suficiente para alguna de las materias 
            //primas necesarias para esa receta, y el metodo crear debe manejar esa excepcion mostrando el mensaje de 
            //error correspondiente al usuario.
            if (!$this->stockDisponible($mpId, $cantidadNecesaria)) {
                error_log(__FILE__.':'.__LINE__.'Se detecta una reserva de MP sin stock');
                /*throw new Exception(
                    "Stock insuficiente de {$item['nombre']}"
                );*/
            }

            // El operario puede repartir la cantidad entre varios depositos:
            // deposito_items[mpId][depositoId] = cantidad.
            $asignaciones = $depositosSeleccionados[$mpId] ?? $depositosSeleccionados[(string)$mpId] ?? null;
            $fuentes = $this->resolverFuentesReserva(
                $mpId,
                $cantidadNecesaria,
                $asignaciones,
                (string)($item['nombre'] ?? '')
            );

            // Una reserva y un movimiento de salida por cada deposito de origen.
            foreach ($fuentes as $fuente) {
                $depositoId = (int)$fuente['deposito_id'];
                $cantidadFuente = (float)$fuente['cantidad'];
                if ($cantidadFuente <= 0) continue;

                $this->db->prepare("
                    INSERT INTO reservas_materia_prima
                    (orden_produccion_id, materia_prima_id, deposito_id, cantidad, cantidad_asignada)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$ordenId, $mpId, $depositoId, $cantidadFuente, $cantidadFuente]);
                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo,origen,referencia_id,materia_prima_id,deposito_id,cantidad,observaciones,usuario_id,created_at)
                    values ('SALIDA','RESERVA',?,?,?,?,'Reserva de MP para Orden de Produccion',?,?)
                ")->execute([$ordenId, $mpId, $depositoId, $cantidadFuente, $_SESSION['user_id'], FECHA_ACTUAL]);
            }
        }
    }

    /**
     * Stock actual de una MP en cada deposito activo (sin contar el de transito).
     */
    public function stockPorDepositoMP(int $mpId): array
    {
        $principalId = (new Deposito())->principalId();

        $stmt = $this->db->prepare("
            SELECT d.id, d.nombre, d.es_sobrante,
                   COALESCE(SUM(
                       CASE
                           WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                           WHEN m.tipo = 'SALIDA' THEN -m.cantidad
                       END
                   ), 0) AS stock
            FROM depositos d
            LEFT JOIN movimientos_stock m
                   ON m.materia_prima_id = ?
                  AND (m.deposito_id = d.id OR (m.deposito_id IS NULL AND d.id = ?))
            WHERE d.activo = 1 AND d.es_transito = 0
            GROUP BY d.id, d.nombre, d.es_sobrante
            ORDER BY d.es_sobrante ASC, (d.id = ?) DESC, d.nombre
        ");
        $stmt->execute([$mpId, $principalId, $principalId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Genera los movimientos automaticos de sobrante de presentacion para una OP.
     *
     * Cuando una MP se consume desde un unico deposito no sobrante y tiene
     * presentacion (ej. barra de 6 M), se toma la cantidad necesaria de barras
     * completas y el resto de la ultima barra se mueve al deposito "MP sobrante":
     *   SALIDA  origen  -> sobrante
     *   ENTRADA sobrante <- sobrante
     * El stock total no cambia, solo se separa el resto.
     *
     * Es idempotente y ajustable: compara el sobrante objetivo con lo que ya se
     * movio para esa OP/MP/deposito y mueve solo la diferencia. Asi tambien
     * corrige una OP que se finalizo antes de confirmar todos los avances.
     * Se aplica por cada deposito de origen que no sea el propio sobrante.
     */
    public function registrarSobrantesAutomaticos(int $ordenId): void
    {
        $stmt = $this->db->prepare("
            SELECT r.materia_prima_id, r.deposito_id,
                   SUM(r.cantidad) AS consumido,
                   esp.presentacion
            FROM reservas_materia_prima r
            JOIN materias_primas mp ON mp.id = r.materia_prima_id
            LEFT JOIN materias_primas_especificaciones esp ON esp.materia_prima_id = mp.id
            WHERE r.orden_produccion_id = ? AND r.estado = 'CONSUMIDO'
            GROUP BY r.materia_prima_id, r.deposito_id, esp.presentacion
        ");
        $stmt->execute([$ordenId]);
        $consumos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($consumos)) {
            return;
        }

        $depositoModel = new Deposito();
        $sobranteId = $depositoModel->ensureSobrante();
        $principalId = $depositoModel->principalId();
        $usuarioId = $_SESSION['user_id'] ?? null;
        $fecha = defined('FECHA_ACTUAL') ? FECHA_ACTUAL : date('Y-m-d H:i:s');

        foreach ($consumos as $c) {
            $mpId = (int)$c['materia_prima_id'];
            $presentacion = (float)($c['presentacion'] ?? 0);
            $consumido = (float)$c['consumido'];
            if ($presentacion <= 0 || $consumido <= 0) {
                continue;
            }

            $depositoId = $c['deposito_id'] !== null ? (int)$c['deposito_id'] : $principalId;
            if ($depositoId <= 0 || $depositoId === $sobranteId) {
                continue;
            }

            $paquetes = (int)ceil(($consumido / $presentacion) - 0.0000001);
            $objetivo = round(($paquetes * $presentacion) - $consumido, 6);

            // Neto ya movido entre este deposito y el sobrante para esta OP/MP.
            $filtroDeposito = ($depositoId === $principalId)
                ? '(deposito_id = ? OR deposito_id IS NULL)'
                : 'deposito_id = ?';
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(
                    CASE WHEN tipo = 'SALIDA' THEN cantidad
                         WHEN tipo = 'ENTRADA' THEN -cantidad END
                ), 0)
                FROM movimientos_stock
                WHERE referencia_id = ? AND origen = 'SOBRANTE_MP'
                  AND materia_prima_id = ? AND {$filtroDeposito}
            ");
            $stmt->execute([$ordenId, $mpId, $depositoId]);
            $existente = (float)$stmt->fetchColumn();

            $diferencia = round($objetivo - $existente, 6);
            if (abs($diferencia) <= 0.000001) {
                continue;
            }

            if ($diferencia > 0) {
                // Falta mover resto desde el deposito de origen al sobrante.
                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, materia_prima_id, deposito_id, cantidad, observaciones, usuario_id, created_at)
                    VALUES ('SALIDA', 'SOBRANTE_MP', ?, ?, ?, ?, 'Sobrante automatico de presentacion enviado a MP sobrante', ?, ?)
                ")->execute([$ordenId, $mpId, $depositoId, $diferencia, $usuarioId, $fecha]);

                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, materia_prima_id, deposito_id, cantidad, observaciones, usuario_id, created_at)
                    VALUES ('ENTRADA', 'SOBRANTE_MP', ?, ?, ?, ?, 'Sobrante automatico de presentacion recibido de produccion', ?, ?)
                ")->execute([$ordenId, $mpId, $sobranteId, $diferencia, $usuarioId, $fecha]);
            } else {
                // Hay mas sobrante que el objetivo: devolver el exceso al origen.
                $ajuste = abs($diferencia);
                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, materia_prima_id, deposito_id, cantidad, observaciones, usuario_id, created_at)
                    VALUES ('SALIDA', 'SOBRANTE_MP', ?, ?, ?, ?, 'Ajuste de sobrante automatico: devolucion al deposito de origen', ?, ?)
                ")->execute([$ordenId, $mpId, $sobranteId, $ajuste, $usuarioId, $fecha]);

                $this->db->prepare("
                    INSERT INTO movimientos_stock
                    (tipo, origen, referencia_id, materia_prima_id, deposito_id, cantidad, observaciones, usuario_id, created_at)
                    VALUES ('ENTRADA', 'SOBRANTE_MP', ?, ?, ?, ?, 'Ajuste de sobrante automatico recibido desde MP sobrante', ?, ?)
                ")->execute([$ordenId, $mpId, $depositoId, $ajuste, $usuarioId, $fecha]);
            }

            error_log("[Produccion] OP {$ordenId} MP {$mpId}: sobrante objetivo {$objetivo}, movido antes {$existente}, ajuste {$diferencia}.");
        }
    }

    /**
     * Convierte las asignaciones del formulario (una o varias fuentes por MP) en
     * una lista de [deposito_id, cantidad]. Valida que la suma coincida con lo
     * necesario y que no se supere el stock de un deposito cuando hay stock total
     * suficiente. Si no vienen asignaciones, reparte automaticamente.
     */
    private function resolverFuentesReserva(int $mpId, float $cantidadNecesaria, $asignaciones, string $nombreMp = ''): array
    {
        $depositos = $this->stockPorDepositoMP($mpId);
        if (empty($depositos)) {
            throw new Exception('No hay depositos activos para reservar ' . $nombreMp . '.');
        }

        $porId = [];
        foreach ($depositos as $d) {
            $porId[(int)$d['id']] = $d;
        }

        // Compatibilidad con el formato viejo: deposito_items[mpId] = idDeposito.
        if (!is_array($asignaciones)) {
            $asignaciones = !empty($asignaciones)
                ? [(int)$asignaciones => $cantidadNecesaria]
                : [];
        }

        $fuentes = [];
        $totalAsignado = 0.0;
        foreach ($asignaciones as $depId => $cantidad) {
            $depId = (int)$depId;
            $cantidad = round((float)str_replace(',', '.', (string)$cantidad), 6);
            if ($cantidad <= 0) {
                continue;
            }
            if (!isset($porId[$depId])) {
                throw new Exception('El deposito seleccionado para ' . $nombreMp . ' no es valido.');
            }
            $fuentes[] = ['deposito_id' => $depId, 'cantidad' => $cantidad];
            $totalAsignado += $cantidad;
        }

        // Sin asignaciones (por ejemplo, JS deshabilitado): repartir automaticamente.
        if (empty($fuentes)) {
            $fuentes = $this->distribuirFuentesReserva($depositos, $cantidadNecesaria);
            $totalAsignado = array_sum(array_column($fuentes, 'cantidad'));
        }

        if (abs($totalAsignado - $cantidadNecesaria) > 0.0005) {
            throw new Exception(
                'Las fuentes de ' . $nombreMp . ' suman ' . number_format($totalAsignado, 3, ',', '.')
                . ' y se necesitan ' . number_format($cantidadNecesaria, 3, ',', '.') . '.'
            );
        }

        // Si el stock total alcanza, cada fuente debe caber en su deposito.
        // Si no alcanza, se permite negativo para planificar la compra.
        $stockTotal = 0.0;
        foreach ($depositos as $d) {
            $stockTotal += (float)$d['stock'];
        }
        if ($stockTotal + 0.0005 >= $cantidadNecesaria) {
            foreach ($fuentes as $fuente) {
                $disponible = (float)$porId[$fuente['deposito_id']]['stock'];
                if ($fuente['cantidad'] > $disponible + 0.0005) {
                    throw new Exception(
                        'El deposito "' . $porId[$fuente['deposito_id']]['nombre'] . '" no tiene stock suficiente de '
                        . $nombreMp . ' (asignado: ' . number_format($fuente['cantidad'], 3, ',', '.')
                        . ', disponible: ' . number_format($disponible, 3, ',', '.') . ').'
                    );
                }
            }
        }

        return $fuentes;
    }

    /**
     * Reparte una cantidad entre depositos: primero el principal, luego los
     * demas y por ultimo el sobrante. Si no alcanza, el resto va al principal
     * (permite crear la OP para planificar la compra).
     */
    private function distribuirFuentesReserva(array $depositos, float $cantidad): array
    {
        $restante = $cantidad;
        $fuentes = [];

        foreach ($depositos as $d) {
            if ($restante <= 0.000001) {
                break;
            }
            $disponible = max(0, (float)$d['stock']);
            $tomar = min($restante, $disponible);
            if ($tomar > 0.000001) {
                $fuentes[] = ['deposito_id' => (int)$d['id'], 'cantidad' => round($tomar, 6)];
                $restante -= $tomar;
            }
        }

        if ($restante > 0.000001) {
            $principalId = (int)$depositos[0]['id'];
            $agregado = false;
            foreach ($fuentes as &$fuente) {
                if ((int)$fuente['deposito_id'] === $principalId) {
                    $fuente['cantidad'] = round((float)$fuente['cantidad'] + $restante, 6);
                    $agregado = true;
                    break;
                }
            }
            unset($fuente);
            if (!$agregado) {
                $fuentes[] = [
                    'deposito_id' => $principalId,
                    'cantidad' => round($restante, 6),
                ];
            }
        }

        return $fuentes;
    }

    public function find(int $id): ?array //devuelve la info de la orden de produccion
    {
        $stmt = $this->db->prepare("
            SELECT op.*, p.nombre AS producto, p.precio_venta, u.nombre AS nombre_user, p.id AS producto_id
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
            SELECT
                r.materia_prima_id,
                mp.nombre,
                r.cantidad,
                r.estado,
                r.deposito_id,
                dep.nombre AS deposito_nombre,
                um.nombre AS unidad_medida,
                COALESCE(
                    (SELECT ocd.precio_unitario
                     FROM ordenes_compra_detalle ocd
                     JOIN ordenes_compra oc ON oc.id = ocd.orden_compra_id
                     WHERE ocd.materia_prima_id = r.materia_prima_id
                       AND oc.estado IN ('APROBADA','RECIBIDA','PARCIAL')
                     ORDER BY oc.created_at DESC
                     LIMIT 1),
                    mp.precio_actual,
                    0
                ) AS precio_unitario
            FROM reservas_materia_prima r
            JOIN materias_primas mp ON mp.id = r.materia_prima_id
            LEFT JOIN unidad_medida um ON um.id_medida = mp.id_unidadmedida
            LEFT JOIN depositos dep ON dep.id = r.deposito_id
            WHERE r.orden_produccion_id = ?
              AND r.estado = 'RESERVADO'
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
        $items = [];
        $ok = 0;
        error_log(print_r($receta, true));
        foreach ($receta as $item) {
            $necesario = (float)$item['cantidad'] * $cantidad; //cantidad a producir * cantidad de ese item que se necesita para producir 1 unidad del producto final, me da la cantidad total necesaria de ese item para producir la cantidad deseada del producto final
            $disponible = $this->stockDisponibleCantidad($item['materia_prima_id']);

            if ($disponible < $necesario) { //comprar lo disponible y mostrar lo faltante, o directamente mostrar que no hay stock suficiente para esa cantidad a producir?
                $faltantes[] = [
                    'materia_prima_id' => $item['materia_prima_id'],
                    'materia_prima' => $item['nombre'],
                    'unidad'        => $item['unidad_medida'] ?? '',
                    'necesario'     => $necesario,
                    'disponible'    => $disponible,
                    'faltante'      => $necesario - $disponible
                ];
            } else {
                $ok++;
            }

            // Depositos con stock y sugerencia de reparto: primero el principal,
            // luego los demas y por ultimo el sobrante. El operario puede modificarlo.
            $depositos = $this->stockPorDepositoMP((int)$item['materia_prima_id']);
            $faltanteSugerir = $necesario;
            foreach ($depositos as &$d) {
                $disponible = max(0, (float)$d['stock']);
                $sugerido = min($faltanteSugerir, $disponible);
                $d['sugerido'] = round($sugerido, 6);
                $faltanteSugerir -= $sugerido;
            }
            unset($d);
            // Si no alcanza el stock, el faltante queda sugerido en el primer deposito
            // (permite crear la OP para planificar la compra).
            if ($faltanteSugerir > 0.000001 && !empty($depositos)) {
                $depositos[0]['sugerido'] = round((float)$depositos[0]['sugerido'] + $faltanteSugerir, 6);
            }

            $items[] = [
                'materia_prima_id' => (int)$item['materia_prima_id'],
                'materia_prima'    => $item['nombre'],
                'unidad'           => $item['unidad_medida'] ?? '',
                'necesario'        => $necesario,
                'disponible'       => $disponible,
                'presentacion'     => $item['presentacion'] ?? null,
                'depositos'        => $depositos,
            ];
        }

        $estado = 'ok'; // normalmente devuelve ok salvo no haya stock o si hay varios items y alguno no tiene stocks muestra warning.
        if (count($faltantes) === count($receta)) {
            $estado = 'error';
        } elseif (count($faltantes) > 0) {
            $estado = 'warning';
        }

        return [
            'estado'    => $estado,
            'faltantes' => $faltantes,
            'items'     => $items
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

        if ($estado === 'FINALIZADA') {
            $this->registrarSobrantesAutomaticos($id);
        }
    }

    /**
     * Suma de avances de una OP. Si $soloConfirmados = true, cuenta unicamente
     * los avances ya confirmados (los que realmente produjeron).
     */
    private function totalAvances(int $ordenId, bool $soloConfirmados = false): float
    {
        $sql = "SELECT COALESCE(SUM(cantidad_producida), 0)
                FROM orden_produccion_detalle
                WHERE orden_id = ?";
        if ($soloConfirmados) {
            $sql .= " AND confirma_produccion IS NOT NULL";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ordenId]);
        return (float)$stmt->fetchColumn();
    }

    public function avances(array $data){ //llamo desde controlador avances para registrar producciones, aca solo inicio el
                                        //proceso de produccion, luego falta registrar el fin de produccion,
                                        //ahi es cuando hago los mov de stock producto.
        $this->db->beginTransaction();
        try {
            $ordenId = (int)$data['orden_id'];
            $nuevaCantidad = (float)$data['cantidad_producida'];
            if ($nuevaCantidad <= 0) {
                throw new Exception('La cantidad producida debe ser mayor a cero.');
            }

            $orden = $this->find($ordenId);
            if (!$orden) {
                throw new Exception('La orden de produccion no existe.');
            }

            // No permitir cargar mas avances que lo pedido, contando tanto los
            // confirmados como los pendientes de confirmar.
            $comprometido = $this->totalAvances($ordenId, false);
            $pedido = (float)$orden['cantidad'];
            if ($comprometido + $nuevaCantidad > $pedido + 0.0005) {
                $disponible = max(0, $pedido - $comprometido);
                throw new Exception(
                    'La cantidad supera lo pedido. La OP pide ' . number_format($pedido, 3, ',', '.')
                    . ', ya hay ' . number_format($comprometido, 3, ',', '.')
                    . ' cargado/confirmado y quedan ' . number_format($disponible, 3, ',', '.')
                    . ' disponibles para cargar.'
                );
            }

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
            //10/7/26 no debo cambiar el estado, aca solo registro un avance
            //$dato_opid=(int) $data['orden_id']; //obtengo el id de produccion (tabla orden_produccion_detalle)
            //$change_estado= $this->estadoChange($dato_opid); // validar true.
            //error_log('Cambio de estado:'.$change_estado.' - '.__FILE__.":".__LINE__);
            //2 No debo insertar en la tabla de mov el ingreso de producto que viene de produccion, porque esto se confirma cuando se cierra el avance
            //error_log('Insertando movimiento de stock para el producto: '.$data['producto_id'].' - '.__FILE__.":".__LINE__);
            //cambiar el estado de la materia prima solo si lo producido alcanza lo pedido
            //si esta completa eliminar el dato de la tabla reserva MP antes cambiando el estado reservado a consumido.
            //a medida que se va agregando producccion se debe ir aumentando el stock del producto.
            /*if($change_estado === true){
                error_log('confirmo la creacion del avance pero no muevo stock todavia');
                //this->ajustarProducto($data['producto_id'],$data['cantidad_producida'],$data['observaciones'],$data['usuario_id'],'ENTRADA',$data['orden_id']);
            }*/
            $this->db->commit();
            $_SESSION['success']="Registro de Avance generado correctamente. Se espera su finalizacion de Produccion para actualizar el stock de producto y materia prima consumida.";
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
                WHERE orden_id=? AND confirma_produccion IS NOT NULL
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
                $this->registrarSobrantesAutomaticos($id_orden);
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
                (tipo, origen, producto_id, cantidad, deposito_id, lote_id, observaciones, usuario_id,referencia_id)
                VALUES (?, 'OP', ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $tipo,
                $productoId,
                $cantidad,
                $data['deposito_id'] ?? null,
                $data['lote_id'] ?? null,
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
    /**
     * Reservas vigentes de una MP agrupadas por deposito de origen.
     */
    private function fuentesReservadas(int $ordenId, int $mpId): array
    {
        $stmt = $this->db->prepare("
            SELECT deposito_id, SUM(cantidad) AS cantidad
            FROM reservas_materia_prima
            WHERE orden_produccion_id = ? AND materia_prima_id = ? AND estado = 'RESERVADO'
            GROUP BY deposito_id
            ORDER BY deposito_id
        ");
        $stmt->execute([$ordenId, $mpId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reparte una cantidad entre las fuentes reservadas, en proporcion a lo
     * reservado en cada deposito. Devuelve [deposito_id, cantidad].
     */
    private function distribuirCantidadFuentes(array $fuentes, float $cantidad): array
    {
        if (empty($fuentes) || $cantidad <= 0) {
            return [];
        }

        $totalReservado = 0.0;
        foreach ($fuentes as $f) {
            $totalReservado += (float)$f['cantidad'];
        }
        if ($totalReservado <= 0.000001) {
            return [];
        }

        $asignaciones = [];
        $restante = $cantidad;
        $ultimo = count($fuentes) - 1;

        foreach ($fuentes as $i => $f) {
            $cant = ($i === $ultimo)
                ? $restante
                : round($cantidad * ((float)$f['cantidad'] / $totalReservado), 6);
            $cant = min($cant, $restante);
            if ($cant > 0.000001) {
                $asignaciones[] = [
                    'deposito_id' => $f['deposito_id'] !== null ? (int)$f['deposito_id'] : null,
                    'cantidad' => round($cant, 6),
                ];
                $restante -= $cant;
            }
        }

        if ($restante > 0.000001) {
            $asignaciones[] = [
                'deposito_id' => $fuentes[0]['deposito_id'] !== null ? (int)$fuentes[0]['deposito_id'] : null,
                'cantidad' => round($restante, 6),
            ];
        }

        return $asignaciones;
    }

    //cancelamos un op, esto implica cambiar el estado de la OP a cancelada, eliminar las reservas de MP y 
    //devolver al stock la cantidad reservada, y si habia avances registrados cambiar el estado de esos avances a cancelados 
    //o eliminarlos segun como se quiera manejar el historial.
    public function cancelarProduccion(int $id):bool{
        error_log("Cancelacion de la OP id: ".$id." - ".__FILE__.":".__LINE__);
        //Primero evaluo que la OP no este finalizada, si esta finalizada no se puede cancelar.
        $stmt=$this->db->prepare("SELECT estado FROM ordenes_produccion where id=?");
        $stmt->execute([$id]);
        $estado=$stmt->fetchColumn();
        if($estado==='FINALIZADA'){
            error_log("La OP id: ".$id." ya esta finalizada, no se puede cancelar."." - ".__FILE__.":".__LINE__);
            $_SESSION['error']="La OP id: ".$id." ya esta finalizada, no se puede cancelar.";
            return false;
        }
        try {
            $this->db->beginTransaction();
            //en tabla reserva mp cambio estado de cada mp de la OP a liberado y la borro, luego esas cantidades las sumo al stock de mP
            //consulto lo producido:
            $producido=$this->cantidadProducidaOP($id);
            $stmt=$this->db->prepare("UPDATE ordenes_produccion SET ESTADO = 'CANCELADA' WHERE id = ?");
            $stmt->execute([$id]);
            $stmt=$this->db->prepare("SELECT cantidad FROM ordenes_produccion where id=?");
            $stmt->execute([$id]);
            $pedido=(int)$stmt->fetchColumn();
            $faltante=$pedido-$producido;
            $stmt=$this->db->prepare("SELECT receta_id FROM ordenes_produccion where id=?");
            $stmt->execute([$id]);
            $receta=(int)$stmt->fetchColumn();
            error_log("Pedido: ".print_r($pedido,true)." Producida: ".print_r($producido,true)." Faltante: ".print_r($faltante,true)." Receta id: ".print_r($receta,true)." - ".__FILE__.":".__LINE__);
            $stmt=$this->db->prepare("SELECT * FROM recetas_detalle WHERE receta_id= ?");
            $stmt->execute([$receta]);
            $recetadetalle=$stmt->fetchALL(PDO::FETCH_ASSOC);
            error_log(print_r($recetadetalle,true)." - ".__FILE__.":".__LINE__);
            foreach ($recetadetalle as $item) { //recorro cada item de la receta
                $cantidad_mp=$item['cantidad']*$faltante;// obtengo cuanto es la cantidad que voy a devoler al cancelar la op.
                error_log("MP id: ".print_r($item['materia_prima_id'],true)." Cantidad a devolver: ".print_r($cantidad_mp,true)." - ".__FILE__.":".__LINE__);

                // Devolver a cada deposito de origen, en proporcion a lo reservado.
                $fuentes = $this->fuentesReservadas($id, (int)$item['materia_prima_id']);
                $devoluciones = $this->distribuirCantidadFuentes($fuentes, (float)$cantidad_mp);
                if (empty($devoluciones)) {
                    $devoluciones = [['deposito_id' => null, 'cantidad' => (float)$cantidad_mp]];
                }

                foreach ($devoluciones as $devolucion) {
                    $depositoId = $devolucion['deposito_id'];
                    $cantidadDevolver = (float)$devolucion['cantidad'];
                    if ($cantidadDevolver <= 0) continue;

                    //inserto en movimientos de stock la devolucion de la materia prima no utilizada
                    $this->db->prepare("
                        INSERT INTO movimientos_stock
                        (tipo, origen, materia_prima_id, deposito_id, cantidad, observaciones, usuario_id,referencia_id)
                        VALUES ('ENTRADA', 'CANCELACION_OP', ?, ?, ?, 'Devolucion de MP no utilizada por cancelacion de OP', ?,?)
                    ")->execute([$item['materia_prima_id'], $depositoId, $cantidadDevolver, $_SESSION['user_id'], $id]);
                    $this->db->prepare("INSERT INTO reservas_materia_prima (orden_produccion_id, materia_prima_id, deposito_id, cantidad, estado) VALUES (?, ?, ?, ?, 'LIBERADO')
                    ")->execute([$id, $item['materia_prima_id'], $depositoId, $cantidadDevolver]);
                }
            }

            // Si la OP se cancela despues de producir una parte, el resto de la
            // ultima presentacion de lo consumido queda en MP sobrante.
            $this->registrarSobrantesAutomaticos($id);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            //throw $th;
            error_log("Error al intentar anular/cancelar produccion, error: ".$e->getMessage().' - '.__FILE__.":".__LINE__);
            $_SESSION['error']="Error al intentar anular/cancelar produccion, error: ".$e->getMessage();
            $this->db->rollBack();
            return false;
        }
    }
    //devolvemos la cantidad producida relacinada a una OP, dentro orden_produccion_detalle.
    private function cantidadProducidaOP(int $op_id){
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(cantidad_producida) FROM orden_produccion_detalle
                WHERE orden_id=? AND confirma_produccion IS NOT NULL
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

            // Leer el avance antes de confirmar para validar el tope de produccion
            // y evitar dobles confirmaciones del mismo avance.
            $stmt = $this->db->prepare("
                SELECT id_tbl_ordendetalle, orden_id, producto_id, cantidad_producida, confirma_produccion
                FROM orden_produccion_detalle
                WHERE id_tbl_ordendetalle = ?
                FOR UPDATE
            ");
            $stmt->execute([$id_avance]);
            $filaAvance = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$filaAvance) {
                $this->db->rollBack();
                $_SESSION['error'] = 'El avance indicado no existe.';
                return false;
            }
            if (!empty($filaAvance['confirma_produccion'])) {
                // Ya estaba confirmado: no duplicar consumo ni stock de producto.
                $this->db->rollBack();
                return true;
            }

            $ordenId = (int)$filaAvance['orden_id'];
            $cantidadAvance = (float)$filaAvance['cantidad_producida'];
            $orden = $this->find($ordenId);
            if (!$orden) {
                $this->db->rollBack();
                $_SESSION['error'] = 'La orden de produccion no existe.';
                return false;
            }

            $confirmadoSinEste = $this->totalAvances($ordenId, true);
            $pedido = (float)$orden['cantidad'];
            if ($confirmadoSinEste + $cantidadAvance > $pedido + 0.0005) {
                $disponible = max(0, $pedido - $confirmadoSinEste);
                $this->db->rollBack();
                $_SESSION['error'] = 'No se puede confirmar el avance: la OP pide '
                    . number_format($pedido, 3, ',', '.') . ' y ya hay '
                    . number_format($confirmadoSinEste, 3, ',', '.') . ' confirmado. '
                    . 'Disponible para confirmar: ' . number_format($disponible, 3, ',', '.') . '.';
                return false;
            }

            $stmt=$this->db->prepare("UPDATE orden_produccion_detalle set confirma_produccion=? WHERE id_tbl_ordendetalle=?");//Solo confirmo que se ha cerrado un avance
            $stmt->execute([FECHA_ACTUAL,$id_avance]);

            $avance = [$filaAvance];
            error_log('$avance:'.print_r($avance,true).' - '.__FILE__.":".__LINE__);
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
                    // Conservar el deposito de origen de cada reserva, repartiendo
                    // el consumo entre todas las fuentes de la MP.
                    $fuentes = $this->fuentesReservadas((int)$avance[0]['orden_id'], (int)$mp['materia_prima_id']);
                    $consumos = $this->distribuirCantidadFuentes($fuentes, (float)$cantidad_mp);
                    if (empty($consumos)) {
                        $consumos = [['deposito_id' => null, 'cantidad' => (float)$cantidad_mp]];
                    }

                    foreach ($consumos as $consumo) {
                        $depositoId = $consumo['deposito_id'];
                        $cantidadConsumo = (float)$consumo['cantidad'];
                        if ($cantidadConsumo <= 0) continue;

                        $this->db->prepare("
                            INSERT INTO reservas_materia_prima
                            (orden_produccion_id, materia_prima_id, deposito_id, cantidad, estado, procesado_at)
                            VALUES (?, ?, ?, ?, 'CONSUMIDO', ?)
                        ")->execute([
                            $avance[0]['orden_id'],
                            $mp['materia_prima_id'],
                            $depositoId,
                            $cantidadConsumo,
                            FECHA_ACTUAL
                        ]);
                    }
                    //inserto el registro en la tabla reserva mp para generar el registro de uso de materia prima, con el estado consumido, esto me permite tener el registro de consumo de materia prima a medida que se van registrando los avances, sin necesidad de esperar a que se complete toda la producción.
                    error_log("RegistroOK - ".$avance[0]['orden_id']."-".$mp['materia_prima_id']."-".$cantidad_mp.' - '.__FILE__.":".__LINE__);
                } catch (Exception $e) {
                    error_log(print_r($e->getMessage(), true).'-'.__FILE__.":".__LINE__);
                }
            }
            //llamo a ajustar el ingreso del producto, esto inserta el movimiento de stock con el tipo ENTRADA y la referencia a la OP, esto me permite tener el stock real del producto actualizado a medida que se van registrando los avances, sin necesidad de esperar a que se complete toda la producción.
            $this->ajustarProducto($avance[0]['producto_id'],$avance[0]['cantidad_producida'],"Ingreso por produccion OP ID: ".$avance[0]['orden_id'],$_SESSION['user_id'],'ENTRADA',$avance[0]['orden_id']);
            //Ver y confirmar estado de la OP, si se completo o no, para cambiar el estado a FINALIZADA o dejarlo en EN_PRODUCCION
            $this->estadoChange($avance[0]['orden_id']);
            $this->db->commit();
            error_log("Registro el cierre OK del avance: $id_avance. ".__FILE__.":".__LINE__);
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al intentar regitraar el cierre del avance $id_avance.". $e->getMessage().". ".__FILE__.":".__LINE__);
            if (empty($_SESSION['error'])) {
                $_SESSION['error'] = 'Error al confirmar el avance: ' . $e->getMessage();
            }
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
