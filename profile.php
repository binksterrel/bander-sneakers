<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Vous devez être connecté pour voir un profil.";
    header("Location: login.php");
    exit;
}

// Vérifier si l'ID utilisateur est fourni
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id']) || $_GET['user_id'] <= 0) {
    $_SESSION['error_message'] = "ID utilisateur invalide.";
    header("Location: 2ndhand.php");
    exit;
}

$user_id = (int)$_GET['user_id'];

// Gérer l'abonnement/désabonnement
$subscription_message = '';
$subscription_status = '';
if (isset($_POST['subscribe']) && isLoggedIn()) {
    $subscriber_id = $_SESSION['user_id'];
    $subscribe = $_POST['subscribe'] === 'subscribe';
    if (manageSubscription($subscriber_id, $user_id, $subscribe)) {
        $subscription_message = $subscribe ? "Vous vous êtes abonné avec succès !" : "Vous vous êtes désabonné avec succès.";
        $subscription_status = 'success';
    } else {
        $subscription_message = "Une erreur s'est produite lors de la gestion de l'abonnement.";
        $subscription_status = 'error';
    }
}

// Récupérer les informations du profil
$profile = getUserProfile($user_id);
if (!$profile) {
    $_SESSION['error_message'] = "Utilisateur non trouvé.";
    header("Location: 2ndhand.php");
    exit;
}

// Récupérer les annonces, produits vendus, signalements et abonnés
$products = getUserProducts($user_id);
$sold_count = getSoldCount($user_id);
$report_count = getReportCount($user_id);
$subscriber_count = getSubscriberCount($user_id);
$is_subscribed = isLoggedIn() ? isSubscribed($_SESSION['user_id'], $user_id) : false;

// Calculer l'ancienneté de l'utilisateur en mois
$created_at = new DateTime($profile['created_at']);
$now = new DateTime();
$interval = $created_at->diff($now);
$months_active = $interval->y * 12 + $interval->m;

// Titre et description de la page
$page_title = "Profil de " . htmlspecialchars($profile['username']) . " | Bander-Sneakers";
$page_description = "Découvrez le profil de " . htmlspecialchars($profile['username']) . " sur Bander-Sneakers.";
include 'includes/header.php';
?>

<!-- Ajout de Font Awesome pour les icônes -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li><a href="2ndhand.php">2ndHand</a></li>
            <li class="active"><?= htmlspecialchars($profile['username']) ?></li>
        </ul>
    </div>
</div>

