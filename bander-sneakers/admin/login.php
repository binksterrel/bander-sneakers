<?php
// Page de connexion pour l'administration
session_start();

// Inclure la configuration et les fonctions
require_once '../includes/config.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['email']) || empty($_POST['email']) || !isset($_POST['password']) || empty($_POST['password'])) {
        $error_message = 'Veuillez remplir tous les champs.';
    } else {
        $email = cleanInput($_POST['email']);
        $password = $_POST['password'];

        try {
            $db = getDbConnection();
            $stmt = $db->prepare('SELECT user_id, username, email, password, is_admin FROM users WHERE email = :email AND is_admin = 1');
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch();

            if ($user) {
                // Test temporaire
                $stored_hash = $user['password'];
                if (password_verify($password, $stored_hash)) {
                    echo "Mot de passe correct !";
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['is_admin'] = true;
                    header('Location: index.php');
                    exit();
                } else {
                    $error_message = 'Mot de passe incorrect. Hachage stocké : ' . $stored_hash;
                }
            } else {
                $error_message = 'Utilisateur non trouvé ou pas admin.';
            }
        } catch (PDOException $e) {
            $error_message = 'Erreur PDO : ' . $e->getMessage();
        }
    }
}
// Si l'utilisateur est déjà connecté et est admin, le rediriger vers le tableau de bord
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: index.php');
    exit();
}

$error_message = '';

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

            // Récupérer l'utilisateur par son email et vérifier qu'il est admin
            $stmt = $db->prepare('SELECT user_id, username, email, password, is_admin FROM users WHERE email = :email AND is_admin = 1');
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Connexion réussie
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['is_admin'] = true;

                // Rediriger vers le tableau de bord admin
                header('Location: index.php');
                exit();
            } else {
                // Connexion échouée
                $error_message = 'Email ou mot de passe incorrect, ou vous n\'avez pas les droits d\'administration.';
            }
        } catch (PDOException $e) {
            $error_message = 'Une erreur est survenue. Veuillez réessayer plus tard.';
            // Enregistrer l'erreur dans un fichier log
            error_log('Erreur PDO: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Bander-Sneakers</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="admin-login-container">
        <div class="admin-login-header">
            <h1>Bander-Sneakers</h1>
            <h2>Administration</h2>
        </div>

        <div class="admin-login-form-container">
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?= $error_message ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="admin-login-form">
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

            <div class="admin-login-footer">
                <a href="../index.php">Retour au site</a>
            </div>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>
