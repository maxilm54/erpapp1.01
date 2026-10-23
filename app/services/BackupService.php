<?php
/**
 * Servicio de respaldo de las bases del ERP (master + tenants).
 *
 * Lo usan el CLI (app/helpers/backup.php) y el panel admin (/admin/backups),
 * para que la logica de volcado viva en un solo lugar.
 *
 * OJO: conecta directamente a la BD master leyendo .env, NO usa
 * Database::getInstance(). En CLI no hay tenant en la sesion y el
 * Database caeria silenciosamente a la BD por defecto del .env.
 *
 * OJO 2: los .sql NO se guardan dentro del repo. Quedarian servidos como
 * archivos estaticos por Apache (el webroot es el padre del proyecto) y
 * contienen hashes de password y datos de las empresas. El destino por
 * defecto es <dos niveles arriba del proyecto>/_erp_backups, y se puede
 * cambiar con la clave BACKUP_PATH en el .env.
 */

class BackupService
{
    /** @var string mysqldump a ejecutar. */
    private string $mysqldump;
    /** @var string valor configurado en MYSQLDUMP_PATH ('' si no hay). */
    private string $mysqldumpConfigurado;
    private string $dbHost;
    private string $dbUser;
    private string $dbPass;

    public function __construct()
    {
        $env = $this->leerEnv();

        $this->dbHost = $env['DB_HOST'] ?? 'localhost';
        $this->dbUser = $env['DB_USER'] ?? 'root';
        $this->dbPass = $env['DB_PASS'] ?? '';

        // La variable de entorno manda sobre el .env (igual que con
        // BACKUP_PATH): asi el cron puede sobreescribirla sin editar nada.
        $configurado = getenv('MYSQLDUMP_PATH') ?: ($env['MYSQLDUMP_PATH'] ?? '');

        $this->mysqldumpConfigurado = $configurado;
        $this->mysqldump            = $this->resolverMysqldump($configurado);
    }

    /**
     * Busca mysqldump: primero la ruta del .env, despues la de XAMPP en
     * Windows, por ultimo el PATH (Linux / hosting).
     *
     * Si MYSQLDUMP_PATH esta mal escrito NO se falla aca: se cae al binario
     * siguiente y comprobarEntorno() avisa del problema en firme. Un
     * MYSQLDUMP_PATH equivocado que se ignorara en silencio dejaria el panel
     * diciendo "entorno listo" y el respaldo reventaria despues.
     */
    private function resolverMysqldump(string $configurado): string
    {
        if ($configurado !== '' && file_exists($configurado)) {
            return $configurado;
        }

        $xampp = 'C:/xampp/mysql/bin/mysqldump.exe';
        if (file_exists($xampp)) {
            return $xampp;
        }

        return 'mysqldump';
    }

    // =====================================================================
    // Configuracion
    // =====================================================================

    /**
     * Lee el .env del proyecto. Sirve tanto en web como en CLI.
     */
    private function leerEnv(): array
    {
        $raiz = defined('BASE_PATH')
            ? BASE_PATH
            : dirname(__DIR__, 2); // app/services/ -> app/ -> raiz

        if (!file_exists($raiz . '/.env')) {
            return [];
        }

        return parse_ini_file($raiz . '/.env', false, INI_SCANNER_TYPED) ?: [];
    }

