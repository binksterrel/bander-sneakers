<?php
// loyalty.php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];
$points = getLoyaltyPoints($userId);
$conversionRate = 200; // 200 points = 10 € de réduction
$discountValue = 10; // Valeur de la réduction en euros

// Traiter l'utilisation des points
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['use_points'])) {
    $pointsToUse = (int)$_POST['points_to_use'];
    
    if ($pointsToUse > 0 && $pointsToUse <= $points && $pointsToUse % $conversionRate === 0) {
        $discount = ($pointsToUse / $conversionRate) * $discountValue;
        if (useLoyaltyPoints($userId, $pointsToUse)) {
            // Stocker la réduction dans une session pour l'utiliser lors du checkout
            $_SESSION['loyalty_discount'] = $discount;
            $_SESSION['success_message'] = "Vous avez utilisé $pointsToUse points pour obtenir une réduction de $discount € !";
            redirect('loyalty.php');
        } else {
            $_SESSION['error_message'] = "Erreur lors de l'utilisation des points.";
        }
    } else {
        $_SESSION['error_message'] = "Nombre de points invalide. Vous devez utiliser un multiple de $conversionRate points.";
    }
}

$page_title = "Programme de Fidélité | Bander-Sneakers";
$page_description = "Consultez vos points de fidélité et utilisez-les pour obtenir des réductions sur vos achats.";
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">Programme de Fidélité</li>
        </ul>
    </div>
</div>

<!-- Loyalty Section -->
<section class="loyalty-section">
    <div class="container">
        <h1>Programme de Fidélité</h1>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-message">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <div class="loyalty-info">
            <p>Vous avez actuellement <strong><?= $points ?></strong> points de fidélité.</p>
            <p>Utilisez vos points pour obtenir des réductions : <strong>100 points = 10 € de réduction</strong>.</p>
        </div>

        <?php if ($points >= $conversionRate): ?>
            <form action="loyalty.php" method="POST" class="loyalty-form">
                <label for="points_to_use">Combien de points souhaitez-vous utiliser ?</label>
                <input type="number" name="points_to_use" id="points_to_use" min="<?= $conversionRate ?>" max="<?= $points ?>" step="<?= $conversionRate ?>" required>
                <button type="submit" name="use_points" class="btn">Utiliser les points</button>
            </form>
        <?php else: ?>
            <p>Vous n'avez pas assez de points pour obtenir une réduction. Continuez à acheter pour gagner plus de points !</p>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>