<?php
require 'vendor/autoload.php';
require 'config/google-config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug = 2;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'beautyandblessed.business@gmail.com';
    $mail->Password = 'untuurujqeczidko';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
    $mail->Port = 465;
    $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
    $mail->setFrom('beautyandblessed.business@gmail.com', 'Test');
    $mail->addAddress('beautyandblessed.business@gmail.com');
    $mail->Subject = 'Test Email';
    $mail->Body = 'This is a test email.';
    $mail->send();
    echo 'SUCCESS';
} catch (Exception $e) {
    echo 'ERROR: ' . $mail->ErrorInfo;
}
