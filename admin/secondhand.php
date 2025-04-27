<?php
session_start();

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

require_once '../includes/config.php';
require_once '../includes/functions.php';

$db = getDbConnection();
$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Gestion des actions
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($_GET['action'] === 'delete') {
        try {
            $stmt = $db->prepare("UPDATE secondhand_products SET statut = 'supprimé' WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $_SESSION['success_message'] = "Annonce supprimée avec succès.";
            header("Location: secondhand.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// Filtres et recherche
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'actif';

// Récupérer les annonces
$sql = "SELECT sp.*, u.username, c.category_name, b.brand_name 
        FROM secondhand_products sp 
        JOIN users u ON sp.user_id = u.user_id 
        LEFT JOIN categories c ON sp.category_id = c.category_id 
        LEFT JOIN brands b ON sp.brand_id = b.brand_id 
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (sp.title LIKE :search OR sp.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status_filter) {
    $sql .= " AND sp.statut = :status";
    $params[':status'] = $status_filter;
}

$sql .= " ORDER BY sp.created_at DESC";
$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Gestion des annonces 2ndHand - Admin Bander-Sneakers";
include 'includes/header.php';
?>

<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Gestion des annonces 2ndHand</h1>
            <p>Voir et gérer les annonces publiées par les utilisateurs.</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= $error_message ?></div>
        <?php endif; ?>

        <div class="filters">
            <form method="GET" class="filter-form">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher par titre ou description...">
                <select name="status">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?= $status_filter == 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="vendu" <?= $status_filter == 'vendu' ? 'selected' : '' ?>>Vendu</option>
                    <option value="supprimé" <?= $status_filter == 'supprimé' ? 'selected' : '' ?>>Supprimé</option>
                    <option value="en attente" <?= $status_filter == 'en attente' ? 'selected' : '' ?>>En attente</option>
                </select>
                <button type="submit" class="btn btn-primary">Filtrer</button>
            </form>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Titre</th>
                    <th>Vendeur</th>
                    <th>Catégorie</th>
                    <th>Marque</th>
                    <th>Prix (€)</th>
                    <th>État</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="10">Aucune annonce trouvée.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= $product['id'] ?></td>
                            <td><?= htmlspecialchars($product['title']) ?></td>
                            <td><?= htmlspecialchars($product['username']) ?></td>
                            <td><?= htmlspecialchars($product['category_name'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($product['brand_name'] ?: 'N/A') ?></td>
                            <td><?= number_format($product['price'], 2) ?></td>
                            <td><?= htmlspecialchars($product['etat']) ?></td>
                            <td><?= htmlspecialchars($product['statut']) ?></td>
                            <td><?= date('d/m/Y', strtotime($product['created_at'])) ?></td>
                            <td>
                                <a href="../2ndhand-detail.php?id=<?= $product['id'] ?>" target="_blank" class="btn btn-secondary btn-sm">Voir</a>
                                <a href="secondhand-edit.php?id=<?= $product['id'] ?>" class="btn btn-secondary btn-sm">Modifier</a>
                                <a href="secondhand.php?action=delete&id=<?= $product['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cette annonce ?');">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    .filters { margin-bottom: 20px; }
    .filter-form { display: flex; gap: 10px; }
    .filter-form input[type="text"], .filter-form select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th, .admin-table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
    .admin-table th { background: #f1f1f1; }
    .btn-sm { padding: 5px 10px; }
</style>

<?php include 'includes/footer.php'; ?>