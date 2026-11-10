<?php

require_once BASE_PATH . '/app/core/Model.php';

/**
 * Archivos adjuntos de los documentos del tenant:
 * presupuestos, notas de pedido, remitos de salida y ordenes de produccion.
 *
 * Los archivos viven en storage/empresa_{tenantId}/adjuntos/{modulo}/{documentoId}/
 * (nunca servidos directo por Apache: se descargan via AdjuntosController::descargar).
 */
class Adjunto extends Model
{
    public const MODULOS = ['PRESUPUESTO', 'NOTA_PEDIDO', 'REMITO_SALIDA', 'PRODUCCION', 'ORDEN_COMPRA'];

    /** Extensiones permitidas (whitelist). */
    private const EXTENSIONES = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic',
        'doc', 'docx', 'odt', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx',
        'txt', 'zip', 'rar', 'msg', 'eml',
    ];

    /** Tamano maximo por archivo (10 MB). */
    private const MAX_BYTES = 10485760;

    public static function moduloValido(string $modulo): bool
    {
        return in_array(strtoupper($modulo), self::MODULOS, true);
    }

    /**
     * Adjuntos de un documento, del mas nuevo al mas viejo.
     */
    public function porDocumento(string $modulo, int $documentoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, u.nombre AS usuario_nombre
             FROM documentos_adjuntos a
             LEFT JOIN users u ON u.id = a.usuario_id
             WHERE a.modulo = :modulo AND a.documento_id = :doc
             ORDER BY a.created_at DESC, a.id DESC"
        );
        $stmt->execute(['modulo' => strtoupper($modulo), 'doc' => $documentoId]);
        return $stmt->fetchAll();
    }

    public function countPorDocumento(string $modulo, int $documentoId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM documentos_adjuntos WHERE modulo = :modulo AND documento_id = :doc"
        );
        $stmt->execute(['modulo' => strtoupper($modulo), 'doc' => $documentoId]);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM documentos_adjuntos WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Guarda un archivo subido ($_FILES['x'] de un solo file).
     * Devuelve el id del adjunto o lanza Exception con el motivo.
     */
    public function guardar(string $modulo, int $documentoId, array $file): int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception(self::mensajeErrorUpload((int)($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }
        if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new Exception('El archivo supera el maximo de 10 MB o esta vacio.');
        }

        $nombreOriginal = basename((string)$file['name']);
        $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONES, true)) {
            throw new Exception("Extension no permitida (.{$ext}). Permitidos: "
                . implode(', ', self::EXTENSIONES));
        }

        $dir = empresaStoragePath('adjuntos/' . strtoupper($modulo) . '/' . $documentoId);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $nombreGuardado = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = $dir . '/' . $nombreGuardado;

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            throw new Exception('No se pudo mover el archivo subido al storage.');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO documentos_adjuntos
             (modulo, documento_id, nombre_original, nombre_guardado, tamano, mime, usuario_id)
             VALUES (:modulo, :doc, :original, :guardado, :tamano, :mime, :usuario)"
        );
        $stmt->execute([
            'modulo'   => strtoupper($modulo),
            'doc'      => $documentoId,
            'original' => mb_substr($nombreOriginal, 0, 255),
            'guardado' => $nombreGuardado,
            'tamano'   => (int)$file['size'],
            'mime'     => mb_substr((string)($file['type'] ?? ''), 0, 100),
            'usuario'  => $_SESSION['user_id'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Guarda varios files de un input multiple. Devuelve la cantidad guardada.
     */
    public function guardarMultiples(string $modulo, int $documentoId, array $files): int
    {
        $guardados = 0;
        if (empty($files['name']) || !is_array($files['name'])) {
            return 0;
        }
        foreach ($files['name'] as $i => $nombre) {
            $uno = [
                'name'     => $nombre,
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$i] ?? 0,
            ];
            if ((int)$uno['error'] === UPLOAD_ERR_NO_FILE) continue;
            $this->guardar($modulo, $documentoId, $uno);
            $guardados++;
        }
        return $guardados;
    }

    /**
     * Copia un adjunto a otro documento (ej: al generar la NP desde el PR aprobado).
     * Copia el archivo en disco y crea la fila nueva.
     */
    public function copiarADocumento(int $adjuntoId, string $moduloDestino, int $documentoDestinoId): ?int
    {
        $origen = $this->find($adjuntoId);
        if (!$origen) return null;

        $dirDestino = empresaStoragePath('adjuntos/' . strtoupper($moduloDestino) . '/' . $documentoDestinoId);
        if (!is_dir($dirDestino)) {
            mkdir($dirDestino, 0775, true);
        }

        $ext = strtolower(pathinfo($origen['nombre_guardado'], PATHINFO_EXTENSION));
        $nombreGuardado = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = $dirDestino . '/' . $nombreGuardado;

        $rutaOrigen = $this->rutaFisica($origen);
        if (!file_exists($rutaOrigen)) return null;
        if (!copy($rutaOrigen, $destino)) return null;

        $stmt = $this->db->prepare(
            "INSERT INTO documentos_adjuntos
             (modulo, documento_id, nombre_original, nombre_guardado, tamano, mime, usuario_id)
             VALUES (:modulo, :doc, :original, :guardado, :tamano, :mime, :usuario)"
        );
        $stmt->execute([
            'modulo'   => strtoupper($moduloDestino),
            'doc'      => $documentoDestinoId,
            'original' => $origen['nombre_original'],
            'guardado' => $nombreGuardado,
            'tamano'   => $origen['tamano'],
            'mime'     => $origen['mime'],
            'usuario'  => $_SESSION['user_id'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Copia TODOS los adjuntos de un documento a otro. Devuelve la cantidad copiada.
     */
    public function copiarTodos(string $moduloOrigen, int $documentoOrigenId, string $moduloDestino, int $documentoDestinoId): int
    {
        $copiados = 0;
        foreach ($this->porDocumento($moduloOrigen, $documentoOrigenId) as $a) {
            if ($this->copiarADocumento((int)$a['id'], $moduloDestino, $documentoDestinoId)) {
                $copiados++;
            }
        }
        return $copiados;
    }

    /**
     * Elimina un adjunto (archivo fisico + fila).
     */
    public function eliminar(int $id): bool
    {
        $adj = $this->find($id);
        if (!$adj) return false;

        $ruta = $this->rutaFisica($adj);
        if (file_exists($ruta)) {
            @unlink($ruta);
        }

        $stmt = $this->db->prepare("DELETE FROM documentos_adjuntos WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Ruta fisica del archivo en storage.
     */
    public function rutaFisica(array $adjunto): string
    {
        return empresaStoragePath('adjuntos/' . $adjunto['modulo'] . '/' . $adjunto['documento_id'])
            . '/' . basename($adjunto['nombre_guardado']);
    }

    private static function mensajeErrorUpload(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el maximo permitido por el servidor (upload_max_filesize).',
            UPLOAD_ERR_PARTIAL => 'El archivo se subio parcialmente. Intente de nuevo.',
            UPLOAD_ERR_NO_FILE => 'No se selecciono ningun archivo.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Error del servidor al escribir el archivo temporal.',
            default => 'Error al subir el archivo (codigo ' . $code . ').',
        };
    }
}
