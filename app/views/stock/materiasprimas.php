<h1>Stock de Materias Primas</h1>

<form method="GET" class="row mb-3">
    <div class="col-md-4">
        <select name="deposito_id" class="form-select" onchange="this.form.submit()">
            <option value="">Todos los depósitos</option>
            <?php foreach ($depositos as $d): ?>
            <option value="<?= $d['id'] ?>" <?= ($deposito_id == $d['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['nombre']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="table-scroll mt-3">
    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th>Materia Prima</th>
                <th>Unidad</th>
                <th class="text-end">Stock Disponible</th>
                <th class="text-end">Stock Reservado</th>
                <th class="text-end">Total</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stock as $mp): 
                $stock_val=(float)$mp['stock'];
                $reserva=(float)($mp['stockreserva']??0);
                $total=$stock_val + $reserva;
                $badgeClass = match($mp['estado'] ?? 'normal') {
                    'critico' => 'bg-danger',
                    'bajo' => 'bg-warning text-dark',
                    'alto' => 'bg-primary',
                    default => 'bg-success'
                };
            ?>
            <tr>
                <td><?= htmlspecialchars($mp['nombre']) ?></td>
                <td><?= htmlspecialchars($mp['unidad_medida']) ?></td>
                <td class="text-end"><?= number_format($stock_val, 1,',', '.') ?></td>
                <td class="text-end"><?= number_format($reserva, 1,',', '.') ?></td>
                <td class="text-end"><?= number_format($total,1,',', '.') ?></td>
                <td class="text-center">
                    <span class="badge <?= $badgeClass ?>"><?= strtoupper($mp['estado'] ?? 'NORMAL') ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<hr>
<a href="<?= BASE_URL ?>/ajustesstock" class="btn btn-primary">Volver</a>