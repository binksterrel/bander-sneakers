<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$conversation_id = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : null;

if (!$conversation_id) {
    echo json_encode(['success' => false, 'message' => 'ID de conversation manquant']);
    exit;
}

try {
    $db = getDbConnection();

    // Vérifier que l'utilisateur fait partie de la conversation
    $query = "SELECT user1_id, user2_id FROM conversations 
              WHERE conversation_id = :conversation_id 
              AND (user1_id = :user_id1 OR user2_id = :user_id2)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':conversation_id', $conversation_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id1', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'Conversation non trouvée ou accès non autorisé']);
        exit;
    }

    // Fermer la conversation
    $query = "UPDATE conversations SET is_closed = 1 WHERE conversation_id = :conversation_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':conversation_id', $conversation_id, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Conversation fermée avec succès']);
} catch (PDOException $e) {
    error_log("Erreur PDO dans close-conversation.php : " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>