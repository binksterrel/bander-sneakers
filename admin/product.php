<?php
// Page de visualisation d'un produit (admin)
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

// Vérifier si un ID de produit est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: products.php");
    exit();
}

$sneaker_id = (int)$_GET['id'];

// Récupérer les détails du produit
try {
    $stmt = $db->prepare("
        SELECT s.*, b.brand_name, c.category_name
        FROM sneakers s
        LEFT JOIN brands b ON s.brand_id = b.brand_id
        LEFT JOIN categories c ON s.category_id = c.category_id
        WHERE s.sneaker_id = :sneaker_id
    ");
    $stmt->execute([':sneaker_id' => $sneaker_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header("Location: products.php");
        exit();
    }

    // Récupérer les images
    $stmt = $db->prepare("SELECT image_url, is_primary FROM sneaker_images WHERE sneaker_id = :sneaker_id");
    $stmt->execute([':sneaker_id' => $sneaker_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les tailles disponibles
    $stmt = $db->prepare("
        SELECT sz.size_value, ss.stock_quantity
        FROM sneaker_sizes ss
        JOIN sizes sz ON ss.size_id = sz.size_id
        WHERE ss.sneaker_id = :sneaker_id
    ");
    $stmt->execute([':sneaker_id' => $sneaker_id]);
    $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur PDO dans product.php : " . $e->getMessage());
    die("Une erreur est survenue. Veuillez réessayer plus tard.");
}

// Titre de la page
$page_title = htmlspecialchars($product['sneaker_name']) . " - Admin Bander-Sneakers";

// Inclure l'en-tête admin
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1><?= htmlspecialchars($product['sneaker_name']) ?></h1>
            <p>Détails du produit</p>
        </div>

        <div class="product-info">
            <div class="product-images">
                <?php if (!empty($images)): ?>
                    <?php foreach ($images as $image): ?>
                        <img src="../assets/images/sneakers/<?= htmlspecialchars($image['image_url']) ?>" alt="<?= htmlspecialchars($product['sneaker_name']) ?>" class="<?= $image['is_primary'] ? 'primary' : '' ?>">
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Aucune image disponible.</p>
                <?php endif; ?>
            </div>
            <div class="product-details-text">
                <p><strong>ID :</strong> <?= $product['sneaker_id'] ?></p>
                <p><strong>Marque :</strong> <?= htmlspecialchars($product['brand_name']) ?></p>
                <p><strong>Catégorie :</strong> <?= htmlspecialchars($product['category_name']) ?></p>
                <p><strong>Prix :</strong> 
                    <?php if ($product['discount_price']): ?>
                        <span style="text-decoration: line-through;"><?= number_format($product['price'], 2) ?> €</span>
                        <br><?= number_format($product['discount_price'], 2) ?> €
                    <?php else: ?>
                        <?= number_format($product['price'], 2) ?> €
                    <?php endif; ?>
                </p>
                <p><strong>Genre :</strong> <?= htmlspecialchars($product['gender']) ?></p>
                <p><strong>Description :</strong> <?= htmlspecialchars($product['description']) ?: 'Aucune description.' ?></p>
                <p><strong>Tailles disponibles :</strong>
                    <?php if (!empty($sizes)): ?>
                        <ul>
                            <?php foreach ($sizes as $size): ?>
                                <li><?= htmlspecialchars($size['size_value']) ?> (Stock: <?= $size['stock_quantity'] ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        Aucune taille disponible.
                    <?php endif; ?>
                </p>
                <p><strong>Stock total :</strong> <?= $product['stock_quantity'] ?></p>
                <p><strong>Date de sortie :</strong> <?= $product['release_date'] ?: 'Non spécifiée' ?></p>
                <p><strong>Nouveau produit :</strong> <?= $product['is_new_arrival'] ? 'Oui' : 'Non' ?></p>
                <p><strong>En vedette :</strong> <?= $product['is_featured'] ? 'Oui' : 'Non' ?></p>
                <p><strong>Date de création :</strong> <?= $product['created_at'] ?></p>
                <p><strong>Dernière mise à jour :</strong> <?= $product['updated_at'] ?></p>
            </div>
        </div>

        <div class="actions">
            <a href="product-edit.php?id=<?= $sneaker_id ?>" type="submit" class="btn btn-secondary">Modifier</a>
            <a href="products.php" class="btn btn-secondary">Retour à la liste</a>
        </div>
    </div>
</div>

<style>
    .product-info { 
        display: flex; 
        gap: 2rem; 
        margin-bottom: 2rem; 
    }
    .product-images img { 
        max-width: 200px; 
    }
    .product-images img.primary { 
        border: 2px solid #007bff; 
    }
    .product-details-text ul { 
        list-style: none; 
        padding: 0; 
    }
    .actions { 
        display: flex; 
        gap: 1rem; 
    }
</style>

<?php
// Inclure le pied de page admin
include 'includes/footer.php';
?>