<!-- Profile Section -->
<section class="profile-section">
    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-cover"></div>
            <div class="profile-header-content">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-info">
                    <h1 class="profile-username"><?= htmlspecialchars($profile['username']) ?></h1>
                    <p class="profile-meta">
                        <i class="fas fa-calendar-alt"></i> Inscrit depuis le <?= date('d/m/Y', strtotime($profile['created_at'])) ?>
                    </p>
                    <div class="profile-badges">
                        <span class="badge"><i class="fas fa-check-circle"></i> Email vérifié</span>
                        <span class="badge"><i class="fas fa-clock"></i> Actif depuis <?= $months_active ?> mois</span>
                    </div>
                    <div class="profile-actions">
                        <?php if (isLoggedIn() && $_SESSION['user_id'] != $user_id): ?>
                            <form id="start-conversation-form" method="post" action="start-conversation.php">
                                <input type="hidden" name="seller_id" value="<?= $user_id ?>">
                                <button type="submit" class="btn btn-primary btn-contact">
                                    <i class="fas fa-comment-dots"></i> Contacter
                                </button>
                            </form>
                            <form method="POST" style="display:inline;" id="subscribe-form">
                                <input type="hidden" name="subscribe" value="<?= $is_subscribed ? 'unsubscribe' : 'subscribe' ?>">
                                <button type="submit" class="btn <?= $is_subscribed ? 'btn-unsubscribe' : 'btn-subscribe-action' ?>">
                                    <i class="fas <?= $is_subscribed ? 'fa-user-minus' : 'fa-user-plus' ?>"></i>
                                    <?= $is_subscribed ? 'Se désabonner' : 'S\'abonner' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Stats -->
        <div class="profile-stats-grid">
            <div class="stat-card">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-shopping-bag stat-icon"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-number"><?= $sold_count ?></h3>
                    <p class="stat-label">Produits vendus</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-box-open stat-icon"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-number"><?= count($products) ?></h3>
                    <p class="stat-label">Annonces actives</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-users stat-icon"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-number"><?= $subscriber_count ?></h3>
                    <p class="stat-label">Abonnés</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper">
                    <i class="fas fa-exclamation-triangle stat-icon"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-number"><?= $report_count ?></h3>
                    <p class="stat-label">Signalements</p>
                </div>
            </div>
        </div>

        <!-- Profile Tabs -->
        <div class="profile-tabs">
            <ul class="tab-nav">
                <li class="active"><a href="#dashboard" data-tab="dashboard"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                <li><a href="#products" data-tab="products"><i class="fas fa-tags"></i> Annonces</a></li>
            </ul>

            <!-- Dashboard Tab -->
            <div id="dashboard" class="profile-tab active">
                <div class="tab-header">
                    <h2>Tableau de bord</h2>
                    <p>Voici un aperçu de l'activité de <?= htmlspecialchars($profile['username']) ?> sur Bander-Sneakers.</p>
                </div>
                
                <?php if (count($products) > 0): ?>
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h3><i class="fas fa-fire"></i> Dernières annonces</h3>
                            <?php if (count($products) > 3): ?>
                                <a href="#products" data-tab="products" class="view-all">Voir tout <i class="fas fa-arrow-right"></i></a>
                            <?php endif; ?>
                        </div>
                        <div class="product-grid">
                            <?php foreach (array_slice($products, 0, 3) as $product): ?>
                                <div class="product-card">
                                    <div class="product-image">
                                        <?php
                                        $images = isset($product['images']) && !empty($product['images']) ? explode(',', $product['images']) : [];
                                        $first_image = !empty($images[0]) ? htmlspecialchars($images[0]) : 'assets/images/placeholder.jpg';
                                        ?>
                                        <img src="<?= $first_image ?>" alt="<?= htmlspecialchars($product['title'] ?? 'Produit sans titre') ?>">
                                        <div class="product-status status-<?= htmlspecialchars($product['statut'] ?? 'inconnu') ?>">
                                            <?= ucfirst(htmlspecialchars($product['statut'] ?? 'inconnu')) ?>
                                        </div>
                                        <div class="product-actions">
                                            <a href="2ndhand-detail.php?id=<?= $product['id'] ?>" class="action-btn view-btn" title="Voir l'annonce">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="product-info">
                                        <h3 class="product-title">
                                            <a href="2ndhand-detail.php?id=<?= $product['id'] ?>"><?= htmlspecialchars($product['title'] ?? 'Produit sans titre') ?></a>
                                        </h3>
                                        <div class="product-meta">
                                            <div class="product-price">
                                                <i class="fas fa-tag"></i>
                                                <span class="current-price">
                                                    <?= isset($product['price']) ? number_format((float)$product['price'], 2) : 'Prix non défini' ?> €
                                                </span>
                                            </div>
                                            <div class="product-date">
                                                <i class="far fa-calendar"></i>
                                                <?= isset($product['created_at']) ? date('d/m/Y', strtotime($product['created_at'])) : 'Date inconnue' ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h3>Aucune annonce</h3>
                        <p><?= htmlspecialchars($profile['username']) ?> n'a pas encore publié d'annonces.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Products Tab -->
            <div id="products" class="profile-tab">
                <div class="tab-header">
                    <h2>Annonces de <?= htmlspecialchars($profile['username']) ?></h2>
                    <?php if (count($products) > 0): ?>
                        <div class="filter-sort">
                            <select id="product-sort" class="sort-select">
                                <option value="recent">Plus récentes</option>
                                <option value="price-asc">Prix croissant</option>
                                <option value="price-desc">Prix décroissant</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (count($products) > 0): ?>
                    <div class="product-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card" data-price="<?= isset($product['price']) ? (float)$product['price'] : 0 ?>" data-date="<?= isset($product['created_at']) ? strtotime($product['created_at']) : 0 ?>">
                                <div class="product-image">
                                    <?php
                                    $images = isset($product['images']) && !empty($product['images']) ? explode(',', $product['images']) : [];
                                    $first_image = !empty($images[0]) ? htmlspecialchars($images[0]) : 'assets/images/placeholder.jpg';
                                    ?>
                                    <img src="<?= $first_image ?>" alt="<?= htmlspecialchars($product['title'] ?? 'Produit sans titre') ?>">
                                    <div class="product-status status-<?= htmlspecialchars($product['statut'] ?? 'inconnu') ?>">
                                        <?= ucfirst(htmlspecialchars($product['statut'] ?? 'inconnu')) ?>
                                    </div>
                                    <div class="product-actions">
                                        <a href="2ndhand-detail.php?id=<?= $product['id'] ?>" class="action-btn view-btn" title="Voir l'annonce">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="product-info">
                                    <h3 class="product-title">
                                        <a href="2ndhand-detail.php?id=<?= $product['id'] ?>"><?= htmlspecialchars($product['title'] ?? 'Produit sans titre') ?></a>
                                    </h3>
                                    <div class="product-meta">
                                        <div class="product-price">
                                            <i class="fas fa-tag"></i>
                                            <span class="current-price">
                                                <?= isset($product['price']) ? number_format((float)$product['price'], 2) : 'Prix non défini' ?> €
                                            </span>
                                        </div>
                                        <div class="product-date">
                                            <i class="far fa-calendar"></i>
                                            <?= isset($product['created_at']) ? date('d/m/Y', strtotime($product['created_at'])) : 'Date inconnue' ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h3>Aucune annonce</h3>
                        <p><?= htmlspecialchars($profile['username']) ?> n'a pas encore publié d'annonces.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Toast Container -->
