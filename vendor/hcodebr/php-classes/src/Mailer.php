<?php

namespace Hcode;

use Rain\Tpl;

class Mailer
{

    const USERNAME = 'email@gmail.com';
    const PASSWORD = 'password';
    const FROM_NAME = 'ENTERPRISE';

    private $mail;

    public function __construct($toAddress, $toName, $subject, $tplName, $data = array())
    {
        $page = new Page([
            "header" => false,
            "footer" => false
        ], '/views/admin/email/');

        $html = $page->setTpl($tplName, $data, true);

        $this->mail = new \PHPMailer();

        $this->mail->isSMTP();

        $this->mail->SMTPDebug = 0;

        $this->mail->Debugoutput = 'html';

        $this->mail->Host = 'smtp.gmail.com';

        $this->mail->Port = 587;

        $this->mail->SMTPSecure = 'tls';

        $this->mail->SMTPAuth = true;

        $this->mail->Username = Mailer::USERNAME;

        $this->mail->Password = Mailer::PASSWORD;

        $this->mail->setFrom(Mailer::USERNAME, Mailer::FROM_NAME);

        $this->mail->addAddress($toAddress, $toName);

        $this->mail->Subject = $subject;

        $this->mail->msgHTML($html);

        $this->mail->AltBody = "Esta é uma mensagem automática, não responda.";

        $this->send();
    }

    public function send()
    {
        return $this->mail->send();
    }
}