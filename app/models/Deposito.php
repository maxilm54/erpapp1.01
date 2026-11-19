<?php
require_once BASE_PATH . '/app/core/Model.php';

class Deposito extends Model
{
    public function all(): array
    {
        return $this->db->query(
            "SELECT * FROM depositos WHERE activo = 1
             ORDER BY es_transito ASC, es_sobrante ASC, es_reservado_np ASC, nombre"
        )->fetchAll();
    }

    /**
     * Depositos para selectores normales. Por defecto excluye el deposito de
     * productos reservados por NP: ese deposito se usa solo desde el flujo de
     * produccion/reservas/remitos de una NP.
     */
    public function allSeleccionables(bool $incluirSobrante = true, bool $incluirReservadoNp = false): array
    {
        $filtroSobrante = $incluirSobrante ? '' : ' AND es_sobrante = 0';
        $filtroReservado = $incluirReservadoNp ? '' : ' AND es_reservado_np = 0';
        return $this->db->query(
            "SELECT * FROM depositos WHERE activo = 1 AND es_transito = 0{$filtroSobrante}{$filtroReservado}
             ORDER BY es_sobrante ASC, nombre"
        )->fetchAll();
    }

    public function getTransito(): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM depositos WHERE es_transito = 1 AND activo = 1 LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function ensureTransito(): int
    {
        $transito = $this->getTransito();
        if ($transito) return (int)$transito['id'];

        $this->db->prepare(
            "INSERT INTO depositos (nombre, descripcion, es_transito) VALUES ('En Tránsito', 'Depósito virtual para mercadería en tránsito entre depósitos', 1)"
        )->execute();
        return (int)$this->db->lastInsertId();
    }

    /**
     * Depósito principal: primer depósito físico (no tránsito, no sobrante).
     * Es el destino por defecto de los movimientos históricos sin deposito_id.
     */
    public function getPrincipal(): ?array
    {
        $stmt = $this->db->query(
            "SELECT * FROM depositos
             WHERE activo = 1 AND es_transito = 0 AND es_sobrante = 0 AND es_reservado_np = 0
             ORDER BY id ASC LIMIT 1"
        );
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function principalId(): int
    {
        $principal = $this->getPrincipal();
        return $principal ? (int)$principal['id'] : 0;
    }

    /**
     * Depósito donde se guardan los restos de MP (barras/rollos parciales).
     */
    public function getSobrante(): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM depositos WHERE es_sobrante = 1 AND activo = 1 LIMIT 1"
        );
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Devuelve el ID del depósito de sobrantes, creándolo si no existe.
     */
    public function ensureSobrante(): int
    {
        $sobrante = $this->getSobrante();
        if ($sobrante) return (int)$sobrante['id'];

        $this->db->prepare(
            "INSERT INTO depositos (nombre, descripcion, es_sobrante)
             VALUES ('MP sobrante', 'Restos de materia prima que no completan una presentacion', 1)"
        )->execute();
        return (int)$this->db->lastInsertId();
    }

    /**
     * Deposito especial donde se guardan los productos reservados por NP
     * (produccion desde una OP con NP y reservas manuales de una NP).
     */
    public function getReservadoNp(): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM depositos WHERE es_reservado_np = 1 LIMIT 1"
        );
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Devuelve el ID del deposito reservado por NP, creandolo o reactivandolo
     * si no existe. Se llama desde produccion/reservas/remitos para que nunca
     * falte.
     */
    public function ensureReservadoNp(): int
    {
        $reservado = $this->getReservadoNp();
        if ($reservado) {
            if ((int)($reservado['activo'] ?? 1) !== 1) {
                $this->db->prepare("UPDATE depositos SET activo = 1 WHERE id = ?")
                    ->execute([(int)$reservado['id']]);
            }
            return (int)$reservado['id'];
        }

        // Si ya existe un deposito con el nombre esperado, se reutiliza.
        $stmt = $this->db->prepare(
            "SELECT id FROM depositos WHERE nombre = ? LIMIT 1"
        );
        $stmt->execute(['Deposito Productos Reservados (NP)']);
        $existenteId = (int)$stmt->fetchColumn();
        if ($existenteId > 0) {
            $this->db->prepare(
                "UPDATE depositos SET es_reservado_np = 1, activo = 1 WHERE id = ?"
            )->execute([$existenteId]);
            return $existenteId;
        }

        $this->db->prepare(
            "INSERT INTO depositos (nombre, descripcion, es_reservado_np)
             VALUES ('Deposito Productos Reservados (NP)', 'Productos reservados para Notas de Pedido', 1)"
        )->execute();
        return (int)$this->db->lastInsertId();
    }

    public function reservadoNpId(): int
    {
        $reservado = $this->getReservadoNp();
        return $reservado ? (int)$reservado['id'] : 0;
    }

    public function esReservadoNp(int $depositoId): bool
    {
        $reservado = $this->getReservadoNp();
        return $reservado && (int)$reservado['id'] === $depositoId;
    }

    public function allWithInactives(): array
    {
        return $this->db->query(
            "SELECT * FROM depositos ORDER BY activo DESC, nombre"
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM depositos WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO depositos (nombre, descripcion) VALUES (?, ?)"
        );
        $stmt->execute([$data['nombre'], $data['descripcion'] ?? null]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE depositos SET nombre = ?, descripcion = ? WHERE id = ?"
        );
        return $stmt->execute([$data['nombre'], $data['descripcion'] ?? null, $id]);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare(
            "UPDATE depositos SET activo = 0 WHERE id = :id"
        )->execute(['id' => $id]);
    }
}