<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Materiaprima.php';
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