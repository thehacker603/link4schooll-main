<?php
session_start();
session_unset();   // Rimuove tutte le variabili di sessione
session_destroy(); // Distrugge la sessione
header("Location: index.php"); // Reindirizza l'utente alla pagina di login
exit();
?>
