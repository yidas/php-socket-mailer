<?php

namespace yidas\socketMailer;

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

    protected $form;

    protected $subject;
    
    protected $body;

    protected $headers;

    protected $charset = 'utf-8';

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
        
        try {
            
            // SSL/TLS
            $protocol = ($this->transport['encryption']==='ssl') ? "ssl://" : '';

            if (!($socket = fsockopen($protocol . $this->transport['host'], $this->transport['port'], $errno, $errstr, 15))) {

                throw new Exception("Error connecting to '{$this->transport['host']}' ({$errno}) ({$errstr})", 500);
            }

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

            // MAIL From Socket with binding Recipients text
            $fromText = "";
            $fromEmail = "";
            if (empty($this->form)) {
                
                throw new Exception("Form is empty", 400);
            }
            elseif (is_string($this->form)) {
                
                $fromText = $fromEmail = $this->form;
            } 
            else if (is_array($this->form)) {

                foreach ($this->form as $key => $value) {
                    
                    $fromEmail = is_string($key) ? $key : $value;
                    
                    $fromText .= ($fromText) ? "," : $fromText; 
                    $fromText .= is_string($key) ? "{$value} <{$key}>" : "<{$value}>";

                    // Single sender
                    break;
                }
            }
            // Socket MAIL FROM (Single sender)
            $this->_socket_send($socket, "MAIL FROM:<{$fromEmail}>");
            $this->_server_parse($socket, '250');


            // RCPT To Socket with binding Recipients text
            $toText = "";
            if (empty($this->form) && !is_array($this->form)) {
                
                throw new Exception("Recipient is empty", 400);
            }
            // Parse $this->to
            foreach ($this->to as $key => $value) {

                $email = is_string($key) ? $key : $value;

                $toText .= ($toText) ? "," : $toText; 
                $toText .= is_string($key) ? "{$value} <{$email}>" : "<{$email}>";

                // Socket RCPT TO
                $this->_socket_send($socket, "RCPT TO:<{$email}>");
                $this->_server_parse($socket, '250');
            }
            
            // Essential headers
            // $this->addHeader('Subject', $this->subject);
            $this->addHeader('Subject', "=?{$this->charset}?B?".base64_encode($this->subject)."?=");
            $this->addHeader('To', $toText);
            $this->addHeader('From', $fromText);

            // Message-ID
            $timestamp = floor(microtime(true) * 1000);
            $senderDomain = explode('@', $fromEmail);
            $senderDomain = isset($senderDomain[1]) ? $senderDomain[1] : $this->transport['host'];
            $messageId = md5($fromEmail . $timestamp) . $timestamp . "@{$senderDomain}";
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
            fclose($socket);
            
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

    protected function _socket_send($socket, $message, $hideLog=false)
    {
        fwrite($socket, $message . static::LB);

        $message = $hideLog ? '[credentials hidden]' : trim($message);
        $this->logText .= date("Y-m-d h:i:s") . ' CLIENT -> SERVER: ' . $message . "\n";
    }

    /**
     * Server Response Parser
     *
     * @param resource $socket fsockopen resource
     * @param string $expectedResponse
     * @return void
     */
    protected function _server_parse($socket, $expectedResponse, $hideLog=false)
    {
        $serverResponse = '';

        while (substr($serverResponse, 3, 1) != ' ') {

            if (!($serverResponse = fgets($socket, 256))) {
                
                throw new Exception('Error while fetching server response codes.'. __FILE__. __LINE__, 500);
            }         
            
            $serverResponse = $hideLog ? '[credentials hidden]' : trim($serverResponse);
            $this->logText .= date("Y-m-d h:i:s") . ' SERVER -> CLIENT: ' . $serverResponse . "\n";
        }
     
        if (!(substr($serverResponse, 0, 3) == $expectedResponse)) {
            
            throw new Exception("Unable to send e-mail.{$serverResponse}" . __FILE__. __LINE__, 500);
        }
    }
}
