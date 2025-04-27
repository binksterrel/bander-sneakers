<?php
/**
 * Fonctions utilitaires pour le site Bander-Sneakers
 */

// Vérification et inclusion de config.php
if (!file_exists(__DIR__ . '/config.php')) {
    error_log("Erreur : config.php introuvable dans " . __DIR__);
    die("Erreur de configuration : fichier config.php manquant.");
}
require_once __DIR__ . '/config.php';

// Vérification et inclusion de vendor/autoload.php
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    error_log("Erreur : vendor/autoload.php introuvable dans " . __DIR__ . '/../vendor/');
    die("Erreur de configuration : dépendances Composer non installées. Exécutez 'composer install'.");
}
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_log("Début de functions.php - Chargement des fonctions utilitaires");

/**
 * Nettoie une chaîne de caractères
 * @param string $data Données à nettoyer
 * @param bool $encodeHTML Indique si on doit encoder les caractères spéciaux
 * @return string Données nettoyées
 */
function cleanInput($data, $encodeHTML = true) {
    error_log("cleanInput appelé avec data : " . $data);
    $data = trim($data);
    $data = stripslashes($data);
    
    // Si $encodeHTML est vrai, on applique htmlspecialchars, sinon on le laisse brut
    if ($encodeHTML) {
        $data = htmlspecialchars($data);
    }

    error_log("cleanInput - Résultat après nettoyage : " . $data);
    return $data;
}


/**
 * Redirige vers une URL
 * @param string $url URL de redirection
 */
function redirect($url) {
    error_log("Redirection vers : " . $url);
    header("Location: " . $url);
    exit();
}

/**
 * Vérifie si l'utilisateur est connecté
 * @return bool True si l'utilisateur est connecté, sinon False
 */
function isLoggedIn() {
    $isLoggedIn = isset($_SESSION['user_id']);
    error_log("isLoggedIn - Résultat : " . ($isLoggedIn ? 'true' : 'false'));
    return $isLoggedIn;
}

/**
 * Vérifie si l'utilisateur est un administrateur
 * @return bool True si l'utilisateur est un administrateur, sinon False
 */
function isAdmin() {
    // Vérifie si la session contient 'is_admin' et si sa valeur est 1 (administrateur)
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    error_log("isAdmin - Résultat : " . ($isAdmin ? 'true' : 'false'));
    return $isAdmin;
}

/**
 * Génère une chaîne aléatoire
 * @param int $length Longueur de la chaîne
 * @return string Chaîne aléatoire
 */
function generateRandomString($length = 10) {
    error_log("generateRandomString appelé avec length : $length");
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    error_log("generateRandomString - Résultat : $randomString");
    return $randomString;
}

/**
 * Récupère toutes les sneakers avec filtres optionnels
 * @param array $filters Filtres à appliquer (brand_id, category_id, gender, is_featured, is_new_arrival, search, price_min, price_max, sort)
 * @param int $limit Nombre maximum de résultats
 * @param int $offset Décalage pour la pagination
 * @return array Tableau de sneakers
 */
function getSneakers($filters = [], $limit = 0, $offset = 0) {
    error_log("Début de getSneakers - Filtres : " . print_r($filters, true) . ", Limit : $limit, Offset : $offset");

    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans getSneakers");
        throw new Exception("Erreur de connexion à la base de données");
    }

    $sql = "SELECT s.*, b.brand_name, c.category_name,
            (SELECT image_url FROM sneaker_images WHERE sneaker_id = s.sneaker_id AND is_primary = 1 LIMIT 1) AS primary_image
            FROM sneakers s
            LEFT JOIN brands b ON s.brand_id = b.brand_id
            LEFT JOIN categories c ON s.category_id = c.category_id
            WHERE 1=1";
    $conditions = [];
    $params = [];
    $paramCount = 0;

    // Gestion des filtres
    if (isset($filters['brand_id'])) {
        $conditions[] = "s.brand_id = ?";
        $params[] = $filters['brand_id'];
        $paramCount++;
        error_log("Filtre brand_id ajouté : " . $filters['brand_id']);
    }

    if (isset($filters['category_id'])) {
        $conditions[] = "s.category_id = ?";
        $params[] = $filters['category_id'];
        $paramCount++;
        error_log("Filtre category_id ajouté : " . $filters['category_id']);
    }

    if (isset($filters['gender'])) {
        if ($filters['gender'] == 'homme') {
            $conditions[] = "(s.gender = 'homme' OR s.gender = 'unisex')";
        } elseif ($filters['gender'] == 'femme') {
            $conditions[] = "(s.gender = 'femme' OR s.gender = 'unisex')";
        } elseif ($filters['gender'] == 'enfant') {
            $conditions[] = "s.gender = 'enfant'";
        }
        error_log("Filtre gender ajouté : " . $filters['gender']);
    }

    if (isset($filters['is_featured'])) {
        $conditions[] = "s.is_featured = ?";
        $params[] = $filters['is_featured'];
        $paramCount++;
        error_log("Filtre is_featured ajouté : " . $filters['is_featured']);
    }

    if (isset($filters['is_new_arrival'])) {
        $conditions[] = "s.is_new_arrival = ?";
        $params[] = $filters['is_new_arrival'];
        $paramCount++;
        error_log("Filtre is_new_arrival ajouté : " . $filters['is_new_arrival']);
    }

    if (isset($filters['search'])) {
        $conditions[] = "(s.sneaker_name LIKE ? OR b.brand_name LIKE ? OR s.description LIKE ?)";
        $searchValue = '%' . $filters['search'] . '%';
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $paramCount += 3;
        error_log("Filtre search ajouté : " . $searchValue);
    }

    if (isset($filters['price_min'])) {
        $conditions[] = "((s.discount_price IS NOT NULL AND s.discount_price >= ?) OR (s.discount_price IS NULL AND s.price >= ?))";
        $params[] = $filters['price_min'];
        $params[] = $filters['price_min'];
        $paramCount += 2;
        error_log("Filtre price_min ajouté : " . $filters['price_min']);
    }

    if (isset($filters['price_max'])) {
        $conditions[] = "((s.discount_price IS NOT NULL AND s.discount_price <= ?) OR (s.discount_price IS NULL AND s.price <= ?))";
        $params[] = $filters['price_max'];
        $params[] = $filters['price_max'];
        $paramCount += 2;
        error_log("Filtre price_max ajouté : " . $filters['price_max']);
    }

    // Ajout des conditions à la requête
    if (!empty($conditions)) {
        $sql .= " AND " . implode(' AND ', $conditions);
    }

    // Tri
    if (isset($filters['sort'])) {
        switch ($filters['sort']) {
            case 'price_asc':
                $sql .= " ORDER BY s.price ASC";
                break;
            case 'price_desc':
                $sql .= " ORDER BY s.price DESC";
                break;
            case 'name_asc':
                $sql .= " ORDER BY s.sneaker_name ASC";
                break;
            case 'name_desc':
                $sql .= " ORDER BY s.sneaker_name DESC";
                break;
            case 'newest':
                $sql .= " ORDER BY s.release_date DESC";
                break;
            default:
                $sql .= " ORDER BY s.sneaker_id DESC";
        }
        error_log("Tri appliqué : " . $filters['sort']);
    } else {
        $sql .= " ORDER BY s.sneaker_id DESC";
        error_log("Tri par défaut appliqué : ORDER BY s.sneaker_id DESC");
    }

    // Limite et décalage pour la pagination
    if ($limit > 0) {
        $sql .= " LIMIT ?";
        $params[] = (int)$limit;
        $paramCount++;
        error_log("Limite ajoutée : $limit");

        if ($offset > 0) {
            $sql .= " OFFSET ?";
            $params[] = (int)$offset;
            $paramCount++;
            error_log("Offset ajouté : $offset");
        }
    }

    error_log("Requête SQL finale dans getSneakers : " . $sql);
    error_log("Nombre de placeholders attendus : $paramCount");
    error_log("Paramètres à lier : " . print_r($params, true));

    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log("Erreur lors de la préparation de la requête dans getSneakers : " . print_r($db->errorInfo(), true));
            throw new PDOException("Erreur lors de la préparation de la requête");
        }

        // Lier les paramètres un par un
        for ($i = 0; $i < count($params); $i++) {
            $paramIndex = $i + 1;
            $paramValue = $params[$i];
            $paramType = (is_int($paramValue) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $stmt->bindValue($paramIndex, $paramValue, $paramType);
            error_log("Paramètre lié [$paramIndex] : $paramValue (Type : " . ($paramType == PDO::PARAM_INT ? 'INT' : 'STR') . ")");
        }

        error_log("Nombre total de paramètres liés : " . count($params));

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Nombre de sneakers récupérées : " . count($results));
        return $results;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getSneakers : " . $e->getMessage());
        throw new PDOException("Erreur lors de l'exécution de la requête dans getSneakers : " . $e->getMessage());
    }
}

