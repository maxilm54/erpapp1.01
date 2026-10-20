<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/core/Csrf.php';
require_once BASE_PATH . '/app/models/Cuentacorrienteempresa.php';

/**
 * Cuenta corriente de la empresa.
 * Registra ingresos y gastos de la empresa y la deuda por proveedor.
 */
class CuentacorrienteempresaController extends Controller
{
    private CuentaCorrienteEmpresa $modelo;

    public function __construct()
    {
        $this->modelo = new CuentaCorrienteEmpresa();
    }

    public function index(): void
    {
        $filtros = [
            'tipo'         => strtoupper(trim($_GET['tipo'] ?? '')),
            'categoria_id' => $_GET['categoria_id'] ?? '',
            'proveedor_id' => $_GET['proveedor_id'] ?? '',
            'fecha_desde'  => trim($_GET['fecha_desde'] ?? ''),
            'fecha_hasta'  => trim($_GET['fecha_hasta'] ?? ''),
            'q'            => trim($_GET['q'] ?? ''),
        ];

        $perPage = 50;
        $totalRows  = $this->modelo->countAll($filtros);
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $page       = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);

        $filtros['limit']  = $perPage;
        $filtros['offset'] = ($page - 1) * $perPage;

        $this->view('cuenta_corriente_emp/index', [
            'title'       => 'Cta. Cte. Empresa',
            'movements'   => $this->modelo->findAll($filtros),
            'balance'     => $this->modelo->getBalance($filtros),
            'saldos'      => $this->modelo->getSaldosPorProveedor(),
            'categorias'  => $this->modelo->getCategorias(),
            'proveedores' => $this->modelo->getProveedores(),
            'filtros'     => $filtros,
            'page'        => $page,
            'totalPages'  => $totalPages,
        ]);
    }

    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . '/cuentacorrienteempresa/create');
                exit;
            }

            $error = $this->validar($_POST);
            if ($error) {
                $_SESSION['error'] = $error;
                header('Location: ' . BASE_URL . '/cuentacorrienteempresa/create');
                exit;
            }

            $this->modelo->create([
                'tipo'           => $_POST['tipo'],
                'descripcion'    => trim($_POST['descripcion']),
                'monto'          => (float)str_replace(',', '.', $_POST['monto']),
                'fecha'          => $_POST['fecha'] . ' 00:00:00',
                'categoria_id'   => $_POST['categoria_id'] ?? null,
                'proveedor_id'   => $_POST['proveedor_id'] ?? null,
                'referencia_id'  => $_POST['referencia_id'] ?? null,
                'referencia_tipo' => $_POST['referencia_tipo'] ?? null,
                'usuario_id'     => (int)$_SESSION['user_id'],
            ]);

            $_SESSION['success'] = 'Movimiento registrado correctamente.';
            header('Location: ' . BASE_URL . '/cuentacorrienteempresa');
            exit;
        }

        $this->view('cuenta_corriente_emp/form', [
            'title'       => 'Nuevo Movimiento',
            'movimiento'  => null,
            'categorias'  => $this->modelo->getCategorias(),
            'proveedores' => $this->modelo->getProveedores(),
        ]);
    }

    public function edit(int $id): void
    {
        validarId($id, BASE_URL . '/cuentacorrienteempresa');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'Token CSRF invalido.';
                header('Location: ' . BASE_URL . "/cuentacorrienteempresa/edit/$id");
                exit;
            }

            $error = $this->validar($_POST);
            if ($error) {
                $_SESSION['error'] = $error;
                header('Location: ' . BASE_URL . "/cuentacorrienteempresa/edit/$id");
                exit;
            }

            $this->modelo->update($id, [
                'tipo'          => $_POST['tipo'],
                'descripcion'   => trim($_POST['descripcion']),
                'monto'         => (float)str_replace(',', '.', $_POST['monto']),
                'fecha'         => $_POST['fecha'] . ' 00:00:00',
                'categoria_id'  => $_POST['categoria_id'] ?? null,
                'proveedor_id'  => $_POST['proveedor_id'] ?? null,
            ]);

            $_SESSION['success'] = 'Movimiento actualizado correctamente.';
            header('Location: ' . BASE_URL . '/cuentacorrienteempresa');
            exit;
        }

        $movimiento = $this->modelo->findById($id);
        if (!$movimiento) {
            $_SESSION['error'] = 'Movimiento no encontrado.';
            header('Location: ' . BASE_URL . '/cuentacorrienteempresa');
            exit;
        }

        $this->view('cuenta_corriente_emp/form', [
            'title'       => 'Editar Movimiento',
            'movimiento'  => $movimiento,
            'categorias'  => $this->modelo->getCategorias(),
            'proveedores' => $this->modelo->getProveedores(),
        ]);
    }

    public function delete(int $id): void
    {
        validarId($id, BASE_URL . '/cuentacorrienteempresa');

        $this->modelo->delete($id);
        $_SESSION['success'] = 'Movimiento anulado.';
        header('Location: ' . BASE_URL . '/cuentacorrienteempresa');
        exit;
    }

    /**
     * Valida el formulario. Devuelve el mensaje de error o null si todo esta bien.
     */
    private function validar(array $data): ?string
    {
        $tipo = strtoupper(trim($data['tipo'] ?? ''));
        if (!in_array($tipo, ['INGRESO', 'GASTO'], true)) {
            return 'El tipo debe ser INGRESO o GASTO.';
        }

        if (trim($data['descripcion'] ?? '') === '') {
            return 'La descripcion es obligatoria.';
        }

        $monto = (float)str_replace(',', '.', $data['monto'] ?? '0');
        if ($monto <= 0) {
            return 'El monto debe ser mayor a 0.';
        }

        if (trim($data['fecha'] ?? '') === '') {
            return 'La fecha es obligatoria.';
        }

        return null;
    }
}
