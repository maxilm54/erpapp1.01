<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/AjusteStock.php';
class AjustesstockController extends Controller
{
    private AjusteStock $model;

    public function __construct()
    {
        $this->model = new AjusteStock();
    }

    public function index()
    {
        $ajustes = $this->model->all();

        $this->view('ajustes_stock/index', [
            'ajustes' => $ajustes
        ]);
    }

    public function producto()
    {
        if ($_POST) {
            try {
                $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';
                if ($motivo === '') {
                    throw new Exception('El Ajuste no fue realizado. Debe indicar un motivo del ajuste');
                }
                $this->model->ajustarProducto(
                    (int)$_POST['producto_id'],
                    (float)$_POST['cantidad'],
                    htmlspecialchars($motivo),
                    (int)$_SESSION['user_id'],
                    htmlspecialchars($_POST['tipo'])

                );

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

    public function materiaPrima()
    {
        if ($_POST) {
            try {
                $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';
                if ($motivo === '') {
                    throw new Exception('El Ajuste no fue realizado. Debe indicar un motivo del ajuste');
                }
                $this->model->ajustarMateriaPrima(
                    (int)$_POST['materia_prima_id'],
                    (float)$_POST['cantidad'],
                    htmlspecialchars($motivo),
                    (int)$_SESSION['user_id'],
                    htmlspecialchars($_POST['tipo'])
                );
                if(1){
                    $_SESSION['success'] = 'Ajuste realizado con éxito';
                } // borre la parte del else

                header('Location: ' . BASE_URL . '/ajustesstock');
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header('Location: ' . BASE_URL . '/ajustesstock');
                exit;
            }
        }

        $this->view('ajustes_stock/form_materia_prima');
    }
}