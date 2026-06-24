<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Proveedor.php';

class ProveedoresController extends Controller{
    private Proveedor $proveedor;

    public function __construct(){
        $this->proveedor = new Proveedor();
    }

    public function index(){
        $this->view('proveedores/index',['title'=>'Proveedores','items'=>$this->proveedor->all()]);
    }

    public function create(){
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                error_log('CSRF error validacion de token ProveedoresController::create'.__FILE__.':'.__LINE__);
                header('Location: ' . BASE_URL . '/proveedores/create');
                exit;
            }
            //$this->proveedor->save($_POST);
            $this->proveedor->save([
                'razon_social' => htmlspecialchars(trim($_POST['razon_social'])),
                'cuit' => htmlspecialchars(trim($_POST['cuit'])),
                'email' => filter_var($_POST['email'], FILTER_SANITIZE_EMAIL),
                'telefono' => htmlspecialchars(trim($_POST['telefono'])),
                'contacto' => htmlspecialchars(trim($_POST['contacto'])),
                'rubro' => htmlspecialchars(trim($_POST['rubro'])),
                'localidad' => htmlspecialchars(trim($_POST['localidad'])),
                'direccion' => htmlspecialchars(trim($_POST['direccion'])),
                'observaciones_gral' => htmlspecialchars(trim($_POST['observaciones_gral'])),
            ]);
            header('Location: '.BASE_URL.'/proveedores');
            
            exit;
        }

        $this->view('proveedores/form',['title'=>'Nuevo Proveedor']);
    }

    public function edit($id)
    {
        validarId($id, BASE_URL . '/proveedores');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                error_log('CSRF error validacion de token ProveedoresController::edit'.__FILE__.':'.__LINE__);
                header('Location: ' . BASE_URL . '/proveedores/edit/' . $id);
                exit;
            }
            $this->proveedor->update($id, $_POST);
            header('Location: '.BASE_URL.'/proveedores');
            exit;
        }

        $this->view('proveedores/form', [
            'title'=>'Editar Proveedor',
            'proveedor'=>$this->proveedor->find($id)
        ]);
    }
}