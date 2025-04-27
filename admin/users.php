<?php
// Page de gestion des utilisateurs
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

// Supprimer un utilisateur si demandé
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    if ($user_id === $_SESSION['user_id']) {
        $error_message = "Vous ne pouvez pas supprimer votre propre compte.";
    } else {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("DELETE FROM users WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            $db->commit();
            $success_message = "Utilisateur supprimé avec succès.";
        } catch (PDOException $e) {
            $db->rollBack();
            $error_message = "Erreur lors de la suppression de l'utilisateur : " . $e->getMessage();
            error_log("Erreur PDO dans users : " . $e->getMessage());
        }
    }
}

// Ajouter ou modifier un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username']);
    $email = cleanInput($_POST['email']);
    $password = $_POST['password'] ?? '';
    $first_name = cleanInput($_POST['first_name']);
    $last_name = cleanInput($_POST['last_name']);
    $is_admin = isset($_POST['is_admin']) ? 1 : 0; // 1 si coché, 0 sinon
    $points = isset($_POST['points']) && is_numeric($_POST['points']) ? (int)$_POST['points'] : 0;
    $user_id = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : null;

    if (empty($username) || empty($email) || (!$user_id && empty($password))) {
        $error_message = "Le nom d'utilisateur, l'email et le mot de passe (pour un nouvel utilisateur) sont obligatoires.";
    } elseif ($points < 0) {
        $error_message = "Les points ne peuvent pas être négatifs.";
    } else {
        try {
            $db->beginTransaction();
            if ($user_id) {
                // Mise à jour d'un utilisateur existant
                $sql = "
                    UPDATE users 
                    SET username = :username, 
                        email = :email, 
                        first_name = :first_name, 
                        last_name = :last_name, 
                        is_admin = :is_admin
                    WHERE user_id = :user_id
                ";
                $params = [
                    ':username' => $username,
                    ':email' => $email,
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':is_admin' => $is_admin,
                    ':user_id' => $user_id
                ];
                if (!empty($password)) {
                    $sql = str_replace('WHERE', ", password = :password WHERE", $sql);
                    $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $stmt = $db->prepare($sql);
                $stmt->execute($params);

                // Ajouter les points si différents de zéro
                if ($points > 0) {
                    $stmt = $db->prepare("
                        INSERT INTO loyalty_points (user_id, points, earned_at)
                        VALUES (:user_id, :points, NOW())
                    ");
                    $stmt->execute([':user_id' => $user_id, ':points' => $points]);
                }
                $success_message = "Utilisateur mis à jour avec succès.";
            } else {
                // Ajout d'un nouvel utilisateur
                $stmt = $db->prepare("
                    INSERT INTO users (username, email, password, first_name, last_name, is_admin)
                    VALUES (:username, :email, :password, :first_name, :last_name, :is_admin)
                ");
                $stmt->execute([
                    ':username' => $username,
                    ':email' => $email,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':is_admin' => $is_admin
                ]);
                $new_user_id = $db->lastInsertId();

                // Ajouter les points initiaux si spécifiés
                if ($points > 0) {
                    $stmt = $db->prepare("
                        INSERT INTO loyalty_points (user_id, points, earned_at)
                        VALUES (:user_id, :points, NOW())
                    ");
                    $stmt->execute([':user_id' => $new_user_id, ':points' => $points]);
                }
                $success_message = "Utilisateur ajouté avec succès.";
            }
            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            $error_message = "Erreur lors de l'opération sur l'utilisateur : " . $e->getMessage();
            error_log("Erreur PDO dans users : " . $e->getMessage());
        }
    }
}

// Pagination
$items_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Filtres
$filters = [];
if (isset($_GET['username']) && !empty($_GET['username'])) {
    $filters['username'] = $_GET['username'];
}
if (isset($_GET['is_admin']) && in_array($_GET['is_admin'], ['0', '1'])) {
    $filters['is_admin'] = (int)$_GET['is_admin'];
}

// Récupérer le nombre total d'utilisateurs pour la pagination
$sql = "SELECT COUNT(*) as total FROM users WHERE 1=1";
$params = [];
if (isset($filters['username'])) {
    $sql .= " AND username LIKE :username";
    $params[':username'] = '%' . $filters['username'] . '%';
}
if (isset($filters['is_admin'])) {
    $sql .= " AND is_admin = :is_admin";
    $params[':is_admin'] = $filters['is_admin'];
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Récupérer les utilisateurs avec filtres, pagination et total des points
$sql = "
    SELECT u.user_id, u.username, u.email, u.first_name, u.last_name, u.is_admin, u.created_at,
           COALESCE(SUM(lp.points), 0) as total_points
    FROM users u
    LEFT JOIN loyalty_points lp ON u.user_id = lp.user_id
    WHERE 1=1
";
$params = [];
if (isset($filters['username'])) {
    $sql .= " AND u.username LIKE :username";
    $params[':username'] = '%' . $filters['username'] . '%';
}
if (isset($filters['is_admin'])) {
    $sql .= " AND u.is_admin = :is_admin";
    $params[':is_admin'] = $filters['is_admin'];
}
$sql .= " GROUP BY u.user_id ORDER BY u.created_at DESC LIMIT :offset, :items_per_page";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':items_per_page', $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les données d'un utilisateur spécifique pour modification
$edit_user = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare("
        SELECT u.*, COALESCE(SUM(lp.points), 0) as total_points 
        FROM users u 
        LEFT JOIN loyalty_points lp ON u.user_id = lp.user_id 
        WHERE u.user_id = :user_id
        GROUP BY u.user_id
    ");
    $stmt->execute([':user_id' => $edit_id]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Titre de la page
$page_title = "Gestion des utilisateurs - Admin Bander-Sneakers";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Gestion des utilisateurs</h1>
            <p>Gérez les comptes utilisateurs et leurs points de fidélité.</p>
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
            <h2><?= $edit_user ? 'Modifier l\'utilisateur' : 'Ajouter un utilisateur' ?></h2>
            <form action="users.php" method="POST" class="admin-form">
                <?php if ($edit_user): ?>
                    <input type="hidden" name="user_id" value="<?= $edit_user['user_id'] ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="username">Nom d'utilisateur *</label>
                    <input type="text" name="username" id="username" value="<?= $edit_user ? htmlspecialchars($edit_user['username']) : '' ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" name="email" id="email" value="<?= $edit_user ? htmlspecialchars($edit_user['email']) : '' ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe <?= $edit_user ? '(laisser vide pour ne pas modifier)' : '*' ?></label>
                    <input type="password" name="password" id="password" <?= $edit_user ? '' : 'required' ?>>
                </div>
                <div class="form-group">
                    <label for="first_name">Prénom</label>
                    <input type="text" name="first_name" id="first_name" value="<?= $edit_user ? htmlspecialchars($edit_user['first_name']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="last_name">Nom</label>
                    <input type="text" name="last_name" id="last_name" value="<?= $edit_user ? htmlspecialchars($edit_user['last_name']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="points">Ajouter des points (laisser vide pour aucun changement)</label>
                    <input type="number" name="points" id="points" min="0" value="" placeholder="0">
                    <?php if ($edit_user): ?>
                        <small class="form-text text-muted">Points actuels : <?= $edit_user['total_points'] ?></small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_admin" <?= $edit_user && $edit_user['is_admin'] ? 'checked' : '' ?>>
                        Administrateur
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $edit_user ? 'Mettre à jour' : 'Ajouter' ?></button>
                    <?php if ($edit_user): ?>
                        <a href="users.php" class="btn btn-secondary">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="admin-filters">
            <form action="users.php" method="GET" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="username">Nom d'utilisateur</label>
                        <input type="text" id="username" name="username" value="<?= isset($filters['username']) ? htmlspecialchars($filters['username']) : '' ?>">
                    </div>
                    <div class="filter-group">
                        <label for="is_admin">Rôle</label>
                        <select id="is_admin" name="is_admin">
                            <option value="">Tous</option>
                            <option value="1" <?= isset($filters['is_admin']) && $filters['is_admin'] == 1 ? 'selected' : '' ?>>Administrateurs</option>
                            <option value="0" <?= isset($filters['is_admin']) && $filters['is_admin'] == 0 ? 'selected' : '' ?>>Utilisateurs</option>
                        </select>
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <a href="users.php" class="btn btn-secondary">Réinitialiser</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="admin-table-container">
            <?php if (empty($users)): ?>
                <div class="no-results">
                    <p>Aucun utilisateur trouvé.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><a href="?order_by=id&order_dir=<?php echo ($order_by == 'id' && $order_dir == 'ASC') ? 'desc' : 'asc'; ?>">ID</a></th>
                            <th>Nom d'utilisateur</th>
                            <th><a href="?order_by=email&order_dir=<?php echo ($order_by == 'email' && $order_dir == 'ASC') ? 'desc' : 'asc'; ?>">Email</a></th>
                            <th><a href="?order_by=prenom&order_dir=<?php echo ($order_by == 'prenom' && $order_dir == 'ASC') ? 'desc' : 'asc'; ?>">Prénom</a></th>
                            <th><a href="?order_by=nom&order_dir=<?php echo ($order_by == 'nom' && $order_dir == 'ASC') ? 'desc' : 'asc'; ?>">Nom</a></th>
                            <th>Points</th>
                            <th>Admin</th>
                            <th>Date de création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['user_id'] ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['first_name']) ?></td>
                                <td><?= htmlspecialchars($user['last_name']) ?></td>
                                <td><?= $user['total_points'] ?></td>
                                <td><?= $user['is_admin'] ? 'Oui' : 'Non' ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                <td class="actions-cell">
                                    <a href="users.php?edit=<?= $user['user_id'] ?>" class="btn-action" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="users.php?delete=<?= $user['user_id'] ?>" class="btn-action delete-btn" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
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
    return 'users.php?' . http_build_query($query);
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
        color: rgb(0, 0, 0);
        text-decoration: none;
    }
    .btn-action:hover {
        color: #c0392b;
    }
    .delete-btn {
        color: rgb(0, 0, 0);
    }
    .delete-btn:hover {
        color: #c0392b;
    }
</style>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>