<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

function enviarNotificacion($to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'francisco.mario@alumnos.uaysen.cl'; // Cambia por tu correo
        $mail->Password   = 'bxlkisfijamzcjdo'; // Cambia por tu contraseña
        $mail->SMTPSecure = 'ssl';
        $mail->Port       = 465;

        $mail->setFrom('francisco.mario@alumnos.uaysen.cl', 'Logística UAYSEN');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: '. $mail->ErrorInfo);
        return false;
    }
}
?>
