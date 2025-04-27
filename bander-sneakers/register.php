<?php
session_start();
require_once 'includes/config.php'; // Inclut DB_HOST, DB_USER, DB_PASS, DB_NAME et getDbConnection()
require_once 'includes/functions.php'; // Inclut isLoggedIn(), cleanInput(), etc.
require 'vendor/autoload.php'; // Charge PHPMailer via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Si l'utilisateur est déjà connecté, rediriger vers la page d'accueil
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error_message = '';
$success_message = '';

// Fonction pour envoyer l'email de confirmation
function sendConfirmationEmail($email, $username) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'bander.sneakers@gmail.com';
        $mail->Password = 'kpeeqikaqfkanpbd'; // Mot de passe d'application Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Destinataire
        $mail->setFrom('bander.sneakers@gmail.com', 'Bander Sneakers');
        $mail->addAddress($email);

        // Format HTML du mail
        $mail->isHTML(true);
        $mail->Subject = '🔥 Bienvenue dans la Crew, ' . htmlspecialchars($username) . ' - Bander Sneakers 🔥';
        $mail->Body = '
            <html>
                <body style="font-family: Arial, sans-serif; color: #333; background-color: #fff; margin: 0; padding: 0;">
                    <div style="max-width: 600px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 0 15px rgba(211, 47, 47, 0.2);">
                        <h1 style="text-align: center; font-size: 32px; color: #D32F2F; text-transform: uppercase; letter-spacing: 2px; text-shadow: 0 0 5px rgba(211, 47, 47, 0.5);">
                            Yo <span style="color: #000;">' . htmlspecialchars($username) . '</span> ! 🔥
                        </h1>
                        <p style="text-align: center; font-size: 18px; color: #555; margin: 10px 0;">
                            T’es officiellement dans la <strong style="color: #D32F2F;">Crew Bander-Sneakers</strong>. Le game des kicks est ON !
                        </p>
                        <div style="margin: 20px 0; text-align: center;">
                            <img src="https://via.placeholder.com/500x200/D32F2F/fff?text=Bander+Sneakers+-+Welcome+to+the+Game" 
                                 alt="Bander Sneakers Drop" 
                                 style="max-width: 100%; border-radius: 8px; border: 2px solid #D32F2F;">
                        </div>
                        <p style="font-size: 16px; line-height: 1.5; color: #444; text-align: center;">
                            Pas de blabla, ici c’est du sérieux. T’as débloqué les <strong style="color: #D32F2F;">drops exclusifs</strong>, les <strong style="color: #D32F2F;">restocks secrets</strong>, et les paires qui font baver tout le monde. Lace-up, le hustle démarre NOW.
                        </p>
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="http://localhost/bander-sneakers/index.php" 
                               style="display: inline-block; padding: 15px 30px; background: #D32F2F; color: #fff; text-decoration: none; font-size: 18px; font-weight: bold; text-transform: uppercase; border-radius: 8px; box-shadow: 0 0 10px rgba(211, 47, 47, 0.5);">
                               Drop ta première paire
                            </a>
                        </div>
                        <p style="text-align: center; font-size: 14px; color: #777;">
                            Psst... Un drop limité arrive bientôt. T’es prêt ? 👀
                        </p>
                        <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #D32F2F;">
                            <p style="font-size: 14px; color: #888;">La Team <strong style="color: #D32F2F;">Bander-Sneakers</strong></p>
                            <p style="font-size: 12px; color: #999;">Pas toi ? Laisse tomber ou passe-le à un vrai sneakerhead.</p>
                        </div>
                    </div>
                </body>
            </html>';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de l'envoi de l'email : " . $mail->ErrorInfo);
        return false;
    }
}

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier que tous les champs requis sont remplis
    if (!isset($_POST['username']) || empty($_POST['username']) ||
        !isset($_POST['email']) || empty($_POST['email']) ||
        !isset($_POST['password']) || empty($_POST['password']) ||
        !isset($_POST['confirm_password']) || empty($_POST['confirm_password']) ||
        !isset($_POST['terms']) || $_POST['terms'] !== 'on') {
        $error_message = 'Veuillez remplir tous les champs obligatoires et accepter les conditions.';
    } else {
        // Récupérer et nettoyer les données
        $username = cleanInput($_POST['username']);
        $email = cleanInput($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $first_name = isset($_POST['first_name']) ? cleanInput($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? cleanInput($_POST['last_name']) : '';

        // Vérifications
        if ($password !== $confirm_password) {
            $error_message = 'Les mots de passe ne correspondent pas.';
        } elseif (strlen($password) < 8) {
            $error_message = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Veuillez entrer une adresse email valide.';
        } else {
            try {
                $db = getDbConnection();

                // Vérifier si l'email existe
                $stmt = $db->prepare('SELECT user_id FROM users WHERE email = :email');
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                if ($stmt->fetch()) {
                    $error_message = 'Cette adresse email est déjà utilisée.';
                } else {
                    // Vérifier si le nom d'utilisateur existe
                    $stmt = $db->prepare('SELECT user_id FROM users WHERE username = :username');
                    $stmt->bindParam(':username', $username);
                    $stmt->execute();
                    if ($stmt->fetch()) {
                        $error_message = 'Ce nom d\'utilisateur est déjà utilisé.';
                    } else {
                        // Hacher le mot de passe
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                        // Insérer le nouvel utilisateur
                        $stmt = $db->prepare('
                            INSERT INTO users (username, email, password, first_name, last_name)
                            VALUES (:username, :email, :password, :first_name, :last_name)
                        ');
                        $stmt->bindParam(':username', $username);
                        $stmt->bindParam(':email', $email);
                        $stmt->bindParam(':password', $hashed_password);
                        $stmt->bindParam(':first_name', $first_name);
                        $stmt->bindParam(':last_name', $last_name);
                        $stmt->execute();

                        // Récupérer l'ID de l'utilisateur créé
                        $userId = $db->lastInsertId();

                        // Envoyer l'email de confirmation
                        if (sendConfirmationEmail($email, $username)) {
                            $success_message = 'Inscription réussie ! Un email de confirmation vous a été envoyé.';
                        } else {
                            $success_message = 'Inscription réussie ! (Note : l\'email de confirmation n\'a pas pu être envoyé)';
                        }

                        // Connecter automatiquement l'utilisateur
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['username'] = $username;
                        $_SESSION['email'] = $email;
                        $_SESSION['is_admin'] = false;

                        // Rediriger
                        $redirect_url = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : SITE_URL . '/index.php';
                        unset($_SESSION['redirect_after_login']);
                        header('Location: ' . $redirect_url);
                        exit();
                    }
                }
            } catch (PDOException $e) {
                $error_message = 'Une erreur est survenue. Veuillez réessayer plus tard.';
                error_log('Erreur PDO : ' . $e->getMessage());
            }
        }
    }
}

// Récupérer l'URL de redirection si elle existe
if (isset($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}

// Titre et description de la page
$page_title = "Inscription | " . SITE_NAME;
$page_description = "Créez votre compte " . SITE_NAME . " pour profiter de nos offres exclusives et suivre vos commandes.";

// Inclure l'en-tête
include INCLUDES_PATH . 'header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="<?= SITE_URL ?>/index.php">Accueil</a></li>
            <li class="active">Inscription</li>
        </ul>
    </div>
</div>

<!-- Register Section -->
<section class="auth-section">
    <div class="container">
        <div class="auth-container">
            <div class="auth-form-container">
                <h1 class="auth-title">Créer un compte</h1>

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

                <form action="register.php" method="POST" class="auth-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">Prénom</label>
                            <input type="text" name="first_name" id="first_name" value="<?= isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '' ?>">
                        </div>

                        <div class="form-group">
                            <label for="last_name">Nom</label>
                            <input type="text" name="last_name" id="last_name" value="<?= isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '' ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="username">Nom d'utilisateur <span class="required">*</span></label>
                        <input type="text" name="username" id="username" required value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" name="email" id="email" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">Mot de passe <span class="required">*</span></label>
                        <input type="password" name="password" id="password" required>
                        <p class="form-hint">Le mot de passe doit contenir au moins 8 caractères.</p>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmer le mot de passe <span class="required">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password" required>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="terms" required>
                            J'accepte les <a href="terms-conditions.php">conditions générales</a> et la <a href="privacy-policy.php">politique de confidentialité</a>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">S'inscrire</button>
                    </div>
                </form>

                <div class="auth-links">
                    <p>Vous avez déjà un compte ? <a href="login.php">Connectez-vous</a></p>
                </div>
            </div>

            <div class="auth-sidebar">
                <div class="auth-info">
                    <h2>Avantages de créer un compte</h2>
                    <ul>
                        <li>Suivre l'état de vos commandes</li>
                        <li>Gérer votre liste de souhaits</li>
                        <li>Sauvegarder vos adresses de livraison</li>
                        <li>Accès plus rapide au processus de commande</li>
                        <li>Recevoir des offres exclusives par email</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Inclure le pied de page
include INCLUDES_PATH . 'footer.php';
?>