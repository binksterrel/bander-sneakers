<?php
/**
 * Ajouter un avis sur un produit
 *
 * Ce fichier gère l'ajout d'avis sur les produits.
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Veuillez vous connecter pour laisser un avis.";
    header('Location: login.php');
    exit;
}

// Vérifier que la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Méthode non autorisée');
}

// Vérifier que les données requises sont présentes
if (!isset($_POST['sneaker_id']) || !is_numeric($_POST['sneaker_id']) ||
    !isset($_POST['rating']) || !is_numeric($_POST['rating']) ||
    !isset($_POST['review_text']) || empty($_POST['review_text'])) {

    $_SESSION['error_message'] = "Tous les champs sont obligatoires.";
    header('Location: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php'));
    exit;
}

// Récupérer et valider les données
$sneakerId = (int)$_POST['sneaker_id'];
$rating = (int)$_POST['rating'];
$reviewText = cleanInput($_POST['review_text']);
$userId = $_SESSION['user_id'];

// Valider la note (entre 1 et 5)
if ($rating < 1 || $rating > 5) {
    $_SESSION['error_message'] = "La note doit être comprise entre 1 et 5 étoiles.";
    header('Location: sneaker.php?id=' . $sneakerId);
    exit;
}

try {
    $db = getDbConnection();

    // Vérifier que le produit existe
    $stmt = $db->prepare('SELECT sneaker_id FROM sneakers WHERE sneaker_id = :sneaker_id');
    $stmt->bindParam(':sneaker_id', $sneakerId, PDO::PARAM_INT);
    $stmt->execute();

    if (!$stmt->fetch()) {
        $_SESSION['error_message'] = "Ce produit n'existe pas.";
        header('Location: index.php');
        exit;
    }

    // Vérifier si l'utilisateur a déjà laissé un avis pour ce produit
    $stmt = $db->prepare('SELECT review_id FROM reviews WHERE user_id = :user_id AND sneaker_id = :sneaker_id');
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':sneaker_id', $sneakerId, PDO::PARAM_INT);
    $stmt->execute();

    if ($existingReview = $stmt->fetch()) {
        // Mettre à jour l'avis existant
        $stmt = $db->prepare('
            UPDATE reviews
            SET rating = :rating, review_text = :review_text, updated_at = NOW()
            WHERE review_id = :review_id
        ');
        $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
        $stmt->bindParam(':review_text', $reviewText);
        $stmt->bindParam(':review_id', $existingReview['review_id'], PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success_message'] = "Votre avis a été mis à jour avec succès.";
    } else {
        // Ajouter un nouvel avis
        $stmt = $db->prepare('
            INSERT INTO reviews (user_id, sneaker_id, rating, review_text)
            VALUES (:user_id, :sneaker_id, :rating, :review_text)
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':sneaker_id', $sneakerId, PDO::PARAM_INT);
        $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
        $stmt->bindParam(':review_text', $reviewText);
        $stmt->execute();

        $_SESSION['success_message'] = "Votre avis a été ajouté avec succès.";
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Une erreur est survenue lors de l'enregistrement de votre avis.";
    // Enregistrer l'erreur dans un fichier log
    error_log('Erreur PDO: ' . $e->getMessage());
}

// Rediriger vers la page du produit
header('Location: sneaker.php?id=' . $sneakerId . '#reviews');
exit;
