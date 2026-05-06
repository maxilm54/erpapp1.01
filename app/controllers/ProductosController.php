<?php

require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Producto.php';
require_once BASE_PATH . '/app/models/ProductoCodigo.php';

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

            $this->producto->update($id_prod, $_POST);
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
    private function uploadImagen(): string
    {
        $name = uniqid() . '_' . $_FILES['imagen']['name'];
        $path = BASE_PATH . '/public/uploads/productos/' . $name;
        move_uploaded_file($_FILES['imagen']['tmp_name'], $path);
        return 'uploads/productos/' . $name;
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

    public function newBarcode($idprod){
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
                ($_POST['stock_critico'] < $_POST['stock_minimo'])){
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
}