/**
 * Récupère une sneaker par son ID
 * @param int $sneakerId ID de la sneaker
 * @return array|false Données de la sneaker ou false si non trouvée
 */
function getSneakerById($sneakerId) {
    error_log("getSneakerById appelé avec sneakerId : $sneakerId");

    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans getSneakerById");
        throw new Exception("Erreur de connexion à la base de données");
    }

    $sql = "SELECT s.*, b.brand_name, c.category_name
            FROM sneakers s
            LEFT JOIN brands b ON s.brand_id = b.brand_id
            LEFT JOIN categories c ON s.category_id = c.category_id
            WHERE s.sneaker_id = ?";
    error_log("Requête SQL dans getSneakerById : " . $sql);

    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log("Erreur lors de la préparation de la requête dans getSneakerById : " . print_r($db->errorInfo(), true));
            throw new PDOException("Erreur lors de la préparation de la requête");
        }

        $stmt->bindValue(1, $sneakerId, PDO::PARAM_INT);
        error_log("Paramètre lié [1] : $sneakerId (Type : INT)");

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Résultat de getSneakerById : " . ($result ? 'Trouvé' : 'Non trouvé'));
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getSneakerById : " . $e->getMessage());
        throw new PDOException("Erreur lors de l'exécution de la requête dans getSneakerById : " . $e->getMessage());
    }
}

/**
 * Récupère les images d'une sneaker
 * @param int $sneakerId ID de la sneaker
 * @return array Tableau d'images
 */
function getSneakerImages($sneakerId) {
    error_log("getSneakerImages appelé avec sneakerId : $sneakerId");

    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans getSneakerImages");
        throw new Exception("Erreur de connexion à la base de données");
    }

    $sql = "SELECT * FROM sneaker_images WHERE sneaker_id = ? ORDER BY is_primary DESC";
    error_log("Requête SQL dans getSneakerImages : " . $sql);

    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log("Erreur lors de la préparation de la requête dans getSneakerImages : " . print_r($db->errorInfo(), true));
            throw new PDOException("Erreur lors de la préparation de la requête");
        }

        $stmt->bindValue(1, $sneakerId, PDO::PARAM_INT);
        error_log("Paramètre lié [1] : $sneakerId (Type : INT)");

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Nombre d'images récupérées : " . count($results));
        return $results;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getSneakerImages : " . $e->getMessage());
        throw new PDOException("Erreur lors de l'exécution de la requête dans getSneakerImages : " . $e->getMessage());
    }
}

/**
 * Récupère les tailles disponibles pour une sneaker
 * @param int $sneakerId ID de la sneaker
 * @return array Tableau des tailles disponibles
 */
