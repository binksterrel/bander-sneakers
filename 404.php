<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = "Page non trouvée - Bander-Sneakers";
$page_description = "La page que vous recherchez n'existe pas ou a été déplacée.";
include 'includes/header.php';

// Récupérer quelques produits populaires pour les suggestions
$pdo = getDbConnection();
$popularSneakers = getSneakers(['is_featured' => 1], 4);
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">Page non trouvée</li>
        </ul>
    </div>
</div>

<!-- 404 Error Section -->
<section class="section error-section">
    <div class="container">
        <div class="error-container text-center">
            <div class="error-code">404</div>
            <h1 class="error-title">Page non trouvée</h1>
            <p class="error-description">
                Oups ! La page que vous recherchez n'existe pas ou a été déplacée.
            </p>
            
            <!-- Search Bar -->
            <div class="error-search">
                <form action="search.php" method="get" class="search-form" onsubmit="return validateSearch(this)">
                    <div class="search-input-group">
                        <input type="text" name="q" placeholder="Rechercher des sneakers..." class="search-input" required>
                        <button type="submit" class="search-button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="error-actions">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Retour à l'accueil
                </a>
                <a href="sneakers.php" class="btn btn-primary">
                    <i class="fas fa-shoe-prints"></i> Voir toutes les sneakers
                </a>
                <button onclick="history.back()" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Page précédente
                </button>
            </div>
        </div>
    </div>
</section>

<!-- Suggestions Section -->
<section class="section suggestions-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Vous pourriez aimer</h2>
            <p class="section-subtitle">Découvrez nos sneakers populaires</p>
        </div>
        
        <div class="product-grid">
            <?php foreach ($popularSneakers as $sneaker): ?>
                <div class="product-card">
                    <div class="product-image">
                        <?php if ($sneaker['is_new_arrival']): ?>
                            <div class="product-badge new">Nouveau</div>
                        <?php endif; ?>
                        <?php if ($sneaker['discount_price']): ?>
                            <div class="product-badge sale">-<?= calculateDiscount($sneaker['price'], $sneaker['discount_price']) ?>%</div>
                        <?php endif; ?>
                        <img src="assets/images/sneakers/<?= htmlspecialchars($sneaker['primary_image']) ?>" alt="<?= htmlspecialchars($sneaker['sneaker_name']) ?>" loading="lazy">
                        <div class="product-actions">
                            <a href="wishlist-add.php?id=<?= $sneaker['sneaker_id'] ?>" class="action-btn wishlist-btn" title="Ajouter aux favoris">
                                <i class="far fa-heart"></i>
                            </a>
                            <a href="sneaker.php?id=<?= $sneaker['sneaker_id'] ?>" class="action-btn view-btn" title="Voir le produit">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>
                    <div class="product-info">
                        <div class="product-brand"><?= htmlspecialchars($sneaker['brand_name']) ?></div>
                        <h3 class="product-title">
                            <a href="sneaker.php?id=<?= $sneaker['sneaker_id'] ?>"><?= htmlspecialchars($sneaker['sneaker_name']) ?></a>
                        </h3>
                        <div class="product-price">
                            <?php if ($sneaker['discount_price']): ?>
                                <span class="current-price"><?= formatPrice($sneaker['discount_price']) ?></span>
                                <span class="original-price"><?= formatPrice($sneaker['price']) ?></span>
                            <?php else: ?>
                                <span class="current-price"><?= formatPrice($sneaker['price']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<style>
    .error-section {
        padding: 60px 0;
    }
    
    .error-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 40px 20px;
    }
    
    .error-code {
        font-size: 120px;
        font-weight: 700;
        color: var(--primary-color);
        line-height: 1;
        margin-bottom: 20px;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .error-title {
        font-size: 32px;
        margin-bottom: 20px;
        color: #333;
    }
    
    .error-description {
        font-size: 18px;
        color: #666;
        margin-bottom: 30px;
    }
    
    .error-search {
        margin: 0 auto 30px;
        display: flex;
        justify-content: center;
        align-items: center;
        width: 100%;
    }
    
    .search-form {
        width: 100%;
        max-width: 500px;
    }
    
    .search-input-group {
        display: flex;
        border: 2px solid #ddd;
        border-radius: 30px;
        overflow: hidden;
        width: 100%;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .search-input {
        flex: 1;
        padding: 12px 20px;
        border: none;
        outline: none;
        font-size: 16px;
    }
    
    .search-input:focus {
        border: none;
        outline: none;
    }
    
    .search-input-group:focus-within {
        border-color: var(--primary-color);
    }
    
    .search-button {
        background-color: var(--primary-color);
        color: white;
        border: none;
        padding: 0 25px;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    
    .search-button:hover {
        background-color: #000;
    }
    
    .error-actions {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin-top: 30px;
    }
    
    .error-actions .btn {
        padding: 12px 25px;
        font-weight: 600;
    }
    
    .btn-secondary {
        background-color: #6c757d;
        color: white;
        border: none;
        transition: background-color 0.3s;
    }
    
    .btn-secondary:hover {
        background-color: #5a6268;
    }
    
    .suggestions-section {
        background-color: #f9f9f9;
        padding-top: 40px;
    }
    
    @media (max-width: 768px) {
        .error-code {
            font-size: 100px;
        }
        
        .error-title {
            font-size: 28px;
        }
        
        .error-actions {
            flex-direction: column;
            align-items: center;
        }
        
        .error-actions .btn {
            width: 100%;
            max-width: 300px;
        }
    }
    
    @media (max-width: 480px) {
        .error-code {
            font-size: 80px;
        }
        
        .error-title {
            font-size: 24px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de l'ajout à la wishlist avec AJAX
    const wishlistBtns = document.querySelectorAll('.wishlist-btn');
    wishlistBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.getAttribute('href');

            fetch(url, {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.action === 'added') {
                        showToast('Ajouté aux favoris !', false, 'heart');
                        this.querySelector('i').classList.remove('far');
                        this.querySelector('i').classList.add('fas');
                    } else if (data.action === 'removed') {
                        showToast('Retiré des favoris.', false, 'heart');
                        this.querySelector('i').classList.remove('fas');
                        this.querySelector('i').classList.add('far');
                    }
                } else if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    showToast(data.message || 'Erreur lors de la mise à jour des favoris.', true);
                }
            })
            .catch(error => {
                console.error('Erreur AJAX :', error);
                showToast('Erreur réseau, veuillez réessayer.', true);
            });
        });
    });
    
    // Validation de la recherche
    window.validateSearch = function(form) {
        const query = form.q.value.trim();
        if (!query) {
            showToast('Veuillez entrer un terme de recherche.', true);
            return false;
        }
        return true;
    };
    
    // Fonction pour afficher un toast avec icône personnalisée
    function showToast(message, isError = false, iconType = 'heart') {
        const existingToast = document.getElementById('confirmation-toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.id = 'confirmation-toast';
        const iconClass = isError ? 'fas fa-exclamation-circle' : (iconType === 'cart' ? 'fas fa-shopping-cart' : 'fas fa-heart');
        toast.innerHTML = `<i class="${iconClass}" style="margin-right: 10px;"></i>${message}`;
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: ${isError ? '#dc3545' : '#28a745'};
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            font-size: 1rem;
            max-width: 300px;
            display: flex;
            align-items: center;
        `;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '1';
        }, 100);

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 3000);
    }
});
</script>

<?php include 'includes/footer.php'; ?>