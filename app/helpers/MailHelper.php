<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once BASE_PATH . '/vendor/autoload.php';
class MailHelper{
    public static function send(string $to, string $subject, string $html):bool{
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST; //'mail.alimentostriba.com.ar';
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;//'info_noresponder@alimentostriba.com.ar';
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;

            $mail->setFrom('info_noresponder@alimentostriba.com.ar', 'info ERP APP');
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;

            return $mail->send();
        } catch (Exception $e) {
            error_log('Error en el helpers de los mail: '.$e->getMessage());
            return false;
        }
        return true;
    }
}