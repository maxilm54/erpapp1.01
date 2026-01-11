<h1>Ajuste de Stock – Producto</h1>

<form method="post" id="form-ajuste-producto">

    <label>Producto</label>
    <input type="text" id="buscador-producto" class="form-control" autocomplete="off" required>
    <input type="hidden" name="producto_id" id="producto_id">

    <div id="resultados-producto"
         class="list-group position-absolute w-100"
         style="z-index:1000; display:none;"></div>

    <label class="mt-3">Tipo</label>
    <select name="tipo" class="form-control" required>
        <option value="ENTRADA">Entrada</option>
        <option value="SALIDA">Salida</option>
        <option value="AJUSTE">Ajuste</option>
    </select>

    <label class="mt-3">Cantidad</label>
    <input type="number" step="0.01" min="0.01"
           name="cantidad" class="form-control" required>

    <label class="mt-3">Motivo del ajuste</label>
    <input type="text"
        name="motivo"
        class="form-control"
        maxlength="100"
        required
    placeholder="Ej: Diferencia inventario, rotura, conteo físico">

    <label class="mt-3">Observaciones</label>
    <textarea name="observaciones" class="form-control"></textarea>

    <br>
    <button class="btn btn-success">Guardar</button>
    <a href="<?= BASE_URL ?>/ajustesstock" class="btn btn-secondary">Cancelar</a>
</form>

<script>
/* ===============================
   BUSCADOR REUTILIZABLE
================================ */
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
                    btn.textContent = `${item.sku} - ${item.nombre}`;

                    btn.onclick = () => {
                        seleccionado = item;
                        input.value = btn.textContent;
                        resultados.style.display = 'none';
                    };

                    resultados.appendChild(btn);
                });

                resultados.style.display = 'block';
            });
    });

    // cerrar al perder foco
    document.addEventListener('click', e => {
        if (!resultados.contains(e.target) && e.target !== input) {
            resultados.style.display = 'none';
        }
    });
}

/* ===============================
   INICIALIZACIÓN
================================ */
initBuscador(
    'buscador-producto',
    'resultados-producto',
    '<?= BASE_URL ?>/productos/search?q='
);

/* ===============================
   VALIDACIÓN SUBMIT
================================ */
document.getElementById('form-ajuste-producto').addEventListener('submit', e => {
    if (!seleccionado) {
        e.preventDefault();
        alert('Debe seleccionar un producto válido');
        return;
    }
    document.getElementById('producto_id').value = seleccionado.id;
});
</script>