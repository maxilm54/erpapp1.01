<?php
use Dompdf\Dompdf;
use Dompdf\Options;

require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/app/models/Ctactetenant.php';

/**
 * Comprobantes PDF (letra X) de la cuenta corriente de empresas.
 *
 * Los archivos quedan en storage/empresa_{tenantId}/ctacte/ y se sirven
 * siempre desde PHP (esa carpeta esta bloqueada por storage/.htaccess).
 * Los nombres son deterministas para poder regenerarlos sin guardar la ruta
 * en la base.
 */
class CtactePdfService
{
    private Ctactetenant $model;

    public function __construct()
    {
        $this->model = new Ctactetenant();
    }

    /**
     * Comprobante de deuda de un registro mensual.
     */
    public function generarComprobanteDeuda(int $registroId): string
    {
        $registro = $this->model->findById($registroId);
        if (!$registro) {
            throw new RuntimeException('El registro de cuenta corriente no existe.');
        }

        $pagos = $this->model->pagosAplicados($registroId);

        $html = $this->render('ctacte_deuda', [
            'registro' => $registro,
            'pagos'    => $pagos,
            'emisor'   => $this->datosEmisor(),
            'logo'     => $this->logoEmisor(),
        ]);

        $path = $this->rutaComprobanteDeuda((int)$registro['tenant_id'], $registroId);
        $this->guardarPdf($html, $path);

        return $path;
    }

    /**
     * Comprobante de pago.
     */
    public function generarComprobantePago(int $pagoId): string
    {
        $pago = $this->model->findPago($pagoId);
        if (!$pago) {
            throw new RuntimeException('El pago no existe.');
        }

        $aplicaciones = $this->model->aplicacionesDePago($pagoId);

        $html = $this->render('ctacte_pago', [
            'pago'         => $pago,
            'aplicaciones' => $aplicaciones,
            'emisor'       => $this->datosEmisor(),
            'logo'         => $this->logoEmisor(),
        ]);

        $path = $this->rutaComprobantePago((int)$pago['tenant_id'], $pagoId);
        $this->guardarPdf($html, $path);

        return $path;
    }

    public function rutaComprobanteDeuda(int $tenantId, int $registroId): string
    {
        return $this->directorio($tenantId) . '/comprobante_deuda_' . $registroId . '.pdf';
    }

    public function rutaComprobantePago(int $tenantId, int $pagoId): string
    {
        return $this->directorio($tenantId) . '/comprobante_pago_' . $pagoId . '.pdf';
    }

    /**
     * Datos del emisor (la empresa que administra el sistema). Salen del
     * .env porque el modulo vive en el panel superadmin, sin tenant activo.
     */
    public function datosEmisor(): array
    {
        $nombre = defined('SMTP_FROM_NAME') && SMTP_FROM_NAME !== ''
            ? SMTP_FROM_NAME
            : (defined('APP_NAME') ? APP_NAME : 'Sistema');

        return [
            'nombre' => $nombre,
            'email'  => defined('SMTP_FROM') ? SMTP_FROM : '',
        ];
    }

    public function logoEmisor(): string
    {
        $logoPath = BASE_PATH . '/public/logoempresa.jpg';
        if (!file_exists($logoPath)) {
            return '';
        }
        return 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath));
    }

    private function directorio(int $tenantId): string
    {
        $dir = BASE_PATH . "/storage/empresa_{$tenantId}/ctacte";
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de comprobantes: ' . $dir);
        }
        return $dir;
    }

    private function render(string $template, array $data): string
    {
        extract($data);
        ob_start();
        require BASE_PATH . "/app/views/pdf/{$template}.php";
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

        $bytes = @file_put_contents($path, $dompdf->output());
        if ($bytes === false) {
            throw new RuntimeException('No se pudo guardar el PDF en ' . basename($path) . '.');
        }
    }
}
