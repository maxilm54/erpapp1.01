<?php
require_once BASE_PATH . '/app/core/Model.php';
class MailLog extends Model
{
    protected string $table = 'mails_log';

    public function registrar(array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO mails_log
            (tipo, referencia_id, email_destino, asunto, estado, error, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['tipo'],
            $data['referencia_id'],
            $data['email'],
            $data['asunto'],
            $data['estado'],
            $data['error'] ?? null,
            $data['usuario_id'] ?? null
        ]);
    }
}
