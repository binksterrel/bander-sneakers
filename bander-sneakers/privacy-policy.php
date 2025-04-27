<?php
// Page Politique de Confidentialité
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Titre et description de la page
$page_title = "Politique de Confidentialité - Bander-Sneakers";
$page_description = "Découvrez comment Bander-Sneakers collecte, utilise et protège vos données personnelles conformément à notre politique de confidentialité.";

// Inclure l'en-tête
include 'includes/header.php';
?>

<style>
    .privacy-section {
        padding: 60px 0;
    }
    .privacy-container {
        max-width: 800px;
        margin: 0 auto;
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .privacy-container h1 {
        font-size: 2.5rem;
        margin-bottom: 20px;
        text-align: center;
    }
    .privacy-container h2 {
        font-size: 1.5rem;
        margin: 30px 0 15px;
        color: var(--primary-color, #007bff);
    }
    .privacy-container p, .privacy-container ul {
        margin-bottom: 20px;
        line-height: 1.6;
        color: #555;
    }
    .privacy-container ul {
        padding-left: 20px;
    }
    .privacy-container li {
        margin-bottom: 10px;
    }
    .privacy-container a {
        color: var(--primary-color, #007bff);
        text-decoration: none;
    }
    .privacy-container a:hover {
        text-decoration: underline;
    }
    @media (max-width: 768px) {
        .privacy-container {
            padding: 20px;
        }
        .privacy-container h1 {
            font-size: 2rem;
        }
        .privacy-container h2 {
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
            <li class="active">Politique de Confidentialité</li>
        </ul>
    </div>
</div>

<!-- Privacy Section -->
<section class="privacy-section">
    <div class="container">
        <div class="privacy-container" style="animation: fadeIn 1s ease-out;">
            <h1>Politique de Confidentialité</h1>
            <p>Dernière mise à jour : 21 mars 2025</p>

            <h2>1. Introduction</h2>
            <p>Chez Bander-Sneakers, nous nous engageons à protéger votre vie privée. Cette politique de confidentialité explique comment nous collectons, utilisons, partageons et protégeons vos données personnelles lorsque vous utilisez notre site web (<a href="index.php">www.bander-sneakers.com</a>) et nos services. Elle est conforme au Règlement Général sur la Protection des Données (RGPD) et aux lois applicables en France.</p>

            <h2>2. Données Collectées</h2>
            <p>Nous collectons les types de données suivants :</p>
            <ul>
                <li><strong>Données personnelles</strong> : Nom, adresse email, adresse postale, numéro de téléphone, informations de paiement (lors d’un achat).</li>
                <li><strong>Données de navigation</strong> : Adresse IP, type de navigateur, pages visitées, temps passé sur le site (via des cookies et outils d’analyse).</li>
                <li><strong>Données fournies volontairement</strong> : Messages envoyés via le formulaire de contact ou inscriptions à la newsletter.</li>
            </ul>

            <h2>3. Utilisation des Données</h2>
            <p>Vos données sont utilisées pour :</p>
            <ul>
                <li>Traitement et expédition de vos commandes.</li>
                <li>Personnalisation de votre expérience sur le site (ex. recommandations de produits).</li>
                <li>Envoi de communications marketing (si vous y avez consenti, ex. newsletter).</li>
                <li>Amélioration de nos services grâce à l’analyse des données de navigation.</li>
                <li>Réponse à vos demandes via notre service client.</li>
            </ul>

            <h2>4. Base Légale du Traitement</h2>
            <p>Nous traitons vos données sur les bases suivantes :</p>
            <ul>
                <li><strong>Exécution d’un contrat</strong> : Pour traiter vos commandes.</li>
                <li><strong>Consentement</strong> : Pour les communications marketing (vous pouvez retirer ce consentement à tout moment).</li>
                <li><strong>Intérêt légitime</strong> : Pour améliorer notre site et prévenir la fraude.</li>
                <li><strong>Obligation légale</strong> : Pour respecter les lois fiscales ou autres réglementations.</li>
            </ul>

            <h2>5. Partage des Données</h2>
            <p>Vos données peuvent être partagées avec :</p>
            <ul>
                <li><strong>Prestataires de services</strong> : Transporteurs pour la livraison, processeurs de paiement pour les transactions.</li>
                <li><strong>Partenaires marketing</strong> : Si vous avez consenti à recevoir des offres (ex. via la newsletter).</li>
                <li><strong>Autorités légales</strong> : En cas d’obligation légale ou pour protéger nos droits.</li>
            </ul>
            <p>Nous ne vendons ni ne louons vos données à des tiers à des fins commerciales.</p>

            <h2>6. Sécurité des Données</h2>
            <p>Nous utilisons des mesures techniques et organisationnelles pour protéger vos données, notamment :</p>
            <ul>
                <li>Chiffrement des données sensibles (ex. SSL pour les transactions).</li>
                <li>Accès restreint aux données au sein de notre équipe.</li>
                <li>Mises à jour régulières de nos systèmes de sécurité.</li>
            </ul>
            <p>Toutefois, aucun système n’est infaillible, et nous ne pouvons garantir une sécurité absolue.</p>

            <h2>7. Vos Droits</h2>
            <p>Conformément au RGPD, vous disposez des droits suivants :</p>
            <ul>
                <li><strong>Accès</strong> : Consulter les données que nous détenons sur vous.</li>
                <li><strong>Rectification</strong> : Corriger des données inexactes.</li>
                <li><strong>Suppression</strong> : Demander la suppression de vos données (sous réserve d’obligations légales).</li>
                <li><strong>Opposition</strong> : Refuser certains traitements (ex. marketing).</li>
                <li><strong>Portabilité</strong> : Recevoir vos données dans un format structuré.</li>
            </ul>
            <p>Pour exercer ces droits, contactez-nous à <a href="mailto:bander.sneakers@gmail.com">bander.sneakers@gmail.com</a>.</p>

            <h2>8. Cookies</h2>
            <p>Nous utilisons des cookies pour :</p>
            <ul>
                <li>Assurer le bon fonctionnement du site (cookies essentiels).</li>
                <li>Analyser les performances (cookies analytiques).</li>
                <li>Proposer des publicités ciblées (cookies marketing, avec consentement).</li>
            </ul>
            <p>Vous pouvez gérer vos préférences via notre bannière de cookies ou les paramètres de votre navigateur.</p>

            <h2>9. Durée de Conservation</h2>
            <p>Nous conservons vos données aussi longtemps que nécessaire :</p>
            <ul>
                <li>Données de commande : 5 ans pour des raisons fiscales.</li>
                <li>Données de compte : Jusqu’à suppression de votre compte ou inactivité prolongée (3 ans).</li>
                <li>Données marketing : Jusqu’à retrait de votre consentement.</li>
            </ul>

            <h2>10. Modifications de la Politique</h2>
            <p>Nous pouvons mettre à jour cette politique. Les changements seront publiés ici, et la date de mise à jour sera ajustée. Consultez cette page régulièrement.</p>

            <h2>11. Contact</h2>
            <p>Pour toute question ou demande concernant vos données, contactez notre responsable de la protection des données :</p>
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
    const privacyContainer = document.querySelector('.privacy-container');
    privacyContainer.style.opacity = '0';
    setTimeout(() => {
        privacyContainer.style.opacity = '1';
    }, 100);
});
</script>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>