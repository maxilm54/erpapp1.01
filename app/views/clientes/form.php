<form method="POST" class="card p-4 col-md-6 mx-auto shadow">
       <h4 class="mb-3"><?= $title ?></h4>

       <input class="form-control mb-2" name="razon_social"
              value="<?= $cliente['razon_social'] ?? '' ?>" required
              placeholder="Razón Social">

       <input class="form-control mb-2" name="cuit"
              value="<?= $cliente['cuit'] ?? '' ?>" required
              placeholder="CUIT">

       <input class="form-control mb-2" name="email"
              value="<?= $cliente['email'] ?? '' ?>" 
              placeholder="Email" required>

       <input class="form-control mb-2" name="telefono"
              value="<?= $cliente['telefono'] ?? '' ?>" 
              placeholder="Teléfono" required>

       <input class="form-control mb-3" name="localidad"
           value="<?= $cliente['localidad'] ?? '' ?>" 
           placeholder="Localidad" required>


       <input class="form-control mb-3" name="direccion"
           value="<?= $cliente['direccion'] ?? '' ?>" 
           placeholder="Dirección" required>

       <input class="form-control mb-3" name="contacto"
           value="<?= $cliente['contacto'] ?? '' ?>" 
           placeholder="Contacto" required>
       <Select class="form-control mb-3" name="es_distribuidor">
              <option value="Si">Es Distruibuidor</option>
              <option value="No">No Es Distruibuidor</option>
       </Select>
    <button class="btn btn-success w-100">Guardar</button>
</form>