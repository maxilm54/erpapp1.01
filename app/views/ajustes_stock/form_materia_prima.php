<h1>Ajuste de Stock – Materia Prima</h1>

<form method="post" id="form-ajuste-mp">

    <label for="materia_prima_id">Materia Prima</label>
    <input type="text" id="buscador-mp" class="form-control" autocomplete="off" required>
    <input type="hidden" name="materia_prima_id" id="materia_prima_id">

    <div id="resultados-mp"
         class="list-group position-absolute w-100"
         style="z-index:1000; display:none;"></div>

    <label class="mt-3" for="tipo">Tipo</label>
    <select name="tipo" class="form-control" required>
        <option value="ENTRADA">Entrada</option>
        <option value="SALIDA">Salida</option>
    </select>

    <label class="mt-3" for="cantidad">Cantidad</label>
    <input type="number" step="0.01" min="0.01"
           name="cantidad" class="form-control" required>
           
    <label class="mt-3" for="motivo">Motivo del ajuste</label>
    <input type="text"
        name="motivo"
        class="form-control"
        maxlength="100"
        required
    placeholder="Ej: Merma, corrección de stock">

    <label class="mt-3" for="observaciones">Observaciones</label>
    <textarea name="observaciones" class="form-control"></textarea>

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