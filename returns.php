<?php
// Page Retours et Remboursements
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Titre et description de la page
$page_title = "Retours et Remboursements - Bander-Sneakers";
$page_description = "Découvrez notre politique de retours et remboursements chez Bander-Sneakers, avec des instructions claires pour retourner vos articles.";

// Inclure l'en-tête
include 'includes/header.php';
?>

<style>
    .returns-section {
        padding: 60px 0;
    }
    .returns-container {
        max-width: 800px;
        margin: 0 auto;
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .returns-container h1 {
        font-size: 2.5rem;
        margin-bottom: 20px;
        text-align: center;
    }
    .returns-container h2 {
        font-size: 1.5rem;
        margin: 30px 0 15px;
        color: var(--primary-color, #007bff);
    }
    .returns-container p, .returns-container ul {
        margin-bottom: 20px;
        line-height: 1.6;
        color: #555;
    }
    .returns-container ul {
        padding-left: 20px;
    }
    .returns-container li {
        margin-bottom: 10px;
    }
    .returns-container ol {
        padding-left: 20px;
        margin-bottom: 20px;
    }
    .returns-container a {
        color: var(--primary-color, #007bff);
        text-decoration: none;
    }
    .returns-container a:hover {
        text-decoration: underline;
    }
    .step {
        display: flex;
        align-items: flex-start;
        margin-bottom: 20px;
    }
    .step-number {
        width: 30px;
        height: 30px;
        background: var(--primary-color, #007bff);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        flex-shrink: 0;
    }
    .step-content {
        flex: 1;
    }
    @media (max-width: 768px) {
        .returns-container {
            padding: 20px;
        }
        .returns-container h1 {
            font-size: 2rem;
        }
        .returns-container h2 {
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
            <li class="active">Retours et Remboursements</li>
        </ul>
    </div>
</div>

<!-- Returns Section -->
<section class="returns-section">
    <div class="container">
        <div class="returns-container" style="animation: fadeIn 1s ease-out;">
            <h1>Retours et Remboursements</h1>
            <p>Dernière mise à jour : 21 mars 2025</p>

            <h2>1. Notre Politique de Retour</h2>
            <p>Chez Bander-Sneakers, nous voulons que vous soyez entièrement satisfait de vos achats. Si un article ne vous convient pas, vous disposez de <strong>30 jours</strong> à compter de la date de réception pour demander un retour, sous certaines conditions :</p>
            <ul>
                <li>L’article doit être dans son état d’origine : non porté, non endommagé, avec toutes les étiquettes et emballages d’origine.</li>
                <li>Une preuve d’achat (email de confirmation ou facture) doit être fournie.</li>
                <li>Les frais de retour sont à votre charge, sauf en cas d’erreur de notre part (ex. produit défectueux ou mauvaise référence livrée).</li>
            </ul>

            <h2>2. Processus de Retour</h2>
            <p>Suivez ces étapes simples pour retourner un article :</p>
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <strong>Contactez-nous</strong><br>
                    Envoyez une demande de retour via <a href="mailto:bander.sneakers@gmail.com">bander.sneakers@gmail.com</a> ou notre <a href="contact.php">formulaire de contact</a>. Indiquez votre numéro de commande et la raison du retour.
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <strong>Recevez une autorisation</strong><br>
                    Nous vous enverrons une confirmation avec une étiquette de retour (si applicable) et l’adresse de retour : <em>123 Rue des Sneakers, 75000 Paris</em>.
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <strong>Emballez et expédiez</strong><br>
                    Placez l’article dans son emballage d’origine avec la preuve d’achat, puis expédiez-le via un transporteur de votre choix (gardez une preuve d’envoi).
                </div>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <div class="step-content">
                    <strong>Vérification et traitement</strong><br>
                    Une fois reçu, nous vérifierons l’état de l’article. Vous serez informé par email du statut de votre retour.
                </div>
            </div>

            <h2>3. Remboursements</h2>
            <p>Si votre retour est approuvé :</p>
            <ul>
                <li>Le remboursement sera effectué via le mode de paiement initial dans un délai de <strong>14 jours</strong> après réception et vérification de l’article.</li>
                <li>Les frais de livraison initiaux ne sont pas remboursables, sauf en cas d’erreur de notre part.</li>
                <li>Pour un échange, contactez-nous pour vérifier la disponibilité du nouvel article.</li>
            </ul>

            <h2>4. Exceptions</h2>
            <p>Certaines situations ne permettent pas de retour ou de remboursement :</p>
            <ul>
                <li>Articles portés, endommagés ou sans étiquettes.</li>
                <li>Commandes passées après la période de 30 jours.</li>
                <li>Articles soldés ou personnalisés (sauf défaut de fabrication).</li>
            </ul>

            <h2>5. Produits Défectueux ou Erreurs de Livraison</h2>
            <p>Si vous recevez un article défectueux ou incorrect :</p>
            <ul>
                <li>Contactez-nous sous 7 jours avec des photos du problème.</li>
                <li>Nous prenons en charge les frais de retour et proposons un remplacement ou un remboursement selon votre préférence.</li>
            </ul>

            <h2>6. Contact</h2>
            <p>Pour toute question ou assistance concernant un retour ou remboursement :</p>
            <ul>
                <li>Email : <a href="mailto:bander.sneakers@gmail.com">bander.sneakers@gmail.com</a></li>
                <li>Téléphone : +33 1 23 45 67 89 (Lun-Sam, 9h-20h)</li>
                <li>Adresse : 123 Rue des Sneakers, 75000 Paris</li>
            </ul>
            <p>Consultez également notre <a href="terms-conditions.php">Termes et Conditions</a> pour plus de détails.</p>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation au chargement
    const returnsContainer = document.querySelector('.returns-container');
    returnsContainer.style.opacity = '0';
    setTimeout(() => {
        returnsContainer.style.opacity = '1';
    }, 100);
});
</script>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>