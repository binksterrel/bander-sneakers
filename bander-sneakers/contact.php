<?php
// Page Contact
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Validation basique
    $errors = [];
    if (empty($name)) $errors[] = "Le nom est requis.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Une adresse email valide est requise.";
    if (empty($subject)) $errors[] = "Le sujet est requis.";
    if (empty($message)) $errors[] = "Le message est requis.";

    if (empty($errors)) {
        // Simulation d'envoi (à remplacer par une vraie logique d'envoi d'email)
        // Par exemple : mail($to, $subject, $message, $headers);
        $_SESSION['success_message'] = "Votre message a été envoyé avec succès. Nous vous répondrons sous peu !";
        header('Location: contact.php');
        exit();
    } else {
        $_SESSION['errors'] = $errors;
    }
}

// Récupérer les messages
$success_message = $_SESSION['success_message'] ?? '';
$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['success_message'], $_SESSION['errors']);

// Titre et description de la page
$page_title = "Contactez-nous - Bander-Sneakers";
$page_description = "Contactez l'équipe de Bander-Sneakers pour toute question ou demande. Nous sommes là pour vous aider !";

// Inclure l'en-tête
include 'includes/header.php';
?>

<style>
    .contact-section {
        padding: 60px 0;
    }
    .contact-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
        align-items: start;
    }
    .contact-info, .contact-form-container {
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .contact-info h2, .contact-form-container h2 {
        margin-bottom: 20px;
        font-size: 1.8rem;
    }
    .info-item {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }
    .info-item i {
        margin-right: 10px;
        color: var(--primary-color, #007bff);
        font-size: 20px;
    }
    .contact-form {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .contact-form input, .contact-form textarea {
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 1rem;
        width: 100%;
        box-sizing: border-box;
    }
    .contact-form textarea {
        min-height: 150px;
        resize: vertical;
    }
    .contact-form button {
        padding: 12px 20px;
        background: var(--primary-color, #007bff);
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.3s ease;
    }
    .contact-form button:hover {
        background: darken(var(--primary-color, #007bff), 10%);
    }
    .alert-success, .alert-error {
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    .alert-success {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }
    .alert-error {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
    @media (max-width: 768px) {
        .contact-grid {
            grid-template-columns: 1fr;
        }
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">Contactez-nous</li>
        </ul>
    </div>
</div>

<!-- Contact Section -->
<section class="contact-section">
    <div class="container">
        <div class="contact-grid">
            <!-- Contact Info -->
            <div class="contact-info" style="animation: fadeInUp 1s ease-out;">
                <h2>Contactez-nous</h2>
                <div class="info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <p>123 Rue des Sneakers, 75000 Paris</p>
                </div>
                <div class="info-item">
                    <i class="fas fa-phone"></i>
                    <p>+33 1 23 45 67 89</p>
                </div>
                <div class="info-item">
                    <i class="fas fa-envelope"></i>
                    <p><a href="mailto:bander.sneakers@gmail.com">bander.sneakers@gmail.com</a></p>
                </div>
                <div class="info-item">
                    <i class="fas fa-clock"></i>
                    <p>Lun-Sam : 9h-20h | Dim : Fermé</p>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="contact-form-container" style="animation: fadeInUp 1.2s ease-out;">
                <h2>Envoyez-nous un message</h2>
                <?php if ($success_message): ?>
                    <div class="alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form class="contact-form" action="contact.php" method="post">
                    <input type="text" name="name" placeholder="Votre nom" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    <input type="email" name="email" placeholder="Votre email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    <input type="text" name="subject" placeholder="Sujet" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required>
                    <textarea name="message" placeholder="Votre message" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    <button type="submit">Envoyer</button>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation au scroll
    const elements = document.querySelectorAll('.contact-info, .contact-form-container');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
            }
        });
    }, { threshold: 0.2 });

    elements.forEach(el => observer.observe(el));
});
</script>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>