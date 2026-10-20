<?php
require_once BASE_PATH . '/app/core/Controller.php';
require_once BASE_PATH . '/app/models/Lote.php';

class LotesController extends Controller
{
    private Lote $lote;

    public function __construct()
    {
        $this->lote = new Lote();
    }

    public function index(): void
    {
        $dias = isset($_GET['dias']) ? max(1, (int)$_GET['dias']) : 30;
        $vencidos = $this->lote->allExpired();
        $proximos = $this->lote->allExpiringSoon($dias);

        $this->view('lotes/index', [
            'title' => 'Lotes - Vencimientos',
            'vencidos' => $vencidos,
            'proximos' => $proximos,
            'dias' => $dias
        ]);
    }
}