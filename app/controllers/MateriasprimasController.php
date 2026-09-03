<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Materiaprima.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
class MateriasprimasController extends Controller
{
    private MateriaPrima $mp;

    public function __construct()
    {
        $this->mp = new MateriaPrima();
    }

    public function index()
    {
        $this->view('materias_primas/index', [
            'title'=>'Materias Primas',
            'items'=>$this->mp->all()
        ]);
    }

    public function create(): void // Crea una nueva materia prima
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if(!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = "Token CSRF inválido. Por favor, inténtalo de nuevo.";
                error_log('CSRF token validation failed for MateriasprimasController::create. Provided token: ' . $_POST['csrf_token'] . ' in ' . __FILE__ . ':' . __LINE__);
                header('Location: ' . BASE_URL . '/materiasprimas');
                exit;
            }
            error_log('Crear MP: '.print_r($_POST, true).'-'. __FILE__ . ':' . __LINE__);
            error_log('Archivo Adjunto: '.print_r($_FILES, true).'-'. __FILE__ . ':' . __LINE__);
            $imagen = null;
            if (!empty($_FILES['imagen_mp']['name'])) {
                $imagen = $this->uploadImagenMP();
                error_log('Imagen uploaded for new Materia Prima: ' . $imagen . ' in ' . __FILE__ . ':' . __LINE__);
            }

            // Si no se subio imagen, copiar la imagen por defecto al directorio del tenant
            if (!$imagen) {
                $imagen = $this->copiarImagenPorDefecto();
            }

