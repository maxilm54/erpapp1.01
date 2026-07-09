<?php

require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Cuentacorrientecliente.php';
require_once BASE_PATH.'/app/models/Cliente.php';
require_once BASE_PATH.'/app/models/Pago.php';

class CtaCteController extends Controller
{
    private CuentaCorrienteCliente $model;
    private Cliente $cliente;
    private Pago $pago;

    public function __construct()
    {
        $this->model   = new CuentaCorrienteCliente();
        $this->cliente = new Cliente();
        $this->pago = new Pago();
    }

    /**
     * 📊 Libro general de cuentas corrientes
     */
    public function index()
    {
        $movimientos = $this->model->all();

        $this->view('cta_cte/index', [
            'movimientos' => $movimientos
        ]);
    }

    /**
     * 👁 Ver un movimiento puntual
     */
    public function show($id)
    {
        validarId($id, BASE_URL . '/ctacte');
        $mov = $this->model->find((int)$id);

        if (!$mov) {
            $_SESSION['error'] = 'Movimiento no encontrado';
            error_log('Movimiento no encontrado en CtaCteController::show con ID: ' . $id . ' in ' . __FILE__ . ':' . __LINE__);
            header('Location: '.BASE_URL.'/ctacte');
            exit;
        }

        $this->view('cta_cte/show', [
            'mov' => $mov
        ]);
    }

    /**
     * 💰 Generar pago – Selección de cliente
     */
    public function pago()
    {
        $clientes = $this->cliente->all();
        if ($_SERVER['REQUEST_METHOD'] === 'POST'){
            header('Location: '.BASE_URL.'/ctacte/deudas/'.(int)$_POST['clienteId']);
            exit;
        }
        $this->view('cta_cte/pago_cliente', [
            'clientes' => $clientes
        ]);
    }

    /**
     * 💳 Deudas de un cliente
     */
    public function deudas($clienteId) //luego de la vista de elegir cliente a pagar me deberia mostrar su deuda total
    {
        validarId($clienteId, BASE_URL . '/ctacte/pago');
        $cliente_mov=$this->model->deudasActualCliente((int)$clienteId);
        $deudas = $this->model->deudasPorCliente((int)$clienteId); //busca en el modelo las deudas del cliente, muestra remitos y montos
        $cliente = $this->cliente->find((int)$clienteId); //trae los datos del cliente
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $this->registrarPago();
        }
        $this->view('cta_cte/pago_deudas', [ //muestra la vista de pago de deudas el form para ingresar el monto a pagar
            'cliente' => $cliente,
            'deudas'  => $deudas,
            'cliente_mov' => $cliente_mov
        ]);
    }

    /**
     * ✅ Registrar pago
     */
    public function registrarPago()
    {
        if (!$_POST) {
            header('Location: '.BASE_URL.'/ctacte');
            exit;
        }

        try { //EN LA VISTA DE PAGO CUANDO REGISTRO SE LLAMA AL MODELO registrarCredito con el id del cliente, monto, tipo PAGO, referencia 0, user_id y observaciones
            $this->model->registrarCredito(
                (int)$_POST['cliente_id'],
                (float)$_POST['monto'],
                'PAGO',
                0,
                $_SESSION['user_id'],
                $_POST['observaciones'] ?? null
            );
            $id = $this->pago->registrar([ //nueva funcion que registra el pago en la tabla de pagos y envia el comprobante por mail y lo gaurda tambien
                    'cliente_id' => $_POST['cliente_id'],
                    'usuario_id' => $_SESSION['user_id'],
                    'monto'      => (float)$_POST['monto'],
                    'medio_pago' => $_POST['medio_pago'] ?? null,
                    'observaciones' => $_POST['observaciones'] ?? null
                ]);            
            header('Location: '.BASE_URL.'/ctacte');

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: '.BASE_URL.'/ctacte/pago');
        }
        exit;
    }
}
