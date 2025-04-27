<?php
// Inclure l'autoload de Composer
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Redirection vers le panneau d'administration
 *
 * Ce fichier redirige l'utilisateur vers la page d'administration.
 */

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si l'utilisateur est déjà connecté et est admin, le rediriger vers le tableau de bord
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: admin/index.php');
    exit();
}

// Sinon, rediriger vers la page de connexion admin
header('Location: admin/login.php');
exit();