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
    //Vista para listar los ingresos de mercaderia, muestra fecha/proveedor/remito/orden y boton para ver detalle del ingreso
    public function index()
    {
        $ingresos = $this->ingreso->all();

        $this->view('ingresos/index', [
            'ingresos' => $ingresos
        ]);
    }
    //Funcion para ingresar mercaderia, recibe el id de la orden de compra, se ejecuta desde la vista ingresarmercaderia/create/id_oc
    //valida que la orden exista y que no este completamente recibida, luego valida que el numero de remito no se repita 
    //para el mismo proveedor, si todo es correcto registra el ingreso y redirige a la vista del ingreso creado
    public function create($ordenCompraId)
    {
        validarId($ordenCompraId, BASE_URL . '/ordenescompra');
        $orden = $this->oc->findWithDetalle($ordenCompraId); //traigo datos de la orden
        if (!$orden) { //devuelvo error si no existe la orden de compra
            $_SESSION['error'] = "El número de OC $ordenCompraId no existe.";
            error_log('Controlador de Numero de OC devuelve error, no existe la oc: '.$ordenCompraId.' - '.__FILE__.' - '.__LINE__);
            header('Location: '.BASE_URL.'/ordenescompra');
            exit;
        }

        if ($orden['estado'] === 'RECIBIDA') { //devulevo error si la orden ya fue recibida completamente
            $_SESSION['error'] = "El número de OC $ordenCompraId . Ya fue recibida completamente.";
            error_log('Controlador de Numero de OC fue recibida completamente,oc: '.$ordenCompraId.' - '.__FILE__.' - '.__LINE__);
            header('Location: '.BASE_URL.'/ordenescompra');
            exit;
        }
        //no tengo errores, espero el envio del post para procesare el ingreso.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Por favor, intente nuevamente.';
                error_log('Controlador IngresosMercaderiaController: Token CSRF inválido en create - '.__FILE__.' - '.__LINE__);
                header('Location: '.BASE_URL.'/ingresosmercaderia/create/'.$ordenCompraId);
                exit;
            }
            error_log(print_r($_POST, true));
            //Existente me va a devolver si existe o no un remito para ese proveedor, debe venir vacio para poder seguir sino alerta.
            $existente = $this->ingreso->findByRemitoProveedor(
                $orden['proveedor_id'],
                $_POST['remito']
            );
            error_log(print_r($existente, true));
            // existente es 1 (existe numero de remito para ese proveedor) tengo que entrar al if y devolver el alerta.Esto esta ok
            if ($existente===0) {
                $remito=$_POST['remito'];
                $_SESSION['warning'] = 'El número de remito '.$remito.' ya fue ingresado para este proveedor. Ingrese el Numero Correcto.';
                error_log("Controlador ingMerc, metodo findByRemitoProveedor: Remito repetido ".$remito." para el mismo proveedor. ". __FILE__.' - '.__LINE__ );
                header('Location: '.BASE_URL.'/ingresosmercaderia/create/'.$ordenCompraId);//revisar si el remito ya fue ingresado
                exit;
            }
            //todo ok sin errores llamo a la funcion para registrar el ingreso.
            $items = [];
            if (!empty($_POST['items'])) {
                foreach ($_POST['items'] as $index => $cantidad) {
                    if (empty($cantidad) || $cantidad <= 0) continue;
                    
                    $items[] = [
                        'tipo' => $_POST['tipo'][$index] ?? 'materia_prima',
                        'materia_prima_id' => !empty($_POST['materia_prima_id'][$index]) ? (int)$_POST['materia_prima_id'][$index] : null,
                        'producto_id' => !empty($_POST['producto_id'][$index]) ? (int)$_POST['producto_id'][$index] : null,
                        'cantidad' => (float)$cantidad
                    ];
                }
            }
            
            $data = [
                'remito' => $_POST['remito'],
                'items' => $items
            ];
            
            $ingresoId = $this->ingreso->registrar(
                $orden['id'],
                $orden['proveedor_id'],
                $_SESSION['user_id'],
                $data
            );
            $_SESSION['success'] = 'Ingreso registrado de orden '.$orden['id'].' correctamente';
            header('Location: '.BASE_URL.'/ingresosmercaderia/show/'.$ingresoId);
            exit;
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
        validarId($id, BASE_URL . '/ingresosmercaderia');
        $ingreso = $this->ingreso->findWithDetalle($id);

        if (!$ingreso) {
            $_SESSION['error'] = 'Ingreso no encontrado';
            error_log('Ingreso no encontrado en IngresosMercaderiaController::show con ID: ' . $id . ' in ' . __FILE__ . ':' . __LINE__);
            header('Location: ' . BASE_URL . '/ingresosmercaderia');
            die();
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