<?php
// Page des promotions Bander-Sneakers
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Paramètres de pagination
$items_per_page = 12;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Récupérer toutes les sneakers en promotion
$db = getDbConnection();
$stmt = $db->prepare("
    SELECT s.*, b.brand_name, c.category_name,
    (SELECT image_url FROM sneaker_images WHERE sneaker_id = s.sneaker_id AND is_primary = 1 LIMIT 1) AS primary_image
    FROM sneakers s
    LEFT JOIN brands b ON s.brand_id = b.brand_id
    LEFT JOIN categories c ON s.category_id = c.category_id
    WHERE s.discount_price IS NOT NULL AND s.discount_price > 0
    ORDER BY (s.price - s.discount_price) / s.price DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$promotions = $stmt->fetchAll();

// Compter le nombre total de sneakers en promotion pour la pagination
$stmt = $db->prepare("
    SELECT COUNT(*) as total FROM sneakers
    WHERE discount_price IS NOT NULL AND discount_price > 0
");
$stmt->execute();
$result = $stmt->fetch();
$total_items = $result['total'];
$total_pages = ceil($total_items / $items_per_page);

// Récupérer les filtres
$min_discount = 0;
if (isset($_GET['discount'])) {
    $min_discount = (int)$_GET['discount'];
}

// Si on filtre par pourcentage de réduction, on refait la requête
if ($min_discount > 0) {
    $stmt = $db->prepare("
        SELECT s.*, b.brand_name, c.category_name,
        (SELECT image_url FROM sneaker_images WHERE sneaker_id = s.sneaker_id AND is_primary = 1 LIMIT 1) AS primary_image
        FROM sneakers s
        LEFT JOIN brands b ON s.brand_id = b.brand_id
        LEFT JOIN categories c ON s.category_id = c.category_id
        WHERE s.discount_price IS NOT NULL AND s.discount_price > 0
        AND (s.price - s.discount_price) / s.price * 100 >= :min_discount
        ORDER BY (s.price - s.discount_price) / s.price DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':min_discount', $min_discount, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $promotions = $stmt->fetchAll();

    // Mettre à jour le compte total
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM sneakers
        WHERE discount_price IS NOT NULL AND discount_price > 0
        AND (price - discount_price) / price * 100 >= :min_discount
    ");
    $stmt->bindValue(':min_discount', $min_discount, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $total_items = $result['total'];
    $total_pages = ceil($total_items / $items_per_page);
}

// Récupérer les marques et catégories pour les filtres
$brands = getBrands();
$categories = getCategories();

// Titre et description de la page
$page_title = "Promotions - Bander-Sneakers";
$page_description = "Découvrez nos sneakers en promotion. Profitez de réductions exceptionnelles sur les meilleures marques.";

// Récupérer les messages d'erreur ou de succès de la session
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
    /* Ajout de styles pour uniformiser les images comme dans sneakers.php et index.php */
    .product-card .product-image {
        position: relative;
        overflow: hidden;
        height: 250px; /* Hauteur fixe pour uniformité */
    }
    .product-card .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover; /* Garde les proportions sans couper */
        transition: transform 0.3s ease;
    }
    .product-card .product-image:hover img {
        transform: scale(1.05);
    }
</style>

<!-- Display success or error messages -->
<?php if ($success_message || $error_message): ?>
<div class="alert-container">
    <div class="container">
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">Promotions</li>
        </ul>
    </div>
</div>

<!-- Promotions Hero Section -->
<section class="promo-hero">
    <div class="container">
        <div class="promo-hero-content">
            <h1 class="promo-hero-title">Promotions</h1>
            <div class="promo-hero-subtitle">Profitez de réductions exceptionnelles sur nos meilleures sneakers</div>
            <div class="promo-tags">
                <a href="?discount=20" class="promo-tag <?= $min_discount == 20 ? 'active' : '' ?>">-20% et plus</a>
                <a href="?discount=30" class="promo-tag <?= $min_discount == 30 ? 'active' : '' ?>">-30% et plus</a>
                <a href="?discount=40" class="promo-tag <?= $min_discount == 40 ? 'active' : '' ?>">-40% et plus</a>
                <a href="?discount=50" class="promo-tag <?= $min_discount == 50 ? 'active' : '' ?>">-50% et plus</a>
                <?php if ($min_discount > 0): ?>
                    <a href="promotions.php" class="promo-tag reset">Réinitialiser les filtres</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Products Section -->
<section class="section">
    <div class="container">
        <?php if (empty($promotions)): ?>
            <div class="empty-results">
                <i class="fas fa-exclamation-circle"></i>
                <h2>Aucun produit en promotion</h2>
                <p>Aucun produit ne correspond à vos critères de recherche.</p>
                <a href="promotions.php" class="btn btn-primary">Voir toutes les promotions</a>
            </div>
        <?php else: ?>
            <!-- Product Grid -->
            <div class="product-grid">
                <?php foreach ($promotions as $sneaker): ?>
                    <div class="product-card promo-card">
                        <div class="product-image">
                            <?php if ($sneaker['is_new_arrival']): ?>
                                <div class="product-badge new">Nouveau</div>
                            <?php endif; ?>
                            <?php
                                $discount_percent = calculateDiscount($sneaker['price'], $sneaker['discount_price']);
                            ?>
                            <div class="product-badge sale">-<?= $discount_percent ?>%</div>
                            <img src="assets/images/sneakers/<?= htmlspecialchars($sneaker['primary_image']) ?>" alt="<?= htmlspecialchars($sneaker['sneaker_name']) ?>">
                            <div class="product-actions">
                                <a href="wishlist-add.php?id=<?= $sneaker['sneaker_id'] ?>" class="action-btn wishlist-btn" title="Ajouter aux favoris">
                                    <i class="far fa-heart"></i>
                                </a>
                                <a href="sneaker.php?id=<?= $sneaker['sneaker_id'] ?>" class="action-btn view-btn" title="Voir le produit">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                        <div class="product-info">
                            <div class="product-brand"><?= htmlspecialchars($sneaker['brand_name']) ?></div>
                            <h3 class="product-title">
                                <a href="sneaker.php?id=<?= $sneaker['sneaker_id'] ?>"><?= htmlspecialchars($sneaker['sneaker_name']) ?></a>
                            </h3>
                            <div class="product-price">
                                <span class="current-price"><?= formatPrice($sneaker['discount_price']) ?></span>
                                <span class="original-price"><?= formatPrice($sneaker['price']) ?></span>
                            </div>
                            <div class="discount-tag">
                                Économisez <?= formatPrice($sneaker['price'] - $sneaker['discount_price']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?= $current_page - 1 ?><?= $min_discount > 0 ? '&discount=' . $min_discount : '' ?>" class="pagination-link prev">
                            <i class="fas fa-chevron-left"></i> Précédent
                        </a>
                    <?php endif; ?>

                    <div class="pagination-numbers">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?><?= $min_discount > 0 ? '&discount=' . $min_discount : '' ?>" class="pagination-link <?= $i == $current_page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?= $current_page + 1 ?><?= $min_discount > 0 ? '&discount=' . $min_discount : '' ?>" class="pagination-link next">
                            Suivant <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Promotion Banner -->
<section class="promo-banner">
    <div class="container">
        <div class="promo-banner-content">
            <h2>Offre limitée</h2>
            <p>Recevez 10% de réduction supplémentaire sur votre première commande en vous inscrivant à notre newsletter.</p>
            <form class="newsletter-form">
                <input type="email" placeholder="Votre adresse email" required>
                <button type="submit" class="btn btn-primary">S'inscrire</button>
            </form>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de l'ajout à la wishlist avec AJAX
    const wishlistBtns = document.querySelectorAll('.wishlist-btn');
    wishlistBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault(); // Empêche la navigation par défaut
            const url = this.getAttribute('href');

            fetch(url, {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.action === 'added') {
                        showToast('Ajouté aux favoris !', false, 'heart');
                        this.querySelector('i').classList.remove('far');
                        this.querySelector('i').classList.add('fas');
                    } else if (data.action === 'removed') {
                        showToast('Retiré des favoris.', false, 'heart');
                        this.querySelector('i').classList.remove('fas');
                        this.querySelector('i').classList.add('far');
                    }
                } else if (data.redirect) {
                    window.location.href = data.redirect; // Redirection si non connecté
                } else {
                    showToast(data.message || 'Erreur lors de la mise à jour des favoris.', true);
                }
            })
            .catch(error => {
                console.error('Erreur AJAX :', error);
                showToast('Erreur réseau, veuillez réessayer.', true);
            });
        });
    });

    // Fonction pour afficher un toast avec icône personnalisée
    function showToast(message, isError = false, iconType = 'heart') {
        const existingToast = document.getElementById('confirmation-toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.id = 'confirmation-toast';
        const iconClass = isError ? '' : (iconType === 'cart' ? 'fas fa-shopping-cart' : 'fas fa-heart');
        toast.innerHTML = `${isError ? '' : `<i class="${iconClass}" style="margin-right: 10px;"></i>`}${message}`;
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: ${isError ? '#dc3545' : '#28a745'};
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            font-size: 1rem;
            max-width: 300px;
            display: flex;
            align-items: center;
        `;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '1';
        }, 100);

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 3000);
    }
});
</script>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>