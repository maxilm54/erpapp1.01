<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Ajustestock.php';
class AjustesstockController extends Controller
{
    private AjusteStock $model;

    public function __construct()
    {
        $this->model = new AjusteStock();
    }
    //llama al metod para mostrar todos ls movimientos de stocks, de MP y de Prd
    public function index()
    {
        $ajustes = $this->model->all();

        $this->view('ajustes_stock/index', [
            'ajustes' => $ajustes
        ]);
    }

    public function producto()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';
                if ($motivo === '') {
                    error_log('AjusteStockController Error: motivo de ajuste vacío.'.__FILE__.':'.__LINE__);
                    throw new Exception('El Ajuste no fue realizado. Debe indicar un motivo del ajuste');
                }
                $this->model->ajustarProducto(
                    (int)$_POST['producto_id'],
                    (float)$_POST['cantidad'],
                    htmlspecialchars($motivo),
                    (int)$_SESSION['user_id'],
                    htmlspecialchars($_POST['tipo']),
                    htmlspecialchars($_POST['observaciones'])

                );
                error_log('AjusteStockController: Producto ID ' . (int)$_POST['producto_id'] . ', Cantidad ' . (float)$_POST['cantidad'] . ', Tipo ' . htmlspecialchars($_POST['tipo']) . ', Usuario ID ' . (int)$_SESSION['user_id'].', Motivo ' . htmlspecialchars($motivo) . ', Observaciones ' . htmlspecialchars($_POST['observaciones']) . ' - Ajuste realizado exitosamente.'.__FILE__.':'.__LINE__);
                //$_SESSION['success'] = 'Ajuste de producto realizado exitosamente.';
                header('Location: ' . BASE_URL . '/ajustesstock');
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header('Location: ' . BASE_URL . '/ajustesstock');
                exit;
            }
        }

        $this->view('ajustes_stock/form_producto');
    }
    //llama a la vista materia prima ajuste y espera el post para ajustar salida o entrada
    public function materiaPrima()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';
                if ($motivo === '') {
                    error_log('AjusteStockController Error: motivo de ajuste vacío.'.__FILE__.':'.__LINE__);
                    throw new Exception('El Ajuste no fue realizado. Debe indicar un motivo del ajuste');
                }
                $this->model->ajustarMateriaPrima(
                    (int)$_POST['materia_prima_id'],
                    (float)$_POST['cantidad'],
                    htmlspecialchars($motivo),
                    (int)$_SESSION['user_id'],
                    htmlspecialchars($_POST['tipo']),
                    htmlspecialchars($_POST['observaciones'])
                );
                error_log('AjusteStockController: Materia Prima ID ' . (int)$_POST['materia_prima_id'] . ', Cantidad ' . (float)$_POST['cantidad'] . ', Tipo ' . htmlspecialchars($_POST['tipo']) . ', Usuario ID ' . (int)$_SESSION['user_id'].', Motivo ' . htmlspecialchars($motivo) . ', Observaciones ' . htmlspecialchars($_POST['observaciones']) . ' - Ajuste realizado exitosamente.'.__FILE__.':'.__LINE__);
                //$_SESSION['success'] = 'Ajuste de materia prima realizado exitosamente.';
                header('Location: ' . BASE_URL . '/ajustesstock');
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header('Location: ' . BASE_URL . '/ajustesstock/materiaprima');
                exit;
            }
        }

        $this->view('ajustes_stock/form_materia_prima');
    }
}