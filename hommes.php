<?php
// Page des sneakers pour homme
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Filtres
$filters = ['gender' => 'homme'];

// Appliquer les filtres supplémentaires de l'URL
if (isset($_GET['brand_id']) && is_numeric($_GET['brand_id'])) {
    $filters['brand_id'] = (int)$_GET['brand_id'];
}

if (isset($_GET['category_id']) && is_numeric($_GET['category_id'])) {
    $filters['category_id'] = (int)$_GET['category_id'];
}

if (isset($_GET['price_min']) && is_numeric($_GET['price_min'])) {
    $filters['price_min'] = (float)$_GET['price_min'];
}

if (isset($_GET['price_max']) && is_numeric($_GET['price_max'])) {
    $filters['price_max'] = (float)$_GET['price_max'];
}

if (isset($_GET['sort'])) {
    $filters['sort'] = $_GET['sort'];
}

// Pagination
$items_per_page = 12;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Récupérer le nombre total de sneakers pour la pagination
$db = getDbConnection();

$sql = "SELECT COUNT(*) as total FROM sneakers s WHERE gender = 'homme' OR gender = 'unisex'";
$params = [];

if (isset($filters['brand_id'])) {
    $sql .= " AND s.brand_id = :brand_id";
    $params[':brand_id'] = $filters['brand_id'];
}

if (isset($filters['category_id'])) {
    $sql .= " AND s.category_id = :category_id";
    $params[':category_id'] = $filters['category_id'];
}

if (isset($filters['price_min'])) {
    $sql .= " AND ((s.discount_price IS NOT NULL AND s.discount_price >= :price_min) OR (s.discount_price IS NULL AND s.price >= :price_min))";
    $params[':price_min'] = $filters['price_min'];
}

