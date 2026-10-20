<?php
require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Remitosalida.php';
require_once BASE_PATH.'/app/models/Notapedido.php';
require_once BASE_PATH.'/app/models/Cliente.php';
require_once BASE_PATH.'/app/models/Deposito.php';
require_once BASE_PATH.'/app/services/MailService.php';
class RemitosSalidaController extends Controller
{
    private RemitoSalida $model;
    private NotaPedido $np;
    private Cliente $cli;

    public function __construct()
    {
        $this->model = new RemitoSalida();
        $this->np = new NotaPedido();
        $this->cli = new Cliente();
    }
    public function index()
    {
        $remitos = $this->model->all();

        $this->view('remitos_salida/index', [
            'remitos' => $remitos
        ]);
    }

    /**
     * AJAX: devuelve las series DISPONIBLES en un depósito para un producto o MP.
     * GET: deposito_id, producto_id (opcional), materia_prima_id (opcional)
     */
    public function seriesDisponibles(): void
    {
        header('Content-Type: application/json');
        $depositoId = (int)($_GET['deposito_id'] ?? 0);
        $productoId = !empty($_GET['producto_id']) ? (int)$_GET['producto_id'] : null;
        $mpId = !empty($_GET['materia_prima_id']) ? (int)$_GET['materia_prima_id'] : null;

        if ($depositoId <= 0 || ($productoId === null && $mpId === null)) {
            echo json_encode([]);
            return;
        }

        require_once BASE_PATH . '/app/models/Serie.php';
        $serieModel = new Serie();
        $series = $serieModel->disponibles($depositoId, $productoId, $mpId);
        echo json_encode($series);
    }
        public function pdf($id) // funcion para descargar los pdf remitados
    {
        validarId($id, BASE_URL . '/remitossalida');
        $remito = $this->model->find($id);

        if (!$remito || !$remito['pdf_path']) {
            die('PDF no disponible');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($remito['pdf_path']) . '"');
        readfile($remito['pdf_path']);
        exit;
    }

    public function regenerarPdf($id)
    {
        Auth::requireLogin();
        Auth::requireTenant();
        validarId($id, BASE_URL . '/remitossalida');

        try {
            $this->model->generarYGuardarPdf((int)$id);
            $_SESSION['success'] = 'PDF regenerado correctamente.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al regenerar PDF: ' . $e->getMessage();
            error_log('Error regenerando PDF remito: ' . $e->getMessage());
        }

        header("Location: " . BASE_URL . "/remitossalida/show/$id");
        exit;
    }

