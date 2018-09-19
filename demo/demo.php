<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

require __DIR__ . '/../vendor/autoload.php';

$mailer = new \yidas\socketMailer\Mailer([
    'host' => 'mail.your.com',
    'username' => 'service@your.com',
    'password' => 'passwd',
    'port' => '587',
    'encryption' => 'tls',
    ]);

$mailer->debugOn();

$result = $mailer
    ->setSubject('Test中文')
    ->setBody('Test中文')
    ->setTo(['name@your.com' => 'Name姓氏', 'name2@your.com'])
    ->setFrom(['service@your.com' => 'Service服務'])
    ->send();

if ($result) {
    
    echo 'Success';
    
} else {

    echo 'Failed';
}