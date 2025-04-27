<?php

header('Content-Type: text/html; charset=UTF-8');
/**
 * Configuration du site Bander-Sneakers
 * Fichier contenant les paramètres de configuration de la base de données
 * et autres réglages globaux
 */

// Désactiver l'affichage des erreurs en production
// (à supprimer en développement)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Terrel21');
define('DB_NAME', 'bander_sneakers');

// Configuration du site
define('SITE_NAME', 'Bander-Sneakers');
define('SITE_URL', 'http://localhost/bander-sneakers');
define('ADMIN_EMAIL', 'admin@bander-sneakers.com');

// Répertoires du site
define('ROOT_PATH', dirname(__DIR__) . '/');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('UPLOADS_PATH', ROOT_PATH . 'assets/images/uploads/');
define('SNEAKER_IMAGES_PATH', ROOT_PATH . 'assets/images/sneakers/');
define('BRAND_IMAGES_PATH', ROOT_PATH . 'assets/images/brands/');

// Fuseau horaire
date_default_timezone_set('Europe/Paris');

// Initialiser la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Fonction pour se connecter à la base de données
 * @return PDO Instance de connexion PDO
 */
function getDbConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // En production, on afficherait un message plus générique
        die("Erreur de connexion à la base de données: " . $e->getMessage());
    }
}
