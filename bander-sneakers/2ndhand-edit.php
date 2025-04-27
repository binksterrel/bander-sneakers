<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et si l'ID du produit est valide
if (!isLoggedIn() || !isset($_GET['id']) || !is_numeric($_GET['id']) || $_GET['id'] <= 0) {
    $_SESSION['error_message'] = "ID de produit invalide ou accès non autorisé.";
    header('Location: compte.php#secondhand');
    exit;
}

$product_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];
$errors = [];

try {
    // Connexion à la base de données
    $db = getDbConnection();

    // Récupérer les catégories et marques
    $categories = $db->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $brands = $db->query("SELECT brand_id, brand_name FROM brands ORDER BY brand_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Vérifier que l'annonce appartient à l'utilisateur et n'est pas supprimée
    $query = "SELECT * FROM secondhand_products WHERE id = :id AND user_id = :user_id AND statut != 'supprimé'";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $product_id, ':user_id' => $user_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $_SESSION['error_message'] = "Annonce non trouvée ou accès non autorisé.";
        header('Location: compte.php#secondhand');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Récupérer et nettoyer les données du formulaire
        $title = cleanInput($_POST['title'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $etat = $_POST['etat'] ?? '';
        $category_id = isset($_POST['category_id']) && is_numeric($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $brand_id = isset($_POST['brand_id']) && is_numeric($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $size = cleanInput($_POST['size'] ?? '');
        $location = cleanInput($_POST['location'] ?? '');
        $shipping_method = cleanInput($_POST['shipping_method'] ?? '');
        $statut = $_POST['statut'] ?? $product['statut'];

        // Validation des champs obligatoires
        if (empty($title)) $errors[] = "Le titre de l'annonce est requis.";
        if (empty($description)) $errors[] = "La description est requise.";
        if ($price <= 0) $errors[] = "Le prix doit être un nombre positif.";
        if (!in_array($etat, ['neuf', 'très bon', 'bon', 'moyen', 'usagé'])) $errors[] = "L'état sélectionné est invalide.";
        if (empty($category_id)) $errors[] = "La catégorie est requise.";
        if (empty($size)) $errors[] = "La taille est requise.";
        if (!in_array($statut, ['actif', 'vendu', 'en attente'])) $errors[] = "Le statut sélectionné est invalide.";

        // Gestion des images existantes (suppression)
        $images = !empty($product['images']) ? explode(',', $product['images']) : [];
        $images_to_delete = $_POST['delete_images'] ?? []; // Images à supprimer

        if (!empty($images_to_delete)) {
            foreach ($images_to_delete as $image_to_delete) {
                $index = array_search($image_to_delete, $images);
                if ($index !== false) {
                    unset($images[$index]); // Retirer l'image du tableau
                    // Supprimer physiquement le fichier si souhaité
                    if (file_exists($image_to_delete)) {
                        unlink($image_to_delete);
                    }
                }
            }
            $images = array_values($images); // Réindexer le tableau
        }

        // Gestion des nouvelles images
        $max_images = 5;
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        if (!empty($_FILES['images']['name'][0])) {
            $total_files = count(array_filter($_FILES['images']['name']));
            if (count($images) + $total_files > $max_images) {
                $errors[] = "Le total des images (actuelles + nouvelles) ne peut dépasser $max_images.";
            } else {
                $upload_dir = 'uploads/secondhand/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $new_images = [];
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_name = uniqid() . '_' . basename($_FILES['images']['name'][$key]);
                        $file_path = $upload_dir . $file_name;
                        $file_type = $_FILES['images']['type'][$key];
                        $file_size = $_FILES['images']['size'][$key];

                        if (!in_array($file_type, $allowed_types)) {
                            $errors[] = "Le fichier " . htmlspecialchars($_FILES['images']['name'][$key]) . " n'est pas un type d'image autorisé (JPG, PNG, GIF).";
                            continue;
                        }
                        if ($file_size > $max_file_size) {
                            $errors[] = "Le fichier " . htmlspecialchars($_FILES['images']['name'][$key]) . " dépasse la taille maximale de 5MB.";
                            continue;
                        }

                        if (move_uploaded_file($tmp_name, $file_path)) {
                            $new_images[] = $file_path;
                        } else {
                            $errors[] = "Erreur lors de l'upload de l'image " . htmlspecialchars($_FILES['images']['name'][$key]) . ".";
                        }
                    }
                }

                if (!empty($new_images)) {
                    $images = array_merge($images, $new_images); // Ajouter les nouvelles images
                }
            }
        }
        $images_str = implode(',', $images);

        // Mise à jour si aucune erreur
        if (empty($errors)) {
            $query = "UPDATE secondhand_products 
                      SET title = :title, description = :description, price = :price, 
                          etat = :etat, category_id = :category_id, brand_id = :brand_id, 
                          size = :size, images = :images, location = :location, 
                          shipping_method = :shipping_method, statut = :statut, updated_at = NOW()
                      WHERE id = :id AND user_id = :user_id";
            $stmt = $db->prepare($query);
            $success = $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':price' => $price,
                ':etat' => $etat,
                ':category_id' => $category_id,
                ':brand_id' => $brand_id ?: null,
                ':size' => $size,
                ':images' => $images_str,
                ':location' => $location ?: null,
                ':shipping_method' => $shipping_method ?: null,
                ':statut' => $statut,
                ':id' => $product_id,
                ':user_id' => $user_id
            ]);

            if ($success) {
                error_log("Annonce mise à jour avec succès - ID: $product_id, Statut: $statut, User: $user_id");
                $_SESSION['success_message'] = "Annonce mise à jour avec succès !";
                header('Location: compte.php#secondhand');
                exit;
            } else {
                $errors[] = "Erreur lors de la mise à jour de l'annonce.";
            }
        }
    }
} catch (PDOException $e) {
    error_log("Erreur PDO dans 2ndhand-edit.php : " . $e->getMessage());
    $errors[] = "Une erreur est survenue. Veuillez réessayer plus tard.";
}

