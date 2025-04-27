<?php
// Page d'inscription à la newsletter
session_start();

// Inclure la configuration et les fonctions
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Initialiser les variables
$db = getDbConnection();
$success_message = '';
$error_message = '';

// Récupérer les messages de session (si redirection depuis une autre page)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Vérifier si la table newsletter_subscribers existe
try {
    $db->query("SELECT 1 FROM newsletter_subscribers LIMIT 1");
} catch (PDOException $e) {
    if ($e->getCode() === '42S02') { // Code d'erreur pour table manquante
        $error_message = "Erreur : La table 'newsletter_subscribers' n'existe pas dans la base de données. Veuillez contacter l'administrateur.";
        error_log("Table newsletter_subscribers manquante : " . $e->getMessage());
    } else {
        $error_message = "Erreur de base de données : " . $e->getMessage();
        error_log("Erreur PDO dans newsletter-subscribe : " . $e->getMessage());
    }
}

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && empty($error_message)) {
    $email = cleanInput($_POST['email']);

    // Vérifier si l'email est vide
    if (empty($email)) {
        $error_message = "L'email ne peut pas être vide.";
    }
    // Vérifier le format de l'email
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "L'email n'est pas valide.";
    }
    else {
        try {
            // Vérifier si l'email existe déjà
            $stmt = $db->prepare("SELECT COUNT(*) FROM newsletter_subscribers WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $email_exists = $stmt->fetchColumn();

            if ($email_exists) {
                $error_message = "Cet email est déjà inscrit à la newsletter.";
            } else {
                // Insérer l'email dans la table
                $stmt = $db->prepare("INSERT INTO newsletter_subscribers (email, subscribed_at, is_active) VALUES (:email, NOW(), 1)");
                $stmt->execute([':email' => $email]);

                $success_message = "Inscription réussie ! Merci de vous être abonné à notre newsletter.";
            }
        } catch (PDOException $e) {
            $error_message = "Erreur lors de l'inscription : " . $e->getMessage();
            error_log("Erreur PDO dans newsletter-subscribe : " . $e->getMessage());
        }
    }
}

// Titre et description de la page
$page_title = "Inscription à la newsletter | Bander-Sneakers";
$page_description = "Inscrivez-vous à la newsletter de Bander-Sneakers pour recevoir des offres exclusives et des mises à jour sur nos produits.";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">Inscription à la newsletter</li>
        </ul>
    </div>
</div>

<!-- Newsletter Subscription Section -->
<section class="auth-section">
    <div class="container">
        <div class="auth-container">
            <div class="auth-form-container">
                <h1 class="auth-title">Inscription à la newsletter</h1>

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

                <?php if (empty($error_message) || !str_contains($error_message, "table 'newsletter_subscribers' n'existe pas")): ?>
                    <form action="newsletter-subscribe.php" method="POST" class="auth-form">
                        <div class="form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <input type="email" name="email" id="email" placeholder="Entrez votre email" required>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="terms" required>
                                J'accepte la <a href="privacy-policy.php">‎ politique de confidentialité</a>
                            </label>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">S'inscrire</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="auth-sidebar">
                <div class="auth-info">
                    <h2>Avantages de la newsletter</h2>
                    <ul>
                        <li>Recevoir des offres exclusives</li>
                        <li>Être informé des nouvelles collections</li>
                        <li>Participer à des ventes privées</li>
                        <li>Obtenir des codes de réduction</li>
                        <li>Rester à jour sur les tendances</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>