        public function create($notaPedidoId) // llamo al form para remitar una NP
    {
        validarId($notaPedidoId, BASE_URL . '/remitossalida');
        $np = $this->np->findWithPendientes((int)$notaPedidoId); // obtengo la np con los pendientes de remito desde el modelo notas pedido
        $cli_act = $this->cli->cliactive((int)$np['id_cliente']);
        if($cli_act['activo']===0){
            $_SESSION['error'] = 'Cliente Inactivo, No se puede remitar';
            error_log('Cliente Inactivo, No se puede remitar');
            header("Location: " . BASE_URL . "/notaspedido");
            exit;

        }
        error_log(print_r($np, true).'-'.__FILE__.'-'.__LINE__); // guardo un registro de la infoque me devuelve el modelo
        if (!$np ) { // si no existe o hay alguna error en la variable devuelvo el error, dejo un log y redirijo
            $_SESSION['error'] = 'Nota de Pedido, inexistente';
            error_log('Nota de Pedido, inexistente');
            header("Location: " . BASE_URL . "/notaspedido");
            exit;
        }
        if (!$np || $np['estado'] !== 'APROBADA') { //si np es nulo o no esta aprobada alerto redirijo y dejo el log
            $_SESSION['error'] = 'Nota de Pedido # '.$notaPedidoId .' No Aprobada o Anulada';
            error_log('Nota de Pedido, no aprobada: ID '.$notaPedidoId.' Estado: '.$np['estado'].'-'.__FILE__.'-'.__LINE__);
            header("Location: " . BASE_URL . "/notaspedido");
            exit;
        }
        //hasta aqui si no hay errores sigo con el form

        if ($_SERVER['REQUEST_METHOD'] === 'POST') { //cuando desde el form envio el post inicio el proceso de generacion del remito
            error_log('POST recibido para crear Remito de Salida: ' . print_r($_POST, true) . ' - ' . __FILE__ . ':' . __LINE__);
            try {
                if (empty($_POST['items'])) { //si items esta vacio no puedo generar el remito y genero la excepcion, esto no deberia pasar pero por control se deja el bloqueo
                    throw new Exception('No hay productos para remitar');
                }
                error_log('Creando Remito de Salida '.print_r($_POST['items'], true).'-'.__FILE__.'-'.__LINE__); //se ascienta un log de los items a remitir

                // Normalizar items: pasar de [producto_id => cantidad] a [producto_id => ['cantidad' => ..., 'deposito_id' => ..., 'series' => [...]]]
                $itemsNormalizados = [];
                foreach ($_POST['items'] as $productoId => $cantidad) {
                    if (is_array($cantidad)) {
                        $itemNorm = [
                            'cantidad' => (float)($cantidad['cantidad'] ?? 0),
                            'deposito_id' => !empty($cantidad['deposito_id']) ? (int)$cantidad['deposito_id'] : null,
                            'lote_id' => !empty($cantidad['lote_id']) ? (int)$cantidad['lote_id'] : null
                        ];
                        // Series seleccionadas (para productos con usa_serie)
                        if (!empty($cantidad['series']) && is_array($cantidad['series'])) {
                            $itemNorm['series'] = array_values(array_filter(array_map('intval', $cantidad['series'])));
                            // Si hay series, la cantidad es el número de series seleccionadas
                            $itemNorm['cantidad'] = count($itemNorm['series']);
                        }
                        $itemsNormalizados[(int)$productoId] = $itemNorm;
                    } else {
                        $itemsNormalizados[(int)$productoId] = [
                            'cantidad' => (float)$cantidad,
                            'deposito_id' => !empty($_POST['deposito_' . $productoId]) ? (int)$_POST['deposito_' . $productoId] : null,
                            'lote_id' => null
                        ];
                    }
                }

                $id = $this->model->create(
                    (int)$notaPedidoId,
                    (int)$_SESSION['user_id'],
                    $itemsNormalizados,
                    $_POST['observaciones'] ?? null
                );
                error_log('Remito de Salida creado con ID: ' . $id . ' - ' . __FILE__ . ':' . __LINE__);
                // GENERAR Y GUARDA PDF AUTOMÁTICO (antes descargaba auto ahora no por seguridad)
                //$this->model->generarPdf($id); -> obsoleto, ahora lo hago y lo guardo en la db
                $this->model->generarYGuardarPdf($id);
                $_SESSION['success'] = 'Remito de Salida creado correctamente.';
                $this->reenviar($id); //llamo a la funcion de reenviar mail automaticamente al crear el remito
                header("Location: " . BASE_URL . "/remitossalida/show/$id");
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                error_log('Error al crear Remito de Salida: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            }
        }

        $this->view('remitos_salida/form', [ //muestro el form con la info de la np para remitar
            'np' => $np,
            'depositos' => (new Deposito())->allSeleccionables()
        ]);
    }

    public function show($id)
    {
        validarId($id, BASE_URL . '/remitossalida');
        $remito = $this->model->find($id);
        if (!$remito){
            $_SESSION['error'] = 'El remito no existe.';
            error_log('Remito no encontrado - ' . __FILE__ . ':' . __LINE__);
            die('Remito no encontrado');
        } 

        $this->view('remitos_salida/show', [
            'remito' => $remito
        ]);
    }

    public function reenviar($id) //numero de remito a reenviar
    {
        validarId($id, BASE_URL . '/remitossalida');
        $remito = $this->model->findCompleto((int)$id); //traigo el array de datos del remito completo

        if (!$remito || empty($remito['pdf_path'])) {
            $_SESSION['error'] = 'Remito inválido o sin PDF.';
            header("Location: " . BASE_URL . "/remitossalida/show/$id");
            exit;
        }

        try {
            $mail = new MailService(); //instancio el servicio de mail
            $mail->enviarRemito(    //llamo al metodo para enviar el remito por mail
                $remito['cliente'],
                $remito,
                $_SESSION['user_id']
            );

            $_SESSION['success'] = 'Remito reenviado correctamente.';

        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al reenviar remito.' . $e->getMessage();
            error_log('Error al reenviar remito: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
        }

        header("Location: " . BASE_URL . "/remitossalida/show/$id");
        exit;
    }

    // =====================================================
    // REMITO MANUAL (sin Nota de Pedido)
    // =====================================================

    /**
     * Formulario para crear remito manual.
     */
    public function createManual()
    {
        $clientes = $this->cli->allactive();
        $productos = $this->getProductos();

        $this->view('remitos_salida/form_manual', [
            'clientes' => $clientes,
            'productos' => $productos,
            'depositos' => (new Deposito())->allSeleccionables()
        ]);
    }

    /**
     * Guardar remito manual.
     */
    public function storeManual()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: " . BASE_URL . "/remitossalida");
            exit;
        }

        try {
            $items = $_POST['items'] ?? [];
            $itemsManuales = $_POST['items_manual'] ?? [];

            // Validar que haya al menos un item
            $tieneProductos = false;
            foreach ($items as $key => $datos) {
                if (is_array($datos) && (($datos['cantidad'] ?? 0) > 0)) {
                    $tieneProductos = true;
                    break;
                }
            }
            $tieneManuales = !empty($itemsManuales) && count(array_filter($itemsManuales, fn($m) => $m && $this->decodeManualItem($m)['cantidad'] > 0)) > 0;

            if (!$tieneProductos && !$tieneManuales) {
                throw new Exception('No hay items para remitar');
            }

            // Normalizar items de productos
            $itemsNormalizados = [];
            foreach ($items as $productoId => $datos) {
                if (is_numeric($productoId)) {
                    if (is_array($datos)) {
                        $itemNorm = [
                            'cantidad' => (float)($datos['cantidad'] ?? 0),
                            'precio' => (float)($datos['precio'] ?? 0),
                            'deposito_id' => !empty($datos['deposito_id']) ? (int)$datos['deposito_id'] : null,
                        ];
                        // Series seleccionadas (para productos con usa_serie)
                        if (!empty($datos['series']) && is_array($datos['series'])) {
                            $itemNorm['series'] = array_values(array_filter(array_map('intval', $datos['series'])));
                            $itemNorm['cantidad'] = count($itemNorm['series']);
                        }
                        if ($itemNorm['cantidad'] > 0) {
                            $itemsNormalizados[(int)$productoId] = $itemNorm;
                        }
                    }
                }
            }

            // Decodificar items manuales
            $itemsManualesDecodificados = [];
            foreach ($itemsManuales as $item) {
                $decoded = $this->decodeManualItem($item);
                if ($decoded && $decoded['cantidad'] > 0) {
                    $itemsManualesDecodificados[] = $decoded;
                }
            }

            // Construir datos del cliente
            $clienteData = [];
            $tipoCliente = $_POST['tipo_cliente'] ?? 'existente';

            if ($tipoCliente === 'existente' && !empty($_POST['cliente_id'])) {
                $clienteData['cliente_id'] = (int)$_POST['cliente_id'];
            } else {
                $clienteData['cliente_nombre'] = trim($_POST['cliente_nombre'] ?? '');
                $clienteData['cliente_cuit'] = trim($_POST['cliente_cuit'] ?? '');
                $clienteData['cliente_direccion'] = trim($_POST['cliente_direccion'] ?? '');
                $clienteData['cliente_email'] = trim($_POST['cliente_email'] ?? '');
                $clienteData['cliente_telefono'] = trim($_POST['cliente_telefono'] ?? '');
                $clienteData['cliente_localidad'] = trim($_POST['cliente_localidad'] ?? '');

                if (empty($clienteData['cliente_nombre'])) {
                    throw new Exception('El nombre o razón social del cliente es obligatorio.');
                }

                if (empty($clienteData['cliente_email'])) {
                    $clienteData['cliente_email'] = 'contacto@alimentostriba.com.ar';
                }
            }

            $id = $this->model->createManual(
                (int)$_SESSION['user_id'],
                $itemsNormalizados,
                $itemsManualesDecodificados,
                $clienteData,
                $_POST['observaciones'] ?? null
            );

            $this->model->generarYGuardarPdf($id);

            // Enviar email automático si el cliente tiene email
            try {
                $emailCliente = $clienteData['cliente_email'] ?? $clienteData['email'] ?? '';
                if (!empty($emailCliente) && $emailCliente !== 'contacto@alimentostriba.com.ar') {
                    require_once BASE_PATH . '/app/services/MailService.php';
                    $mailService = new MailService();
                    $remitoCompleto = $this->model->findCompleto($id);
                    if ($remitoCompleto) {
                        $mailService->enviarRemito($remitoCompleto['cliente'], $remitoCompleto, $_SESSION['user_id']);
                    }
                }
            } catch (Exception $e) {
                error_log('Error enviando email de remito manual: ' . $e->getMessage());
            }

            $_SESSION['success'] = 'Remito manual creado correctamente.';
            header("Location: " . BASE_URL . "/remitossalida/show/$id");
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            error_log('Error al crear remito manual: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            header("Location: " . BASE_URL . "/remitossalida/create-manual");
            exit;
        }
    }

    private function decodeManualItem(string $encoded): ?array {
        $decoded = base64_decode($encoded, true);
        if ($decoded === false) return null;
        $parts = explode('|', $decoded);
        if (count($parts) !== 3) return null;
        return [
            'descripcion' => $parts[0],
            'cantidad' => (float)$parts[1],
            'precio' => (float)$parts[2],
        ];
    }

    /**
     * Traer productos activos con stock.
     */
    private function getProductos(): array
    {
        require_once BASE_PATH . '/app/models/Producto.php';
        $productoModel = new Producto();
        $depositoId = !empty($_GET['deposito_id']) ? (int)$_GET['deposito_id'] : null;
        return $productoModel->allConStock($depositoId);
    }

    /**
     * AJAX: buscar productos con stock para el remito manual.
     */
    public function searchProducts(): void
    {
        header('Content-Type: application/json');

        $q = trim($_GET['q'] ?? '');
        $depositoId = !empty($_GET['deposito_id']) ? (int)$_GET['deposito_id'] : null;
        if (strlen($q) < 2) {
            echo json_encode([]);
            return;
        }

        require_once BASE_PATH . '/app/models/Producto.php';
        $productoModel = new Producto();
        $productos = $productoModel->searchConStock($q, $depositoId);

        echo json_encode($productos);
    }

    /**
     * AJAX: stock de un producto en un depósito específico.
     */
    public function stockDeposito(): void
    {
        header('Content-Type: application/json');

        $productoId = (int)($_GET['producto_id'] ?? 0);
        $depositoId = (int)($_GET['deposito_id'] ?? 0);

        if ($productoId <= 0) {
            echo json_encode(['stock' => 0, 'stock_total' => 0]);
            return;
        }

        if ($depositoId <= 0) {
            $stock = $this->model->stockDisponibleProducto($productoId);
            echo json_encode(['stock' => $stock, 'stock_total' => $stock]);
            return;
        }

        $stock = $this->model->stockDisponibleProductoPorDeposito($productoId, $depositoId);
        $stockTotal = $this->model->stockDisponibleProducto($productoId);

        echo json_encode([
            'stock' => $stock,
            'stock_total' => $stockTotal
        ]);
    }
}
