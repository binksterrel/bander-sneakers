<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json'); // Indique que la réponse est en JSON

error_log("Début de start-conversation.php"); // Log de démarrage

// Vérification de la connexion de l'utilisateur
if (!isset($_SESSION['user_id'])) {
    error_log("Utilisateur non connecté");
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
    exit;
}

// Vérification de la présence et de la validité de l'ID du vendeur
if (!isset($_POST['seller_id']) || !is_numeric($_POST['seller_id'])) {
    error_log("Vendeur non spécifié");
    echo json_encode(['success' => false, 'message' => 'Vendeur non spécifié.']);
    exit;
}

$buyer_id = (int)$_SESSION['user_id']; // ID de l'utilisateur connecté (acheteur)
$seller_id = (int)$_POST['seller_id']; // ID du vendeur
$product_id = isset($_POST['product_id']) && is_numeric($_POST['product_id']) ? (int)$_POST['product_id'] : null;

error_log("buyer_id: $buyer_id, seller_id: $seller_id, product_id: $product_id"); // Log des IDs

// Vérification que l'utilisateur ne tente pas de se parler à lui-même
if ($buyer_id === $seller_id) {
    error_log("Tentative de conversation avec soi-même");
    echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas démarrer une conversation avec vous-même.']);
    exit;
}

try {
    $db = getDbConnection();

    // Vérifier si une conversation existe déjà entre les deux utilisateurs
    $query = "SELECT conversation_id, is_closed FROM conversations 
              WHERE (user1_id = :buyer_id1 AND user2_id = :seller_id1) 
                 OR (user1_id = :seller_id2 AND user2_id = :buyer_id2)";
    error_log("Requête SQL dans start-conversation.php : $query"); // Log de la requête
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':buyer_id1' => $buyer_id,
        ':seller_id1' => $seller_id,
        ':seller_id2' => $seller_id,
        ':buyer_id2' => $buyer_id
    ]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($conversation) {
        // Une conversation existe déjà
        $conversation_id = $conversation['conversation_id'];
        error_log("Conversation existante trouvée : $conversation_id"); // Log de la conversation trouvée

        // Si la conversation était fermée, la rouvrir en mettant is_closed à 0
        if ($conversation['is_closed'] == 1) {
            $query = "UPDATE conversations SET is_closed = 0 WHERE conversation_id = :conversation_id";
            $stmt = $db->prepare($query);
            $stmt->execute([':conversation_id' => $conversation_id]);
            error_log("Conversation rouverte : $conversation_id"); // Log de la réouverture
        }
    } else {
        // Créer une nouvelle conversation avec is_closed = 0
        $query = "INSERT INTO conversations (user1_id, user2_id, created_at, is_closed) 
                  VALUES (:buyer_id, :seller_id, NOW(), 0)";
        error_log("Requête SQL pour créer une conversation : $query"); // Log de la requête d'insertion
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':buyer_id' => $buyer_id,
            ':seller_id' => $seller_id
        ]);
        $conversation_id = $db->lastInsertId();
        error_log("Nouvelle conversation créée : $conversation_id"); // Log de la nouvelle conversation
    }

    // Réponse JSON avec l'URL de redirection
    echo json_encode([
        'success' => true,
        'redirect' => 'chat.php?conversation_id=' . urlencode($conversation_id),
        'message' => 'Conversation prête.'
    ]);
    exit;

} catch (PDOException $e) {
    // Gestion des erreurs PDO
    error_log("Erreur PDO dans start-conversation.php : " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la création de la conversation : ' . $e->getMessage()
    ]);
    exit;
}
?>