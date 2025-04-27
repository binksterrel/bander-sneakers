<?php
// Page de confirmation de commande
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'ID de commande est disponible (via GET ou SESSION)
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (isset($_SESSION['order_id']) ? $_SESSION['order_id'] : null);
if (!$orderId) {
    error_log("Order ID not set in session or GET parameter.");
    $_SESSION['error_message'] = "Aucun identifiant de commande fourni.";
    header('Location: index.php');
    exit();
}

error_log("Processing order confirmation for order_id: $orderId");
error_log("Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NULL'));

try {
    $db = getDbConnection();

    // Récupérer les détails de la commande avec first_name et last_name depuis users
    $stmt = $db->prepare("
        SELECT o.*, u.email, u.first_name, u.last_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.user_id
        WHERE o.order_id = :order_id
    ");
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_log("Order not found for order_id: $orderId");
        $_SESSION['error_message'] = "Commande introuvable.";
        header('Location: index.php');
        exit();
    }

    error_log("Order details retrieved: " . print_r($order, true));

    // Déterminer le nom à afficher
    $displayName = 'Client';
    if (!empty($order['first_name'])) {
        $displayName = htmlspecialchars($order['first_name']);
    } elseif (isset($_SESSION['guest_first_name'])) {
        $displayName = htmlspecialchars($_SESSION['guest_first_name']);
    } else {
        error_log("No first_name found for order_id: $orderId, defaulting to 'Client'");
    }

    // Récupérer les articles de la commande avec le prix original pour comparaison
    $stmt = $db->prepare("
        SELECT oi.*, s.sneaker_name, s.price AS original_price, s.discount_price, s.primary_image AS image_url, sz.size_value, sz.size_type, b.brand_name
        FROM order_items oi
        JOIN sneakers s ON oi.sneaker_id = s.sneaker_id
        JOIN sizes sz ON oi.size_id = sz.size_id
        LEFT JOIN brands b ON s.brand_id = b.brand_id
        WHERE oi.order_id = :order_id
    ");
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orderItems)) {
        error_log("No items found for order_id: $orderId");
        $_SESSION['error_message'] = "Aucun article trouvé pour cette commande.";
        header('Location: index.php');
        exit();
    }

    // Vérifier et récupérer les images si primary_image est vide
    foreach ($orderItems as &$item) {
        if (empty($item['image_url'])) {
            $stmt = $db->prepare("
                SELECT image_url 
                FROM sneaker_images 
                WHERE sneaker_id = :sneaker_id 
                LIMIT 1
            ");
            $stmt->execute(['sneaker_id' => $item['sneaker_id']]);
            $item['image_url'] = $stmt->fetchColumn() ?: null;
        }
    }
    unset($item);

} catch (PDOException $e) {
    error_log("Database error while fetching order details: " . $e->getMessage());
    $_SESSION['error_message'] = "Une erreur est survenue lors de la récupération des détails de votre commande.";
    header('Location: index.php');
    exit();
}

// Titre et description de la page
$page_title = "Confirmation de commande - Bander-Sneakers";
$page_description = "Votre commande a été confirmée. Merci pour votre achat sur Bander-Sneakers.";

// Inclure l'en-tête
include 'includes/header.php';
?>

<style>
    /* Ajout du style pour le prix barré */
    .confirmation-section {
        padding: 60px 0;
    }
    .confirmation-header {
        text-align: center;
        margin-bottom: 40px;
        animation: fadeIn 1s ease-in-out;
    }
    .confirmation-icon {
        font-size: 60px;
        color: var(--success-color, #28a745);
        margin-bottom: 20px;
    }
    .confirmation-header h1 {
        font-size: 2.5rem;
        margin-bottom: 10px;
    }
    .confirmation-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 40px;
    }
    .order-info, .shipping-info {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .order-info ul {
        list-style: none;
        padding: 0;
    }
    .order-info li {
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
    }
    .status-pending { color: #ff9800; }
    .status-confirmed { color: #28a745; }
    .status-shipped { color: #007bff; }
    .status-delivered { color: #6f42c1; }
    .order-summary {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 40px;
    }
    .order-item {
        display: flex;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid #eee;
    }
    .item-image img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 4px;
        margin-right: 20px;
    }
    .item-image .no-image {
        width: 80px;
        height: 80px;
        background: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #999;
        margin-right: 20px;
        border-radius: 4px;
    }
    .item-details h3 {
        margin: 0 0 5px;
        font-size: 1.1rem;
    }
    .item-details p {
        margin: 5px 0;
        color: #666;
    }
    .item-price {
        font-weight: 600;
        margin-left: auto;
    }
    .order-totals {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
    .total-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    .grand-total {
        font-weight: 700;
        font-size: 1.2rem;
    }
    .price-strikethrough {
        text-decoration: line-through;
        color: #999;
        margin-right: 10px;
    }
    .confirmation-actions {
        text-align: center;
        margin-bottom: 20px;
    }
    .confirmation-actions .btn {
        margin: 0 10px;
    }
    .confirmation-message {
        text-align: center;
        color: #666;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @media (max-width: 768px) {
        .confirmation-details {
            grid-template-columns: 1fr;
        }
        .confirmation-header h1 {
            font-size: 2rem;
        }
    }
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li><a href="cart.php">Panier</a></li>
            <li class="active">Confirmation de commande</li>
        </ul>
    </div>
</div>

<!-- Order Confirmation Section -->
<section class="confirmation-section">
    <div class="container">
        <div class="confirmation-header">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Commande confirmée</h1>
            <p>Merci pour votre achat, <?= $displayName ?> ! Votre commande a été traitée avec succès.</p>
        </div>

        <div class="confirmation-details">
            <div class="order-info">
                <h2>Informations de commande</h2>
                <ul>
                    <li><span>Numéro de commande:</span> <strong>#<?= $order['order_id'] ?></strong></li>
                    <li><span>Date:</span> <strong><?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?></strong></li>
                    <li><span>Statut:</span> <span class="status-<?= strtolower($order['order_status']) ?>"><?= ucfirst($order['order_status']) ?></span></li>
                    <li><span>Mode de paiement:</span> <strong><?= ucfirst($order['payment_method']) ?></strong></li>
                    <li><span>Mode de livraison:</span> <strong><?= ucfirst($order['shipping_method']) ?></strong></li>
                </ul>
            </div>

            <div class="shipping-info">
                <h2>Adresse de livraison</h2>
                <p>
                    <strong><?= htmlspecialchars($order['first_name'] ?? $displayName) . ' ' . htmlspecialchars($order['last_name'] ?? '') ?></strong><br>
                    <?= htmlspecialchars($order['shipping_address']) ?><br>
                    <?= htmlspecialchars($order['shipping_postal_code']) ?> <?= htmlspecialchars($order['shipping_city']) ?><br>
                    <?= htmlspecialchars($order['shipping_country']) ?>
                </p>
            </div>
        </div>

        <div class="order-summary">
            <h2>Récapitulatif de votre commande (Commande #<?= $order['order_id'] ?>)</h2>
            <div class="order-items">
                <?php foreach ($orderItems as $item): ?>
                    <div class="order-item">
                        <div class="item-image">
                            <?php if ($item['image_url']): ?>
                                <img src="assets/images/sneakers/<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['sneaker_name']) ?>">
                            <?php else: ?>
                                <div class="no-image"><i class="fas fa-image"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="item-details">
                            <h3><?= htmlspecialchars($item['sneaker_name']) ?></h3>
                            <p>Marque: <?= htmlspecialchars($item['brand_name']) ?></p>
                            <p>Taille: <?= htmlspecialchars($item['size_value']) ?> (<?= htmlspecialchars($item['size_type']) ?>)</p>
                            <p>Quantité: <?= $item['quantity'] ?></p>
                        </div>
                        <div class="item-price"><?= formatPrice($item['price'] * $item['quantity']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="order-totals">
                <?php
                $subtotal = 0;
                $originalSubtotal = 0; // Sous-total sans réduction
                foreach ($orderItems as $item) {
                    $subtotal += $item['price'] * $item['quantity']; // Prix payé (order_items.price)
                    $originalPrice = $item['discount_price'] !== null && $item['discount_price'] < $item['original_price']
                        ? $item['original_price']
                        : $item['price']; // Si pas de promo, le prix payé est le prix original
                    $originalSubtotal += $originalPrice * $item['quantity'];
                }
                $subtotal = round($subtotal, 2);
                $originalSubtotal = round($originalSubtotal, 2);

                $shipping = round($order['total_amount'] - $subtotal, 2);
                if ($shipping < 0) {
                    error_log("Negative shipping calculated for order_id: $orderId - Subtotal: $subtotal, Total: {$order['total_amount']}");
                    $shipping = 0;
                }

                $originalTotal = $originalSubtotal + $shipping; // Total sans promo
                $hasDiscount = $originalTotal > $order['total_amount'] + 0.01; // Tolérance pour arrondi

                $calculatedTotal = $subtotal + $shipping;
                if (abs($calculatedTotal - $order['total_amount']) > 0.01) {
                    error_log("Total mismatch for order_id: $orderId - DB Total: {$order['total_amount']}, Calculated: $calculatedTotal");
                }
                ?>
                <div class="total-row">
                    <span>Sous-total:</span>
                    <span><?= formatPrice($subtotal) ?></span>
                </div>
                <div class="total-row">
                    <span>Frais de livraison:</span>
                    <span><?= $shipping > 0 ? formatPrice($shipping) : 'Gratuit' ?></span>
                </div>
                <div class="total-row grand-total">
                    <span>Total:</span>
                    <span>
                        <?php if ($hasDiscount): ?>
                            <span class="price-strikethrough"><?= formatPrice($originalTotal) ?></span>
                        <?php endif; ?>
                        <?= formatPrice($order['total_amount']) ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="confirmation-actions">
            <a href="index.php" class="btn btn-primary btn-lg">
                <i class="fas fa-home"></i> Retour à l'accueil
            </a>
            <?php if (isLoggedIn()): ?>
                <a href="compte.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-user"></i> Mon compte
                </a>
            <?php endif; ?>
        </div>

        <div class="confirmation-message">
            <p>Un email de confirmation a été envoyé à <strong><?= htmlspecialchars($order['email'] ?? 'l’adresse fournie') ?></strong>.</p>
            <p>Si vous avez des questions, contactez-nous via <a href="contact.php">notre page de contact</a>.</p>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation de confirmation
    const confirmationHeader = document.querySelector('.confirmation-header');
    confirmationHeader.style.opacity = '0';
    setTimeout(() => {
        confirmationHeader.style.opacity = '1';
    }, 100);

    // Afficher un toast de confirmation
    showToast('Merci pour votre commande !');

    function showToast(message, isError = false) {
        const existingToast = document.getElementById('confirmation-toast');
        if (existingToast) existingToast.remove();

        const toast = document.createElement('div');
        toast.id = 'confirmation-toast';
        toast.style.position = 'fixed';
        toast.style.bottom = '20px';
        toast.style.right = '20px';
        toast.style.backgroundColor = isError ? 'rgba(220, 53, 69, 0.9)' : 'rgba(40, 167, 69, 0.9)';
        toast.style.color = 'white';
        toast.style.padding = '12px 24px';
        toast.style.borderRadius = '4px';
        toast.style.zIndex = '9999';
        toast.style.boxShadow = '0 3px 10px rgba(0,0,0,0.2)';
        toast.style.transition = 'all 0.3s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        toast.innerHTML = `<i class="fas fa-check-circle" style="margin-right: 8px;"></i> ${message}`;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        }, 10);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(20px)';
            setTimeout(() => toast.parentNode?.removeChild(toast), 300);
        }, 3000);
    }
});
</script>

<?php
// Nettoyer les données de session uniquement si l'utilisateur est redirigé volontairement
if (isset($_SESSION['order_id'])) {
    unset($_SESSION['order_id']);
}
if (isset($_SESSION['guest_first_name'])) {
    unset($_SESSION['guest_first_name']);
}

// Inclure le pied de page
include 'includes/footer.php';
?>