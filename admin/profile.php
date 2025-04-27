<?php
// Page de profil administrateur
session_start();

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Inclure la configuration et les fonctions
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Initialiser les variables
$db = getDbConnection();
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Messages de session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Récupérer les informations de l'utilisateur
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error_message'] = "Erreur : utilisateur non trouvé.";
    header("Location: products.php");
    exit();
}

// Statistiques pour le tableau de bord
$total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_orders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$total_products = $db->query("SELECT COUNT(*) FROM sneakers")->fetchColumn();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = cleanInput($_POST['first_name'] ?? '');
        $last_name = cleanInput($_POST['last_name'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        $phone = cleanInput($_POST['phone'] ?? '');
        $address = cleanInput($_POST['address'] ?? '');
        $city = cleanInput($_POST['city'] ?? '');
        $postal_code = cleanInput($_POST['postal_code'] ?? '');
        $country = cleanInput($_POST['country'] ?? '');

        if (empty($email)) {
            $error_message = "L'email est requis.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Email invalide.";
        } elseif ($email !== $user['email']) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = "Cet email est déjà utilisé.";
            }
        }

        if (empty($error_message)) {
            try {
                $stmt = $db->prepare("
                    UPDATE users SET
                    first_name = ?, last_name = ?, email = ?, phone = ?,
                    address = ?, city = ?, postal_code = ?, country = ?,
                    updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $first_name, $last_name, $email, $phone,
                    $address, $city, $postal_code, $country,
                    $user_id
                ]);

                $success_message = "Profil mis à jour avec succès.";
                $user = array_merge($user, [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'city' => $city,
                    'postal_code' => $postal_code,
                    'country' => $country
                ]);
            } catch (PDOException $e) {
                $error_message = "Erreur lors de la mise à jour.";
                error_log("Erreur dans profile.php : " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "Tous les champs sont requis.";
        } elseif (!password_verify($current_password, $user['password'])) {
            $error_message = "Mot de passe actuel incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Les nouveaux mots de passe ne correspondent pas.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Le mot de passe doit contenir au moins 6 caractères.";
        }

        if (empty($error_message)) {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                $success_message = "Mot de passe mis à jour avec succès.";
            } catch (PDOException $e) {
                $error_message = "Erreur lors de la mise à jour du mot de passe.";
                error_log("Erreur dans profile.php : " . $e->getMessage());
            }
        }
    }
}

// Titre de la page
$page_title = "Profil Admin - Bander-Sneakers";

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="products.php">Gestion des produits</a></li>
            <li class="active">Mon Profil</li>
        </ul>
    </div>
</div>

