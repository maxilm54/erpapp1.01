<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Numerador.php';
require_once BASE_PATH . '/app/core/Csrf.php';

class NumeradoresController extends Controller
{

    private function requireAdmin(): void
    {
        Auth::requireLogin();
        Auth::requireTenant();
        if (!Auth::isEmpresaAdmin()) {
            $_SESSION['error'] = 'No tienes permisos para administrar numeradores.';
            header('Location: ' . BASE_URL . '/home');
            exit;
        }
    }

    public function index(): void
    {
        $this->requireAdmin();

        $numeradorModel = new Numerador();
        $numeradores = $numeradorModel->getAll();

        $this->view('numeradores/index', [
            'title'      => 'Numeradores',
            'numeradores' => $numeradores
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();

        $numeradorModel = new Numerador();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'CSRF invalido.';
                header('Location: ' . BASE_URL . '/numeradores/create');
                exit;
            }

            $tipo = strtoupper(trim($_POST['tipo'] ?? ''));
            $ultimoNumero = (int)($_POST['ultimo_numero'] ?? 0);
            $incremento = (int)($_POST['incremento'] ?? 1);
            $prefijo = trim($_POST['prefijo'] ?? '');

            if (empty($tipo)) {
                $_SESSION['error'] = 'El tipo es obligatorio.';
                header('Location: ' . BASE_URL . '/numeradores/create');
                exit;
            }

            if (!preg_match('/^[A-Z0-9_]+$/', $tipo)) {
                $_SESSION['error'] = 'El tipo solo puede contener letras mayusculas, numeros y guiones bajos.';
                header('Location: ' . BASE_URL . '/numeradores/create');
                exit;
            }

            if ($numeradorModel->tipoExists($tipo)) {
                $_SESSION['error'] = "Ya existe un numerador con el tipo '{$tipo}'.";
                header('Location: ' . BASE_URL . '/numeradores/create');
                exit;
            }

            if ($incremento < 1) {
                $_SESSION['error'] = 'El incremento debe ser al menos 1.';
                header('Location: ' . BASE_URL . '/numeradores/create');
                exit;
            }

            if ($ultimoNumero < 0) {
                $_SESSION['error'] = 'El ultimo numero no puede ser negativo.';
                header('Location: ' . BASE_URL . '/numeradores/create');
                exit;
            }

            $numeradorModel->create([
                'tipo'           => $tipo,
                'ultimo_numero'  => $ultimoNumero,
                'incremento'     => $incremento,
                'prefijo'        => $prefijo,
            ]);

            $_SESSION['success'] = "Numerador '{$tipo}' creado correctamente.";
            header('Location: ' . BASE_URL . '/numeradores');
            exit;
        }

        $this->view('numeradores/form', [
            'title'     => 'Nuevo Numerador',
            'numerador' => null,
            'csrf'      => Csrf::generate()
        ]);
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();

        $numeradorModel = new Numerador();
        $numerador = $numeradorModel->findById($id);

        if (!$numerador) {
            $_SESSION['error'] = 'Numerador no encontrado.';
            header('Location: ' . BASE_URL . '/numeradores');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['error'] = 'CSRF invalido.';
                header('Location: ' . BASE_URL . "/numeradores/edit/{$id}");
                exit;
            }

            $tipo = strtoupper(trim($_POST['tipo'] ?? ''));
            $ultimoNumero = (int)($_POST['ultimo_numero'] ?? 0);
            $incremento = (int)($_POST['incremento'] ?? 1);
            $prefijo = trim($_POST['prefijo'] ?? '');

            if (empty($tipo)) {
                $_SESSION['error'] = 'El tipo es obligatorio.';
                header('Location: ' . BASE_URL . "/numeradores/edit/{$id}");
                exit;
            }

            if (!preg_match('/^[A-Z0-9_]+$/', $tipo)) {
                $_SESSION['error'] = 'El tipo solo puede contener letras mayusculas, numeros y guiones bajos.';
                header('Location: ' . BASE_URL . "/numeradores/edit/{$id}");
                exit;
            }

            if ($numeradorModel->tipoExists($tipo, $id)) {
                $_SESSION['error'] = "Ya existe otro numerador con el tipo '{$tipo}'.";
                header('Location: ' . BASE_URL . "/numeradores/edit/{$id}");
                exit;
            }

            if ($incremento < 1) {
                $_SESSION['error'] = 'El incremento debe ser al menos 1.';
                header('Location: ' . BASE_URL . "/numeradores/edit/{$id}");
                exit;
            }

            if ($ultimoNumero < 0) {
                $_SESSION['error'] = 'El ultimo numero no puede ser negativo.';
                header('Location: ' . BASE_URL . "/numeradores/edit/{$id}");
                exit;
            }

            $numeradorModel->update($id, [
                'tipo'           => $tipo,
                'ultimo_numero'  => $ultimoNumero,
                'incremento'     => $incremento,
                'prefijo'        => $prefijo,
            ]);

            $_SESSION['success'] = "Numerador '{$tipo}' actualizado correctamente.";
            header('Location: ' . BASE_URL . '/numeradores');
            exit;
        }

        $this->view('numeradores/form', [
            'title'     => 'Editar Numerador',
            'numerador' => $numerador,
            'csrf'      => Csrf::generate()
        ]);
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/numeradores');
            exit;
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'CSRF invalido.';
            header('Location: ' . BASE_URL . '/numeradores');
            exit;
        }

        $numeradorModel = new Numerador();
        $numerador = $numeradorModel->findById($id);

        if (!$numerador) {
            $_SESSION['error'] = 'Numerador no encontrado.';
            header('Location: ' . BASE_URL . '/numeradores');
            exit;
        }

        // Prevenir eliminar numeradores en uso (REMITO es critico)
        $enUso = ['REMITO', 'FACTURA', 'REMITO_COMPRA'];
        if (in_array($numerador['tipo'], $enUso)) {
            $_SESSION['error'] = "No se puede eliminar el numerador '{$numerador['tipo']}' porque esta en uso por el sistema.";
            header('Location: ' . BASE_URL . '/numeradores');
            exit;
        }

        $numeradorModel->delete($id);

        $_SESSION['success'] = "Numerador '{$numerador['tipo']}' eliminado.";
        header('Location: ' . BASE_URL . '/numeradores');
        exit;
    }
}
