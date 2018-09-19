<?php

namespace yidas\socketMailer;

interface MailerInterface 
{
    // Set the From address with an associative array
    public function setFrom($from);

    // Set the To addresses with an associative array (setTo/setCc/setBcc)
    public function setTo($recipients);

    // Set the To addresses with an associative array (setTo/setCc/setBcc)
    public function setCc($recipients);

    // Set the To addresses with an associative array (setTo/setCc/setBcc)
    public function setBcc($recipients);

    // Give it a body
    public function setBody($text);

    // Give the message a subject
    public function setSubject($title);

    // Optionally add any attachments
    public function attach();

    public function addHeader($key, $value);

    public function send();
}