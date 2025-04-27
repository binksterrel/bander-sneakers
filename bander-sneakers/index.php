<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pdo = getDbConnection();
$featuredSneakers = getSneakers(['is_featured' => 1], 4);
$newArrivals = getSneakers(['is_new_arrival' => 1], 8);
$brands = getBrands();

// Filtrer les marques pour exclure brand_id = 6 ("Autres")
$filteredBrands = array_filter($brands, function($brand) {
    return $brand['brand_id'] != 6;
});

$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$page_title = "Bander-Sneakers - Votre destination pour les sneakers premium";
$page_description = "Découvrez, achetez et partagez les sneakers que vous aimez avec Bander-Sneakers.";
include 'includes/header.php';
?>

<style>
    /* Styles inchangés pour le chat et le programme de fidélité */
    .chat-button {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: 50%;
        width: 60px;
        height: 60px;
        font-size: 24px;
        cursor: pointer;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s ease-in-out;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 999;
    }
    .chat-button:hover {
        transform: scale(1.1);
    }
    .chat-button img {
        width: 30px;
    }
    .chat-modal {
        display: none;
        position: fixed;
        bottom: 90px;
        right: 20px;
        width: 350px;
        max-height: 500px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 1000;
        flex-direction: column;
    }
    .chat-header {
        background: var(--primary-color, #007bff);
        color: white;
        padding: 10px 15px;
        border-radius: 8px 8px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .chat-header span {
        font-weight: bold;
    }
    .chat-body {
        padding: 15px;
        overflow-y: auto;
        flex: 1;
    }
    .message {
        margin-bottom: 10px;
        padding: 10px;
        border-radius: 5px;
        max-width: 80%;
    }
    .message.assistant {
        background: #f1f1f1;
        margin-left: auto;
    }
    .message.user {
        background: var(--primary-color, #007bff);
        color: white;
        margin-right: auto;
    }
    .message .time {
        font-size: 0.8rem;
        color: #999;
        display: block;
        margin-top: 5px;
    }
    .chat-footer {
        padding: 10px;
        display: flex;
        border-top: 1px solid #eee;
    }
    .chat-footer input {
        flex: 1;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px 0 0 4px;
        outline: none;
    }
    .chat-footer button {
        padding: 8px 15px;
        background: var(--primary-color, #007bff);
        color: white;
        border: none;
        border-radius: 0 4px 4px 0;
        cursor: pointer;
    }
    .chat-footer button:hover {
        background: rgb(0, 0, 0);
    }
    .fullscreen-btn, .close-btn {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        font-size: 1rem;
    }
    .chat-modal.fullscreen {
        width: 100%;
        height: 100%;
        bottom: 0;
        right: 0;
        max-height: none;
    }
    .loyalty-promo {
        background: linear-gradient(135deg, #ffd700 0%, #ffec80 100%);
        border-radius: 10px;
        padding: 30px;
        text-align: center;
        margin: 40px 0;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    .loyalty-promo .loyalty-icon {
        font-size: 3rem;
        color: #fff;
        margin-bottom: 15px;
    }
    .loyalty-promo h2 {
        font-size: 1.8rem;
        color: #333;
        margin-bottom: 10px;
    }
    .loyalty-promo p {
        color: #555;
        margin-bottom: 20px;
    }
    .loyalty-promo .btn {
        background-color: #fff;
        color: #333;
        border: 2px solid #333;
        padding: 10px 20px;
        font-weight: bold;
        transition: all 0.3s ease;
    }
    .loyalty-promo .btn:hover {
        background-color: #333;
        color: #fff;
    }
    /* Ajout de styles pour uniformiser les images comme dans sneakers.php */
    .product-card .product-image {
        position: relative;
        overflow: hidden;
        height: 250px; /* Hauteur fixe pour uniformité */
    }
    .product-card .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover; /* Garde les proportions sans couper */
        transition: transform 0.3s ease;
    }
    .product-card .product-image:hover img {
        transform: scale(1.05);
    }
</style>

<?php if ($success_message || $error_message): ?>
<div class="alert-container">
    <div class="container">
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<section class="hero">
    <div class="hero-overlay"></div>
    <img src="assets/images/banner.png" alt="Bander-Sneakers Collection" class="hero-image">
    <div class="hero-content">
        <h2 class="hero-subtitle">Nouvelle collection</h2>
        <h1 class="hero-title">Préparez-vous pour la saison</h1>
        <p class="hero-description">Découvrez notre nouvelle collection de sneakers pour la saison et trouvez votre paire parfaite.</p>
        <a href="sneakers.php" class="btn btn-primary">Découvrir</a>
        <a href="promotions.php" class="btn btn-outline">Voir les promotions</a>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Produits Vedettes</h2>
            <p class="section-subtitle">Notre sélection de sneakers incontournables du moment.</p>
        </div>
        <div class="product-grid">
            <?php foreach ($featuredSneakers as $sneaker): ?>
                <div class="product-card">
                    <div class="product-image">
                        <?php if ($sneaker['is_new_arrival']): ?>
                            <div class="product-badge new">Nouveau</div>
                        <?php endif; ?>
                        <?php if ($sneaker['discount_price']): ?>
                            <div class="product-badge sale">-<?= calculateDiscount($sneaker['price'], $sneaker['discount_price']) ?>%</div>
                        <?php endif; ?>
                        <img src="assets/images/sneakers/<?= htmlspecialchars($sneaker['primary_image']) ?>" alt="<?= htmlspecialchars($sneaker['sneaker_name']) ?>">
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
        <div class="text-center mt-4">
            <a href="sneakers.php?is_featured=1" class="btn btn-primary">Voir tous les produits vedettes</a>
        </div>
    </div>
</section>

<section class="banner">
    <div class="container">
        <div class="banner-grid">
            <div class="banner-item">
                <img src="assets/images/hommes.png" alt="Collection Homme">
                <div class="banner-content">
                    <h2>Collection Homme</h2>
                    <p>Trouvez votre style avec notre collection homme.</p>
                    <a href="hommes.php" class="btn btn-outline">Découvrir</a>
                </div>
            </div>
            <div class="banner-item">
                <img src="assets/images/femmes.png" alt="Collection Femme">
                <div class="banner-content">
                    <h2>Collection Femme</h2>
                    <p>Élégantes et confortables pour toutes les occasions.</p>
                    <a href="femmes.php" class="btn btn-outline">Découvrir</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Nouveautés</h2>
            <p class="section-subtitle">Les dernières sneakers tout juste arrivées dans notre boutique.</p>
        </div>
        <div class="product-grid">
            <?php foreach ($newArrivals as $sneaker): ?>
                <div class="product-card">
                    <div class="product-image">
                        <?php if ($sneaker['is_new_arrival']): ?>
                            <div class="product-badge new">Nouveau</div>
                        <?php endif; ?>
                        <?php if ($sneaker['discount_price']): ?>
                            <div class="product-badge sale">-<?= calculateDiscount($sneaker['price'], $sneaker['discount_price']) ?>%</div>
                        <?php endif; ?>
                        <img src="assets/images/sneakers/<?= htmlspecialchars($sneaker['primary_image']) ?>" alt="<?= htmlspecialchars($sneaker['sneaker_name']) ?>">
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
        <div class="text-center custom-margin">
            <a href="sneakers.php?is_new_arrival=1" class="btn btn-primary">Voir toutes les nouveautés</a>
        </div>
    </div>
</section>

<!-- Section Programme de fidélité -->
<section class="section">
    <div class="container">
        <div class="loyalty-promo">
            <div class="loyalty-icon">
                <i class="fas fa-star"></i>
            </div>
            <h2>Rejoignez notre programme de fidélité</h2>
            <p>Gagnez des points à chaque achat et échangez-les contre des réductions exclusives !</p>
            <a href="<?= isset($_SESSION['user_id']) ? 'compte.php#loyalty' : 'login.php' ?>" class="btn">
                <?= isset($_SESSION['user_id']) ? 'Voir mes points' : 'S\'inscrire maintenant' ?>
            </a>
        </div>
    </div>
</section>

<section class="section brands-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Nos Marques</h2>
            <p class="section-subtitle">Nous proposons les meilleures marques de sneakers du marché.</p>
        </div>
        <div class="brands-grid">
            <?php foreach ($filteredBrands as $brand): ?>
                <div class="brand-item">
                    <a href="sneakers.php?brand_id=<?= $brand['brand_id'] ?>">
                        <img src="assets/images/brands/<?= $brand['brand_logo'] ?>" alt="<?= $brand['brand_name'] ?>">
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section features-section">
    <div class="container">
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <h3>Livraison Gratuite</h3>
                <p>Livraison gratuite pour toutes les commandes de plus de 100€.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-undo"></i>
                </div>
                <h3>Retours Faciles</h3>
                <p>Retours gratuits sous 30 jours pour tous les articles.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Paiement Sécurisé</h3>
                <p>Vos transactions sont 100% sécurisées avec nous.</p>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h3>Support 24/7</h3>
                <p>Notre équipe de support est disponible 24/7 pour vous aider.</p>
            </div>
        </div>
    </div>
</section>

<button class="chat-button" onclick="toggleChat()">
    <img src="assets/images/chata.png" alt="Chat">
</button>

<div class="chat-modal" id="chatModal">
    <div class="chat-header">
        <span>Bander Assistant</span>
        <button class="fullscreen-btn" onclick="toggleFullscreen()">⛶</button>
        <button class="close-btn" onclick="closeChat()">✖</button>
    </div>
    <div class="chat-body" id="chatBody">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="message assistant">
                <strong>Bander Assistant :</strong> Bonjour ! Connectez-vous pour discuter avec un conseiller.
                <span class="time"><?= date('H:i') ?></span>
            </div>
        <?php else: ?>
            <div class="message assistant">
                <strong>Bander Assistant :</strong> Bonjour ! Comment puis-je vous aider aujourd’hui ?
                <span class="time"><?= date('H:i') ?></span>
            </div>
        <?php endif; ?>
    </div>
    <div class="chat-footer">
        <input type="text" id="chatInput" placeholder="Tapez votre message..." onkeydown="if(event.key==='Enter') sendMessage()" <?= !isset($_SESSION['user_id']) ? 'disabled' : '' ?>>
        <button onclick="sendMessage()" <?= !isset($_SESSION['user_id']) ? 'disabled' : '' ?>>
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<script>
function toggleChat() {
    console.log('Toggle chat clicked');
    const chatModal = document.getElementById('chatModal');
    chatModal.style.display = chatModal.style.display === 'flex' ? 'none' : 'flex';
    if (chatModal.style.display === 'flex') {
        loadMessages();
        scrollToBottom();
    }
}

function closeChat() {
    console.log('Close chat clicked');
    document.getElementById('chatModal').style.display = 'none';
}

function toggleFullscreen() {
    console.log('Toggle fullscreen clicked');
    const chatModal = document.getElementById('chatModal');
    chatModal.classList.toggle('fullscreen');
}

function sendMessage() {
    console.log('Send message triggered');
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if (!message) {
        console.log('Message vide, envoi annulé');
        return;
    }

    console.log('Sending message:', message);
    fetch('chat-api.php?action=send_message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `message=${encodeURIComponent(message)}`
    })
    .then(response => {
        console.log('Response received:', response);
        if (!response.ok) throw new Error('Erreur réseau: ' + response.status);
        return response.json();
    })
    .then(data => {
        console.log('Data parsed:', data);
        if (data.success) {
            console.log('Message envoyé avec succès');
            loadMessages();
            input.value = '';
        } else {
            console.error('Erreur serveur:', data.error);
        }
    })
    .catch(error => console.error('Erreur AJAX:', error));
}

function loadMessages() {
    console.log('Loading messages');
    fetch('chat-api.php?action=get_messages')
    .then(response => {
        console.log('Response received:', response);
        if (!response.ok) throw new Error('Erreur réseau: ' + response.status);
        return response.json();
    })
    .then(data => {
        console.log('Messages received:', data);
        const chatBody = document.getElementById('chatBody');
        if (data.success && data.messages && data.messages.length > 0) {
            chatBody.innerHTML = '';
            data.messages.forEach(msg => {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${msg.is_admin ? 'assistant' : 'user'}`;
                const prefix = msg.is_admin ? '<strong>Bander :</strong> ' : '<strong>Vous :</strong> ';
                messageDiv.innerHTML = `${prefix}${msg.message_text}<span class="time">${new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>`;
                chatBody.appendChild(messageDiv);
            });
        } else if (!<?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>) {
            chatBody.innerHTML = '<div class="message assistant"><strong>Bander :</strong> Bonjour ! Connectez-vous pour discuter avec un conseiller.<span class="time">' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + '</span></div>';
        } else {
            chatBody.innerHTML = '<div class="message assistant"><strong>Bander :</strong> Bonjour ! Comment puis-je vous aider aujourd’hui ?<span class="time">' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + '</span></div>';
        }
        scrollToBottom();
    })
    .catch(error => console.error('Erreur AJAX:', error));
}

function scrollToBottom() {
    console.log('Scrolling to bottom');
    const chatBody = document.getElementById('chatBody');
    chatBody.scrollTop = chatBody.scrollHeight;
}

setInterval(() => {
    if (document.getElementById('chatModal').style.display === 'flex') {
        console.log('Interval: Checking for new messages');
        loadMessages();
    }
}, 5000);

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded');
    if (<?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>) {
        loadMessages();
    }


// Gestion de l'ajout à la wishlist avec AJAX
const wishlistBtns = document.querySelectorAll('.wishlist-btn');
    wishlistBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault(); // Empêche la navigation par défaut
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
                    window.location.href = data.redirect; // Redirection si non connecté
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
    
    // Fonction pour afficher un toast avec icône personnalisée
    function showToast(message, isError = false, iconType = 'heart') {
        const existingToast = document.getElementById('confirmation-toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.id = 'confirmation-toast';
        const iconClass = isError ? '' : (iconType === 'cart' ? 'fas fa-shopping-cart' : 'fas fa-heart');
        toast.innerHTML = `${isError ? '' : `<i class="${iconClass}" style="margin-right: 10px;"></i>`}${message}`;
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

<style>

.chat-body {
    padding: 10px;
}

.message {
    max-width: 70%;
    margin: 5px 0;
    padding: 8px 12px;
    border-radius: 5px;
}

.message.assistant {
    float: left;
    background-color: #f0f0f0;
    margin-right: 30%;
}

.message.user {
    float: right;
    background-color: var(--primary-color);
    color: white;
    margin-left: 30%;
}

.message:after {
    content: "";
    display: table;
    clear: both;
}

</style>

<?php include 'includes/footer.php'; ?>