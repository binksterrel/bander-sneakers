<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Initialiser les filtres
$filters = [];
$page_title = "2ndHand - Produits d'occasion | Bander-Sneakers";
$page_description = "Découvrez des sneakers d'occasion publiés par la communauté. Filtrez par marque, catégorie, état, prix et plus encore.";

// Filtres par marque
if (isset($_GET['brand_id']) && !empty($_GET['brand_id']) && is_numeric($_GET['brand_id'])) {
    $filters['brand_id'] = (int)$_GET['brand_id'];
    $page_title = "2ndHand - Marque ID " . htmlspecialchars($_GET['brand_id']) . " | Bander-Sneakers";
    $page_description = "Découvrez des sneakers d'occasion pour la marque ID " . htmlspecialchars($_GET['brand_id']) . ".";
}

// Filtres par catégorie
if (isset($_GET['category_id']) && !empty($_GET['category_id']) && is_numeric($_GET['category_id'])) {
    $filters['category_id'] = (int)$_GET['category_id'];
    $page_title = "2ndHand - Catégorie ID " . htmlspecialchars($_GET['category_id']) . " | Bander-Sneakers";
    $page_description = "Découvrez des sneakers d'occasion dans la catégorie ID " . htmlspecialchars($_GET['category_id']) . ".";
}

// Filtres par état
if (isset($_GET['etat']) && !empty($_GET['etat'])) {
    $filters['etat'] = $_GET['etat'];
    $page_title = "2ndHand - État " . htmlspecialchars($_GET['etat']) . " | Bander-Sneakers";
    $page_description = "Découvrez des produits d'occasion en état " . htmlspecialchars($_GET['etat']) . ".";
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
    $page_title = "Recherche 2ndHand: " . htmlspecialchars($_GET['search']) . " | Bander-Sneakers";
    $page_description = "Résultats de recherche pour \"" . htmlspecialchars($_GET['search']) . "\" dans la section 2ndHand.";
}

// Tri
if (isset($_GET['sort']) && in_array($_GET['sort'], ['price_asc', 'price_desc', 'title_asc', 'title_desc', 'newest'])) {
    $filters['sort'] = $_GET['sort'];
}

// Pagination
$items_per_page = 12;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Connexion à la base de données
$db = getDbConnection();

