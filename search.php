<?php
// Page de recherche
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Débogage : Vérifier si le fichier est chargé
error_log("Début de search.php");

// Vérifier que le terme de recherche est fourni
if (!isset($_GET['q']) || empty($_GET['q'])) {
    error_log("Terme de recherche manquant ou vide. Redirection vers index.php");
    header('Location: index.php');
    exit();
}

$searchTerm = cleanInput($_GET['q']);
error_log("Terme de recherche : " . $searchTerm);

// Initialiser les filtres avec le terme de recherche
$filters = [
    'search' => $searchTerm
];
error_log("Filtres : " . print_r($filters, true));

// Pagination
$items_per_page = 12;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;
error_log("Pagination - items_per_page : $items_per_page, current_page : $current_page, offset : $offset");

// Récupérer le nombre total de résultats pour la pagination
$db = getDbConnection();
if (!$db) {
    error_log("Erreur : Impossible d'obtenir la connexion à la base de données");
    die("Erreur de connexion à la base de données");
}

$sql = "SELECT COUNT(DISTINCT s.sneaker_id) as total 
        FROM sneakers s
        LEFT JOIN brands b ON s.brand_id = b.brand_id
        WHERE (s.sneaker_name LIKE ? 
               OR s.description LIKE ? 
               OR b.brand_name LIKE ?)";
$searchValue = '%' . $searchTerm . '%';
error_log("Requête SQL pour compter les résultats : " . $sql);
error_log("Valeur de recherche : " . $searchValue);

try {
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("Erreur lors de la préparation de la requête : " . print_r($db->errorInfo(), true));
        throw new PDOException("Erreur lors de la préparation de la requête");
    }

    $stmt->bindValue(1, $searchValue, PDO::PARAM_STR);
    $stmt->bindValue(2, $searchValue, PDO::PARAM_STR);
    $stmt->bindValue(3, $searchValue, PDO::PARAM_STR);
    error_log("Paramètres liés pour la requête de comptage : [1: $searchValue, 2: $searchValue, 3: $searchValue]");

    $stmt->execute();
    $result = $stmt->fetch();
    $total_items = $result['total'];
    $total_pages = ceil($total_items / $items_per_page);
    error_log("Nombre total de résultats : $total_items, Nombre total de pages : $total_pages");
} catch (PDOException $e) {
    error_log("Erreur PDO lors du comptage des résultats : " . $e->getMessage());
    die("Erreur lors du comptage des résultats : " . $e->getMessage());
}

// Récupérer les sneakers correspondant à la recherche
try {
    error_log("Appel de getSneakers avec filtres : " . print_r($filters, true) . ", limit : $items_per_page, offset : $offset");
    $sneakers = getSneakers($filters, $items_per_page, $offset);
    error_log("Nombre de sneakers récupérées : " . count($sneakers));
} catch (Exception $e) {
    error_log("Erreur lors de l'appel de getSneakers : " . $e->getMessage());
    die("Erreur lors de la récupération des sneakers : " . $e->getMessage());
}

// Titre et description de la page
$page_title = "Recherche: " . htmlspecialchars($searchTerm) . " | Bander-Sneakers";
$page_description = "Résultats de recherche pour \"" . htmlspecialchars($searchTerm) . "\". Trouvez les sneakers que vous cherchez sur Bander-Sneakers.";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">Recherche: <?= htmlspecialchars($searchTerm) ?></li>
        </ul>
    </div>
</div>

<!-- Search Results Section -->
<section class="search-section">
    <div class="container">
        <div class="search-header">
            <h1>Résultats de recherche pour "<?= htmlspecialchars($searchTerm) ?>"</h1>
            <p><?= $total_items ?> résultat(s) trouvé(s)</p>
        </div>

        <!-- Search Form -->
        <div class="search-form-wrapper">
            <form action="search.php" method="GET" class="search-form">
                <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Rechercher des sneakers...">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>

        <?php if (empty($sneakers)): ?>
            <div class="no-results">
                <p>Aucun résultat trouvé pour votre recherche.</p>
                <p>Essayez avec d'autres termes ou consultez nos suggestions ci-dessous.</p>

                <div class="search-suggestions">
                    <h3>Suggestions populaires</h3>
                    <ul class="suggestions-list">
                        <li><a href="search.php?q=Nike">Nike</a></li>
                        <li><a href="search.php?q=Adidas">Adidas</a></li>
                        <li><a href="search.php?q=Air+Jordan">Air Jordan</a></li>
                        <li><a href="search.php?q=running">Running</a></li>
                        <li><a href="search.php?q=basketball">Basketball</a></li>
                    </ul>
                </div>

                <div class="popular-categories">
                    <h3>Catégories populaires</h3>
                    <div class="category-buttons">
                        <?php
                        try {
                            $categories = getCategories();
                            error_log("Nombre de catégories récupérées : " . count($categories));
                            foreach ($categories as $category) {
                                echo '<a href="sneakers.php?category_id=' . $category['category_id'] . '" class="category-btn">' . $category['category_name'] . '</a>';
                            }
                        } catch (Exception $e) {
                            error_log("Erreur lors de la récupération des catégories : " . $e->getMessage());
                            echo '<p>Erreur lors de la récupération des catégories.</p>';
                        }
                        ?>
                    </div>
                </div>
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

                            <img src="assets/images/sneakers/<?= $sneaker['primary_image'] ?>" alt="<?= $sneaker['sneaker_name'] ?>">

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
                            <div class="product-brand"><?= $sneaker['brand_name'] ?></div>
                            <h3 class="product-title">
                                <a href="sneaker.php?id=<?= $sneaker['sneaker_id'] ?>"><?= $sneaker['sneaker_name'] ?></a>
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
                            <li><a href="search.php?q=<?= urlencode($searchTerm) ?>&page=<?= $current_page - 1 ?>"><i class="fas fa-chevron-left"></i></a></li>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);

                        if ($start_page > 1) {
                            echo '<li><a href="search.php?q=' . urlencode($searchTerm) . '&page=1">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="ellipsis">...</li>';
                            }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $active = $i == $current_page ? 'active' : '';
                            echo '<li class="' . $active . '"><a href="search.php?q=' . urlencode($searchTerm) . '&page=' . $i . '">' . $i . '</a></li>';
                        }

                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="ellipsis">...</li>';
                            }
                            echo '<li><a href="search.php?q=' . urlencode($searchTerm) . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        }
                        ?>

                        <?php if ($current_page < $total_pages): ?>
                            <li><a href="search.php?q=<?= urlencode($searchTerm) ?>&page=<?= $current_page + 1 ?>"><i class="fas fa-chevron-right"></i></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
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
error_log("Fin de search.php");
?>