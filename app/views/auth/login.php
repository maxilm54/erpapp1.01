
<!-- Fondo interactivo de estrellas y texto -->
    <canvas id="starCanvas"></canvas>

    <div class="card">
      <form method="POST" class="card-body">
        <h3>Iniciar Sesión</h3>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
        <input name="email" type="email" placeholder="Correo electrónico" required autocomplete="email" />
        <input name="password" type="password" placeholder="Contraseña" required autocomplete="current-password" />
        <button type="submit">Ingresar</button>
        <?php
          if (isset($error)) {
            echo '<div class="alert alert-danger mt-2"> <i class="bi bi-exclamation-triangle"></i> ' . $_SESSION['error'] . '</div>';$_SESSION['error'] = null;
          }
        ?>
      </form>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php require_once BASE_PATH.'/app/views/layout/alerts.php'; ?>
    <script src="<?= BASE_URL ?>/js/confirmations.js"></script>

    <!-- Script para la animación de estrellas y formación de texto -->
    <script>
      const canvas = document.getElementById('starCanvas');
      const ctx = canvas.getContext('2d');

      let width, height;
      function resize() {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
      }
      window.addEventListener('resize', resize);
      resize();

      const stars = [];
      const numStars = 120;

      for (let i = 0; i < numStars; i++) {
        stars.push({
          x: Math.random() * width,
          y: Math.random() * height,
          length: Math.random() * 20 + 10,
          speed: Math.random() * 4 + 2,
          opacity: Math.random() * 0.7 + 0.3
        });
      }

      function drawTargetText() {
        ctx.font = "bold 28px 'Poppins', sans-serif";
        ctx.textAlign = "center";
        ctx.textBaseline = "bottom";
        
        // Brillo celeste para que combine con el estilo metálico
        ctx.shadowColor = "rgba(56, 189, 248, 0.8)";
        ctx.shadowBlur = 20;
        ctx.fillStyle = "rgba(241, 245, 249, 0.9)";
        ctx.fillText("DmTech erpcliente 1.0", width / 2, height - 30);
        
        ctx.shadowBlur = 0;
      }

      function animate() {
        ctx.fillStyle = "#040914";
        ctx.fillRect(0, 0, width, height);

        ctx.lineWidth = 1.5;

        for (let i = 0; i < stars.length; i++) {
          let s = stars[i];
          ctx.beginPath();
          ctx.moveTo(s.x, s.y);
          ctx.lineTo(s.x, s.y + s.length);
          ctx.strokeStyle = `rgba(186, 230, 253, ${s.opacity})`;
          ctx.stroke();

          s.y += s.speed;

          if (s.y > height - 50) {
            s.y = -20;
            s.x = Math.random() * width;
          }
        }

        drawTargetText();

        requestAnimationFrame(animate);
      }

      animate();
    </script>
  </body>
</html>