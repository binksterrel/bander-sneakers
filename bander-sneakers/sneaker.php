<?php
// Page de détail d'une sneaker
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'ID de la sneaker est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$sneakerId = (int)$_GET['id'];

// Récupérer les détails de la sneaker
$sneaker = getSneakerById($sneakerId);

// Si la sneaker n'existe pas, rediriger vers la page d'accueil
if (!$sneaker) {
    header('Location: index.php');
    exit();
}

// Récupérer les images de la sneaker
$images = getSneakerImages($sneakerId);

// Récupérer les tailles disponibles
$sizes = getSneakerSizes($sneakerId);

// Récupérer les avis
$reviews = getSneakerReviews($sneakerId);

// Récupérer des sneakers similaires (même marque et catégorie)
$similarSneakers = getSneakers([
    'brand_id' => $sneaker['brand_id'],
    'category_id' => $sneaker['category_id']
], 4);

// Titre et description de la page
$page_title = $sneaker['sneaker_name'] . ' - ' . $sneaker['brand_name'] . ' | Bander-Sneakers';
$page_description = substr(strip_tags($sneaker['description']), 0, 160);

// Inclure l'en-tête
include 'includes/header.php';

?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li><a href="sneakers.php">Sneakers</a></li>
            <li><a href="sneakers.php?brand_id=<?= $sneaker['brand_id'] ?>"><?= htmlspecialchars($sneaker['brand_name']) ?></a></li>
            <li class="active"><?= htmlspecialchars($sneaker['sneaker_name']) ?></li>
        </ul>
    </div>
</div>

