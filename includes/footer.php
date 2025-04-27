<?php
require_once 'includes/functions.php';
?>

</main> <!-- Fin du contenu principal -->

        <footer class="footer">
            <div class="footer-top">
                <div class="container">
                    <div class="footer-widgets">
                        <div class="footer-widget">
                            <h3>À propos de Bander-Sneakers</h3>
                            <p>Bander-Sneakers est votre destination de référence pour les sneakers. Nous proposons une large sélection de modèles des meilleures marques avec des prix compétitifs et un service client exceptionnel.</p>
                            <div class="social-links">
                                <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                                <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                                <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                                <a href="#" class="social-link"><i class="fab fa-youtube"></i></a>
                            </div>
                        </div>

                        <div class="footer-widget">
                            <h3>Liens rapides</h3>
                            <ul class="footer-links">
                                <li><a href="index.php">Accueil</a></li>
                                <li><a href="sneakers.php">Sneakers</a></li>
                                <li><a href="hommes.php">Hommes</a></li>
                                <li><a href="femmes.php">Femmes</a></li>
                                <li><a href="enfants.php">Enfants</a></li>
                                <li><a href="promotions.php">Promotions</a></li>
                            </ul>
                        </div>

                        <div class="footer-widget">
                            <h3>Informations</h3>
                            <ul class="footer-links">
                                <li><a href="about.php">À propos de nous</a></li>
                                <li><a href="contact.php">Contact</a></li>
                                <li><a href="faq.php">FAQ</a></li>
                                <li><a href="privacy-policy.php">Politique de confidentialité</a></li>
                                <li><a href="terms-conditions.php">Conditions générales</a></li>
                                <li><a href="returns.php">Retours et remboursements</a></li>
                            </ul>
                        </div>

                        <div class="footer-widget">
                            <h3>Contactez-nous</h3>
                            <ul class="contact-info">
                                <li><i class="fas fa-map-marker-alt"></i> 123 Rue des Sneakers, 75000 Paris</li>
                                <li><i class="fas fa-phone"></i> +33 1 23 45 67 89</li>
                                <li><i class="fas fa-envelope"></i> bander.sneakers@gmail.com</li>
                                <li><i class="fas fa-clock"></i> Lun-Sam: 9h-20h, Dim: Fermé</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-middle">
                <div class="container">
                    <div class="newsletter">
                        <h3>Inscrivez-vous à notre newsletter</h3>
                        <p>Recevez les dernières nouvelles, offres spéciales et promotions exclusives directement dans votre boîte mail.</p>
                        <form class="newsletter-form" action="newsletter-subscribe.php" method="post">
                            <input type="email" name="email" placeholder="Votre adresse email" required>
                            <button type="submit">S'inscrire</button>
                        </form>
                    </div>

                    <div class="payment-methods">
                        <h3>Modes de paiement acceptés</h3>
                        <div class="payment-icons">
                            <i class="fab fa-cc-visa"></i>
                            <i class="fab fa-cc-mastercard"></i>
                            <i class="fab fa-cc-amex"></i>
                            <i class="fab fa-cc-paypal"></i>
                            <i class="fab fa-cc-apple-pay"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="container">
                    <div class="copyright">
                        <p>&copy; <?= date('Y') ?> Bander-Sneakers. Tous droits réservés.</p>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Scripts -->
        <script src="assets/js/main.js"></script>
    </body>
</html>
