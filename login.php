<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include 'config.php';

    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Password errata.";
        }
    } else {
        $error = "Utente non trovato.";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Link4Class - Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    body {
      background-color: var(--bg-color);
      color: var(--text-color);
      transition: var(--transition);
    }

    .form-container {
      max-width: 400px;
      margin: 6rem auto;
      background: var(--card-bg);
      padding: 3rem 2rem;
      border-radius: var(--border-radius);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
      position: relative;
      transition: var(--transition);
    }

    #theme-toggle {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: transparent;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: var(--text-color);
      transition: var(--transition);
    }

    .form-container h1 {
      text-align: center;
      font-size: 2rem;
      font-weight: 800;
      margin-bottom: 1rem;
    }

    form {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .input-group {
      display: flex;
      flex-direction: column;
    }

    .input-group label {
      margin-bottom: 0.5rem;
      font-weight: 600;
    }

    input[type="text"],
    input[type="password"] {
      padding: 1rem;
      border: 1px solid var(--accent-color);
      background: transparent;
      border-radius: var(--border-radius);
      font-size: 1rem;
      color: var(--text-color);
      transition: var(--transition);
    }

    input:focus {
      border-color: var(--accent-color);
      outline: none;
    }

    button[type="submit"] {
      margin-top: 1rem;
    }

    .error {
      margin-top: 1rem;
      color: red;
      text-align: center;
    }

    .cyber-footer {
      text-align: center;
      margin-top: 2rem;
    }

    .nav-btn {
      display: inline-block;
      margin: 0 1rem;
      color: var(--accent-color);
      text-decoration: none;
      font-weight: 600;
      transition: var(--transition);
    }

    .nav-btn:hover {
      color: #111;
    }

    body.dark-theme .nav-btn:hover {
      color: var(--text-color);
    }

    footer {
      margin-top: 2rem;
      text-align: center;
      padding: 0;
    }
  </style>
</head>
<body>

  <div class="form-container">
    <button id="theme-toggle">🌓</button>

    <h1>Login</h1>
    <form method="POST">
      <div class="input-group">
        <label for="login-username">Username</label>
        <input type="text" id="login-username" name="username" placeholder="Inserisci il tuo username" required>
      </div>
      <div class="input-group">
        <label for="login-password">Password</label>
        <input type="password" id="login-password" name="password" placeholder="Inserisci la password" required>
        <button type="button" id="toggle-password" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: transparent; border: none; cursor: pointer;"></button>
      </div>
      <button class="button" type="submit">Accedi</button>
      <?php if(isset($error)): ?>
        <p class="error"><?php echo $error; ?></p>
      <?php endif; ?>
    </form>

    <footer class="cyber-footer">
      <a href="index.php" class="nav-btn">Home</a>
      <a href="register.php" class="nav-btn">Registrati</a>
      <a href="send_reset.php" class="nav-btn">Password dimenticata?</a>
    </footer>
  </div>

  <!-- Script per il tema -->
  <script>
    const toggleBtn = document.getElementById('theme-toggle');

    // Applica il tema salvato
    if (localStorage.getItem('theme') === 'dark') {
      document.body.classList.add('dark-theme');
    }

    // Imposta l’icona all’avvio
    toggleBtn.textContent = document.body.classList.contains('dark-theme') ? "☀️" : "🌓";

    toggleBtn.addEventListener('click', () => {
      document.body.classList.toggle('dark-theme');
      const isDark = document.body.classList.contains('dark-theme');
      localStorage.setItem('theme', isDark ? 'dark' : 'light');
      toggleBtn.textContent = isDark ? "☀️" : "🌓";
    });
    const passwordInput = document.getElementById('login-password');
  const togglePasswordBtn = document.getElementById('toggle-password');

  togglePasswordBtn.addEventListener('click', () => {
    const isPasswordVisible = passwordInput.type === 'text';
    passwordInput.type = isPasswordVisible ? 'password' : 'text';
    togglePasswordBtn.textContent = isPasswordVisible ? '👁️' : '🙈';
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