// Préparer les données pour le formulaire
$page_title = "Modifier une annonce - 2ndHand | Bander-Sneakers";
$page_description = "Modifiez votre annonce sur la plateforme 2ndHand de Bander-Sneakers.";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description) ?>"> 
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .image-preview, .current-images {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .image-preview img, .current-images img {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .current-images .image-container {
            position: relative;
            display: inline-block;
        }
        .current-images .delete-checkbox {
            position: absolute;
            top: 5px;
            right: 5px;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .form-group label.required:after {
            content: " *";
            color: #dc3545;
        }
        .form-hint {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <div class="container">
            <ul class="breadcrumb-list">
                <li><a href="index.php">Accueil</a></li>
                <li><a href="2ndhand.php">2ndHand</a></li>
                <li><a href="compte.php#secondhand">Mes annonces</a></li>
                <li class="active">Modifier une annonce</li>
            </ul>
        </div>
    </div>

    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-form-container">
                    <h2 class="auth-title">Modifier une annonce</h2>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <?php foreach ($errors as $error): ?>
                                <p><?= htmlspecialchars($error) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form action="2ndhand-edit.php?id=<?= $product_id ?>" method="POST" enctype="multipart/form-data" class="auth-form" onsubmit="return confirm('Êtes-vous sûr de vouloir mettre à jour cette annonce ?');">
                        <div class="form-group">
                            <label for="title" class="required">Titre de l'annonce</label>
                            <input type="text" id="title" name="title" value="<?= htmlspecialchars($product['title']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="description" class="required">Description</label>
                            <textarea id="description" name="description" rows="5" required><?= htmlspecialchars($product['description']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="price" class="required">Prix (€)</label>
                            <input type="number" id="price" name="price" step="0.01" min="0" value="<?= htmlspecialchars($product['price']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="etat" class="required">État</label>
                            <select id="etat" name="etat" required>
                                <option value="neuf" <?= $product['etat'] === 'neuf' ? 'selected' : '' ?>>Neuf</option>
                                <option value="très bon" <?= $product['etat'] === 'très bon' ? 'selected' : '' ?>>Très bon</option>
                                <option value="bon" <?= $product['etat'] === 'bon' ? 'selected' : '' ?>>Bon</option>
                                <option value="moyen" <?= $product['etat'] === 'moyen' ? 'selected' : '' ?>>Moyen</option>
                                <option value="usagé" <?= $product['etat'] === 'usagé' ? 'selected' : '' ?>>Usagé</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="category_id" class="required">Catégorie</label>
                            <select id="category_id" name="category_id" required>
                                <option value="">Sélectionner une catégorie</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['category_id'] ?>" <?= $product['category_id'] == $category['category_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="brand_id">Marque</label>
                            <select id="brand_id" name="brand_id">
                                <option value="">Sélectionner une marque (optionnel)</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?= $brand['brand_id'] ?>" <?= $product['brand_id'] == $brand['brand_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($brand['brand_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="size" class="required">Taille</label>
                            <input type="text" id="size" name="size" value="<?= htmlspecialchars($product['size']) ?>" required placeholder="Ex. 42, M, L">
                            <p class="form-hint">Indiquez la taille (par exemple, 42 pour une pointure).</p>
                        </div>
                        <div class="form-group">
                            <label for="location">Localisation</label>
                            <input type="text" id="location" name="location" value="<?= htmlspecialchars($product['location'] ?? '') ?>" placeholder="Ex. Paris, France">
                            <p class="form-hint">Indiquez où se trouve l'article (optionnel).</p>
                        </div>
                        <div class="form-group">
                            <label for="shipping_method">Méthode d'expédition</label>
                            <input type="text" id="shipping_method" name="shipping_method" value="<?= htmlspecialchars($product['shipping_method'] ?? '') ?>" placeholder="Ex. Colissimo, Remise en main propre">
                            <p class="form-hint">Indiquez les options d'expédition (optionnel).</p>
                        </div>
                        <div class="form-group">
                            <label for="statut" class="required">Statut de l'annonce</label>
                            <select id="statut" name="statut" required>
                                <option value="actif" <?= $product['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                                <option value="vendu" <?= $product['statut'] === 'vendu' ? 'selected' : '' ?>>Vendu</option>
                                <option value="en attente" <?= $product['statut'] === 'en attente' ? 'selected' : '' ?>>En attente</option>
                            </select>
                            <p class="form-hint">Sélectionnez le statut actuel de l'annonce.</p>
                        </div>
                        <div class="form-group">
                            <label>Images actuelles</label>
                            <div class="current-images">
                                <?php
                                $images = !empty($product['images']) ? explode(',', $product['images']) : [];
                                if (!empty($images)) {
                                    foreach ($images as $image) {
                                        echo '<div class="image-container">';
                                        echo '<img src="' . htmlspecialchars($image) . '" alt="Image actuelle">';
                                        echo '<input type="checkbox" class="delete-checkbox" name="delete_images[]" value="' . htmlspecialchars($image) . '" onchange="this.parentElement.classList.toggle(\'to-delete\', this.checked)">';
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<p>Aucune image actuellement.</p>';
                                }
                                ?>
                            </div>
                            <p class="form-hint">Cochez les images à supprimer.</p>
                        </div>
                        <div class="form-group">
                            <label for="images">Ajouter de nouvelles images</label>
                            <input type="file" id="images" name="images[]" multiple accept="image/*">
                            <p class="form-hint">Formats acceptés : JPG, PNG, GIF. Maximum 5 images au total, 5MB par fichier.</p>
                            <div class="image-preview" id="image-preview"></div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Mettre à jour</button>
                            <a href="compte.php#secondhand" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>

                    <div class="auth-links">
                        <p>Retourner à la liste des annonces ? <a href="compte.php#secondhand">Voir mes annonces</a></p>
                    </div>
                </div>

                <div class="auth-sidebar">
                    <div class="auth-info">
                        <h2>Conseils pour une annonce efficace</h2>
                        <ul>
                            <li><strong>Titre clair :</strong> Utilisez un titre précis (ex. "Nike Air Max 90 - Taille 42").</li>
                            <li><strong>Description détaillée :</strong> Mentionnez la taille, l'état, les défauts éventuels, et l'historique de l'article.</li>
                            <li><strong>Photos de qualité :</strong> Prenez des photos nettes sous différents angles, avec un bon éclairage.</li>
                            <li><strong>Prix réaliste :</strong> Fixez un prix en fonction de l'état et de la valeur marchande.</li>
                            <li><strong>Répondez rapidement :</strong> Soyez réactif aux messages des acheteurs potentiels.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('images');
        const preview = document.getElementById('image-preview');
        const maxFiles = 5;
        const currentImagesCount = <?= count($images) ?>;

        input.addEventListener('change', function() {
            preview.innerHTML = ''; // Réinitialiser l'aperçu
            const files = this.files;
            const checkedToDelete = document.querySelectorAll('.delete-checkbox:checked').length;
            const remainingImages = currentImagesCount - checkedToDelete;

            if (remainingImages + files.length > maxFiles) {
                alert(`Vous ne pouvez avoir que ${maxFiles} images au total (actuelles - supprimées + nouvelles).`);
                this.value = ''; // Réinitialiser l'input
                return;
            }

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (!file.type.match('image.*')) {
                    alert('Veuillez sélectionner uniquement des fichiers image (JPG, PNG, GIF).');
                    this.value = '';
                    preview.innerHTML = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert(`Le fichier ${file.name} dépasse la taille maximale de 5MB.`);
                    this.value = '';
                    preview.innerHTML = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            }
        });
    });
    </script>
</body>
</html>