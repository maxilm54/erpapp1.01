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

            $imagen = null;
            if (!empty($_FILES['imagen']['name'])) {
                $imagen = $this->uploadImagen();
            }

            $productoId = $this->producto->create($_POST, $imagen);

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
            'title' => 'Nuevo Producto'
        ]);
    }

    public function update($id_prod): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            /*$imagen = null;
            if (!empty($_FILES['imagen']['name'])) {
                $imagen = $this->uploadImagen();
            }*/

            $productoId = $this->producto->update($id_prod, $_POST/*, $imagen*/);

           /* foreach ($_POST['codigos'] as $i => $codigo) {
                if ($codigo !== '') {
                    $this->codigo->add(
                        $productoId,
                        $codigo,
                        $_POST['tipos'][$i]
                    );
                }
            }*/
            
            header('Location: ' . BASE_URL . '/productos');
            exit;
        }
        $infoProducto = $this->producto->find($id_prod);
        $this->view('productos/formedit', [
            'title' => 'Editar Producto',
            'producto' => $infoProducto,
            'barcodes' => $this->producto->getBarcodeByProductoId($id_prod)
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            //$this->codigo->deleteByProductoId($idprod);

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
}