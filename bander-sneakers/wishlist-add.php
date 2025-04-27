<?php
/**
 * Ajouter ou retirer un produit de la liste de souhaits
 * Réponse JSON pour une intégration AJAX
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Définir le type de contenu comme JSON
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour gérer vos favoris.',
        'redirect' => 'login.php'
    ]);
    exit();
}

// Vérifier que l'ID du produit est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Produit invalide.'
    ]);
    exit();
}

$sneakerId = (int)$_GET['id'];
$userId = $_SESSION['user_id'];

try {
    $db = getDbConnection();

    // Vérifier si le produit existe
    $stmt = $db->prepare('SELECT sneaker_id FROM sneakers WHERE sneaker_id = ?');
    $stmt->execute([$sneakerId]);
    if (!$stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Ce produit n\'existe pas.'
        ]);
        exit();
    }

    // Vérifier si le produit est déjà dans la liste de souhaits
    $stmt = $db->prepare('SELECT wishlist_id FROM wishlist WHERE user_id = ? AND sneaker_id = ?');
    $stmt->execute([$userId, $sneakerId]);
    $wishlistItem = $stmt->fetch();

    if ($wishlistItem) {
        // Supprimer de la wishlist
        $stmt = $db->prepare('DELETE FROM wishlist WHERE wishlist_id = ?');
        $stmt->execute([$wishlistItem['wishlist_id']]);
        echo json_encode([
            'success' => true,
            'action' => 'removed',
            'message' => 'Produit retiré des favoris.'
        ]);
    } else {
        // Ajouter à la wishlist
        $stmt = $db->prepare('INSERT INTO wishlist (user_id, sneaker_id) VALUES (?, ?)');
        $stmt->execute([$userId, $sneakerId]);
        echo json_encode([
            'success' => true,
            'action' => 'added',
            'message' => 'Produit ajouté aux favoris.'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la mise à jour des favoris.'
    ]);
    error_log('Erreur PDO: ' . $e->getMessage());
}
exit();