<!-- Profile Section -->
<section class="account-section">
    <div class="container">
        <h1 class="section-title">Mon Profil Administrateur</h1>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= $error_message ?></div>
        <?php endif; ?>

        <div class="account-container">
            <!-- Sidebar -->
            <div class="account-sidebar">
                <div class="account-user">
                    <div class="user-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="user-info">
                        <h3><?= htmlspecialchars($user['first_name'] ? $user['first_name'] . ' ' . $user['last_name'] : $user['username']) ?></h3>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                </div>
                <ul class="account-nav">
                    <li class="active"><a href="#dashboard" data-tab="dashboard">Tableau de bord</a></li>
                    <li><a href="#profile" data-tab="profile">Informations personnelles</a></li>
                    <li><a href="#password" data-tab="password">Changer de mot de passe</a></li>
                    <li><a href="products.php">Retour aux produits</a></li>
                    <li><a href="../logout.php">Déconnexion</a></li>
                </ul>
            </div>

            <!-- Content -->
            <div class="account-content">
                <!-- Dashboard Tab -->
                <div id="dashboard" class="account-tab active">
                    <h2>Tableau de bord</h2>
                    <p>Bienvenue dans votre espace administrateur, <?= htmlspecialchars($user['first_name'] ?? $user['username']) ?>.</p>
                    <div class="dashboard-stats">
                        <div class="dashboard-stat">
                            <i class="fas fa-users"></i>
                            <div class="stat-content">
                                <span class="stat-value"><?= $total_users ?></span>
                                <span class="stat-label">Utilisateurs</span>
                            </div>
                        </div>
                        <div class="dashboard-stat">
                            <i class="fas fa-shopping-bag"></i>
                            <div class="stat-content">
                                <span class="stat-value"><?= $total_orders ?></span>
                                <span class="stat-label">Commandes</span>
                            </div>
                        </div>
                        <div class="dashboard-stat">
                            <i class="fas fa-box"></i>
                            <div class="stat-content">
                                <span class="stat-value"><?= $total_products ?></span>
                                <span class="stat-label">Produits</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Tab -->
                <div id="profile" class="account-tab">
                    <h2>Informations personnelles</h2>
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="profile-intro">
                            <h3>Bonjour, <?= htmlspecialchars($user['first_name'] ?? $user['username']) ?></h3>
                            <p>Mettez à jour vos informations personnelles</p>
                        </div>
                    </div>

                    <form action="profile.php" method="POST" class="profile-form enhanced">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="form-section">
                            <h3><i class="fas fa-user-edit"></i> Informations de base</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name">Prénom</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-user input-icon"></i>
                                        <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" placeholder="Votre prénom">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Nom</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-user input-icon"></i>
                                        <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" placeholder="Votre nom">
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email">Email <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-envelope input-icon"></i>
                                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required placeholder="Votre email">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Téléphone</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-phone input-icon"></i>
                                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="Votre téléphone">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-section">
                            <h3><i class="fas fa-map-marker-alt"></i> Adresse</h3>
                            <div class="form-group full-width">
                                <label for="address">Adresse</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-home input-icon"></i>
                                    <input type="text" id="address" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="Votre adresse">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city">Ville</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-city input-icon"></i>
                                        <input type="text" id="city" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>" placeholder="Votre ville">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="postal_code">Code postal</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-mailbox input-icon"></i>
                                        <input type="text" id="postal_code" name="postal_code" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>" placeholder="Code postal">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="country">Pays</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-globe input-icon"></i>
                                    <select id="country" name="country">
                                        <option value="">Sélectionner un pays</option>
                                        <option value="France" <?= ($user['country'] ?? '') == 'France' ? 'selected' : '' ?>>France</option>
                                        <option value="Belgique" <?= ($user['country'] ?? '') == 'Belgique' ? 'selected' : '' ?>>Belgique</option>
                                        <option value="Suisse" <?= ($user['country'] ?? '') == 'Suisse' ? 'selected' : '' ?>>Suisse</option>
                                        <option value="Luxembourg" <?= ($user['country'] ?? '') == 'Luxembourg' ? 'selected' : '' ?>>Luxembourg</option>
                                        <option value="Canada" <?= ($user['country'] ?? '') == 'Canada' ? 'selected' : '' ?>>Canada</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions enhanced">
                            <button type="reset" class="btn btn-outline">Annuler</button>
                            <button type="submit" class="btn btn-outline"><i class="fas fa-save"></i> Enregistrer</button>
                        </div>
                    </form>
                </div>

                <!-- Password Tab -->
                <div id="password" class="account-tab">
                    <h2>Changer de mot de passe</h2>
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="profile-intro">
                            <h3>Sécurité</h3>
                            <p>Modifiez votre mot de passe</p>
                        </div>
                    </div>
                    <form action="profile.php" method="POST" class="password-form enhanced">
                        <input type="hidden" name="change_password" value="1">
                        <div class="form-section animated">
                            <h3><i class="fas fa-key"></i> Modification du mot de passe</h3>
                            <div class="form-group">
                                <label for="current_password">Mot de passe actuel <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" id="current_password" name="current_password" required placeholder="Mot de passe actuel">
                                    <span class="toggle-password" data-target="current_password"><i class="fas fa-eye"></i></span>
                                </div>
                            </div>
                            <div class="password-divider">
                                <span class="divider-line"></span>
                                <span class="divider-text">Nouveau mot de passe</span>
                                <span class="divider-line"></span>
                            </div>
                            <div class="form-group">
                                <label for="new_password">Nouveau mot de passe <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-lock-open input-icon"></i>
                                    <input type="password" id="new_password" name="new_password" required placeholder="Nouveau mot de passe">
                                    <span class="toggle-password" data-target="new_password"><i class="fas fa-eye"></i></span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirmer <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-check-circle input-icon"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirmer le mot de passe">
                                    <span class="toggle-password" data-target="confirm_password"><i class="fas fa-eye"></i></span>
                                </div>
                                <div class="password-match-indicator">
                                    <i class="fas fa-times-circle"></i>
                                    <span>Les mots de passe ne correspondent pas</span>
                                </div>
                            </div>
                        </div>
                        <div class="password-tips enhanced">
                            <div class="tips-header">
                                <i class="fas fa-shield-alt"></i>
                                <h4>Conseils pour un mot de passe fort</h4>
                            </div>
                            <div class="tips-content">
                                <ul class="tips-list">
                                    <li class="tip-item">
                                        <span class="tip-icon"><i class="fas fa-ruler"></i></span>
                                        <span class="tip-text">Utilisez au moins 8 caractères</span>
                                    </li>
                                    <li class="tip-item">
                                        <span class="tip-icon"><i class="fas fa-font"></i></span>
                                        <span class="tip-text">Combinez majuscules et minuscules</span>
                                    </li>
                                    <li class="tip-item">
                                        <span class="tip-icon"><i class="fas fa-hashtag"></i></span>
                                        <span class="tip-text">Incluez chiffres et caractères spéciaux</span>
                                    </li>
                                </ul>
                                <div class="password-strength-meter">
                                    <div class="strength-label">Force du mot de passe</div>
                                    <div class="strength-bars">
                                        <span class="strength-bar"></span>
                                        <span class="strength-bar"></span>
                                        <span class="strength-bar"></span>
                                        <span class="strength-bar"></span>
                                    </div>
                                    <div class="strength-text">Pas encore évalué</div>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions enhanced">
                            <button type="reset" class="btn btn-outline">Annuler</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Changer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Variables CSS (définies ici pour l'autonomie) */
:root {
    --white: #fff;
    --gray-light: #f5f5f5;
    --primary-color: #ff3e3e;
    --primary-dark: rgb(179, 0, 0);
    --text-color: #333;
    --text-light: #666;
    --border-color: #ddd;
    --success-color: #28a745;
    --error-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: rgb(184, 23, 23);
    --box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    --transition: all 0.3s ease;
    --primary-color-rgb: 0, 123, 255;
    --success-color-rgb: 40, 167, 69;
    --error-color-rgb: 220, 53, 69;
    --warning-color-rgb: 255, 193, 7;
    --info-color-rgb: 23, 162, 184;
}

/* Profile Section */
.account-section {
    padding: 3rem 0;
}

.account-container {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 2rem;
    margin-top: 2rem;
}

/* Sidebar */
.account-sidebar {
    background-color: var(--white);
    border-radius: 8px;
    box-shadow: var(--box-shadow);
    padding: 0;
    position: sticky;
    top: 100px;
}

.account-user {
    padding: 1.5rem;
    background-color: var(--gray-light);
    border-radius: 8px 8px 0 0;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.user-avatar {
    width: 60px;
    height: 60px;
    background-color: var(--primary-color);
    color: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
}

.user-info h3 {
    font-size: 1.2rem;
    margin-bottom: 0.25rem;
}

.user-info p {
    font-size: 0.9rem;
    color: var(--text-light);
}

.account-nav {
    list-style: none;
    padding: 1rem 0;
}

.account-nav li {
    margin-bottom: 0.25rem;
}

.account-nav li a {
    display: block;
    padding: 0.75rem 1.5rem;
    color: var(--text-color);
    transition: var(--transition);
    border-left: 3px solid transparent;
}

.account-nav li a:hover {
    background-color: rgba(var(--primary-color-rgb), 0.05);
    color: var(--primary-color);
}

.account-nav li.active a {
    background-color: rgba(var(--primary-color-rgb), 0.1);
    color: var(--primary-color);
    border-left-color: var(--primary-color);
    font-weight: 500;
}

/* Content */
.account-content {
    background-color: var(--white);
    border-radius: 8px;
    box-shadow: var(--box-shadow);
    padding: 2rem;
}

.account-tab {
    display: none;
}

.account-tab.active {
    display: block;
}

.account-tab h2 {
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border-color);
}

/* Dashboard Stats */
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.dashboard-stat {
    background-color: var(--gray-light);
    border-radius: 8px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.dashboard-stat i {
    font-size: 2rem;
    color: var(--primary-color);
}

.stat-content {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-color);
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-light);
}

