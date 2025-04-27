<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'ID du produit est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || $_GET['id'] <= 0) {
    $_SESSION['error_message'] = "ID de produit invalide.";
    header('Location: 2ndhand.php');
    exit;
}

$product_id = (int)$_GET['id'];
$error = '';

try {
    // Connexion à la base de données
    $db = getDbConnection();

    // Récupérer les détails du produit
    $query = "SELECT sp.*, u.username, u.email, c.category_name, b.brand_name 
              FROM secondhand_products sp 
              JOIN users u ON sp.user_id = u.user_id 
              LEFT JOIN categories c ON sp.category_id = c.category_id 
              LEFT JOIN brands b ON sp.brand_id = b.brand_id 
              WHERE sp.id = :id AND sp.statut = 'actif'";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $_SESSION['error_message'] = "Produit non trouvé ou désactivé.";
        header('Location: 2ndhand.php');
        exit;
    }

    // Incrémenter le nombre de vues
    $update_views_query = "UPDATE secondhand_products SET views = views + 1 WHERE id = :id";
    $update_stmt = $db->prepare($update_views_query);
    $update_stmt->execute([':id' => $product_id]);

    // Recharger les données du produit pour obtenir le nombre de vues mis à jour (optionnel)
    $stmt->execute([':id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    // Récupérer des annonces similaires
    $similar_query = "SELECT sp.*, c.category_name, b.brand_name 
                     FROM secondhand_products sp 
                     LEFT JOIN categories c ON sp.category_id = c.category_id 
                     LEFT JOIN brands b ON sp.brand_id = b.brand_id 
                     WHERE sp.statut = 'actif' 
                     AND sp.id != :id 
                     AND (sp.category_id = :category_id OR sp.brand_id = :brand_id) 
                     LIMIT 4";
    $stmt = $db->prepare($similar_query);
    $stmt->execute([
        ':id' => $product_id,
        ':category_id' => $product['category_id'],
        ':brand_id' => $product['brand_id']
    ]);
    $similar_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération du produit : " . htmlspecialchars($e->getMessage());
}

$page_title = htmlspecialchars($product['title']) . ' | Bander-Sneakers';
$page_description = substr(strip_tags($product['description']), 0, 160);
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li><a href="2ndhand.php">2ndHand</a></li>
            <?php if ($product['brand_id']): ?>
                <li><a href="2ndhand.php?brand_id=<?= $product['brand_id'] ?>"><?= htmlspecialchars($product['brand_name']) ?></a></li>
            <?php endif; ?>
            <li class="active"><?= htmlspecialchars($product['title']) ?></li>
        </ul>
    </div>
</div>

<!-- Product Detail Section -->
<section class="product-detail">
    <div class="container">
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <div class="product-gallery">
                <?php
                $images = explode(',', $product['images']);
                $first_image = !empty($images[0]) ? htmlspecialchars($images[0]) : 'assets/images/placeholder.jpg';
                ?>
                <div class="main-image">
                    <img src="<?php echo $first_image; ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" id="main-image">
                </div>
                <?php if (count($images) > 1): ?>
                    <div class="thumbnail-list">
                        <?php foreach ($images as $index => $image): ?>
                            <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" data-image="<?php echo htmlspecialchars($image); ?>">
                                <img src="<?php echo htmlspecialchars($image); ?>" alt="Thumbnail <?php echo $index + 1; ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="product-info-detail">
                <h1><?= htmlspecialchars($product['title']) ?></h1>

                <div class="product-meta">
                    <?php if ($product['brand_id']): ?>
                        <span class="brand">Marque: <a href="2ndhand.php?brand_id=<?= $product['brand_id'] ?>"><?= htmlspecialchars($product['brand_name']) ?></a></span>
                    <?php endif; ?>
                    <span class="category">Catégorie: <a href="2ndhand.php?category_id=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name'] ?: 'Non spécifiée') ?></a></span>
                    <span class="etat">État: <?= htmlspecialchars($product['etat']) ?></span>
                    <span class="size">Taille: <?= htmlspecialchars($product['size']) ?></span>
                    <?php if ($product['location']): ?>
                        <span class="location">Localisation: <?= htmlspecialchars($product['location']) ?></span>
                    <?php endif; ?>
                    <?php if ($product['shipping_method']): ?>
                        <span class="shipping">Expédition: <?= htmlspecialchars($product['shipping_method']) ?></span>
                    <?php endif; ?>
                    <span class="release-date">Publié le: <?= date('d/m/Y', strtotime($product['created_at'])) ?></span>
                    <span class="views">Vues: <?= htmlspecialchars($product['views']) ?></span>
                    <span class="seller">Vendu par: <a href="profile.php?user_id=<?= htmlspecialchars($product['user_id']) ?>"><?= htmlspecialchars($product['username']) ?></a></span>
                </div>

                <div class="product-price-detail">
                    <span class="current-price"><?= number_format($product['price'], 2) ?> €</span>
                </div>

                <div class="product-description">
                    <?= nl2br(htmlspecialchars($product['description'])) ?>
                </div>

                <div class="product-actions-detail">
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $product['user_id']): ?>
                        <a href="2ndhand-edit.php?id=<?= $product['id'] ?>" class="btn btn-primary">Modifier l'annonce</a>
                        <a href="compte.php#conversations" class="btn btn-secondary">Voir les conversations</a>
                    <?php else: ?>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <form id="start-conversation-form" method="post" action="start-conversation.php">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <input type="hidden" name="seller_id" value="<?= $product['user_id'] ?>">
                                    <button type="submit" class="btn btn-primary start-conversation-btn">
                                        <i class="fas fa-comments"></i> Contacter le vendeur
                                    </button>
                                </form>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-primary">Se connecter pour contacter</a>
                            <?php endif; ?>
                            <a href="profile.php?user_id=<?= htmlspecialchars($product['user_id']) ?>" class="btn btn-primary">
                                Vendu par: <?= htmlspecialchars($product['username']) ?>
                            </a>
                        </div>
                        <a href="report.php?type=secondhand&id=<?= $product['id'] ?>" class="btn btn-secondary" onclick="return confirm('Êtes-vous sûr de vouloir signaler cette annonce ?');">Signaler l'annonce</a>
                    <?php endif; ?>
                </div>

                <div class="product-additional-info">
                    <div class="info-item">
                        <i class="fas fa-handshake"></i> Achat et vente en direct entre particuliers
                    </div>
                    <div class="info-item">
                        <i class="fas fa-user-shield"></i> Assurez-vous de bien vérifier le produit avant l'achat
                    </div>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i> Possibilité de remise en main propre selon accord avec le vendeur
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Product Tabs Section -->
<section class="product-tabs">
    <div class="container">
        <div class="tabs">
            <div class="tab-links">
                <a href="#description" class="tab-link active">Description</a>
                <a href="#specifications" class="tab-link">Spécifications</a>
                <a href="#reviews" class="tab-link">Avis (0)</a>
            </div>

            <div class="tab-content">
                <div id="description" class="tab-pane active">
                    <h2>Description de l'annonce</h2>
                    <div class="tab-text">
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </div>
                </div>

                <div id="specifications" class="tab-pane">
                    <h2>Spécifications</h2>
                    <div class="specifications-list">
                        <?php if ($product['brand_id']): ?>
                            <div class="spec-item">
                                <span class="spec-name">Marque:</span>
                                <span class="spec-value"><?= htmlspecialchars($product['brand_name']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="spec-item">
                            <span class="spec-name">Catégorie:</span>
                            <span class="spec-value"><?= htmlspecialchars($product['category_name'] ?: 'Non spécifiée') ?></span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-name">État:</span>
                            <span class="spec-value"><?= htmlspecialchars($product['etat']) ?></span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-name">Taille:</span>
                            <span class="spec-value"><?= htmlspecialchars($product['size']) ?></span>
                        </div>
                        <?php if ($product['location']): ?>
                            <div class="spec-item">
                                <span class="spec-name">Localisation:</span>
                                <span class="spec-value"><?= htmlspecialchars($product['location']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($product['shipping_method']): ?>
                            <div class="spec-item">
                                <span class="spec-name">Expédition:</span>
                                <span class="spec-value"><?= htmlspecialchars($product['shipping_method']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="spec-item">
                            <span class="spec-name">Vendu par:</span>
                            <span class="spec-value"><?= htmlspecialchars($product['username']) ?></span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-name">Vues:</span>
                            <span class="spec-value"><?= htmlspecialchars($product['views']) ?></span>
                        </div>
                    </div>
                </div>

                <div id="reviews" class="tab-pane">
                    <h2>Avis</h2>
                    <p class="no-reviews">Les avis ne sont pas disponibles pour les annonces 2ndHand.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Similar Products Section -->
<section class="section similar-products">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Annonces similaires</h2>
            <p class="section-subtitle">Découvrez d'autres annonces qui pourraient vous intéresser.</p>
        </div>

        <div class="product-grid">
            <?php foreach ($similar_products as $similar): ?>
                <div class="product-card">
                    <div class="product-image">
                        <?php
                        $similar_images = explode(',', $similar['images']);
                        $similar_first_image = !empty($similar_images[0]) ? htmlspecialchars($similar_images[0]) : 'assets/images/placeholder.jpg';
                        ?>
                        <img src="<?= $similar_first_image ?>" alt="<?= htmlspecialchars($similar['title']) ?>">
                        <div class="product-actions">
                            <a href="2ndhand-detail.php?id=<?= $similar['id'] ?>" class="action-btn view-btn" title="Voir l'annonce">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>

                    <div class="product-info">
                        <?php if ($similar['brand_id']): ?>
                            <div class="product-brand"><?= htmlspecialchars($similar['brand_name']) ?></div>
                        <?php endif; ?>
                        <h3 class="product-title">
                            <a href="2ndhand-detail.php?id=<?= $similar['id'] ?>"><?= htmlspecialchars($similar['title']) ?></a>
                        </h3>
                        <div class="product-price">
                            <span class="current-price"><?= number_format($similar['price'], 2) ?> €</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des thumbnails
    const thumbnails = document.querySelectorAll('.thumbnail');
    const mainImage = document.getElementById('main-image');

    if (thumbnails && mainImage) {
        thumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', function() {
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                mainImage.src = this.dataset.image;
            });
        });
    }

    // Gestion des onglets
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabPanes = document.querySelectorAll('.tab-pane');

    if (tabLinks && tabPanes) {
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                tabLinks.forEach(link => link.classList.remove('active'));
                tabPanes.forEach(pane => pane.classList.remove('active'));
                this.classList.add('active');
                document.querySelector(this.getAttribute('href')).classList.add('active');
            });
        });
    }

    // Gestion du bouton "Contacter le vendeur" avec AJAX
    const startConversationForm = document.getElementById('start-conversation-form');
    if (startConversationForm) {
        startConversationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('start-conversation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    showToast(data.message || 'Erreur lors de la création de la conversation.', true);
                }
            })
            .catch(error => {
                showToast('Erreur réseau, veuillez réessayer: ' + error.message, true);
            });
        });
    }

    // Fonction pour afficher un toast
    function showToast(message, isError = false) {
        const existingToast = document.getElementById('confirmation-toast');
        if (existingToast) existingToast.remove();

        const toast = document.createElement('div');
        toast.id = 'confirmation-toast';
        toast.innerHTML = message;
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

        setTimeout(() => toast.style.opacity = '1', 100);
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
});
</script>

<style>
.product-gallery {
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.main-image {
    position: relative;
    width: 100%;
    max-width: 500px;
    margin: 0 auto;
}
.main-image img {
    width: 100%;
    height: auto;
    border-radius: 10px;
    object-fit: cover;
}
.thumbnail-list {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding: 10px 0;
}
.thumbnail {
    flex: 0 0 auto;
    width: 80px;
    height: 80px;
    cursor: pointer;
    border: 2px solid transparent;
    border-radius: 5px;
    transition: border 0.3s;
}
.thumbnail.active {
    border-color: #007bff;
}
.thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 5px;
}
.product-info-detail {
    padding: 20px;
}
.product-meta {
    margin: 10px 0;
    color: #666;
}
.product-meta span {
    display: block;
    margin-bottom: 5px;
}
.product-price-detail {
    font-size: 24px;
    font-weight: bold;
    color: #007bff;
    margin: 15px 0;
}
.product-actions-detail {
    margin-top: 20px;
}
.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 20px;
}
</style>

<?php include 'includes/footer.php'; ?>