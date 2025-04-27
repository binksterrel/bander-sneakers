<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Administration - Bander-Sneakers'; ?></title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Styles spécifiques au menu déroulant */
        .user-dropdown {
            position: relative;
        }

        .user-dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--primary-color); /* Ajustez selon votre thème */
        }

        .user-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
            min-width: 150px;
            z-index: 1000;
        }

        .user-dropdown-menu.active {
            display: block;
        }

        .user-dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: #333;
        }

        .user-dropdown-menu a:hover {
            background: #f5f5f5;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-header">
            <h1>Bander-Sneakers</h1>
            <span>Administration</span>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li>
                    <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-chart-pie"></i> Tableau de bord
                    </a>
                </li>
                <li>
                    <a href="admin-chat.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin-chat.php' ? 'active' : ''; ?>">
                        <i class="fas fa-comments"></i> Chat Admin 
                    </a>
                </li>
                <li>
                    <a href="products.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-bag"></i> Produits
                    </a>
                </li>
                <li>
                    <a href="orders.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i> Commandes
                    </a>
                </li>
                <li>
                    <a href="categories.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> Catégories
                    </a>
                </li>
                <li>
                    <a href="brands.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'brands.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tag"></i> Marques
                    </a>
                </li>
                <li>
                    <a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Utilisateurs
                    </a>
                </li>
                <li>
                    <a href="reviews.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : ''; ?>">
                        <i class="fas fa-star"></i> Avis
                    </a>
                </li>
                <li>
    <a href="secondhand.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'secondhand.php' ? 'active' : ''; ?>">
        <i class="fas fa-recycle"></i> 2ndHand
    </a>
</li>
<li>
    <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
        <i class="fas fa-flag"></i> Signalements
    </a>
</li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <a href="../index.php" target="_blank">
                <i class="fas fa-external-link-alt"></i> Voir le site
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="admin-main">
        <!-- Top Navigation -->
        <header class="admin-topbar">
            <div class="topbar-toggle">
                <button id="sidebar-toggle"><i class="fas fa-bars"></i></button>
            </div>

            <div class="topbar-title">
                <?php echo isset($page_title) ? $page_title : 'Administration'; ?>
            </div>

            <div class="topbar-user">
                <div class="user-dropdown">
                    <a href="#" class="user-dropdown-toggle" id="userDropdownToggle">
                        <span class="user-name"><?php echo $_SESSION['username']; ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </a>
                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        <a href="profile.php"><i class="fas fa-user"></i> Profil</a>
                        <a href="settings.php"><i class="fas fa-cogs"></i> Paramètres</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="admin-content-wrapper">

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggle = document.getElementById('userDropdownToggle');
        const menu = document.getElementById('userDropdownMenu');

        // Ouvrir/fermer le menu au clic sur le toggle
        toggle.addEventListener('click', function(e) {
            e.preventDefault(); // Empêche le lien de rediriger
            menu.classList.toggle('active');
        });

        // Fermer le menu si on clique ailleurs sur la page
        document.addEventListener('click', function(e) {
            if (!toggle.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('active');
            }
        });

        // Empêcher la fermeture du menu quand on clique à l'intérieur
        menu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    </script>
</body>
</html>