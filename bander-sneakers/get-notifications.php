<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['notifications' => [], 'count' => 0]);
    exit;
}

try {
    $user_id = (int)$_SESSION['user_id']; // Cast en int pour sécurité
    $notifications = getUnreadNotifications($user_id);
    $count = countUnreadNotifications($user_id);

    // Vérifier si les fonctions ont retourné des résultats valides
    if ($notifications === false || $count === false) {
        throw new Exception("Erreur lors de la récupération des notifications.");
    }

    echo json_encode([
        'notifications' => array_map(function($notif) {
            return [
                'notification_id' => (int)$notif['notification_id'],
                'message' => htmlspecialchars($notif['message']),
                'type' => $notif['type'],
                'related_id' => $notif['related_id'] ? (int)$notif['related_id'] : null,
                'created_at' => $notif['created_at']
            ];
        }, $notifications),
        'count' => (int)$count
    ]);
} catch (Exception $e) {
    // Retourner une erreur JSON en cas de problème
    echo json_encode([
        'notifications' => [],
        'count' => 0,
        'error' => 'Une erreur est survenue : ' . $e->getMessage()
    ]);
    error_log("Erreur dans get-notifications.php : " . $e->getMessage());
}
exit;
?>