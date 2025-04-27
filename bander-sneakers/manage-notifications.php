<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit;
}

if ($action === 'mark_read') {
    $success = markNotificationAsRead($id, $user_id);
    echo json_encode(['success' => $success]);
} elseif ($action === 'delete') {
    $success = deleteNotification($id, $user_id);
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false, 'message' => 'Action invalide']);
}
exit;
?>