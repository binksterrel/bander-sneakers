<?php
// Page de gestion des produits
session_start();

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

// Inclure la configuration et les fonctions
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Initialiser les variables
$db = getDbConnection();
$success_message = '';
$error_message = '';

// Récupérer les messages de session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Mettre à jour un prix si demandé via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_price'])) {
    $sneaker_id = (int)$_POST['sneaker_id'];
    $new_price = (float)$_POST['price'];
    $new_discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;

    error_log("Début mise à jour prix - sneaker_id: $sneaker_id, new_price: $new_price, new_discount_price: " . ($new_discount_price ?? 'NULL'));

    try {
        $sql = "
            SELECT s.sneaker_name, s.price, s.discount_price, si.image_url AS primary_image
            FROM sneakers s
            LEFT JOIN sneaker_images si ON si.sneaker_id = s.sneaker_id AND si.is_primary = 1
            WHERE s.sneaker_id = :sneaker_id
        ";
        $stmt = $db->prepare($sql);
        error_log("Requête SELECT préparée : $sql");
        $params = [':sneaker_id' => $sneaker_id];
        $stmt->execute($params);
        error_log("Requête SELECT exécutée avec sneaker_id: $sneaker_id");
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_discount = $current['discount_price'];
        error_log("Données actuelles : " . print_r($current, true));

        updateSneakerPrice($sneaker_id, $new_price, $new_discount_price);
        $success_message = "Prix mis à jour avec succès.";

        if ($new_discount_price !== null && $new_discount_price != $current_discount) {
            $image = $current['primary_image'] ? '../assets/images/sneakers/' . $current['primary_image'] : 'https://via.placeholder.com/500x200/D32F2F/fff?text=' . urlencode($current['sneaker_name']);
            if (sendPromotionNewsletter($sneaker_id, $current['sneaker_name'], $new_price, $new_discount_price, $image)) {
                $success_message .= " Newsletter de promotion envoyée aux abonnés.";
            } else {
                $error_message .= " Échec de l'envoi de la newsletter.";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la mise à jour du prix : " . $e->getMessage();
        error_log("Erreur PDO dans products : " . $e->getMessage());
    }
}

// Supprimer un produit si demandé
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $sneaker_id = (int)$_GET['delete'];
    try {
        $stmt = $db->prepare("SELECT image_url FROM sneaker_images WHERE sneaker_id = :sneaker_id");
        $stmt->execute([':sneaker_id' => $sneaker_id]);
        $images = $stmt->fetchAll();
        foreach ($images as $image) {
            $file_path = '../assets/images/sneakers/' . $image['image_url'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        $stmt = $db->prepare("DELETE FROM sneakers WHERE sneaker_id = :sneaker_id");
        $stmt->execute([':sneaker_id' => $sneaker_id]);
        $success_message = "Produit supprimé avec succès.";
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression du produit : " . $e->getMessage();
        error_log("Erreur PDO dans products : " . $e->getMessage());
    }
}

// Pagination
$items_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Filtres
$filters = [];
if (isset($_GET['brand_id']) && is_numeric($_GET['brand_id'])) {
    $filters['brand_id'] = (int)$_GET['brand_id'];
}
if (isset($_GET['category_id']) && is_numeric($_GET['category_id'])) {
    $filters['category_id'] = (int)$_GET['category_id'];
}
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Récupérer le nombre total de produits pour la pagination
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
if (isset($filters['search'])) {
    $sql .= " AND s.sneaker_name LIKE :search";
    $params[':search'] = '%' . $filters['search'] . '%';
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Récupérer les produits avec filtres et pagination
$sql = "
    SELECT s.sneaker_id, s.sneaker_name, s.price, s.discount_price, s.stock_quantity, 
           b.brand_name, c.category_name, 
           (SELECT image_url FROM sneaker_images si WHERE si.sneaker_id = s.sneaker_id AND si.is_primary = 1 LIMIT 1) as primary_image
    FROM sneakers s
    LEFT JOIN brands b ON s.brand_id = b.brand_id
    LEFT JOIN categories c ON s.category_id = c.category_id
    WHERE 1=1
";
$params = [];
if (isset($filters['brand_id'])) {
    $sql .= " AND s.brand_id = :brand_id";
    $params[':brand_id'] = $filters['brand_id'];
}
if (isset($filters['category_id'])) {
    $sql .= " AND s.category_id = :category_id";
    $params[':category_id'] = $filters['category_id'];
}
if (isset($filters['search'])) {
    $sql .= " AND s.sneaker_name LIKE :search";
    $params[':search'] = '%' . $filters['search'] . '%';
}
$sql .= " ORDER BY s.sneaker_id DESC LIMIT :offset, :items_per_page";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':items_per_page', $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Récupérer les marques et catégories pour les filtres
$brands = getBrands();
$categories = getCategories();

// Titre de la page
$page_title = "Gestion des produits - Admin Bander-Sneakers";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Gestion des produits</h1>
            <p>Gérez les produits de votre boutique.</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?= $success_message ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <div class="admin-filters">
            <form action="products.php" method="GET" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="brand_id">Marque</label>
                        <select id="brand_id" name="brand_id">
                            <option value="">Toutes les marques</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= $brand['brand_id'] ?>" <?= isset($filters['brand_id']) && $filters['brand_id'] == $brand['brand_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($brand['brand_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="category_id">Catégorie</label>
                        <select id="category_id" name="category_id">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['category_id'] ?>" <?= isset($filters['category_id']) && $filters['category_id'] == $category['category_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="search">Recherche</label>
                        <input type="text" id="search" name="search" value="<?= isset($filters['search']) ? htmlspecialchars($filters['search']) : '' ?>" placeholder="Nom du produit">
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <a href="products.php" class="btn btn-secondary">Réinitialiser</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="add-product-btn">
            <a href="product-add.php" class="btn btn-secondary">Ajouter un produit</a>
        </div>

        <div class="admin-table-container">
            <?php if (empty($products)): ?>
                <div class="no-results">
                    <p>Aucun produit trouvé.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Nom</th>
                            <th>Marque</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Stock par taille</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= $product['sneaker_id'] ?></td>
                                <td>
                                    <?php if ($product['primary_image']): ?>
                                        <img src="../assets/images/sneakers/<?= htmlspecialchars($product['primary_image']) ?>" alt="<?= htmlspecialchars($product['sneaker_name']) ?>" style="max-width: 50px;">
                                    <?php else: ?>
                                        <span>Aucune image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($product['sneaker_name']) ?></td>
                                <td><?= htmlspecialchars($product['brand_name']) ?></td>
                                <td><?= htmlspecialchars($product['category_name']) ?></td>
                                <td>
                                    <form method="POST" action="products.php" style="display: inline;">
                                        <input type="hidden" name="sneaker_id" value="<?= $product['sneaker_id'] ?>">
                                        <input type="number" name="price" value="<?= number_format($product['price'], 2) ?>" step="0.01" style="width: 80px;" required>
                                        <input type="number" name="discount_price" value="<?= $product['discount_price'] ? number_format($product['discount_price'], 2) : '' ?>" step="0.01" style="width: 80px;" placeholder="Promo">
                                        <button type="submit" name="update_price" class="btn-action" title="Mettre à jour le prix">
                                            <i class="fas fa-save"></i>
                                        </button>
                                    </form>
                                </td>
                                <td><?= $product['stock_quantity'] ?></td>
                                <td class="actions-cell">
                                    <a href="product.php?id=<?= $product['sneaker_id'] ?>" class="btn-action" title="Voir">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="product-edit.php?id=<?= $product['sneaker_id'] ?>" class="btn-action" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="products.php?delete=<?= $product['sneaker_id'] ?>" class="btn-action delete-btn" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <ul>
                            <?php if ($current_page > 1): ?>
                                <li><a href="<?= updateQueryString(['page' => $current_page - 1]) ?>"><i class="fas fa-chevron-left"></i></a></li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            if ($start_page > 1) {
                                echo '<li><a href="' . updateQueryString(['page' => 1]) . '">1</a></li>';
                                if ($start_page > 2) echo '<li class="ellipsis">...</li>';
                            }

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active = $i == $current_page ? 'active' : '';
                                echo '<li class="' . $active . '"><a href="' . updateQueryString(['page' => $i]) . '">' . $i . '</a></li>';
                            }

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) echo '<li class="ellipsis">...</li>';
                                echo '<li><a href="' . updateQueryString(['page' => $total_pages]) . '">' . $total_pages . '</a></li>';
                            }
                            ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li><a href="<?= updateQueryString(['page' => $current_page + 1]) ?>"><i class="fas fa-chevron-right"></i></a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
/**
 * Met à jour les paramètres de l'URL.
 *
 * @param array $params Les paramètres à mettre à jour
 * @return string L'URL mise à jour
 */
function updateQueryString($params) {
    $query = $_GET;
    foreach ($params as $key => $value) {
        $query[$key] = $value;
    }
    return 'products.php?' . http_build_query($query);
}
?>

<style>
    .admin-filters {
        margin-bottom: 2rem;
    }
    .filter-form {
        background: var(--white);
        padding: 1rem;
        border-radius: 8px;
        box-shadow: var(--box-shadow);
    }
    .filter-row {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
    }
    .filter-group {
        flex: 1;
    }
    .filter-buttons {
        display: flex;
        gap: 0.5rem;
    }
    .admin-table-container {
        background: var(--white);
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: var(--box-shadow);
    }
    .actions-cell {
        display: flex;
        gap: 0.5rem;
    }
    .btn-action {
        color: rgb(0, 0, 0);
        text-decoration: none;
    }
    .btn-action:hover {
        color: #c0392b;
    }
    .delete-btn {
        color: rgb(0, 0, 0);
    }
    .delete-btn:hover {
        color: #c0392b;
    }
    .add-product-btn {
        margin-bottom: 1.5rem;
    }
</style>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>