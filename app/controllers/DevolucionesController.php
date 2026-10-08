<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Devolucion.php';
require_once BASE_PATH . '/app/models/Remitosalida.php';
require_once BASE_PATH . '/app/models/Cliente.php';
require_once BASE_PATH . '/app/models/CajaBanco.php';
require_once BASE_PATH . '/app/services/MailService.php';

class DevolucionesController extends Controller
{
    private Devolucion $model;

    public function __construct()
    {
        $this->model = new Devolucion();
    }

    public function index(): void
    {
        $devoluciones = $this->model->all();

        $this->view('devoluciones/index', [
            'title'       => 'Devoluciones',
            'devoluciones' => $devoluciones,
        ]);
    }

    public function create(): void
    {
        $this->view('devoluciones/form', [
            'title'    => 'Nueva Devolución',
            'devolucion' => null,
            'cajas'    => (new CajaBanco())->getall(),
        ]);
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/devoluciones/create');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            header('Location: ' . BASE_URL . '/devoluciones/create');
            exit;
        }

        try {
            $remitoId = (int)($_POST['remito_id'] ?? 0);
            if ($remitoId <= 0) {
                throw new Exception('Debe seleccionar un remito válido.');
            }

            $remitoData = $this->model->findFromRemito($remitoId);
            if (!$remitoData) {
                throw new Exception('El remito seleccionado no existe.');
            }

            $motivo = trim($_POST['motivo'] ?? '');
            if (empty($motivo)) {
                throw new Exception('Debe indicar el motivo de la devolución.');
            }

            $condicionesValidas = ['NUEVO', 'BUEN_ESTADO', 'ESTADO_REGULAR', 'DANADO', 'INSERVIBLE'];

            $remitoDetalle = [];
            foreach ($remitoData['detalle'] as $rd) {
                $remitoDetalle[(int)$rd['producto_id']] = $rd;
            }

            $items = [];
            $postItems = $_POST['items'] ?? [];
            foreach ($postItems as $productoId => $itemData) {
                $productoId = (int)$productoId;
                $cantidadTotal = (float)($itemData['cantidad'] ?? 0);
                if ($cantidadTotal <= 0) continue;

                if (!isset($remitoDetalle[$productoId])) {
                    throw new Exception("El producto #{$productoId} no pertenece a este remito.");
                }

                $remitoItem = $remitoDetalle[$productoId];
                $pendiente = (float)$remitoItem['pendiente_devolver'];

                if ($cantidadTotal > $pendiente + 0.01) {
                    $yaDevuelto = (float)$remitoItem['ya_devuelto'];
                    throw new Exception(
                        "La cantidad a devolver del producto «{$remitoItem['producto_nombre']}» (" . number_format($cantidadTotal, 2, ',', '.') . ") " .
                        "supera lo pendiente (" . number_format($pendiente, 2, ',', '.') . "). " .
                        "Ya fue devuelto: " . number_format($yaDevuelto, 2, ',', '.') . "."
                    );
                }

                $condicion = $itemData['condicion'] ?? 'BUEN_ESTADO';
                if (!in_array($condicion, $condicionesValidas, true)) {
                    throw new Exception("Condición inválida «{$condicion}» para el producto #{$productoId}.");
                }

                $cantidadReingresa = (float)($itemData['reingresa'] ?? 0);
                $cantidadDescarta = (float)($itemData['descarta'] ?? 0);

                $precioUnitario = (float)$remitoItem['precio_unitario'];
                if ($precioUnitario <= 0) {
                    throw new Exception("El producto «{$remitoItem['producto_nombre']}» no tiene precio definido en el remito.");
                }

                $obsItem = $itemData['observaciones'] ?? null;

                $items[] = [
                    'producto_id'       => $productoId,
                    'cantidad_total'    => $cantidadTotal,
                    'cantidad_reingresa' => $cantidadReingresa,
                    'cantidad_descarta' => $cantidadDescarta,
                    'condicion'         => $condicion,
                    'precio_unitario'   => $precioUnitario,
                    'observaciones'     => $obsItem,
                ];
            }

            if (empty($items)) {
                throw new Exception('Debe agregar al menos un producto a devolver.');
            }

            foreach ($items as $item) {
                if ($item['cantidad_reingresa'] != $item['cantidad_total']) {
                    throw new Exception("La cantidad reingresa debe ser igual a la cantidad devuelta para el producto #{$item['producto_id']}.");
                }
                if ($item['cantidad_descarta'] > $item['cantidad_reingresa']) {
                    throw new Exception("La cantidad descarta no puede superar la reingresa para el producto #{$item['producto_id']}.");
                }
            }

            $totalItems = array_reduce($items, fn($sum, $i) => $sum + ($i['cantidad_total'] * $i['precio_unitario']), 0);

            $reembolsos = [];
            $postReembolsos = $_POST['reembolsos'] ?? [];
            foreach ($postReembolsos as $rData) {
                $metodo = trim($rData['metodo'] ?? '');
                $monto = (float)($rData['monto'] ?? 0);
                if (empty($metodo) || $monto <= 0) continue;

                if (!in_array($metodo, ['NOTA_CREDITO', 'EFECTIVO', 'TRANSFERENCIA'], true)) {
                    throw new Exception("Método de reembolso inválido «{$metodo}».");
                }

                $cajaBancoId = !empty($rData['caja_banco_id']) ? (int)$rData['caja_banco_id'] : null;
                if (in_array($metodo, ['EFECTIVO', 'TRANSFERENCIA'], true) && !$cajaBancoId) {
                    throw new Exception("El reembolso en {$metodo} requiere indicar una caja o banco.");
                }

                if ($cajaBancoId) {
                    $cajaModel = new CajaBanco();
                    $caja = $cajaModel->findById($cajaBancoId);
                    if (!$caja) {
                        throw new Exception("La caja/banco #{$cajaBancoId} no existe.");
                    }
                    $saldoCaja = (float)$caja['saldo_actual'];
                    if ($saldoCaja < $monto - 0.01) {
                        throw new Exception(
                            "El saldo de «{$caja['nombre']}» ($" . number_format($saldoCaja, 2, ',', '.') . ") " .
                            "no alcanza para cubrir el reembolso de $" . number_format($monto, 2, ',', '.') . " en {$metodo}."
                        );
                    }
                }

                $reembolsos[] = [
                    'metodo'         => $metodo,
                    'monto'          => $monto,
                    'caja_banco_id'  => $cajaBancoId,
                    'observaciones'  => $rData['observaciones'] ?? null,
                ];
            }

            $totalReembolsos = array_reduce($reembolsos, fn($sum, $r) => $sum + $r['monto'], 0);

            if ($totalReembolsos > 0 && $totalReembolsos < $totalItems - 0.01) {
                throw new Exception("El monto total de reembolsos ($" . number_format($totalReembolsos, 2) . ") no puede ser menor al total de la devolución ($" . number_format($totalItems, 2) . ").");
            }

            $ncTotal = array_reduce($reembolsos, fn($sum, $r) => $sum + ($r['metodo'] === 'NOTA_CREDITO' ? $r['monto'] : 0), 0);
            if ($ncTotal > 0 && $ncTotal < $totalItems - 0.01) {
                throw new Exception("La Nota de Crédito debe cubrir el total de la devolución ($" . number_format($totalItems, 2) . "), pero solo indica $" . number_format($ncTotal, 2) . ".");
            }

            $usuarioId = $_SESSION['user_id'];
            $clienteId = (int)$remitoData['cliente_id'];

            $devolucionId = $this->model->create(
                [
                    'remito_id'      => $remitoId,
                    'cliente_id'     => $clienteId,
                    'usuario_id'     => $usuarioId,
                    'motivo'         => $motivo,
                    'cliente_nombre' => $remitoData['cliente_nombre'] ?? null,
                ],
                $items,
                $reembolsos
            );

            try {
                $this->model->generarYGuardarPdf($devolucionId);
            } catch (Exception $e) {
                error_log("Error generando PDF devolución #{$devolucionId}: " . $e->getMessage());
            }

            try {
                $mailService = new MailService();
                $devolucionData = $this->model->find($devolucionId);
                if (!empty($devolucionData['cliente_email'])) {
                    $mailService->enviar('DEVOLUCION', $devolucionId, $devolucionData['cliente_email'], $usuarioId, [
                        'cliente_nombre' => $devolucionData['nombre_cliente'] ?? '',
                        'numero'         => $devolucionData['numero'],
                        'fecha'          => date('d/m/Y', strtotime($devolucionData['created_at'])),
                        'motivo'         => $devolucionData['motivo'],
                    ], [
                        'attachments' => $devolucionData['pdf_path'] ?? null,
                    ]);
                }
            } catch (Exception $e) {
                error_log("Error enviando email devolución #{$devolucionId}: " . $e->getMessage());
            }

            $_SESSION['success'] = 'Devolución procesada correctamente.';
            header('Location: ' . BASE_URL . '/devoluciones/show/' . $devolucionId);
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: ' . BASE_URL . '/devoluciones/create');
            exit;
        }
    }

    public function show(int $id): void
    {
        validarId($id, BASE_URL . '/devoluciones');

        $devolucion = $this->model->find($id);
        if (!$devolucion) {
            $_SESSION['error'] = 'Devolución no encontrada.';
            header('Location: ' . BASE_URL . '/devoluciones');
            exit;
        }

        $this->view('devoluciones/show', [
            'title'      => 'Devolución #' . $devolucion['numero'],
            'devolucion' => $devolucion,
        ]);
    }

    public function anular(int $id): void
    {
        validarId($id, BASE_URL . '/devoluciones');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $devolucion = $this->model->find($id);
            if (!$devolucion) {
                $_SESSION['error'] = 'Devolución no encontrada.';
                header('Location: ' . BASE_URL . '/devoluciones');
                exit;
            }

            $this->view('devoluciones/anular', [
                'title'      => 'Anular Devolución #' . $devolucion['numero'],
                'devolucion' => $devolucion,
                'id'         => $id,
            ]);
            return;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            header('Location: ' . BASE_URL . '/devoluciones/show/' . $id);
            exit;
        }

        try {
            $motivo = trim($_POST['motivo'] ?? '');
            if (empty($motivo)) {
                throw new Exception('Debe indicar el motivo de la anulación.');
            }

            $usuarioId = $_SESSION['user_id'];
            $this->model->anular($id, $usuarioId, $motivo);

            $_SESSION['success'] = 'Devolución anulada correctamente.';
            header('Location: ' . BASE_URL . '/devoluciones/show/' . $id);
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: ' . BASE_URL . '/devoluciones/show/' . $id);
            exit;
        }
    }

    public function pdf(int $id): void
    {
        validarId($id, BASE_URL . '/devoluciones');
        $devolucion = $this->model->find($id);

        if (!$devolucion || empty($devolucion['pdf_path'])) {
            die('PDF no disponible');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($devolucion['pdf_path']) . '"');
        readfile($devolucion['pdf_path']);
        exit;
    }

    public function regenerarPdf(int $id): void
    {
        Auth::requireLogin();
        Auth::requireTenant();
        validarId($id, BASE_URL . '/devoluciones');

        try {
            $this->model->generarYGuardarPdf($id);
            $_SESSION['success'] = 'PDF regenerado correctamente.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al regenerar PDF: ' . $e->getMessage();
            error_log('Error regenerando PDF devolución: ' . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '/devoluciones/show/' . $id);
        exit;
    }

    public function reenviar(int $id): void
    {
        Auth::requireLogin();
        Auth::requireTenant();
        validarId($id, BASE_URL . '/devoluciones');

        try {
            $devolucion = $this->model->find($id);
            if (!$devolucion) {
                throw new Exception('Devolución no encontrada');
            }

            if (empty($devolucion['cliente_email'])) {
                throw new Exception('El cliente no tiene email configurado');
            }

            $mailService = new MailService();
            $mailService->enviar('DEVOLUCION', $id, $devolucion['cliente_email'], $_SESSION['user_id'], [
                'cliente_nombre' => $devolucion['nombre_cliente'] ?? '',
                'numero'         => $devolucion['numero'],
                'fecha'          => date('d/m/Y', strtotime($devolucion['created_at'])),
                'motivo'         => $devolucion['motivo'],
            ], [
                'attachments' => $devolucion['pdf_path'] ?? null,
            ]);

            $_SESSION['success'] = 'Email reenviado correctamente.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error al reenviar email: ' . $e->getMessage();
        }

        header('Location: ' . BASE_URL . '/devoluciones/show/' . $id);
        exit;
    }

    public function buscarRemito(): void
    {
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');

        if (strlen($q) < 1) {
            echo json_encode([]);
            return;
        }

        $stmt = Database::getInstance()->prepare("
            SELECT r.id, r.numero, r.created_at,
                   COALESCE(r.cliente_nombre, c.razon_social) AS cliente_nombre,
                   COALESCE(r.cliente_id, np.cliente_id) AS cliente_id
            FROM remitos_salida r
            LEFT JOIN notas_pedido np ON np.id = r.nota_pedido_id
            LEFT JOIN clientes c ON c.id = r.cliente_id OR (np.id IS NOT NULL AND c.id = np.cliente_id)
            WHERE r.numero LIKE ? OR r.id LIKE ?
            ORDER BY
                CASE
                    WHEN r.numero = ? THEN 0
                    WHEN r.numero LIKE CONCAT(?, '%') THEN 1
                    ELSE 2
                END,
                r.numero DESC
            LIMIT 20
        ");
        $like = "%{$q}%";
        $stmt->execute([$like, $like, $q, $q]);

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function itemsRemito(int $id): void
    {
        header('Content-Type: application/json');

        $remito = $this->model->findFromRemito($id);
        if (!$remito) {
            echo json_encode(['error' => 'Remito no encontrado']);
            return;
        }

        echo json_encode($remito);
    }

    public function deudasCliente(): void
    {
        header('Content-Type: application/json');
        $clienteId = (int)($_GET['cliente_id'] ?? 0);
        $clienteNombre = trim($_GET['cliente_nombre'] ?? '');

        if ($clienteId <= 0) {
            echo json_encode(['deuda' => 0, 'detalle' => []]);
            return;
        }

        $cc = new CuentaCorrienteCliente();

        if ($clienteId == 9999 && !empty($clienteNombre)) {
            $saldo = $cc->saldoPorNombreOcasional($clienteNombre);
            $deuda = (float)($saldo['saldo'] ?? 0);
            echo json_encode(['deuda' => max(0, $deuda), 'detalle' => []]);
            return;
        }

        $deudas = $cc->deudasActualCliente($clienteId);
        $deuda = 0;
        if (!empty($deudas[0]['saldo'])) {
            $deuda = (float)$deudas[0]['saldo'];
        }

        $movimientos = $cc->deudasPorCliente($clienteId);
        echo json_encode(['deuda' => max(0, $deuda), 'detalle' => $movimientos]);
    }
}
