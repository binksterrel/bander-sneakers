<?php
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
$mail = new PHPMailer(true);
echo "PHPMailer est chargé !";
?>