<h1>Ajuste de Stock – Materia Prima</h1>

<form method="post" id="form-ajuste-mp">
    <div class="row">
        <div class="col-6">
            <label>Materia Prima</label>
            <input type="text" id="buscador-mp" class="form-control" autocomplete="off" required>
            <input type="hidden" name="materia_prima_id" id="materia_prima_id">

            <div id="resultados-mp"
                class="list-group position-absolute w-100"
                style="z-index:1000; display:none;">
            </div>
        </div>
        <div class="col-6">
            <label>Tipo</label>
            <select name="tipo" class="form-control" required>
                <option value="ENTRADA">Entrada</option>
                <option value="SALIDA">Salida</option>
            </select>
        </div>
    </div>
    <div class="row">
        <div class="col-4">
            <label class="mt-3">Cantidad</label>
            <input type="number" step="0.01" min="0.01" name="cantidad" class="form-control" required>
        </div>
        <div class="col-4">
            <label class="mt-3">Depósito</label>
            <select name="deposito_id" class="form-control">
                <option value="">Sin depósito</option>
                <?php foreach ($depositos as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-4">
            <label class="mt-3">Lote</label>
            <input type="text" name="codigo_lote" class="form-control" placeholder="N° de lote">
        </div>
    </div>
    <div class="row">
        <div class="col-6">
            <label class="mt-3">Motivo del ajuste</label>
            <input type="text" name="motivo" class="form-control" maxlength="40" required placeholder="Ej: Diferencia inventario, rotura, conteo físico">
        </div>
        <div class="col-6">
            <label class="mt-3">Observaciones</label>
            <textarea name="observaciones" class="form-control"></textarea>
        </div>
    </div>
    <br>
    <button class="btn btn-success">Guardar</button>
    <a href="<?= BASE_URL ?>/ajustesstock" class="btn btn-secondary">Cancelar</a>
</form>

<script>
let seleccionado = null;

function initBuscador(inputId, resultadosId, endpoint) {
    const input = document.getElementById(inputId);
    const resultados = document.getElementById(resultadosId);

    input.addEventListener('input', () => {
        const q = input.value.trim();

        if (q.length < 2) {
            resultados.style.display = 'none';
            return;
        }

        fetch(endpoint + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                resultados.innerHTML = '';

                if (!data.length) {
                    resultados.style.display = 'none';
                    return;
                }

                data.forEach(item => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action';
                    btn.textContent = item.nombre;

                    btn.onclick = () => {
                        seleccionado = item;
                        input.value = item.nombre;
                        resultados.style.display = 'none';
                    };

                    resultados.appendChild(btn);
                });

                resultados.style.display = 'block';
            });
    });
}

initBuscador(
    'buscador-mp',
    'resultados-mp',
    '<?= BASE_URL ?>/materiasprimas/search?q='
);

document.getElementById('form-ajuste-mp').addEventListener('submit', e => {
    if (!seleccionado) {
        e.preventDefault();
        alert('Debe seleccionar una materia prima válida');
        return;
    }
    document.getElementById('materia_prima_id').value = seleccionado.id;
});
</script>