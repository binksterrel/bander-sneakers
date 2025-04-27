<?php
session_start();

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
    if ($_GET['action'] === 'resolve') {
        try {
            $stmt = $db->prepare("UPDATE reports SET etat_signalement = 'résolu' WHERE report_id = :id"); // Corrigé : 'résolu'
            $stmt->execute([':id' => $id]);
            $_SESSION['success_message'] = "Signalement marqué comme résolu.";
            header("Location: reports.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Erreur lors de la résolution : " . $e->getMessage();
        }
    } elseif ($_GET['action'] === 'reject') {
        try {
            $stmt = $db->prepare("UPDATE reports SET etat_signalement = 'rejeté' WHERE report_id = :id"); // Corrigé : 'rejeté'
            $stmt->execute([':id' => $id]);
            $_SESSION['success_message'] = "Signalement rejeté.";
            header("Location: reports.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Erreur lors du rejet : " . $e->getMessage();
        }
    }
}

// Filtres
$etat_signalement_filter = isset($_GET['etat_signalement']) ? $_GET['etat_signalement'] : '';

// Récupérer les signalements
$sql = "SELECT r.*, u.username AS reporter, ru.username AS reported_user,
        CASE 
            WHEN r.type = 'secondhand' THEN sp.title 
            WHEN r.type = 'review' THEN rev.review_text 
        END as item_title
        FROM reports r
        JOIN users u ON r.user_id = u.user_id
        JOIN users ru ON r.reported_user_id = ru.user_id
        LEFT JOIN secondhand_products sp ON r.type = 'secondhand' AND r.item_id = sp.id
        LEFT JOIN reviews rev ON r.type = 'review' AND r.item_id = rev.review_id
        WHERE 1=1";
$params = [];

if ($etat_signalement_filter) {
    $sql .= " AND r.etat_signalement = :etat_signalement";
    $params[':etat_signalement'] = $etat_signalement_filter;
}

$sql .= " ORDER BY r.created_at DESC";
$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Gestion des signalements - Admin Bander-Sneakers";
include 'includes/header.php';
?>

<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Gestion des signalements</h1>
            <p>Voir et gérer les signalements soumis par les utilisateurs.</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= $error_message ?></div>
        <?php endif; ?>

        <div class="filters">
            <form method="GET" class="filter-form">
                <select name="etat_signalement">
                    <option value="">Tous les statuts</option>
                    <option value="en attente" <?= $etat_signalement_filter == 'en attente' ? 'selected' : '' ?>>En attente</option>
                    <option value="résolu" <?= $etat_signalement_filter == 'résolu' ? 'selected' : '' ?>>Résolu</option>
                    <option value="rejeté" <?= $etat_signalement_filter == 'rejeté' ? 'selected' : '' ?>>Rejeté</option>
                </select>
                <button type="submit" class="btn btn-primary">Filtrer</button>
            </form>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Article</th>
                    <th>Signalé par</th>
                    <th>Utilisateur signalé</th>
                    <th>Raison</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr><td colspan="9">Aucun signalement trouvé.</td></tr>
                <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><?= $report['report_id'] ?></td>
                            <td><?= htmlspecialchars($report['type']) ?></td>
                            <td>
                                <?php if ($report['type'] == 'secondhand'): ?>
                                    <a href="../2ndhand-detail.php?id=<?= $report['item_id'] ?>" target="_blank"><?= htmlspecialchars($report['item_title']) ?></a>
                                <?php else: ?>
                                    <?= htmlspecialchars(substr($report['item_title'], 0, 50)) . (strlen($report['item_title']) > 50 ? '...' : '') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($report['reporter']) ?></td>
                            <td><?= htmlspecialchars($report['reported_user']) ?></td>
                            <td><?= htmlspecialchars($report['reason']) ?></td>
                            <td><?= isset($report['etat_signalement']) ? htmlspecialchars($report['etat_signalement']) : 'Non défini' ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($report['created_at'])) ?></td>
                            <td>
                                <?php if (isset($report['etat_signalement']) && $report['etat_signalement'] == 'en attente'): ?>
                                    <a href="reports.php?action=resolve&id=<?= $report['report_id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Marquer ce signalement comme résolu ?');">Résoudre</a>
                                    <a href="reports.php?action=reject&id=<?= $report['report_id'] ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Rejeter ce signalement ?');">Rejeter</a>
                                <?php else: ?>
                                    <span class="text-muted">Traité</span>
                                <?php endif; ?>
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
    .filter-form select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th, .admin-table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
    .admin-table th { background: #f1f1f1; }
    .btn-sm { padding: 5px 10px; margin-right: 5px; }
    .btn-success { background-color: #28a745; color: white; text-decoration: none; }
    .btn-success:hover { background-color: #218838; }
    .btn-secondary { background-color: #6c757d; color: white; text-decoration: none; }
    .btn-secondary:hover { background-color: #5a6268; }
    .text-muted { color: #888; }
</style>

<?php include 'includes/footer.php'; ?>