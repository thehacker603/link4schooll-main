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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | Link4School</title>
  <style>
    /* ------------------- VARIABILI ------------------- */
    :root {
      --bg: linear-gradient(135deg, #0f172a, #1e293b);
      --glass-bg: rgba(255, 255, 255, 0.08);
      --glass-border: rgba(255, 255, 255, 0.2);
      --glass-blur: 18px;

      --accent: #5d79ff;
      --accent-2: #21b1ff;

      --text: #f8fafc;
      --muted: #94a3b8;

      --radius: 20px;
      --shadow-soft: 0 12px 48px rgba(0, 0, 0, 0.6);
      --speed: 220ms;
    }

    html, body {
      margin: 0;
      padding: 0;
      font-family: "Inter", system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    /* Decorazioni */
    body::before, body::after {
      content: "";
      position: absolute;
      border-radius: 50%;
      filter: blur(120px);
      opacity: 0.25;
      z-index: 0;
    }
    body::before {
      width: 350px; height: 350px;
      background: var(--accent);
      top: -120px; left: -100px;
    }
    body::after {
      width: 400px; height: 400px;
      background: var(--accent-2);
      bottom: -150px; right: -120px;
    }

    /* ------------------- CARD LOGIN ------------------- */
    .login-card {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 420px;
      padding: 2.5rem;
      border-radius: var(--radius);
      background: var(--glass-bg);
      backdrop-filter: blur(var(--glass-blur)) saturate(1.2);
      -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(1.2);
      border: 1px solid var(--glass-border);
      box-shadow: var(--shadow-soft);
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
      animation: fadeIn 0.7s ease-out;
    }

    .login-card h1 {
      font-size: 2rem;
      font-weight: 900;
      text-align: center;
      background: linear-gradient(90deg, var(--accent), var(--accent-2));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      margin-bottom: 0.5rem;
    }

    .login-card p {
      text-align: center;
      color: var(--muted);
      font-size: 1rem;
      margin-bottom: 1rem;
    }

    /* ------------------- FORM ------------------- */
    form {
      display: grid;
      gap: 1.2rem;
    }

    .input-group {
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
    }

    .input-group label {
      font-weight: 600;
      font-size: 0.9rem;
      color: var(--muted);
    }

    .input-group input {
      padding: 0.85rem 1rem;
      border-radius: 12px;
      border: 1px solid var(--glass-border);
      background: rgba(255, 255, 255, 0.05);
      color: var(--text);
      font-size: 1rem;
      transition: border-color var(--speed), box-shadow var(--speed);
    }

    .input-group input:focus {
      border-color: var(--accent);
      outline: none;
      box-shadow: 0 0 0 2px rgba(93,121,255,0.3);
    }

    /* ------------------- BUTTON ------------------- */
    .btn {
      padding: 0.9rem 1.5rem;
      border-radius: 14px;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      border: none;
      color: #fff;
      font-weight: 700;
      font-size: 1rem;
      cursor: pointer;
      transition: transform var(--speed), box-shadow var(--speed);
      box-shadow: 0 4px 20px rgba(33,177,255,0.4);
    }

    .btn:hover {
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 8px 28px rgba(33,177,255,0.6);
    }

    /* ------------------- FOOTER ------------------- */
    .footer-links {
      display: flex;
      justify-content: space-between;
      font-size: 0.9rem;
      margin-top: 0.5rem;
    }

    .footer-links a {
      color: var(--accent-2);
      font-weight: 600;
      text-decoration: none;
      transition: color var(--speed);
    }

    .footer-links a:hover {
      color: #fff;
    }

    /* Animazione */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ------------------- RESPONSIVE ------------------- */
    @media (max-width: 500px) {
      .login-card {
        margin: 0 1rem;
        padding: 2rem 1.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="login-card">
    <h1>Benvenuto</h1>
    <p>Accedi al tuo account per continuare</p>

    <form action="login.php" method="POST">
      <div class="input-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="Inserisci username" required>
      </div>

      <div class="input-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Inserisci password" required>
      </div>

      <button type="submit" class="btn">Accedi</button>
    </form>

    <div class="footer-links">
      <a href="#">Password dimenticata?</a>
      <a href="#">Registrati</a>
    </div>
  </div>
</body>
</html>


