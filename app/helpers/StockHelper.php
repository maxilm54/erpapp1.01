<?php
class StockHelper
{
    public static function estado($actual, $minimo, $critico, $maximo)
    {
        if ($actual <= $critico) {
            return ['color' => 'danger', 'texto' => 'CRÍTICO'];
        }

        if ($actual <= $minimo) {
            return ['color' => 'warning', 'texto' => 'BAJO'];
        }

        if ($actual >= $maximo && $maximo > 0) {
            return ['color' => 'primary', 'texto' => 'SOBRE STOCK'];
        }

        return ['color' => 'success', 'texto' => 'NORMAL'];
    }
}
