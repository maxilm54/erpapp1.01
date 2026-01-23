<h1>Registrar Pago</h1>

<form method="POST">
    <label>Cliente</label>
    <select name="clienteId" class="form-control" required>
        <?php foreach ($clientes as $c): ?>
            <option value="<?= $c['id'] ?>">
                <?= $c['razon_social'] ?>
            </option>
        <?php endforeach ?>
    </select>

    <br>
    <button class="btn btn-primary">Continuar</button>
</form>