/* Profile Form */
.profile-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background-color: var(--gray-light);
    border-radius: 8px;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background-color: var(--primary-color);
    color: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.profile-intro h3 {
    font-size: 1.4rem;
    margin-bottom: 0.5rem;
}

.profile-intro p {
    color: var(--text-light);
}

.profile-form.enhanced .form-section,
.password-form.enhanced .form-section {
    background-color: var(--gray-light);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.3s ease;
}

.profile-form.enhanced .form-section:hover,
.password-form.enhanced .form-section:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.profile-form.enhanced h3,
.password-form.enhanced h3 {
    font-size: 1.2rem;
    margin-bottom: 1.5rem;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.profile-form.enhanced h3 i,
.password-form.enhanced h3 i {
    font-size: 1.1rem;
}

.profile-form.enhanced .form-group,
.password-form.enhanced .form-group {
    margin-bottom: 1.5rem;
}

.profile-form.enhanced .form-group.full-width {
    grid-column: 1 / -1;
}

.profile-form.enhanced label,
.password-form.enhanced label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-color);
}

.profile-form.enhanced .input-wrapper,
.password-form.enhanced .input-wrapper {
    position: relative;
}

.profile-form.enhanced .input-icon,
.password-form.enhanced .input-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--primary-color);
    font-size: 1rem;
    opacity: 0.7;
}

