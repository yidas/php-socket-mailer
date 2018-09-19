PHP Socket Mailer
=================

PHP SMTP Mailer library implemented by socket

[![Latest Stable Version](https://poser.pugx.org/yidas/socket-mailer/v/stable?format=flat-square)](https://packagist.org/packages/yidas/socket-mailer)
[![Latest Unstable Version](https://poser.pugx.org/yidas/socket-mailer/v/unstable?format=flat-square)](https://packagist.org/packages/yidas/socket-mailer)
[![License](https://poser.pugx.org/yidas/socket-mailer/license?format=flat-square)](https://packagist.org/packages/yidas/socket-mailer)

---

DEMONSTRATION
-------------

```php
$mailer = new \yidas\socketMailer\Mailer([
    'host' => 'mail.your.com',
    'username' => 'service@your.com',
    'password' => 'passwd',
    ]);

$result = $mailer
    ->setSubject('Mail Title')
    ->setBody('Content Text')
    ->setTo(['name@your.com', 'peter@your.com' => 'Peter'])
    ->setFrom(['service@your.com' => 'Service Mailer'])
    ->setCc(['cc@your.com' => 'CC Receiver'])
    ->setBcc(['bcc@your.com'])
    ->send();
```
---

REQUIREMENTS
------------

This library requires the following:

- PHP 5.4.0+

---

INSTALLATION
------------

Run Composer in your project:

    composer require yidas/socket-mailer
    
Then you could call it after Composer is loaded depended on your PHP framework:

```php
require __DIR__ . '/vendor/autoload.php';

use \yidas\socketMailer\Mailer;
```
    
---

CONFIGURATION
-------------

To configure a relay mail server:

```php
$mailer = new \yidas\socketMailer\Mailer([
    'host' => 'mail.your.com',
    'username' => 'service@your.com',
    'password' => 'passwd',
    'port' => 587,
    'encryption' => 'tls',
    ]);
```

### SSL Connection

```php
$mailer = new \yidas\socketMailer\Mailer([
    // ...
    'port' => 465,
    'encryption' => 'ssl',
    ]);
```

### Non-Auth Connection

If you want to connect to SMTP relay server with free auth in allowed networks:

```php
$mailer = new \yidas\socketMailer\Mailer([
    'host' => 'mail.your.com',
    'port' => 25,
    ]);
```

---

USAGES
------

### debugOn()

```php
public self debugOn()
```

### setFrom()

```php
public self setFrom(string|array $form) 
```

### setTo()

```php
public self setTo(array $recipients) 
```

### setCc()

```php
public self setCc(array $recipients) 
```

### setBcc()

```php
public self setBcc(array $recipients) 
```

### setBody()

```php
public self setBody(string $text) 
```

### setSubject()

```php
public self setSubject(string $title) 
```

### addHeader()

```php
public self addHeader($key, $value) 
```

*Example:*
```php
$mailer = $mailer->addHeader('Your-Header-Name', 'the header value');
```

### send()

```php
public boolean send()
```


