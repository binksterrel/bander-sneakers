<?php
// Page de gestion des commandes
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

// Supprimer une commande si demandé
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $order_id = (int)$_GET['delete'];
    try {
        $db->beginTransaction();

        // Récupérer le user_id et total_amount de la commande
        $stmt = $db->prepare("SELECT user_id, total_amount FROM orders WHERE order_id = :order_id");
        $stmt->execute([':order_id' => $order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        $debug_info = "Commande $order_id : user_id = " . ($order['user_id'] ?? 'NULL') . ", total_amount = " . ($order['total_amount'] ?? 'NULL') . "\n";

        if ($order && $order['user_id']) {
            // Vérifier les points associés à cet utilisateur (on suppose que points = total_amount)
            $stmt = $db->prepare("
                SELECT user_id, points 
                FROM loyalty_points 
                WHERE user_id = :user_id 
                AND points = :points 
                AND points > 0
            ");
            $expected_points = floor($order['total_amount']); // 1 € = 1 point
            $stmt->execute([
                ':user_id' => $order['user_id'],
                ':points' => $expected_points
            ]);
            $loyalty = $stmt->fetch(PDO::FETCH_ASSOC);

            $debug_info .= "Recherche des points pour order_id $order_id : user_id = {$order['user_id']}, points attendus = $expected_points\n";
            if ($loyalty) {
                $debug_info .= "Points trouvés : user_id = {$loyalty['user_id']}, points = {$loyalty['points']}\n";
            } else {
                $debug_info .= "Aucun point trouvé pour order_id $order_id avec points = $expected_points pour user_id {$order['user_id']}\n";
            }

            if ($loyalty && $loyalty['points'] > 0) {
                $user_id = $loyalty['user_id'];
                $points_to_remove = -$loyalty['points']; // Points négatifs pour annuler
                $debug_info .= "Annulation des points pour order_id $order_id : $points_to_remove points pour user_id $user_id\n";

                // Ajouter une entrée négative dans loyalty_points
                $stmt = $db->prepare("
                    INSERT INTO loyalty_points (user_id, points) 
                    VALUES (:user_id, :points)
                ");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':points' => $points_to_remove
                ]);
                $debug_info .= "Entrée négative ajoutée pour $points_to_remove points.\n";
            } else {
                $debug_info .= "Aucune action d'annulation : points non trouvés ou déjà annulés.\n";
            }
        } else {
            $debug_info .= "Commande $order_id n'a pas de user_id ou n'existe pas.\n";
        }

        // Supprimer les détails de la commande (order_items)
        $stmt = $db->prepare("DELETE FROM order_items WHERE order_id = :order_id");
        $stmt->execute([':order_id' => $order_id]);
        $debug_info .= "order_items supprimés pour order_id $order_id\n";

        // Supprimer la commande
        $stmt = $db->prepare("DELETE FROM orders WHERE order_id = :order_id");
        $stmt->execute([':order_id' => $order_id]);
        $debug_info .= "Commande $order_id supprimée\n";

        $db->commit();
        $success_message = "Commande supprimée avec succès et points de fidélité annulés si applicable.\nDétails :\n" . nl2br($debug_info);
    } catch (PDOException $e) {
        $db->rollBack();
        $error_message = "Erreur lors de la suppression de la commande : " . $e->getMessage() . "\nDétails :\n" . nl2br($debug_info);
    }
}

// Mettre à jour le statut d'une commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['order_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = in_array($_POST['order_status'], ['pending', 'processing', 'shipped', 'delivered', 'cancelled']) ? $_POST['order_status'] : null;
    if ($new_status) {
        try {
            $db->beginTransaction();

            // Récupérer le statut actuel et les détails de la commande
            $stmt = $db->prepare("SELECT user_id, total_amount, order_status FROM orders WHERE order_id = :order_id");
            $stmt->execute([':order_id' => $order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $debug_info = "Commande $order_id : user_id = " . ($order['user_id'] ?? 'NULL') . ", total_amount = " . ($order['total_amount'] ?? 'NULL') . ", statut actuel = " . ($order['order_status'] ?? 'NULL') . "\n";

            if ($order) {
                // Si le nouveau statut est 'cancelled' et que le statut actuel n'était pas 'cancelled'
                if ($new_status === 'cancelled' && $order['order_status'] !== 'cancelled' && $order['user_id']) {
                    // Vérifier les points associés à cet utilisateur
                    $stmt = $db->prepare("
                        SELECT user_id, points 
                        FROM loyalty_points 
                        WHERE user_id = :user_id 
                        AND points = :points 
                        AND points > 0
                    ");
                    $expected_points = floor($order['total_amount']); // 1 € = 1 point
                    $stmt->execute([
                        ':user_id' => $order['user_id'],
                        ':points' => $expected_points
                    ]);
                    $loyalty = $stmt->fetch(PDO::FETCH_ASSOC);

                    $debug_info .= "Recherche des points pour order_id $order_id : user_id = {$order['user_id']}, points attendus = $expected_points\n";
                    if ($loyalty) {
                        $debug_info .= "Points trouvés : user_id = {$loyalty['user_id']}, points = {$loyalty['points']}\n";
                    } else {
                        $debug_info .= "Aucun point trouvé pour order_id $order_id avec points = $expected_points pour user_id {$order['user_id']}\n";
                    }

                    if ($loyalty && $loyalty['points'] > 0) {
                        $user_id = $loyalty['user_id'];
                        $points_to_remove = -$loyalty['points']; // Points négatifs pour annuler
                        $debug_info .= "Annulation des points pour order_id $order_id (statut annulé) : $points_to_remove points pour user_id $user_id\n";

                        // Ajouter une entrée négative dans loyalty_points
                        $stmt = $db->prepare("
                            INSERT INTO loyalty_points (user_id, points) 
                            VALUES (:user_id, :points)
                        ");
                        $stmt->execute([
                            ':user_id' => $user_id,
                            ':points' => $points_to_remove
                        ]);
                        $debug_info .= "Entrée négative ajoutée pour $points_to_remove points.\n";
                    } else {
                        $debug_info .= "Aucune action d'annulation : points non trouvés ou déjà annulés.\n";
                    }
                }

                // Mettre à jour le statut de la commande
                $stmt = $db->prepare("UPDATE orders SET order_status = :order_status, updated_at = NOW() WHERE order_id = :order_id");
                $stmt->execute([':order_status' => $new_status, ':order_id' => $order_id]);
                $debug_info .= "Statut mis à jour à '$new_status' pour order_id $order_id\n";

                // Ajouter une notification pour l'utilisateur
                if ($order['user_id']) {
                    $stmt = $db->prepare("SELECT sneaker_name FROM order_items oi JOIN sneakers s ON oi.sneaker_id = s.sneaker_id WHERE order_id = :order_id LIMIT 1");
                    $stmt->execute([':order_id' => $order_id]);
                    $sneaker_name = $stmt->fetchColumn() ?: 'Produit inconnu';
                    $status_fr = [
                        'pending' => 'en attente',
                        'processing' => 'en cours',
                        'shipped' => 'expédiée',
                        'delivered' => 'livrée',
                        'cancelled' => 'annulée'
                    ];
                    $notification_message = "Votre Commande#$order_id : $sneaker_name est " . $status_fr[$new_status];
                    addNotification($order['user_id'], $notification_message, 'order_update', $order_id);
                    $debug_info .= "Notification ajoutée pour user_id {$order['user_id']} : '$notification_message'\n";
                }
            } else {
                $debug_info .= "Commande $order_id n'existe pas.\n";
            }

            $db->commit();
            $success_message = "Statut de la commande mis à jour avec succès.\nDétails :\n" . nl2br($debug_info);
        } catch (PDOException $e) {
            $db->rollBack();
            $error_message = "Erreur lors de la mise à jour du statut : " . $e->getMessage() . "\nDétails :\n" . nl2br($debug_info);
        }
    } else {
        $error_message = "Statut invalide.";
    }
}

// Pagination
$items_per_page = 10; // Peut être ajusté via settings.php si implémenté
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Filtres
$filters = [];
if (isset($_GET['status']) && in_array($_GET['status'], ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
    $filters['status'] = $_GET['status'];
}
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $filters['user_id'] = (int)$_GET['user_id'];
}
if (isset($_GET['date']) && !empty($_GET['date'])) {
    $filters['date'] = $_GET['date'];
}
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Récupérer le nombre total de commandes pour la pagination
$sql = "SELECT COUNT(*) as total FROM orders o LEFT JOIN users u ON o.user_id = u.user_id WHERE 1=1";
$params = [];
if (isset($filters['status'])) {
    $sql .= " AND o.order_status = :status";
    $params[':status'] = $filters['status'];
}
if (isset($filters['user_id'])) {
    $sql .= " AND o.user_id = :user_id";
    $params[':user_id'] = $filters['user_id'];
}
if (isset($filters['date'])) {
    $sql .= " AND DATE(o.created_at) = :date";
    $params[':date'] = $filters['date'];
}
if (isset($filters['search'])) {
    $sql .= " AND (o.order_id LIKE :search OR u.username LIKE :search)";
    $params[':search'] = '%' . $filters['search'] . '%';
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Récupérer les commandes avec filtres et pagination
$sql = "
    SELECT o.order_id, o.user_id, o.order_status, o.total_amount, o.created_at, o.updated_at, u.username
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    WHERE 1=1
";
$params = [];
if (isset($filters['status'])) {
    $sql .= " AND o.order_status = :status";
    $params[':status'] = $filters['status'];
}
if (isset($filters['user_id'])) {
    $sql .= " AND o.user_id = :user_id";
    $params[':user_id'] = $filters['user_id'];
}
if (isset($filters['date'])) {
    $sql .= " AND DATE(o.created_at) = :date";
    $params[':date'] = $filters['date'];
}
if (isset($filters['search'])) {
    $sql .= " AND (o.order_id LIKE :search OR u.username LIKE :search)";
    $params[':search'] = '%' . $filters['search'] . '%';
}
$sql .= " ORDER BY o.created_at DESC LIMIT :offset, :items_per_page";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':items_per_page', $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

// Récupérer les utilisateurs pour le filtre
$users = $db->query("SELECT user_id, username FROM users ORDER BY username ASC")->fetchAll();

// Titre de la page
$page_title = "Gestion des commandes - Admin Bander-Sneakers";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Gestion des commandes</h1>
            <p>Gérez les commandes des clients.</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?= $success_message ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <div class="admin-filters">
            <form action="orders.php" method="GET" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="status">Statut</label>
                        <select id="status" name="status">
                            <option value="">Tous les statuts</option>
                            <option value="pending" <?= isset($filters['status']) && $filters['status'] == 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="processing" <?= isset($filters['status']) && $filters['status'] == 'processing' ? 'selected' : '' ?>>En cours</option>
                            <option value="shipped" <?= isset($filters['status']) && $filters['status'] == 'shipped' ? 'selected' : '' ?>>Expédiée</option>
                            <option value="delivered" <?= isset($filters['status']) && $filters['status'] == 'delivered' ? 'selected' : '' ?>>Livrée</option>
                            <option value="cancelled" <?= isset($filters['status']) && $filters['status'] == 'cancelled' ? 'selected' : '' ?>>Annulée</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="user_id">Utilisateur</label>
                        <select id="user_id" name="user_id">
                            <option value="">Tous les utilisateurs</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['user_id'] ?>" <?= isset($filters['user_id']) && $filters['user_id'] == $user['user_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="date">Date</label>
                        <input type="date" id="date" name="date" value="<?= isset($filters['date']) ? htmlspecialchars($filters['date']) : '' ?>">
                    </div>
                    <div class="filter-group">
                        <label for="search">Recherche (ID ou utilisateur)</label>
                        <input type="text" id="search" name="search" value="<?= isset($filters['search']) ? htmlspecialchars($filters['search']) : '' ?>" placeholder="Rechercher...">
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <a href="orders.php" class="btn btn-secondary">Réinitialiser</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="admin-table-container">
            <?php if (empty($orders)): ?>
                <div class="no-results">
                    <p>Aucune commande trouvée.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Utilisateur</th>
                            <th>Total (€)</th>
                            <th>Statut</th>
                            <th>Date de création</th>
                            <th>Dernière mise à jour</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= $order['order_id'] ?></td>
                                <td><?= $order['username'] ? htmlspecialchars($order['username']) : 'Anonyme' ?></td>
                                <td><?= number_format($order['total_amount'], 2) ?></td>
                                <td>
                                    <form method="POST" action="orders.php" style="display:inline;">
                                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                        <select name="order_status" onchange="this.form.submit()">
                                            <option value="pending" <?= $order['order_status'] == 'pending' ? 'selected' : '' ?>>En attente</option>
                                            <option value="processing" <?= $order['order_status'] == 'processing' ? 'selected' : '' ?>>En cours</option>
                                            <option value="shipped" <?= $order['order_status'] == 'shipped' ? 'selected' : '' ?>>Expédiée</option>
                                            <option value="delivered" <?= $order['order_status'] == 'delivered' ? 'selected' : '' ?>>Livrée</option>
                                            <option value="cancelled" <?= $order['order_status'] == 'cancelled' ? 'selected' : '' ?>>Annulée</option>
                                        </select>
                                    </form>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?></td>
                                <td class="actions-cell">
                                    <a href="order-details.php?id=<?= $order['order_id'] ?>" class="btn-action" title="Voir les détails">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="orders.php?delete=<?= $order['order_id'] ?>" class="btn-action delete-btn" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette commande ? Cela annulera également les points de fidélité associés.');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <ul>
                            <?php if ($current_page > 1): ?>
                                <li><a href="<?= updateQueryString(['page' => $current_page - 1]) ?>"><i class="fas fa-chevron-left"></i></a></li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            if ($start_page > 1) {
                                echo '<li><a href="' . updateQueryString(['page' => 1]) . '">1</a></li>';
                                if ($start_page > 2) echo '<li class="ellipsis">...</li>';
                            }

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active = $i == $current_page ? 'active' : '';
                                echo '<li class="' . $active . '"><a href="' . updateQueryString(['page' => $i]) . '">' . $i . '</a></li>';
                            }

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="ellipsis">...</li>';
                                }
                                echo '<li><a href="' . updateQueryString(['page' => $total_pages]) . '">' . $total_pages . '</a></li>';
                            }
                            ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li><a href="<?= updateQueryString(['page' => $current_page + 1]) ?>"><i class="fas fa-chevron-right"></i></a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
/**
 * Met à jour les paramètres de l'URL.
 *
 * @param array $params Les paramètres à mettre à jour
 * @return string L'URL mise à jour
 */
function updateQueryString($params) {
    $query = $_GET;
    foreach ($params as $key => $value) {
        $query[$key] = $value;
    }
    return 'orders.php?' . http_build_query($query);
}
?>

<style>
    .admin-filters {
        margin-bottom: 2rem;
    }
    .filter-form {
        background: var(--white);
        padding: 1rem;
        border-radius: 8px;
        box-shadow: var(--box-shadow);
    }
    .filter-row {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
    }
    .filter-group {
        flex: 1;
    }
    .filter-buttons {
        display: flex;
        gap: 0.5rem;
    }
    .admin-table-container {
        background: var(--white);
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: var(--box-shadow);
    }
    .actions-cell {
        display: flex;
        gap: 0.5rem;
    }
    .btn-action {
        color: rgb(0, 0, 0);
        text-decoration: none;
    }
    .btn-action:hover {
        color: #c0392b;
    }
    .delete-btn {
        color: rgb(0, 0, 0);
    }
    .delete-btn:hover {
        color: #c0392b;
    }
</style>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>