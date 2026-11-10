<?php

require_once BASE_PATH.'/app/core/Controller.php';
require_once BASE_PATH.'/app/models/Notapedido.php';
require_once BASE_PATH.'/app/models/Presupuesto.php';
require_once BASE_PATH.'/app/models/Configuracion.php';
require_once BASE_PATH.'/app/models/Campopersonalizado.php';
require_once BASE_PATH.'/app/models/Adjunto.php';
require_once BASE_PATH.'/app/services/MailService.php';

class NotasPedidoController extends Controller
{
    private NotaPedido $np;

    public function __construct()
    {
        $this->np = new NotaPedido();
    }

    // 📄 Listado
    public function index()
    {
        $this->view('notas_pedido/index', [
            'notas' => $this->np->all()
        ]);
    }

    // ➕ Crear
    public function create()
    {
        $this->view('notas_pedido/form', [
            'clientes'     => $this->np->clientes(),
            'presupuestos' => [],
            'usarNomenclador' => (new Configuracion())->activa('usar_nomenclador', true),
            'usarAdjuntos' => (new Configuracion())->activa('usar_adjuntos', false),
            'cpCampos'     => (new Campopersonalizado())->porModulo('NOTA_PEDIDO'),
            'cpValores'    => [],
        ]);
    }

    // 💾 Guardar
    public function store()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'])) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                error_log('CSRF error validacion de token NotasPedidoController::store'.__FILE__.':'.__LINE__);
                header('Location: ' . BASE_URL . '/notaspedido/create');
                exit;
            }
            try {
                $fechaEntrega = trim((string)($_POST['fecha_entrega'] ?? ''));
                if ($fechaEntrega === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEntrega)) {
                    $fechaEntrega = null;
                }

                $id = $this->np->create([
                    'cliente_id'   => $_POST['cliente_id'],
                    'presupuesto_id' => $_POST['presupuesto_id'] ?: null,
                    'usuario_id'   => $_SESSION['user_id'],
                    'items'        => $_POST['items'] ?? [],
                    'observaciones' => $_POST['observaciones'] ?? '',
                    'fecha_entrega' => $fechaEntrega
                ]);

                // Campos personalizados: primero heredar los del presupuesto elegido
                // (se ven en la NP aunque ahi no esten configurados) y luego guardar
                // los propios del form de la NP.
                try {
                    $cp = new Campopersonalizado();
                    $prIdOrigen = (int)($_POST['presupuesto_id'] ?? 0);
                    $cpHeredados = $prIdOrigen > 0
                        ? $cp->copiarTodos('PRESUPUESTO', $prIdOrigen, 'NOTA_PEDIDO', (int)$id)
                        : 0;
                    $cp->guardarDesdePost('NOTA_PEDIDO', (int)$id, $_POST['campos_cp'] ?? []);
                    if ($cpHeredados > 0) {
                        $_SESSION['success'] = "Nota de pedido creada con {$cpHeredados} campo(s) adicional(es) heredado(s) del presupuesto.";
                    }
                } catch (Exception $e) {
                    error_log('[Campos NP] ' . $e->getMessage());
                }

                // Adjuntos subidos en el form de creacion (config activa)
                if ((new Configuracion())->activa('usar_adjuntos', false)
                    && !empty($_FILES['archivos_adjuntos']['name'][0])) {
                    try {
                        $n = (new Adjunto())->guardarMultiples('NOTA_PEDIDO', (int)$id, $_FILES['archivos_adjuntos']);
                        if ($n > 0) {
                            $_SESSION['success'] = "Nota de pedido creada con {$n} adjunto(s).";
                        }
                    } catch (Exception $e) {
                        error_log('[Adjuntos NP] ' . $e->getMessage());
                        $_SESSION['error'] = 'La NP se creó pero falló un adjunto: ' . $e->getMessage();
                    }
                }
            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header('Location: ' . BASE_URL . '/notaspedido/create');
                exit;
            }
            
            // Generar PDF inmediatamente
            try {
                $pdfPath = $this->np->generarYGuardarPdf($id);
                error_log("[PDF NotaPedido] Generado: {$pdfPath}");
            } catch (Exception $e) {
                error_log("[PDF NotaPedido] Error al generar PDF: " . $e->getMessage());
            }
            
            header('Location: '.BASE_URL.'/notaspedido/show/'.$id);
            exit;
        }
    }

    // 👁 Ver
    public function show($id)
    {
        validarId($id, BASE_URL . '/notaspedido');
        $np = $this->np->findWithDetalle($id);
        if (!$np){
            $_SESSION['error'] = 'Nota de Pedido no encontrada';
            error_log('Error: Nota de Pedido no encontrada en NotasPedidoController::show'.__FILE__.':'.__LINE__);
            header('Location: ' . BASE_URL . '/notaspedido');
            die();
        }

        $this->view('notas_pedido/show', compact('np'));
    }

    // ✅ Aprobar
    public function approve($id)
    {
        validarId($id, BASE_URL . '/notaspedido');
        $this->np->aprobar($id);
        header('Location: '.BASE_URL.'/notaspedido/show/'.$id);
        exit;
    }

    /**
     * Convierte una linea libre de la NP en un producto real de la base.
     */
    public function agregarProducto($id, $detalleId)
    {
        validarId($id, BASE_URL . '/notaspedido');
        validarId($detalleId, BASE_URL . '/notaspedido/show/' . $id);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
            exit;
        }
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
            header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
            exit;
        }

        try {
            $productoId = $this->np->agregarProductoADetalle((int)$id, (int)$detalleId);
            try {
                $this->np->generarYGuardarPdf((int)$id);
            } catch (Exception $e) {
                error_log('[NotaPedido] Error regenerando PDF tras agregar producto: ' . $e->getMessage());
            }
            $_SESSION['success'] = 'Producto #' . $productoId . ' agregado a la base. Ya puede editarlo.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
        exit;
    }

    // ❌ Anular controlar y borrar este metodo viejo
    public function anularviejo($id)
    {
        validarId($id, BASE_URL . '/notaspedido');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->np->anular($id, $_POST['motivo']);
            header('Location: '.BASE_URL.'/notaspedido/show/'.$id);
            exit;
        }

        $this->view('notas_pedido/anular', ['id' => $id]);
    }
    public function anular($id)
    {
        validarId($id, BASE_URL . '/notaspedido');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->np->anular(
                    (int)$id,
                    $_POST['motivo']
                );

                $_SESSION['success'] = 'Nota de Pedido anulada';
                header('Location: ' . BASE_URL . '/notaspedido');
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }
        }
        $this->view('notas_pedido/anular', ['id' => $id]);
    }

    // 💾 Guardar campos personalizados desde la vista de la NP (incluye las NPs
    // generadas automaticamente al aprobar un presupuesto).
    public function guardarCampos($id)
    {
        validarId($id, BASE_URL . '/notaspedido');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
                exit;
            }

            try {
                $n = (new Campopersonalizado())->guardarDesdePost('NOTA_PEDIDO', (int)$id, $_POST['campos_cp'] ?? []);
                $_SESSION['success'] = $n > 0
                    ? 'Información adicional guardada.'
                    : 'No hay campos configurados en Empresa > Configuraciones para este módulo.';
            } catch (Exception $e) {
                $_SESSION['error'] = 'No se pudo guardar la información adicional: ' . $e->getMessage();
                error_log('[Campos NP guardarCampos] ' . $e->getMessage());
            }

            // Regenerar el PDF para que refleje los valores guardados
            try {
                $this->np->generarYGuardarPdf((int)$id);
            } catch (Exception $e) {
                error_log('[Campos NP PDF] ' . $e->getMessage());
            }
        }

        header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
        exit;
    }

    // ❌ Quitar una fila de valor (ej: heredada del presupuesto) de la NP
    public function quitarValor($id, $valorId)
    {
        validarId($id, BASE_URL . '/notaspedido');
        validarId($valorId, BASE_URL . '/notaspedido/show/' . $id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Token CSRF inválido. Inténtalo de nuevo.';
                header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
                exit;
            }

            try {
                $quitado = (new Campopersonalizado())->quitarValor((int)$valorId, 'NOTA_PEDIDO', (int)$id);
                $_SESSION['success'] = $quitado ? 'Valor quitado de la nota de pedido.' : 'El valor ya no existe.';
            } catch (Exception $e) {
                $_SESSION['error'] = 'No se pudo quitar el valor: ' . $e->getMessage();
                error_log('[Campos NP quitarValor] ' . $e->getMessage());
            }

            try {
                $this->np->generarYGuardarPdf((int)$id);
            } catch (Exception $e) {
                error_log('[Campos NP PDF] ' . $e->getMessage());
            }
        }

        header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
        exit;
    }

    public function pdf($id)
    {
        validarId($id, BASE_URL . '/notaspedido');
        $np = $this->np->findWithDetalle($id);

        if (!$np || empty($np['pdf_path']) || !file_exists($np['pdf_path'])) {
            $_SESSION['error'] = 'PDF no disponible. Regenerelo desde la vista de la nota de pedido.';
            header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
            exit;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($np['pdf_path']) . '"');
        readfile($np['pdf_path']);
        exit;
    }

    public function regenerarPdf($id)
    {
        validarId($id, BASE_URL . '/notaspedido');
        try {
            $this->np->generarYGuardarPdf((int)$id);
            $_SESSION['success'] = 'PDF regenerado correctamente.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al regenerar PDF: ' . $e->getMessage();
        }
        header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
        exit;
    }

    public function reenviar($id)
    {
        validarId($id, BASE_URL . '/notaspedido');
        $np = $this->np->findWithDetalle($id);

        if (!$np || empty($np['pdf_path']) || !file_exists($np['pdf_path'])) {
            $_SESSION['error'] = 'Nota de Pedido inválida o sin PDF. Regenerelo primero.';
            header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
            exit;
        }

        try {
            $mail = new MailService();
            $mail->enviarNotaPedido($np, (int)$np['cliente_id'], $_SESSION['user_id']);
            $_SESSION['success'] = 'Nota de Pedido enviada por email correctamente.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al enviar email: ' . $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/notaspedido/show/' . $id);
        exit;
    }
}