<div id="toast" class="toast"></div>

<style>
/* Variables d'origine conservées */
:root {
    --white: #fff;
    --gray-light: #f8f9fa;
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
    --box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    --transition: all 0.3s ease;
    --subscribe-bg: #28a745;
    --unsubscribe-bg: #dc3545;
}

/* Global Styles */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 15px;
}

/* Breadcrumb */
.breadcrumb {
    background-color: var(--gray-light);
    padding: 1rem 0;
    margin-bottom: 2rem;
}

.breadcrumb-list {
    display: flex;
    flex-wrap: wrap;
}

.breadcrumb-list li {
    position: relative;
    padding-right: 1.5rem;
    margin-right: 0.5rem;
}

.breadcrumb-list li:not(:last-child)::after {
    content: '/';
    position: absolute;
    right: 0;
    top: 0;
    color: var(--text-light);
}

.breadcrumb-list li a {
    color: var(--text-light);
}

.breadcrumb-list li a:hover {
    color: var(--primary-color);
}

.breadcrumb-list li.active {
    color: var(--text-color);
    font-weight: 500;
}

/* Profile Section */
.profile-section {
    padding: 30px 0 60px;
}

/* Profile Header */
.profile-header {
    background-color: var(--white);
    border-radius: 8px;
    box-shadow: var(--box-shadow);
    overflow: hidden;
    margin-bottom: 30px;
    position: relative;
}

.profile-cover {
    height: 220px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
}

.profile-header-content {
    display: flex;
    padding: 20px;
    position: relative;
    margin-top: -50px;
}

.profile-avatar {
    width: 100px;
    height: 100px;
    background: var(--primary-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 2.5rem;
    border: 4px solid var(--white);
    box-shadow: var(--box-shadow);
    margin-right: 20px;
    flex-shrink: 0;
}

.profile-info {
    flex: 1;
    padding-top: 50px;
}

.profile-username {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-color);
    margin: 0 0 5px;
}

.profile-meta {
    display: flex;
    align-items: center;
    font-size: 0.9rem;
    color: var(--text-light);
    margin: 0 0 10px;
}

.profile-meta i {
    margin-right: 5px;
    color: var(--primary-color);
}

.profile-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 15px;
}

.badge {
    display: inline-flex;
    align-items: center;
    background-color: #fff0f0;
    color: var(--primary-color);
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.badge i {
    margin-right: 5px;
}

.profile-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
}

.btn i {
    margin-right: 8px;
}

.btn-primary {
    background-color: var(--primary-color);
    color: var(--white);
}

.btn-primary:hover {
    background-color: var(--primary-dark);
    transform: translateY(-2px);
}

.btn-subscribe-action {
    background-color: var(--subscribe-bg);
    color: var(--white);
}

.btn-subscribe-action:hover {
    background-color: #218838;
    transform: translateY(-2px);
}

.btn-unsubscribe {
    background-color: #666;
    color: var(--white);
}

.btn-unsubscribe:hover {
    background-color: var(--unsubscribe-bg);
    transform: translateY(-2px);
}

/* Profile Stats */
.profile-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background-color: var(--white);
    border-radius: 8px;
    box-shadow: var(--box-shadow);
    padding: 20px;
    display: flex;
    align-items: center;
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.stat-icon-wrapper {
    width: 60px;
    height: 60px;
    background-color: #fff0f0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    flex-shrink: 0;
}