            $barcode = $_POST['barcode'] ?? null;
            $tipo = $_POST['tipo'] ?? null;
            $this->mp->create($_POST, $imagen, $barcode, $tipo);
            header('Location: '.BASE_URL.'/materiasprimas');
            exit;
        }
        $categorias=$this->mp->categoriasMP();
        $umedida=$this->mp->umedidaMP();
         $this->view('materias_primas/form', [
            'title'=>'Nueva Materia Prima',
            'categorias'=>$categorias,
            'umedida'=>$umedida
        ]);
    }

    public function import(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Intente nuevamente.';
                header('Location: ' . BASE_URL . '/materiasprimas/import');
                exit;
            }

            // Step 2: Confirm import from session data
            if (isset($_POST['confirmar']) && isset($_SESSION['import_preview_mp'])) {
                $items = $_SESSION['import_preview_mp'];
                unset($_SESSION['import_preview_mp']);

                $valid = array_filter($items, fn($p) => empty($p['_error']));
                if (empty($valid)) {
                    $_SESSION['error'] = 'No hay materias primas válidas para importar.';
                    header('Location: ' . BASE_URL . '/materiasprimas/import');
                    exit;
                }

                $result = $this->mp->createBulk(array_values($valid));
                $msg = "Se importaron {$result['imported']} materias primas.";
                if (!empty($result['errors'])) {
                    $msg .= " " . count($result['errors']) . " fallaron.";
                }
                $_SESSION['success'] = $msg;
                header('Location: ' . BASE_URL . '/materiasprimas');
                exit;
            }

            // Step 1: Parse uploaded file
            if (empty($_FILES['archivo']['name'])) {
                $_SESSION['error'] = 'Debe seleccionar un archivo .xlsx';
                header('Location: ' . BASE_URL . '/materiasprimas/import');
                exit;
            }

            $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'xlsx') {
                $_SESSION['error'] = 'Solo se permiten archivos .xlsx';
                header('Location: ' . BASE_URL . '/materiasprimas/import');
                exit;
            }

            if ($_FILES['archivo']['size'] > 10 * 1024 * 1024) {
                $_SESSION['error'] = 'El archivo no puede superar 10MB.';
                header('Location: ' . BASE_URL . '/materiasprimas/import');
                exit;
            }

            try {
                $spreadsheet = IOFactory::load($_FILES['archivo']['tmp_name']);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();

                if (count($rows) < 2) {
                    $_SESSION['error'] = 'El archivo no contiene datos.';
                    header('Location: ' . BASE_URL . '/materiasprimas/import');
                    exit;
                }

                $header = array_map(fn($h) => mb_strtolower(trim($h)), $rows[0]);
                $map = [];
                $expected = ['nombre', 'sku', 'unidad_medida', 'categoria', 'barcode'];
                foreach ($expected as $col) {
                    $idx = array_search($col, $header);
                    if ($idx !== false) $map[$col] = $idx;
                }

                if (!isset($map['nombre']) || !isset($map['sku'])) {
                    $_SESSION['error'] = 'El archivo debe tener las columnas: nombre, sku';
                    header('Location: ' . BASE_URL . '/materiasprimas/import');
                    exit;
                }

                $umedidaList = $this->mp->umedidaMP();
                $umedidaMap = [];
                foreach ($umedidaList as $um) {
                    $umedidaMap[strtolower($um['nombre'])] = $um['id_medida'];
                }

                $categoriasList = $this->mp->categoriasMP();
                $categoriasMap = [];
                foreach ($categoriasList as $cat) {
                    $categoriasMap[strtolower($cat['categoria_nombre'])] = $cat['id_categoria'];
                }

                $seenSkus = [];
                $items = [];

                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    $r = $i + 1;
                    $nombre = trim($row[$map['nombre']] ?? '');
                    $sku = trim($row[$map['sku']] ?? '');
                    $umedidaText = trim($row[$map['unidad_medida']] ?? '');
                    $categoriaText = trim($row[$map['categoria']] ?? '');
                    $barcode = trim($row[$map['barcode']] ?? '');

                    $item = [
                        '_row' => $r,
                        'nombre' => $nombre,
                        'sku' => $sku,
                        'unidad_medida_text' => $umedidaText,
                        'categoria_text' => $categoriaText,
                        'barcode' => $barcode,
                        '_error' => '',
                        '_error_type' => '',
                    ];

                    if ($nombre === '' || $sku === '') {
                        $item['_error'] = 'Faltan campos obligatorios (nombre, sku)';
                        $item['_error_type'] = 'validation';
                        $items[] = $item;
                        continue;
                    }

                    if (isset($seenSkus[$sku])) {
                        $item['_error'] = "SKU duplicado en el archivo (fila {$seenSkus[$sku]})";
                        $item['_error_type'] = 'duplicate_file';
                        $items[] = $item;
                        continue;
                    }
                    $seenSkus[$sku] = $r;

                    $umId = 1;
                    if ($umedidaText !== '') {
                        $umId = $umedidaMap[strtolower($umedidaText)] ?? 1;
                    }
                    $item['id_unidadmedida'] = $umId;

                    $catId = null;
                    if ($categoriaText !== '') {
                        $catId = $categoriasMap[strtolower($categoriaText)] ?? null;
                    }
                    $item['categoria'] = $catId;

                    $item['barcode_tipo'] = 'INTERNO';
                    $items[] = $item;
                }

                $allSkus = array_column(array_filter($items, fn($p) => empty($p['_error'])), 'sku');
                $existingSkus = $this->mp->findSkus($allSkus);

                foreach ($items as &$p) {
                    if (empty($p['_error']) && in_array($p['sku'], $existingSkus)) {
                        $p['_error'] = 'SKU ya existe en base de datos';
                        $p['_error_type'] = 'duplicate_db';
                    }
                }
                unset($p);

                $_SESSION['import_preview_mp'] = $items;

                $this->view('materias_primas/import', [
                    'title' => 'Importar Materias Primas',
                    'preview' => $items,
                    'csrf' => Csrf::generate(),
                ]);

            } catch (\Exception $e) {
                error_log('Error parseando Excel MP: ' . $e->getMessage());
                $_SESSION['error'] = 'Error al leer el archivo: ' . $e->getMessage();
                header('Location: ' . BASE_URL . '/materiasprimas/import');
                exit;
            }
            return;
        }

        // GET: show upload form
        $this->view('materias_primas/import', [
            'title' => 'Importar Materias Primas',
            'preview' => null,
            'csrf' => Csrf::generate(),
        ]);
    }

    public function search() // Busca materias primas por nombre para autocompletar
    {
        $q = trim($_GET['q'] ?? '');

        if (strlen($q) < 2) {
            echo json_encode([]);
            return;
        }

        $model = new MateriaPrima();
        echo json_encode($model->search($q));
    }

    public function update($id) // Actualiza datas de una materia prima
    {
        validarId($id, BASE_URL . '/materiasprimas');
        $item = $this->mp->find($id);
        $categorias=$this->mp->categoriasMP();
        $umedida=$this->mp->umedidaMP();
        if (!$item) {
            $_SESSION['error'] = "Materia Prima no encontrada.";
            header('Location: '.BASE_URL.'/materiasprimas');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if(!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = "Token CSRF inválido. Por favor, inténtalo de nuevo.";
                error_log('CSRF token validation failed for MateriasprimasController::update. Provided token: ' . $_POST['csrf_token'] . ' in ' . __FILE__ . ':' . __LINE__);
                header('Location: ' . BASE_URL . '/materiasprimas');
                exit;
            }

            // Manejar subida de imagen
            $imagen = null;
            if (!empty($_FILES['imagen_mp']['name'])) {
                $imagen = $this->uploadImagenMP();
            }

            $this->mp->update($id, $_POST, $imagen);
            header('Location: '.BASE_URL.'/materiasprimas');
            exit;
        }

        $this->view('materias_primas/edit', [
            'title'=>'Editar Materia Prima',
            'item'=>$item,
            'categorias'=>$categorias,
            'umedida'=>$umedida
        ]);
    }
    private function uploadImagenMP(): ?string
    {
        if (empty($_FILES['imagen_mp']['name'])) {
            return null;
        }
        
        // Validar extensión
        $ext = strtolower(pathinfo($_FILES['imagen_mp']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (!in_array($ext, $permitidas)) {
            $_SESSION['error'] = 'Tipo de archivo no permitido. Solo imágenes.';
            error_log('Tipo de archivo no permitido: ' . $ext.' - '.__FILE__.':'.__LINE__);
            return null;
        }
        
        // Validar tamaño (ej: 5MB máximo)
        if ($_FILES['imagen_mp']['size'] > 5 * 1024 * 1024) {
            $_SESSION['error'] = 'El archivo es demasiado grande. Máximo 5MB.';
            error_log('Archivo demasiado grande: ' . $_FILES['imagen_mp']['size'].' bytes - '.__FILE__.':'.__LINE__);
            return null;
        }
        
        // Validar MIME real (usando finfo)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['imagen_mp']['tmp_name']);
        $mimesPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
        
        if (!in_array($mime, $mimesPermitidos)) {
            $_SESSION['error'] = 'El archivo no es una imagen válida.';
            error_log('Archivo con MIME no permitido: ' . $mime.' - '.__FILE__.':'.__LINE__);
            return null;
        }
        
        // Guardar en directorio del tenant
        $uploadDir = empresaUploadPath('materiasprimas');
        $name = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $path = $uploadDir . '/' . $name;
        
        if (move_uploaded_file($_FILES['imagen_mp']['tmp_name'], $path)) {
            return $name;
        }
        $_SESSION['error'] = 'Error al subir la imagen.';
        error_log('Error moving uploaded file: ' . $_FILES['imagen_mp']['error']);
        return null;
    }

    /**
     * Copia la imagen por defecto al directorio del tenant
     */
    private function copiarImagenPorDefecto(): ?string
    {
        $origen = BASE_PATH . '/storage/imgpordefecto.jpg';
        if (!file_exists($origen)) {
            error_log('Imagen por defecto no encontrada: ' . $origen);
            return null;
        }

        $uploadDir = empresaUploadPath('materiasprimas');
        $destino = $uploadDir . '/sin-imagen.jpg';

        // Si ya existe la copia, no volver a copiar
        if (file_exists($destino)) {
            return 'sin-imagen.jpg';
        }

        if (copy($origen, $destino)) {
            return 'sin-imagen.jpg';
        }

        error_log('Error al copiar imagen por defecto a: ' . $destino);
        return null;
    }
    public function stockdata($id){
        validarId($id, BASE_URL . '/materiasprimas');
         if (!$this->mp->find($id)) {
            $_SESSION['error'] = "Materia Prima no encontrada.";
            error_log('Materia Prima no encontrada en MateriasprimasController::stockdata con ID: ' . $id . ' in ' . __FILE__ . ':' . __LINE__);
            header('Location: '.BASE_URL.'/materiasprimas');
            exit;
        }
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            if(!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = "Token CSRF inválido. Por favor, inténtalo de nuevo.";
                error_log('CSRF token validation failed for MateriasprimasController::stockdata POST. Provided token: ' . $_POST['csrf_token'] . ' in ' . __FILE__ . ':' . __LINE__);
                header('Location: ' . BASE_URL . '/materiasprimas');
                exit;
            }
            if( //validaciones para prhibir valor de stock incorrectos
                $_POST['stock_minimo'] < 0 || $_POST['stock_critico'] < 0 || $_POST['stock_maximo'] < 0 ||
                ($_POST['stock_minimo'] > $_POST['stock_maximo']) ||
                ($_POST['stock_critico'] > $_POST['stock_maximo']) ||
                ($_POST['stock_critico'] > $_POST['stock_minimo'])){
                $_SESSION['error'] = 'Los valores de stock no pueden ser negativos. Tampoco el stock mínimo puede ser mayor que el máximo, ni el stock crítico puede ser mayor que el máximo o menor que el mínimo.';
                header('Location: ' . BASE_URL . '/materiasprimas/stockdata/' . $id);
                exit;

            }
            $this->mp->paramStocks($id, $_POST);
            exit;
            $cantidad = floatval($_POST['cantidad']);
            $this->mp->updateStock($id, $cantidad);
            header('Location: ' . BASE_URL . '/materiasprimas');
            exit;
        }
        $mp=$this->mp->find($id);
        $stockmovements = $this->mp->stockByProductoId_movstock($id);
        if (!$mp) {
            $_SESSION['error'] = "Materia Prima no encontrada.";
            header('Location: '.BASE_URL.'/materiasprimas');
            exit;
        }
        $this->view('materias_primas/stockdata', [
            'title'=>'Actualizar Stock - '.$mp['nombre'],
            'item'=>$mp,
            'stockmovements'=>$stockmovements,
            'csrf' => Csrf::generate()
        ]);
    }

    public function updatebarcode($id){ //muestra los codigos y los actualizo
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            $_SESSION['error'] = "ID inválido.";
            header('Location: ' . BASE_URL . '/materiasprimas');
            exit;
        }
        $mp = $this->mp->find($id);
        if (!$mp) {
            $_SESSION['error'] = "Materia Prima no encontrada.";
            header('Location: '.BASE_URL.'/materiasprimas');
            exit;
        }
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            if(!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = "Token CSRF inválido. Por favor, inténtalo de nuevo.";
                error_log('CSRF token validation failed for MateriasprimasController::updatebarcode POST. Provided token: ' . $_POST['csrf_token'] . ' in ' . __FILE__ . ':' . __LINE__);
                header('Location: ' . BASE_URL . '/materiasprimas/updatebarcode/' . $id);
                exit;
            }
            $ids = $_POST['ids'] ?? [];
            $codigos = $_POST['codigos'] ?? [];
            $tipos = $_POST['tipos'] ?? [];
            foreach ($ids as $index => $id_codigo) {
                if(isset($codigos[$index]) && isset($tipos[$index])) {
                    $this->mp->updateBarcodes($id_codigo, $codigos[$index], $tipos[$index]);
                }
            }
            header('Location: ' . BASE_URL . '/materiasprimas/updatebarcode/' . $id);
            exit;
        }
        $barcodes = $this->mp->getBarcodesByMPId($id);
        $this->view('materias_primas/formeditbarcode', [
            'title' => 'Editar Códigos de Barra - ' . $mp['nombre'],
            'materia' => $mp,
            'barcodes' => $barcodes
        ]);
    }

    public function newbarcode($id){ //dentro de la vista update tengo el boton para cargar uno nuevo
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            $_SESSION['error'] = "ID inválido.";
            header('Location: ' . BASE_URL . '/materiasprimas');
            exit;
        }
        $mp = $this->mp->find($id);
        if (!$mp) {
            $_SESSION['error'] = "Materia Prima no encontrada.";
            header('Location: '.BASE_URL.'/materiasprimas');
            exit;
        }
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            if(!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = "Token CSRF inválido. Por favor, inténtalo de nuevo.";
                error_log('CSRF token validation failed for MateriasprimasController::newbarcode POST. Provided token: ' . $_POST['csrf_token'] . ' in ' . __FILE__ . ':' . __LINE__);
                header('Location: ' . BASE_URL . '/materiasprimas');
                exit;
            }
            $codigo = $_POST['codigo'] ?? null;
            $tipo = $_POST['tipo'] ?? null;
            if(empty($codigo) || empty($tipo)) {
                $_SESSION['error'] = "Código y tipo son campos obligatorios.";
                header('Location: ' . BASE_URL . '/materiasprimas/newbarcode/' . $id);
                exit;
            }
            $this->mp->addBarcode($id, $codigo, $tipo);
            header('Location: ' . BASE_URL . '/materiasprimas/updatebarcode/' . $id);
            exit;
        }
        $this->view('materias_primas/formnewbarcode', [
            'title' => 'Agregar Código de Barra - ' . $mp['nombre'],
            'materia' => $mp
        ]);
    }
}