function getSneakerSizes($sneakerId) {
    error_log("getSneakerSizes appelé avec sneakerId : $sneakerId");

    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans getSneakerSizes");
        throw new Exception("Erreur de connexion à la base de données");
    }

    $sql = "SELECT ss.*, s.size_value, s.size_type
            FROM sneaker_sizes ss
            JOIN sizes s ON ss.size_id = s.size_id
            WHERE ss.sneaker_id = ? AND ss.stock_quantity > 0
            ORDER BY s.size_type, s.size_value";
    error_log("Requête SQL dans getSneakerSizes : " . $sql);

    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log("Erreur lors de la préparation de la requête dans getSneakerSizes : " . print_r($db->errorInfo(), true));
            throw new PDOException("Erreur lors de la préparation de la requête");
        }

        $stmt->bindValue(1, $sneakerId, PDO::PARAM_INT);
        error_log("Paramètre lié [1] : $sneakerId (Type : INT)");

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Nombre de tailles récupérées : " . count($results));
        return $results;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getSneakerSizes : " . $e->getMessage());
        throw new PDOException("Erreur lors de l'exécution de la requête dans getSneakerSizes : " . $e->getMessage());
    }
}

/**
 * Récupère tous les avis pour une sneaker
 * @param int $sneakerId ID de la sneaker
 * @return array Tableau des avis
 */
function getSneakerReviews($sneakerId) {
    error_log("getSneakerReviews appelé avec sneakerId : $sneakerId");

    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans getSneakerReviews");
        throw new Exception("Erreur de connexion à la base de données");
    }

    $sql = "SELECT r.*, u.username
            FROM reviews r
            LEFT JOIN users u ON r.user_id = u.user_id
            WHERE r.sneaker_id = ?
            ORDER BY r.created_at DESC";
    error_log("Requête SQL dans getSneakerReviews : " . $sql);

    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log("Erreur lors de la préparation de la requête dans getSneakerReviews : " . print_r($db->errorInfo(), true));
            throw new PDOException("Erreur lors de la préparation de la requête");
        }

        $stmt->bindValue(1, $sneakerId, PDO::PARAM_INT);
        error_log("Paramètre lié [1] : $sneakerId (Type : INT)");

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Nombre d'avis récupérés : " . count($results));
        return $results;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getSneakerReviews : " . $e->getMessage());
        throw new PDOException("Erreur lors de l'exécution de la requête dans getSneakerReviews : " . $e->getMessage());
    }
}

/**
 * Récupère toutes les marques
 * @return array Tableau des marques
 */
function getBrands() {
    error_log("getBrands appelé");

    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans getBrands");
        throw new Exception("Erreur de connexion à la base de données");
    }

    $sql = "SELECT * FROM brands ORDER BY brand_name";
    error_log("Requête SQL dans getBrands : " . $sql);

    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log("Erreur lors de la préparation de la requête dans getBrands : " . print_r($db->errorInfo(), true));
            throw new PDOException("Erreur lors de la préparation de la requête");
        }

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Nombre de marques récupérées : " . count($results));
        return $results;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getBrands : " . $e->getMessage());
        throw new PDOException("Erreur lors de l'exécution de la requête dans getBrands : " . $e->getMessage());
    }
}

/**
 * Récupère toutes les catégories
 * @return array Tableau des catégories
 */
function getCategories() {
    error_log("getCategories appelé");

    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans getCategories");
        throw new Exception("Erreur de connexion à la base de données");
    }

    $sql = "SELECT * FROM categories ORDER BY category_name";
    error_log("Requête SQL dans getCategories : " . $sql);

    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log("Erreur lors de la préparation de la requête dans getCategories : " . print_r($db->errorInfo(), true));
            throw new PDOException("Erreur lors de la préparation de la requête");
        }

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Nombre de catégories récupérées : " . count($results));
        return $results;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getCategories : " . $e->getMessage());
        throw new PDOException("Erreur lors de l'exécution de la requête dans getCategories : " . $e->getMessage());
    }
}

/**
 * Formate un prix pour l'affichage
 * @param float $price Prix à formater
 * @return string Prix formaté
 */
function formatPrice($price) {
    error_log("formatPrice appelé avec price : $price");
    $formattedPrice = number_format($price, 2, ',', ' ') . ' €';
    error_log("formatPrice - Résultat : $formattedPrice");
    return $formattedPrice;
}

/**
 * Calcule le pourcentage de réduction
 * @param float $originalPrice Prix original
 * @param float $discountPrice Prix réduit
 * @return int Pourcentage de réduction
 */
function calculateDiscount($originalPrice, $discountPrice) {
    error_log("calculateDiscount appelé avec originalPrice : $originalPrice, discountPrice : $discountPrice");

    if ($originalPrice <= 0 || $discountPrice <= 0 || $discountPrice >= $originalPrice) {
        error_log("calculateDiscount - Conditions non remplies, retourne 0");
        return 0;
    }

    $discount = round(100 - ($discountPrice * 100 / $originalPrice));
    error_log("calculateDiscount - Pourcentage de réduction : $discount");
    return $discount;
}

/**
 * Génère un slug à partir d'une chaîne
 * @param string $string Chaîne à transformer en slug
 * @return string Slug généré
 */
function generateSlug($string) {
    error_log("generateSlug appelé avec string : $string");

    // Remplacer les caractères spéciaux
    $string = str_replace(['é', 'è', 'ê', 'ë'], 'e', $string);
    $string = str_replace(['à', 'â', 'ä'], 'a', $string);
    $string = str_replace(['ù', 'û', 'ü'], 'u', $string);
    $string = str_replace(['ô', 'ö'], 'o', $string);
    $string = str_replace(['ï', 'î'], 'i', $string);
    $string = str_replace(['ç'], 'c', $string);

    // Convertir en minuscules et remplacer les espaces par des tirets
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);

    $slug = trim($string, '-');
    error_log("generateSlug - Résultat : $slug");
    return $slug;
}

/**
 * Ajoute des points de fidélité à un utilisateur
 * @param int $userId ID de l'utilisateur
 * @param int $points Nombre de points à ajouter
 * @param string $description Description de l'opération (non utilisé si la colonne n'existe pas)
 * @return bool True si les points ont été ajoutés, false sinon
 */