<!-- Product Detail Section -->
<section class="product-detail">
    <div class="container">
        <div class="product-gallery">
            <?php if (!empty($images)): ?>
                <div class="main-image">
                    <img src="assets/images/sneakers/<?= htmlspecialchars($images[0]['image_url']) ?>" alt="<?= htmlspecialchars($sneaker['sneaker_name']) ?>">
                </div>

                <?php if (count($images) > 1): ?>
                    <div class="thumbnail-list">
                        <?php foreach ($images as $index => $image): ?>
                            <div class="thumbnail <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>">
                                <img src="assets/images/sneakers/<?= htmlspecialchars($image['image_url']) ?>" alt="<?= htmlspecialchars($sneaker['sneaker_name']) ?> - Image <?= $index + 1 ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="main-image">
                    <div class="no-image">Image non disponible</div>
                </div>
            <?php endif; ?>
        </div>

        <div class="product-info-detail">
            <h1><?= htmlspecialchars($sneaker['sneaker_name']) ?></h1>

            <div class="product-meta">
                <span class="brand">Marque: <a href="sneakers.php?brand_id=<?= $sneaker['brand_id'] ?>"><?= htmlspecialchars($sneaker['brand_name']) ?></a></span>
                <span class="category">Catégorie: <a href="sneakers.php?category_id=<?= $sneaker['category_id'] ?>"><?= htmlspecialchars($sneaker['category_name']) ?></a></span>
                <span class="sku">SKU: BS-<?= $sneakerId ?></span>
                <?php if ($sneaker['release_date']): ?>
                    <span class="release-date">Date de sortie: <?= date('d/m/Y', strtotime($sneaker['release_date'])) ?></span>
                <?php endif; ?>
            </div>

            <div class="product-price-detail">
                <?php if ($sneaker['discount_price']): ?>
                    <span class="current-price"><?= formatPrice($sneaker['discount_price']) ?></span>
                    <span class="original-price"><?= formatPrice($sneaker['price']) ?></span>
                    <span class="discount">-<?= calculateDiscount($sneaker['price'], $sneaker['discount_price']) ?>%</span>
                <?php else: ?>
                    <span class="current-price"><?= formatPrice($sneaker['price']) ?></span>
                <?php endif; ?>
            </div>

            <div class="product-description">
                <?= nl2br(htmlspecialchars($sneaker['description'])) ?>
            </div>

            <form class="add-to-cart-form" method="post">
                <input type="hidden" name="sneaker_id" value="<?= $sneakerId ?>">
                <input type="hidden" name="size_id" value="">

                <?php if (!empty($sizes)): ?>
                    <div class="size-selection">
                        <h3>Sélectionnez votre taille</h3>
                        <div class="size-options">
                            <?php foreach ($sizes as $size): ?>
                                <div class="size-option <?= $size['stock_quantity'] > 0 ? '' : 'disabled' ?>" data-size-id="<?= $size['size_id'] ?>">
                                    <?= htmlspecialchars($size['size_value']) ?>
                                    <?php if ($size['stock_quantity'] <= 0): ?>
                                        <span class="out-of-stock-label">Épuisé</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="size-error" style="display: none; color: #dc3545; margin-top: 0.5rem;">Veuillez sélectionner une taille disponible</p>
                    </div>
                <?php else: ?>
                    <div class="out-of-stock">
                        <p>Ce produit est actuellement épuisé.</p>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Quantité :</label>
                    <div class="quantity-selector">
                        <button type="button" class="quantity-decrease">-</button>
                        <input type="number" name="quantity" id="quantity" value="1" min="1" max="10">
                        <button type="button" class="quantity-increase">+</button>
                    </div>
                </div>

                <div class="product-actions-detail">
                    <button type="submit" class="btn btn-primary add-to-cart" <?= empty($sizes) ? 'disabled' : '' ?>>
                        <i class="fas fa-shopping-cart"></i> Ajouter au panier
                    </button>

                    <a href="wishlist-add.php?id=<?= $sneakerId ?>" class="btn wishlist-btn">
                        <i class="far fa-heart"></i>
                    </a>
                </div>
            </form>

            <div class="product-additional-info">
                <div class="info-item">
                    <i class="fas fa-truck"></i> Livraison gratuite pour les commandes de plus de 100€
                </div>
                <div class="info-item">
                    <i class="fas fa-undo"></i> Retours gratuits sous 30 jours
                </div>
                <div class="info-item">
                    <i class="fas fa-shield-alt"></i> Produit 100% authentique garanti
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Product Tabs Section -->
<section class="product-tabs">
    <div class="container">
        <div class="tabs">
            <div class="tab-links">
                <a href="#description" class="tab-link active">Description</a>
                <a href="#specifications" class="tab-link">Spécifications</a>
                <a href="#reviews" class="tab-link">Avis (<?= count($reviews) ?>)</a>
            </div>

            <div class="tab-content">
                <div id="description" class="tab-pane active">
                    <h2>Description du produit</h2>
                    <div class="tab-text">
                        <?= nl2br(htmlspecialchars($sneaker['description'])) ?>
                    </div>
                </div>

                <div id="specifications" class="tab-pane">
                    <h2>Spécifications</h2>
                    <div class="specifications-list">
                        <div class="spec-item">
                            <span class="spec-name">Marque:</span>
                            <span class="spec-value"><?= htmlspecialchars($sneaker['brand_name']) ?></span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-name">Catégorie:</span>
                            <span class="spec-value"><?= htmlspecialchars($sneaker['category_name']) ?></span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-name">Référence:</span>
                            <span class="spec-value">BS-<?= $sneakerId ?></span>
                        </div>
                        <?php if ($sneaker['release_date']): ?>
                            <div class="spec-item">
                                <span class="spec-name">Date de sortie:</span>
                                <span class="spec-value"><?= date('d/m/Y', strtotime($sneaker['release_date'])) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="spec-item">
                            <span class="spec-name">Tailles disponibles:</span>
                            <span class="spec-value">
                                <?php if (!empty($sizes)): ?>
                                    <?php
                                    $size_values = array_map(function($size) {
                                        return htmlspecialchars($size['size_value']) . ($size['stock_quantity'] <= 0 ? ' (Épuisé)' : '');
                                    }, $sizes);
                                    echo implode(', ', $size_values);
                                    ?>
                                <?php else: ?>
                                    Aucune taille disponible
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div id="reviews" class="tab-pane">
                    <h2>Avis clients</h2>
                    <?php if (!empty($reviews)): ?>
                        <div class="reviews-list">
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-item">
                                    <div class="review-header">
                                        <div class="review-author">
                                            <strong><?= htmlspecialchars($review['username'] ?: 'Utilisateur anonyme') ?></strong>
                                        </div>
                                        <div class="review-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i <= $review['rating'] ? '' : '-o' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="review-date">
                                            <?= date('d/m/Y', strtotime($review['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="review-content">
                                        <?= nl2br(htmlspecialchars($review['review_text'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-reviews">Aucun avis pour le moment. Soyez le premier à donner votre avis sur ce produit !</p>
                    <?php endif; ?>

                    <?php if (isLoggedIn()): ?>
                        <div class="add-review">
                            <h3>Donnez votre avis</h3>
                            <form action="add-review.php" method="post" class="review-form">
                                <input type="hidden" name="sneaker_id" value="<?= $sneakerId ?>">

                                <div class="form-group">
                                    <label>Note:</label>
                                    <div class="rating-selector">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input type="radio" name="rating" value="<?= $i ?>" id="rating-<?= $i ?>" required <?= $i === 5 ? 'checked' : '' ?>>
                                            <label for="rating-<?= $i ?>"><i class="fas fa-star"></i></label>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="review_text">Votre avis:</label>
                                    <textarea name="review_text" id="review_text" rows="5" required></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary">Soumettre l'avis</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <p class="login-to-review">
                            <a href="login.php">Connectez-vous</a> pour laisser un avis.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Similar Products Section -->
<section class="section similar-products">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Produits similaires</h2>
            <p class="section-subtitle">Vous pourriez également aimer ces sneakers.</p>
        </div>

        <div class="product-grid">
            <?php
            $count = 0;
            foreach ($similarSneakers as $similar):
                if ($similar['sneaker_id'] == $sneakerId) continue;
                if ($count >= 4) break;
                $count++;
            ?>
                <div class="product-card">
                    <div class="product-image">
                        <?php if ($similar['discount_price']): ?>
                            <div class="product-badge sale">-<?= calculateDiscount($similar['price'], $similar['discount_price']) ?>%</div>
                        <?php endif; ?>

                        <img src="assets/images/sneakers/<?= htmlspecialchars($similar['primary_image']) ?>" alt="<?= htmlspecialchars($similar['sneaker_name']) ?>">

                        <div class="product-actions">
                            <a href="wishlist-add.php?id=<?= $similar['sneaker_id'] ?>" class="action-btn wishlist-btn" title="Ajouter aux favoris">
                                <i class="far fa-heart"></i>
                            </a>
                            <a href="sneaker.php?id=<?= $similar['sneaker_id'] ?>" class="action-btn view-btn" title="Voir le produit">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>

                    <div class="product-info">
                        <div class="product-brand"><?= htmlspecialchars($similar['brand_name']) ?></div>
                        <h3 class="product-title">
                            <a href="sneaker.php?id=<?= $similar['sneaker_id'] ?>"><?= htmlspecialchars($similar['sneaker_name']) ?></a>
                        </h3>
                        <div class="product-price">
                            <?php if ($similar['discount_price']): ?>
                                <span class="current-price"><?= formatPrice($similar['discount_price']) ?></span>
                                <span class="original-price"><?= formatPrice($similar['price']) ?></span>
                            <?php else: ?>
                                <span class="current-price"><?= formatPrice($similar['price']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            tabLinks.forEach(link => link.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            this.classList.add('active');
            document.querySelector(this.getAttribute('href')).classList.add('active');
        });
    });

    // Gestion du formulaire avec AJAX (panier)
    const addToCartForm = document.querySelector('.add-to-cart-form');
    const sizeOptions = document.querySelectorAll('.size-option');
    const sizeInput = document.querySelector('input[name="size_id"]');
    const sizeError = document.querySelector('.size-error');
    const decreaseBtn = document.querySelector('.quantity-decrease');
    const increaseBtn = document.querySelector('.quantity-increase');
    const quantityInput = document.querySelector('#quantity');

    if (addToCartForm && sizeOptions.length > 0) {
        sizeOptions.forEach(option => {
            option.addEventListener('click', function() {
                if (!this.classList.contains('disabled')) {
                    sizeOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    sizeInput.value = this.getAttribute('data-size-id');
                    if (sizeError) sizeError.style.display = 'none';
                }
            });
        });

        addToCartForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!sizeInput.value) {
                if (sizeError) sizeError.style.display = 'block';
                return;
            }

            const formData = new FormData(addToCartForm);

            fetch('cart-add.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cartCountElement = document.querySelector('.cart-count');
                    if (cartCountElement && data.cart_count !== undefined) {
                        cartCountElement.textContent = data.cart_count;
                    }
                    showToast('Article ajouté au panier !');
                } else {
                    showToast(data.message || 'Erreur lors de l\'ajout au panier.', true);
                }
            })
            .catch(error => {
                console.error('Erreur AJAX :', error);
                showToast('Erreur réseau, veuillez réessayer.', true);
            });
        });
    }

    // Gestion de la quantité
    if (decreaseBtn && increaseBtn && quantityInput) {
        decreaseBtn.addEventListener('click', function() {
            const value = Number.parseInt(quantityInput.value) || 1;
            if (value > 1) {
                quantityInput.value = value - 1;
            }
        });

        increaseBtn.addEventListener('click', function() {
            const value = Number.parseInt(quantityInput.value) || 1;
            if (value < 10) {
                quantityInput.value = value + 1;
            }
        });

        quantityInput.addEventListener('input', function() {
            let value = Number.parseInt(this.value) || 1;
            if (value < 1) value = 1;
            if (value > 10) value = 10;
            this.value = value;
        });
    }

    // Gestion de l'ajout à la wishlist avec AJAX
    const wishlistBtns = document.querySelectorAll('.wishlist-btn');
    wishlistBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
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
                    window.location.href = data.redirect;
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

    // Gestion de la galerie d'images
    const thumbnails = document.querySelectorAll('.thumbnail');
    const mainImage = document.querySelector('.main-image img');
    thumbnails.forEach(thumbnail => {
        thumbnail.addEventListener('click', function() {
            thumbnails.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            mainImage.src = this.querySelector('img').src;
        });
    });

    // Fonction pour afficher un toast avec icône personnalisée
    function showToast(message, isError = false, iconType = 'cart') {
        const existingToast = document.getElementById('confirmation-toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.id = 'confirmation-toast';
        const iconClass = isError ? 'fas fa-exclamation-circle' : (iconType === 'cart' ? 'fas fa-shopping-cart' : 'fas fa-heart');
        toast.innerHTML = `<i class="${iconClass}" style="margin-right: 10px;"></i>${message}`;
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