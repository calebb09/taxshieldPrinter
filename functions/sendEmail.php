<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function sendEmail($to, $subject, $message)
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'mail.codeopia.dev'; // Your SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = ''; // Your Gmail
        $mail->Password = '';  // App password, not your real Gmail password
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 0;

        // Recipients
        $mail->setFrom('', 'Taxshiled');
        $mail->addAddress($to); // Recipient email

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->send();
        return true; // success

    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        return false; // success
    }
}
?>