function addLoyaltyPoints($userId, $points, $description = 'Points gagnés') {
    error_log("addLoyaltyPoints appelé avec userId : $userId, points : $points, description : $description");

    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans addLoyaltyPoints");
        return false;
    }

    $sql = "INSERT INTO loyalty_points (user_id, points, earned_at) VALUES (?, ?, NOW())";
    error_log("Requête SQL dans addLoyaltyPoints : $sql");

    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log("Erreur lors de la préparation de la requête dans addLoyaltyPoints : " . print_r($db->errorInfo(), true));
            return false;
        }

        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $points, PDO::PARAM_INT);
        error_log("Paramètres liés : [user_id: $userId, points: $points]");

        if (!$stmt->execute()) {
            error_log("Échec de l'exécution de la requête dans addLoyaltyPoints : " . print_r($stmt->errorInfo(), true));
            return false;
        }

        error_log("Résultat de l'ajout des points : Succès");
        return true;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans addLoyaltyPoints : " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère le total des points de fidélité d'un utilisateur
 * @param int $userId ID de l'utilisateur
 * @return int Total des points
 */
function getLoyaltyPoints($userId) {
    error_log("getLoyaltyPoints appelé avec userId : $userId");

    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans getLoyaltyPoints");
        return 0;
    }

    $sql = "SELECT SUM(points) as total_points FROM loyalty_points WHERE user_id = ?";
    error_log("Requête SQL dans getLoyaltyPoints : $sql");

    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log("Erreur lors de la préparation de la requête dans getLoyaltyPoints : " . print_r($db->errorInfo(), true));
            return 0;
        }

        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        error_log("Paramètre lié : [user_id: $userId]");

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalPoints = $result['total_points'] ?? 0;
        error_log("Total des points pour userId $userId : $totalPoints");
        return (int)$totalPoints;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getLoyaltyPoints : " . $e->getMessage());
        return 0;
    }
}

/**
 * Utilise des points de fidélité pour une réduction
 * @param int $userId ID de l'utilisateur
 * @param int $pointsToUse Nombre de points à utiliser
 * @return bool True si les points ont été utilisés, false sinon
 */
function useLoyaltyPoints($userId, $pointsToUse) {
    error_log("useLoyaltyPoints appelé avec userId : $userId, pointsToUse : $pointsToUse");

    $totalPoints = getLoyaltyPoints($userId);
    if ($totalPoints < $pointsToUse) {
        error_log("Erreur : Pas assez de points pour userId $userId. Points disponibles : $totalPoints, Points demandés : $pointsToUse");
        return false;
    }

    return addLoyaltyPoints($userId, -$pointsToUse, 'Points utilisés pour une réduction');
}

/**
 * Enregistre une commande et accorde des points de fidélité
 * @param int|null $userId ID de l'utilisateur (peut être null pour les invités)
 * @param float $totalAmount Montant total de la commande
 * @param string $shippingAddress Adresse de livraison
 * @param string $shippingCity Ville de livraison
 * @param string $shippingPostalCode Code postal de livraison
 * @param string $shippingCountry Pays de livraison
 * @param string $paymentMethod Méthode de paiement
 * @param string $shippingMethod Méthode de livraison
 * @return int|bool ID de la commande si succès, false sinon
 */
function createOrderAndAwardPoints($userId, $totalAmount, $shippingAddress, $shippingCity, $shippingPostalCode, $shippingCountry, $paymentMethod, $shippingMethod) {
    error_log("createOrderAndAwardPoints appelé avec userId: " . ($userId ?? 'NULL') . ", totalAmount: $totalAmount");

    $db = getDbConnection();
    if (!$db) return false;

    try {
        $sql = "INSERT INTO orders (
            user_id, total_amount, shipping_address, shipping_city, shipping_postal_code, shipping_country,
            payment_method, shipping_method, order_status
        ) VALUES (
            :user_id, :total_amount, :shipping_address, :shipping_city, :shipping_postal_code, :shipping_country,
            :payment_method, :shipping_method, 'pending'
        )";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':total_amount' => $totalAmount,
            ':shipping_address' => $shippingAddress,
            ':shipping_city' => $shippingCity,
            ':shipping_postal_code' => $shippingPostalCode,
            ':shipping_country' => $shippingCountry,
            ':payment_method' => $paymentMethod,
            ':shipping_method' => $shippingMethod
        ]);
        $orderId = $db->lastInsertId();

        if ($userId) {
            $points = floor($totalAmount);
            addLoyaltyPoints($userId, $points, "Points gagnés pour la commande #$orderId");
            addNotification($userId, "🛍️ Vous avez reçu $points points grâce à votre Commande #$orderId", 'points_purchase', $orderId);
        }

        return $orderId;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans createOrderAndAwardPoints : " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère l'historique des points de fidélité d'un utilisateur
 * @param int $userId ID de l'utilisateur
 * @return array Historique des points
 */
function getLoyaltyPointsHistory($userId) {
    error_log("getLoyaltyPointsHistory appelé avec userId : $userId");

    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans getLoyaltyPointsHistory");
        return [];
    }

    $sql = "SELECT points, description, earned_at FROM loyalty_points WHERE user_id = ? ORDER BY earned_at DESC";
    error_log("Requête SQL dans getLoyaltyPointsHistory : $sql");

    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->execute();
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Historique des points récupéré : " . print_r($history, true));
        return $history;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getLoyaltyPointsHistory : " . $e->getMessage());
        return [];
    }
}

/**
 * Tronque un texte à une longueur donnée
 * @param string $text Texte à tronquer
 * @param int $length Longueur maximale
 * @param string $suffix Suffixe à ajouter (par défaut '...')
 * @return string Texte tronqué
 */
function truncate($text, $length, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Ajoute une notification pour un utilisateur
 * @param int $userId ID de l'utilisateur
 * @param string $message Message de la notification
 * @param string $type Type de notification
 * @param int|null $relatedId ID de l'élément lié (optionnel)
 * @return bool Succès de l'opération
 */
function addNotification($userId, $message, $type, $relatedId = null) {
    error_log("addNotification appelé avec userId: $userId, message: $message, type: $type, relatedId: " . ($relatedId ?? 'NULL'));
    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans addNotification");
        return false;
    }

    $allowedTypes = ['message', 'points_purchase', 'points_spin', 'order_update', 'report', 'price_change'];
    if (!in_array($type, $allowedTypes)) {
        error_log("Erreur : Type de notification '$type' non autorisé dans addNotification");
        return false;
    }

    $sql = "INSERT INTO notifications (user_id, message, type, related_id) VALUES (:user_id, :message, :type, :related_id)";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':message' => $message,
            ':type' => $type,
            ':related_id' => $relatedId
        ]);
        error_log("Notification ajoutée pour userId: $userId");
        return true;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans addNotification : " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les notifications non lues d'un utilisateur
 * @param int $userId ID de l'utilisateur
 * @param int $limit Nombre maximum de notifications (par défaut 5)
 * @return array Tableau des notifications non lues
 */
