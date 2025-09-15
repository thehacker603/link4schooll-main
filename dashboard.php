<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

// Recupera i gruppi a cui l'utente appartiene
$stmt_groups = $conn->prepare("
    SELECT g.id, g.nome 
    FROM groups g 
    JOIN group_members gm ON g.id = gm.group_id 
    WHERE gm.user_id = ?
");
if (!$stmt_groups) {
    die("Errore nella query dei gruppi: " . $conn->error);
}
$stmt_groups->bind_param("i", $_SESSION['user_id']);
$stmt_groups->execute();
$groups_result = $stmt_groups->get_result();

// Determina il gruppo selezionato
$selected_group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;
$posts_result = null;
$is_leader = false; // Inizializza la variabile

// Gestione invio nuovo post
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_content']) && $selected_group_id) {
  $post_content = trim($_POST['post_content']);

  if (!empty($post_content)) {
      $stmt_post = $conn->prepare("
          INSERT INTO group_posts (group_id, user_id, content, created_at) 
          VALUES (?, ?, ?, NOW())
      ");

      if ($stmt_post) {
          $stmt_post->bind_param("iis", $selected_group_id, $_SESSION['user_id'], $post_content);
          $stmt_post->execute();

          // Redirect PRG
          header("Location: dashboard.php?group_id=" . $selected_group_id);
          exit();
      } else {
          die("Errore nella preparazione della query di inserimento post: " . $conn->error);
      }
  }
}
// Gestione invio nuovo commento
// … codice precedente …

// Gestione invio nuovo commento (ora con verifica del post nel gruppo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && isset($_POST['comment_content'], $_POST['post_id']) 
    && $selected_group_id
) {
    $comment_content = trim($_POST['comment_content']);
    $post_id = (int)$_POST['post_id'];

    if (!empty($comment_content)) {
        // 1. Verifica che il post esista e appartenga al gruppo selezionato
        $stmt_check_post = $conn->prepare("
            SELECT 1 
            FROM group_posts 
            WHERE id = ? 
              AND group_id = ?
        ");
        $stmt_check_post->bind_param("ii", $post_id, $selected_group_id);
        $stmt_check_post->execute();
        $stmt_check_post->store_result();

        if ($stmt_check_post->num_rows > 0) {
            // 2. Inserisci il commento
            $stmt_comment = $conn->prepare("
                INSERT INTO replies (post_id, user_id, content, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            if ($stmt_comment) {
                $stmt_comment->bind_param("iis", $post_id, $_SESSION['user_id'], $comment_content);
                $stmt_comment->execute();
            } else {
                die("Errore nella query di inserimento commento: " . $conn->error);
            }
        } else {
            // Post non valido per questo gruppo
            // Puoi gestire un messaggio d'errore se vuoi
        }
    }
}

// … resto del codice …

if ($selected_group_id) {
    // Verifica che l'utente appartenga al gruppo
    $stmt_check = $conn->prepare("
        SELECT 1 
        FROM group_members 
        WHERE user_id = ? AND group_id = ?
    ");
    if (!$stmt_check) {
        die("Errore nella query di controllo gruppo: " . $conn->error);
    }
    $stmt_check->bind_param("ii", $_SESSION['user_id'], $selected_group_id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        // Verifica se l'utente è leader del gruppo
        $stmt_leader_check = $conn->prepare("
            SELECT is_leader 
            FROM group_members 
            WHERE user_id = ? AND group_id = ?
        ");
        if ($stmt_leader_check) {
            $stmt_leader_check->bind_param("ii", $_SESSION['user_id'], $selected_group_id);
            $stmt_leader_check->execute();
            $result_leader = $stmt_leader_check->get_result();
            $row_leader = $result_leader->fetch_assoc();
            $is_leader = $row_leader['is_leader'] == 1;
        }

        // Recupera i post del gruppo
        $stmt_posts = $conn->prepare("
            SELECT p.id, p.content, p.created_at, u.username, p.user_id 
            FROM group_posts p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.group_id = ? 
            ORDER BY p.created_at DESC
        ");
        if (!$stmt_posts) {
            die("Errore nella query dei post: " . $conn->error);
        }
        $stmt_posts->bind_param("i", $selected_group_id);
        $stmt_posts->execute();
        $posts_result = $stmt_posts->get_result();
    } else {
        $group_access_denied = true;
    }
}
?>
<?php
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - Link4School</title>
  <link rel="stylesheet" href="style.css">
  <script>
  // Applica il tema salvato PRIMA del rendering della pagina
  (function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.body?.setAttribute('data-theme', savedTheme);
    document.documentElement.setAttribute('data-theme', savedTheme); // backup nel caso body non sia ancora pronto
  })();
</script>

  <style>
    :root {
      --background-light: #f5f5f5;
      --background-dark: #1e1e1e;
      --text-light: #111;
      --text-dark: #f5f5f5;
      --card-light: #fff;
      --card-dark: #2a2a2a; 
      --input-light: #fff;   
      --input-dark: #333;
      --border-radius: 8px;
      --transition: 0.3s ease;
    }

    [data-theme="dark"] {
      --background-light: var(--background-dark);
      --text-light: var(--text-dark);
      --card-light: var(--card-dark);
      --input-light: var(--input-dark);
    }

    html, body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: var(--background-light);
      color: var(--text-light);
      transition: var(--transition);
    }

    header {
      padding: 1.5rem 4rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: var(--card-light);
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .logo {
      font-weight: 800;
      font-size: 1.5rem;
      color: var(--text--color);
      text-decoration: none;
    }

    .dashboard {
      display: flex;
      height: calc(100vh - 100px);
      overflow: hidden;
    }

    .sidebar {
      width: 300px;
      background: var(--card-light);
      padding: 2rem;
      overflow-y: auto;
      border-right: 1px solid #ddd;
    }

    .group-list {
      list-style: none;
      padding: 0;
      margin-bottom: 2rem;
    }

    .group-item {
      padding: 0.8rem 1rem;
      background:rgb(126, 126, 126);
      border-radius: var(--border-radius);
      margin-bottom: 0.5rem;
      cursor: pointer;
      color: white;
      transition: var(--transition);
    }

    .group-item:hover {
      background: #d0d0d0;
    }

    .chat-container {
      flex: 1;
      display: flex;
      flex-direction: column;
      padding: 2rem;
      background: var(--background-light);
      overflow-y: auto;
    }

    .message-list {
      list-style: none;
      padding: 0;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .message-list li {
      background: var(--card-light);
      padding: 1rem;
      border-radius: var(--border-radius);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .btn {
      background: var(--accent-color);
      color: #fff;
      padding: 0.8rem;
      border: none;
      border-radius: var(--border-radius);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      text-align: center;
      display: inline-block;
      text-decoration: none;
    }

    .btn.danger {
      background: #e63946;
    }

    .btn.manage {
      background: none;
      color: var(--text--color);
      border: none;
      font-weight: 600;
      margin-bottom: 1rem;
      padding-left: 0;
      cursor: pointer;
    }

    .form-container {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      margin-bottom: 2rem;
    }

    .form-container input,
    .form-container textarea {
      padding: 0.8rem;
      border-radius: var(--border-radius);
      border: 1px solid #ccc;
      background: var(--input-light);
      color: var(--text-light);
      transition: var(--transition);
    }

    .theme-toggle {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    #theme-toggle {
      display: none;
      
    }

    .theme-label {
      cursor: pointer;
      font-size: 1.5rem;
    }

    [data-theme="dark"] .theme-label::before {
      content: "☀️";
    }

    [data-theme="light"] .theme-label::before {
      content: "🌙";
    }
    .header-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}
header {
  padding: 1.5rem 4rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--card-light);
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.comment-list {
    list-style: none;
    padding: 0;
    margin-top: 1rem;
    margin-left: 1rem;
    border-left: 2px solid var(--text-light);
}

.comment-list li {
    margin-bottom: 1rem;
    padding-left: 1rem;
    background: var(--card-light);
    border-radius: var(--border-radius);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.comment-list li p {
    margin: 0;
}
.toggle-comments-btn {
    background: none;
    border: none;
    color: var(--text-light);
    font-size: 0.9rem;
    cursor: pointer;
    text-decoration: underline;
    margin-top: 0.5rem;
    display: inline-block;
    text-align: right;
}

body.dark .toggle-comments-btn {
    color: var(--text-dark);
}

.comment-container {
    margin-top: 1rem;
    padding-left: 1rem;
    border-left: 2px solid var(--text-light);
}

body.dark .comment-container {
    border-left: 2px solid var(--text-dark);
}
.toggle-comments-btn {
    background: none;
    border: none;
    color: var(--text-light);
    font-size: 0.9rem;
    cursor: pointer;
    text-decoration: underline;
    margin-top: 0.5rem;
    display: inline-block;
    text-align: left;
    padding: 0;
}

body.dark .toggle-comments-btn {
    color: var(--text-dark);
}

.comment-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 1rem;
    margin-top: 0.5rem;
}

.comment-actions .reply-btn {
    background: var(--accent-color);
    color: #fff;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: var(--border-radius);
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition);
}

.comment-actions .reply-btn:hover {
    background: darken(var(--accent-color), 10%);
}

body.dark .comment-actions .reply-btn {
    background: var(--text-dark);
    color: var(--background-light);
}
.form-container {
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 1rem;
}
.sidebar .form-container {
    margin-top: 1rem;
}

.sidebar .btn {
    display: block;
    width: 100%;
    text-align: center;
    margin-top: 0.5rem;
}
.delete-post-btn {
    background: #e63946;
    color: #fff;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: var(--border-radius);
    font-size: 0.9rem;
    cursor: pointer;
    transition: background 0.3s ease;
}

.delete-post-btn:hover {
    background: #d62839;
}

body.dark .delete-post-btn {
    background: #ff4d4d;
    color: #000;
}

body.dark .delete-post-btn:hover {
    background: #ff1a1a;
}
/* Assicurati che il contenitore del post sia position: relative */
.post-item {
  position: relative;
  padding: 1.5rem;       /* o il padding che usi per i post */
  margin-bottom: 1rem;   /* spazio tra i post */
  background: var(--card-light);
  border-radius: var(--border-radius);
}

/* Stile e posizionamento del bottone Elimina */
.delete.btn {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  background-color: #e63946;
  color: #fff;
  border: none;
  padding: 0.25rem 0.5rem;
  font-size: 0.85rem;
  border-radius: 4px;
  cursor: pointer;
  transition: background-color var(--transition);
}
.post-item {
  position: relative; /* necessario per l’assoluto interno */
}

.message-list li {
  position: relative;
}

.delete.btn:hover {
  background-color: #c92c39;
}

/* Adatta al tema scuro */
body[data-theme="dark"] .post-item {
  background: var(--card-light);
}
body[data-theme="dark"] .delete.btn {
  background-color: #e63946;
}
.message-box {
    padding: 10px 20px;
    margin: 15px 0;
    border-radius: 8px;
    font-weight: bold;
    text-align: center;
}

.message-box.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.message-box.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.chat_priv{
  background: var(--accent-color);
      color: #fff;
      padding: 0.8rem;
      border: none;
      border-radius: var(--border-radius);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      text-align: center;
      display: inline-block;
      text-decoration: none;
}
  </style>
</head>
<body>
<header>
  <a href="#" class="logo">Link4School</a>
  <a href="chat.php" class="chat_priv">chat privata</a>
  <div class="header-right">
    <div class="theme-toggle">
      <input type="checkbox" id="theme-toggle">
      <label for="theme-toggle" class="theme-label"></label>
    </div>
    <a href="logout.php" class="btn danger" id="logout-link">Logout</a>
  </div>
</header>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const logoutLink = document.getElementById('logout-link');
    if (logoutLink) {
      logoutLink.addEventListener('click', function(e) {
        if (!confirm('Sei sicuro di voler uscire?')) {
          e.preventDefault();
        }
      });
    }
  });
</script>


  <div class="dashboard">
    <aside class="sidebar">
    <h2>I tuoi gruppi</h2>
    <?php if ($groups_result->num_rows > 0): ?>
        <ul class="group-list">
            <?php while ($group = $groups_result->fetch_assoc()): ?>
                <li class="group-item" onclick="window.location.href='?group_id=<?php echo $group['id']; ?>'">
                    <?php echo htmlspecialchars($group['nome']); ?>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p>Nessun gruppo al momento.</p>
    <?php endif; ?>

    <h3>Unisciti a un Gruppo Pubblico</h3>
<form id="join-group-form" class="form-container">
    <input type="text" name="group_name" placeholder="Nome del gruppo" required>
    <button type="submit" class="btn">Unisciti</button>
</form>
<div id="message-box-public" class="message-box" style="display: none;"></div>

<h3>Unisciti tramite Link</h3>
<form id="join-link-form" class="form-container">
    <input type="text" name="invite_link" placeholder="Link di invito" required>
    <button type="submit" class="btn">Unisciti</button>
</form>
<div id="message-box-invite" class="message-box" style="display: none;"></div>

    <div class="form-container">
        <a href="create_group.php" class="btn">Crea nuovo gruppo</a>
    </div>
</aside>

    <main class="chat-container">
      <?php if (isset($group_access_denied)): ?>
        <p style="color: red;">Non hai accesso a questo gruppo.</p>
      <?php elseif ($selected_group_id && $posts_result): ?>
        <form action="manage_group.php" method="get">
          <input type="hidden" name="group_id" value="<?php echo $selected_group_id; ?>">
          <?php if ($is_leader): ?>
            <button class="btn manage" type="submit">Gestisci gruppo</button>
          <?php endif; ?>
        </form>
        <form action="leave_group.php" method="post">
           <input type="hidden" name="group_id" value="<?php echo $selected_group_id; ?>"> <!-- ID del gruppo -->
           <button type="submit" class="btn leave">Lascia il gruppo</button>
        </form>


        <h2>Post del gruppo</h2>
        <form action="dashboard.php?group_id=<?php echo $selected_group_id; ?>" method="POST" class="form-container">
          <textarea name="post_content" placeholder="Scrivi qualcosa..." rows="4" required></textarea>
          <button type="submit" class="btn">Pubblica Post</button>
        </form>

        <ul class="message-list">
          <?php if ($posts_result->num_rows > 0): ?>
            <?php while ($post = $posts_result->fetch_assoc()): ?>
              <li>
                <strong><?php echo htmlspecialchars($post['username']); ?></strong><br>
                <small><?php echo htmlspecialchars($post['created_at']); ?></small><br>
                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>

                <!-- Pulsante per eliminare il post -->
                <?php if ($post['user_id'] == $_SESSION['user_id'] || $is_leader): ?>
                    <button class="delete-post-btn"onclick="deletePost(<?php echo $post['id']; ?>, this)" >Elimina</button>
                <?php endif; ?>

                <!-- Recupera i commenti per il post -->
                <?php
                $stmt_comments = $conn->prepare("
                    SELECT c.content, c.created_at, u.username 
                    FROM replies c 
                    JOIN users u ON c.user_id = u.id 
                    WHERE c.post_id = ? 
                    ORDER BY c.created_at ASC
                ");
                $stmt_comments->bind_param("i", $post['id']);
                $stmt_comments->execute();
                $comments_result = $stmt_comments->get_result();
                ?>

                <!-- Contenitore dei commenti -->
                <div class="comment-container" id="comments-<?php echo $post['id']; ?>" style="display: none;">
                    <ul class="comment-list">
                        <?php if ($comments_result->num_rows > 0): ?>
                            <?php while ($comment = $comments_result->fetch_assoc()): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($comment['username']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($comment['created_at']); ?></small><br>
                                    <p><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li>Nessun commento ancora.</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Pulsante per mostrare/nascondere i commenti e il pulsante Rispondi -->
                <div class="comment-actions">
                    <button class="toggle-comments-btn" data-post-id="<?php echo $post['id']; ?>">Vedi risposte</button>
                    <button class="reply-btn" data-reply-id="<?php echo $post['id']; ?>">Rispondi</button>
                </div>

                <!-- Form per aggiungere un commento -->
                <form id="reply-form-<?php echo $post['id']; ?>" action="dashboard.php?group_id=<?php echo $selected_group_id; ?>" method="POST" class="form-container">
                    <textarea name="comment_content" placeholder="Scrivi un commento..." rows="2" required></textarea>
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                    <button type="submit" class="btn">Commenta</button>
                </form>
              </li>
            <?php endwhile; ?>
          <?php else: ?>
            <li>Nessun post ancora in questo gruppo.</li>
          <?php endif; ?>
        </ul>
      <?php else: ?>
        <h2>Seleziona un gruppo per visualizzare i post</h2>
      <?php endif; ?>
    </main>
  </div>

  <script>
  // Applica il tema salvato PRIMA del rendering della pagina
  (function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.body.classList.add(savedTheme);
    document.documentElement.setAttribute('data-theme', savedTheme); // Per supportare CSS basato su data-theme
  })();

  // Gestione del cambio tema
  document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('theme-toggle');
    const body = document.body;

    // Imposta lo stato iniziale del toggle
    const savedTheme = localStorage.getItem('theme') || 'light';
    toggle.checked = savedTheme === 'dark';

    toggle.addEventListener('change', () => {
      const newTheme = toggle.checked ? 'dark' : 'light';
      body.classList.remove('dark', 'light');
      body.classList.add(newTheme);
      document.documentElement.setAttribute('data-theme', newTheme); // Aggiorna data-theme
      localStorage.setItem('theme', newTheme);
    });
  });

  // Gestione del pulsante per mostrare/nascondere i commenti
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toggle-comments-btn').forEach(button => {
      button.addEventListener('click', () => {
        const postId = button.getAttribute('data-post-id');
        const commentContainer = document.getElementById(`comments-${postId}`);
        if (commentContainer.style.display === 'none') {
          commentContainer.style.display = 'block';
          button.textContent = 'Nascondi risposte';
        } else {
          commentContainer.style.display = 'none';
          button.textContent = 'Vedi risposte';
        }
      });
    });

    // Gestione del pulsante "Rispondi"
    document.querySelectorAll('.reply-btn').forEach(button => {
        button.addEventListener('click', () => {
            const replyId = button.getAttribute('data-reply-id');
            const replyForm = document.getElementById(`reply-form-${replyId}`);
            replyForm.style.display = replyForm.style.display === 'none' ? 'flex' : 'none';
        });
    });

    // Gestione del pulsante "Elimina"
    document.querySelectorAll('.delete-post-btn').forEach(button => {
        button.addEventListener('click', () => {
            const postId = button.getAttribute('data-post-id');
            deletePost(postId, button); // Usa la funzione deletePost
        });
    });
  });

  // Funzione per eliminare un post
  function deletePost(postId, button) {
    fetch('delete_post.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `post_id=${postId}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Errore nella risposta del server');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            button.closest('li').remove();
            alert('Post eliminato con successo.');
        } else {
            alert(data.message || 'Errore durante l\'eliminazione del post.');
        }
    })
    .catch(error => {
        alert('Errore durante la richiesta: ' + error.message);
    });
  }
