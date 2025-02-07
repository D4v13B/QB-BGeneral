<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

class Mailer
{
   private $mail;

   public function __construct()
   {
      $this->mail = new PHPMailer(true);
      try {
         $config = include "./config.php";
         $this->mail->isSMTP();
         $this->mail->Host = $config["EMAIL_HOST"];
         $this->mail->SMTPAuth = true;
         $this->mail->Username = $config["EMAIL_USERNAME"];
         $this->mail->Password = $config["EMAIL_PASSWORD"];
         $this->mail->SMTPSecure = $config["EMAIL_SECURE"];
         $this->mail->Port = $config["EMAIL_PORT"];

         $this->mail->setFrom($config["EMAIL_USERNAME"], $config["EMPRESA"]);
      } catch (Exception $e) {
         throw new Exception("Error en la configuración del Mailer: " . $e->getMessage());
      }
   }

   /**
    * Envía un correo electrónico a uno o varios destinatarios.
    *
    * @param string|array $recipients Dirección de correo electrónico o un array de direcciones.
    * @param string $subject Asunto del correo electrónico.
    * @param string $body Contenido del correo en formato HTML.
    * @param string $altBody (Opcional) Contenido alternativo en texto plano. Si no se proporciona, se generará automáticamente eliminando etiquetas HTML de $body.
    *
    * @return bool Devuelve `true` si el correo se envía correctamente, `false` en caso contrario.
    *
    * @throws Exception Lanza una excepción si ocurre un error al enviar el correo.
    */
   public function sendMail($recipients, $subject, $body, $altBody = '')
   {
      try {
         $this->mail->clearAddresses();

         if (is_array($recipients)) {
            foreach ($recipients as $recipient) {
               $this->mail->addAddress($recipient);
            }
         } else {
            $this->mail->addAddress($recipients);
         }

         $this->mail->Subject = $subject;
         $this->mail->Body = $body;
         $this->mail->AltBody = $altBody ?: strip_tags($body);

         return $this->mail->send();
      } catch (Exception $e) {
         throw new Exception("Error al enviar el correo: " . $e->getMessage());
      }
   }
}
