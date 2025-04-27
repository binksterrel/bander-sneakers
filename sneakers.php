<?php
// Page de liste des sneakers avec filtres
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Initialiser les filtres
$filters = [];
$page_title = "Toutes les Sneakers | Bander-Sneakers";
$page_description = "Découvrez notre collection complète de sneakers. Filtrez par marque, catégorie, prix et plus encore.";

// Filtres par marque
if (isset($_GET['brand_id']) && is_numeric($_GET['brand_id'])) {
    $filters['brand_id'] = (int)$_GET['brand_id'];

    // Récupérer les informations de la marque pour le titre de la page
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("SELECT brand_name FROM brands WHERE brand_id = :brand_id");
        $stmt->bindParam(':brand_id', $filters['brand_id'], PDO::PARAM_INT);
        $stmt->execute();
        $brand = $stmt->fetch();

        if ($brand) {
            $page_title = "Sneakers " . $brand['brand_name'] . " | Bander-Sneakers";
            $page_description = "Découvrez notre collection de sneakers " . $brand['brand_name'] . ". Les derniers modèles et les classiques de la marque.";
        }
    } catch (PDOException $e) {
        // En cas d'erreur, on garde le titre par défaut
    }
}

// Filtres par catégorie
if (isset($_GET['category_id']) && is_numeric($_GET['category_id'])) {
    $filters['category_id'] = (int)$_GET['category_id'];

    // Récupérer les informations de la catégorie pour le titre de la page
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("SELECT category_name FROM categories WHERE category_id = :category_id");
        $stmt->bindParam(':category_id', $filters['category_id'], PDO::PARAM_INT);
        $stmt->execute();
        $category = $stmt->fetch();

        if ($category) {
            $page_title = "Sneakers " . $category['category_name'] . " | Bander-Sneakers";
            $page_description = "Découvrez notre collection de sneakers " . $category['category_name'] . ". Les meilleurs modèles pour votre style.";
        }
    } catch (PDOException $e) {
        // En cas d'erreur, on garde le titre par défaut
    }
}

// Filtres supplémentaires
if (isset($_GET['is_featured']) && $_GET['is_featured'] == '1') {
    $filters['is_featured'] = 1;
    $page_title = "Sneakers en Vedette | Bander-Sneakers";
    $page_description = "Découvrez notre sélection de sneakers vedettes. Les modèles les plus populaires et exclusifs.";
}

if (isset($_GET['is_new_arrival']) && $_GET['is_new_arrival'] == '1') {
    $filters['is_new_arrival'] = 1;
    $page_title = "Nouvelles Arrivées | Bander-Sneakers";
    $page_description = "Découvrez nos dernières nouveautés en matière de sneakers. Les modèles les plus récents viennent d'arriver.";
}

// Filtres de prix
if (isset($_GET['price_min']) && is_numeric($_GET['price_min'])) {
    $filters['price_min'] = (float)$_GET['price_min'];
}

if (isset($_GET['price_max']) && is_numeric($_GET['price_max'])) {
    $filters['price_max'] = (float)$_GET['price_max'];
}

// Recherche
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
    $page_title = "Recherche: " . htmlspecialchars($_GET['search']) . " | Bander-Sneakers";
    $page_description = "Résultats de recherche pour \"" . htmlspecialchars($_GET['search']) . "\". Trouvez les sneakers que vous cherchez sur Bander-Sneakers.";
}

// Tri
if (isset($_GET['sort']) && in_array($_GET['sort'], ['price_asc', 'price_desc', 'name_asc', 'name_desc', 'newest'])) {
    $filters['sort'] = $_GET['sort'];
}

// Pagination
$items_per_page = 12;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Récupérer le nombre total de sneakers pour la pagination
$db = getDbConnection();

$sql = "SELECT COUNT(*) as total FROM sneakers s WHERE 1=1";
$params = [];

if (isset($filters['brand_id'])) {
    $sql .= " AND s.brand_id = :brand_id";
    $params[':brand_id'] = $filters['brand_id'];
}

if (isset($filters['category_id'])) {
    $sql .= " AND s.category_id = :category_id";
    $params[':category_id'] = $filters['category_id'];
}

if (isset($filters['is_featured'])) {
    $sql .= " AND s.is_featured = :is_featured";
    $params[':is_featured'] = $filters['is_featured'];
}

if (isset($filters['is_new_arrival'])) {
    $sql .= " AND s.is_new_arrival = :is_new_arrival";
    $params[':is_new_arrival'] = $filters['is_new_arrival'];
}

if (isset($filters['search'])) {
    $sql .= " AND (s.sneaker_name LIKE :search OR s.description LIKE :search)";
    $params[':search'] = '%' . $filters['search'] . '%';
}

if (isset($filters['price_min'])) {
    $sql .= " AND s.price >= :price_min";
    $params[':price_min'] = $filters['price_min'];
}

if (isset($filters['price_max'])) {
    $sql .= " AND s.price <= :price_max";
    $params[':price_max'] = $filters['price_max'];
}

$stmt = $db->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$result = $stmt->fetch();
$total_items = $result['total'];
$total_pages = ceil($total_items / $items_per_page);

