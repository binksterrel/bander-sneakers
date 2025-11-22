# Bander-Sneakers

Bander-Sneakers est une plateforme e-commerce complète dédiée à la vente de sneakers. Ce projet offre une expérience utilisateur intuitive pour les clients et un système d'administration robuste pour gérer l'ensemble de la boutique en ligne.

## 🚀 Fonctionnalités

### Côté Client
- Catalogue de sneakers avec filtrage avancé (marque, catégorie, prix, etc.)
- Système de recherche performant
- Système de Recommandation IA : Suggestions intelligentes de produits ("Les clients ont aussi acheté" & "Basé sur les favoris") calculées en temps réel selon le comportement des utilisateurs.
- Pages produits détaillées avec galerie d'images
- Système d'avis et de notation des produits
- Panier d'achat interactif avec gestion des quantités
- Liste de souhaits personnalisée
- Processus de paiement sécurisé
- Suivi de commandes en temps réel
- Comptes utilisateurs avec historique des achats
- Sections dédiées pour hommes, femmes et enfants

### Côté Administration
- Tableau de bord analytique avec statistiques des ventes
- Gestion complète du catalogue produits (CRUD)
- Gestion des stocks et des tailles disponibles
- Suivi et mise à jour des commandes
- Administration des comptes utilisateurs
- Chat en direct avec les clients
- Gestion des catégories et des marques
- Système de notifications
- Outils promotionnels et gestion des remises

## 💻 Technologies utilisées

- **Backend**: PHP natif
- **Frontend**: HTML5, CSS3, JavaScript
- **Base de données**: MySQL
- **Outils supplémentaires**:
  - Intelligence Artificielle: Algorithmes d'analyse comportementale (Cross-selling & Wishlist matching)
  - Système de chat en temps réel
  - Système de notifications
  - PHPMailer pour l'envoi d'emails

## ⚙️ Prérequis

- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Serveur web (Apache, Nginx)

## 📋 Installation

### 1. Configuration de la base de données
1. Créez une base de données MySQL nommée `bander_sneakers`
2. Importez le fichier SQL fourni:
```bash
mysql -u [utilisateur] -p bander_sneakers < dump.sql
```
3. Configurez les paramètres de connexion dans `includes/config.php`

### 2. Configuration du serveur
- Assurez-vous que votre serveur web pointe vers le répertoire racine du projet
- Configurez les droits d'accès appropriés pour les dossiers d'upload

### 3. Lancement de l'application
- Accédez au site via votre navigateur à l'adresse configurée
- Pour le panneau d'administration, naviguez vers `/admin`

### 4. Compte administrateur par défaut
- Utilisez les identifiants par défaut pour accéder au panneau d'administration
- N'oubliez pas de changer le mot de passe après la première connexion!

## 📁 Structure du projet

