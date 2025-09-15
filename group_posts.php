<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['group_id']) || !is_numeric($_GET['group_id'])) {
    die("ID del gruppo non valido.");
}

$group_id = intval($_GET['group_id']);

$stmt_group = $conn->prepare("SELECT nome FROM groups WHERE id = ?");
if (!$stmt_group) {
    die("Errore nella query SQL (recupero gruppo): " . $conn->error);
}
$stmt_group->bind_param("i", $group_id);
$stmt_group->execute();
$group_info = $stmt_group->get_result()->fetch_assoc();

if (!$group_info) {
    die("Gruppo non trovato.");
}

$stmt_admin = $conn->prepare("SELECT * FROM group_members WHERE group_id = ? AND user_id = ? AND is_leader = 1");
if (!$stmt_admin) {
    die("Errore nella query SQL (verifica amministratore): " . $conn->error);
}
$stmt_admin->bind_param("ii", $group_id, $user_id);
$stmt_admin->execute();
$is_admin = $stmt_admin->get_result()->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Post del Gruppo: <?php echo htmlspecialchars($group_info['nome']); ?></title>
    <link rel="stylesheet" href="styile_group.css">
    <script>
        const groupId = <?php echo $group_id; ?>;

        function createPostElement(post) {
            const postElement = document.createElement('div');
            postElement.classList.add('post');
            postElement.dataset.postId = post.id;

            const postTitle = document.createElement('div');
            postTitle.classList.add('post-title');
            postTitle.textContent = post.username;

            const postContent = document.createElement('div');
            postContent.classList.add('post-content');
            postContent.innerHTML = post.content.replace(/\n/g, '<br>');

            const postTimestamp = document.createElement('div');
            postTimestamp.classList.add('post-timestamp');
            postTimestamp.textContent = `Pubblicato il ${post.created_at}`;

            const replyButton = document.createElement('button');
            replyButton.className = 'btn reply-btn';
            replyButton.textContent = 'Rispondi';
            replyButton.onclick = () => toggleReplyForm(post.id);

            const replyForm = document.createElement('div');
            replyForm.id = `reply-form-${post.id}`;
            replyForm.className = 'reply-form';
            replyForm.style.display = 'none';
            replyForm.innerHTML = `
                <form method="POST" onsubmit="submitReply(event, ${post.id})">
                    <input type="hidden" name="post_id" value="${post.id}">
                    <textarea name="reply_content" placeholder="Scrivi una risposta..." required></textarea>
                    <button type="submit" class="btn">Invia</button>
                </form>
            `;

            const repliesContainer = document.createElement('div');
            repliesContainer.classList.add('replies');
            post.replies.forEach(reply => {
                const replyElement = document.createElement('div');
                replyElement.classList.add('reply');
                replyElement.innerHTML = `
                    <strong>${reply.username}</strong>: ${reply.content.replace(/\n/g, '<br>')}
                    <div class="reply-timestamp">Pubblicato il ${reply.created_at}</div>
                `;
                repliesContainer.appendChild(replyElement);
            });

            postElement.append(postTitle, postContent, postTimestamp, replyButton, replyForm, repliesContainer);
            return postElement;
        }

        function loadPosts() {
            fetch(`fetch_posts.php?group_id=${groupId}`)
                .then(response => response.json())
                .then(posts => {
                    const postsContainer = document.getElementById('posts-container');
                    const existingPosts = {};

                    document.querySelectorAll('.post').forEach(postEl => {
                        const id = postEl.dataset.postId;
                        existingPosts[id] = postEl;
                    });

                    posts.forEach(post => {
                        if (existingPosts[post.id]) {
                            const postEl = existingPosts[post.id];

                            postEl.querySelector('.post-content').innerHTML = post.content.replace(/\n/g, '<br>');
                            postEl.querySelector('.post-timestamp').textContent = `Pubblicato il ${post.created_at}`;

                            const repliesContainer = postEl.querySelector('.replies');
                            repliesContainer.innerHTML = '';
                            post.replies.forEach(reply => {
                                const replyElement = document.createElement('div');
                                replyElement.classList.add('reply');
                                replyElement.innerHTML = `
                                    <strong>${reply.username}</strong>: ${reply.content.replace(/\n/g, '<br>')}
                                    <div class="reply-timestamp">Pubblicato il ${reply.created_at}</div>
                                `;
                                repliesContainer.appendChild(replyElement);
                            });
                        } else {
                            const newPost = createPostElement(post);
                            postsContainer.appendChild(newPost);
                        }
                    });

                    for (const postId in existingPosts) {
                        if (!posts.some(post => post.id == postId)) {
                            existingPosts[postId].remove();
                        }
                    }
                })
                .catch(err => console.error('Errore nel caricamento dei post:', err));
        }

        function toggleReplyForm(postId) {
            const form = document.getElementById(`reply-form-${postId}`);
            if (form) {
                form.style.display = (form.style.display === "none" || form.style.display === "") ? "block" : "none";
            }
        }

        function toggleCreatePostForm() {
            const form = document.getElementById('create-post-form');
            form.style.display = (form.style.display === "none" || form.style.display === "") ? "block" : "none";
        }

        function submitPost(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('group_id', groupId);

            fetch('submit_post.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    form.reset();
                    toggleCreatePostForm();
                    loadPosts();
                } else {
                    alert('Errore: ' + data.message);
                }
            })
            .catch(err => console.error('Errore invio post:', err));
        }

        function submitReply(event, postId) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('post_id', postId);

            fetch('submit_reply.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // ✅ Svuota il campo textarea
                    form.querySelector('textarea').value = '';

                    // ✅ Nasconde il form di risposta
                    form.parentElement.style.display = 'none';

                    // ✅ Aggiorna i post
                    loadPosts();
                } else {
                    alert('Errore: ' + data.message);
                }
            })
            .catch(err => console.error('Errore invio risposta:', err));
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadPosts();
            setInterval(loadPosts, 2000);
        });
    </script>
</head>
<body>
    <h1>Post del Gruppo: <?php echo htmlspecialchars($group_info['nome']); ?></h1>

    <button class="btn create-post-btn" onclick="toggleCreatePostForm()">Crea un nuovo post</button>

    <div id="create-post-form" style="display: none; margin-top: 20px;">
        <form method="POST" onsubmit="submitPost(event)">
            <textarea name="post_content" placeholder="Scrivi il tuo post..." required style="width: 100%; height: 100px;"></textarea>
            <button type="submit" class="btn">Pubblica</button>
        </form>
    </div>

    <div class="posts-container" id="posts-container"></div>

    <a href="dashboard.php" class="btn">Torna alla tua dashboard</a>
    <?php if ($is_admin): ?>
        <a href="manage_group.php?group_id=<?php echo $group_id; ?>" class="btn manage-group-btn">Gestisci Gruppo</a>
    <?php endif; ?>
</body>
</html>
