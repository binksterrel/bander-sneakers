<?php
// Page À propos
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Titre et description de la page
$page_title = "À propos de Bander-Sneakers - Notre Histoire";
$page_description = "Découvrez l’histoire de Bander-Sneakers, une aventure née en 2025, portée par une passion pour les sneakers et l’innovation.";

// Inclure l'en-tête
include 'includes/header.php';
?>

<style>
    .about-hero {
        background: url('assets/images/about-hero-bg.jpg') center/cover no-repeat;
        padding: 100px 0;
        color: white;
        text-align: center;
        position: relative;
    }
    .about-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4);
    }
    .about-hero-content {
        position: relative;
        z-index: 1;
    }
    .about-hero h1 {
        font-size: 3rem;
        margin-bottom: 20px;
    }
    .about-section, .mission-section, .store-section, .cta-section {
        padding: 60px 0;
    }
    .about-grid, .store-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
        align-items: center;
    }
    .about-image img, .store-image img {
        width: 100%;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .mission-values {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 30px;
        margin-top: 30px;
    }
    .mission-value {
        display: flex;
        align-items: flex-start;
    }
    .value-icon {
        font-size: 24px;
        color: var(--primary-color, #007bff);
        margin-right: 15px;
    }
    .value-content h3 {
        margin: 0 0 10px;
        font-size: 1.2rem;
    }
    .store-info .info-item {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }
    .store-info i {
        margin-right: 10px;
        color: var(--primary-color, #007bff);
    }
    .cta-content {
    text-align: center;
    background: #f8f9fa;
    padding: 40px;
    border-radius: 8px;
}

.newsletter-form {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px; /* Augmenté pour un meilleur espacement */
    margin: 30px auto; /* Centré avec auto sur les côtés */
    max-width: 500px; /* Limite la largeur pour éviter un étirement excessif */
}

.newsletter-form input {
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    flex: 1; /* Prend l'espace disponible */
    min-width: 0; /* Permet au champ de rétrécir si nécessaire */
}

.newsletter-form button {
    padding: 12px 20px; /* Alignement avec l'input */
}

@media (max-width: 768px) {
    .newsletter-form {
        flex-direction: column;
        gap: 10px;
        max-width: 100%; /* Pleine largeur sur mobile */
    }
    .newsletter-form input, .newsletter-form button {
        width: 100%; /* Pleine largeur sur mobile */
        box-sizing: border-box; /* Inclut padding/border dans la largeur */
    }
}
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">À propos</li>
        </ul>
    </div>
</div>

<!-- About Hero Section -->
<section class="about-hero">
    <div class="container">
        <div class="about-hero-content">
            <h1>Notre Histoire</h1>
            <p>Une aventure née en 2025, guidée par la passion des sneakers et l’innovation</p>
        </div>
    </div>
</section>

<!-- About Section -->
<section class="about-section">
    <div class="container">
        <div class="about-grid">
            <div class="about-content" style="animation: fadeInUp 1s ease-out;">
                <h2>Une Vision, Deux Passionnés</h2>
                <p>Fondée en 2025 à Paris, Bander-Sneakers est le fruit d’une vision commune entre deux co-fondateurs, Terrel Nuentsa et Mathieu Siegel. Passionnés par les sneakers et la culture qui les entoure, ils ont uni leurs forces pour créer une plateforme unique dédiée aux amateurs de style et d’authenticité.</p>
                <p>Ce qui a débuté comme une idée audacieuse dans un monde en constante évolution s’est rapidement transformé en une destination incontournable pour les amoureux des sneakers. Leur objectif ? Offrir une sélection soigneusement choisie, mêlant classiques intemporels et dernières tendances, tout en plaçant l’expérience client au cœur de leur démarche.</p>
                <p>Aujourd’hui, Bander-Sneakers incarne bien plus qu’une boutique : c’est un espace où la passion rencontre l’innovation, où chaque paire raconte une histoire, et où chaque client fait partie d’une communauté grandissante.</p>
            </div>
            <div class="about-image" style="animation: fadeInUp 1.2s ease-out;">
                <img src="https://mir-s3-cdn-cf.behance.net/project_modules/fs/241ac4125283407.614b3afaa4bf1.jpg" alt="Univers Bander-Sneakers">
            </div>
        </div>
    </div>
</section>

<!-- Mission Section -->
<section class="mission-section">
    <div class="container">
        <div class="mission-content">
            <h2 style="animation: fadeInUp 1s ease-out;">Notre Mission</h2>
            <p style="animation: fadeInUp 1.1s ease-out;">Chez Bander-Sneakers, nous voulons redéfinir l’expérience d’achat de sneakers en combinant accessibilité, qualité et engagement envers notre communauté.</p>
            <div class="mission-values">
                <div class="mission-value" style="animation: fadeInUp 1.2s ease-out;">
                    <div class="value-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="value-content">
                        <h3>Authenticité Absolue</h3>
                        <p>Chaque paire est garantie 100% authentique, sourcée auprès de partenaires officiels.</p>
                    </div>
                </div>
                <div class="mission-value" style="animation: fadeInUp 1.3s ease-out;">
                    <div class="value-icon"><i class="fas fa-heart"></i></div>
                    <div class="value-content">
                        <h3>Passion Authentique</h3>
                        <p>Nous vivons et respirons sneakers, avec une équipe dédiée à partager cette passion.</p>
                    </div>
                </div>
                <div class="mission-value" style="animation: fadeInUp 1.4s ease-out;">
                    <div class="value-icon"><i class="fas fa-users"></i></div>
                    <div class="value-content">
                        <h3>Une Communauté Vivante</h3>
                        <p>Nous connectons les passionnés pour célébrer ensemble l’univers des sneakers.</p>
                    </div>
                </div>
                <div class="mission-value" style="animation: fadeInUp 1.5s ease-out;">
                    <div class="value-icon"><i class="fas fa-leaf"></i></div>
                    <div class="value-content">
                        <h3>Engagement Durable</h3>
                        <p>Nous intégrons des pratiques écoresponsables pour un avenir plus vert.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Store Section -->
<section class="store-section">
    <div class="container">
        <div class="store-grid">
            <div class="store-image" style="animation: fadeInUp 1s ease-out;">
                <img src="https://media.lesechos.com/api/v1/images/view/62c43ccd05b1eb57176ae7bc/1280x720/2398317-kith-un-americain-a-paris-web-tete-061533272847.jpg" alt="Boutique Bander-Sneakers Paris">
            </div>
            <div class="store-content" style="animation: fadeInUp 1.2s ease-out;">
                <h2>Notre Boutique</h2>
                <p>Plongez dans l’univers Bander-Sneakers en visitant notre boutique au cœur de Paris. Un lieu pensé pour les passionnés, où chaque visite est une expérience unique.</p>
                <div class="store-info">
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <p>123 Rue des Sneakers, 75000 Paris</p>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <p>+33 1 23 45 67 89</p>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <p>Lun-Sam : 10h-19h | Dim : Fermé</p>
                    </div>
                </div>
                <a href="contact.php" class="btn btn-primary">Nous Contacter</a>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content" style="animation: fadeInUp 1s ease-out;">
            <h2>Rejoignez Notre Univers</h2>
            <p>Abonnez-vous à notre newsletter pour ne rien manquer des nouveautés, exclusivités et événements spéciaux.</p>
            <form class="newsletter-form" action="newsletter-subscribe.php" method="post">
                <input type="email" name="email" placeholder="Votre adresse email" required>
                <button type="submit" class="btn btn-primary">S’abonner</button>
            </form>
            <div class="social-links">
                <a href="https://instagram.com/bandersneakers" target="_blank" class="social-link"><i class="fab fa-instagram"></i></a>
                <a href="https://facebook.com/bandersneakers" target="_blank" class="social-link"><i class="fab fa-facebook"></i></a>
                <a href="https://twitter.com/bandersneakers" target="_blank" class="social-link"><i class="fab fa-twitter"></i></a>
                <a href="https://tiktok.com/@bandersneakers" target="_blank" class="social-link"><i class="fab fa-tiktok"></i></a>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation au scroll
    const sections = document.querySelectorAll('.about-section, .mission-section, .store-section, .cta-section');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.querySelectorAll('[style*="animation"]').forEach(el => {
                    el.style.animationPlayState = 'running';
                });
            }
        });
    }, { threshold: 0.2 });

    sections.forEach(section => observer.observe(section));

    // Validation simple du formulaire newsletter
    const form = document.querySelector('.newsletter-form');
    form.addEventListener('submit', function(e) {
        const email = form.querySelector('input[name="email"]').value;
        if (!email.includes('@') || !email.includes('.')) {
            e.preventDefault();
            alert('Veuillez entrer une adresse email valide.');
        }
    });
});
</script>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>