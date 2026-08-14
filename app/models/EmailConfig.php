<?php
require_once BASE_PATH . '/app/core/Model.php';

class EmailConfig extends Model
{
    protected string $table = 'email_config';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtener la configuración SMTP activa del tenant actual.
     * Si no existe, retorna null para usar fallback de .env.
     */
    public function getActive(): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM email_config WHERE activo = 1 ORDER BY id ASC LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            // Tabla no existe aún (migración no ejecutada), usar fallback .env
            error_log("EmailConfig: Tabla email_config no disponible, usando .env: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener la configuración por ID.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM email_config WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtener la configuración completa, con fallback a .env si no hay registro en BD.
     */
    public function getResolved(): array
    {
        $config = $this->getActive();

        if ($config) {
            return [
                'smtp_host'       => $config['smtp_host'],
                'smtp_port'       => (int)$config['smtp_port'],
                'smtp_secure'     => $config['smtp_secure'],
                'smtp_user'       => $config['smtp_user'],
                'smtp_pass'       => $config['smtp_pass'],
                'smtp_from_email' => $config['smtp_from_email'],
                'smtp_from_name'  => $config['smtp_from_name'],
                'bcc_email'       => $config['bcc_email'] ?? null,
                'source'          => 'database',
            ];
        }

        // Fallback: usar constantes del .env
        return [
            'smtp_host'       => SMTP_HOST,
            'smtp_port'       => (int)SMTP_PORT,
            'smtp_secure'     => SMTP_SECURE,
            'smtp_user'       => SMTP_USER,
            'smtp_pass'       => SMTP_PASS,
            'smtp_from_email' => SMTP_FROM,
            'smtp_from_name'  => SMTP_FROM_NAME,
            'bcc_email'       => null,
            'source'          => 'env',
        ];
    }

    /**
     * Crear o actualizar la configuración SMTP del tenant.
     */
    public function save(array $data): bool
    {
        $existing = $this->getActive();

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        return $this->insert($data);
    }

    /**
     * Insertar nueva configuración.
     */
    public function insert(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO email_config
            (smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, smtp_from_email, smtp_from_name, bcc_email, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        return $stmt->execute([
            $data['smtp_host'],
            $data['smtp_port'] ?? 465,
            $data['smtp_secure'] ?? 'ssl',
            $data['smtp_user'],
            $data['smtp_pass'],
            $data['smtp_from_email'],
            $data['smtp_from_name'] ?? '',
            $data['bcc_email'] ?? null,
        ]);
    }

    /**
     * Actualizar configuración existente.
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE email_config SET
                smtp_host = ?,
                smtp_port = ?,
                smtp_secure = ?,
                smtp_user = ?,
                smtp_pass = ?,
                smtp_from_email = ?,
                smtp_from_name = ?,
                bcc_email = ?,
                activo = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['smtp_host'],
            $data['smtp_port'] ?? 465,
            $data['smtp_secure'] ?? 'ssl',
            $data['smtp_user'],
            $data['smtp_pass'],
            $data['smtp_from_email'],
            $data['smtp_from_name'] ?? '',
            $data['bcc_email'] ?? null,
            $data['activo'] ?? 1,
            $id,
        ]);
    }

    /**
     * Eliminar configuración (vuelve a usar .env).
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM email_config WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Probar conexión SMTP con los datos proporcionados.
     */
    public static function testConnection(array $data): array
    {
        try {
            require_once BASE_PATH . '/vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $data['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $data['smtp_user'];
            $mail->Password   = $data['smtp_pass'];
            $mail->SMTPSecure = $data['smtp_secure'];
            $mail->Port       = $data['smtp_port'];
            $mail->setFrom($data['smtp_from_email'], $data['smtp_from_name'] ?? '');
            $mail->isHTML(true);

            // Solo verificar conexión, no enviar
            $mail->smtpConnect();
            $mail->getSMTPInstance()->close();

            return ['success' => true, 'message' => 'Conexión SMTP exitosa.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}
