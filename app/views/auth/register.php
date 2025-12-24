<form method="POST" class="col-md-6 mx-auto card p-4 shadow">
    <h3 class="mb-3">Registro</h3>

    <input type="hidden" name="csrf" value="<?= $csrf ?>">

    <input class="form-control mb-2" name="nombre" placeholder="Nombre" required>
    <input class="form-control mb-2" name="email" type="email" placeholder="Email" required>
    <input class="form-control mb-2" name="password" type="password" placeholder="Password" required>

    <button class="btn btn-primary w-100">Registrarse</button>
</form>