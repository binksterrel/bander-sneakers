<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$db = getDbConnection();
$categories = $db->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$brands = $db->query("SELECT brand_id, brand_name FROM brands ORDER BY brand_name ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et nettoyer les données du formulaire
    $title = cleanInput($_POST['title'] ?? '');
    $description = cleanInput($_POST['description'] ?? '', false);
    $price = floatval($_POST['price'] ?? 0);
    $etat = $_POST['etat'] ?? '';
    $category_id = isset($_POST['category_id']) && is_numeric($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $brand_id = isset($_POST['brand_id']) && is_numeric($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $size = cleanInput($_POST['size'] ?? '');
    $location = cleanInput($_POST['location'] ?? '');
    $shipping_method = cleanInput($_POST['shipping_method'] ?? '');
    $user_id = $_SESSION['user_id'];

    // Validation des champs obligatoires
    if (empty($title)) $errors[] = "Le titre de l'annonce est requis.";
    if (empty($description)) $errors[] = "La description est requise.";
    if ($price <= 0) $errors[] = "Le prix doit être un nombre positif.";
    if (!in_array($etat, ['neuf', 'très bon', 'bon', 'moyen', 'usagé'])) $errors[] = "L'état sélectionné est invalide.";
    if (empty($category_id)) $errors[] = "La catégorie est requise.";
    if (empty($size)) $errors[] = "La taille est requise.";

    // Gestion des images (optionnelles)
    $images = [];
    $max_images = 5;
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_file_size = 5 * 1024 * 1024; // 5MB

    if (!empty($_FILES['images']['name'][0])) {
        $total_files = count(array_filter($_FILES['images']['name']));
        if ($total_files > $max_images) {
            $errors[] = "Vous ne pouvez uploader que $max_images images maximum.";
        } else {
            $upload_dir = 'uploads/secondhand/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

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
                        $images[] = $file_path;
                    } else {
                        $errors[] = "Erreur lors de l'upload de l'image " . htmlspecialchars($_FILES['images']['name'][$key]) . ".";
                    }
                }
            }
        }
    }
    $images_str = implode(',', $images);

    // Si aucune erreur, insérer l'annonce dans la base de données
    if (empty($errors)) {
        try {
            $query = "INSERT INTO secondhand_products (user_id, title, description, price, etat, category_id, brand_id, size, images, location, shipping_method, statut, created_at) 
                      VALUES (:user_id, :title, :description, :price, :etat, :category_id, :brand_id, :size, :images, :location, :shipping_method, 'actif', NOW())";
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
                ':images' => $images_str ?: null,
                ':location' => $location ?: null,
                ':shipping_method' => $shipping_method ?: null
            ]);

            // Récupérer l'ID de l'annonce insérée et notifier les abonnés
            $product_id = $db->lastInsertId();
            notifySubscribersOfNewProduct($user_id, $product_id, $title);

            $_SESSION['success_message'] = "Annonce publiée avec succès !";
            header('Location: 2ndhand.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la publication de l'annonce : " . htmlspecialchars($e->getMessage());
        }
    }
}

// Titre et description de la page
$page_title = "Publier une annonce - 2ndHand | Bander-Sneakers";
$page_description = "Publiez votre annonce sur Bander-Sneakers pour vendre vos sneakers d'occasion.";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li><a href="2ndhand.php">2ndHand</a></li>
            <li class="active">Publier une annonce</li>
        </ul>
    </div>
</div>

