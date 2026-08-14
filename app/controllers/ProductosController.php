<?php

require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Producto.php';
require_once BASE_PATH . '/app/models/Productocodigo.php';

class ProductosController extends Controller
{
    private Producto $producto;
    private ProductoCodigo $codigo;

    public function __construct()
    {
        $this->producto = new Producto();
        $this->codigo = new ProductoCodigo();
    }

    public function index(): void
    {
        $this->view('productos/index', [
            'title' => 'Productos',
            'productos' => $this->producto->all()
        ]);
    }

    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log('creacion de producto: ' . print_r($_FILES, true).' - ' . print_r($_POST, true).__FILE__.':'.__LINE__);
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Intente nuevamente.';
                error_log('Error en token controller create productos'.__FILE__.':'.__LINE__);
                header('Location: ' . BASE_URL . '/productos/create');
                exit;
            }
            $imagen = null;
            if (!empty($_FILES['imagen']['name'])) {
                $imagen = $this->uploadImagen();
            }

            // Si no se subio imagen, copiar la imagen por defecto al directorio del tenant
            if (!$imagen) {
                $imagen = $this->copiarImagenPorDefecto();
            }

            $productoId = $this->producto->create([
                'nombre' => htmlspecialchars(trim($_POST['nombre'])),
                'sku' => htmlspecialchars(trim($_POST['sku'])),
                'descripcion' => htmlspecialchars(trim($_POST['descripcion'])),
                'precio_venta' => (float)$_POST['precio_venta'],
                'imagen' => $imagen,
                'user_create' => $_SESSION['user_id'],
                'unidad_medida' => (int)$_POST['unidad_medida']
            ], $imagen);

            foreach ($_POST['codigos'] as $i => $codigo) {
                if ($codigo !== '') {
                    $this->codigo->add(
                        $productoId,
                        $codigo,
                        $_POST['tipos'][$i]
                    );
                }
            }
            
            header('Location: ' . BASE_URL . '/productos');
            exit;
        }
        $this->view('productos/form', [
            'title' => 'Nuevo Producto',
            'umedida' => $this->producto->unidadProd()
        ]);
    }

    public function update($id_prod): void
    {
        validarId($id_prod, BASE_URL . '/productos');
        if(!filter_var($id_prod, FILTER_VALIDATE_INT)) {
            $_SESSION['error'] = 'ID de producto inválido.';
            header('Location: ' . BASE_URL . '/productos');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Por favor, inténtalo de nuevo.';
                header('Location: ' . BASE_URL . '/productos/update/' . $id_prod);
                exit;
            }

            // Manejar subida de imagen
            $imagen = null;
            if (!empty($_FILES['imagen']['name'])) {
                $imagen = $this->uploadImagen();
            }

            $this->producto->update($id_prod, $_POST, $imagen);
            header('Location: ' . BASE_URL . '/productos');
            exit;
        }
        $infoProducto = $this->producto->find($id_prod);
        $infoUmedida = $this->producto->unidadProd();
        $this->view('productos/formedit', [
            'title' => 'Editar Producto',
            'producto' => $infoProducto,
            'barcodes' => $this->producto->getBarcodeByProductoId($id_prod),
            'umedida' => $infoUmedida
        ]);
    }
    private function uploadImagen(): ?string
    {
        if (empty($_FILES['imagen']['name'])) {
        return null;
        }
        // Validar extensión
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $permitidas)) {
            $_SESSION['error'] = 'Tipo de archivo no permitido. Solo imágenes.';
            error_log('Tipo de archivo no permitido: ' . $ext.' - '.__FILE__.':'.__LINE__);
            return null;
        }
        // Validar tamaño (ej: 5MB máximo)
        if ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
            $_SESSION['error'] = 'El archivo es demasiado grande. Máximo 5MB.';
            error_log('Archivo demasiado grande: ' . $_FILES['imagen']['size'].' bytes - '.__FILE__.':'.__LINE__);
            return null;
        }
        // Validar MIME real (usando finfo)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['imagen']['tmp_name']);
        $mimesPermitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $mimesPermitidos)) {
            $_SESSION['error'] = 'El archivo no es una imagen válida.';
            error_log('Archivo con MIME no permitido: ' . $mime.' - '.__FILE__.':'.__LINE__);
            return null;
        }

        // Guardar en directorio del tenant
        $uploadDir = empresaUploadPath('productos');
        $name = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $path = $uploadDir . '/' . $name;
        
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $path)) {
            error_log('Imagen subida correctamente: ' . $path);
            return $name;
        }
        $_SESSION['error'] = 'Error al subir la imagen.';
        error_log('Error moving uploaded file: ' . $_FILES['imagen']['error']);
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

        $uploadDir = empresaUploadPath('productos');
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

    // 🔍 BUSCADOR AJAX
    public function search()
    {
        // Seguridad mínima
        header('Content-Type: application/json');

        $q = trim($_GET['q'] ?? '');

        if (strlen($q) < 2) {
            echo json_encode([]);
            return;
        }

        $productos = $this->producto->search($q);

        echo json_encode($productos);
    }

    public function updatebarcode($idprod){
        validarId($idprod, BASE_URL . '/productos');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Por favor, inténtalo de nuevo.';
                header('Location: ' . BASE_URL . '/productos/updatebarcode/' . $idprod);
                exit;
            }
            if(!filter_var($idprod, FILTER_VALIDATE_INT)) {
                $_SESSION['error'] = 'ID de producto inválido.';
                header('Location: ' . BASE_URL . '/productos');
                exit;
            }
            // $this->codigo->deleteByProductoId($idprod);

            foreach ($_POST['codigos'] as $i => $codigo) {
                error_log($_POST['codigos'][$i]);
                if ($codigo !== '') {
                    $this->codigo->update(
                        $_POST['ids'][$i] ,
                        $codigo,
                        $_POST['tipos'][$i]
                    );
                }
            }
            
            header('Location: ' . BASE_URL . '/productos');
            exit;
        }
        $infoProducto = $this->producto->find($idprod);
        $this->view('productos/formeditbarcode', [
            'title' => 'Actualizar Códigos de Barra',
            'producto' => $infoProducto,
            'barcodes' => $this->producto->getBarcodeByProductoId($idprod)
        ]);
    }

    public function newbarcode($idprod){
        validarId($idprod, BASE_URL . '/productos');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!empty($_POST['codigo'])) {
                $this->codigo->add(
                    htmlspecialchars($_POST['id_prod']),
                    htmlspecialchars($_POST['codigo']),
                    htmlspecialchars($_POST['tipo'])
                );
            }
            
            header('Location: ' . BASE_URL . '/productos');
            exit;
        }
        $infoProducto = $this->producto->find($idprod);
        $this->view('productos/formnewbarcode', [
            'title' => 'Nuevo Código de Barra',
            'producto' => $infoProducto
        ]);
    }

    public function stockdata($idprod){
        validarId($idprod, BASE_URL . '/productos');
        $infoProducto = $this->producto->find($idprod);
        $inforStockmov = $this->producto->stockByProductoId_movstock($idprod);
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            //error_log(print_r($_POST, true));
            if(!Csrf::validate($_POST['csrf_token'])){
                $_SESSION['error'] = 'Token CSRF inválido. Por favor, inténtalo de nuevo.';
                header('Location: ' . BASE_URL . '/productos/stockdata/' . $idprod);
                exit;
            }
            if(
                $_POST['stock_minimo'] < 0 || $_POST['stock_critico'] < 0 || $_POST['stock_maximo'] < 0 ||
                ($_POST['stock_minimo'] > $_POST['stock_maximo']) ||
                ($_POST['stock_critico'] > $_POST['stock_maximo']) ||
                ($_POST['stock_critico'] > $_POST['stock_minimo'])){
                $_SESSION['error'] = 'Los valores de stock no pueden ser negativos. Tampoco el stock mínimo puede ser mayor que el máximo, ni el stock crítico puede ser mayor que el máximo o menor que el mínimo.';
                header('Location: ' . BASE_URL . '/productos/stockdata/' . $idprod);
                exit;

            }
            $this->producto->paramStocks($idprod, $_POST);
            exit;
        }
        $this->view('productos/stockdata', [
            'title' => 'Datos de Stock',
            'producto' => $infoProducto,
            'stockmovements' => $inforStockmov,
            'csrf' => Csrf::generate()
        ]);
    }

    public function preciocompra($idprod){
        validarId($idprod, BASE_URL . '/productos');
        require_once BASE_PATH . '/app/models/Productocostos.php';

        $producto = $this->producto->find($idprod);
        if (!$producto) {
            $_SESSION['error'] = 'Producto no encontrado.';
            header('Location: ' . BASE_URL . '/productos');
            exit;
        }

        $costosModel = new Productocostos();
        $costos = $costosModel->getByProducto($idprod);
        $calculo = null;

        if ($costos) {
            $calculo = Productocostos::calcularPrecioVenta($costos, (float)$producto['precio_venta']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . '/productos/preciocompra/' . $idprod);
                exit;
            }

            $data = [
                'precio_compra'       => (float)($_POST['precio_compra'] ?? 0),
                'costo_fijo'          => (float)($_POST['costo_fijo'] ?? 0),
                'costo_variable_pct'  => (float)($_POST['costo_variable_pct'] ?? 0),
                'margen_ganancia_pct' => (float)($_POST['margen_ganancia_pct'] ?? 0),
            ];

            $costosModel->createOrUpdate((int)$idprod, $data);

            // Recalcular despues de guardar
            $costos = $costosModel->getByProducto($idprod);
            $calculo = Productocostos::calcularPrecioVenta($costos, (float)$producto['precio_venta']);

            $_SESSION['success'] = 'Costos del producto actualizados.';
            header('Location: ' . BASE_URL . '/productos/preciocompra/' . $idprod);
            exit;
        }

        $this->view('productos/preciocompra', [
            'title'   => 'Costos y Precios - ' . $producto['nombre'],
            'producto' => $producto,
            'costos'  => $costos,
            'calculo' => $calculo,
            'csrf'    => Csrf::generate()
        ]);
    }
}