<?php

require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/IngresoMercaderia.php';
require_once BASE_PATH.'/app/models/OrdenCompra.php';
require_once BASE_PATH.'/app/models/MateriaPrima.php';

class IngresosMercaderiaController extends Controller
{
    private OrdenCompra $oc;
    private IngresoMercaderia $ingreso;

    public function __construct()
    {
        $this->oc = new OrdenCompra();
        $this->ingreso = new IngresoMercaderia();
    }

    public function create($ordenCompraId)
    {
        $orden = $this->oc->findWithDetalle($ordenCompraId);

        if (!$orden || $orden['estado'] !== 'APROBADA') {
            die('Orden no válida');
        }

        if ($_POST) {
            if ($_POST) {
            $this->ingreso->registrar($ordenCompraId, $_POST);
            header('Location: '.BASE_URL.'/ordenescompra');
            exit;
        }
        }  
        $this->view('ingresos/form', [
            'title'=>'Ingreso de mercadería',
            'detalle'=>$orden['detalle']
        ]);
    }
}