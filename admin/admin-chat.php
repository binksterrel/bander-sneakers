<?php
// Page de gestion du chat pour l'administration
session_start();

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

// Inclure la configuration et les fonctions
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Initialiser les variables
$db = getDbConnection();
$success_message = '';
$error_message = '';

// Récupérer les messages de session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Récupérer les statistiques de chat
$chat_stats = [
    'total_users' => $db->query("SELECT COUNT(DISTINCT user_id) FROM chat_messages WHERE is_deleted = 0")->fetchColumn(),
    'total_messages' => $db->query("SELECT COUNT(*) FROM chat_messages WHERE is_deleted = 0")->fetchColumn(),
    'unread_messages' => $db->query("
        SELECT COUNT(*) FROM chat_messages 
        WHERE is_admin = 0 AND is_deleted = 0 
        AND created_at > COALESCE(
            (SELECT MAX(cm2.created_at) FROM chat_messages cm2 
             WHERE cm2.user_id = chat_messages.user_id AND cm2.is_admin = 1), 
            '1970-01-01'
        )
    ")->fetchColumn(),
    'avg_response_time' => $db->query("
        SELECT AVG(TIMESTAMPDIFF(MINUTE, user_msg.created_at, admin_msg.created_at)) as avg_time
        FROM chat_messages user_msg
        JOIN chat_messages admin_msg ON user_msg.user_id = admin_msg.user_id
        WHERE user_msg.is_admin = 0 AND admin_msg.is_admin = 1
        AND admin_msg.created_at > user_msg.created_at
        AND NOT EXISTS (
            SELECT 1 FROM chat_messages mid
            WHERE mid.user_id = user_msg.user_id
            AND mid.created_at > user_msg.created_at
            AND mid.created_at < admin_msg.created_at
        )
    ")->fetchColumn() ?: 0
];

// Récupérer la liste des utilisateurs avec messages non lus (exclure les messages supprimés)
$users_with_messages = $db->query("
    SELECT DISTINCT u.user_id, u.username, u.email,
        (SELECT COUNT(*) FROM chat_messages cm2 
         WHERE cm2.user_id = u.user_id AND cm2.is_admin = 0 
         AND cm2.created_at > COALESCE(
             (SELECT MAX(cm3.created_at) FROM chat_messages cm3 
              WHERE cm3.user_id = u.user_id AND cm3.is_admin = 1), 
             '1970-01-01'
         ) 
         AND cm2.is_deleted = 0) as unread_count,
        (SELECT MAX(cm4.created_at) FROM chat_messages cm4 
         WHERE cm4.user_id = u.user_id AND cm4.is_deleted = 0) as last_message_date,
        (SELECT cm5.message_text FROM chat_messages cm5 
         WHERE cm5.user_id = u.user_id AND cm5.is_deleted = 0 
         ORDER BY cm5.created_at DESC LIMIT 1) as last_message
    FROM chat_messages cm
    JOIN users u ON u.user_id = cm.user_id
    WHERE cm.is_deleted = 0
    ORDER BY last_message_date DESC
")->fetchAll();

// Filtres pour l'historique
$selected_user_id = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$search_query = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Récupérer les informations de l'utilisateur sélectionné (nom et email)
$selected_user = null;
if ($selected_user_id) {
    $stmt = $db->prepare("SELECT username, email FROM users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $selected_user_id]);
    $selected_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer les messages de l'utilisateur sélectionné
$chat_messages = [];
if ($selected_user_id) {
    $query = "
        SELECT cm.message_id, cm.message_text, cm.created_at, cm.is_admin, cm.is_deleted, u.username
        FROM chat_messages cm
        JOIN users u ON cm.user_id = u.user_id
        WHERE cm.user_id = :user_id
    ";

    $params = [':user_id' => $selected_user_id];

    if ($search_query) {
        $query .= " AND cm.message_text LIKE :search";
        $params[':search'] = "%{$search_query}%";
    }

    if ($date_filter) {
        $query .= " AND DATE(cm.created_at) = :date";
        $params[':date'] = $date_filter;
    }

    $query .= " ORDER BY cm.created_at ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $chat_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Titre de la page
$page_title = "Gestion du chat - Admin Bander-Sneakers";

// Inclure l'en-tête (assure-toi que admin_styles.css est inclus ici)
include 'includes/header.php';
?>

<!-- Styles spécifiques pour le chat admin -->
<style>
/* Variables de couleurs */
.admin-chat-app {
    --chat-primary-color: #ff3e3e;
    --chat-primary-hover: #e62e2e;
    --chat-secondary-color: #f8f9fa;
    --chat-text-dark: #333;
    --chat-text-medium: #666;
    --chat-text-light: #999;
    --chat-border-color: #eee;
    --chat-white: #fff;
    --chat-shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
    --chat-shadow-md: 0 4px 12px rgba(0,0,0,0.05);
    --chat-radius-sm: 6px;
    --chat-radius-md: 8px;
    --chat-radius-lg: 10px;
    --chat-radius-full: 50%;
    --chat-transition: all 0.2s ease;
}

/* Conteneur principal du chat */
.admin-chat-app {
    max-width: 1200px;
    margin: 0 auto;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Stats (alignées sur dashboard-stats du CSS global) */
.chat-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.chat-stat-card {
    background-color: var(--white);
    border-radius: 8px;
    box-shadow: var(--box-shadow);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    transition: var(--transition);
}

.chat-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.chat-stat-icon {
    width: 60px;
    height: 60px;
    background-color: rgba(255, 62, 62, 0.1);
    color: var(--chat-primary-color);
    border-radius: 8px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 1.5rem;
    margin-right: 1.5rem;
}

.chat-stat-card h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: var(--text-light);
}

.chat-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-color);
}

/* Interface principale */
.admin-chat-app .chat-main {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
    background: var(--chat-white);
    border-radius: var(--chat-radius-lg);
    overflow: hidden;
    box-shadow: var(--box-shadow);
    height: 600px;
    border: 1px solid var(--chat-border-color);
}

/* Liste des utilisateurs */
.admin-chat-app .chat-users {
    background: var(--chat-secondary-color);
    border-right: 1px solid var(--chat-border-color);
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

.admin-chat-app .chat-users-header {
    padding: 15px;
    border-bottom: 1px solid var(--chat-border-color);
    flex-shrink: 0;
}

.admin-chat-app .chat-users-header h2 {
    margin: 0 0 15px;
    font-size: 1.2rem;
    color: var(--chat-text-dark);
}

.admin-chat-app .chat-search {
    position: relative;
    margin-bottom: 10px;
}

.admin-chat-app .chat-search input {
    width: 100%;
    padding: 10px 30px 10px 12px;
    border: 1px solid var(--chat-border-color);
    border-radius: var(--chat-radius-sm);
    font-size: 0.9rem;
    transition: var(--chat-transition);
}

.admin-chat-app .chat-search input:focus {
    outline: none;
    border-color: var(--chat-primary-color);
    box-shadow: 0 0 0 2px rgba(255, 62, 62, 0.1);
}

.admin-chat-app .chat-search button {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--chat-text-light);
    cursor: pointer;
}

.admin-chat-app .chat-search button:hover {
    color: var(--chat-primary-color);
}

.admin-chat-app .chat-filter select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--chat-border-color);
    border-radius: var(--chat-radius-sm);
    font-size: 0.9rem;
    background-color: var(--chat-white);
    transition: var(--chat-transition);
}

.admin-chat-app .chat-filter select:focus {
    outline: none;
    border-color: var(--chat-primary-color);
    box-shadow: 0 0 0 2px rgba(255, 62, 62, 0.1);
}

.admin-chat-app .chat-users-list {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
}

.admin-chat-app .chat-user-item {
    padding: 12px;
    border-radius: var(--chat-radius-md);
    margin-bottom: 8px;
    cursor: pointer;
    transition: var(--chat-transition);
    display: flex;
    align-items: center;
    background-color: var(--chat-white);
    border: 1px solid transparent;
}

.admin-chat-app .chat-user-item:hover {
    background-color: rgba(255, 62, 62, 0.05);
    border-color: rgba(255, 62, 62, 0.1);
}

.admin-chat-app .chat-user-item.active {
    background-color: rgba(255, 62, 62, 0.1);
    border-color: rgba(255, 62, 62, 0.2);
}

.admin-chat-app .chat-user-initial {
    width: 40px;
    height: 40px;
    border-radius: var(--chat-radius-full);
    background-color: var(--chat-primary-color);
    color: var(--chat-white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 12px;
    flex-shrink: 0;
}

.admin-chat-app .chat-user-info {
    flex: 1;
    min-width: 0;
}

.admin-chat-app .chat-user-name {
    font-weight: 600;
    margin-bottom: 3px;
    color: var(--chat-text-dark);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-chat-app .chat-user-preview {
    font-size: 0.85rem;
    color: var(--chat-text-medium);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-chat-app .chat-unread-badge {
    background-color: var(--chat-primary-color);
    color: var(--chat-white);
    border-radius: var(--chat-radius-full);
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    margin-left: 10px;
    flex-shrink: 0;
}

/* Zone de conversation */
.admin-chat-app .chat-conversation {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

.admin-chat-app .chat-conversation-header {
    padding: 15px;
    border-bottom: 1px solid var(--chat-border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.admin-chat-app .chat-selected-user {
    display: flex;
    align-items: center;
}

.admin-chat-app .chat-selected-initial {
    width: 40px;
    height: 40px;
    border-radius: var(--chat-radius-full);
    background-color: var(--chat-primary-color);
    color: var(--chat-white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 12px;
}

.admin-chat-app .chat-selected-info h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--chat-text-dark);
}

.admin-chat-app .chat-selected-info p {
    margin: 3px 0 0;
    font-size: 0.85rem;
    color: var(--chat-text-medium);
}

.admin-chat-app .chat-actions {
    display: flex;
    gap: 8px;
}

.admin-chat-app .chat-actions button {
    padding: 8px 12px;
    border-radius: var(--chat-radius-sm);
    border: 1px solid var(--chat-border-color);
    background-color: var(--chat-white);
    color: var(--chat-text-medium);
    cursor: pointer;
    transition: var(--chat-transition);
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9rem;
}

.admin-chat-app .chat-actions button:hover {
    background-color: var(--chat-secondary-color);
    color: var(--chat-text-dark);
}

.admin-chat-app .chat-actions button.danger {
    color: var(--error-color);
}

.admin-chat-app .chat-actions button.danger:hover {
    background-color: rgba(220, 53, 69, 0.1);
}

.admin-chat-app .chat-filters {
    padding: 10px 15px;
    border-bottom: 1px solid var(--chat-border-color);
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.admin-chat-app .chat-filters input, 
.admin-chat-app .chat-filters select {
    padding: 8px 12px;
    border: 1px solid var(--chat-border-color);
    border-radius: var(--chat-radius-sm);
    font-size: 0.9rem;
    transition: var(--chat-transition);
}

.admin-chat-app .chat-filters input:focus,
.admin-chat-app .chat-filters select:focus {
    outline: none;
    border-color: var(--chat-primary-color);
    box-shadow: 0 0 0 2px rgba(255, 62, 62, 0.1);
}

.admin-chat-app .chat-filters input[type="text"] {
    flex: 1;
}

.admin-chat-app .chat-filters button {
    padding: 8px 12px;
    border-radius: var(--chat-radius-sm);
    border: none;
    background-color: var(--chat-primary-color);
    color: var(--chat-white);
    cursor: pointer;
    transition: var(--chat-transition);
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9rem;
}

.admin-chat-app .chat-filters button:hover {
    background-color: var(--chat-primary-hover);
}

.admin-chat-app .chat-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    background-color: #f9f9f9;
}

.admin-chat-app .chat-message {
    margin-bottom: 15px;
    padding: 12px 15px;
    border-radius: var(--chat-radius-md);
    max-width: 80%;
    position: relative;
    box-shadow: var(--chat-shadow-sm);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.admin-chat-app .chat-message.admin {
    background-color: var(--chat-primary-color);
    color: var(--chat-white);
    margin-left: auto;
    border-bottom-right-radius: 0;
}

.admin-chat-app .chat-message.user {
    background-color: var(--chat-white);
    color: var(--chat-text-dark);
    margin-right: auto;
    border-bottom-left-radius: 0;
}

.admin-chat-app .chat-message.deleted {
    opacity: 0.7;
    background-color: #f0f0f0 !important;
    color: var(--chat-text-light) !important;
}

.admin-chat-app .chat-message-sender {
    font-weight: 600;
    margin-bottom: 5px;
}

.admin-chat-app .chat-message-content {
    word-break: break-word;
}

.admin-chat-app .chat-message-time {
    font-size: 0.8rem;
    opacity: 0.8;
    margin-top: 5px;
    text-align: right;
}

.admin-chat-app .chat-message-delete {
    position: absolute;
    top: 5px;
    right: 5px;
    width: 24px;
    height: 24px;
    border-radius: var(--chat-radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(0, 0, 0, 0.1);
    color: inherit;
    border: none;
    cursor: pointer;
    font-size: 0.8rem;
    opacity: 0;
    transition: var(--chat-transition);
}

.admin-chat-app .chat-message:hover .chat-message-delete {
    opacity: 1;
}

.admin-chat-app .chat-message.admin .chat-message-delete {
    background-color: rgba(255, 255, 255, 0.2);
}

.admin-chat-app .chat-form {
    padding: 15px;
    border-top: 1px solid var(--chat-border-color);
    display: flex;
    gap: 10px;
    flex-shrink: 0;
    background-color: var(--chat-white);
    align-items: center;
}

.admin-chat-app .chat-form input {
    flex: 1;
    padding: 12px 15px;
    border: 1px solid var(--chat-border-color);
    border-radius: var(--chat-radius-md);
    font-size: 0.95rem;
    transition: var(--chat-transition);
}

.admin-chat-app .chat-form input:focus {
    outline: none;
    border-color: var(--chat-primary-color);
    box-shadow: 0 0 0 2px rgba(255, 62, 62, 0.1);
}

.admin-chat-app .chat-form select {
    padding: 12px 15px;
    border: 1px solid var(--chat-border-color);
    border-radius: var(--chat-radius-md);
    font-size: 0.95rem;
    background-color: var(--chat-white);
    transition: var(--chat-transition);
    cursor: pointer;
}

.admin-chat-app .chat-form select:focus {
    outline: none;
    border-color: var(--chat-primary-color);
    box-shadow: 0 0 0 2px rgba(255, 62, 62, 0.1);
}

.admin-chat-app .chat-form .chat-send-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--chat-primary-color);
    color: var(--chat-white);
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    cursor: pointer;
    transition: var(--chat-transition);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.admin-chat-app .chat-form .chat-send-btn:hover {
    background-color: var(--chat-primary-hover);
    transform: scale(1.1);
}

.admin-chat-app .chat-form .chat-send-btn:active {
    transform: scale(1);
}

.admin-chat-app .chat-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--chat-text-light);
    text-align: center;
    padding: 20px;
}

.admin-chat-app .chat-empty i {
    font-size: 3rem;
    margin-bottom: 20px;
    opacity: 0.3;
    color: var(--chat-primary-color);
}

.admin-chat-app .chat-empty h3 {
    margin: 0 0 10px;
    font-size: 1.2rem;
    color: var(--chat-text-medium);
}

.admin-chat-app .chat-empty p {
    margin: 0;
    font-size: 0.9rem;
}

/* Date separator */
.admin-chat-app .chat-date-separator {
    display: flex;
    align-items: center;
    margin: 20px 0;
    color: var(--chat-text-light);
    font-size: 0.85rem;
}

.admin-chat-app .chat-date-separator::before,
.admin-chat-app .chat-date-separator::after {
    content: "";
    flex: 1;
    height: 1px;
    background-color: var(--chat-border-color);
}

.admin-chat-app .chat-date-separator::before {
    margin-right: 10px;
}

.admin-chat-app .chat-date-separator::after {
    margin-left: 10px;
}

/* Loading spinner */
.admin-chat-app .chat-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--chat-text-light);
}

.admin-chat-app .chat-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(255, 62, 62, 0.1);
    border-radius: 50%;
    border-top-color: var(--chat-primary-color);
    animation: spin 1s ease-in-out infinite;
    margin-bottom: 10px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .chat-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .admin-chat-app .chat-main {
        grid-template-columns: 1fr;
        height: 500px;
    }
    
    .admin-chat-app .chat-users {
        display: none;
    }
    
    .chat-mobile-toggle {
        display: block;
        margin-bottom: 15px;
    }
    
    .chat-mobile-toggle button {
        width: 100%;
        padding: 10px;
        background-color: var(--gray-light);
        border: 1px solid var(--border-color);
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 0.9rem;
        color: var(--text-color);
        cursor: pointer;
    }
    
    .admin-chat-app .chat-users.mobile-visible {
        display: block;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1000;
    }
    
    .admin-chat-app .chat-users-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .admin-chat-app .chat-close-mobile {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--chat-text-medium);
        cursor: pointer;
    }
}
</style>

<!-- Conteneur principal avec styles globaux -->
<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Gestion du chat</h1>
            <p>Répondez aux messages des utilisateurs et gérez les conversations.</p>
        </div>

        <div class="chat-stats">
            <div class="chat-stat-card">
                <div class="chat-stat-icon"><i class="fas fa-users"></i></div>
                <div>
                    <h3>Utilisateurs</h3>
                    <p class="chat-stat-value"><?= number_format($chat_stats['total_users']) ?></p>
                </div>
            </div>
            <div class="chat-stat-card">
                <div class="chat-stat-icon"><i class="fas fa-envelope"></i></div>
                <div>
                    <h3>Messages</h3>
                    <p class="chat-stat-value"><?= number_format($chat_stats['total_messages']) ?></p>
                </div>
            </div>
            <div class="chat-stat-card">
                <div class="chat-stat-icon"><i class="fas fa-bell"></i></div>
                <div>
                    <h3>Non lus</h3>
                    <p class="chat-stat-value"><?= number_format($chat_stats['unread_messages']) ?></p>
                </div>
            </div>
            <div class="chat-stat-card">
                <div class="chat-stat-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <h3>Temps de réponse</h3>
                    <p class="chat-stat-value"><?= number_format($chat_stats['avg_response_time']) ?> min</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages d'alerte -->
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <span><?= $success_message ?></span>
            <button type="button" class="close-btn" onclick="this.parentElement.style.display='none'">×</button>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <span><?= $error_message ?></span>
            <button type="button" class="close-btn" onclick="this.parentElement.style.display='none'">×</button>
        </div>
    <?php endif; ?>

    <!-- Bouton mobile pour afficher la liste des utilisateurs -->
    <div class="chat-mobile-toggle" style="display: none;">
        <button type="button" onclick="toggleUsersList()">
            <i class="fas fa-users"></i> Afficher les conversations
        </button>
    </div>
</div>

<!-- Conteneur spécifique pour l'interface de chat -->
<div class="admin-chat-app">
    <div class="chat-main">
        <!-- Liste des utilisateurs -->
        <div class="chat-users" id="chatUsers">
            <div class="chat-users-header">
                <h2>Conversations</h2>
                <button type="button" class="chat-close-mobile" style="display: none;" onclick="toggleUsersList()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="chat-search">
                    <input type="text" id="userSearchInput" placeholder="Rechercher un utilisateur..." onkeyup="searchUsers()">
                    <button type="button"><i class="fas fa-search"></i></button>
                </div>
                <div class="chat-filter">
                    <select id="userFilterSelect" onchange="filterUsers()">
                        <option value="all">Tous</option>
                        <option value="unread">Non lus</option>
                        <option value="recent">Récents</option>
                    </select>
                </div>
            </div>
            
            <div class="chat-users-list" id="chatUsersList">
                <?php if (empty($users_with_messages)): ?>
                    <div class="chat-empty">
                        <i class="fas fa-inbox"></i>
                        <p>Aucun utilisateur n'a encore envoyé de message.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($users_with_messages as $user): ?>
                        <div class="chat-user-item <?= $selected_user_id == $user['user_id'] ? 'active' : '' ?>" 
                             data-user-id="<?= $user['user_id'] ?>" 
                             data-username="<?= htmlspecialchars($user['username']) ?>"
                             data-unread="<?= $user['unread_count'] ?>"
                             onclick="window.location.href='admin-chat.php?user_id=<?= $user['user_id'] ?>'">
                            <div class="chat-user-initial"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                            <div class="chat-user-info">
                                <div class="chat-user-name"><?= htmlspecialchars($user['username']) ?></div>
                                <div class="chat-user-preview"><?= html_entity_decode(substr($user['last_message'] ?? '', 0, 30)) . (strlen($user['last_message'] ?? '') > 30 ? '...' : '') ?></div>
                            </div>
                            <?php if ($user['unread_count'] > 0): ?>
                                <div class="chat-unread-badge"><?= $user['unread_count'] ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Zone de conversation -->
        <div class="chat-conversation">
            <?php if ($selected_user_id && $selected_user): ?>
                <div class="chat-conversation-header">
                    <div class="chat-selected-user">
                        <div class="chat-selected-initial"><?= strtoupper(substr($selected_user['username'], 0, 1)) ?></div>
                        <div class="chat-selected-info">
                            <h3><?= htmlspecialchars($selected_user['username']) ?></h3>
                            <p><?= htmlspecialchars($selected_user['email']) ?></p>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <button type="button" id="refreshBtn" onclick="loadMessages()">
                            <i class="fas fa-sync-alt"></i> Rafraîchir
                        </button>
                        <button type="button" class="danger" onclick="deleteConversation(<?= $selected_user_id ?>)">
                            <i class="fas fa-trash-alt"></i> Supprimer
                        </button>
                    </div>
                </div>
                
                <div class="chat-filters">
                    <input type="text" id="messageSearchInput" placeholder="Rechercher dans les messages..." value="<?= htmlspecialchars($search_query) ?>">
                    <input type="date" id="messageDateFilter" value="<?= htmlspecialchars($date_filter) ?>">
                    <button type="button" onclick="applyFilters()">
                        <i class="fas fa-filter"></i> Filtrer
                    </button>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($chat_messages)): ?>
                        <div class="chat-empty">
                            <i class="fas fa-comments"></i>
                            <h3>Aucun message</h3>
                            <p>Commencez la conversation en envoyant un message.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $current_date = '';
                        foreach ($chat_messages as $msg): 
                            $msg_date = date('Y-m-d', strtotime($msg['created_at']));
                            if ($current_date != $msg_date) {
                                $current_date = $msg_date;
                                echo '<div class="chat-date-separator">' . date('d/m/Y', strtotime($msg['created_at'])) . '</div>';
                            }
                        ?>
                            <div class="chat-message <?= $msg['is_admin'] ? 'admin' : 'user' ?> <?= $msg['is_deleted'] ? 'deleted' : '' ?>">
                                <div class="chat-message-sender"><?= $msg['is_admin'] ? 'Vous' : htmlspecialchars($msg['username']) ?></div>
                                <div class="chat-message-content"><?= htmlspecialchars($msg['message_text']) ?></div>
                                <div class="chat-message-time"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
                                <?php if (!$msg['is_deleted']): ?>
                                    <button type="button" class="chat-message-delete" onclick="deleteMessage(<?= $msg['message_id'] ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <form class="chat-form" id="chatForm" onsubmit="sendAdminMessage(event)">
                    <input type="hidden" name="user_id" value="<?= $selected_user_id ?>">
                    <input type="text" name="message" id="messageInput" placeholder="Tapez votre réponse..." required>
                    <select id="predefinedResponses" onchange="insertPredefinedResponse()">
                        <option value="">Réponses prédéfinies</option>
                        <option value="En quoi puis-je vous aider ?">En quoi puis-je vous aider ?</option>
                        <option value="Merci pour votre message, nous vous répondrons sous peu.">Merci pour votre message</option>
                        <option value="Désolé, nous ne pouvons pas traiter votre demande pour le moment.">Demande non traitée</option>
                        <option value="Pouvez-vous fournir plus de détails ?">Plus de détails</option>
                        <option value="Votre demande a été prise en compte, merci.">Demande prise en compte</option>
                        <option value="Cordialement, l’équipe Bander-Sneakers.">Cordialement</option>
                    </select>
                    <button type="submit" class="chat-send-btn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            <?php else: ?>
                <div class="chat-empty">
                    <i class="fas fa-comments"></i>
                    <h3>Aucune conversation sélectionnée</h3>
                    <p>Sélectionnez un utilisateur dans la liste pour voir ses messages.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Charger les messages via l'API
function loadMessages() {
    const userId = <?= json_encode($selected_user_id) ?>;
    if (!userId) return;

    const search = document.getElementById('messageSearchInput') ? document.getElementById('messageSearchInput').value : '';
    const date = document.getElementById('messageDateFilter') ? document.getElementById('messageDateFilter').value : '';

    const chatMessages = document.getElementById('chatMessages');
    chatMessages.innerHTML = '<div class="chat-loading"><div class="chat-spinner"></div><p>Chargement des messages...</p></div>';

    fetch(`../chat-api.php?action=get_messages&user_id=${userId}${search ? `&search=${encodeURIComponent(search)}` : ''}${date ? `&date=${encodeURIComponent(date)}` : ''}`)
    .then(response => {
        if (!response.ok) throw new Error('Erreur réseau: ' + response.status);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            chatMessages.innerHTML = '';
            if (data.messages.length === 0) {
                chatMessages.innerHTML = `
                    <div class="chat-empty">
                        <i class="fas fa-comments"></i>
                        <h3>Aucun message</h3>
                        <p>Commencez la conversation en envoyant un message.</p>
                    </div>`;
                return;
            }
            
            let currentDate = '';
            
            data.messages.forEach(msg => {
                const messageDate = new Date(msg.created_at).toISOString().split('T')[0];
                
                if (messageDate !== currentDate) {
                    currentDate = messageDate;
                    const dateDiv = document.createElement('div');
                    dateDiv.className = 'chat-date-separator';
                    dateDiv.textContent = new Date(msg.created_at).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
                    chatMessages.appendChild(dateDiv);
                }
                
                const messageDiv = document.createElement('div');
                messageDiv.className = `chat-message ${msg.is_admin ? 'admin' : 'user'} ${msg.is_deleted ? 'deleted' : ''}`;
                messageDiv.innerHTML = `
                    <div class="chat-message-sender">${msg.is_admin ? 'Vous' : msg.username}</div>
                    <div class="chat-message-content">${msg.message_text}</div>
                    <div class="chat-message-time">${new Date(msg.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}</div>
                    ${!msg.is_deleted ? `
                        <button type="button" class="chat-message-delete" onclick="deleteMessage(${msg.message_id})">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                `;
                chatMessages.appendChild(messageDiv);
            });
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            // Marquer comme lu
            markAsRead(userId);
        } else {
            console.error('Erreur serveur:', data.error);
            chatMessages.innerHTML = `
                <div class="chat-empty">
                    <i class="fas fa-exclamation-circle"></i>
                    <h3>Erreur</h3>
                    <p>Erreur lors du chargement des messages: ${data.error}</p>
                </div>`;
        }
    })
    .catch(error => {
        console.error('Erreur AJAX:', error);
        chatMessages.innerHTML = `
            <div class="chat-empty">
                <i class="fas fa-exclamation-circle"></i>
                <h3>Erreur</h3>
                <p>Erreur de connexion: ${error.message}</p>
            </div>`;
    });
}

// Marquer les messages comme lus
function markAsRead(userId) {
    fetch(`../chat-api.php?action=mark_as_read&user_id=${userId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const userItem = document.querySelector(`.chat-user-item[data-user-id="${userId}"]`);
            if (userItem) {
                const badge = userItem.querySelector('.chat-unread-badge');
                if (badge) badge.remove();
                userItem.setAttribute('data-unread', '0');
            }
        }
    })
    .catch(error => console.error('Erreur lors du marquage comme lu:', error));
}

// Envoyer un message via l'API
function sendAdminMessage(event) {
    event.preventDefault();
    const form = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    if (!messageInput.value.trim()) return;
    
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    
    fetch('../chat-api.php?action=send_message', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Erreur réseau: ' + response.status);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            document.getElementById('predefinedResponses').value = ''; // Réinitialiser le menu déroulant
            loadMessages();
        } else {
            console.error('Erreur serveur:', data.error);
            alert('Erreur lors de l\'envoi du message: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erreur AJAX:', error);
        alert('Erreur de connexion: ' + error.message);
    })
    .finally(() => {
        submitBtn.disabled = false;
    });
}

// Insérer une réponse prédéfinie
function insertPredefinedResponse() {
    const select = document.getElementById('predefinedResponses');
    const messageInput = document.getElementById('messageInput');
    if (select.value) {
        messageInput.value = select.value;
    }
}

// Supprimer un message
function deleteMessage(messageId) {
    if (!confirm('Voulez-vous vraiment supprimer ce message ?')) return;
    
    fetch(`../chat-api.php?action=delete_message&message_id=${messageId}`)
    .then(response => {
        if (!response.ok) throw new Error('Erreur réseau: ' + response.status);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            loadMessages();
        } else {
            console.error('Erreur serveur:', data.error);
            alert('Erreur lors de la suppression: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erreur AJAX:', error);
        alert('Erreur de connexion: ' + error.message);
    });
}

// Supprimer toute la conversation
function deleteConversation(userId) {
    if (!confirm('Voulez-vous vraiment supprimer toute la conversation ?')) return;
    
    fetch(`../chat-api.php?action=delete_conversation&user_id=${userId}`)
    .then(response => {
        if (!response.ok) throw new Error('Erreur réseau: ' + response.status);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            window.location.href = 'admin-chat.php';
        } else {
            console.error('Erreur serveur:', data.error);
            alert('Erreur lors de la suppression: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erreur AJAX:', error);
        alert('Erreur de connexion: ' + error.message);
    });
}

// Appliquer les filtres
function applyFilters() {
    const userId = <?= json_encode($selected_user_id) ?>;
    const search = document.getElementById('messageSearchInput').value;
    const date = document.getElementById('messageDateFilter').value;
    window.location.href = `admin-chat.php?user_id=${userId}&search=${encodeURIComponent(search)}&date=${encodeURIComponent(date)}`;
}

// Rechercher des utilisateurs
function searchUsers() {
    const searchTerm = document.getElementById('userSearchInput').value.toLowerCase();
    const userItems = document.querySelectorAll('.chat-user-item');
    userItems.forEach(item => {
        const username = item.getAttribute('data-username').toLowerCase();
        item.style.display = username.includes(searchTerm) ? 'flex' : 'none';
    });
}

// Filtrer les utilisateurs
function filterUsers() {
    const filterValue = document.getElementById('userFilterSelect').value;
    const userItems = document.querySelectorAll('.chat-user-item');
    userItems.forEach(item => {
        if (filterValue === 'all') {
            item.style.display = 'flex';
        } else if (filterValue === 'unread') {
            const unreadCount = parseInt(item.getAttribute('data-unread'));
            item.style.display = unreadCount > 0 ? 'flex' : 'none';
        } else if (filterValue === 'recent') {
            const index = Array.from(userItems).indexOf(item);
            item.style.display = index < 10 ? 'flex' : 'none';
        }
    });
}

// Afficher/masquer la liste des utilisateurs sur mobile
function toggleUsersList() {
    const chatUsers = document.getElementById('chatUsers');
    chatUsers.classList.toggle('mobile-visible');
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    loadMessages();
    setInterval(loadMessages, 30000); // Rafraîchit toutes les 30 secondes
    
    // Gestion du responsive
    function handleResize() {
        const isMobile = window.innerWidth <= 768;
        document.querySelector('.chat-mobile-toggle').style.display = isMobile ? 'block' : 'none';
        document.querySelector('.chat-close-mobile').style.display = isMobile ? 'block' : 'none';
        
        if (!isMobile) {
            document.getElementById('chatUsers').classList.remove('mobile-visible');
        }
    }
    
    window.addEventListener('resize', handleResize);
    handleResize();
});
</script>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>