function getUnreadNotifications($userId, $limit = 5) {
    error_log("getUnreadNotifications appelé avec userId: $userId, limit: $limit");
    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans getUnreadNotifications");
        return [];
    }

    $sql = "SELECT * FROM notifications WHERE user_id = :user_id AND is_read = 0 ORDER BY created_at DESC LIMIT :limit";
    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Notifications non lues récupérées pour userId: $userId, count: " . count($notifications));
        return $notifications;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getUnreadNotifications : " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère toutes les notifications d'un utilisateur
 * @param int $userId ID de l'utilisateur
 * @return array Tableau de toutes les notifications
 */
function getAllNotifications($userId) {
    error_log("getAllNotifications appelé avec userId: $userId");
    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans getAllNotifications");
        return [];
    }

    $sql = "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Toutes les notifications récupérées pour userId: $userId, count: " . count($notifications));
        return $notifications;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getAllNotifications : " . $e->getMessage());
        return [];
    }
}

/**
 * Marque une notification comme lue
 * @param int $notificationId ID de la notification
 * @param int $userId ID de l'utilisateur (pour vérification)
 * @return bool Succès de l'opération
 */
function markNotificationAsRead($notificationId, $userId) {
    error_log("markNotificationAsRead appelé avec notificationId: $notificationId, userId: $userId");
    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans markNotificationAsRead");
        return false;
    }

    $sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = :notification_id AND user_id = :user_id";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':notification_id' => $notificationId,
            ':user_id' => $userId
        ]);
        $affected = $stmt->rowCount();
        error_log("Notification marquée comme lue: notificationId: $notificationId, affected: $affected");
        return $affected > 0;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans markNotificationAsRead : " . $e->getMessage());
        return false;
    }
}

/**
 * Supprime une notification
 * @param int $notificationId ID de la notification
 * @param int $userId ID de l'utilisateur (pour vérification)
 * @return bool Succès de l'opération
 */
function deleteNotification($notificationId, $userId) {
    error_log("deleteNotification appelé avec notificationId: $notificationId, userId: $userId");
    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans deleteNotification");
        return false;
    }

    $sql = "DELETE FROM notifications WHERE notification_id = :notification_id AND user_id = :user_id";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':notification_id' => $notificationId,
            ':user_id' => $userId
        ]);
        $affected = $stmt->rowCount();
        error_log("Notification supprimée: notificationId: $notificationId, affected: $affected");
        return $affected > 0;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans deleteNotification : " . $e->getMessage());
        return false;
    }
}

/**
 * Compte les notifications non lues d'un utilisateur
 * @param int $userId ID de l'utilisateur
 * @return int Nombre de notifications non lues
 */
function countUnreadNotifications($userId) {
    error_log("countUnreadNotifications appelé avec userId: $userId");
    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans countUnreadNotifications");
        return 0;
    }

    $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $count = $stmt->fetchColumn();
        error_log("Nombre de notifications non lues pour userId: $userId : $count");
        return (int)$count;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans countUnreadNotifications : " . $e->getMessage());
        return 0;
    }
}

/**
 * Met à jour les prix d'une sneaker et vérifie les changements pour envoyer des notifications
 * @param int $sneakerId ID de la sneaker
 * @param float $newPrice Nouveau prix
 * @param float|null $newDiscountPrice Nouveau prix promotionnel (optionnel)
 */
function updateSneakerPrice($sneakerId, $newPrice, $newDiscountPrice = null) {
    error_log("updateSneakerPrice appelé avec sneakerId: $sneakerId, newPrice: $newPrice, newDiscountPrice: " . ($newDiscountPrice ?? 'NULL'));

    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans updateSneakerPrice");
        throw new Exception("Erreur de connexion à la base de données");
    }

    try {
        $sql = "SELECT price, discount_price FROM sneakers WHERE sneaker_id = :sneaker_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':sneaker_id' => $sneakerId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Données actuelles récupérées : " . print_r($current, true));

        if (!$current) {
            error_log("Produit non trouvé pour l'ID $sneakerId dans updateSneakerPrice");
            throw new Exception("Produit non trouvé pour l'ID $sneakerId");
        }

        $sql = "UPDATE sneakers SET price = :price, discount_price = :discount_price, updated_at = NOW()
                WHERE sneaker_id = :sneaker_id";
        $stmt = $db->prepare($sql);
        $params = [
            ':price' => $newPrice,
            ':discount_price' => $newDiscountPrice,
            ':sneaker_id' => $sneakerId
        ];
        error_log("Exécution UPDATE avec paramètres : " . print_r($params, true));
        $stmt->execute($params);
        error_log("Prix mis à jour pour sneakerId: $sneakerId");

        checkPriceChangesAndNotify($sneakerId, $current['price'], $current['discount_price'], $newPrice, $newDiscountPrice);
    } catch (PDOException $e) {
        error_log("Erreur PDO dans updateSneakerPrice : " . $e->getMessage() . " - Code: " . $e->getCode());
        throw $e; // Relancer pour capture CAUSED dans admin/products.php
    }
}
/**
 * Vérifie les changements de prix et envoie des notifications/emails aux utilisateurs ayant le produit dans leur wishlist
 * @param int $sneakerId ID de la sneaker
 * @param float $oldPrice Ancien prix
 * @param float|null $oldDiscountPrice Ancien prix promotionnel
 * @param float $newPrice Nouveau prix
 * @param float|null $newDiscountPrice Nouveau prix promotionnel
 */
