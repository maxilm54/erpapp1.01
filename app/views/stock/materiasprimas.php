<h1>Stock de Materias Primas</h1>
<div class="table-scroll mt-3">
    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th>Materia Prima</th>
                <th>Unidad</th>
                <th class="text-end">Stock Disponible</th>
                <th class="text-end">Stock Reservado</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stock as $mp): 
                $stock_val=(float)$mp['stock'];
                $reserva=(float)($mp['stockreserva']?:0);
                $total=$stock_val + $reserva;
            ?>
            <tr>
                <td><?= htmlspecialchars($mp['nombre']) ?></td>
                <td><?= htmlspecialchars($mp['unidad_medida']) ?></td>
                <td class="text-end"><?= number_format($stock_val, 1,',', '.') ?></td>
                <td class="text-end"><?= number_format($reserva, 1,',', '.') ?></td>
                <td class="text-end"><?= number_format($total,1,',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<hr>
<a href="<?= BASE_URL ?>/ajustesstock" class="btn btn-primary">Volver</a>