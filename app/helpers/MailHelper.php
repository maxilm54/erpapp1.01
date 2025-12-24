<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once BASE_PATH . '/vendor/autoload.php';
class MailHelper{
    public static function send(string $to, string $subject, string $html):bool{
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'mail.alimentostriba.com.ar';
            $mail->SMTPAuth = true;
            $mail->Username = 'info_noresponder@alimentostriba.com.ar';
            $mail->Password = 'Tucuman#1588';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('info_noresponder@alimentostriba.com.ar', 'info ERP APP');
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;

            return $mail->send();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
        return true;
    }
}