function checkPriceChangesAndNotify($sneakerId, $oldPrice, $oldDiscountPrice, $newPrice, $newDiscountPrice) {
    error_log("checkPriceChangesAndNotify appelé avec sneakerId: $sneakerId, oldPrice: $oldPrice, oldDiscountPrice: " . ($oldDiscountPrice ?? 'NULL') . ", newPrice: $newPrice, newDiscountPrice: " . ($newDiscountPrice ?? 'NULL'));

    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans checkPriceChangesAndNotify");
        return;
    }

    try {
        // Récupérer les informations du produit et l'image principale
        $sql = "SELECT s.sneaker_name,
                       (SELECT image_url FROM sneaker_images WHERE sneaker_id = s.sneaker_id AND is_primary = 1 LIMIT 1) AS primary_image
                FROM sneakers s WHERE s.sneaker_id = :sneaker_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':sneaker_id' => $sneakerId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            error_log("Produit non trouvé pour l'ID $sneakerId dans checkPriceChangesAndNotify");
            return;
        }

        $oldEffectivePrice = $oldDiscountPrice ?? $oldPrice;
        $newEffectivePrice = $newDiscountPrice ?? $newPrice;

        $sql = "SELECT user_id FROM wishlist WHERE sneaker_id = :sneaker_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':sneaker_id' => $sneakerId]);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        error_log("Utilisateurs ayant sneakerId $sneakerId dans leur wishlist : " . count($users));

        foreach ($users as $userId) {
            $message = '';
            if (!$oldDiscountPrice && $newDiscountPrice) {
                $message = "🎉 {$product['sneaker_name']} est en promotion à " . formatPrice($newDiscountPrice) . " !";
                sendPromotionEmail(
                    $userId,
                    $sneakerId,
                    $product['sneaker_name'],
                    $newDiscountPrice,
                    $product['primary_image'] ?: 'https://via.placeholder.com/500x200/D32F2F/fff?text=' . urlencode($product['sneaker_name'])
                );
            } elseif ($oldDiscountPrice && !$newDiscountPrice) {
                $message = "ℹ️ La promotion sur {$product['sneaker_name']} est terminée. Nouveau prix : " . formatPrice($newPrice) . ".";
            } elseif ($newEffectivePrice != $oldEffectivePrice) {
                if ($newEffectivePrice < $oldEffectivePrice) {
                    $message = "⬇️ Le prix de {$product['sneaker_name']} a baissé de " . formatPrice($oldEffectivePrice) . " à " . formatPrice($newEffectivePrice) . " !";
                } else {
                    $message = "⬆️ Le prix de {$product['sneaker_name']} a augmenté de " . formatPrice($oldEffectivePrice) . " à " . formatPrice($newEffectivePrice) . ".";
                }
            }

            if ($message) {
                addNotification($userId, $message, 'price_change', $sneakerId);
            }
        }

        $sql = "INSERT INTO price_history (sneaker_id, old_price, new_price, old_discount_price, new_discount_price)
                VALUES (:sneaker_id, :old_price, :new_price, :old_discount_price, :new_discount_price)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':sneaker_id' => $sneakerId,
            ':old_price' => $oldPrice,
            ':new_price' => $newPrice,
            ':old_discount_price' => $oldDiscountPrice,
            ':new_discount_price' => $newDiscountPrice
        ]);
        error_log("Historique des prix mis à jour pour sneakerId: $sneakerId");
    } catch (PDOException $e) {
        error_log("Erreur PDO dans checkPriceChangesAndNotify : " . $e->getMessage());
    }
}

/**
 * Envoie un email de promotion aux utilisateurs avec une sneaker en wishlist
 * @param int $userId ID de l'utilisateur
 * @param int $sneakerId ID de la sneaker
 * @param string $sneakerName Nom de la sneaker
 * @param float $newDiscountPrice Nouveau prix promotionnel
 * @param string $primaryImage URL de l'image principale de la sneaker
 * @return bool Succès de l'envoi
 */
function sendPromotionEmail($userId, $sneakerId, $sneakerName, $newDiscountPrice, $primaryImage) {
    error_log("sendPromotionEmail appelé avec userId: $userId, sneakerId: $sneakerId, sneakerName: $sneakerName, newDiscountPrice: $newDiscountPrice");

    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans sendPromotionEmail");
        return false;
    }

    $stmt = $db->prepare("SELECT email FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        error_log("Utilisateur non trouvé pour userId: $userId dans sendPromotionEmail");
        return false;
    }
    $email = $user['email'];

    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'bander.sneakers@gmail.com';
        $mail->Password = 'kpeeqikaqfkanpbd'; // Mot de passe d'application Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('bander.sneakers@gmail.com', 'Bander Sneakers');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'ALERTE PROMO : ' . htmlspecialchars($sneakerName) . ' en baisse !';
        $mail->Body = '
            <html>
                <body style="font-family: Arial, sans-serif; color: #333; background-color: #fff; margin: 0; padding: 0;">
                    <div style="max-width: 600px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 0 15px rgba(211, 47, 47, 0.2);">
                        <h1 style="text-align: center; font-size: 32px; color: #D32F2F; text-transform: uppercase; letter-spacing: 2px; text-shadow: 0 0 5px rgba(211, 47, 47, 0.5);">
                            🔥 Drop Alert ! 🔥
                        </h1>
                        <p style="text-align: center; font-size: 18px; color: #555; margin: 10px 0;">
                            Yo bandeur de sneakers, <strong style="color: #D32F2F;">' . htmlspecialchars($sneakerName) . '</strong> vient de passer en mode promo !
                        </p>
                        <div style="margin: 20px 0; text-align: center;">
                            <img src="' . htmlspecialchars($primaryImage) . '" 
                                 alt="' . htmlspecialchars($sneakerName) . '" 
                                 style="max-width: 100%; border-radius: 8px; border: 2px solid #D32F2F;">
                        </div>
                        <p style="font-size: 16px; line-height: 1.5; color: #444; text-align: center;">
                            Cette paire que tu kiffes est maintenant à <strong style="color: #D32F2F;">' . formatPrice($newDiscountPrice) . '</strong>. 
                            C’est LE moment de frapper avant que le stock s’évapore. Lace-up et go !
                        </p>
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="http://localhost/bander-sneakers/sneaker.php?id=' . $sneakerId . '" 
                               style="display: inline-block; padding: 15px 30px; background: #D32F2F; color: #fff; text-decoration: none; font-size: 18px; font-weight: bold; text-transform: uppercase; border-radius: 8px; box-shadow: 0 0 10px rgba(211, 47, 47, 0.5);">
                               Chope-la maintenant
                            </a>
                        </div>
                        <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #D32F2F;">
                            <p style="font-size: 14px; color: #888;">La Team <strong style="color: #D32F2F;">Bander-Sneakers</strong></p>
                            <p style="font-size: 12px; color: #999;">Offre limitée. Si t’es pas intéressé, passe ton tour.</p>
                        </div>
                    </div>
                </body>
            </html>';

        $mail->send();
        error_log("Email de promotion envoyé à $email pour sneakerId: $sneakerId");
        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de l'envoi de l'email de promotion : " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Envoie une newsletter à tous les abonnés lorsqu'une promotion est ajoutée
 * @param int $sneakerId ID de la sneaker
 * @param string $sneakerName Nom de la sneaker
 * @param float $newPrice Nouveau prix normal
 * @param float $newDiscountPrice Nouveau prix promotionnel
 * @param string $primaryImage URL de l'image principale
 * @return bool Succès de l'envoi
 */
