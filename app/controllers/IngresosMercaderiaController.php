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
        $orden = $this->oc->findWithDetalle($ordenCompraId); //traigo datos de la orden
        if (!$orden) {
            die('Orden no válida'); // aca devo generar un error y volver a los listados
        }

        if ($orden['estado'] === 'RECIBIDA') { //aca ya estoy validadndo que la orden esta completamente recibida
            die('La orden ya fue recibida completamente');
        }

        if ($_POST) {
            $existente = $this->ingreso->findByRemitoProveedor( //obtengo datos de remito por proveedor, el numero de remito solo pueden repetirse si el proveedor es distinto
                $orden['proveedor_id'],
                $_POST['remito']
                
            );
            if (!$existente) {
                $_SESSION['error'] = 'El número de remito ya fue ingresado para este proveedor.';
                error_log('Controlador ingMerc, metodo findByRemitoProveedor: Remito repetido para el mismo proveedor'.$existente);
                header('Location: '.BASE_URL.'/ingresosmercaderia');//revisar si el remito ya fue ingresado
                exit;
            }
            try {
                $ingresoId = $this->ingreso->registrar( //metodo para registrar el ingreso
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
        $this->view('ingresos/form', [ //muestro la vista del formulario de ingreso, cuando tengo un post entro valido y creo el ingreso
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
            'ingreso' => $ingreso,
            'faltante' => $this->ingreso->historicoIngresosPorOrden($ingreso['orden_compra_id'], $ingreso['ing_num_indicador'])
        ]);
    }

    public function showhist()
    {
        $historial = $this->ingreso->historialPorOrden();
        $this->view('ingresos/showhist', [
            'historico' => $historial,
        ]);
    }
}