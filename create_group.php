<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['group_name']) && isset($_POST['group_type'])) {
        $group_name = trim($_POST['group_name']);
        $group_type = trim($_POST['group_type']); // 'public' o 'private'

        // Inserisci il gruppo nel database
        $stmt = $conn->prepare("INSERT INTO groups (nome, type, creator_id) VALUES (?, ?, ?)");
        if (!$stmt) {
            die("Errore nella preparazione della query: " . $conn->error);
        }

        $stmt->bind_param("ssi", $group_name, $group_type, $user_id);

        if ($stmt->execute()) {
            $group_id = $stmt->insert_id;

            // Aggiungi il creatore come membro e capo del gruppo
            $stmt_add_creator = $conn->prepare("INSERT INTO group_members (group_id, user_id, is_leader) VALUES (?, ?, 1)");
            if (!$stmt_add_creator) {
                die("Errore nella query SQL (aggiunta creatore): " . $conn->error);
            }
            $stmt_add_creator->bind_param("ii", $group_id, $user_id);
            $stmt_add_creator->execute();

            $_SESSION['create_group_message'] = "Gruppo creato con successo!";
        } else {
            $_SESSION['create_group_message'] = "Si è verificato un errore durante la creazione del gruppo: " . $stmt->error;
        }
    } else {
        $_SESSION['create_group_message'] = "Nome del gruppo o tipo non fornito.";
    }

    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<link rel="stylesheet" href="style.css">
  <meta charset="UTF-8">
  <title>Crea un Gruppo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    /* CSS per il tema chiaro e scuro */
    :root {
      --accent-color:rgb(0, 0, 0);
      --bg-light: #ffffff;
      --text-light: #000000;
      --card-light: #f9f9f9;

      --bg-dark: #1e1e1e;
      --text-dark: #ffffff;
      --card-dark: #2c2c2c;

      --border-radius: 8px;
      --transition: 0.3s ease;
    }

    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background-color: var(--bg-light);
      color: var(--text-light);
      transition: var(--transition);
    }

    body.dark {
      background-color: var(--bg-dark);
      color: var(--text-dark);
    }

    .title {
      text-align: center;
      margin-top: 2rem;
      font-size: 2rem;
    }

    .form-container {
      max-width: 500px;
      margin: 2rem auto;
      background-color: var(--card-light);
      padding: 2rem;
      border-radius: var(--border-radius);
      transition: var(--transition);
    }

    body.dark .form-container {
      background-color: var(--card-dark);
    }

    label {
      display: block;
      margin-top: 1rem;
      font-weight: bold;
    }

    input[type="text"],
    select {
      width: 100%;
      padding: 0.6rem;
      margin-top: 0.5rem;
      border: 1px solid #ccc;
      border-radius: var(--border-radius);
      background: inherit;
      color: inherit;
    }

    button[type="submit"] {
      margin-top: 1.5rem;
      background-color: var(--accent-color);
      color: white;
      border: none;
      padding: 0.7rem 1.5rem;
      border-radius: var(--border-radius);
      cursor: pointer;
      font-weight: bold;
    }

    .center {
      text-align: center;
      margin-top: 2rem;
    }

    .btn {
      text-decoration: none;
      padding: 0.6rem 1rem;
      border-radius: var(--border-radius);
      background-color: var(--accent-color);
      color: white;
      font-weight: bold;
    }

    .theme-toggle {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
    }

    .message {
      color: green;
      text-align: center;
      margin-bottom: 1rem;
    }
    .create_button{
      display: block;
    margin: 1.5rem auto 0; /* Centra il bottone orizzontalmente e aggiunge margine superiore */
    background-color: var(--accent-color);
    color: white;
    border: none;
    padding: 0.7rem 1.5rem;
    border-radius: var(--border-radius);
    cursor: pointer;
    font-weight: bold;
    text-align: center;
    }
  </style>
</head>
<body class="light">
  <button class="theme-toggle" id="themeToggle" title="Cambia tema">🌙</button>

  <h1 class="title">Crea un nuovo gruppo</h1>

  <div class="form-container">
    <?php if (isset($_SESSION['create_group_message'])) {
      echo "<p class='message'>" . $_SESSION['create_group_message'] . "</p>";
      unset($_SESSION['create_group_message']);
    } ?>

    <form method="POST">
      <label for="group_name">Nome del Gruppo:</label>
      <input type="text" name="group_name" id="group_name" required>

      <label for="group_type">Tipo di Gruppo:</label>
      <select name="group_type" id="group_type" required>
        <option value="public">Pubblico</option>
        <option value="private">Privato</option>
      </select>

      <button class="create_button" type="submit">Crea Gruppo</button>
    </form>

    <div class="center">
      <a href="dashboard.php" class="btn">Torna alla tua dashboard</a>
    </div>
  </div>

  <script>
    const toggle = document.getElementById('themeToggle');
    const body = document.body;

    toggle.addEventListener('click', () => {
      body.classList.toggle('dark');
      body.classList.toggle('light');
      toggle.textContent = body.classList.contains('dark') ? '☀️' : '🌙';
    });
    const toggle = document.getElementById('themeToggle');
  const body = document.body;

  // Applica il tema salvato
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme) {
    body.classList.add(savedTheme);
    toggle.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
  } else {
    body.classList.add('light'); // Tema predefinito
  }

  // Cambia tema al clic
  toggle.addEventListener('click', () => {
    const isDark = body.classList.contains('dark');
    body.classList.toggle('dark', !isDark);
    body.classList.toggle('light', isDark);
    toggle.textContent = isDark ? '🌙' : '☀️';
    localStorage.setItem('theme', isDark ? 'light' : 'dark'); // Salva il tema scelto
  });
  </script>
</body>
</html>
