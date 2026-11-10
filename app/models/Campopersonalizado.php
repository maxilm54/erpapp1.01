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
     * Igual que valoresMapa, pero los campos configurados del modulo que no tienen
     * valor propio se prellenan con el valor de una fila heredada del mismo nombre
     * (ej: NP con campo agregado a la configuracion DESPUES de generarse). Asi el
     * usuario ve el valor y al guardar no lo pierde: la fila heredada queda
     * reemplazada por la propia.
     */
    public function valoresMapaConHeredadas(string $modulo, int $documentoId): array
    {
        $mapa = $this->valoresMapa($modulo, $documentoId);

        $campos = $this->porModulo($modulo);
        if (empty($campos)) {
            return $mapa;
        }

        // nombre normalizado => campo_id (para matchear heredadas) + ids propios
        $porNombre = [];
        $idsConfig = [];
        foreach ($campos as $c) {
            $porNombre[mb_strtolower(trim((string)$c['nombre']))] = (int)$c['id'];
            $idsConfig[] = (int)$c['id'];
        }

        foreach ($this->valoresDe($modulo, $documentoId) as $v) {
            $campoId = (int)$v['campo_id'];
            if (in_array($campoId, $idsConfig, true)) {
                continue; // fila propia del modulo: ya esta en el mapa
            }
            // fila heredada: si su nombre matchea un campo activo del modulo sin
            // valor propio, ese campo se prellena con el valor heredado
            $nombre = mb_strtolower(trim((string)($v['campo_nombre'] ?? '')));
            if ($nombre === '' || !isset($porNombre[$nombre])) {
                continue;
            }
            $destId = $porNombre[$nombre];
            if (isset($mapa[$destId]) && trim((string)$mapa[$destId]) !== '') {
                continue; // el campo propio ya tiene valor
            }
            if (trim((string)$v['valor']) === '') {
                continue;
            }
            $mapa[$destId] = (string)$v['valor'];
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

        $escritas = $this->guardarValores($modulo, $documentoId, $valores, $campos);

        // Los campos propios del modulo que recibieron valor "absorben" las filas
        // heredadas del mismo nombre (evita mostrar el valor duplicado: una vez
        // como fila heredada y otra en el campo propio).
        $idsConfig = array_map('intval', array_column($campos, 'id'));
        $nombres = [];
        foreach ($campos as $c) {
            if (array_key_exists((int)$c['id'], $valores)) {
                $nombres[] = (string)$c['nombre'];
            }
        }
        if (!empty($nombres)) {
            $placeholdersIds = implode(',', array_fill(0, count($idsConfig), '?'));
            $placeholdersNombres = implode(',', array_fill(0, count($nombres), '?'));
            $stmt = $this->db->prepare(
                "DELETE v FROM campos_personalizados_valores v
                 LEFT JOIN campos_personalizados cp ON cp.id = v.campo_id
                 WHERE v.modulo = ?
                   AND v.documento_id = ?
                   AND v.campo_id NOT IN ($placeholdersIds)
                   AND COALESCE(NULLIF(v.campo_nombre, ''), cp.nombre) IN ($placeholdersNombres)"
            );
            $stmt->execute(array_merge(
                [strtoupper($modulo), $documentoId],
                $idsConfig,
                $nombres
            ));
        }

        return $escritas;
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
     * generada desde ese presupuesto), en dos pasos:
     *
     *  1) Si el modulo destino tiene un campo ACTIVO con el mismo nombre, el valor
     *     se guarda en ese campo propio del destino (el formulario del destino
     *     lo muestra cargado).
     *  2) Si no hay campo con ese nombre en el destino, la fila se copia como
     *     "heredada" (mismo campo_id + snapshot del nombre), asi se ve en el
     *     destino aunque ahi el campo no este configurado.
     *
     * Devuelve la cantidad de filas afectadas.
     */
    public function copiarTodos(string $moduloOrigen, int $docOrigenId, string $moduloDestino, int $docDestinoId): int
    {
        $moduloDestino = strtoupper($moduloDestino);
        $afectadas = 0;

        // 1) Valores que van a campos propios del destino (match por nombre)
        $stmt = $this->db->prepare(
            "INSERT INTO campos_personalizados_valores
                (campo_id, modulo, documento_id, campo_nombre, valor)
             SELECT dest.id, :dest1, :docDest1, dest.nombre, v.valor
             FROM campos_personalizados_valores v
             LEFT JOIN campos_personalizados cp ON cp.id = v.campo_id
             JOIN campos_personalizados dest
                  ON dest.modulo = :dest2 AND dest.activo = 1
                 AND dest.nombre = COALESCE(NULLIF(v.campo_nombre, ''), cp.nombre)
             WHERE v.modulo = :origen AND v.documento_id = :docOrigen
             ON DUPLICATE KEY UPDATE campo_nombre = VALUES(campo_nombre), valor = VALUES(valor)"
        );
        $stmt->execute([
            'dest1'     => $moduloDestino,
            'docDest1'  => $docDestinoId,
            'dest2'     => $moduloDestino,
            'origen'    => strtoupper($moduloOrigen),
            'docOrigen' => $docOrigenId,
        ]);
        $afectadas += $stmt->rowCount();

        // 2) El resto se copia como filas heredadas (sin campo propio que las reciba)
        $stmt = $this->db->prepare(
            "INSERT INTO campos_personalizados_valores
                (campo_id, modulo, documento_id, campo_nombre, valor)
             SELECT v.campo_id, :dest1, :docDest1, COALESCE(NULLIF(v.campo_nombre, ''), cp.nombre), v.valor
             FROM campos_personalizados_valores v
             LEFT JOIN campos_personalizados cp ON cp.id = v.campo_id
             LEFT JOIN campos_personalizados dest
                  ON dest.modulo = :dest2 AND dest.activo = 1
                 AND dest.nombre = COALESCE(NULLIF(v.campo_nombre, ''), cp.nombre)
             WHERE v.modulo = :origen AND v.documento_id = :docOrigen
               AND dest.id IS NULL
             ON DUPLICATE KEY UPDATE campo_nombre = VALUES(campo_nombre), valor = VALUES(valor)"
        );
        $stmt->execute([
            'dest1'     => $moduloDestino,
            'docDest1'  => $docDestinoId,
            'dest2'     => $moduloDestino,
            'origen'    => strtoupper($moduloOrigen),
            'docOrigen' => $docOrigenId,
        ]);
        $afectadas += $stmt->rowCount();

        return $afectadas;
    }

    /**
     * Elimina una fila de valor SI pertenece al documento indicado (control de
     * propiedad). Se usa para quitar valores heredados desde la vista del documento.
     */
    public function quitarValor(int $valorId, string $modulo, int $documentoId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM campos_personalizados_valores
             WHERE id = :id AND modulo = :modulo AND documento_id = :doc"
        );
        $stmt->execute(['id' => $valorId, 'modulo' => strtoupper($modulo), 'doc' => $documentoId]);
        return $stmt->rowCount() > 0;
    }
}
