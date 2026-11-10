<?php
require_once BASE_PATH . '/app/core/Model.php';

/**
 * Notificaciones del sistema. Vive en la BD MASTER (app_master) para que un
 * solo esquema sirva a superadmin y a los usuarios de todas las empresas.
 *
 * Dos clases de notificaciones:
 *
 * 1) Operativas por empresa (clave estable + tenant_id): se REGENERAN desde la
 *    BD del tenant en cada consulta del centro de notificaciones
 *    (Notificacion::sincronizarOperacionales). El item desaparece cuando el
 *    documento se cierra (cheque cobrado, NP remitida, OP finalizada, etc.) o
 *    cuando cambia su fecha (nueva clave = vuelve a figurar como no leida).
 *    Tipos actuales:
 *      - CHEQUE_VENCIMIENTO  (cheques en cartera con fecha de vencimiento)
 *      - PRESUPUESTO_ENTREGA (presupuestos sin aprobar con fecha de entrega)
 *      - NP_ENTREGA          (notas de pedido aprobadas con fecha de entrega)
 *      - OP_ENTREGA          (ordenes de produccion con fecha de entrega)
 *
 * 2) Novedades globales (tipo NOVEDAD, tenant_id NULL): las publica el
 *    superadmin desde Admin -> Novedades y se ven en todas las empresas hasta
 *    la fecha de vigencia (vigencia_hasta).
 *
 * El estado leido / no leido es POR USUARIO (notificaciones_leidos, users.id
 * de la master, que es el mismo id que viaja en la sesion).
 */
class Notificacion extends Model
{
    public const TIPO_CHEQUE = 'CHEQUE_VENCIMIENTO';
    public const TIPO_PRESUPUESTO = 'PRESUPUESTO_ENTREGA';
    public const TIPO_NOTA_PEDIDO = 'NP_ENTREGA';
    public const TIPO_ORDEN_PRODUCCION = 'OP_ENTREGA';
    public const TIPO_NOVEDAD = 'NOVEDAD';

    /** Ventana de dias hacia adelante para convertir fechas en alertas (vencidas siempre entran) */
    public const VENTANA_DIAS = 30;

    public function __construct()
    {
        $this->db = Database::getMaster();
    }

    // =====================================================
    // Sincronizacion de alertas operativas (desde la BD del tenant)
    // =====================================================

