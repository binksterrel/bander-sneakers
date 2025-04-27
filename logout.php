<?php
/**
 * Déconnexion
 *
 * Ce fichier gère la déconnexion de l'utilisateur.
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Détruire toutes les variables de session
$_SESSION = array();

// Si un cookie de session existe, le supprimer
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Détruire la session
session_destroy();

// Démarrer une nouvelle session pour les messages
session_start();

// Stocker le message de succès dans la nouvelle session
$_SESSION['success_message'] = "Vous avez été déconnecté avec succès.";

// Rediriger vers la page d'accueil
header('Location: index.php');
exit;
