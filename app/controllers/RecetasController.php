<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Receta.php';
require_once BASE_PATH.'/app/models/Producto.php';
require_once BASE_PATH.'/app/models/Materiaprima.php';
require_once BASE_PATH.'/app/models/Configuracion.php';

class RecetasController extends Controller
{
    private Receta $model;
    private Producto $producto;
    private MateriaPrima $materia;
    private Configuracion $config;

    public function __construct()
    {
        $this->model = new Receta();
        $this->producto = new Producto();
        $this->materia  = new MateriaPrima();
        $this->config   = new Configuracion();
    }

    /**
     * La empresa decide desde Configuraciones si las recetas pueden incluir
     * productos ademas de materias primas.
     */
    private function permiteProductos(): bool
    {
        return $this->config->activa('usar_productos_recetas', false);
    }

    /**
     * Convierte las filas del formulario en items normalizados para el modelo.
     * Si la configuracion esta apagada, descarta las filas de tipo PRODUCTO.
     */
    private function itemsDesdePost(array $items, bool $permitirProductos): array
    {
        $resultado = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $cantidad = (float)str_replace(',', '.', (string)($item['cantidad'] ?? 0));
            if ($cantidad <= 0) {
                continue;
            }

            $tipo = strtoupper(trim((string)($item['tipo'] ?? 'MATERIA_PRIMA')));
            if ($tipo === 'PRODUCTO') {
                if (!$permitirProductos) {
                    continue;
                }
                $productoId = (int)($item['producto_id'] ?? $item['ref_id'] ?? 0);
                if ($productoId <= 0) {
                    continue;
                }
                $resultado['prod_'.$productoId] = [
                    'tipo'        => 'PRODUCTO',
                    'producto_id' => $productoId,
                    'cantidad'    => $cantidad,
                ];
            } else {
                $materiaId = (int)($item['materia_prima_id'] ?? $item['ref_id'] ?? 0);
                if ($materiaId <= 0) {
                    continue;
                }
                $resultado['mp_'.$materiaId] = [
                    'tipo'             => 'MATERIA_PRIMA',
                    'materia_prima_id' => $materiaId,
                    'cantidad'         => $cantidad,
                ];
            }
        }
        return array_values($resultado);
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
                $permitirProductos = $this->permiteProductos();
                $items = $this->itemsDesdePost($_POST['items'] ?? [], $permitirProductos);

                $id = $this->model->crear(
                    (int)$_POST['producto_id'],
                    $_POST['nombre'],
                    $items,
                    $_POST['procedimiento'] ?? null,
                    (int)$_SESSION['user_id'],
                    $_POST['procesos'] ?? []
                );

                $_SESSION['success'] = 'Receta creada correctamente';
                header('Location: '.BASE_URL.'/recetas/show/'.$id);
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }
        }

        $this->view('recetas/create', [
            'productos' => $this->producto->all(),
            'materias'  => $this->materia->all(),
            'usarProductosRecetas' => $this->permiteProductos(),
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
                'tipo'          => 'MATERIA_PRIMA',
                'precio_compra' => $precioUnit,
                'subtotal'      => $subtotal,
            ]);
        }

        // Productos componentes: se usa el precio de venta del ABM de producto
        // como costo del componente dentro de la receta.
        $detalleProductos = $this->model->detalleProductos((int)$id);
        foreach ($detalleProductos as $prod) {
            $precioUnit = (float)($prod['precio_venta'] ?? 0);
            $subtotal = $precioUnit * (float)$prod['cantidad'];
            $costoTotalReceta += $subtotal;
            $detalleConCostos[] = [
                'tipo'          => 'PRODUCTO',
                'nombre'        => $prod['nombre'],
                'cantidad'      => $prod['cantidad'],
                'unidad_medida' => $prod['unidad_medida'] ?? null,
                'precio_compra' => $precioUnit,
                'subtotal'      => $subtotal,
                'sku'           => $prod['sku'] ?? null,
            ];
        }

        // Costo fijo de los procesos: no se multiplica por cantidad.
        $costoProcesos = 0;
        foreach (($receta['procesos'] ?? []) as $proceso) {
            $costoProcesos += (float)($proceso['costo'] ?? 0);
        }
        $costoTotalFabricacion = $costoTotalReceta + $costoProcesos;

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
            'costo_procesos'    => $costoProcesos,
            'costo_total_fabricacion' => $costoTotalFabricacion,
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
            try {
                $permitirProductos = $this->permiteProductos();
                $items = $this->itemsDesdePost($_POST['items'] ?? [], $permitirProductos);

                // Si la opcion esta apagada no se pueden agregar productos, pero
                // los que ya tenia la receta se conservan al guardar.
                if (!$permitirProductos) {
                    foreach ($this->model->detalleProductos((int)$id_receta) as $prod) {
                        $items[] = [
                            'tipo'        => 'PRODUCTO',
                            'producto_id' => (int)$prod['producto_id'],
                            'cantidad'    => (float)$prod['cantidad'],
                        ];
                    }
                }

                $this->model->edit_receta(
                    (int)$id_receta,
                    (string)($_POST['nombre'] ?? ''),
                    $_POST['procedimiento'] ?? null,
                    $items,
                    array_key_exists('procesos', $_POST) ? ($_POST['procesos'] ?? []) : null
                );

                $_SESSION['success'] = 'Receta editada correctamente';
                header('Location: '.BASE_URL.'/recetas/show/'.$id_receta);
                exit;
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error al editar la receta: '.$e->getMessage();
                $receta = $this->model->find((int)$id_receta) ?? $receta;
            }
        }
        $this->view('recetas/edit', [
            'receta'        => $receta,
            'rec_det'       => $receta['detalle'],
            'rec_productos' => $this->model->detalleProductos((int)$id_receta),
            'materias'      => $this->materia->all(),
            'productos'     => $this->producto->all(),
            'usarProductosRecetas' => $this->permiteProductos(),
        ]);
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

        $this->modal('recetas/_modal', [
            'receta'        => $receta,
            'rec_det'       => $receta['detalle'],
            'rec_productos' => $this->model->detalleProductos((int)$id),
        ], false);
    }

    public function inactivar(int $id_receta)
    {
        validarId($id_receta, BASE_URL . '/recetas');
        $this->model->inactivar((int)$id_receta);
        header('Location: '.BASE_URL.'/recetas');
        exit;
    }
}