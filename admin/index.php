<?php
session_start();

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

// Inclure la configuration et les fonctions
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Initialiser la connexion à la base de données
$db = getDbConnection();

// Messages de session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Période de temps (par défaut: 30 derniers jours)
$time_period = $_GET['period'] ?? '30days';
$custom_start = $_GET['start'] ?? '';
$custom_end = $_GET['end'] ?? '';

// Déterminer les dates de début et de fin en fonction de la période
$end_date = date('Y-m-d');
switch ($time_period) {
    case '7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $period_label = '7 derniers jours';
        break;
    case '30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_label = '30 derniers jours';
        break;
    case '90days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $period_label = '90 derniers jours';
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-1 year'));
        $period_label = '12 derniers mois';
        break;
    case 'custom':
        $start_date = $custom_start;
        $end_date = $custom_end;
        $period_label = 'Période personnalisée';
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_label = '30 derniers jours';
}

// Récupérer les statistiques générales
try {
    // Statistiques de base
    $stats = [
        'users' => $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn(),
        'products' => $db->query("SELECT COUNT(*) FROM sneakers")->fetchColumn(),
        'orders' => $db->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('pending','processing','shipped','delivered','cancelled')")->fetchColumn(),
        'revenue' => $db->query("SELECT SUM(total_amount) FROM orders WHERE order_status IN ('pending','processing','shipped','delivered')")->fetchColumn() ?: 0,
        'avg_order' => $db->query("SELECT AVG(total_amount) FROM orders WHERE order_status IN ('pending','processing','shipped','delivered','cancelled')")->fetchColumn() ?: 0,
        'secondhand' => $db->query("SELECT COUNT(*) FROM secondhand_products WHERE statut = 'actif'")->fetchColumn() ?: 0
    ];

    // Statistiques de la période sélectionnée
    $period_stats = [
        'new_users' => $db->query("SELECT COUNT(*) FROM users WHERE created_at BETWEEN '$start_date' AND '$end_date' AND is_admin = 0")->fetchColumn(),
        'orders' => $db->query("SELECT COUNT(*) FROM orders WHERE created_at BETWEEN '$start_date' AND '$end_date' AND order_status IN ('pending','processing','shipped','delivered','cancelled')")->fetchColumn(),
        'revenue' => $db->query("SELECT SUM(total_amount) FROM orders WHERE created_at BETWEEN '$start_date' AND '$end_date' AND order_status IN ('pending','processing','shipped','delivered','cancelled')")->fetchColumn() ?: 0,
        'avg_order' => $db->query("SELECT AVG(total_amount) FROM orders WHERE created_at BETWEEN '$start_date' AND '$end_date' AND order_status IN ('pending','processing','shipped','delivered','cancelled')")->fetchColumn() ?: 0,
        'conversion_rate' => 0 // Calculé ci-dessous
    ];

    // Calcul du taux de conversion (commandes / visiteurs uniques)
    $visitors = $db->query("SELECT COUNT(DISTINCT user_id) FROM cart WHERE created_at BETWEEN '$start_date' AND '$end_date'")->fetchColumn();
    $period_stats['conversion_rate'] = $visitors > 0 ? ($period_stats['orders'] / $visitors) * 100 : 0;

    // Clients les plus actifs (top 10)
    $stmt = $db->query("
        SELECT u.user_id, u.username, u.email, COUNT(o.order_id) as total_orders, SUM(o.total_amount) as total_spent,
               MAX(o.created_at) as last_order_date
        FROM users u
        LEFT JOIN orders o ON u.user_id = o.user_id
        WHERE o.order_status IN ('pending','processing','shipped','delivered','cancelled')
        GROUP BY u.user_id, u.username, u.email
        ORDER BY total_orders DESC
        LIMIT 10
    ");
    $top_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Achats par marque
    $stmt = $db->query("
        SELECT b.brand_id, b.brand_name, COUNT(oi.order_item_id) as total_items, 
               SUM(oi.price * oi.quantity) as total_revenue
        FROM brands b
        LEFT JOIN sneakers s ON b.brand_id = s.brand_id
        LEFT JOIN order_items oi ON s.sneaker_id = oi.sneaker_id
        LEFT JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_status IN ('pending','processing','shipped','delivered','cancelled')
        GROUP BY b.brand_id, b.brand_name
        ORDER BY total_revenue DESC
    ");
    $purchases_by_brand = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Revenus par mois (12 derniers mois)
    $stmt = $db->query("
        SELECT DATE_FORMAT(o.created_at, '%Y-%m') as month, 
               SUM(o.total_amount) as total,
               COUNT(o.order_id) as order_count
        FROM orders o
        WHERE o.order_status IN ('pending','processing','shipped','delivered','cancelled')
        GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
        ORDER BY month ASC
        LIMIT 12
    ");
    $revenue_by_month = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Produits les plus vendus
    $stmt = $db->query("
        SELECT s.sneaker_id, s.sneaker_name, s.price, b.brand_name, 
               COUNT(oi.order_item_id) as total_sold,
               SUM(oi.quantity) as total_quantity,
               SUM(oi.price * oi.quantity) as total_revenue
        FROM sneakers s
        JOIN brands b ON s.brand_id = b.brand_id
        JOIN order_items oi ON s.sneaker_id = oi.sneaker_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_status IN ('pending','processing','shipped','delivered','cancelled')
        GROUP BY s.sneaker_id, s.sneaker_name, s.price, b.brand_name
        ORDER BY total_quantity DESC
        LIMIT 10
    ");
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Répartition des commandes par statut
    $stmt = $db->query("
        SELECT order_status, COUNT(*) as count
        FROM orders
        GROUP BY order_status
        ORDER BY count DESC
    ");
    $orders_by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Répartition des ventes par catégorie
    $stmt = $db->query("
        SELECT c.category_name, COUNT(oi.order_item_id) as total_sold,
               SUM(oi.price * oi.quantity) as total_revenue
        FROM categories c
        JOIN sneakers s ON c.category_id = s.category_id
        JOIN order_items oi ON s.sneaker_id = oi.sneaker_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_status IN ('pending','processing','shipped','delivered','cancelled')
        GROUP BY c.category_name
        ORDER BY total_revenue DESC
    ");
    $sales_by_category = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Répartition des ventes par genre
    $stmt = $db->query("
        SELECT s.gender, COUNT(oi.order_item_id) as total_sold,
               SUM(oi.price * oi.quantity) as total_revenue
        FROM sneakers s
        JOIN order_items oi ON s.sneaker_id = oi.sneaker_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_status IN ('pending','processing','shipped','delivered','cancelled')
        GROUP BY s.gender
        ORDER BY total_revenue DESC
    ");
    $sales_by_gender = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tailles les plus populaires
    $stmt = $db->query("
        SELECT sz.size_value, COUNT(oi.order_item_id) as total_sold
        FROM sizes sz
        JOIN order_items oi ON sz.size_id = oi.size_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_status IN ('pending','processing','shipped','delivered','cancelled')
        GROUP BY sz.size_value
        ORDER BY total_sold DESC
        LIMIT 10
    ");
    $popular_sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques des utilisateurs
    $user_stats = [
        'newsletter' => $db->query("SELECT COUNT(*) FROM users WHERE newsletter_subscribed = 1")->fetchColumn(),
        'newsletter_rate' => $db->query("SELECT (COUNT(CASE WHEN newsletter_subscribed = 1 THEN 1 END) / COUNT(*)) * 100 FROM users WHERE is_admin = 0")->fetchColumn(),
        'loyalty_points_avg' => $db->query("SELECT AVG(points) FROM loyalty_points")->fetchColumn() ?: 0,
        'total_subscribers' => $db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active = 1")->fetchColumn()
    ];

    // Statistiques des produits d'occasion
    $secondhand_stats = [
        'active' => $db->query("SELECT COUNT(*) FROM secondhand_products WHERE statut = 'actif'")->fetchColumn(),
        'sold' => $db->query("SELECT COUNT(*) FROM secondhand_products WHERE statut = 'vendu'")->fetchColumn(),
        'pending' => $db->query("SELECT COUNT(*) FROM secondhand_products WHERE statut = 'en attente'")->fetchColumn(),
        'avg_price' => $db->query("SELECT AVG(price) FROM secondhand_products")->fetchColumn() ?: 0,
        'total_views' => $db->query("SELECT SUM(views) FROM secondhand_products")->fetchColumn() ?: 0
    ];

    // Statistiques des avis et commentaires
    $review_stats = [
        'total' => $db->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
        'avg_rating' => $db->query("SELECT AVG(rating) FROM reviews")->fetchColumn() ?: 0,
        'comments' => $db->query("SELECT COUNT(*) FROM comments")->fetchColumn()
    ];

    // Statistiques des messages et conversations
    $message_stats = [
        'conversations' => $db->query("SELECT COUNT(*) FROM conversations")->fetchColumn(),
        'messages' => $db->query("SELECT COUNT(*) FROM messages")->fetchColumn(),
        'unread' => $db->query("SELECT COUNT(*) FROM messages WHERE is_read = 0")->fetchColumn()
    ];

    // Produits avec stock faible
    $stmt = $db->query("
        SELECT s.sneaker_id, s.sneaker_name, s.stock_quantity, b.brand_name
        FROM sneakers s
        JOIN brands b ON s.brand_id = b.brand_id
        WHERE s.stock_quantity < 10 AND s.stock_quantity > 0
        ORDER BY s.stock_quantity ASC
        LIMIT 5
    ");
    $low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Produits les plus consultés (secondhand)
    $stmt = $db->query("
        SELECT id, title, price, views, user_id
        FROM secondhand_products
        WHERE statut = 'actif'
        ORDER BY views DESC
        LIMIT 5
    ");
    $most_viewed_secondhand = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatage des données pour Chart.js
    $brand_labels = array_column($purchases_by_brand, 'brand_name');
    $brand_data = array_column($purchases_by_brand, 'total_revenue');
    $brand_items = array_column($purchases_by_brand, 'total_items');

    $month_labels = array_map(fn($item) => (new DateTime($item['month'] . '-01'))->format('m/Y'), $revenue_by_month);
    $month_data = array_column($revenue_by_month, 'total');
    $month_orders = array_column($revenue_by_month, 'order_count');

    $status_labels = array_column($orders_by_status, 'order_status');
    $status_data = array_column($orders_by_status, 'count');

    $category_labels = array_column($sales_by_category, 'category_name');
    $category_data = array_column($sales_by_category, 'total_revenue');

    $gender_labels = array_column($sales_by_gender, 'gender');
    $gender_data = array_column($sales_by_gender, 'total_revenue');

    $size_labels = array_column($popular_sizes, 'size_value');
    $size_data = array_column($popular_sizes, 'total_sold');

    // Calcul des tendances (comparaison avec la période précédente)
    $previous_start_date = date('Y-m-d', strtotime($start_date . ' -' . (strtotime($end_date) - strtotime($start_date)) . ' seconds'));
    $previous_end_date = date('Y-m-d', strtotime($end_date . ' -' . (strtotime($end_date) - strtotime($start_date)) . ' seconds'));
    
    $previous_revenue = $db->query("SELECT SUM(total_amount) FROM orders WHERE created_at BETWEEN '$previous_start_date' AND '$previous_end_date' AND order_status IN ('pending','processing','shipped','delivered','cancelled')")->fetchColumn() ?: 0;
    $previous_orders = $db->query("SELECT COUNT(*) FROM orders WHERE created_at BETWEEN '$previous_start_date' AND '$previous_end_date' AND order_status IN ('pending','processing','shipped','delivered','cancelled')")->fetchColumn();
    $previous_users = $db->query("SELECT COUNT(*) FROM users WHERE created_at BETWEEN '$previous_start_date' AND '$previous_end_date' AND is_admin = 0")->fetchColumn();
    
    $trends = [
        'revenue' => $previous_revenue > 0 ? (($period_stats['revenue'] - $previous_revenue) / $previous_revenue) * 100 : 100,
        'orders' => $previous_orders > 0 ? (($period_stats['orders'] - $previous_orders) / $previous_orders) * 100 : 100,
        'users' => $previous_users > 0 ? (($period_stats['new_users'] - $previous_users) / $previous_users) * 100 : 100
    ];

} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des statistiques : " . $e->getMessage();
}

// Titre de la page
$page_title = "Statistiques - Admin Bander-Sneakers";
include 'includes/header.php';
?>

<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Tableau de bord analytique</h1>
            <p>Statistiques pour la période: <strong><?= htmlspecialchars($period_label) ?></strong></p>
        </div>
        <div class="header-actions">
            <div class="period-selector">
                <form id="period-form" method="GET" action="">
                    <div class="form-group">
                        <select name="period" id="period-select" class="form-control">
                            <option value="7days" <?= $time_period == '7days' ? 'selected' : '' ?>>7 derniers jours</option>
                            <option value="30days" <?= $time_period == '30days' ? 'selected' : '' ?>>30 derniers jours</option>
                            <option value="90days" <?= $time_period == '90days' ? 'selected' : '' ?>>90 derniers jours</option>
                            <option value="year" <?= $time_period == 'year' ? 'selected' : '' ?>>12 derniers mois</option>
                            <option value="custom" <?= $time_period == 'custom' ? 'selected' : '' ?>>Personnalisé</option>
                        </select>
                    </div>
                    <div id="custom-dates" class="<?= $time_period == 'custom' ? '' : 'hidden' ?>">
                        <div class="form-group">
                            <label for="start">Du:</label>
                            <input type="date" id="start" name="start" value="<?= htmlspecialchars($custom_start) ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="end">Au:</label>
                            <input type="date" id="end" name="end" value="<?= htmlspecialchars($custom_end) ?>" class="form-control">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Appliquer</button>
                </form>
            </div>
            <div class="action-buttons">
                <button id="print-stats" class="btn btn-outline">
                    <i class="fas fa-print"></i> Imprimer
                </button>
                <div class="dropdown">
                    <button class="btn btn-outline dropdown-toggle">
                        <i class="fas fa-download"></i> Exporter
                    </button>
                    <div class="dropdown-menu">
                        <a href="export.php?format=csv&period=<?= $time_period ?>" class="dropdown-item">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                        <a href="export.php?format=excel&period=<?= $time_period ?>" class="dropdown-item">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="export.php?format=pdf&period=<?= $time_period ?>" class="dropdown-item">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($success_message): ?>
        <div class="stats-alert success">
            <i class="fas fa-check-circle"></i> <?= $success_message ?>
            <button type="button" class="close-alert">&times;</button>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="stats-alert error">
            <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            <button type="button" class="close-alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Résumé des statistiques principales -->
    <div class="stats-overview">
        <div class="stats-section">
            <h2>Statistiques clés</h2>
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Revenus</h3>
                        <p class="stat-value"><?= number_format($stats['revenue'], 2) ?> €</p>
                        <div class="stat-trend <?= $trends['revenue'] >= 0 ? 'positive' : 'negative' ?>">
                            <i class="fas fa-<?= $trends['revenue'] >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                            <span><?= number_format(abs($trends['revenue']), 1) ?>%</span>
                        </div>
                        <p class="stat-period"><?= number_format($period_stats['revenue'], 2) ?> € sur la période</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Commandes</h3>
                        <p class="stat-value"><?= number_format($stats['orders']) ?></p>
                        <div class="stat-trend <?= $trends['orders'] >= 0 ? 'positive' : 'negative' ?>">
                            <i class="fas fa-<?= $trends['orders'] >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                            <span><?= number_format(abs($trends['orders']), 1) ?>%</span>
                        </div>
                        <p class="stat-period"><?= number_format($period_stats['orders']) ?> sur la période</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Clients</h3>
                        <p class="stat-value"><?= number_format($stats['users']) ?></p>
                        <div class="stat-trend <?= $trends['users'] >= 0 ? 'positive' : 'negative' ?>">
                            <i class="fas fa-<?= $trends['users'] >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                            <span><?= number_format(abs($trends['users']), 1) ?>%</span>
                        </div>
                        <p class="stat-period"><?= number_format($period_stats['new_users']) ?> nouveaux sur la période</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Panier moyen</h3>
                        <p class="stat-value"><?= number_format($stats['avg_order'], 2) ?> €</p>
                        <div class="stat-trend <?= $period_stats['avg_order'] >= $stats['avg_order'] ? 'positive' : 'negative' ?>">
                            <i class="fas fa-<?= $period_stats['avg_order'] >= $stats['avg_order'] ? 'arrow-up' : 'arrow-down' ?>"></i>
                            <span><?= number_format(abs(($period_stats['avg_order'] - $stats['avg_order']) / $stats['avg_order'] * 100), 1) ?>%</span>
                        </div>
                        <p class="stat-period"><?= number_format($period_stats['avg_order'], 2) ?> € sur la période</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphiques principaux -->
    <div class="stats-charts">
        <div class="chart-row">
            <div class="chart-card large">
                <div class="chart-header">
                    <h3>Évolution des revenus et commandes</h3>
                    <div class="chart-actions">
                        <button class="btn-icon toggle-view active" data-chart="revenueChart" data-view="line">
                            <i class="fas fa-chart-line"></i>
                        </button>
                        <button class="btn-icon toggle-view" data-chart="revenueChart" data-view="bar">
                            <i class="fas fa-chart-bar"></i>
                        </button>
                    </div>
                </div>
                <div class="chart-body">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
        <div class="chart-row">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Répartition par marque</h3>
                    <div class="chart-actions">
                        <button class="btn-icon toggle-view active" data-chart="brandChart" data-view="pie">
                            <i class="fas fa-chart-pie"></i>
                        </button>
                        <button class="btn-icon toggle-view" data-chart="brandChart" data-view="doughnut">
                            <i class="fas fa-circle-notch"></i>
                        </button>
                    </div>
                </div>
                <div class="chart-body">
                    <canvas id="brandChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Ventes par catégorie</h3>
                </div>
                <div class="chart-body">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Statuts des commandes</h3>
                </div>
                <div class="chart-body">
                    <canvas id="orderStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableaux de données -->
    <div class="stats-tables">
        <div class="table-row">
            <div class="table-card">
                <div class="table-header">
                    <h3>Top 10 produits</h3>
                    <div class="table-actions">
                        <button class="btn-icon" id="export-top-products" title="Exporter">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </div>
                <div class="table-body">
                    <?php if (empty($top_products)): ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <p>Aucune donnée disponible</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="stats-table">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>Marque</th>
                                        <th>Prix</th>
                                        <th>Quantité</th>
                                        <th>Revenus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_products as $product): ?>
                                        <tr>
                                            <td>
                                                <a href="product.php?id=<?= $product['sneaker_id'] ?>">
                                                    <?= htmlspecialchars($product['sneaker_name']) ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($product['brand_name']) ?></td>
                                            <td><?= number_format($product['price'], 2) ?> €</td>
                                            <td><?= number_format($product['total_quantity']) ?></td>
                                            <td><?= number_format($product['total_revenue'], 2) ?> €</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="table-row">
            <div class="table-card">
                <div class="table-header">
                    <h3>Clients les plus actifs</h3>
                    <div class="table-actions">
                        <button class="btn-icon" id="export-top-clients" title="Exporter">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </div>
                <div class="table-body">
                    <?php if (empty($top_clients)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>Aucune donnée disponible</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="stats-table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Email</th>
                                        <th>Commandes</th>
                                        <th>Total dépensé</th>
                                        <th>Dernière commande</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_clients as $client): ?>
                                        <tr>
                                            <td>
                                                <a href="users.php?edit=<?= $client['user_id'] ?>">
                                                    <?= htmlspecialchars($client['username']) ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($client['email']) ?></td>
                                            <td><?= number_format($client['total_orders']) ?></td>
                                            <td><?= number_format($client['total_spent'], 2) ?> €</td>
                                            <td><?= date('d/m/Y', strtotime($client['last_order_date'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="table-row two-columns">
            <div class="table-card">
                <div class="table-header">
                    <h3>Produits à stock faible</h3>
                </div>
                <div class="table-body">
                    <?php if (empty($low_stock_products)): ?>
                        <div class="empty-state">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>Aucun produit à stock faible</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="stats-table">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>Marque</th>
                                        <th>Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($low_stock_products as $product): ?>
                                        <tr class="<?= $product['stock_quantity'] <= 5 ? 'warning-row' : '' ?>">
                                            <td>
                                                <a href="products.php?id=<?= $product['sneaker_id'] ?>">
                                                    <?= htmlspecialchars($product['sneaker_name']) ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($product['brand_name']) ?></td>
                                            <td>
                                                <span class="stock-badge <?= $product['stock_quantity'] <= 5 ? 'critical' : 'low' ?>">
                                                    <?= $product['stock_quantity'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-card">
                <div class="table-header">
                    <h3>Produits d'occasion populaires</h3>
                </div>
                <div class="table-body">
                    <?php if (empty($most_viewed_secondhand)): ?>
                        <div class="empty-state">
                            <i class="fas fa-eye-slash"></i>
                            <p>Aucune donnée disponible</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="stats-table">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>Prix</th>
                                        <th>Vues</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($most_viewed_secondhand as $product): ?>
                                        <tr>
                                            <td>
                                                <a href="secondhand.php?id=<?= $product['id'] ?>">
                                                    <?= htmlspecialchars($product['title']) ?>
                                                </a>
                                            </td>
                                            <td><?= number_format($product['price'], 2) ?> €</td>
                                            <td><?= number_format($product['views']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques supplémentaires -->
    <div class="stats-additional">
        <div class="stats-section">
            <h2>Statistiques détaillées</h2>
            <div class="stats-grid">
                <!-- Statistiques des produits d'occasion -->
                <div class="stats-panel">
                    <div class="panel-header">
                        <h3><i class="fas fa-handshake"></i> Produits d'occasion</h3>
                    </div>
                    <div class="panel-body">
                        <div class="mini-stats">
                            <div class="mini-stat">
                                <div class="mini-stat-label">Actifs</div>
                                <div class="mini-stat-value"><?= number_format($secondhand_stats['active']) ?></div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-label">Vendus</div>
                                <div class="mini-stat-value"><?= number_format($secondhand_stats['sold']) ?></div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-label">En attente</div>
                                <div class="mini-stat-value"><?= number_format($secondhand_stats['pending']) ?></div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-label">Prix moyen</div>
                                <div class="mini-stat-value"><?= number_format($secondhand_stats['avg_price'], 2) ?> €</div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-label">Vues totales</div>
                                <div class="mini-stat-value"><?= number_format($secondhand_stats['total_views']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiques des utilisateurs -->
                <div class="stats-panel">
                    <div class="panel-header">
                        <h3><i class="fas fa-user-circle"></i> Utilisateurs</h3>
                    </div>
                    <div class="panel-body">
                        <div class="mini-stats">
                            <div class="mini-stat">
                                <div class="mini-stat-label">Newsletter</div>
                                <div class="mini-stat-value"><?= number_format($user_stats['newsletter']) ?></div>
                                <div class="mini-stat-subtext"><?= number_format($user_stats['newsletter_rate'], 1) ?>% des clients</div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-label">Points fidélité</div>
                                <div class="mini-stat-value"><?= number_format($user_stats['loyalty_points_avg'], 0) ?></div>
                                <div class="mini-stat-subtext">moyenne par client</div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-label">Abonnés</div>
                                <div class="mini-stat-value"><?= number_format($user_stats['total_subscribers']) ?></div>
                                <div class="mini-stat-subtext">newsletter externe incluse</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiques des avis et commentaires -->
                <div class="stats-panel">
                    <div class="panel-header">
                        <h3><i class="fas fa-star"></i> Avis et commentaires</h3>
                    </div>
                    <div class="panel-body">
                        <div class="mini-stats">
                            <div class="mini-stat">
                                <div class="mini-stat-label">Avis produits</div>
                                <div class="mini-stat-value"><?= number_format($review_stats['total']) ?></div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-label">Note moyenne</div>
                                <div class="mini-stat-value">
                                    <?= number_format($review_stats['avg_rating'], 1) ?>
                                    <i class="fas fa-star star-icon"></i>
                                </div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-label">Commentaires</div>
                                <div class="mini-stat-value"><?= number_format($review_stats['comments']) ?></div>
                                <div class="mini-stat-subtext">sur produits d'occasion</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiques des messages -->
                <div class="stats-panel">
                    <div class="panel-header">
                        <h3><i class="fas fa-comments"></i> Messages</h3>
                    </div>
                    <div class="panel-body">
                        <div class="mini-stats">
                            <div class="mini-stat">
                                <div class="mini-stat-label">Conversations</div>
                                <div class="mini-stat-value"><?= number_format($message_stats['conversations']) ?></div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-label">Messages</div>
                                <div class="mini-stat-value"><?= number_format($message_stats['messages']) ?></div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-label">Non lus</div>
                                <div class="mini-stat-value"><?= number_format($message_stats['unread']) ?></div>
                                <?php if ($message_stats['unread'] > 0): ?>
                                    <div class="mini-stat-subtext urgent">Attention requise</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphiques secondaires -->
    <div class="stats-charts">
        <div class="chart-row">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Ventes par genre</h3>
                </div>
                <div class="chart-body">
                    <canvas id="genderChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Tailles populaires</h3>
                </div>
                <div class="chart-body">
                    <canvas id="sizeChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Fin du contenu spécifique à la page -->

<!-- Styles spécifiques à la page de statistiques -->
<style>
/* Styles pour la page de statistiques uniquement - n'affecte pas le header/footer */
.stats-dashboard {
    padding: 20px;
    max-width: 1600px;
    margin: 0 auto;
    font-family: inherit;
}

/* Header Styles */
.stats-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.header-title h1 {
    font-size: 1.8rem;
    margin: 0;
    color: var(--text-color, #333);
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-title h1 i {
    color: var(--primary-color, #ff3e3e);
}

.period-info {
    margin: 5px 0 0;
    color: var(--text-light, #666);
    font-size: 0.9rem;
}

.header-actions {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.period-selector {
    background: white;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

#period-form {
    display: flex;
    gap: 10px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.form-group {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-size: 0.85rem;
    color: var(--text-light, #666);
}

.form-control {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.9rem;
}

.hidden {
    display: none;
}

#custom-dates {
    display: flex;
    gap: 10px;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 8px 15px;
    border-radius: 4px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.btn-primary {
    background-color: var(--primary-color, #ff3e3e);
    color: white;
}

.btn-primary:hover {
    background-color: var(--primary-dark, #e62e2e);
}

.btn-outline {
    background-color: transparent;
    border: 1px solid #ddd;
    color: var(--text-light, #666);
}

.btn-outline:hover {
    background-color: #f5f5f5;
    border-color: #ccc;
}

.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-toggle::after {
    content: '';
    display: inline-block;
    margin-left: 5px;
    vertical-align: middle;
    border-top: 4px solid;
    border-right: 4px solid transparent;
    border-left: 4px solid transparent;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    z-index: 1000;
    display: none;
    min-width: 160px;
    padding: 5px 0;
    margin: 2px 0 0;
    background-color: white;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.dropdown:hover .dropdown-menu {
    display: block;
}

.dropdown-item {
    display: block;
    padding: 8px 15px;
    clear: both;
    font-weight: 400;
    color: var(--text-light, #666);
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.3s ease;
}

.dropdown-item:hover {
    background-color: #f5f5f5;
    color: var(--text-color, #333);
}

/* Alert Styles */
.stats-alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    position: relative;
}

.stats-alert i {
    margin-right: 10px;
    font-size: 1.1rem;
}

.stats-alert.success {
    background-color: #d4edda;
    color: #155724;
}

.stats-alert.error {
    background-color: #f8d7da;
    color: #721c24;
}

.close-alert {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: inherit;
    opacity: 0.7;
}

.close-alert:hover {
    opacity: 1;
}

/* Stats Section */
.stats-section {
    margin-bottom: 30px;
}

.stats-section h2 {
    font-size: 1.3rem;
    margin: 0 0 15px;
    color: var(--text-color, #333);
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

/* Stats Cards */
.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.stat-icon.primary {
    background-color: rgba(255, 62, 62, 0.1);
    color: var(--primary-color, #ff3e3e);
}

.stat-icon.success {
    background-color: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.stat-icon.warning {
    background-color: rgba(255, 193, 7, 0.1);
    color: #ffc107;
}

.stat-icon.info {
    background-color: rgba(23, 162, 184, 0.1);
    color: #17a2b8;
}

.stat-content {
    flex: 1;
}

.stat-content h3 {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-light, #666);
    font-weight: 500;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 5px 0;
    color: var(--text-color, #333);
}

.stat-trend {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-bottom: 5px;
}

.stat-trend.positive {
    background-color: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.stat-trend.negative {
    background-color: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}

.stat-period {
    font-size: 0.85rem;
    margin: 0;
    color: var(--text-light, #666);
}

/* Charts */
.stats-charts {
    margin-bottom: 30px;
}

.chart-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.chart-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.chart-card.large {
    grid-column: 1 / -1;
}

.chart-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chart-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text-color, #333);
}

.chart-actions {
    display: flex;
    gap: 5px;
}

.btn-icon {
    width: 30px;
    height: 30px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid #ddd;
    color: var(--text-light, #666);
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-icon:hover, .btn-icon.active {
    background-color: var(--primary-color, #ff3e3e);
    color: white;
    border-color: var(--primary-color, #ff3e3e);
}

.chart-body {
    padding: 20px;
    height: 300px;
    position: relative;
}

/* Tables */
.stats-tables {
    margin-bottom: 30px;
}

.table-row {
    margin-bottom: 20px;
}

.table-row.two-columns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
}

.table-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.table-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text-color, #333);
}

.table-actions {
    display: flex;
    gap: 5px;
}

.table-body {
    padding: 0;
}

.table-responsive {
    overflow-x: auto;
}

.stats-table {
    width: 100%;
    border-collapse: collapse;
}

.stats-table th, .stats-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.stats-table th {
    background-color: #f8f9fa;
    color: var(--text-light, #666);
    font-weight: 600;
    font-size: 0.9rem;
}

.stats-table tr:last-child td {
    border-bottom: none;
}

.stats-table tr:hover td {
    background-color: #f8f9fa;
}

.stats-table a {
    color: var(--primary-color, #ff3e3e);
    text-decoration: none;
}

.stats-table a:hover {
    text-decoration: underline;
}

.warning-row td {
    background-color: rgba(255, 193, 7, 0.05);
}

.stock-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.stock-badge.critical {
    background-color: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}

.stock-badge.low {
    background-color: rgba(255, 193, 7, 0.1);
    color: #ffc107;
}

/* Empty State */
.empty-state {
    padding: 40px;
    text-align: center;
    color: #adb5bd;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
    font-size: 1rem;
}

/* Additional Stats */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.stats-panel {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.panel-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
}

.panel-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text-color, #333);
    display: flex;
    align-items: center;
    gap: 8px;
}

.panel-header h3 i {
    color: var(--primary-color, #ff3e3e);
}

.panel-body {
    padding: 20px;
}

.mini-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 15px;
}

.mini-stat {
    text-align: center;
}

.mini-stat-label {
    font-size: 0.85rem;
    color: var(--text-light, #666);
    margin-bottom: 5px;
}

.mini-stat-value {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--text-color, #333);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.mini-stat-subtext {
    font-size: 0.75rem;
    color: var(--text-light, #666);
    margin-top: 3px;
}

.mini-stat-subtext.urgent {
    color: #dc3545;
    font-weight: 500;
}

.star-icon {
    color: #ffc107;
    font-size: 0.9rem;
}

/* Responsive Adjustments */
@media (max-width: 992px) {
    .chart-row {
        grid-template-columns: 1fr;
    }
    
    .chart-card.large {
        grid-column: auto;
    }
    
    .table-row.two-columns {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .stats-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
        flex-direction: column;
    }
    
    .period-selector {
        width: 100%;
    }
    
    #period-form {
        flex-direction: column;
    }
    
    #custom-dates {
        flex-direction: column;
    }
    
    .action-buttons {
        width: 100%;
        justify-content: space-between;
    }
    
    .stats-cards {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    .header-actions, .chart-actions, .table-actions {
        display: none;
    }
    
    .stats-dashboard {
        padding: 0;
    }
    
    .stat-card, .chart-card, .table-card, .stats-panel {
        box-shadow: none;
        border: 1px solid #ddd;
        break-inside: avoid;
    }
    
    .chart-body {
        height: auto;
    }
}
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Fermeture des alertes
    document.querySelectorAll('.close-alert').forEach(button => {
        button.addEventListener('click', function() {
            this.parentElement.style.display = 'none';
        });
    });

    // Gestion du sélecteur de période
    const periodSelect = document.getElementById('period-select');
    const customDates = document.getElementById('custom-dates');
    
    periodSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            customDates.classList.remove('hidden');
        } else {
            customDates.classList.add('hidden');
        }
    });

    // Bouton d'impression
    document.getElementById('print-stats').addEventListener('click', () => window.print());

    // Graphique des achats par marque (Pie Chart)
    const brandCtx = document.getElementById('brandChart').getContext('2d');
    const brandChart = new Chart(brandCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode($brand_labels) ?>,
            datasets: [{
                data: <?= json_encode($brand_data) ?>,
                backgroundColor: [
                    '#ef4444', '#f97316', '#eab308', '#22c55e', 
                    '#3b82f6', '#8b5cf6', '#ec4899', '#0ea5e9',
                    '#14b8a6', '#a3e635'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 15,
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value.toLocaleString()} € (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Graphique des revenus mensuels (Line Chart)
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($month_labels) ?>,
            datasets: [{
                label: 'Revenus (€)',
                data: <?= json_encode($month_data) ?>,
                fill: {
                    target: 'origin',
                    above: 'rgba(255, 62, 62, 0.1)'
                },
                borderColor: '#ff3e3e',
                tension: 0.3,
                pointBackgroundColor: '#ff3e3e',
                pointRadius: 4,
                pointHoverRadius: 6
            }, {
                label: 'Commandes',
                data: <?= json_encode($month_orders) ?>,
                fill: false,
                borderColor: '#3b82f6',
                tension: 0.3,
                pointBackgroundColor: '#3b82f6',
                pointRadius: 4,
                pointHoverRadius: 6,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Revenus (€)'
                    },
                    grid: {
                        drawBorder: false
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Nombre de commandes'
                    },
                    grid: {
                        display: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.raw;
                            if (label.includes('Revenus')) {
                                return `${label}: ${value.toLocaleString()} €`;
                            }
                            return `${label}: ${value}`;
                        }
                    }
                }
            }
        }
    });

    // Graphique des statuts de commande (Doughnut Chart)
    const statusCtx = document.getElementById('orderStatusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($status_labels) ?>,
            datasets: [{
                data: <?= json_encode($status_data) ?>,
                backgroundColor: [
                    '#22c55e', // shipped/delivered
                    '#3b82f6', // processing
                    '#eab308', // pending
                    '#ef4444', // cancelled
                    '#8b5cf6'  // autres
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 15,
                        padding: 10,
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });

    // Graphique des ventes par catégorie (Bar Chart)
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryChart = new Chart(categoryCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($category_labels) ?>,
            datasets: [{
                label: 'Revenus par catégorie',
                data: <?= json_encode($category_data) ?>,
                backgroundColor: '#ff3e3e',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let value = context.raw;
                            return `Revenus: ${value.toLocaleString()} €`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Graphique des ventes par genre (Pie Chart)
    const genderCtx = document.getElementById('genderChart').getContext('2d');
    const genderChart = new Chart(genderCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode($gender_labels) ?>,
            datasets: [{
                data: <?= json_encode($gender_data) ?>,
                backgroundColor: [
                    '#3b82f6', // homme
                    '#ec4899', // femme
                    '#8b5cf6', // enfant
                    '#22c55e'  // unisex
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 15,
                        padding: 10,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value.toLocaleString()} € (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Graphique des tailles populaires (Bar Chart)
    const sizeCtx = document.getElementById('sizeChart').getContext('2d');
    const sizeChart = new Chart(sizeCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($size_labels) ?>,
            datasets: [{
                label: 'Quantité vendue',
                data: <?= json_encode($size_data) ?>,
                backgroundColor: '#3b82f6',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Taille EU'
                    }
                }
            }
        }
    });

    // Gestion des boutons de basculement de vue des graphiques
    document.querySelectorAll('.toggle-view').forEach(button => {
        button.addEventListener('click', function() {
            const chartId = this.getAttribute('data-chart');
            const viewType = this.getAttribute('data-view');
            const chartInstance = Chart.getChart(chartId);
            
            // Mettre à jour le type de graphique
            if (chartInstance) {
                chartInstance.config.type = viewType;
                chartInstance.update();
                
                // Mettre à jour l'état actif des boutons
                document.querySelectorAll(`[data-chart="${chartId}"]`).forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
            }
        });
    });

    // Exportation des tableaux
    document.getElementById('export-top-products').addEventListener('click', function() {
        window.location.href = 'export.php?data=top_products&format=csv';
    });
    
    document.getElementById('export-top-clients').addEventListener('click', function() {
        window.location.href = 'export.php?data=top_clients&format=csv';
    });
});
</script>

<?php include 'includes/footer.php'; ?>