function showMessage(message, type = 'success') {
    const box = document.getElementById('message-box');
    box.textContent = message;

    // Applica stile in base al tipo di messaggio
    box.className = 'message-box ' + (type === 'error' ? 'error' : 'success');
    box.style.display = 'block';

    // Nasconde automaticamente dopo 5 secondi
    setTimeout(() => {
        box.style.display = 'none';
    }, 5000);
}
document.addEventListener('DOMContentLoaded', () => {
    // Gestione form gruppo pubblico
    document.getElementById('join-group-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);

        fetch('join_group.php', {
            method: 'POST',
            body: data
        })
        .then(res => res.json())
        .then(res => {
            showMessage(res.message, res.success ? 'success' : 'error', 'public');
            if (res.reload) location.reload();
        })
        .catch(() => {
            showMessage('Errore durante la richiesta.', 'error', 'public');
        });
    });

    // Gestione form link di invito
    document.getElementById('join-link-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);

        fetch('join_group.php', {
            method: 'POST',
            body: data
        })
        .then(res => res.json())
        .then(res => {
            showMessage(res.message, res.success ? 'success' : 'error', 'invite');
            if (res.reload) location.reload();
        })
        .catch(() => {
            showMessage('Errore durante la richiesta.', 'error', 'invite');
        });
    });
});

function showMessage(message, type = 'success', boxType = 'public') {
    const boxId = boxType === 'invite' ? 'message-box-invite' : 'message-box-public';
    const box = document.getElementById(boxId);
    box.textContent = message;
    box.className = 'message-box ' + (type === 'error' ? 'error' : 'success');
    box.style.display = 'block';
    setTimeout(() => { box.style.display = 'none'; }, 5000);
}
  </script>
</body>
</html>