function sendPromotionNewsletter($sneakerId, $sneakerName, $newPrice, $newDiscountPrice, $primaryImage) {
    error_log("sendPromotionNewsletter appelé avec sneakerId: $sneakerId, sneakerName: $sneakerName, newPrice: $newPrice, newDiscountPrice: $newDiscountPrice");

    $db = getDbConnection();
    if (!$db) {
        error_log("Erreur : Impossible d'obtenir la connexion à la base de données dans sendPromotionNewsletter");
        return false;
    }

    // Récupérer tous les abonnés à la newsletter
    $stmt = $db->prepare("SELECT email FROM users WHERE newsletter_subscribed = 1");
    $stmt->execute();
    $subscribers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($subscribers)) {
        error_log("Aucun abonné à la newsletter trouvé dans sendPromotionNewsletter");
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        // Config SMTP (ajustez selon votre serveur)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'bander.sneakers@gmail.com';
        $mail->Password = 'kpeeqikaqfkanpbd'; // Mot de passe d'application Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('bander.sneakers@gmail.com', 'Bander Sneakers');
        $mail->isHTML(true);
        $mail->Subject = 'Nouvelle Promo : ' . htmlspecialchars($sneakerName) . ' en baisse !';

        $mail->Body = '
            <html>
                <body style="font-family: Arial, sans-serif; color: #333; background-color: #fff; margin: 0; padding: 0;">
                    <div style="max-width: 600px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 0 15px rgba(211, 47, 47, 0.2);">
                        <h1 style="text-align: center; font-size: 32px; color: #D32F2F; text-transform: uppercase; letter-spacing: 2px; text-shadow: 0 0 5px rgba(211, 47, 47, 0.5);">
                            🔥 Nouvelle Promo ! 🔥
                        </h1>
                        <p style="text-align: center; font-size: 18px; color: #555; margin: 10px 0;">
                            Salut bandeur de sneakers, une nouvelle offre vient de dropper !
                        </p>
                        <div style="margin: 20px 0; text-align: center;">
                            <img src="' . htmlspecialchars($primaryImage) . '" 
                                 alt="' . htmlspecialchars($sneakerName) . '" 
                                 style="max-width: 100%; border-radius: 8px; border: 2px solid #D32F2F;">
                        </div>
                        <p style="font-size: 16px; line-height: 1.5; color: #444; text-align: center;">
                            <strong style="color: #D32F2F;">' . htmlspecialchars($sneakerName) . '</strong><br>
                            Prix normal : ' . formatPrice($newPrice) . '<br>
                            Prix promo : <strong style="color: #D32F2F;">' . formatPrice($newDiscountPrice) . '</strong><br>
                            Ne rate pas cette chance, le stock part vite !
                        </p>
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="http://localhost/bander-sneakers/sneaker.php?id=' . $sneakerId . '" 
                               style="display: inline-block; padding: 15px 30px; background: #D32F2F; color: #fff; text-decoration: none; font-size: 18px; font-weight: bold; text-transform: uppercase; border-radius: 8px; box-shadow: 0 0 10px rgba(211, 47, 47, 0.5);">
                               Découvrir l’offre
                            </a>
                        </div>
                        <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #D32F2F;">
                            <p style="font-size: 14px; color: #888;">La Team <strong style="color: #D32F2F;">Bander-Sneakers</strong></p>
                            <p style="font-size: 12px; color: #999;">Désinscris-toi si ça te saoule.</p>
                        </div>
                    </div>
                </body>
            </html>';

        // Ajouter tous les abonnés en BCC pour préserver la confidentialité
        foreach ($subscribers as $subscriberEmail) {
            $mail->addBCC($subscriberEmail);
        }

        $mail->send();
        error_log("Newsletter de promotion envoyée à " . count($subscribers) . " abonnés pour sneakerId: $sneakerId");
        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de l'envoi de la newsletter : " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Récupérer les informations du profil utilisateur
 * @param int $user_id
 * @return array|null
 */
function getUserProfile($user_id) {
    try {
        $db = getDbConnection();
        $query = "SELECT username, email, created_at 
                  FROM users 
                  WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            error_log("Profil récupéré pour user_id: $user_id");
            return $user;
        }
        return null;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du profil utilisateur : " . $e->getMessage());
        return null;
    }
}

/**
 * Récupérer toutes les annonces d'un utilisateur
 * @param int $user_id
 * @return array
 */
function getUserProducts($user_id) {
    try {
        $db = getDbConnection();
        $query = "SELECT id, title, price, images, statut, created_at 
                  FROM secondhand_products 
                  WHERE user_id = :user_id AND statut != 'supprimé'
                  ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => $user_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Annonces récupérées pour user_id: $user_id, total: " . count($products));
        return $products;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des annonces : " . $e->getMessage());
        return [];
    }
}