// Récupérer les marques et catégories pour les filtres
$brands = $db->query("SELECT brand_id, brand_name FROM brands ORDER BY brand_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$categories = $db->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les états uniques pour les filtres
$conditions = $db->query("SELECT DISTINCT etat FROM secondhand_products WHERE etat IS NOT NULL AND statut = 'actif'")->fetchAll(PDO::FETCH_COLUMN);

// Récupérer le nombre total de produits pour la pagination
$sql = "SELECT COUNT(*) as total 
        FROM secondhand_products sp 
        JOIN users u ON sp.user_id = u.user_id 
        WHERE sp.statut = 'actif'";
$params = [];

if (isset($filters['brand_id'])) {
    $sql .= " AND sp.brand_id = :brand_id";
    $params[':brand_id'] = $filters['brand_id'];
}

if (isset($filters['category_id'])) {
    $sql .= " AND sp.category_id = :category_id";
    $params[':category_id'] = $filters['category_id'];
}

if (isset($filters['etat'])) {
    $sql .= " AND sp.etat = :etat";
    $params[':etat'] = $filters['etat'];
}

if (isset($filters['search'])) {
    $sql .= " AND (sp.title LIKE :search OR sp.description LIKE :search)";
    $params[':search'] = '%' . $filters['search'] . '%';
}

if (isset($filters['price_min'])) {
    $sql .= " AND sp.price >= :price_min";
    $params[':price_min'] = $filters['price_min'];
}

if (isset($filters['price_max'])) {
    $sql .= " AND sp.price <= :price_max";
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

// Récupérer les produits d'occasion avec les filtres
$sql = "SELECT sp.*, u.username 
        FROM secondhand_products sp 
        JOIN users u ON sp.user_id = u.user_id 
        WHERE sp.statut = 'actif'";
$params = [];

if (isset($filters['brand_id'])) {
    $sql .= " AND sp.brand_id = :brand_id";
    $params[':brand_id'] = $filters['brand_id'];
}

if (isset($filters['category_id'])) {
    $sql .= " AND sp.category_id = :category_id";
    $params[':category_id'] = $filters['category_id'];
}

if (isset($filters['etat'])) {
    $sql .= " AND sp.etat = :etat";
    $params[':etat'] = $filters['etat'];
}

if (isset($filters['search'])) {
    $sql .= " AND (sp.title LIKE :search OR sp.description LIKE :search)";
    $params[':search'] = '%' . $filters['search'] . '%';
}

if (isset($filters['price_min'])) {
    $sql .= " AND sp.price >= :price_min";
    $params[':price_min'] = $filters['price_min'];
}

if (isset($filters['price_max'])) {
    $sql .= " AND sp.price <= :price_max";
    $params[':price_max'] = $filters['price_max'];
}

// Tri
if (isset($filters['sort'])) {
    switch ($filters['sort']) {
        case 'price_asc':
            $sql .= " ORDER BY sp.price ASC";
            break;
        case 'price_desc':
            $sql .= " ORDER BY sp.price DESC";
            break;
        case 'title_asc':
            $sql .= " ORDER BY sp.title ASC";
            break;
        case 'title_desc':
            $sql .= " ORDER BY sp.title DESC";
            break;
        case 'newest':
        default:
            $sql .= " ORDER BY sp.created_at DESC";
            break;
    }
} else {
    $sql .= " ORDER BY sp.created_at DESC";
}

$sql .= " LIMIT :offset, :items_per_page";
$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':items_per_page', $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">2ndHand</li>
            <?php if (isset($filters['brand_id'])): ?>
                <li class="active">Marque ID <?= htmlspecialchars($filters['brand_id']) ?></li>
            <?php endif; ?>
            <?php if (isset($filters['category_id'])): ?>
                <li class="active">Catégorie ID <?= htmlspecialchars($filters['category_id']) ?></li>
            <?php endif; ?>
            <?php if (isset($filters['etat'])): ?>
                <li class="active"><?= htmlspecialchars($filters['etat']) ?></li>
            <?php endif; ?>
            <?php if (isset($filters['search'])): ?>
                <li class="active">Recherche: <?= htmlspecialchars($filters['search']) ?></li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<!-- Category Banner -->
<section class="secondh-hero">
    <div class="container">
        <div class="category-banner-content">
            <h1 class="secondh-hero-title">2ndHand - Produits d'occasion</h1>
            <p class="secondh-hero-subtitle">Achetez et vendez des sneakers d'occasion. Trouvez des pépites uniques partagées par la communauté Bander-Sneakers.</p>
        </div>
    </div>
</section>

<!-- Call to Action Section for Posting an Ad -->
<section class="post-ad-section">
    <div class="container">
        <div class="post-ad-content">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="2ndhand-post.php" class="btn btn-primary btn-post-ad">
                    <i class="fas fa-plus-circle"></i> Publier une annonce
                </a>
            <?php else: ?>
                <p class="post-ad-message">Vous devez être connecté pour publier une annonce. <a href="login.php">Se connecter</a></p>
            <?php endif; ?>
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
                    <form action="2ndhand.php" method="GET" class="filter-form">
                        <?php
                        // Conserver les filtres existants
                        foreach ($filters as $key => $value) {
                            if ($key != 'price_min' && $key != 'price_max' && $key != 'brand_id' && $key != 'category_id' && $key != 'etat' && $key != 'sort') {
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
                            <h4>État</h4>
                            <ul class="filter-list">
                                <?php foreach ($conditions as $condition): ?>
                                    <li>
                                        <label>
                                            <input
                                                type="radio"
                                                name="etat"
                                                value="<?= htmlspecialchars($condition) ?>"
                                                <?= (isset($filters['etat']) && $filters['etat'] == $condition) ? 'checked' : '' ?>
                                            >
                                            <?= htmlspecialchars($condition) ?>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (isset($filters['etat'])): ?>
                                    <li>
                                        <a href="<?= removeParamFromUrl('etat') ?>" class="clear-filter">Effacer la sélection</a>
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
                                <a href="2ndhand.php" class="clear-all-filters">Effacer tous les filtres</a>
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
                            <option value="<?= updateParamInUrl('sort', 'title_asc') ?>" <?= (isset($filters['sort']) && $filters['sort'] == 'title_asc') ? 'selected' : '' ?>>Titre (A-Z)</option>
                            <option value="<?= updateParamInUrl('sort', 'title_desc') ?>" <?= (isset($filters['sort']) && $filters['sort'] == 'title_desc') ? 'selected' : '' ?>>Titre (Z-A)</option>
                        </select>
                    </div>
                    <div class="shop-results">
                        <strong><?= $total_items ?></strong> produits trouvés
                    </div>
                </div>

                <?php if (empty($products)): ?>
                    <div class="no-products">
                        <i class="fas fa-search"></i>
                        <h2>Aucune annonce trouvée</h2>
                        <p>Aucune annonce ne correspond à vos critères de recherche.</p>
                        <a href="2ndhand.php" class="btn btn-primary">Voir toutes les annonces</a>
                    </div>
                <?php else: ?>
                    <div class="product-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <?php
                                    $images = explode(',', $product['images']);
                                    $first_image = !empty($images[0]) ? $images[0] : 'assets/images/placeholder.jpg';
                                    ?>
                                    <img src="<?php echo htmlspecialchars($first_image); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>">
                                    <span class="product-badge"><?php echo htmlspecialchars($product['etat']); ?></span>
                                    <div class="product-actions">
                                        <a href="2ndhand-detail.php?id=<?= $product['id'] ?>" class="action-btn view-btn" title="Voir l'annonce">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="product-info">
                                    <span class="product-brand">Vendu par <?php echo htmlspecialchars($product['username']); ?></span>
                                    <h3 class="product-title">
                                        <a href="2ndhand-detail.php?id=<?= $product['id'] ?>"><?= htmlspecialchars($product['title']) ?></a>
                                    </h3>
                                    <div class="product-price">
                                        <span class="current-price"><?= number_format($product['price'], 2) ?> €</span>
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
    .post-ad-section {
        padding: 20px 0;
        background-color: #f8f9fa;
        text-align: center;
    }
    .post-ad-content {
        max-width: 600px;
        margin: 0 auto;
    }
    .btn-post-ad {
        font-size: 1.2rem;
        padding: 12px 24px;
        background-color: #28a745;
        border-color: #28a745;
        transition: background-color 0.3s, transform 0.1s;
    }
    .btn-post-ad:hover {
        background-color: #218838;
        transform: scale(1.05);
    }
    .btn-post-ad i {
        margin-right: 8px;
    }
    .post-ad-message {
        font-size: 1.1rem;
        color: #555;
    }
    .post-ad-message a {
        color: #ff3e3e;
        text-decoration: underline;
    }
    .post-ad-message a:hover {
        color: #ff3e3e;
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

    return '2ndhand.php?' . http_build_query($params);
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

    return '2ndhand.php?' . http_build_query($params);
}

// Inclure le pied de page
include 'includes/footer.php';
?>