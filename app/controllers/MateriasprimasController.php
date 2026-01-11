<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/MateriaPrima.php';
class MateriasprimasController extends Controller
{
    private MateriaPrima $mp;

    public function __construct()
    {
        $this->mp = new MateriaPrima();
    }

    public function index()
    {
        $this->view('materias_primas/index', [
            'title'=>'Materias Primas',
            'items'=>$this->mp->all()
        ]);
    }

    public function create()
    {
        if ($_POST) {
            $this->mp->create($_POST);
            header('Location: '.BASE_URL.'/materiasprimas');
            exit;
        }

        $this->view('materias_primas/form', [
            'title'=>'Nueva Materia Prima'
        ]);
    }

    public function search()
    {
        $q = trim($_GET['q'] ?? '');

        if (strlen($q) < 2) {
            echo json_encode([]);
            return;
        }

        $model = new MateriaPrima();
        echo json_encode($model->search($q));
    }
}