    /**
     * Directorio base donde viven los respaldos.
     * Por defecto queda FUERA del webroot (se sirve por HTTP lo que este
     * bajo C:/xampp/htdocs). Se puede cambiar con BACKUP_PATH en el .env.
     */
    /**
     * Directorio base donde viven los respaldos.
     *
     * Si BACKUP_PATH esta definido en el .env, manda ese y si no sirve se
     * falla en firme (no se cae en silencio a otro lado).
     *
     * Sin configurar, se prueban varios candidatos y queda el primero que
     * sea escribible y este fuera del webroot. Hacen falta varios porque
     * la altura de la raiz del proyecto cambia segun el servidor:
     *   - XAMPP:      C:/xampp/htdocs/app  -> dos arriba da C:/xampp
     *   - hosting:    /home/usuario/app    -> UNO arriba da /home/usuario
     * Con un solo candidato fijo, en un servidor de los dos falla.
     */
    public function destinoBase(): string
    {
        // 1) el que este configurado: manda el, y si no anda se avisa.
        $configurado = getenv('BACKUP_PATH') ?: '';
        if ($configurado === '') {
            $configurado = $this->leerEnv()['BACKUP_PATH'] ?? '';
        }

        if ($configurado !== '') {
            if (!is_dir($configurado) && !mkdir($configurado, 0775, true) && !is_dir($configurado)) {
                throw new RuntimeException(
                    "BACKUP_PATH apunta a '{$configurado}' y no se pudo crear el directorio. "
                    . "Revisa la clave en el .env o dale permisos de escritura a la carpeta."
                );
            }

            $this->avisarSiEsWebroot($configurado);

            return $this->normalizarRuta($configurado);
        }

        // 2) candidatos por defecto.
        $ultimoError = null;
        foreach ($this->candidatosDestino() as $candidato) {
            // Si no se puede crear, seguimos con el siguiente.
            if (!is_dir($candidato) && !@mkdir($candidato, 0775, true) && !is_dir($candidato)) {
                $ultimoError = $candidato;
                continue;
            }

            // Un destino dentro del webroot dejaria los .sql (con hashes de
            // password) servidos por HTTP: no lo usamos.
            if ($this->esWebrootProbable($candidato)) {
                continue;
            }

            return $this->normalizarRuta($candidato);
        }

        throw new RuntimeException(
            'No hay ningun directorio de respaldos escribible y fuera del webroot. '
            . 'Defini BACKUP_PATH en el .env con una carpeta con permisos de escritura'
            . ($ultimoError !== null ? " (se probo '{$ultimoError}')" : '') . '.'
        );
    }

    /**
     * Pone todas las barras en un mismo sentido. En Windows dirname() devuelve
     * contrabarra y el resto del repo usa '/', lo que deja rutas con separadores
     * mezclados en el panel.
     */
    private function normalizarRuta(string $ruta): string
    {
        return rtrim(str_replace('\\', '/', $ruta), '/');
    }

    /**
     * Destinos por defecto, en orden de prioridad.
     * Se calculan desde la raiz del proyecto (app/services/BackupService.php
     * -> dos niveles arriba).
     *
     * @param string|null $raiz Raiz del proyecto. Solo para los tests: deja
     *                          simular otro layout de servidor.
     * @return string[]
     */
    private function candidatosDestino(?string $raiz = null): array
    {
        $raiz = $raiz ?? dirname(__DIR__, 2);

        $candidatos = [
            // hosting tipico: /home/usuario/app -> /home/usuario/_erp_backups
            dirname($raiz) . '/_erp_backups',
            // XAMPP: C:/xampp/htdocs/app -> C:/xampp/_erp_backups
            dirname($raiz, 2) . '/_erp_backups',
            // ultimo recurso: siempre escribible, nunca esta en el webroot.
            rtrim(sys_get_temp_dir(), '/\\') . '/erp_backups',
        ];

        return array_values(array_unique($candidatos));
    }