<!-- Post Ad Section -->
<section class="auth-section">
    <div class="container">
        <div class="auth-container">
            <div class="auth-form-container">
                <h1 class="auth-title">Publier une annonce</h1>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form action="2ndhand-post.php" method="POST" enctype="multipart/form-data" class="auth-form">
                    <div class="form-group">
                        <label for="title">Titre de l'annonce <span class="required">*</span></label>
                        <input type="text" id="title" name="title" value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description <span class="required">*</span></label>
                        <textarea id="description" name="description" rows="5" required><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="price">Prix (€) <span class="required">*</span></label>
                        <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo isset($price) && $price > 0 ? $price : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="etat">État <span class="required">*</span></label>
                        <select id="etat" name="etat" required>
                            <option value="neuf" <?php echo (isset($etat) && $etat === 'neuf') ? 'selected' : ''; ?>>Neuf</option>
                            <option value="très bon" <?php echo (isset($etat) && $etat === 'très bon') ? 'selected' : ''; ?>>Très bon</option>
                            <option value="bon" <?php echo (isset($etat) && $etat === 'bon') ? 'selected' : ''; ?>>Bon</option>
                            <option value="moyen" <?php echo (isset($etat) && $etat === 'moyen') ? 'selected' : ''; ?>>Moyen</option>
                            <option value="usagé" <?php echo (isset($etat) && $etat === 'usagé') ? 'selected' : ''; ?>>Usagé</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="category_id">Catégorie <span class="required">*</span></label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Sélectionner une catégorie</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['category_id'] ?>" <?php echo (isset($category_id) && $category_id == $category['category_id']) ? 'selected' : ''; ?>>
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
                                <option value="<?= $brand['brand_id'] ?>" <?php echo (isset($brand_id) && $brand_id == $brand['brand_id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($brand['brand_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="size">Taille <span class="required">*</span></label>
                        <input type="text" id="size" name="size" value="<?php echo isset($size) ? htmlspecialchars($size) : ''; ?>" required placeholder="Ex. 42, M, L">
                        <p class="form-hint">Indiquez la taille (par exemple, 42 pour une pointure).</p>
                    </div>
                    <div class="form-group">
                        <label for="location">Localisation</label>
                        <input type="text" id="location" name="location" value="<?php echo isset($location) ? htmlspecialchars($location) : ''; ?>" placeholder="Ex. Paris, France">
                        <p class="form-hint">Indiquez où se trouve l'article (optionnel).</p>
                    </div>
                    <div class="form-group">
                        <label for="shipping_method">Méthode d'expédition</label>
                        <input type="text" id="shipping_method" name="shipping_method" value="<?php echo isset($shipping_method) ? htmlspecialchars($shipping_method) : ''; ?>" placeholder="Ex. Colissimo, Remise en main propre">
                        <p class="form-hint">Indiquez les options d'expédition (optionnel).</p>
                    </div>
                    <div class="form-group">
                        <label for="images">Images (jusqu'à 5)</label>
                        <input type="file" id="images" name="images[]" multiple accept="image/*">
                        <p class="form-hint">Formats acceptés : JPG, PNG, GIF. Maximum 5 images, 5MB par fichier.</p>
                        <div class="image-preview" id="image-preview"></div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Publier l'annonce</button>
                        <a href="2ndhand.php" class="btn btn-primary">Annuler</a>
                    </div>
                </form>

                <div class="auth-links">
                    <p>Retourner à la liste des annonces ? <a href="2ndhand.php">Voir les annonces</a></p>
                </div>
            </div>

            <div class="auth-sidebar">
                <div class="auth-info">
                    <h2>Conseils pour une annonce efficace</h2>
                    <ul>
                        <li><strong>Titre clair :</strong> Utilisez un titre précis (ex. "Nike Air Max 90 - Taille 42").</li>
                        <li><strong>Description détaillée :</strong> Mentionnez la taille, l'état, les défauts éventuels, et l'historique de l'article.</li>
                        <li><strong>Photos de qualité :</strong> Prenez des photos nettes sous différents angles, avec un bon éclairage.</li>
                        <li><strong>Prix réaliste :</strong> Fixez un prix en fonction de l'état et de la valeur marchande de l'article.</li>
                        <li><strong>Répondez rapidement :</strong> Soyez réactif aux messages des acheteurs potentiels.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<style>
    .image-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }
    .image-preview img {
        max-width: 100px;
        max-height: 100px;
        object-fit: cover;
        border: 1px solid #ddd;
        border-radius: 5px;
    }
    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('images');
    const preview = document.getElementById('image-preview');

    input.addEventListener('change', function() {
        preview.innerHTML = ''; // Réinitialiser l'aperçu
        const files = this.files;
        const maxFiles = 5;

        if (files.length > maxFiles) {
            alert('Vous ne pouvez sélectionner que 5 images maximum.');
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