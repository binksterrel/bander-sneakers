<?php
// Page de gestion des marques
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

// Supprimer une marque si demandé
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $brand_id = (int)$_GET['delete'];
    try {
        // Supprimer le logo s'il existe
        $stmt = $db->prepare("SELECT brand_logo FROM brands WHERE brand_id = :brand_id");
        $stmt->execute([':brand_id' => $brand_id]);
        $logo = $stmt->fetchColumn();
        if ($logo && file_exists('../assets/images/brands/' . $logo)) {
            unlink('../assets/images/brands/' . $logo);
        }

        // Supprimer la marque (les produits associés seront supprimés via ON DELETE CASCADE)
        $stmt = $db->prepare("DELETE FROM brands WHERE brand_id = :brand_id");
        $stmt->execute([':brand_id' => $brand_id]);
        $success_message = "Marque supprimée avec succès.";
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression de la marque : " . $e->getMessage();
        error_log("Erreur PDO dans brands : " . $e->getMessage());
    }
}

// Ajouter ou modifier une marque
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brand_name = cleanInput($_POST['brand_name']);
    $brand_description = cleanInput($_POST['brand_description']);
    $brand_id = isset($_POST['brand_id']) && is_numeric($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;

    if (empty($brand_name)) {
        $error_message = "Le nom de la marque est obligatoire.";
    } else {
        try {
            // Gestion du logo
            $brand_logo = null;
            if (!empty($_FILES['brand_logo']['name'])) {
                $file_name = uniqid() . '_' . basename($_FILES['brand_logo']['name']);
                $upload_path = '../assets/images/brands/' . $file_name;
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

                if (in_array($_FILES['brand_logo']['type'], $allowed_types) && $_FILES['brand_logo']['size'] <= 2 * 1024 * 1024) { // 2MB max
                    if (move_uploaded_file($_FILES['brand_logo']['tmp_name'], $upload_path)) {
                        $brand_logo = $file_name;
                        // Supprimer l'ancien logo si modification
                        if ($brand_id) {
                            $stmt = $db->prepare("SELECT brand_logo FROM brands WHERE brand_id = :brand_id");
                            $stmt->execute([':brand_id' => $brand_id]);
                            $old_logo = $stmt->fetchColumn();
                            if ($old_logo && file_exists('../assets/images/brands/' . $old_logo)) {
                                unlink('../assets/images/brands/' . $old_logo);
                            }
                        }
                    } else {
                        throw new Exception("Échec du téléversement du logo.");
                    }
                } else {
                    throw new Exception("Le logo doit être une image (JPEG, PNG, GIF) et ne pas dépasser 2 Mo.");
                }
            }

            if ($brand_id) {
                // Mise à jour d'une marque existante
                $sql = "UPDATE brands SET brand_name = :brand_name, brand_description = :brand_description";
                $params = [
                    ':brand_name' => $brand_name,
                    ':brand_description' => $brand_description,
                    ':brand_id' => $brand_id
                ];
                if ($brand_logo) {
                    $sql .= ", brand_logo = :brand_logo";
                    $params[':brand_logo'] = $brand_logo;
                }
                $sql .= " WHERE brand_id = :brand_id";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $success_message = "Marque mise à jour avec succès.";
            } else {
                // Ajout d'une nouvelle marque
                $stmt = $db->prepare("
                    INSERT INTO brands (brand_name, brand_description, brand_logo) 
                    VALUES (:brand_name, :brand_description, :brand_logo)
                ");
                $stmt->execute([
                    ':brand_name' => $brand_name,
                    ':brand_description' => $brand_description,
                    ':brand_logo' => $brand_logo
                ]);
                $success_message = "Marque ajoutée avec succès.";
            }
        } catch (Exception $e) {
            $error_message = "Erreur lors de l'opération sur la marque : " . $e->getMessage();
            error_log("Erreur dans brands : " . $e->getMessage());
        }
    }
}

// Récupérer toutes les marques
$brands = $db->query("SELECT * FROM brands ORDER BY brand_name ASC")->fetchAll();

// Récupérer les données d'une marque spécifique pour modification (si demandé)
$edit_brand = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM brands WHERE brand_id = :brand_id");
    $stmt->execute([':brand_id' => $edit_id]);
    $edit_brand = $stmt->fetch();
}

// Titre de la page
$page_title = "Gestion des marques - Admin Bander-Sneakers";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Gestion des marques</h1>
            <p>Gérez les marques de produits.</p>
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
            <h2><?= $edit_brand ? 'Modifier la marque' : 'Ajouter une marque' ?></h2>
            <form action="brands.php" method="POST" enctype="multipart/form-data" class="admin-form">
                <?php if ($edit_brand): ?>
                    <input type="hidden" name="brand_id" value="<?= $edit_brand['brand_id'] ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="brand_name">Nom de la marque *</label>
                    <input type="text" name="brand_name" id="brand_name" value="<?= $edit_brand ? htmlspecialchars($edit_brand['brand_name']) : '' ?>" required>
                </div>
                <div class="form-group">
                    <label for="brand_description">Description</label>
                    <textarea name="brand_description" id="brand_description" rows="4"><?= $edit_brand ? htmlspecialchars($edit_brand['brand_description']) : '' ?></textarea>
                </div>
                <div class="form-group">
                    <label for="brand_logo">Logo (JPEG, PNG, GIF, max 2 Mo)</label>
                    <input type="file" name="brand_logo" id="brand_logo" accept="image/jpeg,image/png,image/gif">
                    <?php if ($edit_brand && $edit_brand['brand_logo']): ?>
                        <p>Logo actuel : <img src="../assets/images/brands/<?= htmlspecialchars($edit_brand['brand_logo']) ?>" alt="Logo" style="max-width: 100px; margin-top: 0.5rem;"></p>
                    <?php endif; ?>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $edit_brand ? 'Mettre à jour' : 'Ajouter' ?></button>
                    <?php if ($edit_brand): ?>
                        <a href="brands.php" class="btn btn-secondary">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="admin-table-container">
            <?php if (empty($brands)): ?>
                <div class="no-results">
                    <p>Aucune marque trouvée.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Description</th>
                            <th>Logo</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($brands as $brand): ?>
                            <tr>
                                <td><?= $brand['brand_id'] ?></td>
                                <td><?= htmlspecialchars($brand['brand_name']) ?></td>
                                <td><?= htmlspecialchars($brand['brand_description']) ?></td>
                                <td>
                                    <?php if ($brand['brand_logo']): ?>
                                        <img src="../assets/images/brands/<?= htmlspecialchars($brand['brand_logo']) ?>" alt="Logo" style="max-width: 50px;">
                                    <?php else: ?>
                                        Aucun logo
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="brands.php?edit=<?= $brand['brand_id'] ?>" class="btn-action" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="brands.php?delete=<?= $brand['brand_id'] ?>" class="btn-action delete-btn" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette marque ? Les produits associés seront également supprimés.');">
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
        color:rgb(0, 0, 0);
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