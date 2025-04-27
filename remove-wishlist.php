<?php
/**
 * Supprimer un produit de la liste de souhaits
 *
 * Ce fichier gère la suppression de produits de la liste de favoris d'un utilisateur.
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['error_message'] = 'Vous devez être connecté pour gérer vos favoris.';
    header('Location: login.php');
    exit();
}

// Vérifier que l'ID du produit est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Produit invalide.';
    header('Location: wishlist.php');
    exit();
}

$sneakerId = (int)$_GET['id'];
$userId = $_SESSION['user_id'];

try {
    $db = getDbConnection();

    // Vérifier si le produit est dans la liste de souhaits de l'utilisateur
    $stmt = $db->prepare('SELECT wishlist_id FROM wishlist WHERE user_id = ? AND sneaker_id = ?');
    $stmt->execute([$userId, $sneakerId]);
    $wishlistItem = $stmt->fetch();

    if ($wishlistItem) {
        // Supprimer le produit de la liste de souhaits
        $stmt = $db->prepare('DELETE FROM wishlist WHERE wishlist_id = ?');
        $stmt->execute([$wishlistItem['wishlist_id']]);

        $_SESSION['success_message'] = 'Le produit a été retiré de vos favoris.';
    } else {
        $_SESSION['error_message'] = 'Ce produit n\'est pas dans vos favoris.';
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Une erreur est survenue lors de la mise à jour de vos favoris.';
    // Enregistrer l'erreur dans un fichier log
    error_log('Erreur PDO: ' . $e->getMessage());
}

// Rediriger vers la page précédente ou la page des favoris
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) !== false) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
} else {
    header('Location: wishlist.php');
}
exit();