    /**
     * True si el directorio quedaria servible por el web server.
     *
     * Se fija en dos señales concretas, no en suposiciones:
     *   - DOCUMENT_ROOT, cuando hay (peticion web).
     *   - El patron de esta app: BASE_URL es {host}/{nombre_proyecto}/public,
     *     asi que si el padre del proyecto contiene <nombre>/public, ese padre
     *     es el webroot. Es lo que pasa en XAMPP y en hosting con public_html.
     *
     * @param string|null $raiz Raiz del proyecto. Solo para los tests.
     */
    private function esWebrootProbable(string $dir, ?string $raiz = null): bool
    {
        $dirReal = realpath($dir);
        if ($dirReal === false) {
            // Todavia no existe el directorio (chequeo previo a crearlo):
            // se normaliza contra el padre, que si existe, y se le agrega
            // el nombre. Asi la comprobacion sirve en los dos ordenes.
            $padreReal = realpath(dirname($dir));
            if ($padreReal === false) {
                return false;
            }
            $dirReal = $padreReal . DIRECTORY_SEPARATOR . basename($dir);
        }

        // 1) Con DOCUMENT_ROOT no hay que adivinar: es el webroot real del
        //    servidor. Este es el caso del panel, y el que decide sobre
        //    produccion. OJO: en CLI ese superglobal trae el directorio de
        //    trabajo, no el webroot, asi que solo se confia con una peticion
        //    web de verdad.
        $enWeb = (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg');
        if ($enWeb && !empty($_SERVER['DOCUMENT_ROOT'])) {
            return $this->bajoDocumentoRoot($dirReal, $_SERVER['DOCUMENT_ROOT']);
        }

        // 2) Sin DOCUMENT_ROOT (CLI / cron) se infiere por el patron de la
        //    app: BASE_URL es {host}/{nombre_proyecto}/public, asi que si el
        //    padre del proyecto contiene <nombre>/public, ese padre es el
        //    webroot. Es lo que pasa en XAMPP (padre = htdocs) y en hosting
        //    principal (padre = public_html).
        //
        //    OJO: en un subdominio cPanel el proyecto vive en
        //    /home/usuario/subdominio/ y el webroot es ese subdominio (o su
        //    public/), NO el home /home/usuario. Por eso solo se marca al
        //    padre como webroot si su nombre es tipico de un directorio que
        //    se sirve directamente por HTTP.
        $raiz  = $raiz ?? dirname(__DIR__, 2);
        $padre = dirname($raiz);

        $nombrePadre = strtolower(basename($padre));
        $nombresWebroot = ['htdocs', 'public_html', 'www', 'html', 'httpdocs'];

        $padreEsWebroot = in_array($nombrePadre, $nombresWebroot, true)
            && is_dir($padre . DIRECTORY_SEPARATOR . basename($raiz) . DIRECTORY_SEPARATOR . 'public');

        if (!$padreEsWebroot) {
            return false;
        }

        $raizWeb = realpath($padre);
        if ($raizWeb === false) {
            return false;
        }

        return str_starts_with($dirReal . DIRECTORY_SEPARATOR, $raizWeb . DIRECTORY_SEPARATOR);
    }

    /**
     * True si la ruta absoluta queda bajo el DOCUMENT_ROOT del servidor.
     * Es la comprobacion definitiva: no infiere nada, usa el webroot real.
     */
    private function bajoDocumentoRoot(string $dirAbsoluta, string $docRoot): bool
    {
        $docReal = realpath($docRoot);
        if ($docReal === false) {
            return false;
        }

        return str_starts_with(
            $dirAbsoluta . DIRECTORY_SEPARATOR,
            $docReal . DIRECTORY_SEPARATOR
        );
    }

    /**
     * Avisa por log si un BACKUP_PATH configurado quedo dentro del webroot.
     * No lo bloquea: quien lo configuro tiene la ultima palabra, pero que
     * quede registrado.
     */
    private function avisarSiEsWebroot(string $dir): void
    {
        if ($this->esWebrootProbable($dir)) {
            error_log(
                '[Backup] ATENCION: BACKUP_PATH apunta a una carpeta dentro del webroot '
                . "({$dir}). Los .sql quedarian accesibles por HTTP."
            );
        }
    }

    /**
     * Comprueba que el entorno permita respaldar. Devuelve la lista de
     * problemas (vacia = todo ok). El panel la muestra antes de dejar
     * generar un respaldo, para fallar con un mensaje accionable y no con
     * un "mysqldump: command not found" enterrado en un log.
     */
    public function comprobarEntorno(): array
    {
        $problemas = [];

        // 1) exec() habilitado: en hosting compartido suele venir deshabilitada.
        $deshabilitadas = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        if (!function_exists('exec') || in_array('exec', $deshabilitadas, true)) {
            $problemas[] = 'PHP tiene deshabilitada la funcion exec(), y sin ella no se puede invocar '
                         . 'a mysqldump. En hosting compartido hay que pedir que la habiliten, o '
                         . 'correr los respaldos desde la consola del servidor.';
        }

        // 2) mysqldump localizable.
        if ($this->mysqldumpConfigurado !== '' && !file_exists($this->mysqldumpConfigurado)) {
            $problemas[] = "MYSQLDUMP_PATH apunta a '{$this->mysqldumpConfigurado}' y ese archivo "
                         . 'no existe. Se ignora y se busca solo, pero conviene corregir la clave '
                         . 'en el .env (o borrarla) antes de que el cron falle.';
        }

        if (!file_exists($this->mysqldump) && !$this->estaEnPath($this->mysqldump)) {
            $problemas[] = "No se encontro mysqldump (se probo '{$this->mysqldump}' y el PATH). "
                         . 'Defini MYSQLDUMP_PATH en el .env con la ruta absoluta del binario.';
        }

        // 3) directorio de destino escribible.
        try {
            $destino = $this->destinoBase();
            $prueba  = $destino . DIRECTORY_SEPARATOR . '.escritura_' . getmypid();
            if (@file_put_contents($prueba, '1') === false) {
                $problemas[] = "El directorio de respaldos '{$destino}' no admite escritura. "
                             . 'Defini BACKUP_PATH en el .env apuntando a una carpeta con permisos, '
                             . 'y fuera del webroot.';
            } else {
                @unlink($prueba);
            }
        } catch (Throwable $e) {
            $problemas[] = $e->getMessage();
        }

        return $problemas;
    }

    /**
     * True si el binario esta en algun directorio del PATH.
     */
    private function estaEnPath(string $binario): bool
    {
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            if ($dir !== '' && file_exists($dir . DIRECTORY_SEPARATOR . $binario)) {
                return true;
            }
        }

        return false;
    }

