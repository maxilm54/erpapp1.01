
<?php
$error =$_SESSION['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/jpg" href="<?= BASE_URL ?>/logoempresa.jpg?v=<?= time() ?>">
    <title>ERP - DmTech</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
      integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css" />
    <style>
      body {
        height: 100vh;
        margin: 0;
        display: grid;
        place-items: center;
        background: #040914; /* Cielo azul oscuro profundo */
        color: #e2e8f0;
        font-family: "Poppins", sans-serif;
        overflow: hidden;
        position: relative;
      }

      /* Canvas para el fondo de estrellas y texto acumulado */
      #starCanvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
      }

      :root {
        --color-primary: #1e40af;
        --color-border: rgba(148, 163, 184, 0.3);
      }

      * {
        box-sizing: border-box;
      }

      /* Tarjeta con efecto Gris Metalizado */
      .card {
        position: relative;
        z-index: 10;
        display: flex;
        flex-direction: column;
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.85) 0%, rgba(15, 23, 42, 0.90) 100%);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-radius: 22px;
        padding: 20px;
        width: clamp(300px, 85vw, 420px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.6), 
                    inset 0 1px 0 rgba(255, 255, 255, 0.15),
                    0 0 20px rgba(56, 189, 248, 0.15); /* Brillo sutil exterior */
        border: 1px solid rgba(148, 163, 184, 0.25);
      }

      form {
        padding: 30px 18px;
        display: flex;
        flex-direction: column;
        gap: 16px;
      }

      form h3 {
        text-align: center;
        font-weight: 600;
        color: #f8fafc;
        font-size: 24px;
        margin-bottom: 10px;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
      }

      button,
      input {
        height: 48px;
        border-radius: 10px;
        font-family: inherit;
        font-size: 15px;
        width: 100%;
        outline: none;
      }

      /* Inputs con estilo Gris Metalizado Oscuro */
      input {
        background: rgba(15, 23, 42, 0.7);
        border: 1px solid var(--color-border);
        color: #f1f5f9;
        padding: 0 16px;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.3);
        transition: all 0.3s ease;
      }

      input:focus {
        border-color: #38bdf8;
        background: rgba(15, 23, 42, 0.9);
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.4), 
                    0 0 12px rgba(56, 189, 248, 0.4); /* Brillo celeste metálico al escribir */
      }

      input::placeholder {
        color: #64748b;
      }

      /* Botón con estilo Azul Metalizado Brillante */
      form > button[type="submit"] {
        color: #ffffff;
        border: 1px solid rgba(96, 165, 250, 0.4);
        background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 50%, #1e40af 100%);
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(29, 78, 216, 0.5),
                    inset 0 1px 0 rgba(255, 255, 255, 0.3);
        transition: all 0.3s ease;
      }

      form > button[type="submit"]:hover {
        background: linear-gradient(135deg, #2563eb 0%, #60a5fa 50%, #1d4ed8 100%);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.7),
                    inset 0 1px 0 rgba(255, 255, 255, 0.4);
        transform: translateY(-1px);
      }

      .alert {
        font-size: 14px;
        padding: 10px;
        border-radius: 8px;
        background: rgba(127, 29, 29, 0.8);
        border: 1px solid rgba(248, 113, 113, 0.3);
        color: #fca5a5;
      }
    </style>
  </head>
  <body>