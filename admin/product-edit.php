<?php
// Page de modification d'un produit
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
$sneaker_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

// Vérifier si un produit est spécifié
if (!$sneaker_id) {
    $_SESSION['error_message'] = "Aucun produit spécifié.";
    header("Location: products.php");
    exit();
}

// Récupérer les messages de session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Récupérer les détails du produit
try {
    $stmt = $db->prepare("
        SELECT s.*, b.brand_name, c.category_name
        FROM sneakers s
        LEFT JOIN brands b ON s.brand_id = b.brand_id
        LEFT JOIN categories c ON s.category_id = c.category_id
        WHERE s.sneaker_id = :sneaker_id
    ");
    $stmt->execute(['sneaker_id' => $sneaker_id]);
    $product = $stmt->fetch();

    if (!$product) {
        $_SESSION['error_message'] = "Produit non trouvé.";
        header("Location: products.php");
        exit();
    }
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération du produit : " . $e->getMessage();
    error_log("Erreur PDO dans product-edit (récupération produit) : " . $e->getMessage());
}

// Récupérer les tailles associées avec jointure sur sizes
try {
    $stmt = $db->prepare("
        SELECT ss.size_id, s.size_value
        FROM sneaker_sizes ss
        JOIN sizes s ON ss.size_id = s.size_id
        WHERE ss.sneaker_id = :sneaker_id
    ");
    $stmt->execute(['sneaker_id' => $sneaker_id]);
    $product_sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $product_size_ids = array_column($product_sizes, 'size_id');
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des tailles : " . $e->getMessage();
    error_log("Erreur PDO dans product-edit (récupération tailles) : " . $e->getMessage());
}

// Récupérer toutes les tailles disponibles
$all_sizes = $db->query("SELECT size_id, size_value FROM sizes WHERE size_type = 'EU' ORDER BY size_value ASC")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les images associées
$stmt = $db->prepare("SELECT image_id, image_url, is_primary FROM sneaker_images WHERE sneaker_id = :sneaker_id");
$stmt->execute(['sneaker_id' => $sneaker_id]);
$product_images = $stmt->fetchAll();

// Séparer l'image principale des images secondaires
$primary_image = null;
$secondary_images = [];
foreach ($product_images as $image) {
    if ($image['is_primary']) {
        $primary_image = $image;
    } else {
        $secondary_images[] = $image;
    }
}

// Récupérer les marques et catégories pour les listes déroulantes
$brands = getBrands();
$categories = getCategories();

// Liste des options pour le genre
$gender_options = [
    'homme' => 'Hommes',
    'femme' => 'Femmes',
    'enfant' => 'Enfant',
    'unisex' => 'Unisexe'
];

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sneaker_name = cleanInput($_POST['sneaker_name']);
    $brand_id = (int)$_POST['brand_id'];
    $category_id = (int)$_POST['category_id'];
    $price = (float)$_POST['price'];
    $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
    $stock_quantity = (int)$_POST['stock_quantity'];
    $description = cleanInput($_POST['description'] ?? '', false);
    $release_date = !empty($_POST['release_date']) ? $_POST['release_date'] : null;
    $is_new_arrival = isset($_POST['is_new_arrival']) ? 1 : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $size_ids = isset($_POST['size_ids']) ? array_map('intval', $_POST['size_ids']) : [];
    $gender = cleanInput($_POST['gender']);

    if (empty($sneaker_name) || $brand_id <= 0 || $category_id <= 0 || $price <= 0 || $stock_quantity < 0) {
        $error_message = "Veuillez remplir tous les champs obligatoires.";
    } elseif (empty($size_ids)) {
        $error_message = "Veuillez sélectionner au moins une taille.";
    } elseif (!array_key_exists($gender, $gender_options)) {
        $error_message = "Veuillez sélectionner un genre valide.";
    } else {
        try {
            // Récupérer les prix actuels pour comparaison (Correction ici)
            $stmt_current = $db->prepare("
                SELECT s.price, s.discount_price, si.image_url AS primary_image
                FROM sneakers s
                LEFT JOIN sneaker_images si ON s.sneaker_id = si.sneaker_id AND si.is_primary = 1
                WHERE s.sneaker_id = :sneaker_id
            ");
            error_log("Récupération des prix actuels pour sneaker_id: $sneaker_id");
            $stmt_current->execute(['sneaker_id' => $sneaker_id]);
            $current = $stmt_current->fetch(PDO::FETCH_ASSOC);
            $current_price = $current['price'];
            $current_discount = $current['discount_price'];

            // Mettre à jour le produit
            $stmt_update = $db->prepare("
                UPDATE sneakers
                SET sneaker_name = :sneaker_name,
                    brand_id = :brand_id,
                    category_id = :category_id,
                    price = :price,
                    discount_price = :discount_price,
                    stock_quantity = :stock_quantity,
                    description = :description,
                    release_date = :release_date,
                    is_new_arrival = :is_new_arrival,
                    is_featured = :is_featured,
                    gender = :gender
                WHERE sneaker_id = :sneaker_id
            ");
            $params = [
                'sneaker_name' => $sneaker_name,
                'brand_id' => $brand_id,
                'category_id' => $category_id,
                'price' => $price,
                'discount_price' => $discount_price,
                'stock_quantity' => $stock_quantity,
                'description' => $description,
                'release_date' => $release_date,
                'is_new_arrival' => $is_new_arrival,
                'is_featured' => $is_featured,
                'gender' => $gender,
                'sneaker_id' => $sneaker_id
            ];
            error_log("Mise à jour du produit avec params: " . print_r($params, true));
            $stmt_update->execute($params);

            // Supprimer les tailles existantes
            $stmt_delete_sizes = $db->prepare("DELETE FROM sneaker_sizes WHERE sneaker_id = :sneaker_id");
            error_log("Suppression des tailles existantes pour sneaker_id: $sneaker_id");
            $stmt_delete_sizes->execute(['sneaker_id' => $sneaker_id]);

            // Ajouter les nouvelles tailles
            $stmt_insert_size = $db->prepare("INSERT INTO sneaker_sizes (sneaker_id, size_id, stock_quantity) VALUES (:sneaker_id, :size_id, :stock_quantity)");
            foreach ($size_ids as $size_id) {
                $size_params = [
                    'sneaker_id' => $sneaker_id,
                    'size_id' => $size_id,
                    'stock_quantity' => $stock_quantity
                ];
                error_log("Insertion taille avec params: " . print_r($size_params, true));
                $stmt_insert_size->execute($size_params);
            }

            // Gestion de l'image principale
            if (!empty($_FILES['primary_image']['name']) && $_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
                if ($primary_image) {
                    $file_path = '../assets/images/sneakers/' . $primary_image['image_url'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    } else {
                        error_log("Fichier introuvable lors de la suppression : " . $file_path);
                    }
                    $stmt_delete_image = $db->prepare("DELETE FROM sneaker_images WHERE image_id = :image_id");
                    error_log("Suppression ancienne image principale image_id: " . $primary_image['image_id']);
                    $stmt_delete_image->execute(['image_id' => $primary_image['image_id']]);
                }

                $file_name = uniqid() . '_' . basename($_FILES['primary_image']['name']);
                $upload_path = '../assets/images/sneakers/' . $file_name;
                if (move_uploaded_file($_FILES['primary_image']['tmp_name'], $upload_path)) {
                    $stmt_insert_image = $db->prepare("INSERT INTO sneaker_images (sneaker_id, image_url, is_primary) VALUES (:sneaker_id, :image_url, 1)");
                    $image_params = [
                        'sneaker_id' => $sneaker_id,
                        'image_url' => $file_name
                    ];
                    error_log("Insertion nouvelle image principale avec params: " . print_r($image_params, true));
                    $stmt_insert_image->execute($image_params);
                } else {
                    throw new Exception("Erreur lors du téléversement de l'image principale.");
                }
            }

            // Gestion des nouvelles images secondaires
            if (!empty($_FILES['new_images']['name'][0])) {
                $stmt_insert_secondary = $db->prepare("INSERT INTO sneaker_images (sneaker_id, image_url, is_primary) VALUES (:sneaker_id, :image_url, 0)");
                foreach ($_FILES['new_images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['new_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_name = uniqid() . '_' . basename($_FILES['new_images']['name'][$key]);
                        $upload_path = '../assets/images/sneakers/' . $file_name;
                        if (move_uploaded_file($tmp_name, $upload_path)) {
                            $secondary_params = [
                                'sneaker_id' => $sneaker_id,
                                'image_url' => $file_name
                            ];
                            error_log("Insertion image secondaire avec params: " . print_r($secondary_params, true));
                            $stmt_insert_secondary->execute($secondary_params);
                        }
                    }
                }
            }

            // Supprimer les images secondaires sélectionnées
            if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                $stmt_select_image = $db->prepare("SELECT image_url, is_primary FROM sneaker_images WHERE image_id = :image_id AND sneaker_id = :sneaker_id");
                $stmt_delete_secondary = $db->prepare("DELETE FROM sneaker_images WHERE image_id = :image_id");
                foreach ($_POST['delete_images'] as $image_id) {
                    $stmt_select_image->execute(['image_id' => $image_id, 'sneaker_id' => $sneaker_id]);
                    $image = $stmt_select_image->fetch();
                    if ($image && !$image['is_primary']) {
                        $file_path = '../assets/images/sneakers/' . $image['image_url'];
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        } else {
                            error_log("Fichier introuvable lors de la suppression : " . $file_path);
                        }
                        error_log("Suppression image secondaire image_id: $image_id");
                        $stmt_delete_secondary->execute(['image_id' => $image_id]);
                    }
                }
            }

            // Mettre à jour la colonne primary_image dans la table sneakers
            $stmt_get_primary = $db->prepare("SELECT image_url FROM sneaker_images WHERE sneaker_id = :sneaker_id AND is_primary = 1 LIMIT 1");
            error_log("Récupération nouvelle image principale pour sneaker_id: $sneaker_id");
            $stmt_get_primary->execute(['sneaker_id' => $sneaker_id]);
            $primary_image_url = $stmt_get_primary->fetchColumn();

            $stmt_update_primary = $db->prepare("UPDATE sneakers SET primary_image = :primary_image WHERE sneaker_id = :sneaker_id");
            $primary_params = [
                'primary_image' => $primary_image_url ?: null,
                'sneaker_id' => $sneaker_id
            ];
            error_log("Mise à jour primary_image avec params: " . print_r($primary_params, true));
            $stmt_update_primary->execute($primary_params);

            // Vérifier si une image principale existe
            $stmt_check_primary = $db->prepare("SELECT COUNT(*) FROM sneaker_images WHERE sneaker_id = :sneaker_id AND is_primary = 1");
            error_log("Vérification présence image principale pour sneaker_id: $sneaker_id");
            $stmt_check_primary->execute(['sneaker_id' => $sneaker_id]);
            $primary_image_count = $stmt_check_primary->fetchColumn();
            if ($primary_image_count == 0) {
                $error_message = "Une image principale est requise. Veuillez en téléverser une.";
            } else {
                $success_message = "";
                // Gérer les changements de prix
                if ($price != $current_price || $discount_price != $current_discount) {
                    error_log("Mise à jour des prix via updateSneakerPrice pour sneaker_id: $sneaker_id");
                    updateSneakerPrice($sneaker_id, $price, $discount_price);
                    $success_message .= " Notifications envoyées aux utilisateurs ayant le produit dans leurs favoris.";
                    if ($discount_price !== null && $discount_price != $current_discount) {
                        $image = $current['primary_image'] ? '../assets/images/sneakers/' . $current['primary_image'] : 'https://via.placeholder.com/500x200/D32F2F/fff?text=' . urlencode($sneaker_name);
                        if (sendPromotionNewsletter($sneaker_id, $sneaker_name, $price, $discount_price, $image)) {
                            $success_message .= " Newsletter de promotion envoyée aux abonnés.";
                        } else {
                            $error_message .= " Échec de l'envoi de la newsletter.";
                        }
                    }
                }

                $_SESSION['success_message'] = "Produit mis à jour avec succès." . $success_message;
                header("Location: products.php");
                exit();
            }
        } catch (Exception $e) {
            $error_message = "Erreur lors de la mise à jour du produit : " . $e->getMessage();
            error_log("Erreur dans product-edit (traitement POST) : " . $e->getMessage());
        }
    }
}

// Titre de la page
$page_title = "Modifier un produit - Admin Bander-Sneakers";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Modifier un produit</h1>
            <p>Modifiez les détails du produit sélectionné.</p>
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

        <form action="product-edit.php?id=<?= $sneaker_id ?>" method="POST" enctype="multipart/form-data" class="admin-form">
            <div class="form-group">
                <label for="sneaker_name">Nom du produit *</label>
                <input type="text" name="sneaker_name" id="sneaker_name" value="<?= htmlspecialchars($product['sneaker_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="brand_id">Marque *</label>
                <select name="brand_id" id="brand_id" required>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= $brand['brand_id'] ?>" <?= $product['brand_id'] == $brand['brand_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($brand['brand_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="category_id">Catégorie *</label>
                <select name="category_id" id="category_id" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['category_id'] ?>" <?= $product['category_id'] == $category['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="gender">Genre *</label>
                <select name="gender" id="gender" required>
                    <option value="">Sélectionnez le genre</option>
                    <?php foreach ($gender_options as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $product['gender'] == $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="release_date">Date de sortie</label>
                <input type="date" name="release_date" id="release_date" value="<?= $product['release_date'] ?: '' ?>">
            </div>

            <div class="form-group">
                <label for="price">Prix (€) *</label>
                <input type="number" name="price" id="price" step="0.01" value="<?= $product['price'] ?>" required>
            </div>

            <div class="form-group">
                <label for="discount_price">Prix réduit (€)</label>
                <input type="number" name="discount_price" id="discount_price" step="0.01" value="<?= $product['discount_price'] ?: '' ?>">
            </div>

            <div class="form-group">
                <label for="stock_quantity">Quantité en stock globale *</label>
                <input type="number" name="stock_quantity" id="stock_quantity" value="<?= $product['stock_quantity'] ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="5"><?= htmlspecialchars($product['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label>Tailles disponibles</label>
                <div class="checkbox-group">
                    <?php foreach ($all_sizes as $size): ?>
                        <label>
                            <input type="checkbox" name="size_ids[]" value="<?= $size['size_id'] ?>"
                                <?= in_array($size['size_id'], $product_size_ids) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($size['size_value']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Image principale actuelle</label>
                <div class="image-list">
                    <?php if ($primary_image): ?>
                        <div class="image-item">
                            <img src="../assets/images/sneakers/<?= $primary_image['image_url'] ?>" alt="Image principale">
                        </div>
                    <?php else: ?>
                        <p>Aucune image principale définie.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="primary_image">Remplacer l'image principale (optionnel)</label>
                <input type="file" name="primary_image" id="primary_image" accept="image/*">
            </div>

            <div class="form-group">
                <label>Images secondaires actuelles</label>
                <div class="image-list">
                    <?php if (empty($secondary_images)): ?>
                        <p>Aucune image secondaire disponible.</p>
                    <?php else: ?>
                        <?php foreach ($secondary_images as $image): ?>
                            <div class="image-item">
                                <img src="../assets/images/sneakers/<?= $image['image_url'] ?>" alt="Image">
                                <label>
                                    <input type="checkbox" name="delete_images[]" value="<?= $image['image_id'] ?>">
                                    Supprimer
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="new_images">Ajouter de nouvelles images secondaires (optionnel)</label>
                <input type="file" name="new_images[]" id="new_images" multiple accept="image/*">
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_new_arrival" <?= $product['is_new_arrival'] ? 'checked' : '' ?>>
                    Nouveau produit
                </label>
                <label>
                    <input type="checkbox" name="is_featured" <?= $product['is_featured'] ? 'checked' : '' ?>>
                    Produit en vedette
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
                <a href="products.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<style>
    .admin-form {
        background: var(--white);
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: var(--box-shadow);
    }
    .checkbox-group {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .image-list {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .image-item {
        text-align: center;
    }
    .image-item img {
        max-width: 100px;
        margin-bottom: 0.5rem;
    }
</style>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>