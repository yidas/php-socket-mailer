<?php

namespace yidas\socketMailer;

use yidas\socketHelper\SocketHelper as SocketHelper;
use Exception;

/**
 * Socket Mailer
 * 
 * @author  Nick Tsai <myintaer@gmail.com>
 * @version 1.1.0
 */
class Mailer implements MailerInterface
{
    /**
     * SMTP line break constant.
     *
     * @var string
     */
    const LB = "\r\n";
    
    // Transport setting
    public $transport = [];
    
    /**
     * Debug Mode
     *
     * @var boolean|int
     */
    public $debugMode = false;

    protected $to;

    protected $cc;

    protected $bcc;

    protected $form;

    protected $subject;
    
    protected $body;

    protected $headers;

    protected $charset = 'utf-8';

    /**
     * Cache for the map between domain and MX record
     *
     * @var array
     */
    private $cacheDomainMX = [];

    /**
     * Debug log text
     *
     * @var string
     */
    private $logText;

    /**
     * Allowed encryptions
     *
     * @var array
     */
    private $allowedEncryptions = ['ssl', 'tls'];
    
    /**
     * Constructor
     *
     * @param array $opt Transport Options
     */
    function __construct($opt=[]) 
    {
        // Option Template
        $default = [
            'host' => 'localhost',
            'username' => '',
            'password' => '',
            'port' => '25',
            'encryption' => '',
            'mtaModeOn' => false,
        ];

        // Set Transport
        $this->transport = array_merge($default, $opt);

        // Protocol check
        $this->transport['encryption'] = in_array($this->transport['encryption'], $this->allowedEncryptions)
            ? $this->transport['encryption']
            : '';
    }

    /**
     * Turn debug mode on
     *
     * @param boolean|integer 1: error, 2: flow
     * @return object Self
     */
    public function debugOn($mode = true)
    {
        $this->debugMode = $mode;

        return $this;
    }

    /**
     * Set from
     *
     * @param string|array $form
     * @return object Self
     */
    public function setFrom($form) 
    {
        $this->form = $form;
        
        return $this;
    }

    /**
     * Set To
     *
     * @param array $recipients
     * @return object Self
     */
    public function setTo($recipients) 
    {
        $this->to = $recipients;

        return $this;
    }

    /**
     * Set CC
     *
     * @param array $recipients
     * @return object Self
     */
    public function setCc($recipients) 
    {
        $this->cc = $recipients;

        return $this;
    }
    
    /**
     * Set BCC
     *
     * @param array $recipients
     * @return object Self
     */
    public function setBcc($recipients) 
    {
        $this->bcc = $recipients;

        return $this;
    }
    
    /**
     * Set Body
     *
     * @param string $text
     * @return object Self
     */
    public function setBody($text) 
    {
        $this->body = $text;
        
        return $this;
    }
    
    /**
     * Set Subject
     *
     * @param string $title
     * @return object Self
     */
    public function setSubject($title) 
    {
        $this->subject = $title;

        return $this;
    }
    
    /**
     * Set Attachment
     *
     * @return object Self
     */
    public function attach() 
    {
        return $this;
    }
    
