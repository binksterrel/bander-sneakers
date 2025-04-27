<?php
/**
 * Déconnexion de l'administration
 */

// Démarrer la session
session_start();

// Détruire toutes les variables de session
$_SESSION = array();

// Détruire la session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Rediriger vers la page de connexion
header('Location: login.php');
exit;
