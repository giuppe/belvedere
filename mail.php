<?php

require_once 'vendors/php-simple-mail/class.simple_mail.php';

function isValidEmail($email){
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

$email = isValidEmail($_POST['email']);

$mail = new SimpleMail();
$mail->setTo('massimo.merenda@unirc.it', 'Massimo Merenda')
->setSubject('Iscrizione dal sito')
->setFrom('iscrizione@sensea.babbage.it', 'Sensea')
->addMailHeader('Bcc', 'giuseppe.da@gmail.com', 'Webmaster')
->addMailHeader('Reply-To', 'no-reply@sensea.it', 'Sensea')
->addGenericHeader('X-Mailer', 'PHP/' . phpversion())
->addGenericHeader('Content-Type', 'text/html; charset="utf-8"')
->setMessage('Iscrizione dal sito Sensea: <br/><strong>'.$email.'</strong>')
->setWrap(100);
$send = $mail->send();
//echo ($send) ? 'Email sent successfully' : 'Could not send email';