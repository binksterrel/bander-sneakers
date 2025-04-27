<?php
// process-order.php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];

// Exemple de données (à remplacer par les données réelles du formulaire de checkout)
$totalAmount = 150.50; // Montant total de la commande (à calculer dynamiquement)
$shippingAddress = "123 Rue Exemple";
$shippingCity = "Paris";
$shippingPostalCode = "75001";
$shippingCountry = "France";
$paymentMethod = "credit_card"; // Exemple : "credit_card", "paypal", etc.
$shippingMethod = "standard"; // Exemple : "standard", "express", etc.

// Enregistrer la commande et accorder des points
if (createOrderAndAwardPoints(
    $userId,
    $totalAmount,
    $shippingAddress,
    $shippingCity,
    $shippingPostalCode,
    $shippingCountry,
    $paymentMethod,
    $shippingMethod
)) {
    $_SESSION['success_message'] = "Commande passée avec succès ! Vous avez gagné " . (int)floor($totalAmount) . " points de fidélité.";
    // Réinitialiser la réduction après la commande
    if (isset($_SESSION['loyalty_discount'])) {
        unset($_SESSION['loyalty_discount']);
    }
    redirect('order-confirmation.php');
} else {
    $_SESSION['error_message'] = "Erreur lors du traitement de la commande.";
    redirect('checkout.php');
}
?>