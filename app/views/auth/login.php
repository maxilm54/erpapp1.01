
      <form  method="POST" class="card-body">
        <h3>Ingresar a tu cuenta</h3>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
        <input name="email" type="email" placeholder="Email" required />
        <input name="password" type="password" placeholder="Password" required />
        <button type="submit">Ingresar</button>
        <?php
          if (isset($error)) {
            echo '<div class="alert alert-danger"> <i class="bi bi-exclamation-triangle"></i> ' . $_SESSION['error'] . '</div>';
            $_SESSION['error'] = null;
          }
        ?>
      </form>
    </div>
  </body>
</html>
