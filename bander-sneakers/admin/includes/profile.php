<?php
// Page de gestion du profil administrateur
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
$user_id = $_SESSION['user_id'];

// Récupérer les informations actuelles de l'utilisateur
$stmt = $db->prepare("SELECT username, email FROM users WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $error_message = "Utilisateur non trouvé.";
    exit();
}

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username']);
    $email = cleanInput($_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation des champs
    if (empty($username) || empty($email)) {
        $error_message = "Le nom d'utilisateur et l'email sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "L'email doit être valide.";
    } else {
        try {
            $db->beginTransaction();

            // Vérifier le mot de passe actuel si un nouveau mot de passe est fourni
            if (!empty($new_password) || !empty($confirm_password)) {
                if (empty($current_password)) {
                    $error_message = "Veuillez entrer votre mot de passe actuel pour modifier le mot de passe.";
                } else {
                    $stmt = $db->prepare("SELECT password FROM users WHERE user_id = :user_id");
                    $stmt->execute([':user_id' => $user_id]);
                    $stored_password = $stmt->fetchColumn();

                    if (!password_verify($current_password, $stored_password)) {
                        $error_message = "Mot de passe actuel incorrect.";
                    } elseif ($new_password !== $confirm_password) {
                        $error_message = "Les nouveaux mots de passe ne correspondent pas.";
                    } elseif (strlen($new_password) < 8) {
                        $error_message = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
                    } else {
                        // Mettre à jour le mot de passe
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET password = :password WHERE user_id = :user_id");
                        $stmt->execute([':password' => $hashed_password, ':user_id' => $user_id]);
                    }
                }
            }

            // Si pas d'erreur, mettre à jour username et email
            if (empty($error_message)) {
                $stmt = $db->prepare("UPDATE users SET username = :username, email = :email WHERE user_id = :user_id");
                $stmt->execute([
                    ':username' => $username,
                    ':email' => $email,
                    ':user_id' => $user_id
                ]);

                // Mettre à jour la session
                $_SESSION['username'] = $username;

                $db->commit();
                $success_message = "Profil mis à jour avec succès.";
            } else {
                $db->rollBack();
            }
        } catch (PDOException $e) {
            $db->rollBack();
            $error_message = "Erreur lors de la mise à jour du profil : " . $e->getMessage();
            error_log("Erreur PDO dans profile : " . $e->getMessage());
        }
    }
}

// Titre de la page
$page_title = "Profil - Admin Bander-Sneakers";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="admin-content">
    <div class="container-fluid">
        <div class="admin-header">
            <h1>Mon Profil</h1>
            <p>Gérez vos informations personnelles.</p>
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
            <form action="profile.php" method="POST" class="admin-form">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur *</label>
                    <input type="text" name="username" id="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>

                <hr>

                <h3>Modifier le mot de passe</h3>
                <div class="form-group">
                    <label for="current_password">Mot de passe actuel</label>
                    <input type="password" name="current_password" id="current_password" placeholder="Entrez votre mot de passe actuel">
                    <small>Laissez vide si vous ne voulez pas changer le mot de passe.</small>
                </div>

                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe</label>
                    <input type="password" name="new_password" id="new_password" placeholder="Minimum 8 caractères">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Répétez le nouveau mot de passe">
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
    hr {
        margin: 2rem 0;
        border: 0;
        border-top: 1px solid #ddd;
    }
    h3 {
        margin-bottom: 1rem;
        font-size: 1.25rem;
    }
</style>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>