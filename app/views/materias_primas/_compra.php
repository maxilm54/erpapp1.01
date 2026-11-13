<?php
/**
 * Unidad de compra y equivalencia con la unidad de stock.
 * Lo incluyen form.php (alta) y edit.php (edición).
 *
 * Espera: $item (array de la MP o vacío) y $umedida (listado de unidades).
 *
 * El factor se guarda canónico: unidades de STOCK por 1 unidad de COMPRA.
 * El formulario permite cargarlo en las dos direcciones y el servidor
 * recalcula el canónico a partir de factor_modo + factor_valor.
 */
$item = $item ?? [];
$idUnidadStock = (int)($item['id_unidadmedida'] ?? 0);
$idUnidadCompra = (int)($item['id_unidadcompra'] ?? 0);
$factorCanonico = (float)($item['factor_conversion'] ?? 1);
if ($factorCanonico <= 0) {
    $factorCanonico = 1;
}
$usaCompra = $idUnidadCompra > 0 && $idUnidadCompra !== $idUnidadStock;
$idUnidadCompraSelect = $usaCompra ? $idUnidadCompra : 0;
$factorInicial = $usaCompra ? $factorCanonico : 1;
?>

<div class="card border-secondary mt-3 mb-3">
    <div class="card-header bg-secondary-subtle py-2">
        <strong><i class="bi bi-arrow-left-right"></i> Compra y conversión</strong>
        <small class="text-muted">— opcional, si el proveedor vende en otra unidad</small>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-5">
                <label class="form-label" for="idUnidadCompra">Unidad de compra</label>
                <select name="id_unidadcompra" id="idUnidadCompra" class="form-select">
                    <option value="">Igual a la unidad de medida</option>
                    <?php foreach ($umedida as $um): ?>
                        <option value="<?= (int)$um['id_medida'] ?>"
                                data-nombre="<?= htmlspecialchars($um['nombre']) ?>"
                                <?= $idUnidadCompraSelect === (int)$um['id_medida'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($um['nombre']) ?> (<?= htmlspecialchars($um['detalle']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">
                    Unidad en la que el proveedor vende la MP (kg, barra, rollo, bolsa...).
                    El stock y las recetas siguen en la unidad de medida.
                    No aplica a MPs con control de N° de serie.
                </div>
            </div>

            <div class="col-md-7">
                <label class="form-label">Equivalencia</label>
                <div id="equivalenciaCompra" class="border rounded p-2 bg-light">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">1</span>
                        <span class="input-group-text" id="factorOrigen">—</span>
                        <span class="input-group-text">=</span>
                        <input type="number" step="0.000001" min="0.000001" name="factor_valor" id="factorValor"
                               class="form-control" value="<?= $usaCompra ? htmlspecialchars((string)$factorInicial) : '' ?>">
                        <span class="input-group-text" id="factorDestino">—</span>
                    </div>
                    <select name="factor_modo" id="factorModo" class="form-select form-select-sm mt-2">
                        <option value="compra_a_stock">1 unidad de compra = X unidades de stock</option>
                        <option value="stock_a_compra">1 unidad de stock = X unidades de compra</option>
                    </select>
                    <div class="form-text" id="factorResumen"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const stockSelect = document.getElementById('idUnidadMedida');
    const compraSelect = document.getElementById('idUnidadCompra');
    const modoSelect = document.getElementById('factorModo');
    const valorInput = document.getElementById('factorValor');
    const origen = document.getElementById('factorOrigen');
    const destino = document.getElementById('factorDestino');
    const resumen = document.getElementById('factorResumen');
    const caja = document.getElementById('equivalenciaCompra');

    if (!stockSelect || !compraSelect || !modoSelect || !valorInput) return;

    let factorCanonico = <?= json_encode($usaCompra ? $factorCanonico : 0) ?>;
    let ultimaCompra = compraSelect.value;
    let ultimaStock = stockSelect.value;

    const nombreUnidad = function (select) {
        const opt = select.options[select.selectedIndex];
        return (opt && opt.dataset.nombre) ? opt.dataset.nombre : 'unidad';
    };
    const unidadStock = function () { return nombreUnidad(stockSelect); };
    const unidadCompra = function () {
        return compraSelect.value ? nombreUnidad(compraSelect) : unidadStock();
    };
    const hayCompra = function () {
        return compraSelect.value !== '' && compraSelect.value !== stockSelect.value;
    };
    const aNumero = function (valor) {
        const n = parseFloat(String(valor).replace(',', '.'));
        return isFinite(n) && n > 0 ? n : 0;
    };
    const formatear = function (n) {
        if (!isFinite(n) || n <= 0) return '';
        return (Math.round(n * 1000000) / 1000000).toString().replace('.', ',');
    };
    const numeroInput = function (n) {
        if (!isFinite(n) || n <= 0) return '';
        return (Math.round(n * 1000000) / 1000000).toString();
    };

    function actualizarResumen() {
        if (!hayCompra()) {
            resumen.textContent = 'La compra se registra en la misma unidad que el stock.';
            return;
        }
        const n = aNumero(valorInput.value);
        if (!n) {
            resumen.textContent = 'Ingrese la equivalencia entre las dos unidades.';
            return;
        }
        const canon = modoSelect.value === 'compra_a_stock' ? n : 1 / n;
        resumen.textContent = '1 ' + unidadCompra() + ' = ' + formatear(canon) + ' ' + unidadStock()
            + ' · 1 ' + unidadStock() + ' = ' + formatear(1 / canon) + ' ' + unidadCompra();
    }

    function pintar() {
        if (!hayCompra()) {
            caja.classList.add('opacity-50');
            valorInput.disabled = true;
            modoSelect.disabled = true;
            origen.textContent = '—';
            destino.textContent = '—';
            valorInput.value = '';
            factorCanonico = 0;
            actualizarResumen();
            return;
        }

        caja.classList.remove('opacity-50');
        valorInput.disabled = false;
        modoSelect.disabled = false;

        const esCompraAStock = modoSelect.value === 'compra_a_stock';
        origen.textContent = esCompraAStock ? unidadCompra() : unidadStock();
        destino.textContent = esCompraAStock ? unidadStock() : unidadCompra();

        if (factorCanonico > 0) {
            valorInput.value = esCompraAStock ? numeroInput(factorCanonico) : numeroInput(1 / factorCanonico);
        }
        actualizarResumen();
    }

    stockSelect.addEventListener('change', function () {
        if (compraSelect.value === stockSelect.value) {
            compraSelect.value = '';
        }
        if (stockSelect.value !== ultimaStock) {
            factorCanonico = 0;
            valorInput.value = '';
        }
        ultimaStock = stockSelect.value;
        pintar();
    });

    compraSelect.addEventListener('change', function () {
        if (compraSelect.value === stockSelect.value) {
            compraSelect.value = '';
        }
        if (compraSelect.value !== ultimaCompra) {
            factorCanonico = 0;
            valorInput.value = '';
        }
        if (!hayCompra()) {
            factorCanonico = 0;
        }
        ultimaCompra = compraSelect.value;
        pintar();
    });

    modoSelect.addEventListener('change', function () {
        const n = aNumero(valorInput.value);
        if (n > 0) {
            factorCanonico = modoSelect.value === 'compra_a_stock' ? n : 1 / n;
        }
        pintar();
    });

    valorInput.addEventListener('input', function () {
        const n = aNumero(valorInput.value);
        factorCanonico = n > 0 ? (modoSelect.value === 'compra_a_stock' ? n : 1 / n) : 0;
        actualizarResumen();
    });

    pintar();
})();
</script>