.stat-icon {
    font-size: 1.5rem;
    color: var(--primary-color);
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-color);
    margin: 0;
    line-height: 1.2;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-light);
    margin: 5px 0 0;
}

/* Profile Tabs */
.profile-tabs {
    background-color: var(--white);
    border-radius: 8px;
    box-shadow: var(--box-shadow);
    overflow: hidden;
}

.tab-nav {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
    background-color: var(--gray-light);
    border-bottom: 1px solid var(--border-color);
}

.tab-nav li {
    flex: 1;
    text-align: center;
}

.tab-nav a {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 15px;
    color: var(--text-color);
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
    border-bottom: 3px solid transparent;
}

.tab-nav i {
    margin-right: 8px;
}

.tab-nav li.active a {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
    background-color: var(--white);
}

.tab-nav a:hover {
    color: var(--primary-color);
}

.profile-tab {
    display: none;
    padding: 30px;
}

.profile-tab.active {
    display: block;
}

.tab-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.tab-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-color);
    margin: 0;
}

.tab-header p {
    color: var(--text-light);
    margin: 5px 0 0;
    font-size: 0.95rem;
}

.filter-sort {
    display: flex;
    align-items: center;
}

.sort-select {
    padding: 8px 15px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background-color: var(--white);
    color: var(--text-color);
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition);
}

.sort-select:hover, .sort-select:focus {
    border-color: var(--primary-color);
    outline: none;
}

/* Dashboard Section */
.dashboard-section {
    margin-bottom: 30px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header h3 {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-color);
    margin: 0;
    display: flex;
    align-items: center;
}

.section-header h3 i {
    margin-right: 8px;
    color: var(--primary-color);
}

.view-all {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    transition: var(--transition);
}

.view-all i {
    margin-left: 5px;
    transition: var(--transition);
}

.view-all:hover {
    color: var(--primary-dark);
}

.view-all:hover i {
    transform: translateX(3px);
}

/* Product Grid */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.product-card {
    background-color: var(--white);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--box-shadow);
    transition: var(--transition);
    position: relative;
    border: 1px solid var(--border-color);
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.product-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.product-card:hover .product-image img {
    transform: scale(1.05);
}

.product-status {
    position: absolute;
    top: 10px;
    left: 10px;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    z-index: 1;
}

.status-actif {
    background-color: #d4edda;
    color: var(--success-color);
}

.status-vendu {
    background-color: #d1ecf1;
    color: var(--info-shipped);
}

.status-en_attente {
    background-color: #fff3cd;
    color: var(--warning-color);
}

.status-supprimé {
    background-color: #f8d7da;
    color: var(--error-color);
}

.status-inconnu {
    background-color: var(--gray-light);
    color: var(--text-light);
}

.product-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    opacity: 0;
    transition: var(--transition);
}

.product-card:hover .product-actions {
    opacity: 1;
}

.action-btn {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background-color: var(--white);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: var(--transition);
}

.action-btn:hover {
    background-color: var(--primary-color);
    color: var(--white);
    transform: scale(1.1);
}

.product-info {
    padding: 15px;
}

.product-title {
    margin: 0 0 10px;
    font-size: 1rem;
    line-height: 1.4;
}

.product-title a {
    color: var(--text-color);
    text-decoration: none;
    transition: var(--transition);
    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-title a:hover {
    color: var(--primary-color);
}

.product-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
}

.product-price {
    display: flex;
    align-items: center;
    color: var(--primary-color);
    font-weight: 600;
}

.product-price i {
    margin-right: 5px;
    font-size: 0.8rem;
}

.product-date {
    display: flex;
    align-items: center;
    color: var(--text-light);
}

.product-date i {
    margin-right: 5px;
    font-size: 0.8rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-icon {
    width: 80px;
    height: 80px;
    background-color: #fff0f0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.empty-icon i {
    font-size: 2rem;
    color: var(--primary-color);
}

.empty-state h3 {
    font-size: 1.2rem;
    color: var(--text-color);
    margin: 0 0 10px;
}

.empty-state p {
    color: var(--text-light);
    margin: 0;
}

/* Toast */
.toast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background-color: var(--success-color);
    color: var(--white);
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    z-index: 1000;
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.3s ease, transform 0.3s ease;
    max-width: 350px;
}

.toast.show {
    opacity: 1;
    transform: translateY(0);
}

.toast.error {
    background-color: var(--error-color);
}

.toast i {
    margin-right: 10px;
    font-size: 1.2rem;
}

