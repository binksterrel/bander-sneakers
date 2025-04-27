<?php
// Page de gestion des paramètres
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

// Mettre à jour les paramètres
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = cleanInput($_POST['site_name']);
    $contact_email = cleanInput($_POST['contact_email']);
    $items_per_page = (int)$_POST['items_per_page'];
    $currency = cleanInput($_POST['currency']);

    if (empty($site_name) || empty($contact_email) || $items_per_page <= 0 || empty($currency)) {
        $error_message = "Tous les champs sont obligatoires et le nombre d'éléments par page doit être supérieur à 0.";
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "L'email de contact doit être valide.";
    } else {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("UPDATE settings SET setting_value = :value WHERE setting_key = :key");
            
            $stmt->execute([':value' => $site_name, ':key' => 'site_name']);
            $stmt->execute([':value' => $contact_email, ':key' => 'contact_email']);
            $stmt->execute([':value' => $items_per_page, ':key' => 'items_per_page']);
            $stmt->execute([':value' => $currency, ':key' => 'currency']);

            $db->commit();
            $success_message = "Paramètres mis à jour avec succès.";
        } catch (PDOException $e) {
            $db->rollBack();
            $error_message = "Erreur lors de la mise à jour des paramètres : " . $e->getMessage();
            error_log("Erreur PDO dans settings : " . $e->getMessage());
        }
    }
}

// Récupérer les paramètres actuels
$settings = [];
$stmt = $db->query("SELECT setting_key, setting_value, setting_description FROM settings");
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['setting_key']] = [
        'value' => $row['setting_value'],
        'description' => $row['setting_description']
    ];
}

// Titre de la page
$page_title = "Paramètres - Admin Bander-Sneakers";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Paramètres</h1>
            <p>Gérez les paramètres généraux du site.</p>
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
            <form action="settings.php" method="POST" class="admin-form">
                <div class="form-group">
                    <label for="site_name">Nom du site *</label>
                    <input type="text" name="site_name" id="site_name" value="<?= htmlspecialchars($settings['site_name']['value']) ?>" required>
                    <small><?= htmlspecialchars($settings['site_name']['description']) ?></small>
                </div>
                <div class="form-group">
                    <label for="contact_email">Email de contact *</label>
                    <input type="email" name="contact_email" id="contact_email" value="<?= htmlspecialchars($settings['contact_email']['value']) ?>" required>
                    <small><?= htmlspecialchars($settings['contact_email']['description']) ?></small>
                </div>
                <div class="form-group">
                    <label for="items_per_page">Éléments par page *</label>
                    <input type="number" name="items_per_page" id="items_per_page" min="1" value="<?= htmlspecialchars($settings['items_per_page']['value']) ?>" required>
                    <small><?= htmlspecialchars($settings['items_per_page']['description']) ?></small>
                </div>
                <div class="form-group">
                    <label for="currency">Devise *</label>
                    <input type="text" name="currency" id="currency" value="<?= htmlspecialchars($settings['currency']['value']) ?>" required>
                    <small><?= htmlspecialchars($settings['currency']['description']) ?></small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                </div>
            </form>
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
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group small {
        display: block;
        color: #666;
        font-size: 0.9em;
        margin-top: 0.3rem;
    }
    .form-actions {
        display: flex;
        gap: 0.5rem;
    }
</style>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>