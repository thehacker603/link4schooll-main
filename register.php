<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

include 'config.php';

if ($conn->connect_error) {
    die("Connessione al database fallita: " . $conn->connect_error);
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if ($password !== $password_confirm) {
        $error = "Le password non coincidono.";
    } else {
        // Controllo username
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Username già in uso.";
        } else {
            // Controllo email
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "Email già in uso.";
            } else {
                // Upload immagine profilo
                $user_image_path = null;

                if (isset($_FILES['user_image']) && $_FILES['user_image']['error'] == UPLOAD_ERR_OK) {
                    $target_dir = "uploads/";
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }

                    $file_name = uniqid() . "_" . basename($_FILES["user_image"]["name"]);
                    $target_file = $target_dir . $file_name;

                    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

                    if (in_array($imageFileType, $allowed_types)) {
                        if (move_uploaded_file($_FILES["user_image"]["tmp_name"], $target_file)) {
                            $user_image_path = $target_file;
                        } else {
                            $error = "Errore nel caricamento dell'immagine.";
                        }
                    } else {
                        $error = "Formato immagine non supportato. Usa jpg, png o gif.";
                    }
                }

                if (!$error) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password, user_image) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $username, $email, $hashed_password, $user_image_path);
                    if ($stmt->execute()) {
                        $_SESSION['user_id'] = $conn->insert_id;
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = "Errore durante la registrazione: " . $stmt->error;
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Registrazione</title>
  <style>
    /* Reset base */
    * {
      margin: 0; padding: 0; box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background-color: #0d0d12;
      color: white;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .form-container {
      background: #1a1a1f;
      padding: 3rem 2.5rem;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
      width: 100%;
      max-width: 420px;
    }

    .form-container h1 {
      text-align: center;
      font-size: 2.5rem;
      font-weight: 800;
      margin-bottom: 2rem;
      color: #3d8bff;
    }

    form {
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    input[type="file"] {
      padding: 1rem;
      font-size: 1rem;
      border-radius: 8px;
      border: 1.5px solid #3d8bff;
      background: transparent;
      color: white;
      transition: border-color 0.3s;
    }

    input[type="text"]:focus,
    input[type="email"]:focus,
    input[type="password"]:focus,
    input[type="file"]:focus {
      outline: none;
      border-color: #2d6fd1;
    }

    label {
      font-weight: 600;
      margin-bottom: 0.3rem;
      display: block;
      color: #a0a8c3;
    }

    button[type="submit"] {
      background-color:rgb(70, 70, 70);
      border: none;
      border-radius: 8px;
      padding: 1rem 0;
      font-weight: 700;
      font-size: 1.1rem;
      color: white;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    button[type="submit"]:hover {
      background-color:rgb(107, 107, 107);
    }

    .error {
      margin-top: 1rem;
      background: #ff4c4c;
      padding: 0.8rem 1rem;
      border-radius: 8px;
      text-align: center;
      font-weight: 600;
      color: white;
      box-shadow: 0 0 5px #ff4c4caa;
    }

    .login-link {
      margin-top: 1.5rem;
      text-align: center;
      font-size: 1rem;
      color: #a0a8c3;
    }

    .login-link a {
      color:rgb(0, 98, 179);
      font-weight: 600;
      text-decoration: none;
      transition: color 0.3s;
    }

    .login-link a:hover {
      color:rgb(255, 255, 255);
    }

    @media (max-width: 480px) {
      .form-container {
        padding: 2rem 1.5rem;
      }
      .form-container h1 {
        font-size: 2rem;
      }
    }
  </style>
</head>
<body>

  <div class="form-container">
    <h1>Registrati</h1>
    <form method="POST" enctype="multipart/form-data" novalidate>
      <label for="username">Username</label>
      <input id="username" type="text" name="username" placeholder="Username" required />

      <label for="email">Email</label>
      <input id="email" type="email" name="email" placeholder="Email" required />

      <label for="password">Password</label>
      <input id="password" type="password" name="password" placeholder="Password" required />

      <label for="password_confirm">Conferma Password</label>
      <input id="password_confirm" type="password" name="password_confirm" placeholder="Conferma Password" required />

      <label for="user_image">Immagine Profilo (opzionale)</label>
      <input id="user_image" type="file" name="user_image" accept="image/*" />

      <button type="submit">Registrati</button>
    </form>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <p class="login-link">
      Hai già un account? <a href="login.php">Accedi</a>
    </p>
  </div>

</body>
</html>