    /**
     * Regenera las alertas operativas del tenant: inserta/actualiza por clave
     * estable y desactiva las que ya no corresponden. No toca novedades.
     */
    public function sincronizarOperacionales(int $tenantId, int $dias = self::VENTANA_DIAS): void
    {
        $dias = max(0, $dias);

        $filas = array_merge(
            $this->filasCheques($dias),
            $this->filasPresupuestos($dias),
            $this->filasNotasPedido($dias),
            $this->filasOrdenesProduccion($dias)
        );

        $upsert = $this->db->prepare("
            INSERT INTO notificaciones (tenant_id, clave, tipo, titulo, mensaje, fecha_referencia, url, activo)
            VALUES (:t, :clave, :tipo, :titulo, :mensaje, :fecha, :url, 1)
            ON DUPLICATE KEY UPDATE
                titulo = VALUES(titulo),
                mensaje = VALUES(mensaje),
                fecha_referencia = VALUES(fecha_referencia),
                url = VALUES(url),
                activo = 1
        ");

        $claves = [];
        foreach ($filas as $f) {
            $upsert->execute([
                ':t'      => $tenantId,
                ':clave'  => $f['clave'],
                ':tipo'   => $f['tipo'],
                ':titulo' => $f['titulo'],
                ':mensaje'=> $f['mensaje'],
                ':fecha'  => $f['fecha_referencia'],
                ':url'    => $f['url'],
            ]);
            $claves[] = $f['clave'];
        }

        // Desactivar las que ya no corresponden (doc cerrado, anulado o fecha cambiada)
        if (empty($claves)) {
            $this->db->prepare("
                UPDATE notificaciones
                SET activo = 0
                WHERE tenant_id = ? AND tipo IN ('CHEQUE_VENCIMIENTO','PRESUPUESTO_ENTREGA','NP_ENTREGA','OP_ENTREGA') AND activo = 1
            ")->execute([$tenantId]);
        } else {
            $ph = implode(',', array_fill(0, count($claves), '?'));
            $this->db->prepare("
                UPDATE notificaciones
                SET activo = 0
                WHERE tenant_id = ? AND tipo IN ('CHEQUE_VENCIMIENTO','PRESUPUESTO_ENTREGA','NP_ENTREGA','OP_ENTREGA')
                  AND clave NOT IN ({$ph}) AND activo = 1
            ")->execute(array_merge([$tenantId], $claves));
        }

        // Limpieza: operativas inactivas hace mas de 90 dias sin lecturas asociadas
        $this->db->exec("
            DELETE n FROM notificaciones n
            LEFT JOIN notificaciones_leidos l ON l.notificacion_id = n.id
            WHERE n.activo = 0
              AND n.tipo IN ('CHEQUE_VENCIMIENTO','PRESUPUESTO_ENTREGA','NP_ENTREGA','OP_ENTREGA')
              AND n.created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
              AND l.id IS NULL
        ");
    }

    /** Cheques en cartera / depositados que vencen en la ventana (o ya vencidos). */
    private function filasCheques(int $dias): array
    {
        $rows = Database::getInstance()->query("
            SELECT ch.id, ch.numero, ch.banco, ch.monto, ch.fecha_vencimiento, ch.estado,
                   COALESCE(NULLIF(ch.cliente_nombre, ''), c.razon_social, CONCAT('Cliente #', ch.cliente_id)) AS cliente
            FROM cheques ch
            LEFT JOIN clientes c ON c.id = ch.cliente_id
            WHERE ch.estado IN ('EN_CARTERA','DEPOSITADO')
              AND ch.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL {$dias} DAY)
            ORDER BY ch.fecha_vencimiento ASC, ch.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $filas = [];
        foreach ($rows as $r) {
            $fecha = substr((string)$r['fecha_vencimiento'], 0, 10);
            $filas[] = [
                'clave'  => self::TIPO_CHEQUE . ':' . (int)$r['id'] . ':' . $fecha,
                'tipo'   => self::TIPO_CHEQUE,
                'titulo' => 'Cheque N° ' . ($r['numero'] ?? '') . ' - ' . ($r['cliente'] ?? ''),
                'mensaje' => 'Banco: ' . ($r['banco'] ?? '') . ' · Vence el ' . date('d/m/Y', strtotime($fecha))
                    . ' · Monto: $ ' . number_format((float)$r['monto'], 2, ',', '.')
                    . ' · Estado: ' . $r['estado'],
                'fecha_referencia' => $fecha,
                'url'    => '/cheques',
            ];
        }
        return $filas;
    }

    /** Presupuestos SIN aprobar (BORRADOR) con fecha de entrega comprometida. */
    private function filasPresupuestos(int $dias): array
    {
        $rows = Database::getInstance()->query("
            SELECT pr.id, pr.fecha_entrega, c.razon_social
            FROM presupuestos pr
            JOIN clientes c ON c.id = pr.cliente_id
            WHERE pr.fecha_entrega IS NOT NULL
              AND pr.estado = 'BORRADOR'
              AND pr.fecha_entrega <= DATE_ADD(CURDATE(), INTERVAL {$dias} DAY)
            ORDER BY pr.fecha_entrega ASC, pr.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $filas = [];
        foreach ($rows as $r) {
            $fecha = substr((string)$r['fecha_entrega'], 0, 10);
            $filas[] = [
                'clave'  => self::TIPO_PRESUPUESTO . ':' . (int)$r['id'] . ':' . $fecha,
                'tipo'   => self::TIPO_PRESUPUESTO,
                'titulo' => 'Presupuesto #' . (int)$r['id'] . ' - ' . $r['razon_social'],
                'mensaje' => 'Entrega comprometida el ' . date('d/m/Y', strtotime($fecha))
                    . ' y el presupuesto sigue sin aprobar.',
                'fecha_referencia' => $fecha,
                'url'    => '/presupuestos/show/' . (int)$r['id'],
            ];
        }
        return $filas;
    }

    /** Notas de pedido APROBADAS con fecha de entrega que siguen sin remitarse completo. */
    private function filasNotasPedido(int $dias): array
    {
        $rows = Database::getInstance()->query("
            SELECT np.id, np.fecha_entrega, np.remitido, c.razon_social
            FROM notas_pedido np
            JOIN clientes c ON c.id = np.cliente_id
            WHERE np.fecha_entrega IS NOT NULL
              AND np.estado = 'APROBADA'
              AND np.remitido <> 'RemitidoCompleto'
              AND np.fecha_entrega <= DATE_ADD(CURDATE(), INTERVAL {$dias} DAY)
            ORDER BY np.fecha_entrega ASC, np.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $filas = [];
        foreach ($rows as $r) {
            $fecha = substr((string)$r['fecha_entrega'], 0, 10);
            $filas[] = [
                'clave'  => self::TIPO_NOTA_PEDIDO . ':' . (int)$r['id'] . ':' . $fecha,
                'tipo'   => self::TIPO_NOTA_PEDIDO,
                'titulo' => 'Nota de Pedido #' . (int)$r['id'] . ' - ' . $r['razon_social'],
                'mensaje' => 'Entrega comprometida el ' . date('d/m/Y', strtotime($fecha))
                    . ' · Remitado: ' . $r['remitido'],
                'fecha_referencia' => $fecha,
                'url'    => '/notaspedido/show/' . (int)$r['id'],
            ];
        }
        return $filas;
    }

    /** Ordenes de produccion activas con fecha de entrega comprometida. */
    private function filasOrdenesProduccion(int $dias): array
    {
        $rows = Database::getInstance()->query("
            SELECT op.id, op.fecha_entrega, op.estado, op.cantidad, p.nombre AS producto
            FROM ordenes_produccion op
            JOIN productos p ON p.id = op.producto_id
            WHERE op.fecha_entrega IS NOT NULL
              AND op.estado IN ('PENDIENTE','EN_PRODUCCION')
              AND DATE(op.fecha_entrega) <= DATE_ADD(CURDATE(), INTERVAL {$dias} DAY)
            ORDER BY op.fecha_entrega ASC, op.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $filas = [];
        foreach ($rows as $r) {
            $fecha = substr((string)$r['fecha_entrega'], 0, 10);
            $filas[] = [
                'clave'  => self::TIPO_ORDEN_PRODUCCION . ':' . (int)$r['id'] . ':' . $fecha,
                'tipo'   => self::TIPO_ORDEN_PRODUCCION,
                'titulo' => 'Orden de Producción #' . (int)$r['id'] . ' - ' . $r['producto'],
                'mensaje' => 'Entrega: ' . date('d/m/Y', strtotime($fecha))
                    . ' · Cantidad: ' . number_format((float)$r['cantidad'], 3, ',', '.')
                    . ' · Estado: ' . $r['estado'],
                'fecha_referencia' => $fecha,
                'url'    => '/ordenproduccion/show/' . (int)$r['id'],
            ];
        }
        return $filas;
    }

    // =====================================================
    // Consultas para el centro de notificaciones
    // =====================================================

    /** Todas las notificaciones visibles para el usuario (leidas y no leidas). */
    public function listar(int $userId, ?int $tenantId, bool $soloNoLeidas = false): array
    {
        $sql = "SELECT n.*, l.id AS leido_id, l.leido_at
                FROM notificaciones n
                LEFT JOIN notificaciones_leidos l ON l.notificacion_id = n.id AND l.user_id = :uid
                WHERE n.activo = 1
                  AND (n.tenant_id IS NULL" . ($tenantId !== null ? " OR n.tenant_id = :tid" : "") . ")
                  AND (n.vigencia_hasta IS NULL OR n.vigencia_hasta >= CURDATE())";
        if ($soloNoLeidas) {
            $sql .= " AND l.id IS NULL";
        }
        $sql .= " ORDER BY COALESCE(n.fecha_referencia, DATE(n.created_at)) ASC, n.id ASC";

        $stmt = $this->db->prepare($sql);
        $params = [':uid' => $userId];
        if ($tenantId !== null) {
            $params[':tid'] = $tenantId;
        }
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cantidad de no leidas para el badge de la campana. */
    public function contarNoLeidas(int $userId, ?int $tenantId): int
    {
        $sql = "SELECT COUNT(*)
                FROM notificaciones n
                LEFT JOIN notificaciones_leidos l ON l.notificacion_id = n.id AND l.user_id = :uid
                WHERE n.activo = 1
                  AND (n.tenant_id IS NULL" . ($tenantId !== null ? " OR n.tenant_id = :tid" : "") . ")
                  AND (n.vigencia_hasta IS NULL OR n.vigencia_hasta >= CURDATE())
                  AND l.id IS NULL";

        $stmt = $this->db->prepare($sql);
        $params = [':uid' => $userId];
        if ($tenantId !== null) {
            $params[':tid'] = $tenantId;
        }
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /** Marca una notificacion como leida para el usuario (si le corresponde). */
    public function marcarLeida(int $id, int $userId, ?int $tenantId): bool
    {
        $stmt = $this->db->prepare("SELECT tenant_id FROM notificaciones WHERE id = ? AND activo = 1");
        $stmt->execute([$id]);
        $tenant = $stmt->fetchColumn();
        if ($tenant === false) {
            return false;
        }
        if ($tenant !== null && ($tenantId === null || (int)$tenant !== (int)$tenantId)) {
            return false;
        }

        $ins = $this->db->prepare("INSERT IGNORE INTO notificaciones_leidos (notificacion_id, user_id) VALUES (?, ?)");
        $ins->execute([$id, $userId]);
        return true;
    }

    /** Marca todas las visibles como leidas para el usuario. Retorna cuantas marco. */
    public function marcarTodas(int $userId, ?int $tenantId): int
    {
        // Ojo: con EMULATE_PREPARES = false no se puede reutilizar el mismo
        // placeholder dos veces en una sentencia, por eso uid/uid2.
        $sql = "INSERT IGNORE INTO notificaciones_leidos (notificacion_id, user_id)
                SELECT n.id, :uid
                FROM notificaciones n
                LEFT JOIN notificaciones_leidos l ON l.notificacion_id = n.id AND l.user_id = :uid2
                WHERE n.activo = 1
                  AND (n.tenant_id IS NULL" . ($tenantId !== null ? " OR n.tenant_id = :tid" : "") . ")
                  AND (n.vigencia_hasta IS NULL OR n.vigencia_hasta >= CURDATE())
                  AND l.id IS NULL";

        $stmt = $this->db->prepare($sql);
        $params = [':uid' => $userId, ':uid2' => $userId];
        if ($tenantId !== null) {
            $params[':tid'] = $tenantId;
        }
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    // =====================================================
    // Novedades (Admin -> Novedades, solo superadmin)
    // =====================================================

    /** Todas las novedades (incluye inactivas y vencidas), para el panel admin. */
    public function novedades(): array
    {
        return $this->db->query("
            SELECT n.*, t.nombre AS tenant_nombre
            FROM notificaciones n
            LEFT JOIN tenants t ON t.id = n.tenant_id
            WHERE n.tipo = '" . self::TIPO_NOVEDAD . "'
            ORDER BY n.created_at DESC, n.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM notificaciones WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crearNovedad(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO notificaciones (tenant_id, tipo, titulo, mensaje, vigencia_hasta, activo)
            VALUES (:tenant, '" . self::TIPO_NOVEDAD . "', :titulo, :mensaje, :vigencia, 1)
        ");
        $stmt->execute([
            ':tenant'  => !empty($data['tenant_id']) ? (int)$data['tenant_id'] : null,
            ':titulo'  => $data['titulo'],
            ':mensaje' => $data['mensaje'] ?? null,
            ':vigencia'=> $data['vigencia_hasta'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizarNovedad(int $id, array $data): void
    {
        $this->db->prepare("
            UPDATE notificaciones
            SET tenant_id = :tenant, titulo = :titulo, mensaje = :mensaje, vigencia_hasta = :vigencia
            WHERE id = :id AND tipo = '" . self::TIPO_NOVEDAD . "'
        ")->execute([
            ':tenant'  => !empty($data['tenant_id']) ? (int)$data['tenant_id'] : null,
            ':titulo'  => $data['titulo'],
            ':mensaje' => $data['mensaje'] ?? null,
            ':vigencia'=> $data['vigencia_hasta'],
            ':id'      => $id,
        ]);
    }

    /** Baja logica de una novedad (activo = 0). */
    public function desactivar(int $id): void
    {
        $this->db->prepare("UPDATE notificaciones SET activo = 0 WHERE id = ?")->execute([$id]);
    }
}
