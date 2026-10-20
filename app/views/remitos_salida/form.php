<h1>Nuevo Remito</h1>

<p><strong>Cliente:</strong> <?= htmlspecialchars($np['cliente_nombre']) ?></p>

<form method="post">
<div class="mb-3">
    <label class="form-label">Depósito de origen</label>
    <select name="deposito_id" id="deposito_origen" class="form-select" required>
        <option value="">Seleccionar depósito...</option>
        <?php foreach ($depositos as $d): ?>
        <option value="<?= $d['id'] ?>" data-nombre="<?= htmlspecialchars($d['nombre']) ?>"><?= htmlspecialchars($d['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
    <div id="alertaDeposito" class="alert alert-warning mt-2 d-none">
        <i class="bi bi-arrow-left-right"></i> Some de los productos tienen stock repartido entre varios depósitos.
        Puede <a href="javascript:void(0)" onclick="irATransferencias(event)">realizar una transferencia</a> para concentrar el stock en el depósito seleccionado y poder remitar.
    </div>
</div>
<div class="table-scroll mt-3">
    <table class="table" id="tablaRemito">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Pedida</th>
                <th>Precio U</th>
                <th>Precio Subtotal</th>
                <th>Remitida</th>
                <th>Pendiente</th>
                <th>Disponible</th>
                <th>Disponible en Depósito</th>
                <th width="140">Remitir ahora</th>
            </tr>
        </thead>
        <tbody>
            <?php $indice=0; foreach ($np['detalle'] as $item): ?>
                <?php if ($item['pendiente'] <= 0) continue; ?>
<?php
                    $u = strtoupper($item['unidad_nombre'] ?? '');
                    $esUnidadEntera = in_array($u, ['U','UN','UNIDAD','UNIDADES']);
                    $step = $esUnidadEntera ? '1' : '0.01';
                    $esSerie = !empty($item['usa_serie']);
                ?>
                <tr data-producto="<?= $item['producto_id'] ?>" data-serie="<?= $esSerie ? '1' : '0' ?>">
                    <td>
                        <?= htmlspecialchars($item['nombre']) ?> <small class="text-muted">(<?= $u ?>)</small>
                        <?php if ($esSerie): ?><span class="badge bg-danger">SERIE</span><?php endif; ?>
                    </td>
                    <td><?= number_format($item['pedida'], 3, ',', '.') ?></td>
                    <td>$ <?= number_format($item['precio'], 2, ',', '.') ?></td>
                    <td>$ <?= number_format($item['total_linea'], 2, ',', '.') ?></td>
                    <td><?= number_format($item['remitida'], 3, ',', '.') ?></td>
                    <td><?= number_format($item['pendiente'], 3, ',', '.') ?></td>
                    <td class="stock-total"><?= number_format($item['stock_disponible'] ?? 0, 3, ',', '.') ?></td>
                    <td class="stock-deposito">—</td>
                    <td>
                        <?php if ($esSerie): ?>
                        <!-- Producto con serie: selector múltiple de series disponibles -->
                        <select name="items[<?= $item['producto_id'] ?>][series][]" 
                                class="form-select form-select-sm series-select" 
                                multiple size="3"
                                data-producto="<?= $item['producto_id'] ?>"
                                data-pendiente="<?= (int)$item['pendiente'] ?>"></select>
                        <small class="text-muted series-info">Seleccione el depósito para cargar series</small>
                        <input type="hidden" name="items[<?= $item['producto_id'] ?>][cantidad]" value="0" class="qty-serie-hidden">
                        <?php else: ?>
                        <input type="number"
                            step="<?= $step ?>"
                            min="0"
                            max="<?= $item['pendiente'] ?>"
                            name="items[<?= $item['producto_id'] ?>][cantidad]"
                            class="form-control qty-input" required>
                        <?php endif; ?>
                        <input type="hidden" name="items[<?= $item['producto_id'] ?>][deposito_id]" value="" class="deposito-item">
                    </td>
                </tr>
            <?php $indice++; endforeach; ?>
        </tbody>
    </table>
</div>

<div class="mb-3">
    <label>Observaciones</label>
    <textarea name="observaciones" class="form-control"></textarea>
</div>

<button class="btn btn-success">Confirmar Remito</button>
<a href="<?= BASE_URL ?>/notaspedido/show/<?= $np['id'] ?>" class="btn btn-secondary">Ver NP</a>

</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const depSelect = document.getElementById('deposito_origen');
    const itemDepInputs = document.querySelectorAll('.deposito-item');
    const alertaDeposito = document.getElementById('alertaDeposito');
    const rows = document.querySelectorAll('#tablaRemito tbody tr[data-producto]');

    function obtenerStockDeposito(productoId, depositoId) {
        return fetch('<?= BASE_URL ?>/remitossalida/stock-deposito?producto_id=' + productoId + '&deposito_id=' + (depositoId || 0))
            .then(r => r.json())
            .catch(() => ({ stock: 0 }));
    }

    function cargarSeries(row, depositoId) {
        const productoId = row.dataset.producto;
        const select = row.querySelector('.series-select');
        const info = row.querySelector('.series-info');
        if (!select) return;
        select.innerHTML = '';
        if (!depositoId) {
            info.textContent = 'Seleccione el depósito para cargar series';
            return;
        }
        fetch('<?= BASE_URL ?>/remitossalida/seriesDisponibles?producto_id=' + productoId + '&deposito_id=' + depositoId)
            .then(r => r.json())
            .then(series => {
                if (!series.length) {
                    info.textContent = 'No hay series disponibles en este depósito';
                    return;
                }
                info.textContent = series.length + ' serie(s) disponible(s) — seleccione las que remitirá';
                series.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.numero_serie + (s.codigo_lote ? ' (Lote: ' + s.codigo_lote + ')' : '');
                    select.appendChild(opt);
                });
            })
            .catch(() => { info.textContent = 'Error cargando series'; });
    }

    // Actualizar cantidad hidden según series seleccionadas
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('series-select')) {
            const row = e.target.closest('tr');
            const cantidad = e.target.selectedOptions.length;
            row.querySelector('.qty-serie-hidden').value = cantidad;
        }
    });

    async function actualizarStocks() {
        const depositoId = depSelect.value;
        itemDepInputs.forEach(inp => inp.value = depositoId);

        let hayParcial = false;

        for (const row of rows) {
            const productoId = row.dataset.producto;
            const esSerie = row.dataset.serie === '1';
            const stockDepCell = row.querySelector('.stock-deposito');

            if (esSerie) {
                // Fila con serie: cargar series del depósito
                cargarSeries(row, depositoId);
                stockDepCell.textContent = depositoId ? '—' : '—';
                continue;
            }

            const qtyInput = row.querySelector('.qty-input');
            const pendiente = parseFloat(qtyInput.max) || 0;

            if (!depositoId) {
                stockDepCell.textContent = '—';
                qtyInput.max = pendiente;
                continue;
            }

            const data = await obtenerStockDeposito(productoId, depositoId);
            const stockEnDeposito = data.stock;

            stockDepCell.textContent = numberFormat(stockEnDeposito);

            const maxRemitir = Math.min(pendiente, stockEnDeposito);
            qtyInput.max = maxRemitir;
            if (parseFloat(qtyInput.value || 0) > maxRemitir) {
                qtyInput.value = maxRemitir > 0 ? maxRemitir : '';
            }

            if (stockEnDeposito < pendiente) {
                hayParcial = true;
            }
        }

        if (hayParcial) {
            alertaDeposito.classList.remove('d-none');
        } else {
            alertaDeposito.classList.add('d-none');
        }
    }

    depSelect.addEventListener('change', actualizarStocks);

    function numberFormat(val) {
        return Number(val).toLocaleString('es-AR', { maximumFractionDigits: 3 });
    }

    window.irATransferencias = function(e) {
        e.preventDefault();
        const depositoId = depSelect.value;
        const url = '<?= BASE_URL ?>/transferencias/create' + (depositoId ? '?origen=' + depositoId : '');
        Swal.fire({
            title: '¿Realizar transferencia?',
            text: 'Para remitar desde este depósito, los productos deben tener stock suficiente. Lo redirigimos al módulo de transferencias.',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Ir a Transferencias'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    };

    // Validar antes de enviar: productos con serie deben tener al menos una serie seleccionada
    document.querySelector('form').addEventListener('submit', function(e) {
        let faltantes = 0;
        document.querySelectorAll('.series-select').forEach(sel => {
            if (sel.selectedOptions.length === 0) {
                faltantes++;
                sel.classList.add('is-invalid');
            } else {
                sel.classList.remove('is-invalid');
            }
        });
        if (faltantes > 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Series requeridas',
                text: 'Debe seleccionar al menos una serie disponible para cada producto con control de serie.'
            });
        }
    });
});
</script>