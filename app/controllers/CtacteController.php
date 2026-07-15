<?php

require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Cuentacorrientecliente.php';
require_once BASE_PATH.'/app/models/Cliente.php';
require_once BASE_PATH.'/app/models/Pago.php';
require_once BASE_PATH.'/app/models/CajaBanco.php';
require_once BASE_PATH.'/app/helpers/AsientoAutomatico.php';

class CtaCteController extends Controller
{
    private CuentaCorrienteCliente $model;
    private Cliente $cliente;
    private Pago $pago;
    private CajaBanco $cajaModel;
    private AsientoAutomatico $asientoAuto;

    public function __construct()
    {
        $this->model   = new CuentaCorrienteCliente();
        $this->cliente = new Cliente();
        $this->pago = new Pago();
        $this->cajaModel = new CajaBanco();
        $this->asientoAuto = new AsientoAutomatico();
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
        $cajasBancos = $this->cajaModel->getActivas();
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $this->registrarPago();
        }
        $this->view('cta_cte/pago_deudas', [ //muestra la vista de pago de deudas el form para ingresar el monto a pagar
            'cliente' => $cliente,
            'deudas'  => $deudas,
            'cliente_mov' => $cliente_mov,
            'cajasBancos' => $cajasBancos,
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

        try {
            $clienteId = (int)$_POST['cliente_id'];
            $monto = (float)$_POST['monto'];
            $cajaBancoId = !empty($_POST['caja_banco_id']) ? (int)$_POST['caja_banco_id'] : null;

            // Registrar crédito en ctacte
            $this->model->registrarCredito(
                $clienteId,
                $monto,
                'PAGO',
                0,
                $_SESSION['user_id'],
                $_POST['observaciones'] ?? null
            );

            // Registrar pago en tabla pagos
            $id = $this->pago->registrar([
                'cliente_id' => $clienteId,
                'usuario_id' => $_SESSION['user_id'],
                'monto'      => $monto,
                'medio_pago' => $_POST['medio_pago'] ?? null,
                'observaciones' => $_POST['observaciones'] ?? null
            ]);

            // Generar asiento contable automático
            try {
                $this->asientoAuto->ventaCredito(
                    $clienteId,
                    $monto,
                    'PAGO',
                    $id ?? 0,
                    $_SESSION['user_id'],
                    $cajaBancoId
                );
                $_SESSION['success'] = 'Pago registrado y asiento contable generado.';
            } catch (Exception $e) {
                error_log("Error generando asiento para pago ctacte: " . $e->getMessage());
                $_SESSION['success'] = 'Pago registrado (error al generar asiento: ' . $e->getMessage() . ')';
            }

            header('Location: '.BASE_URL.'/ctacte');

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: '.BASE_URL.'/ctacte/pago');
        }
        exit;
    }
}
