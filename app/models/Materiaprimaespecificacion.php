<?php
require_once BASE_PATH . '/app/core/Model.php';

/**
 * Ficha tecnica de una materia prima (tabla materias_primas_especificaciones).
 *
 * Relacion 1:1 OPCIONAL con materias_primas: si la MP no tiene especificaciones
 * no existe fila. Los parametros son genericos; su significado depende del
 * tipo_gestion de la MP (LINEAL, SUPERFICIE, GRANEL, UNIDAD).
 */
class Materiaprimaespecificacion extends Model
{
    /** Tipos de gestion validos para una materia prima. */
    public const TIPOS_GESTION = ['UNIDAD', 'LINEAL', 'SUPERFICIE', 'GRANEL'];

    /** Campos de la ficha tecnica. */
    private const CAMPOS = [
        'parametro_texto_1',
        'parametro_numerico_1',
        'parametro_numerico_2',
        'presentacion',
        'obs',
    ];

    /**
     * Etiquetas cortas para selects, badges y listados.
     */
    public static function etiquetasTipoGestion(): array
    {
        return [
            'UNIDAD'     => 'Unidad',
            'LINEAL'     => 'Lineal',
            'SUPERFICIE' => 'Superficie',
            'GRANEL'     => 'Granel',
        ];
    }

    /**
     * Normaliza el tipo de gestion recibido. Cualquier valor invalido cae al default.
     */
    public static function normalizarTipoGestion(?string $tipo, string $default = 'UNIDAD'): string
    {
        $tipo = strtoupper(trim((string)$tipo));
        return in_array($tipo, self::TIPOS_GESTION, true) ? $tipo : $default;
    }

    /**
     * Devuelve la ficha tecnica de una MP, o null si no tiene.
     */
    public function findByMateriaPrima(int $materiaPrimaId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM materias_primas_especificaciones WHERE materia_prima_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $materiaPrimaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Indica si el array tiene al menos un dato de la ficha.
     */
    public static function tieneDatos(array $datos): bool
    {
        foreach (self::CAMPOS as $campo) {
            if (!array_key_exists($campo, $datos)) {
                continue;
            }
            $valor = $datos[$campo];
            if ($valor !== null && trim((string)$valor) !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * Indica si el POST trae campos de la ficha (para no borrarla en updates
     * que no la incluyen).
     */
    public static function camposPresentes(array $datos): bool
    {
        return count(array_intersect(self::CAMPOS, array_keys($datos))) > 0;
    }

    /**
     * Convierte un valor del formulario a numero. Devuelve null si viene vacio
     * o si no es un numero valido.
     */
    public static function normalizarNumero($valor, int $decimales = 4): ?float
    {
        if ($valor === null) {
            return null;
        }
        $valor = trim(str_replace(',', '.', (string)$valor));
        if ($valor === '' || !is_numeric($valor)) {
            return null;
        }
        return round((float)$valor, $decimales);
    }

    /**
     * Normaliza los campos de la ficha (textos recortados, numeros a float o null).
     */
    public static function normalizarDatos(array $datos): array
    {
        $texto = trim((string)($datos['parametro_texto_1'] ?? ''));
        $obs = trim((string)($datos['obs'] ?? ''));

        return [
            'parametro_texto_1'    => $texto !== '' ? mb_substr($texto, 0, 50) : null,
            'parametro_numerico_1' => self::normalizarNumero($datos['parametro_numerico_1'] ?? null),
            'parametro_numerico_2' => self::normalizarNumero($datos['parametro_numerico_2'] ?? null),
            'presentacion'         => self::normalizarNumero($datos['presentacion'] ?? null, 2),
            'obs'                  => $obs !== '' ? mb_substr($obs, 0, 255) : null,
        ];
    }

    /**
     * Guarda (inserta o actualiza) la ficha de la MP. Si no hay ningun dato,
     * elimina la ficha para dejar la MP sin especificaciones.
     *
     * No abre transaccion propia: la maneja quien llama (MateriaPrima::create/update).
     */
    public function guardar(int $materiaPrimaId, array $datos): bool
    {
        $ficha = self::normalizarDatos($datos);

        if (!self::tieneDatos($ficha)) {
            $this->eliminar($materiaPrimaId);
            return false;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO materias_primas_especificaciones
                (materia_prima_id, parametro_texto_1, parametro_numerico_1, parametro_numerico_2, presentacion, obs)
             VALUES (:mp, :texto1, :num1, :num2, :presentacion, :obs)
             ON DUPLICATE KEY UPDATE
                parametro_texto_1 = VALUES(parametro_texto_1),
                parametro_numerico_1 = VALUES(parametro_numerico_1),
                parametro_numerico_2 = VALUES(parametro_numerico_2),
                presentacion = VALUES(presentacion),
                obs = VALUES(obs)"
        );
        $stmt->execute([
            'mp'            => $materiaPrimaId,
            'texto1'        => $ficha['parametro_texto_1'],
            'num1'          => $ficha['parametro_numerico_1'],
            'num2'          => $ficha['parametro_numerico_2'],
            'presentacion'  => $ficha['presentacion'],
            'obs'           => $ficha['obs'],
        ]);
        return true;
    }

    /**
     * Elimina la ficha de la MP (no toca la MP).
     */
    public function eliminar(int $materiaPrimaId): void
    {
        $this->db->prepare(
            "DELETE FROM materias_primas_especificaciones WHERE materia_prima_id = :id"
        )->execute(['id' => $materiaPrimaId]);
    }
}