// Récupérer les sneakers avec les filtres
$sneakers = getSneakers($filters, $items_per_page, $offset);

// Récupérer toutes les marques et catégories pour les filtres
$brands = getBrands();
$categories = getCategories();

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">Sneakers</li>
            <?php if (isset($filters['brand_id']) && isset($brand)): ?>
                <li class="active"><?= htmlspecialchars($brand['brand_name']) ?></li>
            <?php endif; ?>
            <?php if (isset($filters['category_id']) && isset($category)): ?>
                <li class="active"><?= htmlspecialchars($category['category_name']) ?></li>
            <?php endif; ?>
            <?php if (isset($filters['is_featured'])): ?>
                <li class="active">Produits Vedettes</li>
            <?php endif; ?>
            <?php if (isset($filters['is_new_arrival'])): ?>
                <li class="active">Nouvelles Arrivées</li>
            <?php endif; ?>
            <?php if (isset($filters['search'])): ?>
                <li class="active">Recherche: <?= htmlspecialchars($filters['search']) ?></li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<!-- Category Banner -->
<section class="category-banner">
    <div class="container">
        <div class="category-banner-content">
            <h1 class="category-title">Sneakers</h1>
            <p class="category-description">Découvrez notre collection de sneakers. Des modèles emblématiques aux dernières tendances, trouvez la paire parfaite pour allier style et confort.</p>
        </div>
    </div>
</section>

