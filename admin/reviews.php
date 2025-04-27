<?php
// Page de gestion des avis
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

// Supprimer un avis si demandé
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $review_id = (int)$_GET['delete'];
    try {
        $stmt = $db->prepare("DELETE FROM reviews WHERE review_id = :review_id");
        $stmt->execute([':review_id' => $review_id]);
        $success_message = "Avis supprimé avec succès.";
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression de l'avis : " . $e->getMessage();
        error_log("Erreur PDO dans reviews : " . $e->getMessage());
    }
}

// Modifier un avis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id'])) {
    $review_id = (int)$_POST['review_id'];
    $rating = (int)$_POST['rating'];
    $review_text = cleanInput($_POST['review_text']);

    if ($rating < 1 || $rating > 5 || empty($review_text)) {
        $error_message = "La note doit être entre 1 et 5, et le texte ne peut pas être vide.";
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE reviews 
                SET rating = :rating, review_text = :review_text 
                WHERE review_id = :review_id
            ");
            $stmt->execute([
                ':rating' => $rating,
                ':review_text' => $review_text,
                ':review_id' => $review_id
            ]);
            $success_message = "Avis mis à jour avec succès.";
        } catch (PDOException $e) {
            $error_message = "Erreur lors de la mise à jour de l'avis : " . $e->getMessage();
            error_log("Erreur PDO dans reviews : " . $e->getMessage());
        }
    }
}

// Pagination
$items_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Filtres
$filters = [];
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $filters['user_id'] = (int)$_GET['user_id'];
}
if (isset($_GET['sneaker_id']) && is_numeric($_GET['sneaker_id'])) {
    $filters['sneaker_id'] = (int)$_GET['sneaker_id'];
}
if (isset($_GET['rating']) && is_numeric($_GET['rating']) && $_GET['rating'] >= 1 && $_GET['rating'] <= 5) {
    $filters['rating'] = (int)$_GET['rating'];
}

