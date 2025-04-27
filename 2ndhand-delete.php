<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Vous devez être connecté pour supprimer une annonce.";
    header("Location: login.php");
    exit;
}

// Vérifier si l'ID du produit est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || $_GET['id'] <= 0) {
    $_SESSION['error_message'] = "ID d'annonce invalide.";
    header("Location: compte.php#secondhand");
    exit;
}

$product_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // Connexion à la base de données
    $db = getDbConnection();

    // Vérifier si l'annonce existe et appartient à l'utilisateur
    $query = "SELECT user_id, statut FROM secondhand_products WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $_SESSION['error_message'] = "Annonce non trouvée.";
        header("Location: compte.php#secondhand");
        exit;
    }

    if ($product['user_id'] != $user_id) {
        $_SESSION['error_message'] = "Vous n'êtes pas autorisé à supprimer cette annonce.";
        header("Location: compte.php#secondhand");
        exit;
    }

    if ($product['statut'] === 'supprimé') {
        $_SESSION['error_message'] = "Cette annonce est déjà supprimée.";
        header("Location: compte.php#secondhand");
        exit;
    }

    // Mettre à jour le statut de l'annonce à "supprimé" (suppression logique)
    $update_query = "UPDATE secondhand_products SET statut = 'supprimé', updated_at = NOW() WHERE id = :id";
    $stmt = $db->prepare($update_query);
    $stmt->execute([':id' => $product_id]);

    // Message de succès
    $_SESSION['success_message'] = "L'annonce a été supprimée avec succès.";
    header("Location: compte.php#secondhand");
    exit;

} catch (PDOException $e) {
    // En cas d'erreur, loguer l'erreur et afficher un message
    error_log("Erreur lors de la suppression de l'annonce : " . $e->getMessage());
    $_SESSION['error_message'] = "Une erreur est survenue lors de la suppression de l'annonce.";
    header("Location: compte.php#secondhand");
    exit;
}
?>