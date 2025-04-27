<?php
// Page Termes et Conditions
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Titre et description de la page
$page_title = "Termes et Conditions - Bander-Sneakers";
$page_description = "Consultez les termes et conditions d'utilisation de Bander-Sneakers, incluant les politiques d'achat, de retour et de confidentialité.";

// Inclure l'en-tête
include 'includes/header.php';
?>

<style>
    .terms-section {
        padding: 60px 0;
    }
    .terms-container {
        max-width: 800px;
        margin: 0 auto;
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .terms-container h1 {
        font-size: 2.5rem;
        margin-bottom: 20px;
        text-align: center;
    }
    .terms-container h2 {
        font-size: 1.5rem;
        margin: 30px 0 15px;
        color: var(--primary-color, #007bff);
    }
    .terms-container p, .terms-container ul {
        margin-bottom: 20px;
        line-height: 1.6;
        color: #555;
    }
    .terms-container ul {
        padding-left: 20px;
    }
    .terms-container li {
        margin-bottom: 10px;
    }
    .terms-container a {
        color: var(--primary-color, #007bff);
        text-decoration: none;
    }
    .terms-container a:hover {
        text-decoration: underline;
    }
    @media (max-width: 768px) {
        .terms-container {
            padding: 20px;
        }
        .terms-container h1 {
            font-size: 2rem;
        }
        .terms-container h2 {
            font-size: 1.3rem;
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
            <li class="active">Termes et Conditions</li>
        </ul>
    </div>
</div>

<!-- Terms Section -->
<section class="terms-section">
    <div class="container">
        <div class="terms-container" style="animation: fadeIn 1s ease-out;">
            <h1>Termes et Conditions</h1>
            <p>Dernière mise à jour : 21 mars 2025</p>

            <h2>1. Introduction</h2>
            <p>Bienvenue sur Bander-Sneakers. Ces termes et conditions régissent votre utilisation de notre site web (<a href="index.php">www.bander-sneakers.com</a>) et les achats effectués via notre plateforme. En accédant à ce site, vous acceptez ces termes dans leur intégralité. Si vous n’êtes pas d’accord, veuillez ne pas utiliser notre site.</p>

            <h2>2. Utilisation du Site</h2>
            <p>Vous vous engagez à utiliser ce site uniquement à des fins légales et conformément à ces termes. Il est interdit de :</p>
            <ul>
                <li>Publier du contenu illégal, diffamatoire ou frauduleux.</li>
                <li>Tenter d’accéder sans autorisation à nos systèmes ou bases de données.</li>
                <li>Utiliser des robots ou outils automatisés pour extraire des données sans notre consentement écrit.</li>
            </ul>

            <h2>3. Conditions de Vente</h2>
            <p>En effectuant un achat sur Bander-Sneakers, vous acceptez les conditions suivantes :</p>
            <ul>
                <li><strong>Disponibilité</strong> : Les produits sont proposés sous réserve de disponibilité. Nous nous réservons le droit de refuser une commande en cas de rupture de stock.</li>
                <li><strong>Prix</strong> : Tous les prix sont indiqués en euros (€) et incluent la TVA applicable. Les frais de livraison sont précisés avant la validation de la commande.</li>
                <li><strong>Paiement</strong> : Les paiements sont sécurisés et acceptés via les méthodes indiquées au moment du règlement (carte bancaire, PayPal, etc.).</li>
                <li><strong>Livraison</strong> : Les délais de livraison sont estimatifs et peuvent varier selon votre localisation et les transporteurs.</li>
            </ul>

            <h2>4. Politique de Retour et Remboursement</h2>
            <p>Nous offrons une politique de retour de 30 jours à compter de la réception de votre commande, sous réserve que :</p>
            <ul>
                <li>Les articles soient retournés dans leur état d’origine (non portés, avec étiquettes).</li>
                <li>Vous fournissiez une preuve d’achat.</li>
                <li>Les frais de retour soient à votre charge, sauf en cas d’erreur de notre part.</li>
            </ul>
            <p>Les remboursements seront effectués via le mode de paiement initial dans un délai de 14 jours après réception et vérification des articles retournés.</p>

            <h2>5. Propriété Intellectuelle</h2>
            <p>Tous les contenus présents sur ce site (images, textes, logos, etc.) sont la propriété de Bander-Sneakers ou de ses partenaires et sont protégés par les lois sur le droit d’auteur. Toute reproduction ou utilisation sans autorisation écrite est interdite.</p>

            <h2>6. Limitation de Responsabilité</h2>
            <p>Bander-Sneakers ne pourra être tenu responsable des dommages indirects ou consécutifs découlant de l’utilisation de ce site ou des produits achetés, dans les limites permises par la loi.</p>

            <h2>7. Modifications des Termes</h2>
            <p>Nous nous réservons le droit de modifier ces termes à tout moment. Les modifications seront effectives dès leur publication sur cette page. Il est de votre responsabilité de consulter régulièrement cette page pour rester informé.</p>

            <h2>8. Droit Applicable</h2>
            <p>Ces termes sont régis par le droit français. Tout litige sera soumis à la compétence exclusive des tribunaux de Paris.</p>

            <h2>9. Contact</h2>
            <p>Pour toute question concernant ces termes, veuillez nous contacter :</p>
            <ul>
                <li>Email : <a href="mailto:bander.sneakers@gmail.com">bander.sneakers@gmail.com</a></li>
                <li>Adresse : 123 Rue des Sneakers, 75000 Paris</li>
                <li>Téléphone : +33 1 23 45 67 89</li>
            </ul>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation au chargement
    const termsContainer = document.querySelector('.terms-container');
    termsContainer.style.opacity = '0';
    setTimeout(() => {
        termsContainer.style.opacity = '1';
    }, 100);
});
</script>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>