<?php

require_once BASE_PATH . '/app/core/Model.php';

/**
 * Campos personalizados por modulo, configurados desde Empresa -> Configuraciones.
 *
 * Cada empresa define campos adicionales (nombre + tipo: texto/numero/fecha) que
 * aparecen en los formularios de PRESUPUESTO, NOTA_PEDIDO, REMITO_SALIDA y
 * ORDEN_COMPRA. Los valores se guardan por documento en
 * campos_personalizados_valores con snapshot del nombre del campo, asi el
 * historial sigue visible aunque el campo se renombre o se elimine.
 *
 * Al generar una Nota de Pedido desde un Presupuesto, copiarTodos() pasa los
 * valores del presupuesto a la NP con el mismo campo_id, por lo que se ven en
 * la NP aunque ahi no tenga configurado ese campo.
 */
class Campopersonalizado extends Model
{
    /** Modulos que aceptan campos personalizados. */
    public const MODULOS = ['PRESUPUESTO', 'NOTA_PEDIDO', 'REMITO_SALIDA', 'ORDEN_COMPRA'];

    /** Tipos de input permitidos. */
    public const TIPOS = ['texto', 'numero', 'fecha'];

    /** Etiquetas para la UI. */
    public const MODULOS_LABELS = [
        'PRESUPUESTO'   => 'Presupuestos',
        'NOTA_PEDIDO'   => 'Notas de Pedido',
        'REMITO_SALIDA' => 'Remitos de Salida',
        'ORDEN_COMPRA'  => 'Órdenes de Compra',
    ];

    public static function moduloValido(string $modulo): bool
    {
        return in_array(strtoupper($modulo), self::MODULOS, true);
    }