/* Responsive Styles */
@media (max-width: 768px) {
    .profile-header-content {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .profile-avatar {
        margin-right: 0;
        margin-bottom: 15px;
    }
    
    .profile-info {
        padding-top: 0;
    }
    
    .profile-badges {
        justify-content: center;
    }
    
    .profile-actions {
        justify-content: center;
    }
    
    .profile-tab {
        padding: 20px 15px;
    }
    
    .tab-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
}

@media (max-width: 576px) {
    .profile-stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .tab-nav a {
        padding: 10px;
        font-size: 0.85rem;
    }
    
    .tab-nav i {
        margin-right: 5px;
    }
    
    .product-grid {
        grid-template-columns: 1fr;
    }
    
    .toast {
        left: 20px;
        right: 20px;
        max-width: none;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des onglets
    const tabLinks = document.querySelectorAll('.tab-nav a[data-tab]');
    const tabs = document.querySelectorAll('.profile-tab');
    
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

    // Gestion de l'ancre dans l'URL
    const hash = window.location.hash.replace('#', '');
    if (hash) {
        const targetTab = document.getElementById(hash);
        const targetLink = document.querySelector(`.tab-nav a[data-tab="${hash}"]`);
        if (targetTab && targetLink) {
            tabLinks.forEach(l => l.parentElement.classList.remove('active'));
            tabs.forEach(t => t.classList.remove('active'));
            targetLink.parentElement.classList.add('active');
            targetTab.classList.add('active');
        }
    }

    // Fonction pour afficher le toast
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        
        toast.innerHTML = `<i class="fas ${icon}"></i> ${message}`;
        toast.className = 'toast';
        if (type === 'error') toast.classList.add('error');
        
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    // Gestion du formulaire "Contacter" via AJAX
    const contactForm = document.getElementById('start-conversation-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('start-conversation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000); // Redirection après 1 seconde pour voir le toast
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erreur lors de la requête AJAX:', error);
                showToast('Une erreur s\'est produite.', 'error');
            });
        });
    }

    // Gestion du formulaire d'abonnement via AJAX
    const subscribeForm = document.getElementById('subscribe-form');
    if (subscribeForm) {
        subscribeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const subscribeAction = formData.get('subscribe');
            const message = subscribeAction === 'subscribe' ? "Vous vous êtes abonné avec succès !" : "Vous vous êtes désabonné avec succès.";
            const errorMessage = "Une erreur s'est produite lors de la gestion de l'abonnement.";

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    showToast(message, 'success');
                    
                    const btn = this.querySelector('button');
                    const icon = btn.querySelector('i');
                    
                    if (subscribeAction === 'subscribe') {
                        btn.classList.remove('btn-subscribe-action');
                        btn.classList.add('btn-unsubscribe');
                        btn.innerHTML = '<i class="fas fa-user-minus"></i> Se désabonner';
                        this.querySelector('input[name="subscribe"]').value = 'unsubscribe';
                    } else {
                        btn.classList.remove('btn-unsubscribe');
                        btn.classList.add('btn-subscribe-action');
                        btn.innerHTML = '<i class="fas fa-user-plus"></i> S\'abonner';
                        this.querySelector('input[name="subscribe"]').value = 'subscribe';
                    }
                } else {
                    showToast(errorMessage, 'error');
                }
            })
            .catch(error => {
                console.error('Erreur lors de la requête AJAX:', error);
                showToast(errorMessage, 'error');
            });
        });
    }

    // Tri des produits
    const productSort = document.getElementById('product-sort');
    if (productSort) {
        productSort.addEventListener('change', function() {
            const sortValue = this.value;
            const productGrid = document.querySelector('#products .product-grid');
            const products = Array.from(productGrid.querySelectorAll('.product-card'));
            
            products.sort((a, b) => {
                if (sortValue === 'price-asc') {
                    return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                } else if (sortValue === 'price-desc') {
                    return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                } else {
                    return parseInt(b.dataset.date) - parseInt(a.dataset.date);
                }
            });
            
            productGrid.innerHTML = '';
            products.forEach(product => productGrid.appendChild(product));
        });
    }

    // Afficher les messages d'abonnement initiaux
    const subscriptionMessage = <?= json_encode($subscription_message) ?>;
    const subscriptionStatus = <?= json_encode($subscription_status) ?>;
    if (subscriptionMessage) {
        showToast(subscriptionMessage, subscriptionStatus);
    }
});
</script>

<?php include 'includes/footer.php'; ?>