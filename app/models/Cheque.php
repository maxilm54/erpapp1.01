<?php
require_once BASE_PATH . '/app/core/Model.php';

/**
 * Cheques de terceros recibidos en cobranzas.
 *
 * Un cheque se crea junto con el pago (Cobro::registrar / Pago::registrar)
 * cuando el medio de pago es "Cheque". Desde acá se controla la cartera,
 * los vencimientos y los avisos por email.
 */
class Cheque extends Model
{
    public const ESTADOS = [
        'EN_CARTERA' => 'En cartera',
        'DEPOSITADO' => 'Depositado',
        'COBRADO'    => 'Cobrado',
        'RECHAZADO'  => 'Rechazado',
        'ANULADO'    => 'Anulado',
    ];

    public const TIPOS = [
        'FISICO' => 'Cheque físico',
        'ECHEQ'  => 'E-cheq',
    ];

    /**
     * Indica si el medio de pago elegido es cheque.
     */
    public static function esMedioCheque(?string $medioPago): bool
    {
        return strtoupper(trim((string)$medioPago)) === 'CHEQUE';
    }

    /**
     * Arma la estructura del cheque a partir del POST del cobro.
     * Devuelve null si el medio de pago no es cheque y lanza excepción si
     * falta algún dato obligatorio. Lo usan CobrosController y CtaCteController.
     */
    public static function datosDesdePost(array $post): ?array
    {
        if (!self::esMedioCheque($post['medio_pago'] ?? null)) {
            return null;
        }

        $numero = trim((string)($post['cheque_numero'] ?? ''));
        $banco = trim((string)($post['cheque_banco'] ?? ''));
        $fechaVencimiento = trim((string)($post['cheque_fecha_vencimiento'] ?? ''));
        $fechaEmision = trim((string)($post['cheque_fecha_emision'] ?? ''));

        if ($numero === '') {
            throw new Exception('Ingrese el número del cheque.');
        }
        if ($banco === '') {
            throw new Exception('Ingrese el banco del cheque.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimiento)) {
            throw new Exception('Ingrese la fecha de vencimiento del cheque.');
        }
        [$anio, $mes, $dia] = array_map('intval', explode('-', $fechaVencimiento));
        if (!checkdate($mes, $dia, $anio)) {
            throw new Exception('La fecha de vencimiento del cheque no es válida.');
        }
        if ($fechaEmision !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEmision)) {
                throw new Exception('La fecha de emisión del cheque no es válida.');
            }
            [$anioE, $mesE, $diaE] = array_map('intval', explode('-', $fechaEmision));
            if (!checkdate($mesE, $diaE, $anioE)) {
                throw new Exception('La fecha de emisión del cheque no es válida.');
            }
        }

        $tipo = strtoupper((string)($post['cheque_tipo'] ?? 'FISICO'));
        if (!isset(self::TIPOS[$tipo])) {
            $tipo = 'FISICO';
        }