    /**
     * Campos activos de un modulo, en el orden de carga (es el orden del formulario).
     */
    public function porModulo(string $modulo): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM campos_personalizados
             WHERE modulo = :modulo AND activo = 1
             ORDER BY id"
        );
        $stmt->execute(['modulo' => strtoupper($modulo)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Todos los campos (activos y de baja) para el panel de Configuraciones.
     */
    public function todos(): array
    {
        return $this->db->query(
            "SELECT * FROM campos_personalizados ORDER BY modulo, id"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM campos_personalizados WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Alta de un campo configurable. Lanza Exception si el modulo es invalido,
     * el nombre esta vacio o ya existe otro campo activo con ese nombre en el modulo.
     */
    public function crear(string $modulo, string $nombre, string $tipo = 'texto'): int
    {
        $modulo = strtoupper(trim($modulo));
        $nombre = trim($nombre);
        $tipo   = strtolower(trim($tipo));
        if ($tipo === '') {
            $tipo = 'texto';
        }

        if (!self::moduloValido($modulo)) {
            throw new Exception('Módulo inválido para campos personalizados.');
        }
        if ($nombre === '' || mb_strlen($nombre) > 150) {
            throw new Exception('El nombre del campo es obligatorio (máximo 150 caracteres).');
        }
        if (!in_array($tipo, self::TIPOS, true)) {
            $tipo = 'texto';
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM campos_personalizados
             WHERE modulo = :modulo AND nombre = :nombre AND activo = 1"
        );
        $stmt->execute(['modulo' => $modulo, 'nombre' => $nombre]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new Exception("Ya existe un campo '{$nombre}' en ese módulo.");
        }

        $stmt = $this->db->prepare(
            "INSERT INTO campos_personalizados (modulo, nombre, tipo)
             VALUES (:modulo, :nombre, :tipo)"
        );
        $stmt->execute(['modulo' => $modulo, 'nombre' => $nombre, 'tipo' => $tipo]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Renombra/cambia el tipo de un campo. Los valores ya guardados conservan
     * su snapshot del nombre (historial intacto).
     */
    public function actualizar(int $id, string $nombre, string $tipo): bool
    {
        $nombre = trim($nombre);
        if ($nombre === '' || mb_strlen($nombre) > 150) {
            throw new Exception('El nombre del campo es obligatorio (máximo 150 caracteres).');
        }
        if (!in_array($tipo, self::TIPOS, true)) {
            $tipo = 'texto';
        }

        $stmt = $this->db->prepare(
            "UPDATE campos_personalizados
             SET nombre = :nombre, tipo = :tipo
             WHERE id = :id AND activo = 1"
        );
        return $stmt->execute(['nombre' => $nombre, 'tipo' => $tipo, 'id' => $id]);
    }

    /**
     * Baja lógica del campo (activo = 0): deja de aparecer en los formularios.
     * Los valores ya guardados siguen visibles en los documentos (snapshot del nombre).
     */
    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE campos_personalizados SET activo = 0 WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Todos los valores guardados de un documento, incluidos los heredados
     * (ej: NP con campos configurados en su presupuesto). El nombre resuelto es
     * el snapshot guardado al cargar el valor; si no hay snapshot, el nombre
     * actual de la configuracion.
     */
    public function valoresDe(string $modulo, int $documentoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT v.id, v.campo_id, v.valor, v.created_at,
                    COALESCE(NULLIF(v.campo_nombre, ''), cp.nombre) AS campo_nombre,
                    cp.tipo
             FROM campos_personalizados_valores v
             LEFT JOIN campos_personalizados cp ON cp.id = v.campo_id
             WHERE v.modulo = :modulo AND v.documento_id = :doc
             ORDER BY v.id"
        );
        $stmt->execute(['modulo' => strtoupper($modulo), 'doc' => $documentoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Solo los valores con contenido (para vistas y PDFs).
     */
    public function valoresVisibles(string $modulo, int $documentoId): array
    {
        $res = [];
        foreach ($this->valoresDe($modulo, $documentoId) as $v) {
            if (trim((string)$v['valor']) !== '') {
                $res[] = $v;
            }
        }
        return $res;
    }

    /**
     * Mapa [campo_id => valor] para prellenar formularios (edicion).
     */
    public function valoresMapa(string $modulo, int $documentoId): array
    {
        $mapa = [];
        foreach ($this->valoresDe($modulo, $documentoId) as $v) {
            $mapa[(int)$v['campo_id']] = (string)$v['valor'];
        }
        return $mapa;
    }

    /**
     * Guarda los valores que llegan del formulario ($_POST['campos_cp']).
     * Solo acepta campos activos del modulo; reescribe esas filas sin tocar las
     * heredadas de otro modulo. Devuelve la cantidad de filas escritas.
     */
    public function guardarDesdePost(string $modulo, int $documentoId, array $post): int
    {
        $campos = $this->porModulo($modulo);
        if (empty($campos)) {
            return 0;
        }

        $valores = [];
        foreach ($campos as $c) {
            $id = (int)$c['id'];
            if (!array_key_exists($id, $post)) {
                continue; // el form no lo envio (la config cambio mientras se cargaba)
            }
            $valores[$id] = trim((string)$post[$id]);
        }

        if (empty($valores)) {
            return 0;
        }

        return $this->guardarValores($modulo, $documentoId, $valores, $campos);
    }

    /**
     * Reescribe las filas de los campos indicados para un documento.
     * $valores: [campo_id => valor]; $camposConfig: filas de configuracion del
     * modulo (se usan para el snapshot del nombre).
     */
    public function guardarValores(string $modulo, int $documentoId, array $valores, array $camposConfig): int
    {
        $modulo = strtoupper($modulo);
        $nombres = [];
        foreach ($camposConfig as $c) {
            $nombres[(int)$c['id']] = (string)$c['nombre'];
        }

        $ids = array_map('intval', array_keys($valores));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $this->db->prepare(
            "DELETE FROM campos_personalizados_valores
             WHERE modulo = ? AND documento_id = ? AND campo_id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$modulo, $documentoId], $ids));

        $stmt = $this->db->prepare(
            "INSERT INTO campos_personalizados_valores
             (campo_id, modulo, documento_id, campo_nombre, valor)
             VALUES (?, ?, ?, ?, ?)"
        );
        $n = 0;
        foreach ($valores as $campoId => $valor) {
            $campoId = (int)$campoId;
            $stmt->execute([
                $campoId,
                $modulo,
                $documentoId,
                $nombres[$campoId] ?? null,
                $valor === '' ? null : mb_substr($valor, 0, 255),
            ]);
            $n++;
        }
        return $n;
    }

    /**
     * Copia los valores de un documento a otro (ej: Presupuesto -> Nota de Pedido
     * generada desde ese presupuesto). Las filas heredadas quedan con el mismo
     * campo_id y snapshot del nombre, asi se ven en el destino aunque ahi el
     * campo no este configurado. Devuelve la cantidad de filas afectadas.
     */
    public function copiarTodos(string $moduloOrigen, int $docOrigenId, string $moduloDestino, int $docDestinoId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO campos_personalizados_valores
                (campo_id, modulo, documento_id, campo_nombre, valor)
             SELECT v.campo_id, :dest, :docDest, COALESCE(NULLIF(v.campo_nombre, ''), cp.nombre), v.valor
             FROM campos_personalizados_valores v
             LEFT JOIN campos_personalizados cp ON cp.id = v.campo_id
             WHERE v.modulo = :origen AND v.documento_id = :docOrigen
             ON DUPLICATE KEY UPDATE campo_nombre = VALUES(campo_nombre), valor = VALUES(valor)"
        );
        $stmt->execute([
            'dest'      => strtoupper($moduloDestino),
            'docDest'   => $docDestinoId,
            'origen'    => strtoupper($moduloOrigen),
            'docOrigen' => $docOrigenId,
        ]);
        return $stmt->rowCount();
    }
}
