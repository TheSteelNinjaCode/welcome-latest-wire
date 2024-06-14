<?php

namespace Lib\PHPMailer;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Lib\Validator;

class Mailer
{
    private PHPMailer $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $this->setup();
    }

    private function setup(): void
    {
        $this->mail->isSMTP();
        $this->mail->SMTPDebug = 0;
        $this->mail->Host = $_ENV['SMTP_HOST'];
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $_ENV['SMTP_USERNAME'];
        $this->mail->Password = $_ENV['SMTP_PASSWORD'];
        $this->mail->SMTPSecure = $_ENV['SMTP_ENCRYPTION'];
        $this->mail->Port = (int) $_ENV['SMTP_PORT'];
        $this->mail->setFrom($_ENV['MAIL_FROM'], $_ENV['MAIL_FROM_NAME']);
    }

    /**
     * Send an email.
     *
     * @param string $to The recipient's email address.
     * @param string $subject The subject of the email.
     * @param string $body The HTML body of the email.
     * @param string $name (optional) The name of the recipient.
     * @param string $altBody (optional) The plain text alternative body of the email.
     *
     * @return bool Returns true if the email is sent successfully, false otherwise.
     *
     * @throws Exception Throws an exception if the email could not be sent.
     *
     * @example
     * $mailer = new Mailer();
     * $to = 'recipient@example.com';
     * $subject = 'Hello';
     * $body = '<h1>Example Email</h1><p>This is the HTML body of the email.</p>';
     * $name = 'John Doe';
     * $altBody = 'This is the plain text alternative body of the email.';
     *
     * try {
     *     $result = $mailer->send($to, $subject, $body, $name, $altBody);
     *     if ($result) {
     *         echo 'Email sent successfully.';
     *     } else {
     *         echo 'Failed to send email.';
     *     }
     * } catch (Exception $e) {
     *     echo 'An error occurred: ' . $e->getMessage();
     * }
     */
    public function send(string $to, string $subject, string $body, string $name = '', string $altBody = ''): bool
    {
        try {
            Validator::validateString($to);
            Validator::validateString($subject);
            Validator::validateString($body);
            Validator::validateString($name);
            Validator::validateString($altBody);

            $this->mail->addAddress($to, $name);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->AltBody = $altBody;

            return $this->mail->send();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