    /**
     * Add a Header
     *
     * @param string $key
     * @param string $value
     * @return object Self
     * @example
     *  $mailer = $mailer->addHeader('Your-Header-Name', 'the header value');
     */
    public function addHeader($key, $value) 
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Send a Mail
     *
     * @return boolean
     */
    public function send()
    {
        // Clear log text
        $this->logText = null;

        $sender;
        $recipients = [];

        // Header handler
        $this->addHeader('Subject', "=?{$this->charset}?B?".base64_encode($this->subject)."?=");
        $fromText = "";
        if (empty($this->form)) {
            
            throw new Exception("From (Sender) is empty", 400);
        }
        elseif (is_string($this->form)) {
            
            $fromText = $sender = $this->form;
        } 
        else if (is_array($this->form)) {

            foreach ($this->form as $key => $value) {
                
                $sender = is_string($key) ? $key : $value;
                
                $fromText .= ($fromText) ? "," : $fromText; 
                $fromText .= is_string($key) ? "{$value} <{$key}>" : "<{$value}>";

                // Single sender
                break;
            }
        }
        $this->addHeader('From', $fromText);
        // Receipt To
        $toText = '';
        foreach ((array) $this->to as $key => $value) {
            $email = is_string($key) ? $key : $value;
            $toText .= ($toText) ? "," : $toText; 
            $toText .= is_string($key) ? "{$value} <{$email}>" : "<{$email}>";
            $this->addHeader('To', $toText);
            $recipients[] = $email;
        }
        $ccText = '';
        foreach ((array) $this->cc as $key => $value) {
            $email = is_string($key) ? $key : $value;
            $ccText .= ($ccText) ? "," : $ccText; 
            $ccText .= is_string($key) ? "{$value} <{$email}>" : "<{$email}>";
            $this->addHeader('Cc', $ccText);
            $recipients[] = $email;
        }
        $bccText = '';
        foreach ((array) $this->bcc as $key => $value) {
            $email = is_string($key) ? $key : $value;
            $bccText .= ($bccText) ? "," : $bccText; 
            $bccText .= is_string($key) ? "{$value} <{$email}>" : "<{$email}>";
            // $this->addHeader('Bcc', $bccText);
            $recipients[] = $email;
        }

        // Inspector
        if (!$recipients) {
                
            throw new Exception("Recipient is empty", 400);
        }

        // Modes
        if ($this->transport['mtaModeOn']) {

            $countFailed = 0;
            foreach ($recipients as $key => $recipient) {
                
                $result = $this->sendToMxMta($sender, $recipient);
                if (!$result) {
                    $countFailed ++;
                }
            }

            return (!$countFailed) ? true : false;

        } else {

            return $this->sendToMta($sender, $recipients);
        }
    }
    
    /**
     * Connect to MTA
     *
     * @return boolean
     */
    public function sendToMta(string $sender, array $recipients)
    {
        try {

            // SSL/TLS
            $protocol = ($this->transport['encryption']==='ssl') ? "ssl://" : '';
            
            $socket = SocketHelper::open([
                'address' => $protocol . $this->transport['host'],
                'port' => $this->transport['port'],
            ]);

            // if (!($socket = fsockopen($protocol . $this->transport['host'], $this->transport['port'], $errno, $errstr, 15))) {

            //     throw new Exception("Error connecting to '{$this->transport['host']}' ({$errno}) ({$errstr})", 500);
            // }

            $this->_server_parse($socket, '220');
            
            $this->_socket_send($socket, 'EHLO '.$this->transport['host']);
            $this->_server_parse($socket, '250');

            // STARTTLS 
            if ($this->transport['encryption']==='tls') {

                $this->_socket_send($socket, 'STARTTLS');
                $this->_server_parse($socket, '220');

                // compatibility of TLS version
                $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                    $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }

                if(false == stream_socket_enable_crypto($socket, true, $crypto_method)){
                    
                    throw new Exception('Unable to start tls encryption', 500);
                }

                $this->_socket_send($socket, 'EHLO '.$this->transport['host']);
                $this->_server_parse($socket, '250');
            }

            // AUTH LOGIN flow while auth info is set
            if ($this->transport['username'] && $this->transport['password']) {

                $this->_socket_send($socket, 'AUTH LOGIN');
                $this->_server_parse($socket, '334');
            
                $this->_socket_send($socket, base64_encode($this->transport['username']), true);
                $this->_server_parse($socket, '334');
            
                $this->_socket_send($socket, base64_encode($this->transport['password']), true);
                $this->_server_parse($socket, '235');
            }

            // Socket MAIL FROM (Single sender)
            $this->_socket_send($socket, "MAIL FROM:<{$sender}>");
            $this->_server_parse($socket, '250');

            // Parse $this->to
            foreach ($recipients as $key => $email) {

                // Socket RCPT TO
                $this->_socket_send($socket, "RCPT TO:<{$email}>");
                $this->_server_parse($socket, '250');
            }
            
            // Essential headers
            // $this->addHeader('Subject', $this->subject);
            // $this->addHeader('Subject', "=?{$this->charset}?B?".base64_encode($this->subject)."?=");
            // $this->addHeader('To', $toText);

            // Message-ID
            $timestamp = floor(microtime(true) * 1000);
            $senderDomain = explode('@', $sender);
            $senderDomain = isset($senderDomain[1]) ? $senderDomain[1] : $this->transport['host'];
            $messageId = md5($sender . $timestamp) . $timestamp . "@{$senderDomain}";
            $messageId = "<{$messageId}>";

            $this->_socket_send($socket, 'DATA');
            $this->_server_parse($socket, '354');

            // Header Text
            $this->headers = array_merge($this->headers, [
                'Date' => date("c"),
                'MIME-Version' => "1.0",
                'Message-ID' => $messageId,
                'Content-Transfer-Encoding' => '8bit',
                'Content-Type' => "text/html; charset={$this->charset}",
            ]);
            foreach ($this->headers as $key => $value) {

                $headerText = "{$key}: {$value}";
                $this->_socket_send($socket, $headerText);
            }

            $this->_socket_send($socket, "{$this->body}");
        
            $this->_socket_send($socket, '.');
            $this->_server_parse($socket, '250');
        
            $this->_socket_send($socket, 'QUIT');
            $socket->close();
            // fclose($socket);
            
        } catch (\Exception $e) {
               
            fclose($socket);
            
            if ($this->debugMode === 2) {

                echo $this->logText;
            }
            
            if ($this->debugMode) {

                throw new Exception($e->getMessage(), $e->getCode());
            }

            return false;
        }
        
