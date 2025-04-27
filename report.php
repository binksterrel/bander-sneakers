<?php
session_start();

if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Vous devez être connecté pour signaler un contenu.";
    header('Location: login.php');
    exit();
}

require_once 'includes/config.php';
require_once 'includes/functions.php';

$db = getDbConnection();
$errors = [];
$success = false;

// Récupérer les paramètres GET pour pré-remplir le formulaire
$type = $_GET['type'] ?? '';
$item_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$item_title = '';

// Vérifier l'élément signalé dès le chargement de la page
if ($type && $item_id) {
    try {
        if ($type === 'secondhand') {
            $stmt = $db->prepare("SELECT title, user_id FROM secondhand_products WHERE id = :id AND statut = 'actif'");
            $stmt->execute([':id' => $item_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($item) {
                $item_title = htmlspecialchars($item['title']);
                $reported_user_id = $item['user_id'];
            } else {
                $errors[] = "L'annonce signalée n'existe pas ou n'est plus active.";
            }
        } elseif ($type === 'review') {
            $stmt = $db->prepare("SELECT review_text, user_id FROM reviews WHERE review_id = :id");
            $stmt->execute([':id' => $item_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($item) {
                $item_title = htmlspecialchars(substr($item['review_text'], 0, 50)) . '...';
                $reported_user_id = $item['user_id'];
            } else {
                $errors[] = "L'avis signalé n'existe pas.";
            }
        } else {
            $errors[] = "Type de signalement invalide.";
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de l'élément : " . $e->getMessage());
        $errors[] = "Une erreur est survenue lors de la vérification de l'élément.";
    }
} else {
    $errors[] = "Aucun élément à signaler spécifié.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $item_id = isset($_POST['item_id']) && is_numeric($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $reason = cleanInput($_POST['reason'] ?? '');

    // Validation des données POST
    if (!in_array($type, ['secondhand', 'review'])) {
        $errors[] = "Type de signalement invalide.";
    }
    if ($item_id <= 0) {
        $errors[] = "ID de l'élément invalide.";
    }
    if (empty($reason)) {
        $errors[] = "Veuillez indiquer une raison pour le signalement.";
    }

    // Vérifier si l'élément existe et récupérer l'utilisateur signalé
    $reported_user_id = 0;
    $item_title = '';
    if (empty($errors)) {
        try {
            if ($type === 'secondhand') {
                $stmt = $db->prepare("SELECT id, user_id, title FROM secondhand_products WHERE id = :id AND statut = 'actif'");
            } else {
                $stmt = $db->prepare("SELECT review_id, user_id, review_text FROM reviews WHERE review_id = :id");
            }
            $stmt->execute([':id' => $item_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                $errors[] = "L'élément signalé n'existe pas ou n'est plus disponible.";
            } else {
                $reported_user_id = $item['user_id'];
                $item_title = $type === 'secondhand' ? $item['title'] : substr($item['review_text'], 0, 50) . '...';
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification POST : " . $e->getMessage());
            $errors[] = "Une erreur est survenue lors de la vérification de l'élément.";
        }
    }

    // Insérer le signalement et ajouter une notification
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Insérer le signalement
            $stmt = $db->prepare("
                INSERT INTO reports (user_id, reported_user_id, type, item_id, reason, created_at) 
                VALUES (:user_id, :reported_user_id, :type, :item_id, :reason, NOW())
            ");
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':reported_user_id' => $reported_user_id,
                ':type' => $type,
                ':item_id' => $item_id,
                ':reason' => $reason
            ]);

            // Ajouter une notification pour l'utilisateur signalé
            $notification_message = "⚠️ Votre " . ($type === 'secondhand' ? "annonce" : "avis") . " '" . htmlspecialchars($item_title) . "' a été signalé pour : " . htmlspecialchars($reason);
            $notification_added = addNotification($reported_user_id, $notification_message, 'report', $item_id);

            if ($notification_added) {
                $db->commit();
                $success = true;
                $_SESSION['success_message'] = "Signalement envoyé avec succès. Merci de votre contribution !";
                header("Location: " . ($type === 'secondhand' ? "2ndhand-detail.php?id=$item_id" : "index.php"));
                exit;
            } else {
                $db->rollBack();
                $errors[] = "Erreur lors de l'ajout de la notification pour l'utilisateur signalé.";
            }
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Erreur lors de l'insertion du signalement : " . $e->getMessage());
            $errors[] = "Une erreur est survenue lors de l'envoi du signalement.";
        }
    }
}

$page_title = "Signaler un contenu - Bander-Sneakers";
$page_description = "Signalez un contenu inapproprié sur Bander-Sneakers.";
include 'includes/header.php';
?>

<section class="auth-section">
    <div class="container">
        <div class="auth-container">
            <div class="auth-form-container">
                <h1 class="auth-title">Signaler un contenu</h1>

                <?php if ($success): ?>
                    <div class="alert alert-success">Signalement envoyé avec succès. Merci de votre contribution !</div>
                <?php elseif (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$success && !empty($type) && !empty($item_id)): ?>
                    <form action="report.php" method="POST" class="auth-form">
                        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                        <input type="hidden" name="item_id" value="<?= $item_id ?>">

                        <div class="form-group">
                            <label>Élément signalé :</label>
                            <p><?= $item_title ?: 'Non spécifié' ?></p>
                        </div>

                        <div class="form-group">
                            <label for="reason">Raison du signalement <span class="required">*</span></label>
                            <textarea id="reason" name="reason" rows="5" required placeholder="Expliquez pourquoi vous signalez cet élément (ex. contenu inapproprié, fraude, etc.)"></textarea>
                            <p class="form-hint">Soyez précis pour aider les modérateurs à traiter votre signalement.</p>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Confirmez-vous ce signalement ?');">Envoyer le signalement</button>
                            <a href="<?= $type === 'secondhand' ? '2ndhand-detail.php?id=' . $item_id : 'index.php' ?>" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="auth-links">
                    <p>Retour à la page précédente ? 
                        <a href="<?= $type === 'secondhand' ? '2ndhand-detail.php?id=' . $item_id : 'index.php' ?>">
                            <?= $type === 'secondhand' ? "Retour à l'annonce" : "Retour à l'accueil" ?>
                        </a>
                    </p>
                </div>
            </div>

            <div class="auth-sidebar">
                <div class="auth-info">
                    <h2>Pourquoi signaler ?</h2>
                    <p>Signalez un contenu si :</p>
                    <ul>
                        <li>Il contient des propos inappropriés ou offensants.</li>
                        <li>Il semble frauduleux ou trompeur.</li>
                        <li>Il viole les règles de la plateforme.</li>
                    </ul>
                    <p>Votre signalement sera examiné par notre équipe de modération.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    .form-group label.required:after {
        content: " *";
        color: #dc3545;
    }
    .form-hint {
        font-size: 0.9em;
        color: #666;
        margin-top: 5px;
    }
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
</style>

<?php include 'includes/footer.php'; ?>