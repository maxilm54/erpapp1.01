<?php

require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Presupuesto.php';
require_once BASE_PATH.'/app/models/Cliente.php';
require_once BASE_PATH.'/app/models/Producto.php';

class PresupuestosController extends Controller
{
    private Presupuesto $pr;

    public function __construct()
    {
        $this->pr = new Presupuesto();
    }

    public function index()
    {
        $this->view('presupuestos/index', [
            'presupuestos' => $this->pr->all()
        ]);
    }

    public function create()
    {
        $this->view('presupuestos/form', [
            'clientes'  => (new Cliente())->all(),
            'productos' => (new Producto())->all()
        ]);
    }

    public function store()
    {
        $id = $this->pr->create([
            'cliente_id' => $_POST['cliente_id'],
            'usuario_id' => $_SESSION['user_id'],
            'items'      => $_POST['items']
        ]);

        header('Location: '.BASE_URL.'/presupuestos/show/'.$id);
        exit;
    }

    public function edit($id)
    {
        $pr = $this->pr->find($id);

        if (!$pr || $pr['estado'] !== 'BORRADOR') {
            die('Presupuesto no editable');
        }

        $this->view('presupuestos/form', [
            'presupuesto' => $pr,
            'clientes'    => (new Cliente())->all(),
            'productos'   => (new Producto())->all()
        ]);
    }

    public function update($id)
    {
        $this->pr->update($id, [
            'cliente_id' => $_POST['cliente_id'],
            'items'      => $_POST['items']
        ]);

        header('Location: '.BASE_URL.'/presupuestos/show/'.$id);
        exit;
    }

    public function show($id)
    {
        $pr = $this->pr->find($id);
        if (!$pr) die('Presupuesto no encontrado');

        $this->view('presupuestos/show', [
            'presupuesto' => $pr
        ]);
    }

    public function aprobar($id)
    {
        $this->pr->aprobar($id);
        header('Location: '.BASE_URL.'/presupuestos/show/'.$id);
        exit;
    }
}