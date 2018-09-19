<?php

namespace yidas\socketMailer;

use Exception;

/**
 * Socket Mailer
 * 
 * @author  Nick Tsai <myintaer@gmail.com>
 * @version 1.0.0
 */
class Mailer implements MailerInterface
{
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
            
            $protocol = ($this->transport['encryption']==='ssl') ? "ssl://" : '';

            if (!($socket = fsockopen($protocol . $this->transport['host'], $this->transport['port'], $errno, $errstr, 15))) {

                throw new Exception("Error connecting to '{$this->transport['host']}' ({$errno}) ({$errstr})", 500);
            }

            $this->_server_parse($socket, '220');
            
            $this->_socket_send($socket, 'EHLO '.$this->transport['host']."\r\n");
            $this->_server_parse($socket, '250');

            // TLS Encryption
            if ($this->transport['encryption']==='tls') {

                $this->_socket_send($socket, 'STARTTLS'."\r\n");
                $this->_server_parse($socket, '220');

                if(false == stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)){
                    
                    throw new Exception('Unable to start tls encryption', 500);
                }

                $this->_socket_send($socket, 'EHLO '.$this->transport['host']."\r\n");
                $this->_server_parse($socket, '250');
            }

            // AUTH LOGIN flow while auth info is set
            if ($this->transport['username'] && $this->transport['password']) {

                $this->_socket_send($socket, 'AUTH LOGIN'."\r\n");
                $this->_server_parse($socket, '334');
            
                $this->_socket_send($socket, base64_encode($this->transport['username'])."\r\n");
                $this->_server_parse($socket, '334');
            
                $this->_socket_send($socket, base64_encode($this->transport['password'])."\r\n");
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
            $this->_socket_send($socket, "MAIL FROM:<{$fromEmail}>\r\n");
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
                $this->_socket_send($socket, "RCPT TO:<{$email}>\r\n");
                $this->_server_parse($socket, '250');
            }
            
            // Essential headers
            // $this->addHeader('Subject', $this->subject);
            $this->addHeader('Subject', "=?UTF-8?B?".base64_encode($this->subject)."?=");
            $this->addHeader('To', $toText);
            $this->addHeader('From', $fromText);

            // Header Text
            $headerText = '';
            foreach ($this->headers as $key => $value) {

                $headerText .= "{$key}: {$value}\r\n";
            }
            // Postfix Headers
            $headerText .= "MIME-Version: 1.0\r\n"
                ."Content-Type: text/html; charset=utf-8\r\n"
                ."Content-Transfer-Encoding: 8bit\r\n\r\n";

            $this->_socket_send($socket, 'DATA'."\r\n");
            $this->_server_parse($socket, '354');
        
            $this->_socket_send($socket, "{$headerText}{$this->body}\r\n");
            // echo "{$headerText}\r\n\r\n{$this->body}\r\n";exit;
        
            $this->_socket_send($socket, '.'."\r\n");
            $this->_server_parse($socket, '250');
        
            $this->_socket_send($socket, 'QUIT'."\r\n");
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

            return $this->logText;
        }

        return true;
    }

    protected function _socket_send($socket, $message)
    {
        $this->logText .= date("Y-m-d h:i:s") . ' CLIENT -> SERVER: ' . $message;

        fwrite($socket, $message);
    }

    /**
     * Server Response Parser
     *
     * @param resource $socket fsockopen resource
     * @param string $expectedResponse
     * @return void
     */
    protected function _server_parse($socket, $expectedResponse)
    {
        $serverResponse = '';

        while (substr($serverResponse, 3, 1) != ' ') {

            if (!($serverResponse = fgets($socket, 256))) {
                
                throw new Exception('Error while fetching server response codes.'. __FILE__. __LINE__, 500);
            }         
            
            $this->logText .= date("Y-m-d h:i:s") . ' SERVER -> CLIENT: ' . trim($serverResponse) . "\n";
        }
     
        if (!(substr($serverResponse, 0, 3) == $expectedResponse)) {
            
            throw new Exception("Unable to send e-mail.{$serverResponse}" . __FILE__. __LINE__, 500);
        }
    }
}
