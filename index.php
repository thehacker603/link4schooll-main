<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php"); // Se è loggato, va alla dashboard
    exit();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>link4school</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>

  <!-- HEADER -->
  <header>
    <div class="header-actions">
      <h2>link4school</h2>
      <div class="header-buttons">
        <button class="button-sigin"><a href="login.php">Accedi</a></button>
        <button id="theme-toggle" class="button-sigin">🌓</button>
      </div>
    </div>
    <nav></nav>
  </header>

  <!-- HERO -->
  <section class="hero">
    <h1>link4class per collaborare insieme</h1>
    <p>Link4Class è una piattaforma pensata per aiutare gli studenti a collaborare facilmente su progetti di classe, condividere appunti e discutere gli esercizi.</p>
    <button class="button"><a href="register.php">Entra nella community</a></button>
  </section>

  <!-- SERVIZI -->
  <section class="section" id="servizi">
    <h2>Cosa puoi fare</h2>
    <div class="card">
      <h3>Gruppi</h3>
      <p>Con noi puoi creare gruppi sia pubblici che privati per condividere le informazioni.</p>
    </div>
    <div class="card" style="margin-top: 2rem;">
      <h3>Chat private</h3>
      <p>Puoi parlare con i tuoi amici avviando chat private che solo voi potete vedere.</p>
    </div>
  </section>

  <!-- FOOTER -->
  <footer id="contatti">
    <p>&copy; 2025 link4class - Tutti i diritti riservati</p>
  </footer>

  <!-- JS per il cambio tema -->
  <script>
    const toggleBtn = document.getElementById('theme-toggle');

    // Applica il tema salvato
    if (localStorage.getItem('theme') === 'dark') {
      document.body.classList.add('dark-theme');
    }

    toggleBtn.addEventListener('click', () => {
      document.body.classList.toggle('dark-theme');
      const isDark = document.body.classList.contains('dark-theme');
      localStorage.setItem('theme', isDark ? 'dark' : 'light');
      toggleBtn.textContent = isDark ? "☀️" : "🌓";
    });

    // Imposta l'icona corretta all'avvio
    toggleBtn.textContent = document.body.classList.contains('dark-theme') ? "☀️" : "🌓";
  </script>

</body>
</html>


<script>
  window.addEventListener("load", () => {
    const loader = document.getElementById("loader");
    if (!loader) return;
    loader.style.opacity = "0";
    setTimeout(() => {
      loader.style.display = "none";
    }, 500);
  });
  const toggleBtn = document.getElementById('theme-toggle');

// Applica il tema salvato, se esiste
if (localStorage.getItem('theme') === 'dark') {
  document.body.classList.add('dark-theme');
}

toggleBtn.addEventListener('click', () => {
  document.body.classList.toggle('dark-theme');
  // Salva la preferenza in localStorage
  if (document.body.classList.contains('dark-theme')) {
    localStorage.setItem('theme', 'dark');
  } else {
    localStorage.setItem('theme', 'light');
  }
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
