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

    private function uploadImagen(): string
    {
        $name = uniqid() . '_' . $_FILES['imagen']['name'];
        $path = BASE_PATH . '/public/uploads/productos/' . $name;
        move_uploaded_file($_FILES['imagen']['tmp_name'], $path);
        return 'uploads/productos/' . $name;
    }
}