        // Debug Mode 2
        if ($this->debugMode === 2) {

            echo $this->logText;
        }

        return true;
    }

    /**
     * Connect to MX MTA
     *
     * @return boolean
     */
    public function sendToMxMta(string $sender, string $recipient)
    {
        try {

            // Target Host
            $targetHost;
            $recipientDomain = substr($recipient, strpos($recipient, '@') + 1);
            if (isset($this->cacheDomainMx[$recipientDomain])) {

                $targetHost = $this->cacheDomainMx[$recipientDomain];

            } else {

                $mxData = dns_get_record($recipientDomain, DNS_MX);
                $targetHost = (isset($mxData[0]['target'])) ? $mxData[0]['target'] : $recipientDomain;
                // var_dump($recipientsDomain);var_dump($targetHost);exit;
                $this->cacheDomainMx[$recipientDomain] = $targetHost;
            }
            
            // SSL/TLS
            $protocol = ($this->transport['encryption']==='ssl') ? "ssl://" : '';

            try {

                $socket = SocketHelper::open([
                    'address' => $protocol . $targetHost,
                    'port' => $this->transport['port'],
                ]);

            } catch (\yidas\socketHelper\exception\ConnectException $e) {
                
                return false;
            }

            // if (!($socket = fsockopen($protocol . $targetHost, $this->transport['port'], $errno, $errstr, 15))) {

            //     throw new Exception("Error connecting to '{$targetHost}' ({$errno}) ({$errstr})", 500);
            // }

            $this->_server_parse($socket, '220');
            
            $this->_socket_send($socket, 'EHLO '.$targetHost);
            $this->_server_parse($socket, '250');

            // STARTTLS 
            if ($this->transport['encryption']==='tls') {

                $this->_socket_send($socket, 'STARTTLS');
                $this->_server_parse($socket, '220');

                // compatibility of TLS version
                $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                    $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }

                if(false == stream_socket_enable_crypto($socket, true, $crypto_method)){
                    
                    throw new Exception('Unable to start tls encryption', 500);
                }

                $this->_socket_send($socket, 'EHLO '.$targetHost);
                $this->_server_parse($socket, '250');
            }

            // Socket MAIL FROM (Single sender)
            $this->_socket_send($socket, "MAIL FROM:<{$sender}>");
            $this->_server_parse($socket, '250');

            // Socket RCPT TO
            $this->_socket_send($socket, "RCPT TO:<{$recipient}>");
            $this->_server_parse($socket, '250');
            
            // Essential headers
            // $this->addHeader('Subject', $this->subject);
            // $this->addHeader('Subject', "=?{$this->charset}?B?".base64_encode($this->subject)."?=");
            // $this->addHeader('To', $toText);

            // Message-ID
            $timestamp = floor(microtime(true) * 1000);
            $senderDomain = explode('@', $sender);
            $senderDomain = isset($senderDomain[1]) ? $senderDomain[1] : $this->transport['host'];
            $messageId = md5($sender . $timestamp) . $timestamp . "@{$senderDomain}";
            $messageId = "<{$messageId}>";

            $this->_socket_send($socket, 'DATA');
            $this->_server_parse($socket, '354');

            // Header Text
            $this->headers = array_merge($this->headers, [
                'Date' => date("c"),
                'MIME-Version' => "1.0",
                'Message-ID' => $messageId,
                'Content-Transfer-Encoding' => '8bit',
                'Content-Type' => "text/html; charset={$this->charset}",
            ]);
            foreach ($this->headers as $key => $value) {

                $headerText = "{$key}: {$value}";
                $this->_socket_send($socket, $headerText);
            }

            $this->_socket_send($socket, "{$this->body}");
        
            $this->_socket_send($socket, '.');
            $this->_server_parse($socket, '250');
        
            $this->_socket_send($socket, 'QUIT');
            $socket->close();
            // fclose($socket);
            
        } catch (\Exception $e) {
               
            // fclose($socket);
            
            // if ($this->debugMode === 2) {

            //     echo $this->logText;
            // }
            
            if ($this->debugMode) {

                throw new Exception($e->getMessage(), $e->getCode());
            }

            return false;
        } 
        
        // Debug Mode 2
        // if ($this->debugMode === 2) {

        //     echo $this->logText;
        // }

        return true;
    }

    protected function _socket_send($socketHelper, $message, $hideLog=false)
    {
        $socketHelper->write($message . static::LB);

        $message = $hideLog ? '[credentials hidden]' : trim($message);
        $this->log($message, true);
    }

    /**
     * Server Response Parser
     *
     * @param resource $socket fsockopen resource
     * @param string $expectedResponse
     * @return void
     */
    protected function _server_parse($socketHelper, $expectedResponse, $hideLog=false)
    {
        $serverResponse = $socketHelper->read(); 
        
        if (!(substr($serverResponse, 0, 3) == $expectedResponse)) {
            
            throw new Exception("Unable to send e-mail.{$serverResponse}" . __FILE__. __LINE__, 500);
        }

        $serverResponse = $hideLog ? '[credentials hidden]' : trim($serverResponse);
        $this->log($serverResponse);
    }

    protected function log(string $message, $isSent=false) {
        
        $logRecord = '';
        $directionText = ($isSent) ? ' CLIENT -> SERVER: ' : ' SERVER -> CLIENT: ';
        $prefixText = date("Y-m-d h:i:s") . $directionText;
        $lines = explode("\n", $message);
        foreach ($lines as $key => $line) {
            if ($key == 0) {

                $logRecord = $prefixText . $line;
            } 
            else {

                $logRecord .= str_repeat(" ", strlen($prefixText)) . $line;
            }
            $logRecord .= "\n";
        }
        $this->logText .= $logRecord;

        if ($this->debugMode === 2) {

            echo $logRecord;
        }
    }

    // protected function _socket_send($socket, $message, $hideLog=false)
    // {
    //     fwrite($socket, $message . static::LB);

    //     $message = $hideLog ? '[credentials hidden]' : trim($message);
    //     $this->logText .= date("Y-m-d h:i:s") . ' CLIENT -> SERVER: ' . $message . "\n";
    // }

    // /**
    //  * Server Response Parser
    //  *
    //  * @param resource $socket fsockopen resource
    //  * @param string $expectedResponse
    //  * @return void
    //  */
    // protected function _server_parse($socket, $expectedResponse, $hideLog=false)
    // {
    //     $serverResponse = '';

    //     while (substr($serverResponse, 3, 1) != ' ') {

    //         if (!($serverResponse = fgets($socket, 256))) {
                
    //             throw new Exception('Error while fetching server response codes.'. __FILE__. __LINE__, 500);
    //         }         
            
    //         $serverResponse = $hideLog ? '[credentials hidden]' : trim($serverResponse);
    //         $this->logText .= date("Y-m-d h:i:s") . ' SERVER -> CLIENT: ' . $serverResponse . "\n";
    //     }
     
    //     if (!(substr($serverResponse, 0, 3) == $expectedResponse)) {
            
    //         throw new Exception("Unable to send e-mail.{$serverResponse}" . __FILE__. __LINE__, 500);
    //     }
    // }
}
