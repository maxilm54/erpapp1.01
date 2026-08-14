<?php
require_once BASE_PATH . '/app/core/Model.php';

class EmailTemplate extends Model
{
    protected string $table = 'email_templates';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Listar todos los templates activos.
     */
    public function all(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM email_templates ORDER BY tipo, es_default DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener template por ID.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM email_templates WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtener el template activo para un tipo específico.
     * Prioriza templates personalizados (es_default=0) sobre los por defecto.
     */
    public function getActiveByType(string $tipo): ?array
    {
        try {
            // Primero buscar template personalizado
            $stmt = $this->db->prepare(
                "SELECT * FROM email_templates WHERE tipo = ? AND activo = 1 AND es_default = 0 LIMIT 1"
            );
            $stmt->execute([$tipo]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) return $result;

            // Si no hay personalizado, usar el default
            $stmt = $this->db->prepare(
                "SELECT * FROM email_templates WHERE tipo = ? AND activo = 1 AND es_default = 1 LIMIT 1"
            );
            $stmt->execute([$tipo]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log("EmailTemplate: Tabla email_templates no disponible: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear un nuevo template.
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO email_templates (tipo, asunto, cuerpo_html, activo, es_default)
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([
            $data['tipo'],
            $data['asunto'],
            $data['cuerpo_html'],
            $data['activo'] ?? 1,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Actualizar un template.
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE email_templates SET
                tipo = ?,
                asunto = ?,
                cuerpo_html = ?,
                activo = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['tipo'],
            $data['asunto'],
            $data['cuerpo_html'],
            $data['activo'] ?? 1,
            $id,
        ]);
    }

    /**
     * Clonar un template default para personalizar.
     */
    public function cloneDefault(int $defaultId, array $overrides = []): int
    {
        $original = $this->find($defaultId);
        if (!$original) throw new Exception('Template no encontrado');

        return $this->create([
            'tipo'       => $overrides['tipo'] ?? $original['tipo'],
            'asunto'     => $overrides['asunto'] ?? $original['asunto'],
            'cuerpo_html'=> $overrides['cuerpo_html'] ?? $original['cuerpo_html'],
            'activo'     => $overrides['activo'] ?? 1,
        ]);
    }

    /**
     * Eliminar un template (solo los no-default).
     */
    public function delete(int $id): bool
    {
        $template = $this->find($id);
        if ($template && $template['es_default']) {
            throw new Exception('No se puede eliminar un template por defecto. Desactívelo en su lugar.');
        }
        $stmt = $this->db->prepare("DELETE FROM email_templates WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Restaurar template default (eliminar el personalizado y dejar que se use el default).
     */
    public function resetToDefault(string $tipo): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM email_templates WHERE tipo = ? AND es_default = 0"
        );
        return $stmt->execute([$tipo]);
    }

    /**
     * Renderizar un template con los datos proporcionados usando sintaxis simple {{variable}}.
     * Soporta:
     *   {{variable}} - reemplazo directo
     *   {{#observaciones}}...{{/observaciones}} - bloque condicional
     *   {{>detalle_tabla}} - inclusión de contenido HTML directo
     */
    public function render(string $html, array $data): string
    {
        // 1. Primero: inclusiones de contenido HTML directo: {{>clave}} (sin escapar)
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $html = str_replace('{{>' . $key . '}}', $value, $html);
            }
        }

        // 2. Después: variables simples: {{variable}}
        // Si el valor contiene HTML y la key no fue reemplazada por {{>}}, reemplazar sin escapar
        // (para templates que aún usan {{clave}} en vez de {{>clave}} con contenido HTML)
        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $placeholder = '{{' . $key . '}}';
                if (strpos($html, $placeholder) !== false) {
                    if (is_string($value) && strpos($value, '<') !== false) {
                        // Contenido HTML: reemplazar sin escapar
                        $html = str_replace($placeholder, $value, $html);
                    } else {
                        $html = str_replace($placeholder, htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'), $html);
                    }
                }
            }
        }

        // 3. Bloques condicionales: {{#clave}}...{{/clave}}
        $html = preg_replace_callback(
            '/\{\{#(\w+)\}\}(.*?)\{\{\/\1\}\}/s',
            function ($matches) use ($data) {
                $key = $matches[1];
                $content = $matches[2];
                return !empty($data[$key]) ? str_replace('{{' . $key . '}}', $data[$key], $content) : '';
            },
            $html
        );

        return $html;
    }

    /**
     * Obtener todos los tipos disponibles.
     */
    public static function getTipos(): array
    {
        return [
            'REMITO'       => 'Remito de Salida',
            'PAGO'         => 'Comprobante de Pago',
            'PRESUPUESTO'  => 'Presupuesto',
            'NOTA_PEDIDO'  => 'Nota de Pedido',
            'FACTURA'      => 'Factura',
            'ORDEN_COMPRA' => 'Orden de Compra',
            'GENERIC'      => 'Genérico',
        ];
    }
}