<!-- Shop Section -->
<section class="shop-section">
    <div class="container">
        <div class="shop-content">
            <!-- Sidebar Filters -->
            <div class="shop-sidebar">
                <div class="filter-widget">
                    <h3>Filtres</h3>
                    <form action="sneakers.php" method="GET" class="filter-form">
                        <?php
                        // Conserver les filtres existants
                        foreach ($filters as $key => $value) {
                            if ($key != 'price_min' && $key != 'price_max' && $key != 'brand_id' && $key != 'category_id' && $key != 'sort') {
                                echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                            }
                        }
                        ?>

                        <div class="filter-group">
                            <h4>Marques</h4>
                            <ul class="filter-list">
                                <?php foreach ($brands as $brand): ?>
                                    <li>
                                        <label>
                                            <input
                                                type="radio"
                                                name="brand_id"
                                                value="<?= $brand['brand_id'] ?>"
                                                <?= (isset($filters['brand_id']) && $filters['brand_id'] == $brand['brand_id']) ? 'checked' : '' ?>
                                            >
                                            <?= htmlspecialchars($brand['brand_name']) ?>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (isset($filters['brand_id'])): ?>
                                    <li>
                                        <a href="<?= removeParamFromUrl('brand_id') ?>" class="clear-filter">Effacer la sélection</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="filter-group">
                            <h4>Catégories</h4>
                            <ul class="filter-list">
                                <?php foreach ($categories as $category): ?>
                                    <li>
                                        <label>
                                            <input
                                                type="radio"
                                                name="category_id"
                                                value="<?= $category['category_id'] ?>"
                                                <?= (isset($filters['category_id']) && $filters['category_id'] == $category['category_id']) ? 'checked' : '' ?>
                                            >
                                            <?= htmlspecialchars($category['category_name']) ?>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (isset($filters['category_id'])): ?>
                                    <li>
                                        <a href="<?= removeParamFromUrl('category_id') ?>" class="clear-filter">Effacer la sélection</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="filter-group">
                            <h4>Prix</h4>
                            <div class="price-filter">
                                <div class="price-inputs">
                                    <div class="price-input">
                                        <label>Min</label>
                                        <input
                                            type="number"
                                            name="price_min"
                                            value="<?= isset($filters['price_min']) ? htmlspecialchars($filters['price_min']) : '' ?>"
                                            placeholder="Min"
                                            min="0"
                                        >
                                    </div>
                                    <div class="price-input">
                                        <label>Max</label>
                                        <input
                                            type="number"
                                            name="price_max"
                                            value="<?= isset($filters['price_max']) ? htmlspecialchars($filters['price_max']) : '' ?>"
                                            placeholder="Max"
                                            min="0"
                                        >
                                    </div>
                                </div>
                                <button type="submit" class="btn-filter">Appliquer</button>
                                <?php if (isset($filters['price_min']) || isset($filters['price_max'])): ?>
                                    <a href="<?= removeParamFromUrl(['price_min', 'price_max']) ?>" class="clear-filter">Effacer</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (count($filters) > 0): ?>
                            <div class="filter-actions">
                                <a href="sneakers.php" class="clear-all-filters">Effacer tous les filtres</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Products Content -->
            <div class="shop-products">
                <div class="shop-header">
                    <div class="filter-toggle">
                        <button class="btn-filter-toggle">
                            <i class="fas fa-filter"></i> Filtres
                        </button>
                    </div>
                    <div class="shop-sorting">
                        <label for="sort-by">Trier par:</label>
                        <select id="sort-by" onchange="window.location.href=this.value">
                            <option value="<?= updateParamInUrl('sort', 'newest') ?>" <?= (isset($filters['sort']) && $filters['sort'] == 'newest') ? 'selected' : '' ?>>Les plus récents</option>
                            <option value="<?= updateParamInUrl('sort', 'price_asc') ?>" <?= (isset($filters['sort']) && $filters['sort'] == 'price_asc') ? 'selected' : '' ?>>Prix croissant</option>
                            <option value="<?= updateParamInUrl('sort', 'price_desc') ?>" <?= (isset($filters['sort']) && $filters['sort'] == 'price_desc') ? 'selected' : '' ?>>Prix décroissant</option>
                            <option value="<?= updateParamInUrl('sort', 'name_asc') ?>" <?= (isset($filters['sort']) && $filters['sort'] == 'name_asc') ? 'selected' : '' ?>>Nom (A-Z)</option>
                            <option value="<?= updateParamInUrl('sort', 'name_desc') ?>" <?= (isset($filters['sort']) && $filters['sort'] == 'name_desc') ? 'selected' : '' ?>>Nom (Z-A)</option>
                        </select>
                    </div>
                    <div class="shop-results">
                        <strong><?= $total_items ?></strong> produits trouvés
                    </div>
                </div>

                <?php if (empty($sneakers)): ?>
                    <div class="no-products">
                        <i class="fas fa-search"></i>
                        <h2>Aucun produit trouvé</h2>
                        <p>Aucun produit ne correspond à vos critères de recherche.</p>
                        <a href="sneakers.php" class="btn btn-primary">Voir tous les produits</a>
                    </div>
                <?php else: ?>
                    <div class="product-grid">
                        <?php foreach ($sneakers as $sneaker): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <?php if ($sneaker['is_new_arrival']): ?>
                                        <div class="product-badge new">Nouveau</div>
                                    <?php endif; ?>

                                    <?php if ($sneaker['discount_price']): ?>
                                        <div class="product-badge sale">-<?= calculateDiscount($sneaker['price'], $sneaker['discount_price']) ?>%</div>
                                    <?php endif; ?>

                                    <img src="assets/images/sneakers/<?= $sneaker['primary_image'] ?>" alt="<?= htmlspecialchars($sneaker['sneaker_name']) ?>">

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
                                        <?php if ($sneaker['discount_price']): ?>
                                            <span class="current-price"><?= formatPrice($sneaker['discount_price']) ?></span>
                                            <span class="original-price"><?= formatPrice($sneaker['price']) ?></span>
                                        <?php else: ?>
                                            <span class="current-price"><?= formatPrice($sneaker['price']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <ul>
                                <?php if ($current_page > 1): ?>
                                    <li><a href="<?= updateParamInUrl('page', $current_page - 1) ?>" aria-label="Page précédente"><i class="fas fa-chevron-left"></i></a></li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);

                                if ($start_page > 1) {
                                    echo '<li><a href="' . updateParamInUrl('page', 1) . '">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="ellipsis">...</li>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    $active = $i == $current_page ? 'active' : '';
                                    echo '<li class="' . $active . '"><a href="' . updateParamInUrl('page', $i) . '">' . $i . '</a></li>';
                                }

                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="ellipsis">...</li>';
                                    }
                                    echo '<li><a href="' . updateParamInUrl('page', $total_pages) . '">' . $total_pages . '</a></li>';
                                }
                                ?>

                                <?php if ($current_page < $total_pages): ?>
                                    <li><a href="<?= updateParamInUrl('page', $current_page + 1) ?>" aria-label="Page suivante"><i class="fas fa-chevron-right"></i></a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<style>
    .shop-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .filter-toggle {
        flex: 0 0 auto;
    }
    .shop-sorting {
        flex: 0 0 auto;
    }
    .shop-results {
        flex: 0 0 auto;
        text-align: right;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle du filtre mobile
    const filterToggle = document.querySelector('.btn-filter-toggle');
    const shopSidebar = document.querySelector('.shop-sidebar');
    const filtersCloseBtn = document.querySelector('.filters-close-btn');

    if (filterToggle && shopSidebar) {
        filterToggle.addEventListener('click', function() {
            shopSidebar.classList.add('active');
            document.body.style.overflow = 'hidden';
        });

        if (filtersCloseBtn) {
            filtersCloseBtn.addEventListener('click', function() {
                shopSidebar.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
    }

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
// Fonction pour mettre à jour un paramètre dans l'URL actuelle
function updateParamInUrl($param, $value) {
    $params = $_GET;

    if (is_array($param)) {
        foreach ($param as $key => $val) {
            $params[$key] = $val;
        }
    } else {
        $params[$param] = $value;
    }

    return 'sneakers.php?' . http_build_query($params);
}

// Fonction pour supprimer un paramètre de l'URL actuelle
function removeParamFromUrl($param) {
    $params = $_GET;

    if (is_array($param)) {
        foreach ($param as $p) {
            if (isset($params[$p])) {
                unset($params[$p]);
            }
        }
    } else {
        if (isset($params[$param])) {
            unset($params[$param]);
        }
    }

    return 'sneakers.php?' . http_build_query($params);
}

// Inclure le pied de page
include 'includes/footer.php';
?>