        return [
            'tipo'              => $tipo,
            'numero'            => $numero,
            'banco'             => $banco,
            'sucursal'          => trim((string)($post['cheque_sucursal'] ?? '')),
            'titular'           => trim((string)($post['cheque_titular'] ?? '')),
            'cuit'              => trim((string)($post['cheque_cuit'] ?? '')),
            'fecha_emision'     => $fechaEmision !== '' ? $fechaEmision : null,
            'fecha_vencimiento' => $fechaVencimiento,
            'observaciones'     => trim((string)($post['cheque_observaciones'] ?? '')),
        ];
    }

    /**
     * Crea el cheque asociado a un pago. Se llama dentro de la transacción
     * del cobro, así que si falla el cobro completo hace rollback.
     */
    public function crearParaPago(int $pagoId, array $data): int
    {
        $tipo = strtoupper((string)($data['tipo'] ?? 'FISICO'));
        if (!isset(self::TIPOS[$tipo])) {
            $tipo = 'FISICO';
        }

        $numero = trim((string)($data['numero'] ?? ''));
        $banco = trim((string)($data['banco'] ?? ''));
        $fechaVencimiento = trim((string)($data['fecha_vencimiento'] ?? ''));

        if ($numero === '' || $banco === '' || $fechaVencimiento === '') {
            throw new Exception('Faltan datos obligatorios del cheque (número, banco o vencimiento).');
        }
        if ((int)$data['cliente_id'] <= 0) {
            throw new Exception('El cheque no tiene un cliente válido.');
        }
        if ((float)$data['monto'] <= 0) {
            throw new Exception('El monto del cheque debe ser mayor a cero.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO cheques
                (pago_id, cliente_id, cliente_nombre, tipo, numero, banco, sucursal,
                 titular, cuit, fecha_emision, fecha_vencimiento, monto, estado,
                 observaciones, caja_banco_id, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'EN_CARTERA', ?, ?, ?)
        ");

        $stmt->execute([
            $pagoId,
            (int)$data['cliente_id'],
            !empty($data['cliente_nombre']) ? trim((string)$data['cliente_nombre']) : null,
            $tipo,
            $numero,
            $banco,
            !empty($data['sucursal']) ? trim((string)$data['sucursal']) : null,
            !empty($data['titular']) ? trim((string)$data['titular']) : null,
            !empty($data['cuit']) ? trim((string)$data['cuit']) : null,
            !empty($data['fecha_emision']) ? $data['fecha_emision'] : null,
            $fechaVencimiento,
            (float)$data['monto'],
            !empty($data['observaciones']) ? trim((string)$data['observaciones']) : null,
            !empty($data['caja_banco_id']) ? (int)$data['caja_banco_id'] : null,
            (int)$data['usuario_id'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Cheque asociado a un pago (o null si el cobro no fue con cheque).
     */
    public function porPago(int $pagoId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT ch.*, cb.nombre AS caja_nombre,
                   DATEDIFF(ch.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM cheques ch
            LEFT JOIN cajas_bancos cb ON cb.id = ch.caja_banco_id
            WHERE ch.pago_id = ?
            LIMIT 1
        ");
        $stmt->execute([$pagoId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Buscar un cheque por ID con datos del cliente, la caja y el pago.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT ch.*,
                   COALESCE(NULLIF(ch.cliente_nombre, ''), c.razon_social, CONCAT('Cliente #', ch.cliente_id)) AS nombre_cliente,
                   c.cuit AS cliente_cuit,
                   c.email AS cliente_email,
                   cb.nombre AS caja_nombre,
                   p.anulado AS pago_anulado,
                   p.monto AS pago_monto,
                   p.created_at AS pago_fecha,
                   DATEDIFF(ch.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM cheques ch
            LEFT JOIN clientes c ON c.id = ch.cliente_id
            LEFT JOIN cajas_bancos cb ON cb.id = ch.caja_banco_id
            LEFT JOIN pagos p ON p.id = ch.pago_id
            WHERE ch.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Listado con filtros: estado, vencimiento (vencidos|proximos|mes),
     * cliente_id, texto, desde y hasta (sobre fecha_vencimiento).
     */
    public function all(array $filtros = []): array
    {
        $where = [];
        $params = [];

        $estado = strtoupper(trim((string)($filtros['estado'] ?? '')));
        if ($estado !== '' && isset(self::ESTADOS[$estado])) {
            $where[] = 'ch.estado = ?';
            $params[] = $estado;
        }

        $vencimiento = trim((string)($filtros['vencimiento'] ?? ''));
        if ($vencimiento === 'vencidos') {
            $where[] = "ch.estado = 'EN_CARTERA' AND ch.fecha_vencimiento < CURDATE()";
        } elseif ($vencimiento === 'hoy') {
            $where[] = "ch.estado = 'EN_CARTERA' AND ch.fecha_vencimiento = CURDATE()";
        } elseif ($vencimiento === 'proximos') {
            $where[] = "ch.estado = 'EN_CARTERA' AND ch.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($vencimiento === 'mes') {
            $where[] = "DATE_FORMAT(ch.fecha_vencimiento, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        }

        $clienteId = (int)($filtros['cliente_id'] ?? 0);
        if ($clienteId > 0) {
            $where[] = 'ch.cliente_id = ?';
            $params[] = $clienteId;
        }

        $texto = trim((string)($filtros['texto'] ?? ''));
        if ($texto !== '') {
            $like = '%' . $texto . '%';
            $where[] = "(ch.numero LIKE ? OR ch.banco LIKE ? OR COALESCE(NULLIF(ch.cliente_nombre, ''), c.razon_social) LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $desde = trim((string)($filtros['desde'] ?? ''));
        if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $where[] = 'ch.fecha_vencimiento >= ?';
            $params[] = $desde;
        }

        $hasta = trim((string)($filtros['hasta'] ?? ''));
        if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $where[] = 'ch.fecha_vencimiento <= ?';
            $params[] = $hasta;
        }

        $sql = "
            SELECT ch.*,
                   COALESCE(NULLIF(ch.cliente_nombre, ''), c.razon_social, CONCAT('Cliente #', ch.cliente_id)) AS nombre_cliente,
                   cb.nombre AS caja_nombre,
                   DATEDIFF(ch.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM cheques ch
            LEFT JOIN clientes c ON c.id = ch.cliente_id
            LEFT JOIN cajas_bancos cb ON cb.id = ch.caja_banco_id
        ";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ch.fecha_vencimiento ASC, ch.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cheques en cartera que vencen dentro de los próximos N días.
     * Con $incluirVencidos = true también trae los ya vencidos.
     */
    public function porVencer(int $dias = 30, bool $incluirVencidos = true): array
    {
        $dias = max(0, $dias);
        $condicion = $incluirVencidos
            ? "ch.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL {$dias} DAY)"
            : "ch.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL {$dias} DAY)";

        return $this->db->query("
            SELECT ch.*,
                   COALESCE(NULLIF(ch.cliente_nombre, ''), c.razon_social, CONCAT('Cliente #', ch.cliente_id)) AS nombre_cliente,
                   DATEDIFF(ch.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM cheques ch
            LEFT JOIN clientes c ON c.id = ch.cliente_id
            WHERE ch.estado = 'EN_CARTERA' AND {$condicion}
            ORDER BY ch.fecha_vencimiento ASC, ch.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cheques en cartera con fecha de vencimiento anterior a hoy.
     */
    public function vencidos(): array
    {
        return $this->db->query("
            SELECT ch.*,
                   COALESCE(NULLIF(ch.cliente_nombre, ''), c.razon_social, CONCAT('Cliente #', ch.cliente_id)) AS nombre_cliente,
                   DATEDIFF(ch.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM cheques ch
            LEFT JOIN clientes c ON c.id = ch.cliente_id
            WHERE ch.estado = 'EN_CARTERA' AND ch.fecha_vencimiento < CURDATE()
            ORDER BY ch.fecha_vencimiento ASC, ch.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Totales para las tarjetas de resumen.
     */
    public function resumen(): array
    {
        $row = $this->db->query("
            SELECT
                SUM(CASE WHEN estado = 'EN_CARTERA' THEN 1 ELSE 0 END) AS en_cartera_cant,
                COALESCE(SUM(CASE WHEN estado = 'EN_CARTERA' THEN monto ELSE 0 END), 0) AS en_cartera_monto,
                SUM(CASE WHEN estado = 'EN_CARTERA' AND fecha_vencimiento < CURDATE() THEN 1 ELSE 0 END) AS vencidos_cant,
                COALESCE(SUM(CASE WHEN estado = 'EN_CARTERA' AND fecha_vencimiento < CURDATE() THEN monto ELSE 0 END), 0) AS vencidos_monto,
                SUM(CASE WHEN estado = 'EN_CARTERA' AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS por_vencer_cant,
                COALESCE(SUM(CASE WHEN estado = 'EN_CARTERA' AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN monto ELSE 0 END), 0) AS por_vencer_monto,
                SUM(CASE WHEN estado = 'DEPOSITADO' THEN 1 ELSE 0 END) AS depositados_cant,
                COALESCE(SUM(CASE WHEN estado = 'DEPOSITADO' THEN monto ELSE 0 END), 0) AS depositados_monto,
                SUM(CASE WHEN estado = 'COBRADO' AND DATE_FORMAT(updated_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN 1 ELSE 0 END) AS cobrados_mes_cant,
                COALESCE(SUM(CASE WHEN estado = 'COBRADO' AND DATE_FORMAT(updated_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN monto ELSE 0 END), 0) AS cobrados_mes_monto
            FROM cheques
        ")->fetch(PDO::FETCH_ASSOC);

        return [
            'en_cartera_cant'    => (int)($row['en_cartera_cant'] ?? 0),
            'en_cartera_monto'   => (float)($row['en_cartera_monto'] ?? 0),
            'vencidos_cant'      => (int)($row['vencidos_cant'] ?? 0),
            'vencidos_monto'     => (float)($row['vencidos_monto'] ?? 0),
            'por_vencer_cant'    => (int)($row['por_vencer_cant'] ?? 0),
            'por_vencer_monto'   => (float)($row['por_vencer_monto'] ?? 0),
            'depositados_cant'   => (int)($row['depositados_cant'] ?? 0),
            'depositados_monto'  => (float)($row['depositados_monto'] ?? 0),
            'cobrados_mes_cant'  => (int)($row['cobrados_mes_cant'] ?? 0),
            'cobrados_mes_monto' => (float)($row['cobrados_mes_monto'] ?? 0),
        ];
    }

    /**
     * Cambia el estado del cheque (depositar, cobrar, rechazar, anular).
     */
    public function cambiarEstado(int $id, string $estado): void
    {
        $estado = strtoupper(trim($estado));
        if (!isset(self::ESTADOS[$estado])) {
            throw new Exception('Estado de cheque inválido.');
        }

        $stmt = $this->db->prepare("UPDATE cheques SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);
    }

    /**
     * Edita los datos del cheque. Si cambia el vencimiento se resetea el
     * aviso para que el cron vuelva a notificarlo.
     */
    public function actualizar(int $id, array $data): void
    {
        $cheque = $this->find($id);
        if (!$cheque) {
            throw new Exception('Cheque no encontrado.');
        }

        $numero = trim((string)($data['numero'] ?? ''));
        $banco = trim((string)($data['banco'] ?? ''));
        $fechaVencimiento = trim((string)($data['fecha_vencimiento'] ?? ''));
        $monto = (float)($data['monto'] ?? 0);

        if ($numero === '') {
            throw new Exception('El número de cheque es obligatorio.');
        }
        if ($banco === '') {
            throw new Exception('El banco es obligatorio.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimiento)) {
            throw new Exception('La fecha de vencimiento es obligatoria.');
        }
        [$anioV, $mesV, $diaV] = array_map('intval', explode('-', $fechaVencimiento));
        if (!checkdate($mesV, $diaV, $anioV)) {
            throw new Exception('La fecha de vencimiento del cheque no es válida.');
        }
        if ($monto <= 0) {
            throw new Exception('El monto del cheque debe ser mayor a cero.');
        }

        $tipo = strtoupper((string)($data['tipo'] ?? 'FISICO'));
        if (!isset(self::TIPOS[$tipo])) {
            $tipo = 'FISICO';
        }

        $fechaEmision = trim((string)($data['fecha_emision'] ?? ''));
        if ($fechaEmision !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEmision)) {
                $fechaEmision = '';
            } else {
                [$anioE, $mesE, $diaE] = array_map('intval', explode('-', $fechaEmision));
                if (!checkdate($mesE, $diaE, $anioE)) {
                    $fechaEmision = '';
                }
            }
        }

        $resetAviso = ($cheque['fecha_vencimiento'] ?? null) !== $fechaVencimiento;

        $stmt = $this->db->prepare("
            UPDATE cheques SET
                tipo = ?,
                numero = ?,
                banco = ?,
                sucursal = ?,
                titular = ?,
                cuit = ?,
                fecha_emision = ?,
                fecha_vencimiento = ?,
                monto = ?,
                observaciones = ?,
                caja_banco_id = ?,
                ultimo_aviso_at = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $tipo,
            $numero,
            $banco,
            !empty($data['sucursal']) ? trim((string)$data['sucursal']) : null,
            !empty($data['titular']) ? trim((string)$data['titular']) : null,
            !empty($data['cuit']) ? trim((string)$data['cuit']) : null,
            $fechaEmision !== '' ? $fechaEmision : null,
            $fechaVencimiento,
            $monto,
            !empty($data['observaciones']) ? trim((string)$data['observaciones']) : null,
            !empty($data['caja_banco_id']) ? (int)$data['caja_banco_id'] : null,
            $resetAviso ? null : ($cheque['ultimo_aviso_at'] ?? null),
            $id,
        ]);
    }

    /**
     * Anula el cheque asociado a un cobro anulado.
     */
    public function anularPorPago(int $pagoId): void
    {
        $this->db->prepare("UPDATE cheques SET estado = 'ANULADO' WHERE pago_id = ?")->execute([$pagoId]);
    }

    /**
     * Cheques en cartera que vencen dentro de N días (o ya vencidos) y que
     * todavía no fueron avisados hoy. Lo usa el cron de notificaciones.
     */
    public function pendientesAviso(int $dias = 7, bool $ignorarAviso = false): array
    {
        $dias = max(0, $dias);
        $filtroAviso = $ignorarAviso
            ? ''
            : 'AND (ch.ultimo_aviso_at IS NULL OR DATE(ch.ultimo_aviso_at) < CURDATE())';

        return $this->db->query("
            SELECT ch.*,
                   COALESCE(NULLIF(ch.cliente_nombre, ''), c.razon_social, CONCAT('Cliente #', ch.cliente_id)) AS nombre_cliente,
                   c.email AS cliente_email,
                   DATEDIFF(ch.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM cheques ch
            LEFT JOIN clientes c ON c.id = ch.cliente_id
            WHERE ch.estado = 'EN_CARTERA'
              AND ch.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL {$dias} DAY)
              {$filtroAviso}
            ORDER BY ch.fecha_vencimiento ASC, ch.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marca los cheques avisados para no repetir el mail el mismo día.
     */
    public function marcarAvisados(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("UPDATE cheques SET ultimo_aviso_at = NOW() WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
    }
}