```
bander-sneakers/
├── admin/                 # Panneau d'administration
│   ├── assets/            # Ressources admin (CSS, JS)
│   │   ├── css/
│   │   │   └── admin.css
│   │   └── js/
│   │       └── admin.js
│   ├── includes/          # Composants admin réutilisables
│   │   ├── footer.php
│   │   ├── header.php
│   │   └── admin-chat.php
│   ├── admin.php          # Page de connexion de l'admin
│   ├── brands.php         # Gestion des marques
│   ├── categories.php     # Gestion des catégories
│   ├── index.php          # Tableau de bord admin
│   ├── login.php          # Connexion admin
│   ├── logout.php         # Déconnexion admin
│   ├── order-details.php  # Détails des commandes
│   ├── orders.php         # Gestion des commandes
│   ├── product-add.php    # Ajout de produits
│   ├── product-edit.php   # Modification de produits
│   ├── products.php       # Liste des produits
│   ├── profile.php        # Profil admin
│   ├── reports.php        # Rapports et statistiques
│   ├── reviews.php        # Gestion des avis
│   ├── secondhand.php     # Gestion des produits d'occasion
│   ├── secondhand-edit.php # Modification des produits d'occasion
│   ├── settings.php       # Paramètres admin
│   └── users.php          # Gestion des utilisateurs
├── assets/                # Ressources principales
│   ├── css/               # Styles CSS
│   │   └── style.css
│   ├── js/                # Scripts JavaScript
│   │   └── [fichiers JS non visibles dans la capture]
│   └── images/            # Images et médias
│       ├── brands/        # Images des marques
│       └── sneakers/      # Images des sneakers
│
├── database/              # Fichiers de base de données
│   └── bander_sneakers.sql # Schéma de la base de données
├── includes/              # Composants partagés
│   ├── config.php         # Configuration de la BD et du site
│   ├── functions.php      # Fonctions utilitaires
│   ├── header.php         # En-tête du site
│   └── footer.php         # Pied de page du site
├── uploads/               # Dossier pour les fichiers uploadés
│   └── secondhand/        # Fichiers pour les produits d'occasion
├── vendor/                # Dépendances externes
│   ├── composer/          # Gestion des dépendances Composer
│   │   └── autoload.php
│   └── phpmailer/         # Bibliothèque PHPMailer pour l'envoi d'emails
├── 2ndhand.php            # Page des produits d'occasion
├── 2ndhand-delete.php     # Suppression de produits d'occasion
├── 2ndhand-detail.php     # Détails des produits d'occasion
├── 2ndhand-edit.php       # Modification des produits d'occasion
├── 2ndhand-post.php       # Publication de produits d'occasion
├── 404.php                # Page d'erreur 404
├── about.php              # Page "À propos"
├── add-review.php         # Ajout d'un avis
├── cart.php               # Panier d'achat
├── cart-add.php           # Ajout au panier
├── chat.php               # Page de chat
├── chat-api.php           # API pour le chat
├── chat-submit.php        # Soumission des messages de chat
├── checkout.php           # Processus de paiement
├── contact.php            # Page de contact
├── enfants.php            # Section enfants
├── faq.php                # Page FAQ
├── femmes.php             # Section femmes
├── get-notifications.php  # Récupération des notifications
├── hommes.php             # Section hommes
├── index.php              # Page d'accueil
├── login.php              # Connexion utilisateur
├── logout.php             # Déconnexion utilisateur
├── loyalty.php            # Programme de fidélité
├── manage-notifications.php # Gestion des notifications
├── newsletter-subscribe.php # Inscription à la newsletter
├── notifications.php      # Page des notifications
├── order-confirmation.php # Confirmation de commande
├── order-details.php      # Détails des commandes
├── privacy-policy.php     # Politique de confidentialité
├── process-orders.php     # Traitement des commandes
├── profile.php            # Profil utilisateur
├── profile-data.php       # Données du profil
├── register.php           # Inscription utilisateur
├── remove-wishlist.php    # Suppression d'un produit de la liste de souhaits
├── report.php             # Signalement
├── returns.php            # Page des retours
├── search.php             # Page de recherche
├── send-message.php       # Envoi de messages
├── sneaker.php            # Page détaillée d'un produit
├── sneakers.php           # Catalogue principal
├── spin.php               # Roulette journalière pour gagner des points
├── start-conversation.php # Démarrage d'une conversation
├── terms-conditions.php   # Conditions générales
├── test.php               # Page de test (mail)
├── wishlist.php           # Liste de souhaits
├── wishlist-add.php       # Ajout à la liste de souhaits
└── wishlist-remove.php    # Suppression de la liste de souhaits
```

## 🔒 Sécurité

- Protection contre les injections SQL
- Hachage sécurisé des mots de passe
- Validation des entrées utilisateur
- Protection contre les attaques CSRF
- Sessions sécurisées

## 📱 Compatibilité

- Design responsive adapté à tous les appareils
- Testé sur les navigateurs modernes (Chrome, Firefox, Safari, Edge)

## 🛠️ Personnalisation

### Thème et apparence
- Modifiez les styles dans `assets/css/style.css`
- Personnalisez les éléments d'interface dans les fichiers PHP correspondants

### Ajout de nouvelles fonctionnalités
1. Développez les fonctions nécessaires dans `includes/functions.php`
2. Créez les pages ou composants requis
3. Mettez à jour la base de données si nécessaire

## 📞 Support et contact

Pour toute question ou assistance concernant l'installation ou l'utilisation de Bander-Sneakers, veuillez nous contacter:

- Email: 43020094@parisnanterre.fr
         43004280@parisnanterre.fr
- Site web: http://localhost/bander-sneakers - https://bander-sneakers.kesug.com

## 📄 Licence

Ce projet est protégé par des droits d'auteur. Tous droits réservés.

## 👨‍👩‍👧‍👦 Contributeurs

- Terrel NUENTSA
- Mathieu SIEGEL

---

© 2025 Bander-Sneakers. Tous droits réservés.
