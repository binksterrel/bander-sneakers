<?php
// Page d'ajout d'un produit
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

// Récupérer les marques, catégories et tailles pour les formulaires
$brands = getBrands();
$categories = getCategories();
$all_sizes = $db->query("SELECT size_id, size_value FROM sizes WHERE size_type = 'EU' ORDER BY size_value ASC")->fetchAll(PDO::FETCH_ASSOC);

// Liste des options pour le genre
$gender_options = [
    'homme' => 'Hommes',
    'femme' => 'Femmes',
    'enfant' => 'Enfant',
    'unisex' => 'Unisexe'
];

// Traitement du formulaire d'ajout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sneaker_name = cleanInput($_POST['sneaker_name'] ?? '');
    $brand_id = (int)($_POST['brand_id'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
    $gender = cleanInput($_POST['gender'] ?? '');
    $description = cleanInput($_POST['description'] ?? '', false); // false pour ne pas encoder les caractères spéciaux // Nouveau champ description
    $size_ids = isset($_POST['size_ids']) ? array_map('intval', $_POST['size_ids']) : [];

    // Validation des champs obligatoires
    if (empty($sneaker_name)) {
        $error_message = "Le nom du produit est requis.";
    } elseif ($brand_id <= 0) {
        $error_message = "Veuillez sélectionner une marque valide.";
    } elseif ($category_id <= 0) {
        $error_message = "Veuillez sélectionner une catégorie valide.";
    } elseif ($price <= 0) {
        $error_message = "Le prix doit être supérieur à 0.";
    } elseif ($stock_quantity < 0) {
        $error_message = "La quantité en stock ne peut pas être négative.";
    } elseif (empty($size_ids)) {
        $error_message = "Veuillez sélectionner au moins une taille.";
    } elseif (empty($_FILES['primary_image']['name'])) {
        $error_message = "Veuillez ajouter une image principale.";
    } elseif (!array_key_exists($gender, $gender_options)) {
        $error_message = "Veuillez sélectionner un genre valide.";
    } else {
        try {
            // Requête avec les champs obligatoires + gender + description
            $sql = "
                INSERT INTO sneakers (
                    sneaker_name, brand_id, category_id, price, stock_quantity, gender, description
                ) VALUES (
                    :sneaker_name, :brand_id, :category_id, :price, :stock_quantity, :gender, :description
                )
            ";

            error_log("Requête SQL : " . $sql);
            error_log("Paramètres : " . json_encode([
                ':sneaker_name' => $sneaker_name,
                ':brand_id' => $brand_id,
                ':category_id' => $category_id,
                ':price' => $price,
                ':stock_quantity' => $stock_quantity,
                ':gender' => $gender,
                ':description' => $description
            ]));

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':sneaker_name', $sneaker_name, PDO::PARAM_STR);
            $stmt->bindValue(':brand_id', $brand_id, PDO::PARAM_INT);
            $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
            $stmt->bindValue(':price', $price, PDO::PARAM_STR);
            $stmt->bindValue(':stock_quantity', $stock_quantity, PDO::PARAM_INT);
            $stmt->bindValue(':gender', $gender, PDO::PARAM_STR);
            $stmt->bindValue(':description', $description, PDO::PARAM_STR);
            $stmt->execute();

            // Récupérer l'ID du produit inséré
            $sneaker_id = $db->lastInsertId();

            // Ajouter les tailles sélectionnées
            $stmt = $db->prepare("
                INSERT INTO sneaker_sizes (sneaker_id, size_id, stock_quantity)
                VALUES (:sneaker_id, :size_id, :stock_quantity)
            ");
            foreach ($size_ids as $size_id) {
                $stmt->execute([
                    ':sneaker_id' => $sneaker_id,
                    ':size_id' => $size_id,
                    ':stock_quantity' => $stock_quantity
                ]);
            }

            // Gestion de l'image principale
            if ($_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
                $file_name = uniqid() . '_' . basename($_FILES['primary_image']['name']);
                $upload_path = '../assets/images/sneakers/' . $file_name;
                if (!move_uploaded_file($_FILES['primary_image']['tmp_name'], $upload_path)) {
                    throw new Exception("Erreur lors du téléversement de l'image principale.");
                }
                $stmt = $db->prepare("
                    INSERT INTO sneaker_images (sneaker_id, image_url, is_primary)
                    VALUES (:sneaker_id, :image_url, 1)
                ");
                $stmt->execute([
                    ':sneaker_id' => $sneaker_id,
                    ':image_url' => $file_name
                ]);

                // Mettre à jour primary_image
                $stmt = $db->prepare("
                    UPDATE sneakers
                    SET primary_image = :image_url
                    WHERE sneaker_id = :sneaker_id
                ");
                $stmt->execute([
                    ':sneaker_id' => $sneaker_id,
                    ':image_url' => $file_name
                ]);
            } else {
                throw new Exception("Erreur lors du téléversement de l'image principale : " . $_FILES['primary_image']['error']);
            }

            // Gestion des images secondaires (facultatif)
            if (!empty($_FILES['secondary_images']['name'][0])) {
                $secondary_images = $_FILES['secondary_images'];
                $total_files = count($secondary_images['name']);

                for ($i = 0; $i < $total_files; $i++) {
                    if ($secondary_images['error'][$i] === UPLOAD_ERR_OK) {
                        $secondary_file_name = uniqid() . '_' . basename($secondary_images['name'][$i]);
                        $secondary_upload_path = '../assets/images/sneakers/' . $secondary_file_name;

                        if (move_uploaded_file($secondary_images['tmp_name'][$i], $secondary_upload_path)) {
                            $stmt = $db->prepare("
                                INSERT INTO sneaker_images (sneaker_id, image_url, is_primary)
                                VALUES (:sneaker_id, :image_url, 0)
                            ");
                            $stmt->execute([
                                ':sneaker_id' => $sneaker_id,
                                ':image_url' => $secondary_file_name
                            ]);
                        } else {
                            error_log("Erreur lors du téléversement de l'image secondaire : " . $secondary_images['name'][$i]);
                        }
                    }
                }
            }

            $_SESSION['success_message'] = "Produit ajouté avec succès.";
            header("Location: products.php");
            exit();
        } catch (Exception $e) {
            $error_message = "Erreur lors de l'ajout du produit : " . $e->getMessage();
            error_log("Erreur dans product-add : " . $e->getMessage());
        }
    }
}

// Titre de la page
$page_title = "Ajouter un produit - Admin Bander-Sneakers";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Ajouter un produit</h1>
            <p>Ajoutez un nouveau produit à votre boutique.</p>
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

        <form action="product-add.php" method="POST" enctype="multipart/form-data" class="admin-form">
            <div class="form-group">
                <label for="sneaker_name">Nom du produit *</label>
                <input type="text" name="sneaker_name" id="sneaker_name" required>
            </div>

            <div class="form-group">
                <label for="brand_id">Marque *</label>
                <select name="brand_id" id="brand_id" required>
                    <option value="">Sélectionnez une marque</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= $brand['brand_id'] ?>"><?= htmlspecialchars($brand['brand_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="category_id">Catégorie *</label>
                <select name="category_id" id="category_id" required>
                    <option value="">Sélectionnez une catégorie</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['category_id'] ?>"><?= htmlspecialchars($category['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="gender">Genre *</label>
                <select name="gender" id="gender" required>
                    <option value="">Sélectionnez le genre</option>
                    <?php foreach ($gender_options as $value => $label): ?>
                        <option value="<?= $value ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="price">Prix (€) *</label>
                <input type="number" name="price" id="price" step="0.01" min="0.01" required>
            </div>

            <div class="form-group">
                <label for="stock_quantity">Quantité en stock globale *</label>
                <input type="number" name="stock_quantity" id="stock_quantity" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="5" placeholder="Entrez une description du produit..."></textarea>
            </div>

            <div class="form-group">
                <label>Tailles disponibles *</label>
                <div class="checkbox-group">
                    <?php foreach ($all_sizes as $size): ?>
                        <label>
                            <input type="checkbox" name="size_ids[]" value="<?= $size['size_id'] ?>">
                            <?= htmlspecialchars($size['size_value']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="primary_image">Image principale * (sera affichée en priorité)</label>
                <input type="file" name="primary_image" id="primary_image" accept="image/*" required>
            </div>

            <div class="form-group">
                <label for="secondary_images">Images secondaires (facultatif, plusieurs fichiers possibles)</label>
                <input type="file" name="secondary_images[]" id="secondary_images" accept="image/*" multiple>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Ajouter le produit</button>
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
    textarea {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        resize: vertical;
    }
</style>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>