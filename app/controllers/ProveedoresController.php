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
        if($_POST){
            $this->proveedor->save($_POST);
            header('Location: '.BASE_URL.'/proveedores');
            
            exit;
        }

        $this->view('proveedores/form',['title'=>'Nuevo Proveedor']);
    }

    public function edit($id)
    {
        if ($_POST) {
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