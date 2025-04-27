<?php
// Page FAQ
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Titre et description de la page
$page_title = "FAQ - Bander-Sneakers";
$page_description = "Consultez les réponses aux questions fréquentes sur les achats, livraisons, retours et plus encore chez Bander-Sneakers.";

// Inclure l'en-tête
include 'includes/header.php';
?>

<style>
    .faq-section {
        padding: 60px 0;
    }
    .faq-container {
        max-width: 800px;
        margin: 0 auto;
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .faq-container h1 {
        font-size: 2.5rem;
        margin-bottom: 30px;
        text-align: center;
    }
    .faq-item {
        margin-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    .faq-question {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        cursor: pointer;
        font-size: 1.2rem;
        font-weight: 600;
        color: #333;
        transition: color 0.3s ease;
    }
    .faq-question:hover {
        color: var(--primary-color, #007bff);
    }
    .faq-question i {
        font-size: 1rem;
        transition: transform 0.3s ease;
    }
    .faq-question.active i {
        transform: rotate(180deg);
    }
    .faq-answer {
        max-height: 0;
        overflow: hidden;
        padding: 0 15px;
        color: #555;
        line-height: 1.6;
        transition: all 0.3s ease;
    }
    .faq-answer.active {
        max-height: 200px; /* Ajustez selon le contenu */
        padding: 15px;
    }
    .faq-answer a {
        color: var(--primary-color, #007bff);
        text-decoration: none;
    }
    .faq-answer a:hover {
        text-decoration: underline;
    }
    @media (max-width: 768px) {
        .faq-container {
            padding: 20px;
        }
        .faq-container h1 {
            font-size: 2rem;
        }
        .faq-question {
            font-size: 1.1rem;
        }
        .faq-answer.active {
            max-height: 300px; /* Plus de place sur mobile */
        }
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="index.php">Accueil</a></li>
            <li class="active">FAQ</li>
        </ul>
    </div>
</div>

<!-- FAQ Section -->
<section class="faq-section">
    <div class="container">
        <div class="faq-container" style="animation: fadeIn 1s ease-out;">
            <h1>Foire aux Questions (FAQ)</h1>

            <div class="faq-item">
                <div class="faq-question">
                    Comment passer une commande sur Bander-Sneakers ?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Pour passer une commande, parcourez notre catalogue sur <a href="sneakers.php">la page Sneakers</a>, ajoutez vos articles au panier, puis suivez les étapes de paiement et de confirmation. Vous recevrez un email de confirmation une fois votre commande validée.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    Quels sont les délais de livraison ?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Les délais de livraison dépendent de votre localisation : 2 à 5 jours ouvrables en France métropolitaine, 5 à 10 jours pour l’international. Consultez votre suivi de commande pour plus de détails après l’expédition.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    Puis-je retourner un article ?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Oui, vous disposez de 30 jours après réception pour retourner un article non porté et dans son état d’origine. Les frais de retour sont à votre charge, sauf en cas d’erreur de notre part. Consultez notre <a href="terms-conditions.php">politique de retour</a> pour plus d’infos.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    Comment savoir si un produit est authentique ?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Tous nos produits sont 100% authentiques, sourcés directement auprès des marques ou de distributeurs officiels. Nous garantissons l’authenticité à chaque étape de notre chaîne d’approvisionnement.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    Quels modes de paiement acceptez-vous ?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Nous acceptons les cartes bancaires (Visa, Mastercard), PayPal, et certains paiements mobiles selon votre région. Toutes les transactions sont sécurisées via un chiffrement SSL.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    Comment puis-je suivre ma commande ?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Une fois votre commande expédiée, vous recevrez un email avec un lien de suivi. Vous pouvez aussi vérifier le statut dans votre compte sur <a href="compte.php">Mon Compte</a> si vous êtes inscrit.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    Que faire si je reçois un article défectueux ?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Contactez-nous immédiatement à <a href="mailto:bander.sneakers@gmail.com">bander.sneakers@gmail.com</a> avec des photos du défaut. Nous organiserons un retour gratuit et un remplacement ou remboursement selon votre préférence.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    Comment contacter le service client ?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Vous pouvez nous joindre via notre <a href="contact.php">page de contact</a>, par email à <a href="mailto:bander.sneakers@gmail.com">bander.sneakers@gmail.com</a>, ou par téléphone au +33 1 23 45 67 89, du lundi au samedi de 9h à 20h.
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de l'accordéon
    const faqQuestions = document.querySelectorAll('.faq-question');
    
    faqQuestions.forEach(question => {
        question.addEventListener('click', function() {
            const answer = this.nextElementSibling;
            const isActive = answer.classList.contains('active');

            // Ferme toutes les réponses ouvertes
            document.querySelectorAll('.faq-answer').forEach(item => {
                item.classList.remove('active');
                item.previousElementSibling.classList.remove('active');
            });

            // Ouvre ou ferme la réponse cliquée
            if (!isActive) {
                answer.classList.add('active');
                this.classList.add('active');
            }
        });
    });

    // Animation au chargement
    const faqContainer = document.querySelector('.faq-container');
    faqContainer.style.opacity = '0';
    setTimeout(() => {
        faqContainer.style.opacity = '1';
    }, 100);
});
</script>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>