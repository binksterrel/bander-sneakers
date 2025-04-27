<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['error_message'] = 'Vous devez être connecté pour modifier vos favoris.';
    header('Location: login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

// Vérifier si un ID de sneaker est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'ID de produit invalide.';
    header('Location: wishlist.php');
    exit();
}

$sneakerId = (int)$_GET['id'];

try {
    $db = getDbConnection();
    $stmt = $db->prepare("
        DELETE FROM wishlist 
        WHERE user_id = :user_id AND sneaker_id = :sneaker_id
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':sneaker_id' => $sneakerId
    ]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success_message'] = 'Produit retiré de vos favoris avec succès.';
    } else {
        $_SESSION['error_message'] = 'Ce produit n\'était pas dans vos favoris.';
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Erreur lors de la suppression : ' . $e->getMessage();
}

// Rediriger vers la liste de souhaits
header('Location: wishlist.php');
exit();
?>