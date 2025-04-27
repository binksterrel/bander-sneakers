<?php
// Page de paiement
require_once 'includes/config.php';
require_once 'includes/functions.php';

error_log("Début de checkout.php");

// Vérifier si l'utilisateur a des articles dans son panier
if (!isset($_SESSION['cart_id'])) {
    error_log("Erreur : Aucun cart_id défini dans la session");
    $_SESSION['error_message'] = "Votre panier est vide.";
    header('Location: cart.php');
    exit();
}

$db = getDbConnection();
if (!$db) {
    error_log("Erreur : Impossible d'obtenir la connexion à la base de données");
    $_SESSION['error_message'] = "Erreur de connexion à la base de données.";
    header('Location: cart.php');
    exit();
}

$cartId = $_SESSION['cart_id'];
error_log("Cart ID récupéré : $cartId");

// Vérifier si le panier contient des articles
$stmt = $db->prepare("SELECT COUNT(*) as count FROM cart_items WHERE cart_id = :cart_id");
$stmt->bindParam(':cart_id', $cartId, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result['count'] == 0) {
    error_log("Erreur : Aucun article dans le panier pour cart_id $cartId");
    $_SESSION['error_message'] = "Votre panier est vide.";
    header('Location: cart.php');
    exit();
}

// Récupérer les articles du panier
function getCartItems($cartId) {
    error_log("getCartItems appelé avec cartId : $cartId");
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT ci.*, s.sneaker_name, s.price, s.discount_price, s.primary_image AS image_url, sz.size_value, sz.size_type, b.brand_name
        FROM cart_items ci
        JOIN sneakers s ON ci.sneaker_id = s.sneaker_id
        JOIN sizes sz ON ci.size_id = sz.size_id
        LEFT JOIN brands b ON s.brand_id = b.brand_id
        WHERE ci.cart_id = :cart_id
    ");
    $stmt->bindParam(':cart_id', $cartId, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Nombre d'articles récupérés dans le panier : " . count($items));

    foreach ($items as &$item) {
        if (empty($item['image_url'])) {
            $stmt = $db->prepare("
                SELECT image_url 
                FROM sneaker_images 
                WHERE sneaker_id = :sneaker_id 
                LIMIT 1
            ");
            $stmt->execute(['sneaker_id' => $item['sneaker_id']]);
            $item['image_url'] = $stmt->fetchColumn() ?: null;
            error_log("Image récupérée pour sneaker_id {$item['sneaker_id']} : " . ($item['image_url'] ?? 'Aucune image'));
        }
    }
    return $items;
}

$cartItems = getCartItems($cartId);

// Calculer le total du panier
$subtotal = 0;
foreach ($cartItems as $item) {
    $price = $item['discount_price'] ? $item['discount_price'] : $item['price'];
    $subtotal += $price * $item['quantity'];
}
error_log("Sous-total calculé : $subtotal");

// Frais de livraison
$shipping = ($subtotal > 0 && $subtotal < 100) ? 5.99 : 0;
error_log("Frais de livraison : $shipping");

// Récupérer les points de fidélité de l'utilisateur (si connecté)
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
error_log("User ID : " . ($userId ?? 'NULL'));
$loyaltyPoints = $userId ? getLoyaltyPoints($userId) : 0;
error_log("Points de fidélité : $loyaltyPoints");

// Nouvelle logique plus commerciale
$conversionRate = 200; // 200 points = 10 € de réduction
$discountValue = 10; // Valeur de la réduction par tranche de 200 points
$maxDiscountPercentage = 20; // La réduction max ne dépasse pas 20% du panier

// Fonction pour récupérer le total du panier
function getTotalCartAmount() {
    return isset($_SESSION['cart_total']) ? (float)$_SESSION['cart_total'] : 0;
}

// Calcul du montant de la réduction
$totalCart = getTotalCartAmount();
$maxDiscountAllowed = $totalCart * ($maxDiscountPercentage / 100);

$possibleDiscount = floor($loyaltyPoints / $conversionRate) * $discountValue;
$finalDiscount = min($possibleDiscount, $maxDiscountAllowed);

error_log("Réduction appliquée : $finalDiscount €");

// Gérer l'utilisation des points de fidélité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['use_points'])) {
    $pointsToUse = (int)$_POST['points_to_use'];
    error_log("Utilisation des points demandée : $pointsToUse");

    if ($pointsToUse > 0 && $pointsToUse <= $loyaltyPoints && $pointsToUse % $conversionRate === 0) {
        $discount = ($pointsToUse / $conversionRate) * $discountValue;
        if (useLoyaltyPoints($userId, $pointsToUse)) {
            $_SESSION['loyalty_discount'] = $discount;
            $_SESSION['success_message'] = "Vous avez utilisé $pointsToUse points pour obtenir une réduction de " . formatPrice($discount) . " !";
            error_log("Points utilisés avec succès : $pointsToUse, Réduction : $discount");
        } else {
            $_SESSION['error_message'] = "Erreur lors de l'utilisation des points.";
            error_log("Erreur lors de l'utilisation des points");
        }
    } else {
        $_SESSION['error_message'] = "Nombre de points invalide. Vous devez utiliser un multiple de $conversionRate points.";
        error_log("Erreur : Nombre de points invalide. Points demandés : $pointsToUse, Points disponibles : $loyaltyPoints");
    }
    header('Location: checkout.php');
    exit();
}

// Appliquer la réduction de fidélité (si présente)
$loyaltyDiscount = isset($_SESSION['loyalty_discount']) ? $_SESSION['loyalty_discount'] : 0;
error_log("Réduction de fidélité appliquée : $loyaltyDiscount");
$total = $subtotal + $shipping - $loyaltyDiscount;
error_log("Total calculé : $total");

// Traitement du formulaire de paiement
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['use_points'])) {
    error_log("Traitement du formulaire de paiement");

    if (
        empty($_POST['first_name']) ||
        empty($_POST['last_name']) ||
        empty($_POST['email']) ||
        empty($_POST['address']) ||
        empty($_POST['city']) ||
        empty($_POST['postal_code']) ||
        empty($_POST['country']) ||
        empty($_POST['payment_method'])
    ) {
        $error_message = "Veuillez remplir tous les champs obligatoires.";
        error_log("Erreur : Champs obligatoires manquants");
    } else {
        try {
            $db->beginTransaction();

            // Vérifier le stock
            foreach ($cartItems as $item) {
                $stmt = $db->prepare("
                    SELECT stock_quantity 
                    FROM sneaker_sizes 
                    WHERE sneaker_id = :sneaker_id AND size_id = :size_id
                ");
                $stmt->execute([':sneaker_id' => $item['sneaker_id'], ':size_id' => $item['size_id']]);
                $stock = $stmt->fetchColumn();

                if ($stock === false || $stock < $item['quantity']) {
                    throw new Exception("Stock insuffisant pour l'article : " . htmlspecialchars($item['sneaker_name']) . " (Taille: " . $item['size_value'] . ").");
                }
            }

            // Récupérer les données du formulaire
            $firstName = cleanInput($_POST['first_name']);
            $lastName = cleanInput($_POST['last_name']);
            $shippingMethod = cleanInput($_POST['shipping_method'] ?? 'standard');
            $paymentMethod = cleanInput($_POST['payment_method']);
            $address = cleanInput($_POST['address']);
            $city = cleanInput($_POST['city']);
            $postalCode = cleanInput($_POST['postal_code']);
            $country = cleanInput($_POST['country']);

            error_log("Données du formulaire : first_name=$firstName, last_name=$lastName, shipping_method=$shippingMethod, payment_method=$paymentMethod, address=$address, city=$city, postal_code=$postalCode, country=$country");

            // Ajuster les frais de livraison si express
            if ($shippingMethod === 'express') {
                $shipping = 8.99;
                $total = $subtotal + $shipping - $loyaltyDiscount;
            }

            // Créer la commande et attribuer des points
            $orderId = createOrderAndAwardPoints(
                $userId,
                $total,
                $address,
                $city,
                $postalCode,
                $country,
                $paymentMethod,
                $shippingMethod,
                $firstName,
                $lastName
            );

            if (!$orderId) {
                throw new Exception("Échec de la création de la commande.");
            }

            // Enregistrer les articles de la commande et décrémenter le stock
            $lowStockThreshold = 10; // Seuil de stock bas
            foreach ($cartItems as $item) {
                $price = $item['discount_price'] ? $item['discount_price'] : $item['price'];
                $stmt = $db->prepare("
                    INSERT INTO order_items (order_id, sneaker_id, size_id, quantity, price)
                    VALUES (:order_id, :sneaker_id, :size_id, :quantity, :price)
                ");
                $stmt->execute([
                    ':order_id' => $orderId,
                    ':sneaker_id' => $item['sneaker_id'],
                    ':size_id' => $item['size_id'],
                    ':quantity' => $item['quantity'],
                    ':price' => $price
                ]);
                error_log("Article ajouté à la commande : sneaker_id={$item['sneaker_id']}, size_id={$item['size_id']}, quantity={$item['quantity']}, price=$price");

                // Mettre à jour le stock avec vérification (corrigé)
                $stmt = $db->prepare("
                    UPDATE sneaker_sizes
                    SET stock_quantity = stock_quantity - :quantity
                    WHERE sneaker_id = :sneaker_id AND size_id = :size_id AND stock_quantity >= :quantity_check
                ");
                $stmt->execute([
                    ':quantity' => $item['quantity'],
                    ':sneaker_id' => $item['sneaker_id'],
                    ':size_id' => $item['size_id'],
                    ':quantity_check' => $item['quantity']
                ]);

                $affectedRows = $stmt->rowCount();
                if ($affectedRows == 0) {
                    throw new Exception("Erreur : Stock insuffisant ou article introuvable pour sneaker_id {$item['sneaker_id']}, taille {$item['size_id']}.");
                }
                error_log("Stock mis à jour pour sneaker_id {$item['sneaker_id']}, size_id {$item['size_id']}");

                // Vérifier le stock restant pour notification
                $stmt = $db->prepare("
                    SELECT stock_quantity 
                    FROM sneaker_sizes 
                    WHERE sneaker_id = :sneaker_id AND size_id = :size_id
                ");
                $stmt->execute([':sneaker_id' => $item['sneaker_id'], ':size_id' => $item['size_id']]);
                $newStock = $stmt->fetchColumn();

                if ($newStock <= $lowStockThreshold) {
                    error_log("Stock bas pour sneaker_id {$item['sneaker_id']}, size_id {$item['size_id']} : $newStock restant");
                    $stmt = $db->prepare("
                        INSERT INTO notifications (user_id, message, type, related_id)
                        VALUES (:admin_id, :message, 'stock_low', :sneaker_id)
                    ");
                    $stmt->execute([
                        ':admin_id' => 1, // Remplacez par l'ID réel de l'admin ou une logique dynamique
                        ':message' => "❗ Stock bas pour {$item['sneaker_name']} (Taille: {$item['size_value']}) : $newStock restant",
                        ':sneaker_id' => $item['sneaker_id']
                    ]);
                }
            }

            // Optionnel : Mettre à jour le stock total dans sneakers (décommenter si nécessaire)
            /*
            $totalQuantity = array_sum(array_column($cartItems, 'quantity'));
            $stmt = $db->prepare("
                UPDATE sneakers
                SET stock_quantity = stock_quantity - :total_quantity
                WHERE sneaker_id IN (" . implode(',', array_column($cartItems, 'sneaker_id')) . ")
            ");
            $stmt->execute([':total_quantity' => $totalQuantity]);
            error_log("Stock total mis à jour dans sneakers");
            */

            // Vider le panier
            $stmt = $db->prepare("DELETE FROM cart_items WHERE cart_id = :cart_id");
            $stmt->execute([':cart_id' => $cartId]);
            error_log("Panier vidé pour cart_id $cartId");

            // Réinitialiser la réduction
            unset($_SESSION['loyalty_discount']);
            error_log("Réduction de fidélité réinitialisée");

            $db->commit();

            // Rediriger vers la confirmation
            $_SESSION['order_id'] = $orderId;
            error_log("Redirection vers order-confirmation.php avec order_id $orderId");
            header('Location: order-confirmation.php');
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
            error_log("Erreur lors du traitement de la commande : " . $e->getMessage());
        }
    }
}

// Récupérer les informations de l'utilisateur s'il est connecté
$user = null;
if ($userId) {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Utilisateur récupéré : " . ($user ? print_r($user, true) : 'Aucun utilisateur trouvé'));
}

// Titre et description de la page
$page_title = "Paiement - Bander-Sneakers";
$page_description = "Finaliser votre commande sur Bander-Sneakers. Entrez vos informations de livraison et de paiement.";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li><a href="cart.php">Panier</a></li>
            <li class="active">Paiement</li>
        </ul>
    </div>
</div>

<!-- Checkout Section -->
<section class="checkout-section">
    <div class="container">
        <div class="checkout-header">
            <h1 class="checkout-title">Finaliser votre commande</h1>
            <div class="checkout-steps">
                <div class="checkout-step completed"><div class="step-number"><i class="fas fa-shopping-cart"></i></div><div class="step-label">Panier</div></div>
                <div class="step-connector"></div>
                <div class="checkout-step active"><div class="step-number"><i class="fas fa-credit-card"></i></div><div class="step-label">Paiement</div></div>
                <div class="step-connector"></div>
                <div class="checkout-step"><div class="step-number"><i class="fas fa-check"></i></div><div class="step-label">Confirmation</div></div>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <div class="checkout-content">
            <div class="checkout-form-container">
                <form action="checkout.php" method="POST" class="checkout-form">
                    <!-- Informations personnelles -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon"><i class="fas fa-user-circle"></i></div>
                            <h2>Informations personnelles</h2>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">Prénom <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" id="first_name" name="first_name" value="<?= $user ? htmlspecialchars($user['first_name']) : '' ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Nom <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" id="last_name" name="last_name" value="<?= $user ? htmlspecialchars($user['last_name']) : '' ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" id="email" name="email" value="<?= $user ? htmlspecialchars($user['email']) : '' ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="phone">Téléphone</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-phone input-icon"></i>
                                    <input type="tel" id="phone" name="phone" value="<?= $user ? htmlspecialchars($user['phone']) : '' ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Adresse de livraison -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <h2>Adresse de livraison</h2>
                        </div>
                        <div class="form-group">
                            <label for="address">Adresse <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-home input-icon"></i>
                                <input type="text" id="address" name="address" value="<?= $user ? htmlspecialchars($user['address']) : '' ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">Ville <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-city input-icon"></i>
                                    <input type="text" id="city" name="city" value="<?= $user ? htmlspecialchars($user['city']) : '' ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="postal_code">Code postal <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-mail-bulk input-icon"></i>
                                    <input type="text" id="postal_code" name="postal_code" value="<?= $user ? htmlspecialchars($user['postal_code']) : '' ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="country">Pays <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-globe input-icon"></i>
                                <select id="country" name="country" required>
                                    <option value="">Sélectionnez un pays</option>
                                    <option value="France" <?= $user && $user['country'] == 'France' ? 'selected' : '' ?>>France</option>
                                    <option value="Belgique" <?= $user && $user['country'] == 'Belgique' ? 'selected' : '' ?>>Belgique</option>
                                    <option value="Suisse" <?= $user && $user['country'] == 'Suisse' ? 'selected' : '' ?>>Suisse</option>
                                    <option value="Luxembourg" <?= $user && $user['country'] == 'Luxembourg' ? 'selected' : '' ?>>Luxembourg</option>
                                    <option value="Canada" <?= $user && $user['country'] == 'Canada' ? 'selected' : '' ?>>Canada</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Mode de livraison -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon"><i class="fas fa-truck"></i></div>
                            <h2>Mode de livraison</h2>
                        </div>
                        <div class="shipping-options">
                            <div class="shipping-option">
                                <input type="radio" id="shipping_standard" name="shipping_method" value="standard" checked>
                                <label for="shipping_standard" class="shipping-card">
                                    <div class="shipping-icon"><i class="fas fa-truck"></i></div>
                                    <div class="shipping-details">
                                        <span class="shipping-name">Livraison standard</span>
                                        <span class="shipping-info">Livraison sous 3-5 jours ouvrés</span>
                                    </div>
                                    <div class="shipping-price"><?= $shipping > 0 ? formatPrice($shipping) : 'Gratuit' ?></div>
                                </label>
                            </div>
                            <div class="shipping-option">
                                <input type="radio" id="shipping_express" name="shipping_method" value="express">
                                <label for="shipping_express" class="shipping-card">
                                    <div class="shipping-icon"><i class="fas fa-shipping-fast"></i></div>
                                    <div class="shipping-details">
                                        <span class="shipping-name">Livraison express</span>
                                        <span class="shipping-info">Livraison sous 24-48h</span>
                                    </div>
                                    <div class="shipping-price"><?= formatPrice(8.99) ?></div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Mode de paiement -->
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon"><i class="fas fa-credit-card"></i></div>
                            <h2>Mode de paiement</h2>
                        </div>
                        <div class="payment-options">
                            <div class="payment-option">
                                <input type="radio" id="payment_card" name="payment_method" value="card" checked>
                                <label for="payment_card" class="payment-card">
                                    <div class="payment-icon"><i class="fas fa-credit-card"></i></div>
                                    <div class="payment-name">Carte bancaire</div>
                                    <div class="card-types">
                                        <i class="fab fa-cc-visa"></i>
                                        <i class="fab fa-cc-mastercard"></i>
                                        <i class="fab fa-cc-amex"></i>
                                    </div>
                                </label>
                            </div>
                            <div class="payment-option">
                                <input type="radio" id="payment_paypal" name="payment_method" value="paypal">
                                <label for="payment_paypal" class="payment-card">
                                    <div class="payment-icon"><i class="fab fa-paypal"></i></div>
                                    <div class="payment-name">PayPal</div>
                                    <div class="card-types"><i class="fab fa-paypal"></i></div>
                                </label>
                            </div>
                        </div>

                        <div class="payment-details" id="card_details">
                            <div class="form-group">
                                <label for="card_number">Numéro de carte</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-credit-card input-icon"></i>
                                    <input type="text" id="card_number" placeholder="1234 5678 9012 3456">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="expiry_date">Date d'expiration</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-calendar input-icon"></i>
                                        <input type="text" id="expiry_date" placeholder="MM/AA">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="cvv">CVV</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-lock input-icon"></i>
                                        <input type="text" id="cvv" placeholder="123">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="card_name">Nom sur la carte</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" id="card_name" placeholder="John Doe">
                                </div>
                            </div>
                            <div class="payment-security">
                                <div class="security-icon"><i class="fas fa-shield-alt"></i></div>
                                <p class="payment-info">Les informations de votre carte ne sont pas enregistrées. Paiement sécurisé conforme PCI DSS.</p>
                            </div>
                        </div>

                        <div class="payment-details" id="paypal_details" style="display: none;">
                            <div class="paypal-info">
                                <div class="paypal-icon"><i class="fab fa-paypal"></i></div>
                                <p>Vous serez redirigé vers PayPal pour finaliser votre paiement en toute sécurité.</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="cart.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Retour au panier</a>
                        <button type="submit" class="btn btn-primary btn-lg checkout-btn"><i class="fas fa-lock"></i> Finaliser la commande</button>
                    </div>
                </form>
            </div>

            <div class="checkout-summary">
                <div class="summary-header">
                    <h2>Récapitulatif</h2>
                    <span class="items-count"><?= count($cartItems) ?> article<?= count($cartItems) > 1 ? 's' : '' ?></span>
                </div>

                <div class="order-items">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="order-item">
                            <div class="item-image">
                                <?php if ($item['image_url']): ?>
                                    <img src="assets/images/sneakers/<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['sneaker_name']) ?>">
                                <?php else: ?>
                                    <div class="no-image"><i class="fas fa-image"></i></div>
                                <?php endif; ?>
                                <div class="item-quantity"><?= $item['quantity'] ?></div>
                            </div>
                            <div class="item-details">
                                <h3><?= htmlspecialchars($item['sneaker_name']) ?></h3>
                                <div class="item-meta">
                                    <span class="item-brand"><?= htmlspecialchars($item['brand_name']) ?></span>
                                    <span class="item-size">Taille: <?= $item['size_value'] ?> (<?= $item['size_type'] ?>)</span>
                                </div>
                            </div>
                            <div class="item-price">
                                <?php
                                $itemPrice = $item['discount_price'] ? $item['discount_price'] : $item['price'];
                                $itemTotal = $itemPrice * $item['quantity'];
                                echo formatPrice($itemTotal);
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="order-totals">
                    <div class="total-row">
                        <span>Sous-total:</span>
                        <span><?= formatPrice($subtotal) ?></span>
                    </div>
                    <div class="total-row">
                        <span>Frais de livraison:</span>
                        <span id="shipping-cost"><?= $shipping > 0 ? formatPrice($shipping) : 'Gratuit' ?></span>
                    </div>
                    <?php if ($subtotal >= 100): ?>
                        <div class="free-shipping-notice">
                            <i class="fas fa-truck"></i> Livraison gratuite
                        </div>
                    <?php else: ?>
                        <div class="free-shipping-progress">
                            <div class="progress-text">
                                <i class="fas fa-truck"></i>
                                Il vous manque <?= formatPrice(100 - $subtotal) ?> pour la livraison gratuite
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= min(100, ($subtotal / 100) * 100) ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($userId): ?>
                        <div class="loyalty-points-section">
                            <div class="total-row">
                                <span>Points de fidélité disponibles:</span>
                                <span><?= $loyaltyPoints ?> points</span>
                            </div>
                            <?php if ($loyaltyPoints >= $conversionRate && $loyaltyDiscount == 0): ?>
                                <form action="checkout.php" method="POST" class="loyalty-form">
                                    <label for="points_to_use">Utiliser vos points (200 points = 10 € de réduction):</label>
                                    <input type="number" name="points_to_use" id="points_to_use" min="<?= $conversionRate ?>" max="<?= $loyaltyPoints ?>" step="<?= $conversionRate ?>" required>
                                    <button type="submit" name="use_points" class="btn btn-small">Appliquer</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($loyaltyDiscount > 0): ?>
                                <div class="total-row">
                                    <span>Réduction (points):</span>
                                    <span>-<?= formatPrice($loyaltyDiscount) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="total-row grand-total">
                        <span>Total:</span>
                        <span id="order-total"><?= formatPrice($total) ?></span>
                    </div>
                </div>

                <div class="checkout-guarantees">
                    <div class="guarantee-item"><i class="fas fa-shield-alt"></i><span>Paiement 100% sécurisé</span></div>
                    <div class="guarantee-item"><i class="fas fa-exchange-alt"></i><span>Retours gratuits sous 30 jours</span></div>
                    <div class="guarantee-item"><i class="fas fa-headset"></i><span>Support client 24/7</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentCard = document.getElementById('payment_card');
    const paymentPaypal = document.getElementById('payment_paypal');
    const cardDetails = document.getElementById('card_details');
    const paypalDetails = document.getElementById('paypal_details');
    const shippingStandard = document.getElementById('shipping_standard');
    const shippingExpress = document.getElementById('shipping_express');
    const shippingCost = document.getElementById('shipping-cost');
    const orderTotal = document.getElementById('order-total');

    const subtotal = <?= $subtotal ?>;
    const standardShipping = <?= $shipping ?>;
    const expressShipping = 8.99;
    const loyaltyDiscount = <?= $loyaltyDiscount ?>;

    function formatPrice(price) {
        return price.toFixed(2).replace('.', ',') + ' €';
    }

    function updateTotal() {
        let shipping = shippingStandard.checked ? standardShipping : expressShipping;
        let total = subtotal + shipping - loyaltyDiscount;
        shippingCost.textContent = shipping > 0 ? formatPrice(shipping) : 'Gratuit';
        orderTotal.textContent = formatPrice(total);
    }

    paymentCard.addEventListener('change', function() {
        if (this.checked) {
            cardDetails.style.display = 'block';
            paypalDetails.style.display = 'none';
        }
    });

    paymentPaypal.addEventListener('change', function() {
        if (this.checked) {
            cardDetails.style.display = 'none';
            paypalDetails.style.display = 'block';
        }
    });

    shippingStandard.addEventListener('change', updateTotal);
    shippingExpress.addEventListener('change', updateTotal);

    updateTotal();
});
</script>

<?php
include 'includes/footer.php';
error_log("Fin de checkout.php");
?>