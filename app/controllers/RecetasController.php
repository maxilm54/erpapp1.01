<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Receta.php';
require_once BASE_PATH.'/app/models/Producto.php';
require_once BASE_PATH.'/app/models/Materiaprima.php';

class RecetasController extends Controller
{
    private Receta $model;
    private Producto $producto;
    private MateriaPrima $materia;

    public function __construct()
    {
        $this->model = new Receta();
        $this->producto = new Producto();
        $this->materia  = new MateriaPrima();
    }

    public function index()
    {
        $this->view('recetas/index', [
            'recetas' => $this->model->all()
        ]);
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Token CSRF inválido';
                header('Location: '.BASE_URL.'/recetas/create');
                exit;
            }
            try {
                $items = [];
                foreach ($_POST['items'] as $i) {
                    $items[] = [
                        'materia_prima_id' => $i['materia_prima_id'],
                        'cantidad' => $i['cantidad']
                    ];
                }

                $id = $this->model->crear(
                    (int)$_POST['producto_id'],
                    $_POST['nombre'],
                    $items,
                    $_POST['procedimiento'] ?? null,
                    (int)$_SESSION['user_id']
                );

                $_SESSION['success'] = 'Receta creada correctamente';
                header('Location: '.BASE_URL.'/recetas/show/'.$id);
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }
        }

        // 🔑 ACÁ se generan los arrays y se envian a la vista
        $this->view('recetas/create', [
            'productos' => $this->producto->all(),
            'materias'  => $this->materia->all()
        ]);
    }

    public function show($id)
    {
        validarId($id, BASE_URL . '/recetas');
        $receta = $this->model->find((int)$id);
        if (!$receta) {
            $_SESSION['error'] = 'Receta no encontrada';
            header('Location: '.BASE_URL.'/recetas');
            exit;
        }

        // Obtener ultimos precios de compra de las materias primas
        $mpIds = array_column($receta['detalle'], 'materia_prima_id');
        $preciosCompra = $this->materia->getUltimosPreciosCompra($mpIds);

        // Calcular costo total de la receta
        $costoTotalReceta = 0;
        $detalleConCostos = [];
        foreach ($receta['detalle'] as $det) {
            $precioUnit = $preciosCompra[(int)$det['materia_prima_id']] ?? null;
            $subtotal = ($precioUnit !== null) ? $precioUnit * (float)$det['cantidad'] : null;
            if ($subtotal !== null) {
                $costoTotalReceta += $subtotal;
            }
            $detalleConCostos[] = array_merge($det, [
                'precio_compra' => $precioUnit,
                'subtotal'      => $subtotal,
            ]);
        }

        // Obtener costos del producto generado (si existen)
        require_once BASE_PATH . '/app/models/Productocostos.php';
        $costosModel = new Productocostos();
        $costosProducto = $costosModel->getByProducto($receta['producto_id']);
        $calculoProducto = null;
        if ($costosProducto) {
            $calculoProducto = Productocostos::calcularPrecioVenta($costosProducto, (float)($receta['precio_venta'] ?? 0));
        }

        $this->view('recetas/show', [
            'receta'            => $receta,
            'rec_det'           => $detalleConCostos,
            'costo_total_receta'=> $costoTotalReceta,
            'costos_producto'   => $costosProducto,
            'calculo_producto'  => $calculoProducto,
        ]);
    }

    public function edit(int $id_receta)
    {
        validarId($id_receta, BASE_URL . '/recetas');
        $receta = $this->model->find((int)$id_receta);
        if (!$receta) {
            $_SESSION['error'] = 'Receta no encontrada';
            header('Location: '.BASE_URL.'/recetas');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Token CSRF inválido';
                header('Location: '.BASE_URL.'/recetas/edit/'.$id_receta);
                exit;
            }
            $editar= $this->model->edit_receta();
            if($editar){
                $_SESSION['success'] = 'Receta editada correctamente';
                header('Location: '.BASE_URL.'/recetas/show/'.$id_receta);
                exit;
            }
        }
       $this->view('recetas/edit', ['receta'=>$receta,'rec_det'=>$receta['detalle']]);
    }
    public function delete(int $id_receta)
    {
        validarId($id_receta, BASE_URL . '/recetas');
        $this->model->delete((int)$id_receta);
        header('Location: '.BASE_URL.'/recetas');
        exit;
    }

    public function ajaxShow($id)
    {
        $receta = $this->model->find((int)$id);

        if (!$receta) {
            http_response_code(404);
            echo 'Receta no encontrada';
            return;
        }

        $this->modal('recetas/_modal', ['receta'=>$receta,'rec_det'=>$receta['detalle']], false);
    }

    public function inactivar(int $id_receta)
    {
        validarId($id_receta, BASE_URL . '/recetas');
        $this->model->inactivar((int)$id_receta);
        header('Location: '.BASE_URL.'/recetas');
        exit;
    }
}