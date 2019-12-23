<?php

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
 * swiftMailer mail handler class.
 */

if(!defined('MYBB_SSL'))
{
    define('MYBB_SSL', 1);
}

if(!defined('MYBB_TLS'))
{
    define('MYBB_TLS', 2);
}

class swiftMailer extends MailHandler {
    /**
     * The used function.
     *
     * @var string
     */
    private $functionUsed;

    /**
     * The swiftMailer transport.
     *
     * @var Swift_SmtpTransport
     */
    private $transport;

    /**
     * The Swift Mailer.
     *
     * @var Swift_Mailer
     */
    private $mailer;

    /**
     * The Swift Message.
     *
     * @var Swift_Message
     */
    private $swift_Message;

    /**
     * SMTP username.
     *
     * @var string
     */
    private $username = '';

    /**
     * SMTP password.
     *
     * @var string
     */
    private $password = '';

    /**
     * User authenticated or not.
     *
     * @var boolean
     */
    private $authenticated = false;

    /**
     * How long before timeout.
     *
     * @var integer
     */
    private $timeout = 5;

    /**
     * SMTP status.
     *
     * @var integer
     */
    private $status = 0;

    /**
     * SMTP default port.
     *
     * @var integer
     */
    private $port = 25;

    /**
     * SMTP default secure port.
     *
     * @var integer
     */
    private $secure_port = 465;

    /**
     * SMTP host.
     *
     * @var string
     */
    private $host = '';

    /**
     * The last received error message from the SMTP server.
     *
     * @var string
     */
    private $last_error = '';

    /**
     * Are we keeping the connection to the SMTP server alive?
     *
     * @var boolean
     */
    private $keep_alive = false;

    /**
     * Whether to use TLS encryption.
     *
     * @var boolean
     */
    private $use_tls = false;

    /**
     * Encryption protocol.
     *
     * @var string
     */
    private $protocol = '';

    /**
     * @return string
     */
    public function getFunctionUsed()
    {
        return $this->functionUsed;
    }

    /**
     * @param string $functionUsed
     */
    public function setFunctionUsed($functionUsed)
    {
        $this->functionUsed = $functionUsed;
    }

    /**
     * @return Swift_SmtpTransport
     */
    public function getTransport()
    {
        return $this->transport;
    }

    /**
     * @param Swift_SmtpTransport $transport
     */
    public function setTransport($transport)
    {
        $this->transport = $transport;
    }

    /**
     * @return Swift_Mailer
     */
    public function getMailer()
    {
        return $this->mailer;
    }

    /**
     * @param Swift_Mailer $mailer
     */
    public function setMailer($mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * @return Swift_Message
     */
    public function getSwiftMessage()
    {
        return $this->swift_Message;
    }

    /**
     * @param Swift_Message $swift_Message
     */
    public function setSwiftMessage($swift_Message)
    {
        $this->swift_Message = $swift_Message;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param string $host
     */
    public function setHost($host)
    {
        $this->host = $host;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param int $port
     */
    public function setPort($port)
    {
        $this->port = $port;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return bool
     */
    public function isUseTls()
    {
        return $this->use_tls;
    }

    /**
     * @param bool $use_tls
     */
    public function setUseTls($use_tls)
    {
        $this->use_tls = $use_tls;
    }

    /**
     * @return string
     */
    public function getProtocol()
    {
        return $this->protocol;
    }

    /**
     * @param string $protocol
     */
    public function setProtocol($protocol)
    {
        $this->protocol = $protocol;
    }

    function __construct()
    {
        global $mybb;

        $this->setFunctionUsed('swiftMailer()');

        switch($mybb->settings['secure_smtp'])
        {
            case MYBB_SSL:
                $this->setProtocol('ssl');
                break;
            case MYBB_TLS:
                $this->setUseTls(true);
                break;
        }

        if(empty($mybb->settings['smtp_host']))
        {
            $this->setHost(@ini_get('SMTP'));
        }
        else
        {
            $this->setHost($mybb->settings['smtp_host']);
        }

        if(empty($mybb->settings['smtp_port']))
        {
            $this->setPort($this->secure_port);
        }
        else
        {
            $this->setPort($mybb->settings['smtp_port']);
        }

        $this->setUsername($mybb->settings['smtp_user']);
        $this->setPassword($mybb->settings['smtp_pass']);
    }

    /**
     * Builds the whole mail.
     * To be used by the different email classes later.
     *
     * @param string $to to email.
     * @param string $subject subject of email.
     * @param string $message message of email.
     * @param string $from from email.
     * @param string $charset charset of email.
     * @param string $headers headers of email.
     * @param string $format format of the email (HTML, plain text, or both?).
     * @param string $message_text plain text version of the email.
     * @param string $return_email the return email address.
     */
    function buildSwiftMessage($to, $subject, $message, $from="", $charset="", $headers="", $format="text", $message_text="", $return_email="")
    {
        global $mybb;

        if($return_email)
        {
            $this->return_email = $return_email;
        }
        else
        {
            $this->return_email = "";
            $this->return_email = $this->get_from_email();
        }

        $this->set_to($to);
        $to = $this->to;
        $this->set_subject($subject);
        $subject = $this->subject;

        if($charset)
        {
            $this->set_charset($charset);
        }

        $this->parse_format = $format;

        // Create a message
        $this->setSwiftMessage(new Swift_Message());

        // Gets the Swift Message object
        $swift_Message = $this->getSwiftMessage();

        // Set a "subject"
        $swift_Message->setSubject($subject);

        $this->setMessage($message, $message_text);

        // Set the "From address"
        if($from)
        {
            $this->from = $from;
            $swift_Message->setFrom($from);
        }
        else
        {
            $this->from = $this->get_from_email();
            /*$this->from_named = '"'.$this->utf8_encode($mybb->settings['bbname']).'"';
            $this->from_named .= " <".$this->from.">";*/
            $swift_Message->setFrom($this->from, $this->utf8_encode($mybb->settings['bbname']));
        }

        // Set the "To address" [Use setTo method for multiple recipients, argument should be array]
        $swift_Message->addTo($to);
    }

    /**
     * Sets and formats the email message.
     *
     * @param string $message message
     * @param string $message_text
     */
    function setMessage($message, $message_text="")
    {
        // Gets the Swift Message object
        $swift_Message = $this->getSwiftMessage();

        $message = $this->cleanup_crlf($message);

        if($message_text)
        {
            $message_text = $this->cleanup_crlf($message_text);
        }

        $this->setClassMessage($message);

        if($this->parse_format == "html" || $this->parse_format == "both")
        {
            // Set the plain-text "Body"
            $swift_Message->setBody($message_text);

            // Set a "Body"
            $swift_Message->addPart($message);
        }
        else
        {
            // Set a "Body"
            $swift_Message->addPart($message);

            // Set the plain-text "Body"
            $swift_Message->setBody($message);
        }
    }

    /**
     * Sets the email message.
     *
     * @param string $message message
     */
    function setClassMessage($message)
    {
        $this->message = $message;
    }

    /**
     * Sends the email.
     *
     * @return bool whether or not the email got sent or not.
     */
    function send()
    {
        global $lang, $mybb;

        $functionUsed = $this->getFunctionUsed();

        // Send the message
        $mailer = $this->getMailer();
        $swift_Message = $this->getSwiftMessage();

        if ($mailer->send($swift_Message))
        {
            $this->fatal_error("This email GOT SEND using the PHP {$functionUsed} Library.");
            return true;
        }
        else
        {
            $this->fatal_error("MyBB was unable to send the email using the PHP {$functionUsed} Library.");
            return false;
        }
    }
}