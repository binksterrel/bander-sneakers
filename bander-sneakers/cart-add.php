<?php
/**
 * Ajouter un produit au panier
 *
 * Ce fichier gère l'ajout de produits au panier d'achat.
 * Il est appelé via AJAX depuis la page de détail du produit.
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier que la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit();
}

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'redirect' => 'login.php']);
    exit();
}

// Initialiser la réponse
$response = [
    'success' => false,
    'cart_count' => 0,
    'message' => ''
];

// Vérifier que l'ID du produit et la taille sont fournis
if (!isset($_POST['sneaker_id']) || !is_numeric($_POST['sneaker_id']) || 
    !isset($_POST['size_id']) || !is_numeric($_POST['size_id'])) {
    $response['message'] = 'Produit ou taille non spécifié.';
    echo json_encode($response);
    exit();
}

// Récupérer les données du formulaire
$sneakerId = (int)$_POST['sneaker_id'];
$sizeId = (int)$_POST['size_id'];
$quantity = isset($_POST['quantity']) && is_numeric($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$userId = $_SESSION['user_id'];

// Valider la quantité
if ($quantity < 1) {
    $quantity = 1;
}

try {
    $db = getDbConnection();

    // Vérifier que le produit existe et qu'il y a du stock dans cette taille
    $stmt = $db->prepare('
        SELECT s.sneaker_id, s.price, s.discount_price, ss.stock_quantity
        FROM sneakers s
        JOIN sneaker_sizes ss ON s.sneaker_id = ss.sneaker_id
        WHERE s.sneaker_id = :sneaker_id AND ss.size_id = :size_id
    ');
    $stmt->bindParam(':sneaker_id', $sneakerId, PDO::PARAM_INT);
    $stmt->bindParam(':size_id', $sizeId, PDO::PARAM_INT);
    $stmt->execute();

    $sneaker = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sneaker || $sneaker['stock_quantity'] < $quantity) {
        $response['message'] = $sneaker ? 'Stock insuffisant pour cette taille.' : 'Produit non trouvé.';
        echo json_encode($response);
        exit();
    }

    // Récupérer ou créer un panier pour l'utilisateur (sans condition sur status)
    $stmt = $db->prepare('
        SELECT cart_id FROM cart 
        WHERE user_id = :user_id
        LIMIT 1
    ');
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $cart = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart) {
        // Créer un nouveau panier si aucun n'existe
        $stmt = $db->prepare('
            INSERT INTO cart (user_id, created_at) 
            VALUES (:user_id, NOW())
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $cartId = $db->lastInsertId();
        $_SESSION['cart_id'] = $cartId; // Stocker dans la session
    } else {
        $cartId = $cart['cart_id'];
        $_SESSION['cart_id'] = $cartId; // Synchroniser la session
    }

    // Vérifier si ce produit avec cette taille est déjà dans le panier
    $stmt = $db->prepare('
        SELECT cart_item_id, quantity
        FROM cart_items
        WHERE cart_id = :cart_id AND sneaker_id = :sneaker_id AND size_id = :size_id
    ');
    $stmt->bindParam(':cart_id', $cartId, PDO::PARAM_INT);
    $stmt->bindParam(':sneaker_id', $sneakerId, PDO::PARAM_INT);
    $stmt->bindParam(':size_id', $sizeId, PDO::PARAM_INT);
    $stmt->execute();

    $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingItem) {
        // Mettre à jour la quantité
        $newQuantity = $existingItem['quantity'] + $quantity;

        // Limiter au stock disponible
        if ($newQuantity > $sneaker['stock_quantity']) {
            $newQuantity = $sneaker['stock_quantity'];
            $response['message'] = 'Quantité ajustée au stock disponible.';
        }

        $stmt = $db->prepare('
            UPDATE cart_items
            SET quantity = :quantity, updated_at = NOW()
            WHERE cart_item_id = :cart_item_id
        ');
        $stmt->bindParam(':quantity', $newQuantity, PDO::PARAM_INT);
        $stmt->bindParam(':cart_item_id', $existingItem['cart_item_id'], PDO::PARAM_INT);
        $stmt->execute();
    } else {
        // Ajouter un nouvel article au panier
        $stmt = $db->prepare('
            INSERT INTO cart_items (cart_id, sneaker_id, size_id, quantity, created_at, updated_at)
            VALUES (:cart_id, :sneaker_id, :size_id, :quantity, NOW(), NOW())
        ');
        $stmt->bindParam(':cart_id', $cartId, PDO::PARAM_INT);
        $stmt->bindParam(':sneaker_id', $sneakerId, PDO::PARAM_INT);
        $stmt->bindParam(':size_id', $sizeId, PDO::PARAM_INT);
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Récupérer le nombre total d'articles dans le panier
    $stmt = $db->prepare('SELECT SUM(quantity) as total FROM cart_items WHERE cart_id = :cart_id');
    $stmt->bindParam(':cart_id', $cartId, PDO::PARAM_INT);
    $stmt->execute();
    $cartTotal = $stmt->fetch(PDO::FETCH_ASSOC);
    $cartCount = (int)($cartTotal['total'] ?? 0);

    // Mettre à jour la session pour synchronisation
    $_SESSION['cart_count'] = $cartCount;

    // Préparer la réponse
    $response = [
        'success' => true,
        'cart_count' => $cartCount,
        'message' => $response['message'] ?: 'Article ajouté au panier avec succès.'
    ];

} catch (PDOException $e) {
    error_log('Erreur PDO dans cart-add.php: ' . $e->getMessage());
    $response['message'] = 'Une erreur est survenue lors de l\'ajout au panier.';
}

// Renvoyer la réponse en JSON
header('Content-Type: application/json');
echo json_encode($response);
?>