.profile-form.enhanced input,
.profile-form.enhanced select,
.password-form.enhanced input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s;
    box-sizing: border-box; /* Garantit que le padding est inclus dans la hauteur */
}

.profile-form.enhanced input:focus,
.profile-form.enhanced select:focus,
.password-form.enhanced input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.1);
    outline: none;
}

.profile-form.enhanced input::placeholder,
.password-form.enhanced input::placeholder {
    color: #aaa;
}

.form-actions.enhanced {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
}

.profile-form.enhanced .btn,
.password-form.enhanced .btn {
    padding: 0.75rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.profile-form.enhanced .btn i,
.password-form.enhanced .btn i {
    font-size: 1rem;
}

.profile-form.enhanced .btn-primary,
.password-form.enhanced .btn-primary {
    background-color: var(--primary-color);
    color: var(--white);
    border: none;
}

.profile-form.enhanced .btn-primary:hover,
.password-form.enhanced .btn-primary:hover {
    background-color: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-color-rgb), 0.2);
}

.profile-form.enhanced .btn-outline,
.password-form.enhanced .btn-outline {
    background-color: transparent;
    color: var(--text-color);
    border: 1px solid var(--border-color);
}

.profile-form.enhanced .btn-outline:hover,
.password-form.enhanced .btn-outline:hover {
    border-color: var(--text-color);
    background-color: var(--gray-light);
}

/* Password Form */
.form-section.animated {
    transform: translateY(10px);
    opacity: 0;
    animation: fadeInUp 0.5s ease forwards;
}

@keyframes fadeInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.toggle-password {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--text-light);
    transition: color 0.3s;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1; /* Ajout pour un centrage précis */
}

.toggle-password i {
    font-size: 1rem; /* Taille ajustée pour un meilleur alignement */
}

.toggle-password:hover {
    color: var(--primary-color);
}

.password-divider {
    display: flex;
    align-items: center;
    margin: 1.5rem 0;
}

.divider-line {
    flex: 1;
    height: 1px;
    background-color: var(--border-color);
}

.divider-text {
    padding: 0 1rem;
    color: var(--text-light);
    font-size: 0.9rem;
}

.password-match-indicator {
    display: none;
    margin-top: 0.5rem;
    color: var(--error-color);
    font-size: 0.85rem;
    align-items: center;
    gap: 0.5rem;
}

.password-match-indicator.match {
    color: var(--success-color);
}

.password-match-indicator i {
    font-size: 1rem;
}

.password-match-indicator.visible {
    display: flex;
}

/* Password Tips */
.password-tips.enhanced {
    background-color: var(--white);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    padding: 0;
    margin-bottom: 2rem;
    overflow: hidden;
}

.tips-header {
    background-color: var(--info-color);
    color: var(--white);
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.tips-header i {
    font-size: 1.5rem;
}

.tips-header h4 {
    font-size: 1.2rem;
    margin: 0;
}

.tips-content {
    padding: 1.5rem;
}

.tips-list {
    list-style: none;
    margin: 0 0 1.5rem 0;
    padding: 0;
}

.tip-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(var(--border-color-rgb), 0.3);
}