if (isset($filters['price_max'])) {
    $sql .= " AND ((s.discount_price IS NOT NULL AND s.discount_price <= :price_max) OR (s.discount_price IS NULL AND s.price <= :price_max))";
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

// Récupérer les sneakers
$sneakers = getSneakers($filters, $items_per_page, $offset);

// Récupérer les marques et catégories pour les filtres
$brands = getBrands();
$categories = getCategories();

// Titre et description de la page
$page_title = "Sneakers pour Homme - Bander-Sneakers";
$page_description = "Découvrez notre collection de sneakers pour homme. Des modèles iconiques aux dernières tendances, trouvez la paire parfaite pour votre style.";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">Hommes</li>
        </ul>
    </div>
</div>

<!-- Category Banner -->
<section class="category-banner">
    <div class="container">
        <div class="category-banner-content">
            <h1 class="category-title">Sneakers Hommes</h1>
            <p class="category-description">Découvrez notre collection de sneakers pour homme. Des classiques intemporels aux nouveautés tendance, trouvez la paire idéale pour affirmer votre style.</p>
        </div>
    </div>
</section>

<!-- Product Listing Section -->
<section class="product-listing">
    <div class="container">
        <div class="product-grid-container">
            <!-- Sidebar Filters -->
            <div class="shop-sidebar">
                <div class="filter-widget">
                    <h3>Filtres</h3>
                    <form action="hommes.php" method="GET" class="filter-form">
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
                    </form>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="product-grid-section">
                <!-- Sorting and Filter Toggle -->
                <div class="listing-header">
                    <div class="filter-toggle">
                        <button class="btn-filter-toggle">
                            <i class="fas fa-filter"></i> Filtres
                        </button>
                    </div>
                    <div class="sorting">
                        <label for="sort-by">Trier par:</label>
                        <select name="sort" id="sort" onchange="window.location.href=this.value">
                            <option value="hommes.php?<?= updateQueryString(['sort' => 'newest']) ?>" <?= (isset($filters['sort']) && $filters['sort'] == 'newest') ? 'selected' : '' ?>>Les plus récents</option>
                            <option value="hommes.php?<?= updateQueryString(['sort' => 'price_asc']) ?>" <?= (isset($filters['sort']) && $filters['sort'] == 'price_asc') ? 'selected' : '' ?>>Prix croissant</option>
                            <option value="hommes.php?<?= updateQueryString(['sort' => 'price_desc']) ?>" <?= (isset($filters['sort']) && $filters['sort'] == 'price_desc') ? 'selected' : '' ?>>Prix décroissant</option>
                            <option value="hommes.php?<?= updateQueryString(['sort' => 'name_asc']) ?>" <?= (isset($filters['sort']) && $filters['sort'] == 'name_asc') ? 'selected' : '' ?>>A-Z</option>
                            <option value="hommes.php?<?= updateQueryString(['sort' => 'name_desc']) ?>" <?= (isset($filters['sort']) && $filters['sort'] == 'name_desc') ? 'selected' : '' ?>>Z-A</option>
                        </select>
                    </div>
                    <div class="shop-results">
                        <strong><?= $total_items ?></strong> produits trouvés
                    </div>
                </div>

                <!-- Product Grid -->
                <?php if (empty($sneakers)): ?>
                    <div class="no-products">
                        <i class="fas fa-search"></i>
                        <h2>Aucun produit trouvé</h2>
                        <p>Aucun produit ne correspond à vos critères de recherche.</p>
                        <a href="hommes.php" class="btn btn-primary">Voir tous les produits</a>
                    </div>
                <?php else: ?>
                    <div class="product-grid">
                        <?php foreach ($sneakers as $sneaker): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <?php if ($sneaker['discount_price']): ?>
                                        <div class="product-badge sale">-<?= calculateDiscount($sneaker['price'], $sneaker['discount_price']) ?>%</div>
                                    <?php endif; ?>

                                    <?php if ($sneaker['is_new_arrival']): ?>
                                        <div class="product-badge new">Nouveau</div>
                                    <?php endif; ?>

                                    <a href="sneaker.php?id=<?= $sneaker['sneaker_id'] ?>">
                                        <img src="assets/images/sneakers/<?= $sneaker['primary_image'] ?>" alt="<?= htmlspecialchars($sneaker['sneaker_name']) ?>">
                                    </a>

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

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <ul>
                                <?php if ($current_page > 1): ?>
                                    <li>
                                        <a href="hommes.php?<?= updateQueryString(['page' => $current_page - 1]) ?>" aria-label="Page précédente">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);

                                if ($start_page > 1) {
                                    echo '<li><a href="hommes.php?' . updateQueryString(['page' => 1]) . '">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="ellipsis">...</li>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    $active = $i == $current_page ? 'active' : '';
                                    echo '<li class="' . $active . '"><a href="hommes.php?' . updateQueryString(['page' => $i]) . '">' . $i . '</a></li>';
                                }

                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="ellipsis">...</li>';
                                    }
                                    echo '<li><a href="hommes.php?' . updateQueryString(['page' => $total_pages]) . '">' . $total_pages . '</a></li>';
                                }
                                ?>

                                <?php if ($current_page < $total_pages): ?>
                                    <li>
                                        <a href="hommes.php?<?= updateQueryString(['page' => $current_page + 1]) ?>" aria-label="Page suivante">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
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
    .listing-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .filter-toggle {
        flex: 0 0 auto;
    }
    .sorting {
        flex: 0 0 auto;
    }
    .result-count {
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
/**
 * Met à jour les paramètres de l'URL.
 *
 * @param array $params Les paramètres à mettre à jour
 * @return string Les paramètres de l'URL mis à jour
 */
function updateQueryString($params) {
    $query = $_GET;

    foreach ($params as $key => $value) {
        $query[$key] = $value;
    }

    return http_build_query($query);
}

/**
 * Supprime un ou plusieurs paramètres de l'URL.
 *
 * @param string|array $param Le(s) paramètre(s) à supprimer
 * @return string L'URL mise à jour
 */
function removeParamFromUrl($param) {
    $query = $_GET;
    if (is_array($param)) {
        foreach ($param as $p) {
            unset($query[$p]);
        }
    } else {
        unset($query[$param]);
    }
    return 'hommes.php' . ($query ? '?' . http_build_query($query) : '');
}

// Inclure le pied de page
include 'includes/footer.php';
?>