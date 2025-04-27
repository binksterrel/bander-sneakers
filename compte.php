<?php
// Page de compte utilisateur
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['error_message'] = 'Vous devez être connecté pour accéder à cette page.';
    header('Location: login.php');
    exit();
}

// Récupérer les informations de l'utilisateur
$db = getDbConnection();
$userId = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    $_SESSION['error_message'] = 'Erreur : utilisateur non trouvé.';
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT COUNT(*) as received_report_count FROM reports WHERE reported_user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$receivedReportCount = $result['received_report_count'] ?? 0;

// Récupérer les points de fidélité
$loyaltyPoints = getLoyaltyPoints($userId);
$loyaltyHistory = getLoyaltyPointsHistory($userId);

// Récupérer les commandes de l'utilisateur
$stmt = $db->prepare("
    SELECT o.*, COUNT(oi.order_item_id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

// Récupérer les produits favoris de l'utilisateur
function getUserWishlist($userId) {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT w.*, s.sneaker_name, s.price, s.discount_price, s.stock_quantity, s.is_new_arrival,
               b.brand_name, c.category_name,
               (SELECT image_url FROM sneaker_images
                WHERE sneaker_id = s.sneaker_id AND is_primary = 1 LIMIT 1) AS primary_image
        FROM wishlist w
        JOIN sneakers s ON w.sneaker_id = s.sneaker_id
        LEFT JOIN brands b ON s.brand_id = b.brand_id
        LEFT JOIN categories c ON s.category_id = c.category_id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}
$wishlistItems = getUserWishlist($userId);

// Récupérer les annonces 2ndHand de l'utilisateur
$stmt = $db->prepare("
    SELECT sp.*, c.category_name, b.brand_name
    FROM secondhand_products sp
    LEFT JOIN categories c ON sp.category_id = c.category_id
    LEFT JOIN brands b ON sp.brand_id = b.brand_id
    WHERE sp.user_id = ? AND sp.statut != 'supprimé'
    ORDER BY sp.created_at DESC
");
$stmt->execute([$userId]);
$secondhandProducts = $stmt->fetchAll();

// Signalements effectués
$stmt = $db->prepare("SELECT COUNT(*) as report_count FROM reports WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$reportCount = $result['report_count'] ?? 0;

// Signalements reçus
$stmt = $db->prepare("SELECT COUNT(*) as received_report_count FROM reports WHERE reported_user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$receivedReportCount = $result['received_report_count'] ?? 0;

// Récupérer les conversations de l'utilisateur
$stmt = $db->prepare("
    SELECT c.conversation_id,
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
            AND m.sender_id != ?
            AND m.is_read = 0) AS unread_count
    FROM conversations c
    JOIN users u1 ON c.user1_id = u1.user_id
    JOIN users u2 ON c.user2_id = u2.user_id
    WHERE c.user1_id = ? OR c.user2_id = ?
    ORDER BY (SELECT MAX(m.sent_at)
              FROM messages m
              WHERE m.conversation_id = c.conversation_id) DESC
");
$stmt->execute([$userId, $userId, $userId]);
$conversations = $stmt->fetchAll();
$totalUnread = 0;
foreach ($conversations as $conv) {
    $totalUnread += (int)$conv['unread_count'];
}

// Traitement des messages de succès et d'erreur (session ou GET)
$success_message = '';
$error_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
} elseif (isset($_GET['success'])) {
    $success_message = urldecode($_GET['success']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
} elseif (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}

// Traitement du formulaire de modification du profil et des notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $firstName = isset($_POST['first_name']) ? cleanInput($_POST['first_name']) : '';
        $lastName = isset($_POST['last_name']) ? cleanInput($_POST['last_name']) : '';
        $email = isset($_POST['email']) ? cleanInput($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? cleanInput($_POST['phone']) : '';
        $address = isset($_POST['address']) ? cleanInput($_POST['address']) : '';
        $city = isset($_POST['city']) ? cleanInput($_POST['city']) : '';
        $postalCode = isset($_POST['postal_code']) ? cleanInput($_POST['postal_code']) : '';
        $country = isset($_POST['country']) ? cleanInput($_POST['country']) : '';
        if ($email !== $user['email']) {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $userId]);
            $result = $stmt->fetch();
            if ($result['count'] > 0) {
                $error_message = "Cette adresse email est déjà utilisée par un autre compte.";
            }
        }
        if (empty($error_message)) {
            try {
                $stmt = $db->prepare("
                    UPDATE users SET
                    first_name = ?, last_name = ?, email = ?, phone = ?,
                    address = ?, city = ?, postal_code = ?, country = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$firstName, $lastName, $email, $phone, $address, $city, $postalCode, $country, $userId]);
                $success_message = "Votre profil a été mis à jour avec succès.";
                $user['first_name'] = $firstName;
                $user['last_name'] = $lastName;
                $user['email'] = $email;
                $user['phone'] = $phone;
                $user['address'] = $address;
                $user['city'] = $city;
                $user['postal_code'] = $postalCode;
                $user['country'] = $country;
            } catch (PDOException $e) {
                $error_message = "Une erreur est survenue lors de la mise à jour de votre profil.";
                error_log("Erreur de mise à jour du profil: " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error_message = "Tous les champs sont obligatoires.";
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $error_message = "Le mot de passe actuel est incorrect.";
        } elseif ($newPassword !== $confirmPassword) {
            $error_message = "Les nouveaux mots de passe ne correspondent pas.";
        } elseif (strlen($newPassword) < 8) {
            $error_message = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        }
        if (empty($error_message)) {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                $success_message = "Votre mot de passe a été mis à jour avec succès.";
            } catch (PDOException $e) {
                $error_message = "Une erreur est survenue lors de la mise à jour de votre mot de passe.";
                error_log("Erreur de mise à jour du mot de passe: " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['update_notifications'])) {
        $newsletterSubscribed = isset($_POST['newsletter_subscribed']) ? 1 : 0;
        try {
            $stmt = $db->prepare("UPDATE users SET newsletter_subscribed = ? WHERE user_id = ?");
            $stmt->execute([$newsletterSubscribed, $userId]);
            $success_message = $newsletterSubscribed ? 
                "Vous êtes maintenant abonné à la newsletter." : 
                "Vous avez été désabonné de la newsletter.";
            $user['newsletter_subscribed'] = $newsletterSubscribed;
        } catch (PDOException $e) {
            $error_message = "Une erreur est survenue lors de la mise à jour des préférences de notification.";
            error_log("Erreur de mise à jour des notifications: " . $e->getMessage());
        }
    }
}

// Titre et description de la page
$page_title = "Mon Compte - Bander-Sneakers";
$page_description = "Gérez votre compte, vos commandes et vos informations personnelles sur Bander-Sneakers.";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Ajout de Font Awesome pour les icônes des toasts -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<style>
    /* Styles existants inchangés */
    .product-card .product-image {
        position: relative;
        overflow: hidden;
        height: 250px;
    }
    .product-card .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    .product-card .product-image:hover img {
        transform: scale(1.05);
    }
    .dashboard-wishlist-item .wishlist-item-image {
        position: relative;
        width: 80px;
        height: 80px;
    }
    .dashboard-wishlist-item .wishlist-item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    :root {
        --white: #fff;
        --gray-light: #f5f5f5;
        --primary-color: #ff3e3e;
        --primary-dark: rgb(179, 0, 0);
        --text-color: #333;
        --text-light: #666;
        --border-color: #ddd;
        --success-color: #28a745;
        --error-color: #dc3545;
        --warning-color: #ffc107;
        --info-processing: rgb(23, 42, 184);
        --info-shipped: rgb(23, 163, 184);
        --info-color: rgb(184, 23, 23);
        --box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        --transition: all 0.3s ease;
        --primary-color-rgb: 0, 123, 255;
        --success-color-rgb: 40, 167, 69;
        --error-color-rgb: 220, 53, 69;
        --warning-color-rgb: 255, 193, 7;
        --info-color-rgb: 23, 162, 184;
        --info-processing-rgb: 23, 42, 184;
        --info-shipped-rgb: 23, 163, 184;
    }
    .password-tips.enhanced {
        background-color: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        padding: 0;
        margin-bottom: 2rem;
        overflow: hidden;
    }
    .tips-header {
        background-color: var(--info-color);
        color: var(--white);
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .tips-header i {
        font-size: 1.5rem;
    }
    .tips-header h4 {
        font-size: 1.2rem;
        margin: 0;
    }
    .tips-content {
        padding: 1.5rem;
    }
    .tips-list {
        list-style: none;
        margin: 0 0 1.5rem 0;
        padding: 0;
    }
    .tip-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(var(--border-color-rgb), 0.3);
    }
    .tip-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    .tip-icon {
        width: 36px;
        height: 36px;
        background-color: rgba(var(--info-color-rgb), 0.1);
        color: var(--info-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }
    .tip-text {
        flex: 1;
        font-size: 0.95rem;
    }
    .password-strength-meter {
        background-color: rgba(var(--info-color-rgb), 0.05);
        border-radius: 6px;
        padding: 1rem;
        margin-top: 1rem;
    }
    .strength-label {
        font-size: 0.9rem;
        font-weight: 500;
        margin-bottom: 0.5rem;
        color: var(--text-color);
    }
    .strength-bars {
        display: flex;
        gap: 0.25rem;
        margin-bottom: 0.5rem;
    }
    .strength-bar {
        height: 6px;
        flex: 1;
        background-color: var(--gray-light);
        border-radius: 3px;
        transition: all 0.3s ease;
    }
    .strength-bar.weak {
        background-color: var(--error-color);
    }
    .strength-bar.medium {
        background-color: var(--warning-color);
    }
    .strength-bar.strong {
        background-color: var(--success-color);
    }
    .strength-text {
        font-size: 0.8rem;
        color: var(--text-light);
        text-align: right;
    }
    .password-form.enhanced .form-group {
        margin-bottom: 1.5rem;
    }
    .password-form.enhanced label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text-color);
    }
    .password-form.enhanced .input-wrapper {
        position: relative;
    }
    .password-form.enhanced .input-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--primary-color);
        font-size: 1rem;
        opacity: 0.7;
    }
    .password-form.enhanced input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.5rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 1rem;
        transition: all 0.3s;
    }
    .password-form.enhanced input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.1);
        outline: none;
    }
    .toggle-password {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: var(--text-light);
        transition: color 0.3s;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .toggle-password:hover {
        color: var(--primary-color);
    }
    .password-match-indicator {
        display: none;
        margin-top: 0.5rem;
        color: var(--error-color);
        font-size: 0.85rem;
        align-items: center;
        gap: 0.5rem;
    }
    .password-match-indicator.match {
        color: var(--success-color);
    }
    .password-match-indicator.visible {
        display: flex;
    }
    .form-actions.enhanced {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
    }
    .btn-primary {
        background-color: var(--primary-color);
        color: var(--white);
        border: none;
        padding: 0.75rem 1.5rem;
    }
    .btn-outline {
        background-color: transparent;
        color: var(--text-color);
        border: 1px solid var(--border-color);
        padding: 0.75rem 1.5rem;
    }
    .order-shpped span {
        padding: 0.25rem 0.75rem;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .status-pending {
        background-color: rgba(var(--warning-color-rgb), 0.1);
        color: var(--warning-color);
    }
    .status-processing {
        background-color: rgba(var(--info-processing-rgb), 0.1);
        color: var(--info-processing);
    }
    .status-shipped {
        background-color: rgba(var(--info-shipped-rgb), 0.1);
        color: var(--info-shipped);
    }
    .status-delivered {
        background-color: rgba(var(--success-color-rgb), 0.1);
        color: var(--success-color);
    }
    .status-cancelled {
        background-color: rgba(var(--error-color-rgb), 0.1);
        color: var(--error-color);
    }
    .conversation-list {
        padding: 10px;
    }
    .conversation-item {
        padding: 10px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
    }
    .conversation-item:hover {
        background: #f5f5f5;
    }
    .unread-badge {
        background: #dc3545;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        margin-left: 10px;
    }
    /* Nouveaux styles pour la section Notifications */
    .notifications-form.enhanced .form-group {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .notifications-form.enhanced label {
        margin: 0;
        font-weight: 500;
        color: var(--text-color);
    }
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    input:checked + .slider {
        background-color: var(--primary-color);
    }
    input:checked + .slider:before {
        transform: translateX(26px);
    }
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">Mon Compte</li>
        </ul>
    </div>
</div>

<!-- Account Section -->
<section class="account-section">
    <div class="container">
        <h1 class="section-title">Mon compte</h1>
        <div class="account-container">
            <!-- Account Sidebar -->
            <div class="account-sidebar">
                <div class="account-user">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <h3><?= $user['first_name'] ? htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) : htmlspecialchars($user['username']) ?></h3>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                </div>
                <ul class="account-nav">
                    <li class="active"><a href="#dashboard" data-tab="dashboard">Tableau de bord</a></li>
                    <li><a href="#orders" data-tab="orders">Mes commandes</a></li>
                    <li><a href="#wishlist" data-tab="wishlist">Mes favoris</a></li>
                    <li><a href="#loyalty" data-tab="loyalty">Programme de fidélité</a></li>
                    <li><a href="#secondhand" data-tab="secondhand">Mes annonces</a></li>
                    <li>
                        <a href="#conversations" data-tab="conversations">
                            Mes conversations
                            <?php if ($totalUnread > 0): ?>
                                <span class="unread-badge"><?= $totalUnread ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li><a href="#profile" data-tab="profile">Informations personnelles</a></li>
                    <li><a href="#password" data-tab="password">Changer de mot de passe</a></li>
                    <li><a href="#notifications" data-tab="notifications">Notifications</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="admin/index.php">Administration</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php">Déconnexion</a></li>
                </ul>
            </div>
            
            <!-- Account Content -->
            <div class="account-content">
                <!-- Dashboard Tab -->
                <div id="dashboard" class="account-tab active">
                    <h2>Tableau de bord</h2>
                    <p>Bienvenue dans votre espace personnel, <?= $user['first_name'] ? htmlspecialchars($user['first_name']) : htmlspecialchars($user['username']) ?>.</p>
                    <div class="dashboard-stats">
                        <div class="dashboard-stat">
                            <i class="fas fa-shopping-bag"></i>
                            <div class="stat-content">
                                <span class="stat-value"><?= count($orders) ?></span>
                                <span class="stat-label">Commandes</span>
                            </div>
                        </div>
                        <div class="dashboard-stat">
                            <i class="fas fa-heart"></i>
                            <div class="stat-content">
                                <span class="stat-value"><?= count($wishlistItems) ?></span>
                                <span class="stat-label">Favoris</span>
                            </div>
                        </div>
                        <div class="dashboard-stat">
                            <i class="fas fa-star"></i>
                            <div class="stat-content">
                                <span class="stat-value"><?= $loyaltyPoints ?></span>
                                <span class="stat-label">Points de fidélité</span>
                            </div>
                        </div>
                        <div class="dashboard-stat">
                            <i class="fas fa-box-open"></i>
                            <div class="stat-content">
                                <span class="stat-value"><?= count($secondhandProducts) ?></span>
                                <span class="stat-label">Annonces 2ndHand</span>
                            </div>
                        </div>
                        <div class="dashboard-stat">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div class="stat-content">
                                <span class="stat-value"><?= $receivedReportCount ?></span>
                                <span class="stat-label">Signalements reçus</span>
                            </div>
                        </div>
                        <div class="dashboard-stat">
                            <i class="fas fa-flag"></i>
                            <div class="stat-content">
                                <span class="stat-value"><?= $reportCount ?></span>
                                <span class="stat-label">Signalements effectués</span>
                             </div>
                        </div>
                    </div>
                    <?php if (count($orders) > 0): ?>
                        <div class="dashboard-section">
                            <h3>Dernières commandes</h3>
                            <div class="dashboard-orders">
                                <?php foreach (array_slice($orders, 0, 3) as $order): ?>
                                    <div class="dashboard-order">
                                        <div class="order-info">
                                            <div class="order-number">Commande #<?= $order['order_id'] ?></div>
                                            <div class="order-date"><?= date('d/m/Y', strtotime($order['created_at'])) ?></div>
                                        </div>
                                        <div class="order-status">
                                            <span class="status-<?= $order['order_status'] ?>"><?= ucfirst($order['order_status']) ?></span>
                                        </div>
                                        <div class="order-total"><?= formatPrice($order['total_amount']) ?></div>
                                        <div class="order-items-count"><?= $order['item_count'] ?> article<?= $order['item_count'] > 1 ? 's' : '' ?></div>
                                        <a href="order-details.php?id=<?= $order['order_id'] ?>" class="btn btn-sm">Détails</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($orders) > 3): ?>
                                <div class="text-center mt-3">
                                    <a href="#orders" data-tab="orders" class="btn btn-primary view-all-orders">Voir toutes mes commandes</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (count($wishlistItems) > 0): ?>
                        <div class="dashboard-section">
                            <h3>Mes favoris</h3>
                            <div class="dashboard-wishlist">
                                <?php foreach (array_slice($wishlistItems, 0, 3) as $item): ?>
                                    <div class="dashboard-wishlist-item">
                                        <div class="wishlist-item-image">
                                            <?php if ($item['primary_image']): ?>
                                                <img src="assets/images/sneakers/<?= htmlspecialchars($item['primary_image']) ?>" alt="<?= htmlspecialchars($item['sneaker_name']) ?>">
                                            <?php else: ?>
                                                <div class="no-image">Aucune image</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="wishlist-item-info">
                                            <h4><?= htmlspecialchars($item['sneaker_name']) ?></h4>
                                            <div class="wishlist-item-price">
                                                <?php if ($item['discount_price']): ?>
                                                    <span class="current-price"><?= formatPrice($item['discount_price']) ?></span>
                                                    <span class="original-price"><?= formatPrice($item['price']) ?></span>
                                                <?php else: ?>
                                                    <span class="current-price"><?= formatPrice($item['price']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="wishlist-item-actions">
                                            <a href="sneaker.php?id=<?= $item['sneaker_id'] ?>" class="btn btn-sm">Voir</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($wishlistItems) > 3): ?>
                                <div class="text-center mt-3">
                                    <a href="#wishlist" data-tab="wishlist" class="btn btn-primary view-all-wishlist">Voir tous mes favoris</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (count($secondhandProducts) > 0): ?>
                        <div class="dashboard-section">
                            <h3>Mes dernières annonces 2ndHand</h3>
                            <div class="dashboard-wishlist">
                                <?php foreach (array_slice($secondhandProducts, 0, 3) as $product): ?>
                                    <div class="dashboard-wishlist-item">
                                        <div class="wishlist-item-image">
                                            <?php
                                            $images = explode(',', $product['images']);
                                            $first_image = !empty($images[0]) ? htmlspecialchars($images[0]) : 'assets/images/placeholder.jpg';
                                            ?>
                                            <img src="<?= $first_image ?>" alt="<?= htmlspecialchars($product['title']) ?>">
                                        </div>
                                        <div class="wishlist-item-info">
                                            <h4><?= htmlspecialchars($product['title']) ?></h4>
                                            <div class="wishlist-item-price">
                                                <span class="current-price"><?= number_format($product['price'], 2) ?> €</span>
                                            </div>
                                        </div>
                                        <div class="wishlist-item-actions">
                                            <a href="2ndhand-detail.php?id=<?= $product['id'] ?>" class="btn btn-sm">Voir</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($secondhandProducts) > 3): ?>
                                <div class="text-center mt-3">
                                    <a href="#secondhand" data-tab="secondhand" class="btn btn-primary">Voir toutes mes annonces</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Orders Tab -->
                <div id="orders" class="account-tab">
                    <h2>Mes commandes</h2>
                    <?php if (count($orders) > 0): ?>
                        <div class="orders-list">
                            <?php foreach ($orders as $order): ?>
                                <div class="order-card">
                                    <div class="order-header">
                                        <div class="order-header-left">
                                            <h3>Commande #<?= $order['order_id'] ?></h3>
                                            <div class="order-date">
                                                <i class="far fa-calendar-alt"></i>
                                                <?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?>
                                            </div>
                                        </div>
                                        <div class="order-header-right">
                                            <div class="order-status">
                                                <span class="status-<?= $order['order_status'] ?>"><?= ucfirst($order['order_status']) ?></span>
                                            </div>
                                            <div class="order-total">
                                                Total: <?= formatPrice($order['total_amount']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="order-content">
                                        <div class="order-shipping">
                                            <h4>Adresse de livraison</h4>
                                            <p>
                                                <?= $order['shipping_address'] ?><br>
                                                <?= $order['shipping_postal_code'] ?> <?= $order['shipping_city'] ?><br>
                                                <?= $order['shipping_country'] ?>
                                            </p>
                                        </div>
                                        <div class="order-details">
                                            <h4>Détails</h4>
                                            <ul>
                                                <li><strong>Méthode de paiement:</strong> <?= ucfirst($order['payment_method']) ?></li>
                                                <li><strong>Méthode de livraison:</strong> <?= ucfirst($order['shipping_method']) ?></li>
                                                <li><strong>Nombre d'articles:</strong> <?= $order['item_count'] ?></li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="order-actions">
                                        <a href="order-details.php?id=<?= $order['order_id'] ?>" class="btn btn-primary">Voir les détails</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-orders">
                            <i class="fas fa-shopping-bag"></i>
                            <h3>Vous n'avez pas encore passé de commande</h3>
                            <p>Découvrez notre catalogue et passez votre première commande dès maintenant.</p>
                            <a href="sneakers.php" class="btn btn-primary">Explorer les produits</a>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Wishlist Tab -->
                <div id="wishlist" class="account-tab">
                    <h2>Mes favoris</h2>
                    <?php if (count($wishlistItems) > 0): ?>
                        <div class="product-grid">
                            <?php foreach ($wishlistItems as $item): ?>
                                <div class="product-card">
                                    <div class="product-image">
                                        <?php if ($item['is_new_arrival']): ?>
                                            <div class="product-badge new">Nouveau</div>
                                        <?php endif; ?>
                                        <?php if ($item['discount_price']): ?>
                                            <div class="product-badge sale">-<?= calculateDiscount($item['price'], $item['discount_price']) ?>%</div>
                                        <?php endif; ?>
                                        <?php if ($item['primary_image']): ?>
                                            <img src="assets/images/sneakers/<?= htmlspecialchars($item['primary_image']) ?>" alt="<?= htmlspecialchars($item['sneaker_name']) ?>">
                                        <?php else: ?>
                                            <div class="no-image">Aucune image</div>
                                        <?php endif; ?>
                                        <div class="product-actions">
                                            <a href="wishlist-add.php?id=<?= $item['sneaker_id'] ?>" class="action-btn wishlist-btn" title="Retirer des favoris">
                                                <i class="fas fa-heart"></i>
                                            </a>
                                            <a href="sneaker.php?id=<?= $item['sneaker_id'] ?>" class="action-btn view-btn" title="Voir le produit">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="product-info">
                                        <div class="product-brand"><?= htmlspecialchars($item['brand_name']) ?></div>
                                        <h3 class="product-title">
                                            <a href="sneaker.php?id=<?= $item['sneaker_id'] ?>"><?= htmlspecialchars($item['sneaker_name']) ?></a>
                                        </h3>
                                        <div class="product-price">
                                            <?php if ($item['discount_price']): ?>
                                                <span class="current-price"><?= formatPrice($item['discount_price']) ?></span>
                                                <span class="original-price"><?= formatPrice($item['price']) ?></span>
                                            <?php else: ?>
                                                <span class="current-price"><?= formatPrice($item['price']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="wishlist-stock">
                                            <?php if ($item['stock_quantity'] > 0): ?>
                                                <span class="in-stock">En stock</span>
                                            <?php else: ?>
                                                <span class="out-of-stock">Rupture de stock</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-wishlist">
                            <i class="fas fa-heart"></i>
                            <h3>Votre liste de favoris est vide</h3>
                            <p>Vous n'avez pas encore ajouté de produits à vos favoris.</p>
                            <a href="sneakers.php" class="btn btn-primary">Explorer les produits</a>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Loyalty Tab -->
                <div id="loyalty" class="account-tab">
                    <h2>Programme de fidélité</h2>
                    <div class="loyalty-header">
                        <div class="loyalty-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="loyalty-info">
                            <h3>Vos points de fidélité</h3>
                            <p>Vous avez actuellement <strong><?= $loyaltyPoints ?> points</strong>.</p>
                            <p>Utilisez vos points pour obtenir des réductions sur vos prochaines commandes (200 points = 10 € de réduction).</p>
                        </div>
                    </div>
                    <?php if (count($loyaltyHistory) > 0): ?>
                        <div class="loyalty-history">
                            <h3>Historique des points</h3>
                            <div class="loyalty-history-list">
                                <?php foreach ($loyaltyHistory as $entry): ?>
                                    <div class="loyalty-history-item">
                                        <div class="loyalty-date">
                                            <i class="far fa-calendar-alt"></i>
                                            <?= date('d/m/Y à H:i', strtotime($entry['earned_at'])) ?>
                                        </div>
                                        <div class="loyalty-points <?= $entry['points'] >= 0 ? 'earned' : 'used' ?>">
                                            <?= $entry['points'] >= 0 ? '+' : '' ?><?= $entry['points'] ?> points
                                        </div>
                                        <div class="loyalty-description">
                                            <?= $entry['points'] >= 0
                                            ? "Points gagnés - " . htmlspecialchars($entry['description'])
                                            : "Points utilisés - " . htmlspecialchars($entry['description']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-loyalty">
                            <i class="fas fa-star"></i>
                            <h3>Si vous n'avez pas d'historique de points</h3>
                            <p>Commencez à gagner des points en passant des commandes ou en tournant la roulette à points !</p>
                            <a href="sneakers.php" class="btn btn-primary">Explorer les produits</a>
                            <a href="spin.php" class="btn btn-primary">Tourner la roulette</a>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Secondhand Tab -->
                <div id="secondhand" class="account-tab">
                    <h2>Mes annonces 2ndHand</h2>
                    <div class="add-product-action text-right mb-4">
                        <a href="2ndhand-post.php" class="btn btn-primary">Ajouter une annonce</a>
                    </div>
                    <?php if (count($secondhandProducts) > 0): ?>
                        <div class="product-grid">
                            <?php foreach ($secondhandProducts as $product): ?>
                                <div class="product-card">
                                    <div class="product-image">
                                        <?php
                                        $images = explode(',', $product['images']);
                                        $first_image = !empty($images[0]) ? htmlspecialchars($images[0]) : 'assets/images/placeholder.jpg';
                                        ?>
                                        <img src="<?= $first_image ?>" alt="<?= htmlspecialchars($product['title']) ?>">
                                        <div class="product-actions">
                                            <a href="2ndhand-edit.php?id=<?= $product['id'] ?>" class="action-btn edit-btn" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="2ndhand-delete.php?id=<?= $product['id'] ?>" class="action-btn delete-btn" title="Supprimer" onclick="return confirm('Voulez-vous vraiment supprimer cette annonce ?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="product-info">
                                        <div class="product-brand"><?= htmlspecialchars($product['brand_name']) ?></div>
                                        <h3 class="product-title">
                                            <a href="2ndhand-detail.php?id=<?= $product['id'] ?>"><?= htmlspecialchars($product['title']) ?></a>
                                        </h3>
                                        <div class="product-price">
                                            <span class="current-price"><?= number_format($product['price'], 2) ?> €</span>
                                        </div>
                                        <div class="wishlist-stock">
                                            <span class="status-<?= $product['statut'] ?>"><?= ucfirst($product['statut']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-secondhand">
                            <i class="fas fa-box-open"></i>
                            <h3>Vous n'avez pas encore d'annonces</h3>
                            <p>Revendez vos sneakers et donnez-leur une seconde vie !</p>
                            <a href="2ndhand-post.php" class="btn btn-primary">Ajouter une annonce</a>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Conversations Tab -->
                <div id="conversations" class="account-tab">
                    <h2>Mes conversations</h2>
                    <?php if (count($conversations) > 0): ?>
                        <div class="conversation-list">
                            <?php foreach ($conversations as $conv): ?>
                                <?php
                                $otherUser = ($conv['user1_id'] == $userId) ? $conv['user2_username'] : $conv['user1_username'];
                                ?>
                                <div class="conversation-item" onclick="window.location.href='chat.php?conversation_id=<?= $conv['conversation_id'] ?>'">
                                    <div class="conversation-info">
                                        <strong><?= htmlspecialchars($otherUser) ?></strong>
                                        <p><?= htmlspecialchars(truncate($conv['last_message'], 50)) ?></p>
                                    </div>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?= $conv['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-conversations">
                            <i class="fas fa-comments"></i>
                            <h3>Aucune conversation</h3>
                            <p>Vous n'avez pas encore de conversations en cours.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Profile Tab -->
                <div id="profile" class="account-tab">
                    <h2>Informations personnelles</h2>
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="profile-intro">
                            <h3>Bonjour, <?= $user['first_name'] ? htmlspecialchars($user['first_name']) : htmlspecialchars($user['username']) ?></h3>
                            <p>Mettez à jour vos informations personnelles et votre adresse</p>
                        </div>
                    </div>
                    <form action="compte.php" method="post" class="profile-form enhanced">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="form-section">
                            <h3><i class="fas fa-user-edit"></i> Informations de base</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name">Prénom</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-user input-icon"></i>
                                        <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" placeholder="Votre prénom">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Nom</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-user input-icon"></i>
                                        <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" placeholder="Votre nom">
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email">Email <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-envelope input-icon"></i>
                                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required placeholder="Votre adresse email">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Téléphone</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-phone input-icon"></i>
                                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="Votre numéro de téléphone">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-section">
                            <h3><i class="fas fa-map-marker-alt"></i> Adresse de livraison</h3>
                            <div class="form-group full-width">
                                <label for="address">Adresse</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-home input-icon"></i>
                                    <input type="text" id="address" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="Votre adresse complète">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city">Ville</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-city input-icon"></i>
                                        <input type="text" id="city" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>" placeholder="Votre ville">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="postal_code">Code postal</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-mailbox input-icon"></i>
                                        <input type="text" id="postal_code" name="postal_code" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>" placeholder="Votre code postal">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="country">Pays</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-globe input-icon"></i>
                                    <select id="country" name="country">
                                        <option value="">Sélectionner un pays</option>
                                        <option value="France" <?= ($user['country'] ?? '') == 'France' ? 'selected' : '' ?>>France</option>
                                        <option value="Belgique" <?= ($user['country'] ?? '') == 'Belgique' ? 'selected' : '' ?>>Belgique</option>
                                        <option value="Suisse" <?= ($user['country'] ?? '') == 'Suisse' ? 'selected' : '' ?>>Suisse</option>
                                        <option value="Luxembourg" <?= ($user['country'] ?? '') == 'Luxembourg' ? 'selected' : '' ?>>Luxembourg</option>
                                        <option value="Canada" <?= ($user['country'] ?? '') == 'Canada' ? 'selected' : '' ?>>Canada</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions enhanced">
                            <button type="reset" class="btn btn-outline">Annuler les modifications</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer les modifications</button>
                        </div>
                    </form>
                </div>
                <!-- Password Tab -->
                <div id="password" class="account-tab">
                    <h2>Changer de mot de passe</h2>
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="profile-intro">
                            <h3>Sécurité du compte</h3>
                            <p>Modifiez votre mot de passe pour sécuriser votre compte</p>
                        </div>
                    </div>
                    <form action="compte.php" method="post" class="password-form enhanced">
                        <input type="hidden" name="change_password" value="1">
                        <div class="form-section animated">
                            <h3><i class="fas fa-key"></i> Modification du mot de passe</h3>
                            <div class="form-group">
                                <label for="current_password">Mot de passe actuel <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" id="current_password" name="current_password" required placeholder="Entrez votre mot de passe actuel">
                                    <span class="toggle-password" onclick="togglePassword('current_password', this)">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="password-divider">
                                <span class="divider-line"></span>
                                <span class="divider-text">Nouveau mot de passe</span>
                                <span class="divider-line"></span>
                            </div>
                            <div class="form-group">
                                <label for="new_password">Nouveau mot de passe <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-lock-open input-icon"></i>
                                    <input type="password" id="new_password" name="new_password" required placeholder="Entrez votre nouveau mot de passe">
                                    <span class="toggle-password" onclick="togglePassword('new_password', this)">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                                <p class="form-help">Le mot de passe doit contenir au moins 8 caractères.</p>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirmer le nouveau mot de passe <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-check-circle input-icon"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirmez votre nouveau mot de passe">
                                    <span class="toggle-password" onclick="togglePassword('confirm_password', this)">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                                <div id="password-match-indicator" class="password-match-indicator">
                                    <i class="fas fa-times-circle"></i>
                                    <span>Les mots de passe ne correspondent pas</span>
                                </div>
                            </div>
                        </div>
                        <div class="password-tips enhanced">
                            <div class="tips-header">
                                <i class="fas fa-shield-alt"></i>
                                <h4>Conseils pour un mot de passe fort</h4>
                            </div>
                            <div class="tips-content">
                                <ul class="tips-list">
                                    <li class="tip-item">
                                        <span class="tip-icon"><i class="fas fa-ruler"></i></span>
                                        <span class="tip-text">Utilisez au moins 8 caractères</span>
                                    </li>
                                    <li class="tip-item">
                                        <span class="tip-icon"><i class="fas fa-font"></i></span>
                                        <span class="tip-text">Combinez majuscules et minuscules</span>
                                    </li>
                                    <li class="tip-item">
                                        <span class="tip-icon"><i class="fas fa-hashtag"></i></span>
                                        <span class="tip-text">Incluez chiffres et caractères spéciaux</span>
                                    </li>
                                </ul>
                                <div class="password-strength-meter">
                                    <div class="strength-label">Force du mot de passe</div>
                                    <div class="strength-bars">
                                        <span class="strength-bar"></span>
                                        <span class="strength-bar"></span>
                                        <span class="strength-bar"></span>
                                        <span class="strength-bar"></span>
                                    </div>
                                    <div class="strength-text">Pas encore évalué</div>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions enhanced">
                            <button type="reset" class="btn btn-outline">Annuler</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Changer mon mot de passe</button>
                        </div>
                    </form>
                </div>
                <!-- Notifications Tab -->
                <div id="notifications" class="account-tab">
                    <h2>Notifications</h2>
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="profile-intro">
                            <h3>Gérer vos notifications</h3>
                            <p>Personnalisez vos préférences de notifications et de newsletter</p>
                        </div>
                    </div>
                    <form action="compte.php" method="post" class="notifications-form enhanced">
                        <input type="hidden" name="update_notifications" value="1">
                        <div class="form-section">
                            <h3><i class="fas fa-envelope"></i> Préférences de newsletter</h3>
                            <div class="form-group">
                                <label for="newsletter_subscribed">Recevoir la newsletter</label>
                                <label class="switch">
                                    <input type="checkbox" id="newsletter_subscribed" name="newsletter_subscribed" <?= ($user['newsletter_subscribed'] ?? 1) ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <p class="form-help">Activez cette option pour recevoir nos offres exclusives, promotions et mises à jour par email.</p>
                        </div>
                        <div class="form-actions enhanced">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer les préférences</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des onglets existants
    const tabLinks = document.querySelectorAll('.account-nav a[data-tab]');
    const tabs = document.querySelectorAll('.account-tab');
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const tabId = this.getAttribute('data-tab');
            tabLinks.forEach(l => l.parentElement.classList.remove('active'));
            tabs.forEach(t => t.classList.remove('active'));
            this.parentElement.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
    // Gestion des boutons "Voir tous"
    const viewAllButtons = document.querySelectorAll('.view-all-orders, .view-all-wishlist');
    viewAllButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const tabId = this.getAttribute('data-tab');
            tabLinks.forEach(l => l.parentElement.classList.remove('active'));
            tabs.forEach(t => t.classList.remove('active'));
            document.querySelector(`.account-nav a[data-tab="${tabId}"]`).parentElement.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
    // Gestion de l'ancre dans l'URL (ex. #notifications)
    const hash = window.location.hash.replace('#', '');
    if (hash) {
        const targetTab = document.getElementById(hash);
        const targetLink = document.querySelector(`.account-nav a[data-tab="${hash}"]`);
        if (targetTab && targetLink) {
            tabLinks.forEach(l => l.parentElement.classList.remove('active'));
            tabs.forEach(t => t.classList.remove('active'));
            targetLink.parentElement.classList.add('active');
            targetTab.classList.add('active');
        }
    }

    // Gestion du toggle password et de la force du mot de passe
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            togglePassword(targetId, this);
        });
    });

    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');

    if (newPasswordInput && confirmPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });

        confirmPasswordInput.addEventListener('input', function() {
            checkPasswordMatch();
        });
    }
});