    // =====================================================================
    // Bases a respaldar
    // =====================================================================

    /**
     * Bases que entran en un respaldo: la master + una por tenant activo.
     * Devuelve [nombre => ['tipo' => ..., 'descripcion' => ...]].
     */
    public function bases(): array
    {
        $pdo = $this->conexionMaster();

        $bases = [
            'app_master' => [
                'tipo'        => 'MASTER',
                'descripcion' => 'Empresas, usuarios y tareas',
            ],
        ];

        $sql = "SELECT t.id, t.nombre, t.dbname
                  FROM tenants t
                 WHERE t.activo = 1
              ORDER BY t.id";
        foreach ($pdo->query($sql) as $t) {
            // dbname con backticks/espacios romperia el dump: lo validamos.
            $dbname = trim($t['dbname']);
            if ($dbname === '' || !preg_match('/^[A-Za-z0-9_]+$/', $dbname)) {
                error_log('[Backup] dbname invalido en tenants #{$t["id"]}: ' . $t['dbname']);
                continue;
            }
            $bases[$dbname] = [
                'tipo'        => 'TENANT',
                'descripcion' => "#{$t['id']} - {$t['nombre']}",
            ];
        }

        return $bases;
    }

    private function conexionMaster(): PDO
    {
        try {
            return new PDO(
                "mysql:host={$this->dbHost};dbname=app_master;charset=utf8mb4",
                $this->dbUser,
                $this->dbPass,
                [
                    PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE    => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES      => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException('No se pudo conectar a la BD master: ' . $e->getMessage(), 0, $e);
        }
    }

    // =====================================================================
    // Respaldo
    // =====================================================================

    /**
     * Ejecuta un respaldo.
     *
     * @param string $modo  'master' | 'tenants' | 'full'
     * @param int    $keep  Si es > 0, deja solo los N respaldos mas recientes
     *                      (rotacion). 0 = no podar nada.
     * @return array Resumen: dir, nombre, modo, bases, total, archivos[]
     */
    public function respaldar(string $modo = 'full', int $keep = 0): array
    {
        if (!in_array($modo, ['master', 'tenants', 'full'], true)) {
            throw new InvalidArgumentException('Modo de respaldo invalido: ' . $modo);
        }

        $problemas = $this->comprobarEntorno();
        if (!empty($problemas)) {
            throw new RuntimeException(implode(' ', $problemas));
        }

        $bases = $this->bases();
        if ($modo === 'master') {
            $bases = array_intersect_key($bases, array_flip(['app_master']));
        } elseif ($modo === 'tenants') {
            unset($bases['app_master']);
        }

        if (empty($bases)) {
            throw new RuntimeException('No hay bases para respaldar.');
        }

        $destino = $this->nombreUnico($this->destinoBase());
        if (!is_dir($destino) && !mkdir($destino, 0775, true)) {
            throw new RuntimeException("No se pudo crear el directorio {$destino}");
        }

        $archivos = [];
        $errores  = 0;

        foreach ($bases as $nombre => $meta) {
            $archivo = $destino . DIRECTORY_SEPARATOR . $nombre . '.sql';
            [$rc, $salida] = $this->volcar($nombre, $archivo);

            $bytes = file_exists($archivo) ? (int) filesize($archivo) : 0;
            $ok    = ($rc === 0 && $bytes > 0);

            $archivos[] = [
                'base'  => $nombre,
                'tipo'  => $meta['tipo'],
                'bytes' => $bytes,
                'ok'    => $ok,
                'error' => $ok
                    ? null
                    : ($salida !== '' ? $salida : "mysqldump devolvio el codigo {$rc}"),
            ];

            if (!$ok) {
                $errores++;
            }
        }

        // Si algo fallo, renombramos el directorio para no dejar un respaldo
        // incompleto con el nombre de uno bueno.
        if ($errores > 0) {
            @rename($destino, $destino . '_incompleto');
            throw new RuntimeException("El respaldo termino con {$errores} error(es).");
        }

        if ($keep > 0) {
            $this->podar($keep);
        }

        $total = 0;
        foreach ($archivos as $a) {
            $total += $a['bytes'];
        }

        return [
            'dir'      => $destino,
            'nombre'   => basename($destino),
            'modo'     => $modo,
            'bases'    => count($archivos),
            'total'    => $total,
            'archivos' => $archivos,
        ];
    }

    /**
     * Ejecuta mysqldump para una base.
     *
     * Usa --result-file y NUNCA redirect de shell: en PowerShell 5.1 el '>'
     * escribe UTF-16 y deja el dump ilegible (ver AGENTS.md).
     *
     * La password viaja por la variable de entorno MYSQL_PWD, no por -p en la
     * linea de comandos: en Windows queda visible en el Administrador de
     * tareas. (En Unix con -p tambien se ve en ps.)
     *
     * @return array [codigo de salida, salida de error]
     */
    private function volcar(string $dbname, string $archivo): array
    {
        $anterior = getenv('MYSQL_PWD');
        putenv('MYSQL_PWD=' . $this->dbPass);

        $cmd = sprintf(
            '"%s" -h %s -u %s --skip-lock-tables --default-character-set=utf8mb4 --result-file="%s" %s 2>&1',
            $this->mysqldump,
            escapeshellarg($this->dbHost),
            escapeshellarg($this->dbUser),
            $archivo,
            escapeshellarg($dbname)
        );

        $salida = [];
        $rc     = 0;
        exec($cmd, $salida, $rc);

        // Restauramos el entorno: otros dumps no deben heredarla por accidente.
        putenv($anterior === false ? 'MYSQL_PWD' : 'MYSQL_PWD=' . $anterior);

        return [$rc, implode(' | ', array_slice($salida, 0, 2))];
    }

    /**
     * Directorio de salida unico para un respaldo nuevo.
     *
     * El nombre lleva la fecha, pero dos respaldos en el mismo segundo
     * (un cron y un click en el panel, o dos clicks rapidos) terminarian
     * escribiendo en el MISMO directorio y mezclando las bases de uno y
     * otro. Por eso se agrega un sufijo incremental.
     */
    private function nombreUnico(string $base): string
    {
        $prefijo = 'erp_' . date('Ymd_His');
        $ruta    = $base . DIRECTORY_SEPARATOR . $prefijo;
        $n       = 2;

        while (is_dir($ruta)) {
            $ruta = $base . DIRECTORY_SEPARATOR . $prefijo . '_' . $n;
            $n++;
        }

        // Se normaliza para que el camino que se muestra (y el hint de
        // restauracion, que es para copiar y pegar) no quede con barras
        // mezcladas: en Windows DIRECTORY_SEPARATOR es contrabarra y el
        // destinoBase() devuelve '/'.
        return $this->normalizarRuta($ruta);
    }

    /**
     * Deja solo los $max respaldos mas recientes. Borra los demas.
     * @return int cantidad de respaldos eliminados
     */
    public function podar(int $max): int
    {
        if ($max < 1) {
            return 0;
        }

        $respaldos = $this->listarRespaldos();
        if (count($respaldos) <= $max) {
            return 0;
        }

        $borrados = 0;
        // listarRespaldos devuelve los mas recientes primero.
        foreach (array_slice($respaldos, $max) as $r) {
            if ($this->borrarRespaldo($r['nombre'])) {
                $borrados++;
            }
        }

        return $borrados;
    }

    // =====================================================================
    // Listado y borrado
    // =====================================================================

    /**
     * Respaldos existentes, del mas reciente al mas viejo.
     * @return array [['nombre','fecha','archivos','bytes','ruta'], ...]
     */
    public function listarRespaldos(): array
    {
        $base  = $this->destinoBase();
        $lista = [];

        foreach (glob($base . DIRECTORY_SEPARATOR . 'erp_*', GLOB_ONLYDIR) ?: [] as $dir) {
            $nombre = basename($dir);

            // Los *_incompleto (respaldos que fallaron a mitad) no cuentan
            // como respaldo: solo sirven para diagnosticar.
            if (str_ends_with($nombre, '_incompleto')) {
                continue;
            }

            $sqls   = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
            $bytes  = 0;
            $ultimo = 0;
            $nombres = [];
            foreach ($sqls as $f) {
                $bytes   += (int) filesize($f);
                $ultimo  = max($ultimo, (int) filemtime($f));
                $nombres[] = basename($f);
            }
            sort($nombres);

            $lista[] = [
                'nombre'   => $nombre,
                'fecha'    => $ultimo ?: (int) filemtime($dir),
                'archivos' => count($sqls),
                'sqls'     => $nombres,
                'bytes'    => $bytes,
                'ruta'     => $dir,
            ];
        }

        // El nombre lleva la fecha al final (erp_YYYYMMDD_HHMM): ordena bien
        // lexicograficamente, pero preferimos el orden por fecha de archivo.
        usort($lista, fn($a, $b) => $b['fecha'] <=> $a['fecha']);

        return $lista;
    }

    /**
     * Borra un respaldo. El nombre viene de la URL, asi que lo validamos
     * contra el formato que nosotros generamos.
     */
    public function borrarRespaldo(string $nombre): bool
    {
        $dir = $this->rutaRespaldo($nombre);
        if ($dir === null) {
            return false;
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            @unlink($f);
        }

        return @rmdir($dir);
    }

    /**
     * Ruta absoluta de un respaldo existente, o null si el nombre no es
     * seguro o el respaldo no existe.
     */
    public function rutaRespaldo(string $nombre): ?string
    {
        // erp_YYYYMMDD_HHMM, con sufijo opcional _N si hubo colision de fecha.
        if (!preg_match('/^erp_\d{8}_\d{6}(_\d+)?$/', $nombre)) {
            return null;
        }

        $ruta = $this->destinoBase() . DIRECTORY_SEPARATOR . $nombre;

        // realpath resuelve ../ y confirma que quedo dentro del directorio base.
        $real      = realpath($ruta);
        $realBase  = realpath($this->destinoBase());
        if ($real === false || $realBase === false || !str_starts_with($real, $realBase)) {
            return null;
        }

        return $real;
    }

    /**
     * Ruta absoluta de un .sql dentro de un respaldo, o null si no es seguro.
     * El nombre del archivo tambien viene de la URL.
     */
    public function rutaArchivo(string $nombreRespaldo, string $archivo): ?string
    {
        $dir = $this->rutaRespaldo($nombreRespaldo);
        if ($dir === null) {
            return null;
        }

        // Solo letras, numeros, guion bajo y punto: nada de / ni ../
        if (!preg_match('/^[A-Za-z0-9_][A-Za-z0-9_\-\.]*\.sql$/', $archivo)) {
            return null;
        }

        $ruta = $dir . DIRECTORY_SEPARATOR . $archivo;
        $real = realpath($ruta);

        if ($real === false || !is_file($real)) {
            return null;
        }

        // Confirmamos que el archivo quedo dentro del directorio del respaldo.
        if (!str_starts_with($real, $dir . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }
}