// Récupérer le nombre total d'avis pour la pagination
$sql = "SELECT COUNT(*) as total FROM reviews r WHERE 1=1";
$params = [];
if (isset($filters['user_id'])) {
    $sql .= " AND r.user_id = :user_id";
    $params[':user_id'] = $filters['user_id'];
}
if (isset($filters['sneaker_id'])) {
    $sql .= " AND r.sneaker_id = :sneaker_id";
    $params[':sneaker_id'] = $filters['sneaker_id'];
}
if (isset($filters['rating'])) {
    $sql .= " AND r.rating = :rating";
    $params[':rating'] = $filters['rating'];
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Récupérer les avis avec filtres et pagination
$sql = "
    SELECT r.review_id, r.user_id, r.sneaker_id, r.rating, r.review_text, r.created_at, 
           u.username, s.sneaker_name
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.user_id
    LEFT JOIN sneakers s ON r.sneaker_id = s.sneaker_id
    WHERE 1=1
";
$params = [];
if (isset($filters['user_id'])) {
    $sql .= " AND r.user_id = :user_id";
    $params[':user_id'] = $filters['user_id'];
}
if (isset($filters['sneaker_id'])) {
    $sql .= " AND r.sneaker_id = :sneaker_id";
    $params[':sneaker_id'] = $filters['sneaker_id'];
}
if (isset($filters['rating'])) {
    $sql .= " AND r.rating = :rating";
    $params[':rating'] = $filters['rating'];
}
$sql .= " ORDER BY r.created_at DESC LIMIT :offset, :items_per_page";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':items_per_page', $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$reviews = $stmt->fetchAll();

// Récupérer les utilisateurs et produits pour les filtres
$users = $db->query("SELECT user_id, username FROM users ORDER BY username ASC")->fetchAll();
$sneakers = $db->query("SELECT sneaker_id, sneaker_name FROM sneakers ORDER BY sneaker_name ASC")->fetchAll();

// Récupérer les données d'un avis spécifique pour modification (si demandé)
$edit_review = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare("
        SELECT r.review_id, r.rating, r.review_text 
        FROM reviews r 
        WHERE r.review_id = :review_id
    ");
    $stmt->execute([':review_id' => $edit_id]);
    $edit_review = $stmt->fetch();
}

// Titre de la page
$page_title = "Gestion des avis - Admin Bander-Sneakers";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Gestion des avis</h1>
            <p>Gérez les avis des utilisateurs sur les produits.</p>
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

        <?php if ($edit_review): ?>
            <div class="admin-form-container">
                <h2>Modifier l'avis</h2>
                <form action="reviews.php" method="POST" class="admin-form">
                    <input type="hidden" name="review_id" value="<?= $edit_review['review_id'] ?>">
                    <div class="form-group">
                        <label for="rating">Note (1-5) *</label>
                        <input type="number" name="rating" id="rating" min="1" max="5" value="<?= $edit_review['rating'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="review_text">Texte de l'avis *</label>
                        <textarea name="review_text" id="review_text" rows="4" required><?= htmlspecialchars($edit_review['review_text']) ?></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Mettre à jour</button>
                        <a href="reviews.php" class="btn btn-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="admin-filters">
            <form action="reviews.php" method="GET" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="user_id">Utilisateur</label>
                        <select id="user_id" name="user_id">
                            <option value="">Tous les utilisateurs</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['user_id'] ?>" <?= isset($filters['user_id']) && $filters['user_id'] == $user['user_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="sneaker_id">Produit</label>
                        <select id="sneaker_id" name="sneaker_id">
                            <option value="">Tous les produits</option>
                            <?php foreach ($sneakers as $sneaker): ?>
                                <option value="<?= $sneaker['sneaker_id'] ?>" <?= isset($filters['sneaker_id']) && $filters['sneaker_id'] == $sneaker['sneaker_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sneaker['sneaker_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="rating">Note</label>
                        <select id="rating" name="rating">
                            <option value="">Toutes les notes</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>" <?= isset($filters['rating']) && $filters['rating'] == $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <a href="reviews.php" class="btn btn-secondary">Réinitialiser</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="admin-table-container">
            <?php if (empty($reviews)): ?>
                <div class="no-results">
                    <p>Aucun avis trouvé.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Utilisateur</th>
                            <th>Produit</th>
                            <th>Note</th>
                            <th>Texte</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $review): ?>
                            <tr>
                                <td><?= $review['review_id'] ?></td>
                                <td><?= $review['username'] ? htmlspecialchars($review['username']) : 'Anonyme' ?></td>
                                <td><?= htmlspecialchars($review['sneaker_name']) ?></td>
                                <td><?= $review['rating'] ?>/5</td>
                                <td><?= htmlspecialchars($review['review_text']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($review['created_at'])) ?></td>
                                <td class="actions-cell">
                                    <a href="reviews.php?edit=<?= $review['review_id'] ?>" class="btn-action" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="reviews.php?delete=<?= $review['review_id'] ?>" class="btn-action delete-btn" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet avis ?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <ul>
                            <?php if ($current_page > 1): ?>
                                <li><a href="<?= updateQueryString(['page' => $current_page - 1]) ?>"><i class="fas fa-chevron-left"></i></a></li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            if ($start_page > 1) {
                                echo '<li><a href="' . updateQueryString(['page' => 1]) . '">1</a></li>';
                                if ($start_page > 2) echo '<li class="ellipsis">...</li>';
                            }

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active = $i == $current_page ? 'active' : '';
                                echo '<li class="' . $active . '"><a href="' . updateQueryString(['page' => $i]) . '">' . $i . '</a></li>';
                            }

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) echo '<li class="ellipsis">...</li>';
                                echo '<li><a href="' . updateQueryString(['page' => $total_pages]) . '">' . $total_pages . '</a></li>';
                            }
                            ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li><a href="<?= updateQueryString(['page' => $current_page + 1]) ?>"><i class="fas fa-chevron-right"></i></a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
/**
 * Met à jour les paramètres de l'URL.
 *
 * @param array $params Les paramètres à mettre à jour
 * @return string L'URL mise à jour
 */
function updateQueryString($params) {
    $query = $_GET;
    foreach ($params as $key => $value) {
        $query[$key] = $value;
    }
    return 'reviews.php?' . http_build_query($query);
}
?>

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
    .admin-filters {
        margin-bottom: 2rem;
    }
    .filter-form {
        background: var(--white);
        padding: 1rem;
        border-radius: 8px;
        box-shadow: var(--box-shadow);
    }
    .filter-row {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
    }
    .filter-group {
        flex: 1;
    }
    .filter-buttons {
        display: flex;
        gap: 0.5rem;
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
        ;
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