function togglePassword(inputId, toggleElement) {
    const input = document.getElementById(inputId);
    const icon = toggleElement.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function checkPasswordStrength(password) {
    const strengthBars = document.querySelectorAll('.strength-bar');
    const strengthText = document.querySelector('.strength-text');
    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    strengthBars.forEach((bar, index) => {
        bar.classList.remove('weak', 'medium', 'strong');
        if (index < strength) {
            if (strength <= 2) bar.classList.add('weak');
            else if (strength === 3) bar.classList.add('medium');
            else bar.classList.add('strong');
        }
    });

    if (strength === 0) strengthText.textContent = 'Pas encore évalué';
    else if (strength <= 2) strengthText.textContent = 'Faible';
    else if (strength === 3) strengthText.textContent = 'Moyen';
    else strengthText.textContent = 'Fort';
}

function checkPasswordMatch() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const indicator = document.getElementById('password-match-indicator');

    if (confirmPassword.length > 0) {
        indicator.classList.add('visible');
        if (newPassword === confirmPassword) {
            indicator.classList.add('match');
            indicator.innerHTML = '<i class="fas fa-check-circle"></i><span>Les mots de passe correspondent</span>';
        } else {
            indicator.classList.remove('match');
            indicator.innerHTML = '<i class="fas fa-times-circle"></i><span>Les mots de passe ne correspondent pas</span>';
        }
    } else {
        indicator.classList.remove('visible');
    }
}

