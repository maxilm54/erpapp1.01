<h1>Anular Nota de Pedido</h1>

<form method="post">
    <div class="mb-3">
        <label>Motivo de anulación</label>
        <textarea name="motivo" class="form-control" required></textarea>
    </div>

    <button class="btn btn-danger">Confirmar Anulación</button>
    <a href="<?= BASE_URL ?>/notaspedido/show/<?= $id ?>" class="btn btn-secondary">Cancelar</a>
</form>