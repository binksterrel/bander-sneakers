<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$selected_conversation_id = isset($_GET['conversation_id']) && is_numeric($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : null;
$error = null;
$other_username = "Utilisateur inconnu"; // Valeur par défaut

try {
    $db = getDbConnection();

    // Récupérer toutes les conversations de l'utilisateur avec le dernier message et les non-lus
    $conversations_query = "
        SELECT c.conversation_id, 
               c.is_closed,  -- Ajout de la colonne is_closed
               u1.user_id AS user1_id, u1.username AS user1_username,
               u2.user_id AS user2_id, u2.username AS user2_username,
               (SELECT m.message_text 
                FROM messages m 
                WHERE m.conversation_id = c.conversation_id 
                ORDER BY m.sent_at DESC 
                LIMIT 1) AS last_message,
               (SELECT COUNT(*) 
                FROM messages m 
                WHERE m.conversation_id = c.conversation_id 
                AND m.sender_id != :user_id1 
                AND m.is_read = 0) AS unread_count
        FROM conversations c
        JOIN users u1 ON c.user1_id = u1.user_id
        JOIN users u2 ON c.user2_id = u2.user_id
        WHERE (c.user1_id = :user_id2 OR c.user2_id = :user_id3)
        AND c.is_closed = 0  -- Ne montrer que les conversations non fermées
        ORDER BY (SELECT MAX(m.sent_at) 
                  FROM messages m 
                  WHERE m.conversation_id = c.conversation_id) DESC";
    $stmt = $db->prepare($conversations_query);
    $stmt->bindParam(':user_id1', $user_id, PDO::PARAM_INT); // Pour unread_count
    $stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT); // Pour user1_id
    $stmt->bindParam(':user_id3', $user_id, PDO::PARAM_INT); // Pour user2_id
    $stmt->execute();
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $messages = [];

    if ($selected_conversation_id) {
        // Vérifier que l'utilisateur fait partie de la conversation
        $query = "SELECT user1_id, user2_id FROM conversations 
                  WHERE conversation_id = :conversation_id 
                  AND (user1_id = :user_id1 OR user2_id = :user_id2)
                  AND is_closed = 0";  // Vérifier que la conversation n'est pas fermée
        $stmt = $db->prepare($query);
        $stmt->bindParam(':conversation_id', $selected_conversation_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id1', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$conversation) {
            error_log("Conversation non trouvée ou accès non autorisé pour user_id: $user_id, conversation_id: $selected_conversation_id");
            $error = "Conversation non trouvée, fermée ou accès non autorisé.";
        } else {
            // Identifier l'autre utilisateur
            $other_user_id = ($conversation['user1_id'] == $user_id) ? $conversation['user2_id'] : $conversation['user1_id'];
            $query = "SELECT username FROM users WHERE user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $other_user_id, PDO::PARAM_INT);
            $stmt->execute();
            $other_user = $stmt->fetch(PDO::FETCH_ASSOC);
            $other_username = $other_user ? htmlspecialchars($other_user['username']) : "Utilisateur inconnu";

            // Récupérer les messages
            $query = "SELECT m.*, u.username 
                      FROM messages m 
                      JOIN users u ON m.sender_id = u.user_id 
                      WHERE m.conversation_id = :conversation_id 
                      ORDER BY m.sent_at ASC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':conversation_id', $selected_conversation_id, PDO::PARAM_INT);
            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Marquer les messages comme lus
            $query = "UPDATE messages 
                      SET is_read = 1 
                      WHERE conversation_id = :conversation_id 
                      AND sender_id != :user_id 
                      AND is_read = 0";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':conversation_id', $selected_conversation_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans chat.php : " . $e->getMessage());
    $error = "Erreur lors de la récupération des données : " . $e->getMessage();
}

$page_title = "Messagerie | Bander-Sneakers";
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li><a href="messages.php">Messages</a></li>
            <li class="active">Chat avec <?= $selected_conversation_id && !$error ? htmlspecialchars($other_username) : '...' ?></li>
        </ul>
    </div>
</div>

