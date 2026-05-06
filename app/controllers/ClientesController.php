<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Cliente.php';
class ClientesController extends Controller{
    private Cliente $cliente;
    public function __construct(){
        $this->cliente=new Cliente();
    }

    public function index():void{
        $this->view('clientes/index',['title'=>'Clientes','clientes'=>$this->cliente->all()]);
    }

    public function create():void{
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                error_log('CSRF error validacion de token ClientesController::create'.__FILE__.':'.__LINE__);
                header('Location: ' . BASE_URL . '/clientes/create');
                exit;
            }
            $this->cliente->create([
                'razon_social' => htmlspecialchars(trim($_POST['razon_social'])),
                'cuit' => htmlspecialchars(trim($_POST['cuit'])),
                'email' => filter_var($_POST['email'], FILTER_SANITIZE_EMAIL),
                'telefono' => htmlspecialchars(trim($_POST['telefono'])),
                'localidad' => htmlspecialchars(trim($_POST['localidad'])),
                'direccion' => htmlspecialchars(trim($_POST['direccion'])),
                'contacto' => htmlspecialchars(trim($_POST['contacto'])),
                'es_distribuidor' => htmlspecialchars(trim($_POST['es_distribuidor'])),
                'observaciones_gral' => htmlspecialchars(trim($_POST['observaciones_gral'])),
                'obs_financieras' => htmlspecialchars(trim($_POST['obs_financieras']))
            ]);
            header('Location: ' . BASE_URL . '/clientes');
            exit;
        }

        $this->view('clientes/form', [
            'title' => 'Nuevo Cliente'
        ]);
    }

    public function edit(int $id): void
    {
        validarId($id, BASE_URL . '/clientes');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                error_log('CSRF error validacion de token ClientesController::edit'.__FILE__.':'.__LINE__);
                header('Location: ' . BASE_URL . '/clientes/edit/' . $id);
                exit;
            }
            $this->cliente->update($id, $_POST);
            header('Location: ' . BASE_URL . '/clientes');
            exit;
        }

        $this->view('clientes/form', [
            'title' => 'Editar Cliente',
            'cliente' => $this->cliente->find($id)
        ]);
    }

     public function delete(int $id): void
    {
        validarId($id, BASE_URL . '/clientes');
        $this->cliente->delete($id);
        header('Location: ' . BASE_URL . '/clientes');
    }
    public function activar(int $id): void
    {
        validarId($id, BASE_URL . '/clientes');
        $this->cliente->activar($id);
        header('Location: ' . BASE_URL . '/clientes');
    }

    public function show(int $id): void
    {
        validarId($id, BASE_URL . '/clientes');
        $this->view('clientes/show', [
            'title' => 'Detalle del Cliente',
            'cliente' => $this->cliente->find($id)
        ]);
    }

}