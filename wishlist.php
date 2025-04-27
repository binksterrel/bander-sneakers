<?php
// Page de liste de souhaits
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['error_message'] = 'Vous devez être connecté pour accéder à vos favoris.';
    header('Location: login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

// Récupérer les produits de la liste de souhaits
function getWishlistItems($userId) {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT w.wishlist_id, w.sneaker_id, w.created_at, 
               s.sneaker_name, s.price, s.discount_price, s.stock_quantity,
               b.brand_name, c.category_name,
               (SELECT image_url FROM sneaker_images 
                WHERE sneaker_id = s.sneaker_id AND is_primary = 1 LIMIT 1) AS primary_image
        FROM wishlist w
        JOIN sneakers s ON w.sneaker_id = s.sneaker_id
        LEFT JOIN brands b ON s.brand_id = b.brand_id
        LEFT JOIN categories c ON s.category_id = c.category_id
        WHERE w.user_id = :user_id
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$wishlistItems = getWishlistItems($userId);

// Titre et description de la page
$page_title = "Mes Favoris - Bander-Sneakers";
$page_description = "Gérez les produits que vous avez ajoutés à votre liste de favoris sur Bander-Sneakers.";

// Récupérer les messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">Mes Favoris</li>
        </ul>
    </div>
</div>

<!-- Wishlist Section -->
<section class="wishlist-section">
    <div class="container">
        <div class="section-header">
            <h1 class="section-title">Mes Favoris</h1>
            <p class="section-subtitle">Les produits que vous avez sauvegardés pour plus tard.</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($wishlistItems)): ?>
            <div class="empty-wishlist text-center">
                <i class="fas fa-heart-broken fa-3x text-muted"></i>
                <h2 class="mt-3">Votre liste de favoris est vide</h2>
                <p class="text-muted">Vous n'avez encore aucun produit dans vos favoris.</p>
                <a href="sneakers.php" class="btn btn-primary mt-2">Explorer les produits</a>
            </div>
        <?php else: ?>
            <div class="wishlist-grid">
                <?php foreach ($wishlistItems as $item): ?>
                    <div class="wishlist-card">
                        <div class="wishlist-image">
                            <a href="sneaker.php?id=<?= $item['sneaker_id'] ?>">
                                <?php if ($item['primary_image']): ?>
                                    <img src="assets/images/sneakers/<?= htmlspecialchars($item['primary_image']) ?>" 
                                         alt="<?= htmlspecialchars($item['sneaker_name']) ?>" 
                                         class="wishlist-img">
                                <?php else: ?>
                                    <div class="no-image">Aucune image</div>
                                <?php endif; ?>
                            </a>
                            <div class="wishlist-actions">
                                <a href="wishlist-remove.php?id=<?= $item['sneaker_id'] ?>" 
                                   class="wishlist-remove" 
                                   title="Retirer des favoris">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>

                        <div class="wishlist-info">
                            <div class="wishlist-brand"><?= htmlspecialchars($item['brand_name'] ?? 'Marque inconnue') ?></div>
                            <h3 class="wishlist-title">
                                <a href="sneaker.php?id=<?= $item['sneaker_id'] ?>">
                                    <?= htmlspecialchars($item['sneaker_name']) ?>
                                </a>
                            </h3>
                            <div class="wishlist-price">
                                <?php if ($item['discount_price']): ?>
                                    <span class="current-price"><?= formatPrice($item['discount_price']) ?></span>
                                    <span class="original-price text-muted text-decoration-line-through"><?= formatPrice($item['price']) ?></span>
                                <?php else: ?>
                                    <span class="current-price"><?= formatPrice($item['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="wishlist-stock">
                                <?php if ($item['stock_quantity'] > 0): ?>
                                    <span class="in-stock text-success">En stock</span>
                                <?php else: ?>
                                    <span class="out-of-stock text-danger">Rupture de stock</span>
                                <?php endif; ?>
                            </div>
                            <div class="wishlist-buttons mt-2">
                                <?php if ($item['stock_quantity'] > 0): ?>
                                    <a href="sneaker.php?id=<?= $item['sneaker_id'] ?>" class="btn btn-primary btn-sm">Voir le produit</a>
                                <?php else: ?>
                                    <a href="#" class="btn btn-outline-secondary btn-sm notify-btn" data-id="<?= $item['sneaker_id'] ?>">M'avertir</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des boutons "M'avertir"
    const notifyButtons = document.querySelectorAll('.notify-btn');
    notifyButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const sneakerId = this.getAttribute('data-id');
            alert(`Vous serez averti lorsque le produit #${sneakerId} sera de retour en stock (fonctionnalité à venir).`);
        });
    });
});
</script>

<style>
    .wishlist-section {
        padding: 40px 0;
    }
    .section-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .section-title {
        font-size: 2rem;
        margin-bottom: 10px;
    }
    .section-subtitle {
        font-size: 1.1rem;
        color: #6c757d;
    }
    .empty-wishlist {
        padding: 50px;
    }
    .wishlist-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }
    .wishlist-card {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        overflow: hidden;
        transition: box-shadow 0.3s;
    }
    .wishlist-card:hover {
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    .wishlist-image {
        position: relative;
        width: 100%;
        height: 200px; /* Hauteur fixe pour toutes les images */
        overflow: hidden;
    }
    .wishlist-img {
        width: 100%;
        height: 100%;
        object-fit: cover; /* Assure que l'image remplit le conteneur sans déformation */
    }
    .no-image {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        color: #6c757d;
        font-size: 1rem;
    }
    .wishlist-actions {
        position: absolute;
        top: 10px;
        right: 10px;
    }
    .wishlist-remove {
        color: #dc3545;
        font-size: 1.2rem;
        text-decoration: none;
        transition: color 0.3s;
    }
    .wishlist-remove:hover {
        color: #bd2130;
    }
    .wishlist-info {
        padding: 15px;
    }
    .wishlist-brand {
        font-size: 0.9rem;
        color: #6c757d;
    }
    .wishlist-title {
        font-size: 1.1rem;
        margin: 5px 0;
    }
    .wishlist-title a {
        color: #212529;
        text-decoration: none;
    }
    .wishlist-title a:hover {
        color: var(--primary-color);
    }
    .wishlist-price {
        margin: 10px 0;
    }
    .current-price {
        font-size: 1.2rem;
        font-weight: bold;
        color: #212529;
    }
    .original-price {
        font-size: 0.9rem;
        margin-left: 5px;
    }
    .wishlist-stock {
        font-size: 0.9rem;
    }
    .btn-sm {
        padding: 6px 12px;
        font-size: 0.9rem;
    }
</style>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>