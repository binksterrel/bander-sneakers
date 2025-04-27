<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/config.php';
require_once 'includes/functions.php';

$db = getDbConnection();
$user_id = $_SESSION['user_id'];
$page_title = "Mes Notifications - Bander-Sneakers";

// Récupérer toutes les notifications de l'utilisateur
$stmt = $db->prepare("
    SELECT notification_id, message, type, related_id, is_read, created_at 
    FROM notifications 
    WHERE user_id = :user_id 
    ORDER BY created_at DESC
");
$stmt->execute([':user_id' => $user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marquer toutes les notifications comme lues si demandé
if (isset($_POST['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
    $stmt->execute([':user_id' => $user_id]);
    header("Location: notifications.php");
    exit();
}

// Supprimer une notification si demandé
if (isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    $notification_id = (int)$_POST['delete'];
    $stmt = $db->prepare("DELETE FROM notifications WHERE notification_id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $notification_id, ':user_id' => $user_id]);
    header("Location: notifications.php");
    exit();
}

include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li><a href="compte.php">Mon Compte</a></li>
            <li class="active">Notifications</li>
        </ul>
    </div>
</div>

<!-- Notif Section -->
<section class="wishlist-section">
    <div class="container">
        <div class="section-header">
            <h1 class="section-title">Mes Notifications</h1>
            <p class="section-subtitle">Retrouvez ici toutes vos notifications.</p>
        </div>

    <div class="notifications-actions">
        <form method="POST" style="display:inline;">
            <button type="submit" name="mark_all_read" class="btn btn-primary">Tout marquer comme lu</button>
        </form>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="no-notifications">
            <p>Aucune notification à afficher.</p>
        </div>
    <?php else: ?>
        <div class="notifications-list">
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>" data-id="<?php echo $notif['notification_id']; ?>">
                    <div class="notification-content">
                        <p><?php echo htmlspecialchars($notif['message']); ?></p>
                        <span class="notification-time"><?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?></span>
                    </div>
                    <div class="notification-actions">
                        <?php if (!$notif['is_read']): ?>
                            <button class="mark-read" data-id="<?php echo $notif['notification_id']; ?>">Marquer comme lu</button>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="delete" value="<?php echo $notif['notification_id']; ?>">
                            <button type="submit" class="delete-notif"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php if ($notif['related_id'] && $notif['type'] === 'order_update'): ?>
                            <a href="order-details.php?id=<?php echo $notif['related_id']; ?>" class="view-details">Voir</a>
                        <?php elseif ($notif['related_id'] && $notif['type'] === 'message'): ?>
                            <a href="chat.php?conversation_id=<?php echo $notif['related_id']; ?>" class="view-details">Voir</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gérer le marquage comme lu via AJAX
    document.querySelectorAll('.mark-read').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            fetch('manage-notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=mark_read&id=${id}`
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      const item = document.querySelector(`.notification-item[data-id="${id}"]`);
                      item.classList.remove('unread');
                      item.classList.add('read');
                      button.remove();
                  }
              });
        });
    });

    // Gérer la suppression via le formulaire (pas besoin d'AJAX ici car on recharge la page)
});
</script>

<style>
.notifications-container {
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    text-align: center;
}
.notifications-actions {
    margin-bottom: 20px;
    text-align: right;
}
.notifications-list {
    text-align: left;
}
.notification-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    background: #fff;
}
.notification-item.unread {
    background: #f9f9f9;
    font-weight: bold;
}
.notification-item.read {
    background: #fff;
}
.notification-content {
    flex-grow: 1;
}
.notification-content p {
    margin: 0 0 5px;
    font-size: 16px;
}
.notification-time {
    font-size: 12px;
    color: #777;
}
.notification-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}
.mark-read {
    background: none;
    border: none;
    color: var(--primary-color);
    cursor: pointer;
    font-size: 14px;
}
.mark-read:hover {
    text-decoration: underline;
}
.delete-notif {
    background: none;
    border: none;
    color: #e74c3c;
    cursor: pointer;
    font-size: 16px;
}
.delete-notif:hover {
    color: #c0392b;
}
.view-details {
    color: #28a745;
    text-decoration: none;
    font-size: 14px;
}
.view-details:hover {
    text-decoration: underline;
}
.no-notifications {
    padding: 20px;
    text-align: center;
    color: #777;
}
.btn-primary {
    padding: 8px 15px;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}
.btn-primary:hover {
    background: var(--primary-color);
}
</style>

<?php include 'includes/footer.php'; ?>