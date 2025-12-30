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
    public function index()
    {
        $ingresos = $this->ingreso->all();

        $this->view('ingresos/index', [
            'ingresos' => $ingresos
        ]);
    }
    public function create($ordenCompraId)
    {
        $orden = $this->oc->findWithDetalle($ordenCompraId);
        if (!$orden) {
            die('Orden no válida');
        }

        if ($orden['estado'] === 'RECIBIDA') {
            die('La orden ya fue recibida completamente');
        }

        if ($_POST) {
            $existente = $this->ingreso->findByRemitoProveedor(
            $orden['proveedor_id'],
            $_POST['remito']
            
        );
            if (!$existente) {
                header('Location: '.BASE_URL.'/ingresosmercaderia');
                exit;
            }
            try {
                $ingresoId = $this->ingreso->registrar(
                    $orden['id'],
                    $orden['proveedor_id'],
                    $_SESSION['user_id'],
                    $_POST
                );

                $_SESSION['success'] = 'Ingreso registrado de orden '.$orden['id'].' correctamente';
                header('Location: '.BASE_URL.'/ingresosmercaderia/show/'.$ingresoId);
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }
        }  
        $this->view('ingresos/form', [
            'title'=>'Ingreso de mercadería',
            'orden' => $orden,
            'detalle'=>$orden['detalle'],
            'proveedor'=>$orden['razon_social'],
            'orden_compra_id'=>$ordenCompraId
        ]);
    }

    public function show($id)
    {
        $ingreso = $this->ingreso->findWithDetalle($id);

        if (!$ingreso) {
            die('Ingreso no encontrado');
        }

        $this->view('ingresos/show', [
            'ingreso' => $ingreso
        ]);
    }
}