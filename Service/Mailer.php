<?php

namespace NTI\EmailBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use NTI\EmailBundle\Entity\Email;
use NTI\EmailBundle\Entity\Smtp;
use NTI\EmailBundle\Utilities\StringUtilities;
use Swift_FileSpool;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Mailer {

    /** @var ContainerInterface $container */
    private $container;

    /** @var EngineInterface $templating */
    private $templating;
    
    /** @var bool $devMode */
    private $devMode = false;

    /** @var string $devTo */
    private $devTo;
    /** @var string $devCc */
    private $devCc;
    /** @var string $devBcc */
    private $devBcc;

    public function __construct(ContainerInterface $container, EngineInterface $templating) {
        $this->container = $container;
        $this->templating = $templating;

        $devMode = $this->container->getParameter('nti_email.dev_mode');
        $this->devMode = is_array($devMode) && isset($devMode["enabled"]) && $devMode["enabled"] == "true";
        if($this->devMode) {
            $this->devTo = $devMode["to"];
            $this->devCc = $devMode["cc"];
            $this->devBcc = $devMode["bcc"];
        }
    }

    /**
     * Send an email with a twig template
     *
     * @param $from
     * @param $to
     * @param $cc
     * @param $bcc
     * @param $subject
     * @param $template
     * @param array $parameters
     * @param array $attachments
     * @return bool
     */
    public function sendFromTemplate($from, $to, $cc, $bcc, $subject, $template, $parameters = array(), $attachments = array()) {
        $html = $this->templating->render($template, $parameters);
        return $this->sendEmail($from, $to, $cc, $bcc, $subject, $html, $attachments);
    }

    /**
     * Send an email with a twig template
     *
     * @param $from
     * @param $to
     * @param $cc
     * @param $bcc
     * @param $subject
     * @param $html
     * @param array $attachments
     * @return bool
     */
    public function sendEmail($from, $to, $cc, $bcc, $subject, $html, $attachments = array()) {
        return $this->processEmail($from, $to, $cc, $bcc, $subject, $html, $attachments);
    }

    /**
     *
     * @param Email $email
     * @return $this
     */
    public function resend(Email $email) {
        $this->processEmail(
            $email->getMessageFrom(),
            $email->getMessageTo(),
            $email->getMessageCc(),
            $email->getMessageBcc(),
            $email->getMessageSubject(),
            $email->getMessageBody(),
            $email->getAttachments()
        );
        return $this;
    }

    /**
     * Test the SMTP configuration
     *
     * @param Smtp $smtp
     * @return $this
     */
    public function testConfiguration(Smtp $smtp) {
        if(!$smtp) {
            return false;
        }

        $realTransport = new \Swift_SmtpTransport(
            $smtp->getHost(),
            $smtp->getPort(),
            $smtp->getEncryption()
        );
        $realTransport->setUsername($smtp->getUser())->setPassword($smtp->getPassword());

        try {
            $realTransport->start();
            return true;
        } catch (\Exception $ex) {
            $this->container->get('logger')->log(\Monolog\Logger::CRITICAL, StringUtilities::BeautifyException($ex));
            return false;
        }
    }


    /**
     * Checks the email spool and sees if any emails should be sent
     *
     * @param OutputInterface|null $output
     * @return bool|void
     */
    public function check(OutputInterface $output = null) {

        $em = $this->container->get('doctrine')->getManager();

        $configurations = $em->getRepository('NTIEmailBundle:Smtp')->findAll();
        
        /** @var Smtp $smtp */
        foreach ($configurations as $smtp){
            $this->handleSmtpSpool($smtp, $output);
        }

    }

    /**
     * @param Smtp $smtp
     * @param OutputInterface|null $output
     * @return bool|void
     * @throws \Swift_IoException
     */
    public function handleSmtpSpool(Smtp $smtp, OutputInterface $output = null){

        $em = $this->container->get('doctrine')->getManager();

        // Spool Directory
        $spoolFolder = $smtp->getSpoolDir();
        // Send Emails
        //create an instance of the spool object pointing to the right position in the filesystem
        /** @var Swift_FileSpool $spool */
        $spool = new \Swift_FileSpool($spoolFolder);
        //create a new instance of Swift_SpoolTransport that accept an argument as Swift_FileSpool
        $transport = new \Swift_SpoolTransport($spool);
        //now create an instance of the transport you usually use with swiftmailer
        //to send real-time email
        $realTransport = new \Swift_SmtpTransport(
            $smtp->getHost(),
            $smtp->getPort(),
            $smtp->getEncryption()
        );
        $realTransport->setUsername($smtp->getUser())->setPassword($smtp->getPassword());
        $spool = $transport->getSpool();
        $spool->setMessageLimit(10);
        $spool->setTimeLimit(100);
        $sent = $spool->flushQueue($realTransport);
        $output->writeln("Sent ".$sent." emails with config: {$smtp->getUniqueId()}.");

        // Check email statuses
        $emails = $em->getRepository(Email::class)->findEmailsToCheckByConfigName($smtp->getUniqueId());
        if(count($emails) <= 0) {
            $output->writeln("No emails to check with config: {$smtp->getUniqueId()}....");
            return;
        }
        /** @var Email $email */
        foreach($emails as $email) {
            // Update last check date
            $email->setLastCheck(new \DateTime());
            // Check if it is sending
            if(file_exists($spoolFolder."/".$email->getFilename().".sending")) {
                $email->setStatus(Email::STATUS_SENDING);
                continue;
            }
            // Check if it's creating, in this case, the mailer didn't get to create the
            // file inside the hash folder before the code reaches it, therefore we have to check
            // the hash folder so we can finish that process here
            if($email->getStatus() == Email::STATUS_CREATING) {
                $filename = null;
                $tempSpoolPath = $email->getPath().$email->getHash()."/";
                // Read the temporary spool path
                $files = scandir($tempSpoolPath, SORT_ASC);                
                foreach($files as $file) {
                    if ($file == "." || $file == "..") continue;
                    $filename = $file;
                    break;
                }
                // Copy the file
                try {
                    if($filename != null) {
                        copy($tempSpoolPath.$filename, $tempSpoolPath."../".$filename);
                        $email->setFilename($filename);
                        $email->setFileContent(base64_encode(file_get_contents($tempSpoolPath."/".$filename)));
                        $email->setStatus(Email::STATUS_QUEUE);
                        @unlink($tempSpoolPath.$filename);
                        @rmdir($tempSpoolPath);
                    }
                    continue;
                } catch (\Exception $ex) {
                    $this->container->get('logger')->log(\Monolog\Logger::CRITICAL, StringUtilities::BeautifyException($ex));
                }
            }
            // Check if it failed
            if(file_exists($spoolFolder."/".$email->getFilename().".failure")) {
                // Attempt to reset it
                @rename($spoolFolder."/".$email->getFilename().".failure", $spoolFolder."/".$email->getFilename());
                $email->setStatus(Email::STATUS_FAILURE);
                continue;
            }
            // Check if the file doesn't exists
            if(!file_exists($spoolFolder."/".$email->getFilename())) {
                $email->setStatus(Email::STATUS_SENT);
                continue;
            }
        }
        try {
            $em->flush();            
        } catch (\Exception $ex) {
            $this->container->get('logger')->log(\Monolog\Logger::CRITICAL, StringUtilities::BeautifyException($ex));
        }
    }

    /**
     * @param Swift_Message $message
     * @param $body
     * @return mixed
     */
    private function embedBase64Images(\Swift_Message $message, $body)
    {
        // Temporary directory to save the images
        $tempDir = "/tmp";

        $arrSrc = array();
        if (!empty($body))
        {
            preg_match_all('/<img[^>]+>/i', stripcslashes($body), $imgTags);

            //All img tags
            for ($i=0; $i < count($imgTags[0]); $i++)
            {
                preg_match('/src="([^"]+)/i', $imgTags[0][$i], $withSrc);

                //Remove src
                $withoutSrc = str_ireplace('src="', '', $withSrc[0]);
                $srcContent = $withoutSrc; // Save the previous content to replace with the cid

                //data:image/png;base64,
                if (strpos($withoutSrc, ";base64,"))
                {
                    //data:image/png;base64,.....
                    list($type, $data) = explode(";base64,", $withoutSrc);
                    //data:image/png
                    list($part, $ext) = explode("/", $type);
                    //Paste in temp file
                    $withoutSrc = $tempDir."/".uniqid("temp_").".".$ext;
                    @file_put_contents($withoutSrc, base64_decode($data));
                    $cid = $message->embed((\Swift_Image::fromPath($withoutSrc)));
                    $body = str_replace($srcContent, $cid, $body);
                }

                //Set to array
                $arrSrc[] = $withoutSrc;
            }
        }
        return $body;
    }



    /**
     * Send an email with a twig template
     *
     * @param $from
     * @param $to
     * @param array $cc
     * @param array $bcc
     * @param $subject
     * @param string $html
     * @param array $attachments
     * @return bool
     */
    private function processEmail($from, $to, $cc = array(), $bcc = array(), $subject, $html = "", $attachments = array()) {

        if($this->devMode) {
            $to = $this->devTo;
            $cc = $this->devCc;
            $bcc = $this->devBcc;
            $subject = "TEST - ".$subject;
        }

        /** @var Swift_Message $message */
        $message = new \Swift_Message($subject);
        $message->setFrom($from);

        $body = $this->embedBase64Images($message, $html);

        $message->setBody($body, 'text/html');

        $message->setContentType("text/html");

        if(is_array($to)) {
            foreach($to as $recipient) {
                if($recipient != "" && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $message->addTo($recipient);
                }
            }
        } elseif($to != "" && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $message->setTo($to);
        } else {
            $this->container->get('logger')->log(\Monolog\Logger::CRITICAL, "Invalid recipient: ".json_encode($to));
            return false;
        }

        if(is_array($bcc)) {
            foreach($bcc as $recipient) {
                if($recipient != "" && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {                    
                    $message->addBcc($recipient);
                }
            }
        } elseif($bcc != "" && filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
            $message->setBcc($bcc);
        }

        if(is_array($cc)) {
            foreach($cc as $recipient) {
                if($recipient != "" && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $message->addCc($recipient);
                }
            }
        } elseif($cc != "" && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
            $message->setCc($cc);
        }

        if(is_array($attachments)) {
            foreach($attachments as $attachment) {
                if(file_exists($attachment) && is_readable($attachment)) {
                    $message->attach(\Swift_Attachment::fromPath($attachment));
                }
            }
        }

        if($this->container->hasParameter("app.mailer.master_bcc")) {
            $message->addBcc($this->container->getParameter("app.mailer.master_bcc"));
        }

        try {
            /** @var EntityManagerInterface $em */
            $em = $this->container->get('doctrine')->getManager();

            /** @var Smtp $smtp */
            $uniqueId = (is_array($from) && count($from) > 0) ? $from[0] : $from;
            $smtp = $em->getRepository(Smtp::class)->findOneBy(array("uniqueId" => strtolower($uniqueId)));

            if (!$smtp) {
                $this->container->get('logger')->log(\Monolog\Logger::WARNING, "Unable to find an SMTP configuration with the UniqueID of {$from}");
                return false;
            }


            // Create a new temporary spool
            $hash = md5(uniqid(time()));
            $tempSpoolPath = $smtp->getSpoolDir()."/".$hash."/";
            $tempSpool = new \Swift_FileSpool($tempSpoolPath);

            /** @var \Swift_Mailer $mailer */
            $mailer = new \Swift_Mailer(new \Swift_SpoolTransport($tempSpool));

            $transport = $mailer->getTransport();
            $transport->setSpool($tempSpool);

            // Send the email to generate the file
            $mailer->send($message);

            // Read the temporary spool path
            $files = scandir($tempSpoolPath, SORT_ASC);
            if(count($files) <= 0) {
                if($this->container->has('nti.logger')){
                    $this->container->get('nti.logger')->logError("Unable to find file in temporary spool with config: {$smtp->getUniqueId()}...");
                }
            }
            $filename = null;

            foreach($files as $file) {
                if ($file == "." || $file == "..") continue;
                $filename = $file;
                break;
            }

            // Copy the file
            if($filename != null) {
                try {
                    copy($tempSpoolPath.$filename, $tempSpoolPath."../".$filename);
                } catch (\Exception $ex) {
                    $this->container->get('logger')->log(\Monolog\Logger::CRITICAL, StringUtilities::BeautifyException($ex));
                }
    
            }
            
            // Save the email and delete the hash directory
            $em = $this->container->get('doctrine')->getManager();
            $email = new Email();

            $from = (is_array($message->getFrom())) ? join(', ', array_keys($message->getFrom())) : $message->getFrom();
            $recipients = (is_array($message->getTo())) ? join(', ', array_keys($message->getTo())) : $message->getTo();
            $email->setFilename($filename);
            $email->setPath($smtp->getSpoolDir()."/");
            $email->setMessageFrom($from);
            $email->setMessageCc($cc);
            $email->setMessageBcc($bcc);
            $email->setMessageTo($recipients);
            $email->setMessageSubject($message->getSubject());
            $email->setMessageBody($message->getBody());
            $email->setAttachments($attachments);
            if($filename == null) {
                $email->setStatus(Email::STATUS_FAILURE);
            }

            $em->persist($email);
            $em->flush();

            @unlink($tempSpoolPath."/".$filename);
            @rmdir($tempSpoolPath);

            return true;

        } catch (\Exception $ex) {
            $this->container->get('logger')->log(\Monolog\Logger::CRITICAL, StringUtilities::BeautifyException($ex));
        }
        return false;
    }
}
