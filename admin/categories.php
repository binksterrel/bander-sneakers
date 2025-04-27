<?php
// Page de gestion des catégories
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

// Supprimer une catégorie si demandé
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $category_id = (int)$_GET['delete'];
    try {
        $stmt = $db->prepare("DELETE FROM categories WHERE category_id = :category_id");
        $stmt->execute([':category_id' => $category_id]);
        $success_message = "Catégorie supprimée avec succès.";
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression de la catégorie : " . $e->getMessage();
        error_log("Erreur PDO dans categories : " . $e->getMessage());
    }
}

// Ajouter ou modifier une catégorie
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_name = cleanInput($_POST['category_name']);
    $category_description = cleanInput($_POST['category_description']);
    $category_id = isset($_POST['category_id']) && is_numeric($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    if (empty($category_name)) {
        $error_message = "Le nom de la catégorie est obligatoire.";
    } else {
        try {
            if ($category_id) {
                // Mise à jour d'une catégorie existante
                $stmt = $db->prepare("
                    UPDATE categories 
                    SET category_name = :category_name, category_description = :category_description 
                    WHERE category_id = :category_id
                ");
                $stmt->execute([
                    ':category_name' => $category_name,
                    ':category_description' => $category_description,
                    ':category_id' => $category_id
                ]);
                $success_message = "Catégorie mise à jour avec succès.";
            } else {
                // Ajout d'une nouvelle catégorie
                $stmt = $db->prepare("
                    INSERT INTO categories (category_name, category_description) 
                    VALUES (:category_name, :category_description)
                ");
                $stmt->execute([
                    ':category_name' => $category_name,
                    ':category_description' => $category_description
                ]);
                $success_message = "Catégorie ajoutée avec succès.";
            }
        } catch (PDOException $e) {
            $error_message = "Erreur lors de l'opération sur la catégorie : " . $e->getMessage();
            error_log("Erreur PDO dans categories : " . $e->getMessage());
        }
    }
}

// Récupérer toutes les catégories
$categories = $db->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

// Récupérer les données d'une catégorie spécifique pour modification (si demandé)
$edit_category = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM categories WHERE category_id = :category_id");
    $stmt->execute([':category_id' => $edit_id]);
    $edit_category = $stmt->fetch();
}

// Titre de la page
$page_title = "Gestion des catégories - Admin Bander-Sneakers";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Gestion des catégories</h1>
            <p>Gérez les catégories de produits.</p>
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

        <div class="admin-form-container">
            <h2><?= $edit_category ? 'Modifier la catégorie' : 'Ajouter une catégorie' ?></h2>
            <form action="categories.php" method="POST" class="admin-form">
                <?php if ($edit_category): ?>
                    <input type="hidden" name="category_id" value="<?= $edit_category['category_id'] ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="category_name">Nom de la catégorie *</label>
                    <input type="text" name="category_name" id="category_name" value="<?= $edit_category ? htmlspecialchars($edit_category['category_name']) : '' ?>" required>
                </div>
                <div class="form-group">
                    <label for="category_description">Description</label>
                    <textarea name="category_description" id="category_description" rows="4"><?= $edit_category ? htmlspecialchars($edit_category['category_description']) : '' ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $edit_category ? 'Mettre à jour' : 'Ajouter' ?></button>
                    <?php if ($edit_category): ?>
                        <a href="categories.php" class="btn btn-secondary">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="admin-table-container">
            <?php if (empty($categories)): ?>
                <div class="no-results">
                    <p>Aucune catégorie trouvée.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?= $category['category_id'] ?></td>
                                <td><?= htmlspecialchars($category['category_name']) ?></td>
                                <td><?= htmlspecialchars($category['category_description']) ?></td>
                                <td class="actions-cell">
                                    <a href="categories.php?edit=<?= $category['category_id'] ?>" class="btn-action" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="categories.php?delete=<?= $category['category_id'] ?>" class="btn-action delete-btn" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ? Les produits associés seront également supprimés.');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .admin-form-container {
        background: var(--white);
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: var(--box-shadow);
        margin-bottom: 2rem;
    }
    .admin-form {
        max-width: 600px;
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
        color:rgb(0, 0, 0);
    }
    .delete-btn:hover {
        color: #c0392b;
    }
</style>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>