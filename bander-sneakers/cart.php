<?php
// Page de panier
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur a un panier
if (!isset($_SESSION['cart_id'])) {
    // Créer un panier pour l'utilisateur
    $db = getDbConnection();
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $sessionId = session_id();

    // Si l'utilisateur n'est pas connecté, on insère NULL pour user_id
    $sql = "INSERT INTO cart (user_id, session_id) VALUES (:user_id, :session_id)";
    $stmt = $db->prepare($sql);

    // Liaison de paramètre conditionnelle pour user_id
    if ($userId) {
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
    }

    $stmt->bindParam(':session_id', $sessionId);
    $stmt->execute();

    $_SESSION['cart_id'] = $db->lastInsertId();
}

// Traitement des actions sur le panier
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $itemId = (int)$_GET['id'];
    $cartId = $_SESSION['cart_id'];
    $db = getDbConnection();

    if ($action === 'remove') {
        // Supprimer un article du panier
        $stmt = $db->prepare("DELETE FROM cart_items WHERE cart_item_id = :item_id AND cart_id = :cart_id");
        $stmt->bindParam(':item_id', $itemId);
        $stmt->bindParam(':cart_id', $cartId);
        $stmt->execute();

        $_SESSION['success_message'] = "Article supprimé du panier.";
        header('Location: cart.php');
        exit();
    } elseif ($action === 'update' && isset($_GET['quantity'])) {
        // Mettre à jour la quantité d'un article
        $quantity = (int)$_GET['quantity'];
        if ($quantity > 0) {
            // Vérifier si l'article existe et appartient au panier de l'utilisateur
            $stmt = $db->prepare("SELECT * FROM cart_items WHERE cart_item_id = :item_id AND cart_id = :cart_id");
            $stmt->bindParam(':item_id', $itemId);
            $stmt->bindParam(':cart_id', $cartId);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $stmt = $db->prepare("UPDATE cart_items SET quantity = :quantity WHERE cart_item_id = :item_id AND cart_id = :cart_id");
                $stmt->bindParam(':quantity', $quantity);
                $stmt->bindParam(':item_id', $itemId);
                $stmt->bindParam(':cart_id', $cartId);
                $stmt->execute();

                $_SESSION['success_message'] = "Quantité mise à jour.";
            }

            header('Location: cart.php');
            exit();
        }
    }
}

// Récupérer les articles du panier
function getCartItems($cartId) {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT ci.*, s.sneaker_name, s.price, s.discount_price, s.primary_image AS image_url, sz.size_value, sz.size_type, b.brand_name
        FROM cart_items ci
        JOIN sneakers s ON ci.sneaker_id = s.sneaker_id
        JOIN sizes sz ON ci.size_id = sz.size_id
        LEFT JOIN brands b ON s.brand_id = b.brand_id
        WHERE ci.cart_id = :cart_id
    ");
    $stmt->bindParam(':cart_id', $cartId);
    $stmt->execute();

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si primary_image est vide, tenter de récupérer une image depuis sneaker_images
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
        }
    }

    return $items;
}

$cartItems = getCartItems($_SESSION['cart_id']);

// Calculer le total du panier
$subtotal = 0;
$total = 0;
$shipping = 0;

foreach ($cartItems as $item) {
    $price = $item['discount_price'] ? $item['discount_price'] : $item['price'];
    $subtotal += $price * $item['quantity'];
}

// Frais de livraison
if ($subtotal > 0 && $subtotal < 100) {
    $shipping = 5.99;
}

$total = $subtotal + $shipping;

// Titre et description de la page
$page_title = "Panier - Bander-Sneakers";
$page_description = "Votre panier d'achat sur Bander-Sneakers. Consultez vos articles et passez commande.";

// Récupérer les messages
$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Inclure l'en-tête
include 'includes/header.php';
?>

