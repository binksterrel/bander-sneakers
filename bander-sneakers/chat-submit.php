<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour envoyer un message.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$message = $_POST['message'] ?? '';

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Le message ne peut pas être vide.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message_text, is_admin) VALUES (:user_id, :message, 0)");
    $stmt->execute(['user_id' => $user_id, 'message' => $message]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur : ' . $e->getMessage()]);
}
?>