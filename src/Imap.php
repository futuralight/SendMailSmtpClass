<?php

namespace Futuralight\YandexMailSender;


class Imap
{

    protected $codeCounter = 1;
    public const SMTP_URL = 'ssl://imap.yandex.com:993';
    protected $connection;
    protected $authString;
    protected $login;
    protected $token;
    protected $errno;
    protected $errstr;
    protected $url;


    public function __construct($login, $token, $url = self::SMTP_URL)
    {
        $this->login = $login;
        $this->token = $token;
        $this->url = $url;
        $this->connect();
        $this->authenticate();
    }

    private function encryptAuthString()
    {
        return base64_encode("user={$this->login}\001auth=Bearer {$this->token}\001\001");
    }

    private function connect()
    {
        $context = stream_context_create();
        stream_context_set_option($context, 'ssl', 'passphrase', '');
        stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
        stream_context_set_option($context, 'ssl', 'verify_peer', false);
        $this->connection = stream_socket_client($this->url ?: self::SMTP_URL, $this->errno, $this->errstr, 30, STREAM_CLIENT_CONNECT, $context);
    }

    private function sendCommand($command)
    {
        if ($this->connection) {
            fwrite($this->connection, "{$command}\r\n");
        } else {
            throw new Exception('Connection is not opened');
        }
    }

    public function authenticate()
    {
        $authArray = [
            'C01 CAPABILITY',
            "A01 AUTHENTICATE XOAUTH2 {$this->encryptAuthString()}"
        ];
        foreach ($authArray as $authCommand) {
            $this->sendCommand($authCommand);
        }
    }

    public function appendMessage($mailbox, $message, $from = "", $to = "", $subject = "", $messageId = "", $mimeVersion = "", $contentType = "", $formData = true, $flags = "(\Seen)")
    {
        if (!isset($mailbox) || !strlen($mailbox)) return false;
        if (!isset($message) || !strlen($message)) return false;
        if (!strlen($flags)) return false;

        $date = date('d-M-Y H:i:s O');
        $crlf = "\r\n";
        if ($formData) {
            if (strlen($from)) $from = "From: $from";
            if (strlen($to)) $to = "To: $to";
            if (strlen($subject)) $subject = "Subject: $subject";
            $messageId = (strlen($messageId)) ? "Message-Id: $messageId" : "Message-Id: " . uniqid();
            $mimeVersion = (strlen($mimeVersion)) ? "MIME-Version: $mimeVersion" : "MIME-Version: 1.0";
            $contentType = (strlen($contentType)) ? "Content-Type: $contentType" : "Content-Type: TEXT/HTML;CHARSET=UTF-8";

            $composedMessage = $date . $crlf;
            if (strlen($from)) $composedMessage .= $from . $crlf;
            if (strlen($subject)) $composedMessage .= $subject . $crlf;
            if (strlen($to)) $composedMessage .= $to . $crlf;
            $composedMessage .= $messageId . $crlf;
            $composedMessage .= $mimeVersion . $crlf;
            $composedMessage .= $contentType . $crlf . $crlf;
            $composedMessage .= $message . $crlf;
        } else {
            $composedMessage = $message;
        }
        $size = strlen($composedMessage);


        $command = "APPEND \"$mailbox\" $flags {" . $size . "}" . $crlf . $composedMessage;

        $this->sendCommand("A" . $this->codeCounter . ' ' . $command);
        $this->readResponse("A" . $this->codeCounter);
        return true;
    }

    private function readResponse($code)
    {
        $response = array();


        $i = 1;
        // $i = 1, because 0 will be status of response
        // Position 0 server reply two dimentional
        // Position 1 message

        while ($line = fgets($this->connection)) {
            $checkLine = preg_split('/\s+/', $line, 0, PREG_SPLIT_NO_EMPTY);
            if (@$checkLine[0] == $code) {
                $response[0][0] = $checkLine[1];
                break;
            } else if (@$checkLine[0] != "*") {
                if (isset($response[1][$i]))
                    $response[1][$i] = $response[1][$i] . $line;
                else
                    $response[1][$i] = $line;
            }
            if (@$checkLine[0] == "*") {
                if (isset($response[0][1]))
                    $response[0][1] = $response[0][1] . $line;
                else
                    $response[0][1] = $line;
                if (isset($response[1][$i])) {
                    $i++;
                }
            }
        }
        $this->codeCounter++;
        return $response;
    }
}
