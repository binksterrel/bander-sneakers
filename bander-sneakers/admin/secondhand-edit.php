<?php
session_start();
require_once '../includes/config.php'; // Chemin relatif corrigé
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    $_SESSION['error_message'] = "Accès réservé aux administrateurs.";
    header('Location: ../login.php');
    exit;
}

// Vérifier si l'ID du produit est valide
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || $_GET['id'] <= 0) {
    $_SESSION['error_message'] = "ID de produit invalide.";
    header('Location: secondhand.php');
    exit;
}

$product_id = (int)$_GET['id'];
$success_message = '';
$error_message = '';

try {
    // Connexion à la base de données
    $db = getDbConnection();

    // Récupérer les catégories, marques et utilisateurs
    $categories = $db->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $brands = $db->query("SELECT brand_id, brand_name FROM brands ORDER BY brand_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $users = $db->query("SELECT user_id, username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer l'annonce sans restriction sur user_id ou statut
    $query = "SELECT * FROM secondhand_products WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $_SESSION['error_message'] = "Annonce non trouvée.";
        header('Location: secondhand.php');
        exit;
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

    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user_id = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $title = cleanInput($_POST['title'] ?? '');
        $description = cleanInput($_POST['description'] ?? '', false);
        $price = floatval($_POST['price'] ?? 0);
        $etat = $_POST['etat'] ?? '';
        $category_id = isset($_POST['category_id']) && is_numeric($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $brand_id = isset($_POST['brand_id']) && is_numeric($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $size = cleanInput($_POST['size'] ?? '');
        $location = cleanInput($_POST['location'] ?? '');
        $shipping_method = cleanInput($_POST['shipping_method'] ?? '');
        $statut = $_POST['statut'] ?? '';

        // Validation des champs obligatoires
        if (empty($user_id)) $error_message = "L'utilisateur est requis.";
        elseif (empty($title)) $error_message = "Le titre de l'annonce est requis.";
        elseif (empty($description)) $error_message = "La description est requise.";
        elseif ($price <= 0) $error_message = "Le prix doit être un nombre positif.";
        elseif (!in_array($etat, ['neuf', 'très bon', 'bon', 'moyen', 'usagé'])) $error_message = "L'état sélectionné est invalide.";
        elseif (empty($category_id)) $error_message = "La catégorie est requise.";
        elseif (empty($size)) $error_message = "La taille est requise.";
        elseif (!in_array($statut, ['actif', 'vendu', 'supprimé', 'en attente'])) $error_message = "Le statut sélectionné est invalide.";
        else {
            // Gestion des images
            $images = !empty($product['images']) ? explode(',', $product['images']) : [];
            $max_images = 5;
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_file_size = 5 * 1024 * 1024; // 5MB

            if (!empty($_FILES['images']['name'][0])) {
                $total_files = count(array_filter($_FILES['images']['name']));
                if ($total_files > $max_images) {
                    $error_message = "Vous ne pouvez uploader que $max_images images maximum.";
                } else {
                    $upload_dir = '../uploads/secondhand/'; // Chemin correct pour secondhand
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                    $new_images = [];
                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_name = uniqid() . '_' . basename($_FILES['images']['name'][$key]);
                            $file_path = $upload_dir . $file_name;
                            $file_type = $_FILES['images']['type'][$key];
                            $file_size = $_FILES['images']['size'][$key];

                            if (!in_array($file_type, $allowed_types)) {
                                $error_message = "Le fichier " . htmlspecialchars($_FILES['images']['name'][$key]) . " n'est pas un type d'image autorisé (JPG, PNG, GIF).";
                                break;
                            }
                            if ($file_size > $max_file_size) {
                                $error_message = "Le fichier " . htmlspecialchars($_FILES['images']['name'][$key]) . " dépasse la taille maximale de 5MB.";
                                break;
                            }

                            if (move_uploaded_file($tmp_name, $file_path)) {
                                $new_images[] = 'uploads/secondhand/' . $file_name; // Chemin relatif stocké dans la base
                            } else {
                                $error_message = "Erreur lors de l'upload de l'image " . htmlspecialchars($_FILES['images']['name'][$key]) . ".";
                                break;
                            }
                        }
                    }

                    if (!empty($new_images)) {
                        foreach ($images as $old_image) {
                            $old_image_path = '../' . $old_image; // Chemin absolu pour suppression
                            if (file_exists($old_image_path)) unlink($old_image_path);
                        }
                        $images = $new_images;
                    }
                }
            }
            $images_str = implode(',', $images);

            if (empty($error_message)) {
                // Mise à jour de l'annonce
                $query = "UPDATE secondhand_products 
                          SET user_id = :user_id, title = :title, description = :description, price = :price, 
                              etat = :etat, category_id = :category_id, brand_id = :brand_id, 
                              size = :size, images = :images, location = :location, 
                              shipping_method = :shipping_method, statut = :statut, updated_at = NOW()
                          WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':user_id' => $user_id,
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
                    ':id' => $product_id
                ]);

                $_SESSION['success_message'] = "Annonce mise à jour avec succès !";
                header('Location: secondhand.php');
                exit;
            }
        }
    }
} catch (PDOException $e) {
    $error_message = "Une erreur est survenue : " . htmlspecialchars($e->getMessage());
    error_log("Erreur PDO dans secondhand-edit : " . $e->getMessage());
}