<style>
    /* Styles pour les contrôles de quantité */
    .quantity-control {
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #ddd;
        border-radius: 30px;
        overflow: hidden;
        width: 110px;
        height: 36px;
        background: #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
    }

    .quantity-control:hover {
        border-color: var(--primary-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .quantity-btn {
        flex: 0 0 36px;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f5f5f5;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
        padding: 0;
    }

    .quantity-btn i {
        font-size: 12px;
        color: #333;
        transition: all 0.2s ease;
    }

    .quantity-btn:hover:not(:disabled) {
        background: var(--primary-color);
    }

    .quantity-btn:hover:not(:disabled) i {
        color: white;
    }

    .quantity-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .quantity-btn:active:not(:disabled) {
        transform: scale(0.95);
    }

    .quantity-input {
        flex: 1;
        text-align: center;
        border: none;
        padding: 0;
        font-weight: 600;
        width: 38px;
        height: 100%;
        outline: none;
        color: var(--text-color);
        background: transparent;
    }

    .quantity-input::-webkit-outer-spin-button,
    .quantity-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    /* Styles pour retour visuel */
    .update-success {
        animation: flash-success 0.8s;
    }

    .update-error {
        animation: flash-error 0.8s;
    }

    @keyframes flash-success {
        0%, 100% { background-color: transparent; }
        50% { background-color: rgba(40, 167, 69, 0.2); }
    }

    @keyframes flash-error {
        0%, 100% { background-color: transparent; }
        50% { background-color: rgba(220, 53, 69, 0.2); }
    }
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">Panier</li>
        </ul>
    </div>
</div>

<!-- Cart Section -->
<section class="cart-section">
    <div class="container">
        <div class="cart-header">
            <h1 class="cart-title">Votre Panier</h1>
            <div class="checkout-steps">
                <div class="checkout-step active">
                    <div class="step-number"><i class="fas fa-shopping-cart"></i></div>
                    <div class="step-label">Panier</div>
                </div>
                <div class="step-connector"></div>
                <div class="checkout-step">
                    <div class="step-number"><i class="fas fa-credit-card"></i></div>
                    <div class="step-label">Paiement</div>
                </div>
                <div class="step-connector"></div>
                <div class="checkout-step">
                    <div class="step-number"><i class="fas fa-check"></i></div>
                    <div class="step-label">Confirmation</div>
                </div>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h2>Votre panier est vide</h2>
                <p>Vous n'avez aucun article dans votre panier actuellement.</p>
                <a href="sneakers.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag"></i> Découvrir nos sneakers
                </a>
            </div>
        <?php else: ?>
            <div class="cart-content">
                <div class="cart-items-container">
                    <div class="cart-items-header">
                        <h2>Articles (<?= count($cartItems) ?>)</h2>
                    </div>
                    <div class="cart-items">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item" id="item-<?= $item['cart_item_id'] ?>">
                                <div class="item-image">
                                    <?php if ($item['image_url']): ?>
                                        <a href="sneaker.php?id=<?= $item['sneaker_id'] ?>">
                                            <img src="assets/images/sneakers/<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['sneaker_name']) ?>">
                                        </a>
                                    <?php else: ?>
                                        <div class="no-image"><i class="fas fa-image"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="item-details">
                                    <h3><a href="sneaker.php?id=<?= $item['sneaker_id'] ?>"><?= htmlspecialchars($item['sneaker_name']) ?></a></h3>
                                    <div class="item-meta">
                                        <span class="item-brand"><?= htmlspecialchars($item['brand_name']) ?></span>
                                        <span class="item-size">Taille: <?= $item['size_value'] ?> (<?= $item['size_type'] ?>)</span>
                                    </div>
                                </div>
                                <div class="item-price">
                                    <?php if ($item['discount_price']): ?>
                                        <span class="current-price"><?= formatPrice($item['discount_price']) ?></span>
                                        <span class="original-price"><?= formatPrice($item['price']) ?></span>
                                    <?php else: ?>
                                        <span class="current-price"><?= formatPrice($item['price']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="item-quantity">
                                    <div class="quantity-control">
                                        <button type="button" class="quantity-btn minus"
                                                data-item-id="<?= $item['cart_item_id'] ?>"
                                                <?= $item['quantity'] <= 1 ? 'disabled' : '' ?>>
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number"
                                               class="quantity-input"
                                               value="<?= $item['quantity'] ?>"
                                               min="1"
                                               data-item-id="<?= $item['cart_item_id'] ?>"
                                               readonly>
                                        <button type="button" class="quantity-btn plus"
                                                data-item-id="<?= $item['cart_item_id'] ?>">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="item-total">
                                    <?php
                                    $itemPrice = $item['discount_price'] ? $item['discount_price'] : $item['price'];
                                    $itemTotal = $itemPrice * $item['quantity'];
                                    echo formatPrice($itemTotal);
                                    ?>
                                </div>
                                <div class="item-actions">
                                    <a href="cart.php?action=remove&id=<?= $item['cart_item_id'] ?>" class="remove-btn" title="Supprimer">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="cart-summary">
                    <div class="summary-header">
                        <h2>Récapitulatif</h2>
                    </div>

                    <div class="order-totals">
                        <div class="total-row">
                            <span>Sous-total:</span>
                            <span id="subtotal"><?= formatPrice($subtotal) ?></span>
                        </div>
                        <div class="total-row">
                            <span>Frais de livraison:</span>
                            <span id="shipping"><?= $shipping > 0 ? formatPrice($shipping) : 'Gratuit' ?></span>
                        </div>
                        <?php if ($subtotal >= 100): ?>
                        <div class="free-shipping-notice">
                            <i class="fas fa-truck"></i> Livraison gratuite
                        </div>
                        <?php else: ?>
                        <div class="free-shipping-progress">
                            <div class="progress-text">
                                <i class="fas fa-truck"></i>
                                Il vous manque <?= formatPrice(100 - $subtotal) ?> pour bénéficier de la livraison gratuite
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= min(100, ($subtotal / 100) * 100) ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="total-row grand-total">
                            <span>Total:</span>
                            <span id="total"><?= formatPrice($total) ?></span>
                        </div>
                    </div>

                    <div class="cart-actions">
                        <a href="checkout.php" class="btn btn-primary btn-lg checkout-btn">
                            <i class="fas fa-credit-card"></i> Passer à la caisse
                        </a>
                        <a href="sneakers.php" class="btn btn-primary btn-lg checkout-btn">
                            <i class="fas fa-arrow-left"></i> Continuer vos achats
                        </a>
                    </div>

                    <div class="checkout-guarantees">
                        <div class="guarantee-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Paiement 100% sécurisé</span>
                        </div>
                        <div class="guarantee-item">
                            <i class="fas fa-exchange-alt"></i>
                            <span>Retours gratuits sous 30 jours</span>
                        </div>
                        <div class="guarantee-item">
                            <i class="fas fa-headset"></i>
                            <span>Support client disponible 24/7</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Récupérer tous les boutons + et - du panier
    const plusButtons = document.querySelectorAll('.quantity-btn.plus');
    const minusButtons = document.querySelectorAll('.quantity-btn.minus');

    // Fonction pour mettre à jour le panier
    function updateCart(itemId, newQuantity, button) {
        // Désactiver le bouton cliqué
        button.disabled = true;

        // Ajouter un indicateur de chargement
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        // Trouver l'élément du panier concerné
        const cartItem = document.getElementById('item-' + itemId);
        const quantityInput = cartItem.querySelector('.quantity-input');
        const minusBtn = cartItem.querySelector('.quantity-btn.minus');

        // Effectuer la requête AJAX
        fetch(`cart.php?action=update&id=${itemId}&quantity=${newQuantity}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur réseau');
                }
                return response.text();
            })
            .then(() => {
                // Mise à jour réussie
                quantityInput.value = newQuantity;
                minusBtn.disabled = (newQuantity <= 1);

                // Mettre à jour les totaux
                updateCartTotals();

                // Effet visuel de succès
                cartItem.classList.add('update-success');

                setTimeout(() => {
                    cartItem.classList.remove('update-success');
                }, 800);

                // Message de confirmation
                showToast(`Quantité mise à jour : ${newQuantity}`);
            })
            .catch(() => {
                // Effet visuel d'erreur
                cartItem.classList.add('update-error');

                setTimeout(() => {
                    cartItem.classList.remove('update-error');
                }, 800);

                // Afficher un message d'erreur
                showToast('Erreur lors de la mise à jour du panier', true);
            })
            .finally(() => {
                // Restaurer le bouton
                button.innerHTML = originalContent;
                button.disabled = false;
            });
    }

    // Fonction pour calculer et mettre à jour les totaux
    function updateCartTotals() {
        let subtotal = 0;

        // Calculer le sous-total
        document.querySelectorAll('.cart-item').forEach(item => {
            const priceText = item.querySelector('.current-price').textContent;
            const price = parseFloat(priceText.replace(/[^\d,]/g, '').replace(',', '.')); // Gère aussi les prix avec virgule
            const quantity = parseInt(item.querySelector('.quantity-input').value);

            // Mise à jour du total par article
            const itemTotal = price * quantity;
            item.querySelector('.item-total').textContent = formatPrice(itemTotal);

            subtotal += itemTotal;
        });

        // Calculer les frais de livraison et le total
        const shipping = (subtotal > 0 && subtotal < 100) ? 5.99 : 0;
        const total = subtotal + shipping;

        // Mettre à jour l'affichage
        document.getElementById('subtotal').textContent = formatPrice(subtotal);
        document.getElementById('shipping').textContent = shipping > 0 ? formatPrice(shipping) : 'Gratuit';
        document.getElementById('total').textContent = formatPrice(total);

        // Mise à jour de la barre de progression pour la livraison gratuite
        const progressBar = document.querySelector('.progress-fill');
        const progressText = document.querySelector('.progress-text');
        const freeShippingNotice = document.querySelector('.free-shipping-notice');
        const freeShippingProgress = document.querySelector('.free-shipping-progress');

        if (subtotal >= 100) {
            if (freeShippingProgress) freeShippingProgress.style.display = 'none';
            if (freeShippingNotice) freeShippingNotice.style.display = 'flex';
        } else {
            if (freeShippingProgress) {
                freeShippingProgress.style.display = 'block';
                if (progressBar) {
                    const progress = Math.min(100, (subtotal / 100) * 100);
                    progressBar.style.width = `${progress}%`;
                }
                if (progressText) {
                    const remaining = (100 - subtotal).toFixed(2);
                    progressText.innerHTML = `<i class="fas fa-truck"></i> Il vous manque ${formatPrice(remaining)} pour bénéficier de la livraison gratuite`;
                }
            }
            if (freeShippingNotice) freeShippingNotice.style.display = 'none';
        }
    }

    // Fonction utilitaire pour formater les prix
    function formatPrice(price) {
        return price.toFixed(2).replace('.', ',') + ' €';
    }

    // Fonction pour afficher un toast de confirmation
    function showToast(message, isError = false) {
        // Vérifier si un toast existe déjà et le supprimer
        const existingToast = document.getElementById('cart-toast');
        if (existingToast) {
            existingToast.remove();
        }

        // Créer le toast
        const toast = document.createElement('div');
        toast.id = 'cart-toast';
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

        // Ajouter une icône selon le type de message
        if (isError) {
            toast.innerHTML = `<i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> ${message}`;
        } else {
            toast.innerHTML = `<i class="fas fa-check-circle" style="margin-right: 8px;"></i> ${message}`;
        }

        // Ajouter au document
        document.body.appendChild(toast);

        // Animation d'entrée
        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        }, 10);

        // Faire disparaître après 3 secondes
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(20px)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }

    // Gestionnaire d'événement pour les boutons "+"
    plusButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (this.disabled) return;

            const itemId = this.getAttribute('data-item-id');
            const quantityInput = document.querySelector(`.quantity-input[data-item-id="${itemId}"]`);
            const newQuantity = parseInt(quantityInput.value) + 1;

            updateCart(itemId, newQuantity, this);
        });
    });

    // Gestionnaire d'événement pour les boutons "-"
    minusButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (this.disabled) return;

            const itemId = this.getAttribute('data-item-id');
            const quantityInput = document.querySelector(`.quantity-input[data-item-id="${itemId}"]`);
            const currentQuantity = parseInt(quantityInput.value);

            if (currentQuantity > 1) {
                updateCart(itemId, currentQuantity - 1, this);
            }
        });
    });
});
</script>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>