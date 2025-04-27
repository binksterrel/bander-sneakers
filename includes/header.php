<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Initialiser le panier si nécessaire
if (!isset($_SESSION['cart_id'])) {
    // Créer un panier pour l'utilisateur connecté ou pour la session
    $db = getDbConnection();

    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $sessionId = session_id();

    $sql = "INSERT INTO cart (user_id, session_id) VALUES (:user_id, :session_id)";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':user_id', $userId, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindParam(':session_id', $sessionId);
    $stmt->execute();

    $_SESSION['cart_id'] = $db->lastInsertId();
}

// Récupérer le nombre d'articles dans le panier
function getCartItemCount() {
    if (!isset($_SESSION['cart_id'])) {
        return 0;
    }

    $db = getDbConnection();
    $cartId = $_SESSION['cart_id'];

    $sql = "SELECT SUM(quantity) as total FROM cart_items WHERE cart_id = :cart_id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':cart_id', $cartId, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch();
    return $result['total'] ? $result['total'] : 0;
}

$cartItemCount = getCartItemCount();

// Récupérer les catégories et marques pour le menu
$categories = getCategories();
$brands = getBrands();

// Récupérer le nombre de notifications non lues pour les utilisateurs connectés
$unread_notifications_count = isLoggedIn() ? countUnreadNotifications($_SESSION['user_id']) : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Bander-Sneakers - Votre destination pour les sneakers'; ?></title>
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : 'Bander-Sneakers - Votre destination pour les sneakers de marque. Découvrez notre collection de Nike, Adidas, Jordan et plus encore.'; ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="container">
                <div class="top-bar-left">
                    <a href="contact.php" class="top-link">Contact</a>
                    <a href="about.php" class="top-link">À propos</a>
                </div>
                <div class="top-bar-right">
                    <?php if (isLoggedIn()): ?>
                        <a href="compte.php" class="top-link">Mon compte</a>
                        <a href="logout.php" class="top-link">Déconnexion</a>
                    <?php else: ?>
                        <a href="login.php" class="top-link">Connexion</a>
                        <a href="register.php" class="top-link">Inscription</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Header -->
        <div class="main-header">
            <div class="container">
                <div class="logo">
                    <a href="index.php">
                        <h1>Bander-Sneakers</h1>
                    </a>
                </div>

                <nav class="main-nav">
                    <ul class="nav-list">
                        <li class="nav-item">
                            <a href="index.php" class="nav-link">Accueil</a>
                        </li>
                        <li class="nav-item">
                            <a href="sneakers.php" class="nav-link">Sneakers</a>
                            <div class="mega-menu">
                                <div class="menu-column">
                                    <h3>Marques</h3>
                                    <ul>
                                        <li><a href="sneakers.php?brand_id=1">Nike</a></li>
                                        <li><a href="sneakers.php?brand_id=2">Adidas</a></li>
                                        <li><a href="sneakers.php?brand_id=5">Jordan</a></li>
                                        <li><a href="sneakers.php?brand_id=3">Puma</a></li>
                                        <li><a href="sneakers.php?brand_id=4">New Balance</a></li>
                                        <li><a href="sneakers.php?brand_id=6">Autres</a></li>
                                    </ul>
                                </div>
                                <div class="menu-column">
                                    <h3>Catégories</h3>
                                    <ul>
                                        <li><a href="sneakers.php?category_id=1">Running</a></li>
                                        <li><a href="sneakers.php?category_id=2">Basketball</a></li>
                                        <li><a href="sneakers.php?category_id=3">Lifestyle</a></li>
                                        <li><a href="sneakers.php?category_id=4">Skate</a></li>
                                        <li><a href="sneakers.php?category_id=5">Limited Edition</a></li>
                                    </ul>
                                </div>
                                <div class="menu-column">
                                    <h3>Collections</h3>
                                    <ul>
                                        <li><a href="sneakers.php?is_new_arrival=1">Nouveautés</a></li>
                                        <li><a href="sneakers.php?is_featured=1">Produits Vedettes</a></li>
                                        <li><a href="promotions.php">Promotions</a></li>
                                    </ul>
                                </div>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a href="hommes.php" class="nav-link">Hommes</a>
                        </li>
                        <li class="nav-item">
                            <a href="femmes.php" class="nav-link">Femmes</a>
                        </li>
                        <li class="nav-item">
                            <a href="enfants.php" class="nav-link">Enfants</a>
                        </li>
                        <li class="nav-item">
                            <a href="promotions.php" class="nav-link">Promotions</a>
                        </li>
                        <li class="nav-item">
                            <a href="2ndhand.php" class="nav-link">2nd'H</a>
                        </li>
                    </ul>
                </nav>

                <div class="header-actions">
                    <div class="search-box">
                        <form action="search.php" method="GET">
                            <input type="text" name="q" placeholder="Rechercher...">
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>

                    <a href="wishlist.php" class="wishlist-icon">
                        <i class="fas fa-heart"></i>
                    </a>

                    <a href="cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cartItemCount; ?></span>
                    </a>

                    <?php if (isLoggedIn()): ?>
                        <div class="notifications-icon">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_notifications_count > 0): ?>
                                <span class="cart-count"><?php echo $unread_notifications_count; ?></span>
                            <?php endif; ?>
                            <div class="notifications-dropdown" id="notifications-dropdown">
                                <div class="dropdown-header">
                                    <h3>Notifications</h3>
                                </div>
                                <div class="notifications-list" id="notifications-list">
                                    <!-- Les notifications seront chargées via AJAX -->
                                </div>
                                <div class="dropdown-footer">
                                    <a href="notifications.php" class="view-all-notifications">Voir plus de notifications</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Script AJAX pour les notifications -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const bellIcon = document.querySelector('.notifications-icon');
        const dropdown = document.getElementById('notifications-dropdown');
        const viewAllLink = document.querySelector('.view-all-notifications');
        let isOpen = false;

        function loadNotifications() {
            fetch('get-notifications.php')
                .then(response => response.json())
                .then(data => {
                    const list = document.getElementById('notifications-list');
                    list.innerHTML = '';
                    if (data.notifications.length === 0) {
                        list.innerHTML = '<p class="no-notifications">Aucune nouvelle notification</p>';
                    } else {
                        data.notifications.forEach(notif => {
                            const timeAgo = new Date(notif.created_at).toLocaleString('fr-FR', {
                                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                            });
                            let viewButton = '';
                            if (notif.type === 'message' && notif.related_id) {
                                viewButton = `<a href="chat.php?conversation_id=${notif.related_id}" class="view-notif" onclick="event.stopPropagation();">Voir</a>`;
                            } else if (notif.type === 'price_change' && notif.related_id) {
                                viewButton = `<a href="sneaker.php?id=${notif.related_id}" class="view-notif" onclick="event.stopPropagation();">Voir</a>`;
                            } else if (notif.type === 'points_purchase' && notif.related_id) {
                                viewButton = `<a href="order-details.php?id=${notif.related_id}" class="view-notif" onclick="event.stopPropagation();">Voir</a>`;
                            } else if (notif.type === 'points_spin') {
                                viewButton = `<a href="spin.php" class="view-notif" onclick="event.stopPropagation();">Voir</a>`;
                            }
                            list.innerHTML += `
                                <div class="notification-item" data-id="${notif.notification_id}">
                                    <p>${notif.message}</p>
                                    <span class="time">${timeAgo}</span>
                                    <div class="notification-actions">
                                        ${viewButton}
                                        <button class="mark-read" data-id="${notif.notification_id}">Marquer comme lu</button>
                                        <button class="delete-notif" data-id="${notif.notification_id}"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                            `;
                        });
                    }
                    attachEventListeners();
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des notifications :', error);
                });
        }

        function attachEventListeners() {
            document.querySelectorAll('.mark-read').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    fetch('manage-notifications.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=mark_read&id=${id}`
                    }).then(() => {
                        loadNotifications();
                        updateNotificationCount();
                    });
                });
            });

            document.querySelectorAll('.delete-notif').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    fetch('manage-notifications.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete&id=${id}`
                    }).then(() => {
                        loadNotifications();
                        updateNotificationCount();
                    });
                });
            });
        }

        function updateNotificationCount() {
            fetch('get-notifications.php')
                .then(response => response.json())
                .then(data => {
                    const count = data.count;
                    const countElement = document.querySelector('.notifications-icon .cart-count');
                    if (count > 0) {
                        if (!countElement) {
                            const span = document.createElement('span');
                            span.className = 'cart-count';
                            span.textContent = count;
                            bellIcon.appendChild(span);
                        } else {
                            countElement.textContent = count;
                        }
                    } else if (countElement) {
                        countElement.remove();
                    }
                });
        }

        if (bellIcon) {
            bellIcon.addEventListener('click', function(e) {
                e.preventDefault();
                if (!isOpen) {
                    loadNotifications();
                    dropdown.style.display = 'block';
                    isOpen = true;
                } else {
                    dropdown.style.display = 'none';
                    isOpen = false;
                }
            });

            // Empêcher la fermeture du dropdown lors du clic sur "Voir plus de notifications"
            viewAllLink.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            document.addEventListener('click', function(e) {
                if (!bellIcon.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                    isOpen = false;
                }
            });

            // Charger le compteur au démarrage
            updateNotificationCount();
        }
    });
    </script>

    <style>
    .notifications-icon {
        position: relative;
        margin-left: 19px;
        cursor: pointer;
        font-size: 1.2rem;
        color: #333;
    }
    .notifications-icon:hover i {
        color: #e74c3c; /* Rouge au survol */
    }
    .notifications-dropdown {
        display: none;
        position: absolute;
        top: 40px;
        right: 0;
        width: 300px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
    }
    .dropdown-header {
        padding: 10px;
        border-bottom: 1px solid #eee;
    }
    .dropdown-header h3 {
        margin: 0;
        font-size: 16px;
    }
    .notifications-list {
        max-height: 300px;
        overflow-y: auto;
        padding: 10px;
    }
    .notification-item {
        padding: 10px;
        border-bottom: 1px solid #eee;
    }
    .notification-item p {
        margin: 0 0 5px;
        font-size: 14px;
    }
    .notification-item .time {
        font-size: 12px;
        color: #777;
    }
    .notification-actions {
        display: flex;
        justify-content: space-between;
        margin-top: 5px;
    }
    .mark-read {
        background: none;
        border: none;
        color: var(--primary-color);
        cursor: pointer;
        font-size: 12px;
    }
    .delete-notif {
        background: none;
        border: none;
        color: #e74c3c;
        cursor: pointer;
    }
    .view-notif {
        background: none;
        border: none;
        color: #ff3e3e;
        cursor: pointer;
        font-size: 12px;
        text-decoration: none;
    }
    .view-notif:hover {
        text-decoration: underline;
    }
    .no-notifications {
        text-align: center;
        color: #777;
        padding: 10px;
    }
    .dropdown-footer {
        padding: 10px;
        text-align: center;
        border-top: 1px solid #eee;
    }
    .dropdown-footer a {
        color: var(--primary-color);
        text-decoration: none;
    }
    </style>
</body>
</html>