// Titre de la page
$page_title = "Modifier une annonce 2ndHand - Admin Bander-Sneakers";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Modifier une annonce 2ndHand</h1>
            <p>Modifiez les détails de l'annonce sélectionnée.</p>
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

        <form action="secondhand-edit.php?id=<?= $product_id ?>" method="POST" enctype="multipart/form-data" class="admin-form">
            <div class="form-group">
                <label for="user_id">Utilisateur *</label>
                <select name="user_id" id="user_id" required>
                    <option value="">Sélectionner un utilisateur</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['user_id'] ?>" <?= $product['user_id'] == $user['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="title">Titre de l'annonce *</label>
                <input type="text" name="title" id="title" value="<?= htmlspecialchars($product['title']) ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Description *</label>
                <textarea name="description" id="description" rows="5" required><?= htmlspecialchars($product['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="price">Prix (€) *</label>
                <input type="number" name="price" id="price" step="0.01" value="<?= $product['price'] ?>" required>
            </div>

            <div class="form-group">
                <label for="etat">État *</label>
                <select name="etat" id="etat" required>
                    <option value="neuf" <?= $product['etat'] === 'neuf' ? 'selected' : '' ?>>Neuf</option>
                    <option value="très bon" <?= $product['etat'] === 'très bon' ? 'selected' : '' ?>>Très bon</option>
                    <option value="bon" <?= $product['etat'] === 'bon' ? 'selected' : '' ?>>Bon</option>
                    <option value="moyen" <?= $product['etat'] === 'moyen' ? 'selected' : '' ?>>Moyen</option>
                    <option value="usagé" <?= $product['etat'] === 'usagé' ? 'selected' : '' ?>>Usagé</option>
                </select>
            </div>

            <div class="form-group">
                <label for="category_id">Catégorie *</label>
                <select name="category_id" id="category_id" required>
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
                <select name="brand_id" id="brand_id">
                    <option value="">Sélectionner une marque (optionnel)</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= $brand['brand_id'] ?>" <?= $product['brand_id'] == $brand['brand_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($brand['brand_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="size">Taille *</label>
                <input type="text" name="size" id="size" value="<?= htmlspecialchars($product['size']) ?>" required placeholder="Ex. 42, M, L">
            </div>

            <div class="form-group">
                <label for="location">Localisation</label>
                <input type="text" name="location" id="location" value="<?= htmlspecialchars($product['location'] ?? '') ?>" placeholder="Ex. Paris, France">
            </div>

            <div class="form-group">
                <label for="shipping_method">Méthode d'expédition</label>
                <input type="text" name="shipping_method" id="shipping_method" value="<?= htmlspecialchars($product['shipping_method'] ?? '') ?>" placeholder="Ex. Colissimo, Remise en main propre">
            </div>

            <div class="form-group">
                <label for="statut">Statut de l'annonce *</label>
                <select name="statut" id="statut" required>
                    <option value="actif" <?= $product['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="vendu" <?= $product['statut'] === 'vendu' ? 'selected' : '' ?>>Vendu</option>
                    <option value="supprimé" <?= $product['statut'] === 'supprimé' ? 'selected' : '' ?>>Supprimé</option>
                    <option value="en attente" <?= $product['statut'] === 'en attente' ? 'selected' : '' ?>>En attente</option>
                </select>
            </div>

            <div class="form-group">
                <label>Images actuelles</label>
                <div class="image-list">
                    <?php
                    $images = !empty($product['images']) ? explode(',', $product['images']) : [];
                    if (!empty($images)): ?>
                        <?php foreach ($images as $image): ?>
                            <div class="image-item">
                                <img src="../uploads/secondhand/<?= htmlspecialchars(basename($image)) ?>" alt="Image">
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Aucune image actuellement.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="images">Remplacer les images (optionnel)</label>
                <input type="file" name="images[]" id="images" multiple accept="image/*">
                <p>Formats acceptés : JPG, PNG, GIF. Maximum 5 images.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
                <a href="secondhand.php" class="btn btn-secondary">Annuler</a>
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