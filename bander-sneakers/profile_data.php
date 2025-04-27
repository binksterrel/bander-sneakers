<?php
header('Content-Type: application/json');
require_once 'includes/config.php';
require_once 'includes/functions.php';

$user_id = (int)($_GET['user_id'] ?? 0);
if ($user_id <= 0) {
    echo json_encode(['error' => 'ID utilisateur invalide']);
    exit;
}

$profile = getUserProfile($user_id);
if (!$profile) {
    echo json_encode(['error' => 'Utilisateur non trouvé']);
    exit;
}

$products = getUserProducts($user_id);
$sold_count = getSoldCount($user_id);
$report_count = getReportCount($user_id);

echo json_encode([
    'username' => htmlspecialchars($profile['username']),
    'sold_count' => $sold_count,
    'report_count' => $report_count,
    'created_at' => date('d/m/Y', strtotime($profile['created_at'])),
    'products' => array_map(function($product) {
        return [
            'id' => $product['id'],
            'title' => htmlspecialchars($product['title']),
            'created_at' => date('d/m/Y', strtotime($product['created_at'])),
            'statut' => htmlspecialchars($product['statut'])
        ];
    }, $products)
]);
?>