/**
 * Compter le nombre de produits vendus par un utilisateur
 * @param int $user_id
 * @return int
 */
function getSoldCount($user_id) {
    try {
        $db = getDbConnection();
        $query = "SELECT COUNT(*) as sold_count 
                  FROM secondhand_products 
                  WHERE user_id = :user_id AND statut = 'vendu'";
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['sold_count'];
    } catch (PDOException $e) {
        error_log("Erreur lors du comptage des produits vendus : " . $e->getMessage());
        return 0;
    }
}

/**
 * Compter le nombre de signalements reçus par un utilisateur
 * @param int $user_id
 * @return int
 */
function getReportCount($user_id) {
    try {
        $db = getDbConnection();
        $query = "SELECT COUNT(*) as report_count 
                  FROM reports 
                  WHERE reported_user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['report_count'];
    } catch (PDOException $e) {
        error_log("Erreur lors du comptage des signalements : " . $e->getMessage());
        return 0;
    }
}

/**
 * Mettre à jour le statut d'une annonce et gérer les logs
 * @param int $product_id
 * @param string $statut
 * @param int $user_id
 * @return bool
 */
function updateProductStatus($product_id, $statut, $user_id) {
    try {
        $db = getDbConnection();
        $query = "UPDATE secondhand_products 
                  SET statut = :statut, updated_at = NOW() 
                  WHERE id = :product_id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $success = $stmt->execute([
            ':statut' => $statut,
            ':product_id' => $product_id,
            ':user_id' => $user_id
        ]);
        
        if ($success) {
            error_log("Statut mis à jour pour product_id: $product_id, nouveau statut: $statut");
            return true;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour du statut : " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifier si un utilisateur est abonné à un autre
 * @param int $subscriber_id
 * @param int $subscribed_to_id
 * @return bool
 */
function isSubscribed($subscriber_id, $subscribed_to_id) {
    try {
        $db = getDbConnection();
        $query = "SELECT COUNT(*) FROM subscriptions WHERE subscriber_id = :subscriber_id AND subscribed_to_id = :subscribed_to_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':subscriber_id' => $subscriber_id, ':subscribed_to_id' => $subscribed_to_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de l'abonnement : " . $e->getMessage());
        return false;
    }
}

/**
 * Compter le nombre d'abonnés d'un utilisateur
 * @param int $user_id
 * @return int
 */
function getSubscriberCount($user_id) {
    try {
        $db = getDbConnection();
        $query = "SELECT COUNT(*) FROM subscriptions WHERE subscribed_to_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => $user_id]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur lors du comptage des abonnés : " . $e->getMessage());
        return 0;
    }
}

/**
 * S'abonner ou se désabonner d'un utilisateur
 * @param int $subscriber_id
 * @param int $subscribed_to_id
 * @param bool $subscribe
 * @return bool
 */
function manageSubscription($subscriber_id, $subscribed_to_id, $subscribe) {
    try {
        $db = getDbConnection();
        if ($subscribe) {
            $query = "INSERT INTO subscriptions (subscriber_id, subscribed_to_id) VALUES (:subscriber_id, :subscribed_to_id)";
        } else {
            $query = "DELETE FROM subscriptions WHERE subscriber_id = :subscriber_id AND subscribed_to_id = :subscribed_to_id";
        }
        $stmt = $db->prepare($query);
        $success = $stmt->execute([':subscriber_id' => $subscriber_id, ':subscribed_to_id' => $subscribed_to_id]);
        
        if ($success && $subscribe) {
            // Ajouter une notification pour l'utilisateur abonné
            $message = "Vous vous êtes abonné à " . getUserProfile($subscribed_to_id)['username'];
            createNotification($subscriber_id, $message, 'subscription', null);
        }
        return $success;
    } catch (PDOException $e) {
        error_log("Erreur lors de la gestion de l'abonnement : " . $e->getMessage());
        return false;
    }
}

/**
 * Créer une notification
 * @param int $user_id
 * @param string $message
 * @param string $type
 * @param int|null $related_id
 * @return bool
 */
function createNotification($user_id, $message, $type, $related_id = null) {
    try {
        $db = getDbConnection();
        $query = "INSERT INTO notifications (user_id, message, type, related_id, is_read) 
                  VALUES (:user_id, :message, :type, :related_id, 0)";
        $stmt = $db->prepare($query);
        return $stmt->execute([
            ':user_id' => $user_id,
            ':message' => $message,
            ':type' => $type,
            ':related_id' => $related_id
        ]);
    } catch (PDOException $e) {
        error_log("Erreur lors de la création de la notification : " . $e->getMessage());
        return false;
    }
}

/**
 * Notifier les abonnés lorsqu'un utilisateur publie une nouvelle annonce
 * @param int $user_id
 * @param int $product_id
 * @param string $product_title
 * @return bool
 */
function notifySubscribersOfNewProduct($user_id, $product_id, $title) {
    $db = getDbConnection();

    // Récupérer les abonnés de l'utilisateur
    $query = "SELECT subscriber_id FROM subscriptions WHERE subscribed_to_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($subscribers) {
        // Préparer le message de notification
        $message = "✔️ Nouvelle annonce de " . getUsernameById($user_id) . " : " . htmlspecialchars($title);

        // Insérer une notification pour chaque abonné
        $insertQuery = "INSERT INTO notifications (user_id, message, type, related_id, created_at) 
                        VALUES (:user_id, :message, 'price_change', :related_id, NOW())";
        $insertStmt = $db->prepare($insertQuery);

        foreach ($subscribers as $subscriber) {
            $insertStmt->execute([
                ':user_id' => $subscriber['subscriber_id'], // L'abonné qui reçoit la notification
                ':message' => $message,
                ':related_id' => $product_id,               // L'ID du produit (remplace product_id)
            ]);
        }
    }
}

function getUsernameById($user_id) {
    $db = getDbConnection();
    $query = "SELECT username FROM users WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ? $user['username'] : 'Utilisateur inconnu';
}



error_log("Fin de functions.php - Chargement terminé");