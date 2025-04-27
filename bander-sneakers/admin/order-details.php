<?php
// Page de détails d'une commande
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
$order = null;
$order_items = [];
$error_message = '';

// Vérifier si un ID de commande est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error_message = "Aucun ID de commande spécifié ou ID invalide.";
} else {
    $order_id = (int)$_GET['id'];

    try {
        // Récupérer les détails de la commande
        $stmt = $db->prepare("
            SELECT o.order_id, o.user_id, o.order_status, o.total_amount, o.created_at, o.updated_at, 
                   o.shipping_address, o.shipping_city, o.shipping_postal_code, o.shipping_country, 
                   o.payment_method, o.shipping_method, u.username
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.user_id
            WHERE o.order_id = :order_id
        ");
        $stmt->execute([':order_id' => $order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $error_message = "Commande introuvable.";
        } else {
            // Récupérer les articles de la commande avec les noms des sneakers et les tailles
            $stmt = $db->prepare("
                SELECT oi.order_item_id, oi.sneaker_id, oi.quantity, oi.price, 
                       s.sneaker_name, sz.size_value
                FROM order_items oi
                LEFT JOIN sneakers s ON oi.sneaker_id = s.sneaker_id
                LEFT JOIN sizes sz ON oi.size_id = sz.size_id
                WHERE oi.order_id = :order_id
            ");
            $stmt->execute([':order_id' => $order_id]);
            $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la récupération des détails de la commande : " . $e->getMessage();
    }
}

// Titre de la page
$page_title = "Détails de la commande #" . ($order ? $order['order_id'] : 'Inconnue') . " - Admin Bander-Sneakers";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Détails de la commande #<?= $order ? $order['order_id'] : 'Inconnue' ?></h1>
            <p>Consultez les informations détaillées de la commande.</p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?= $error_message ?>
            </div>
        <?php elseif ($order): ?>
            <div class="order-details-container">
                <div class="order-summary">
                    <h2>Résumé de la commande</h2>
                    <table class="admin-table">
                        <tbody>
                            <tr>
                                <th>ID de la commande</th>
                                <td><?= $order['order_id'] ?></td>
                            </tr>
                            <tr>
                                <th>Utilisateur</th>
                                <td><?= $order['username'] ? htmlspecialchars($order['username']) : 'Anonyme' ?></td>
                            </tr>
                            <tr>
                                <th>Statut</th>
                                <td>
                                    <?php
                                    $status_labels = [
                                        'pending' => 'En attente',
                                        'processing' => 'En cours',
                                        'shipped' => 'Expédiée',
                                        'delivered' => 'Livrée',
                                        'cancelled' => 'Annulée'
                                    ];
                                    echo $status_labels[$order['order_status']] ?? $order['order_status'];
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Montant total</th>
                                <td><?= number_format($order['total_amount'], 2) ?> €</td>
                            </tr>
                            <tr>
                                <th>Adresse de livraison</th>
                                <td><?= htmlspecialchars($order['shipping_address'] . ', ' . $order['shipping_city'] . ' ' . $order['shipping_postal_code'] . ', ' . $order['shipping_country']) ?></td>
                            </tr>
                            <tr>
                                <th>Méthode de paiement</th>
                                <td><?= htmlspecialchars($order['payment_method']) ?></td>
                            </tr>
                            <tr>
                                <th>Méthode d'expédition</th>
                                <td><?= htmlspecialchars($order['shipping_method']) ?></td>
                            </tr>
                            <tr>
                                <th>Date de création</th>
                                <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                            </tr>
                            <tr>
                                <th>Dernière mise à jour</th>
                                <td><?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="order-items">
                    <h2>Articles commandés</h2>
                    <?php if (empty($order_items)): ?>
                        <p>Aucun article trouvé pour cette commande.</p>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Taille</th>
                                    <th>Quantité</th>
                                    <th>Prix unitaire (€)</th>
                                    <th>Total (€)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['sneaker_name'] ?? 'Sneaker #' . $item['sneaker_id']) ?></td>
                                        <td><?= htmlspecialchars($item['size_value'] ?? 'N/A') ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td><?= number_format($item['price'], 2) ?></td>
                                        <td><?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="order-actions">
                    <a href="orders.php" class="btn btn-secondary">Retour à la liste des commandes</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .order-details-container {
        background: var(--white);
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: var(--box-shadow);
        margin-bottom: 2rem;
    }
    .order-summary, .order-items {
        margin-bottom: 2rem;
    }
    .order-summary h2, .order-items h2 {
        margin-bottom: 1rem;
    }
    .admin-table th {
        width: 30%;
        text-align: left;
    }
    .admin-table td {
        text-align: left;
    }
    .order-actions {
        text-align: right;
    }
    .btn-secondary {
        display: inline-block;
        padding: 0.5rem 1rem;
        background-color: #6c757d;
        color: white;
        text-decoration: none;
        border-radius: 4px;
    }
    .btn-secondary:hover {
        background-color: #5a6268;
    }
</style>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>