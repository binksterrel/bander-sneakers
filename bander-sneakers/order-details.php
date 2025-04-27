<?php
// Page de détails d'une commande pour l'utilisateur
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Inclure la configuration et les fonctions
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Initialiser les variables
$db = getDbConnection();
$user_id = (int)$_SESSION['user_id'];
$order = null;
$order_items = [];
$error_message = '';

// Vérifier si un ID de commande est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error_message = "Aucun ID de commande spécifié ou ID invalide.";
} else {
    $order_id = (int)$_GET['id'];

    try {
        // Récupérer les détails de la commande pour cet utilisateur uniquement
        $stmt = $db->prepare("
            SELECT o.order_id, o.order_status, o.total_amount, o.created_at, o.updated_at, 
                   o.shipping_address, o.shipping_city, o.shipping_postal_code, o.shipping_country, 
                   o.payment_method, o.shipping_method
            FROM orders o
            WHERE o.order_id = :order_id AND o.user_id = :user_id
        ");
        $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $error_message = "Commande introuvable ou vous n'avez pas accès à cette commande.";
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
$page_title = "Détails de la commande #" . ($order ? $order['order_id'] : 'Inconnue') . " - Bander-Sneakers";

// Inclure l'en-tête utilisateur (assumé comme existant)
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="user-content">
    <div class="container">
        <div class="page-header">
            <h1>Détails de la commande #<?= $order ? $order['order_id'] : 'Inconnue' ?></h1>
            <p>Vos informations de commande détaillées.</p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?= $error_message ?>
            </div>
        <?php elseif ($order): ?>
            <div class="order-details-container">
                <div class="order-summary">
                    <h2>Résumé de la commande</h2>
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th>ID de la commande</th>
                                <td><?= $order['order_id'] ?></td>
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
                        <table class="table table-striped">
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
                    <a href="compte.php" class="btn btn-secondary">Retour à mon compte</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .user-content {
        padding: 20px 0;
    }
    .page-header {
        margin-bottom: 30px;
    }
    .order-details-container {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    .order-summary, .order-items {
        margin-bottom: 30px;
    }
    .order-summary h2, .order-items h2 {
        margin-bottom: 15px;
        font-size: 1.5rem;
    }
    .table th {
        width: 30%;
        background-color: #f8f9fa;
    }
    .order-actions {
        text-align: right;
    }
    .btn-secondary {
        display: inline-block;
        padding: 10px 20px;
        background-color: #6c757d;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        transition: background-color 0.3s;
    }
    .btn-secondary:hover {
        background-color: #5a6268;
        color: white;
    }
</style>

<?php
// Inclure le pied de page utilisateur (assumé comme existant)
include 'includes/footer.php';
?>