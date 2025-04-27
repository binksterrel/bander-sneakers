<?php
// Page de connexion
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Si l'utilisateur est déjà connecté, le rediriger vers la page d'accueil
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error_message = '';
$success_message = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier que tous les champs sont remplis
    if (!isset($_POST['email']) || empty($_POST['email']) || !isset($_POST['password']) || empty($_POST['password'])) {
        $error_message = 'Veuillez remplir tous les champs.';
    } else {
        $email = cleanInput($_POST['email']);
        $password = $_POST['password']; // Ne pas nettoyer le mot de passe avant la vérification

        try {
            $db = getDbConnection();

            // Récupérer l'utilisateur par son email
            $stmt = $db->prepare('SELECT user_id, username, email, password, is_admin FROM users WHERE email = :email');
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Connexion réussie
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['is_admin'] = (bool)$user['is_admin'];

                // Rediriger vers la page d'accueil ou la page précédente si disponible
                $redirect_url = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'index.php';
                unset($_SESSION['redirect_after_login']);

                header('Location: ' . $redirect_url);
                exit();
            } else {
                // Connexion échouée
                $error_message = 'Email ou mot de passe incorrect.';
            }
        } catch (PDOException $e) {
            $error_message = 'Une erreur est survenue. Veuillez réessayer plus tard.';
            // Enregistrer l'erreur dans un fichier log
            error_log('Erreur PDO: ' . $e->getMessage());
        }
    }
}

// Récupérer l'URL de redirection si elle existe
if (isset($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}

// Afficher les messages d'erreur ou de succès de la session, puis les supprimer
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Titre et description de la page
$page_title = "Connexion | Bander-Sneakers";
$page_description = "Connectez-vous à votre compte Bander-Sneakers pour accéder à votre profil, vos commandes et vos listes de souhaits.";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">Connexion</li>
        </ul>
    </div>
</div>

<!-- Login Section -->
<section class="auth-section">
    <div class="container">
        <div class="auth-container">
            <div class="auth-form-container">
                <h1 class="auth-title">Connexion</h1>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <?= $error_message ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <?= $success_message ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" name="password" id="password" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Se connecter</button>
                    </div>
                </form>

                <div class="auth-links">
                    <a href="forgot-password.php">Mot de passe oublié ?</a>
                    <span class="separator">|</span>
                    <a href="register.php">Créer un compte</a>
                </div>
            </div>

            <div class="auth-sidebar">
                <div class="auth-info">
                    <h2>Nouveau sur Bander-Sneakers ?</h2>
                    <p>Créez un compte pour bénéficier des avantages suivants :</p>
                    <ul>
                        <li>Suivre vos commandes</li>
                        <li>Gérer votre liste de souhaits</li>
                        <li>Accéder à vos informations personnelles</li>
                        <li>Bénéficier d'offres exclusives</li>
                    </ul>

                    <a href="register.php" class="btn btn-outline">S'inscrire</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>