// Fonction pour afficher un toast
function showToast(message, isError = false) {
    const existingToast = document.getElementById('toast-notification');
    if (existingToast) {
        existingToast.remove();
    }

    const toast = document.createElement('div');
    toast.id = 'toast-notification';
    
    const icon = isError 
        ? '<i class="fas fa-exclamation-circle" style="margin-right: 10px;"></i>' 
        : '<i class="fas fa-check-circle" style="margin-right: 10px;"></i>';
    toast.innerHTML = `${icon}${message}`;
    
    toast.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        background-color: ${isError ? '#dc3545' : '#28a745'};
        color: white;
        padding: 15px 25px;
        border-radius: 10px;
        border: 1px solid ${isError ? '#c82333' : '#218838'};
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.4s ease-in-out, transform 0.3s ease-in-out;
        font-size: 1rem;
        max-width: 350px;
        display: flex;
        align-items: center;
        cursor: pointer;
        transform: translateY(20px);
    `;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
    }, 100);

    let timeout = setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        setTimeout(() => {
            toast.remove();
        }, 400);
    }, 4000);

    toast.addEventListener('click', () => {
        clearTimeout(timeout);
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        setTimeout(() => {
            toast.remove();
        }, 400);
    });
}

// Afficher les messages de session ou GET au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success_message): ?>
        showToast(<?= json_encode($success_message) ?>, false);
    <?php endif; ?>

    <?php if ($error_message): ?>
        showToast(<?= json_encode($error_message) ?>, true);
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>