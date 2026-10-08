<?php

use Dompdf\Dompdf;
use Dompdf\Options;

require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/app/models/Pago.php';
require_once BASE_PATH . '/app/models/Devolucion.php';

class PdfService
{
    private Pago $pago;

    public function __construct()
    {
        $this->pago = new Pago();
    }

    public function generarReciboPago(int $pagoId): string
    {
        $pago = $this->pago->obtenerPagoCompleto($pagoId);

        if (!$pago) {
            throw new Exception('Pago no encontrado');
        }

        $html = $this->renderTemplate('pago', [
            'pago' => $pago
        ]);

        $dir = empresaStoragePath("pagos");
        $filename = "pago_{$pagoId}.pdf";
        $fullPath = $dir . '/' . $filename;

        $this->guardarPdf($html, $fullPath);

        return $fullPath;
    }

    public function regenerarReciboPago(int $pagoId): string
    {
        return $this->generarReciboPago($pagoId);
    }

    public function generarDevolucionPdf(int $devolucionId): string
    {
        $devModel = new Devolucion();
        $devolucion = $devModel->find($devolucionId);

        if (!$devolucion) {
            throw new Exception('Devolución no encontrada');
        }

        $html = $this->renderDevolucionTemplate($devolucion);

        $dir = empresaStoragePath("devoluciones");
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $filename = "devolucion_" . str_pad($devolucion['numero'], 6, '0', STR_PAD_LEFT) . ".pdf";
        $fullPath = $dir . '/' . $filename;

        $this->guardarPdf($html, $fullPath);

        return $fullPath;
    }

    private function renderDevolucionTemplate(array $devolucion): string
    {
        extract($data = [
            'devolucion' => $devolucion,
        ]);
        $empresa = loadEmpresaFromDb();
        $logoPath = empresaLogoPath();
        $logo = ($logoPath && file_exists($logoPath))
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : '';

        ob_start();
        require BASE_PATH . "/app/views/pdf/devolucion.php";
        return ob_get_clean();
    }

    private function guardarPdf(string $html, string $path): void
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        file_put_contents($path, $dompdf->output());
    }

    private function renderTemplate(string $template, array $data): string
    {
        extract($data);
        $empresa = loadEmpresaFromDb();

        ob_start();
        require BASE_PATH . "/app/views/mails/{$template}.php";
        return ob_get_clean();
    }
}