.tip-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.tip-icon {
    width: 36px;
    height: 36px;
    background-color: rgba(var(--info-color-rgb), 0.1);
    color: var(--info-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.tip-text {
    flex: 1;
    font-size: 0.95rem;
}

/* Password Strength Meter */
.password-strength-meter {
    background-color: rgba(var(--info-color-rgb), 0.05);
    border-radius: 6px;
    padding: 1rem;
    margin-top: 1rem;
}

.strength-label {
    font-size: 0.9rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: var(--text-color);
}

.strength-bars {
    display: flex;
    gap: 0.25rem;
    margin-bottom: 0.5rem;
}

.strength-bar {
    height: 6px;
    flex: 1;
    background-color: var(--gray-light);
    border-radius: 3px;
    transition: all 0.3s ease;
}

.strength-bar.weak {
    background-color: var(--error-color);
}

.strength-bar.medium {
    background-color: var(--warning-color);
}

.strength-bar.strong {
    background-color: var(--success-color);
}

.strength-text {
    font-size: 0.8rem;
    color: var(--text-light);
    text-align: right;
}

/* Responsive */
@media screen and (max-width: 992px) {
    .account-container {
        grid-template-columns: 1fr;
    }

    .account-sidebar {
        position: static;
        margin-bottom: 1.5rem;
    }
}

@media screen and (max-width: 768px) {
    .dashboard-stats {
        grid-template-columns: 1fr;
    }

    .profile-header {
        flex-direction: column;
        text-align: center;
    }

    .form-actions.enhanced {
        flex-direction: column;
    }

    .profile-form.enhanced .btn,
    .password-form.enhanced .btn {
        width: 100%;
        justify-content: center;
    }
}

/* Styles pour breadcrumb et alertes (basiques, à ajuster si besoin) */
.breadcrumb {
    margin-bottom: 1.5rem;
}

.breadcrumb-list {
    list-style: none;
    padding: 0;
    display: flex;
    gap: 0.5rem;
}

.breadcrumb-list li a {
    color: var(--primary-color);
    text-decoration: none;
}

.breadcrumb-list li.active {
    color: var(--text-light);
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.alert-success {
    background-color: rgba(var(--success-color-rgb), 0.1);
    color: var(--success-color);
}

.alert-error {
    background-color: rgba(var(--error-color-rgb), 0.1);
    color: var(--error-color);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des onglets
    const navLinks = document.querySelectorAll('.account-nav a');
    const tabContents = document.querySelectorAll('.account-tab');

    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.getAttribute('href').startsWith('#')) {
                e.preventDefault();
                const tabId = this.getAttribute('data-tab');
                navLinks.forEach(nav => nav.parentElement.classList.remove('active'));
                tabContents.forEach(tab => tab.classList.remove('active'));
                this.parentElement.classList.add('active');
                document.getElementById(tabId).classList.add('active');
                window.location.hash = tabId;
            }
        });
    });

    if (window.location.hash) {
        const hash = window.location.hash.substring(1);
        const tabLink = document.querySelector(`.account-nav a[data-tab="${hash}"]`);
        if (tabLink) tabLink.click();
    }

    // Visibilité des mots de passe
    const togglePasswordBtns = document.querySelectorAll('.toggle-password');
    togglePasswordBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (input.type === 'password') {
                input.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });

    // Vérification de correspondance des mots de passe
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const matchIndicator = document.querySelector('.password-match-indicator');

    if (newPasswordInput && confirmPasswordInput && matchIndicator) {
        function checkPasswordMatch() {
            const newPass = newPasswordInput.value;
            const confirmPass = confirmPasswordInput.value;

            if (confirmPass.length > 0) {
                matchIndicator.classList.add('visible');
                if (newPass === confirmPass) {
                    matchIndicator.classList.add('match');
                    matchIndicator.innerHTML = '<i class="fas fa-check-circle"></i><span>Les mots de passe correspondent</span>';
                } else {
                    matchIndicator.classList.remove('match');
                    matchIndicator.innerHTML = '<i class="fas fa-times-circle"></i><span>Les mots de passe ne correspondent pas</span>';
                }
            } else {
                matchIndicator.classList.remove('visible');
            }
        }

        newPasswordInput.addEventListener('input', checkPasswordMatch);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    }

    // Jauge de force du mot de passe
    const strengthBars = document.querySelectorAll('.strength-bar');
    const strengthText = document.querySelector('.strength-text');

    if (newPasswordInput && strengthBars.length && strengthText) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);

            strengthBars.forEach(bar => bar.className = 'strength-bar');
            if (password.length === 0) {
                strengthText.textContent = 'Pas encore évalué';
            } else if (strength < 2) {
                strengthBars[0].classList.add('weak');
                strengthText.textContent = 'Très faible';
            } else if (strength < 4) {
                strengthBars[0].classList.add('weak');
                strengthBars[1].classList.add('weak');
                strengthText.textContent = 'Faible';
            } else if (strength < 6) {
                strengthBars[0].classList.add('medium');
                strengthBars[1].classList.add('medium');
                strengthBars[2].classList.add('medium');
                strengthText.textContent = 'Moyen';
            } else {
                strengthBars[0].classList.add('strong');
                strengthBars[1].classList.add('strong');
                strengthBars[2].classList.add('strong');
                strengthBars[3].classList.add('strong');
                strengthText.textContent = 'Fort';
            }
        });
    }

    function calculatePasswordStrength(password) {
        let strength = 0;
        if (password.length >= 8) strength += 2;
        else if (password.length >= 6) strength += 1;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 1;
        if (/\d/.test(password)) strength += 1;
        if (/[^a-zA-Z0-9]/.test(password)) strength += 1;
        const uniqueChars = new Set(password.split('')).size;
        if (uniqueChars >= 8) strength += 1;
        return strength;
    }
});
</script>

<?php include 'includes/footer.php'; ?>