<!-- Styles adaptés de admin-chat.php -->
<style>
    .chat-container {
        display: flex;
        height: 70vh;
        margin-top: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    .conversation-list {
        width: 30%;
        border-right: 1px solid #ddd;
        overflow-y: auto;
        padding: 10px;
        background: #fff;
    }
    .conversation-item {
        padding: 10px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
        position: relative;
    }
    .conversation-item:hover {
        background: #f5f5f5;
    }
    .conversation-item.active {
        background: #e0e0e0;
        font-weight: bold;
    }
    .unread-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        background: #dc3545;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
    }
    .chat-area {
        width: 70%;
        display: flex;
        flex-direction: column;
    }
    .chat-header {
        padding: 10px;
        border-bottom: 1px solid #ddd;
        background: #f1f1f1;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .chat-messages {
        flex: 1;
        padding: 15px;
        overflow-y: auto;
        background: #f9f9f9;
    }
    .chat-message {
        margin-bottom: 15px;
        padding: 10px;
        border-radius: 5px;
        max-width: 80%;
        position: relative;
    }
    .chat-message.sent {
        background: #ff3e3e;
        color: white;
        margin-left: auto;
    }
    .chat-message.received {
        background: #e0e0e0;
        margin-right: auto;
    }
    .chat-message .time {
        font-size: 0.8rem;
        color: #999;
        display: block;
        margin-top: 5px;
    }
    .chat-form {
        padding: 10px;
        border-top: 1px solid #ddd;
        display: flex;
        background: #fff;
    }
    .chat-form textarea {
        flex: 1;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px 0 0 4px;
        resize: none;
        height: 50px;
    }
    .chat-form button {
        padding: 8px 15px;
        background: #ff3e3e;
        color: white;
        border: none;
        border-radius: 0 4px 4px 0;
    }
    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        padding: 10px;
        border-radius: 5px;
        margin: 20px 0;
    }
    .btn-close-conversation {
        background: #dc3545;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
    }
    .btn-close-conversation:hover {
        background: #c82333;
    }
</style>

<div class="container">
    <h1>Messagerie</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="chat-container">
        <!-- Liste des conversations -->
        <div class="conversation-list">
            <h3>Vos conversations</h3>
            <?php if (empty($conversations)): ?>
                <p>Aucune conversation pour le moment.</p>
            <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                    <?php $other_user = ($conv['user1_id'] == $user_id) ? $conv['user2_username'] : $conv['user1_username']; ?>
                    <div class="conversation-item <?= $selected_conversation_id == $conv['conversation_id'] ? 'active' : '' ?>" 
                         onclick="window.location.href='chat.php?conversation_id=<?= $conv['conversation_id'] ?>'">
                        <?= htmlspecialchars($other_user) ?>
                        <?php if ($conv['last_message']): ?>
                            <small style="display: block; color: #666;"><?= htmlspecialchars(substr($conv['last_message'], 0, 30)) . (strlen($conv['last_message']) > 30 ? '...' : '') ?></small>
                        <?php endif; ?>
                        <?php if ($conv['unread_count'] > 0): ?>
                            <span class="unread-badge"><?= $conv['unread_count'] ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Zone de chat -->
        <div class="chat-area">
            <?php if ($selected_conversation_id && !$error): ?>
                <div class="chat-header">
                    <h4>Conversation avec <strong><?= htmlspecialchars($other_username) ?></strong></h4>
                    <button id="close-conversation" class="btn-close-conversation" data-conversation-id="<?= $selected_conversation_id ?>">Fermer</button>
                </div>
                <div class="chat-messages" id="chat-messages">
                    <?php foreach ($messages as $msg): ?>
                        <div class="chat-message <?= $msg['sender_id'] == $user_id ? 'sent' : 'received' ?>">
                            <strong><?= htmlspecialchars($msg['username']) ?> :</strong>
                            <?= nl2br(htmlspecialchars($msg['message_text'])) ?>
                            <span class="time"><?= date('d/m/Y H:i', strtotime($msg['sent_at'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <form class="chat-form" id="chat-form" method="POST" action="send-message.php">
                    <input type="hidden" name="conversation_id" value="<?= $selected_conversation_id ?>">
                    <textarea name="message_text" id="message_text" placeholder="Tapez votre message..." required></textarea>
                    <button type="submit">Envoyer</button>
                </form>
            <?php else: ?>
                <div class="chat-messages">
                    <p>Sélectionnez une conversation pour commencer à discuter.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatForm = document.getElementById('chat-form');
    const chatMessages = document.getElementById('chat-messages');
    const closeButton = document.getElementById('close-conversation');

    if (chatForm) {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(chatForm);

            fetch('send-message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const message = data.message;
                    const messageDiv = document.createElement('div');
                    messageDiv.classList.add('chat-message', 'sent');
                    messageDiv.innerHTML = `
                        <strong>Vous :</strong>
                        ${message.message_text.replace(/\n/g, '<br>')}
                        <span class="time">${new Date(message.sent_at).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                    `;
                    chatMessages.appendChild(messageDiv);
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                    chatForm.reset();
                } else {
                    alert(data.message || 'Erreur lors de l\'envoi du message.');
                }
            })
            .catch(error => {
                console.error('Erreur AJAX :', error);
                alert('Erreur réseau, veuillez réessayer.');
            });
        });
    }

    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    if (closeButton) {
        closeButton.addEventListener('click', function() {
            const conversationId = this.getAttribute('data-conversation-id');
            if (confirm('Voulez-vous vraiment fermer cette conversation ?')) {
                fetch('close-conversation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'conversation_id=' + encodeURIComponent(conversationId)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.href = 'chat.php'; // Redirige vers la liste des conversations
                    } else {
                        alert(data.message || 'Erreur lors de la fermeture de la conversation.');
                    }
                })
                .catch(error => {
                    console.error('Erreur AJAX :', error);
                    alert('Erreur réseau, veuillez réessayer.');
                });
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>