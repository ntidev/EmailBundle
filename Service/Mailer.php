<?php

namespace NTI\EmailBundle\Service;

use Swift_Message;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use NTI\EmailBundle\Entity\Email;

class Mailer {

    /** @var ContainerInterface $container */
    private $container;

    /** @var EngineInterface $templating */
    private $templating;

    public function __construct(ContainerInterface $container, EngineInterface $templating) {
        $this->container = $container;
        $this->templating = $templating;
    }

    /**
     * Send an email with a twig template
     *
     * @param $from
     * @param $to
     * @param $bcc
     * @param $subject
     * @param $template
     * @param array $parameters
     * @param null $attachment
     * @return bool
     */
    public function sendFromTemplate($from, $to, $bcc, $subject, $template, $parameters = array(), $attachment = null) {

        // If is a test environment prepend TEST - to the subject
        if($this->container->hasParameter('environment') && $this->container->getParameter('environment') == "test") {
            $subject = "TEST - ".$subject;
        }

        /** @var Swift_Message $message */
        $message = \Swift_Message::newInstance("sendmail -bs")
            ->setSubject($subject)
            ->setFrom($from)
            ->setBody($this->templating->render($template, $parameters), 'text/html');

        $message->setContentType("text/html");

        // Check if we are in development
        if($this->container->hasParameter('environment') && $this->container->getParameter('environment') == "dev") {
            $to = $this->container->hasParameter('event_development_email')  ? $this->container->getParameter('event_development_email') : $to;
        }

        $message->setTo($to);

        if(is_array($bcc)) {
            foreach($bcc as $recipient) {
                $message->addBcc($recipient);
            }
        }

        if($attachment && file_exists($attachment)) {
            $message->attach(\Swift_Attachment::fromPath($attachment));
        }

        if($this->container->hasParameter("app.mailer.master_bcc")) {
            $message->addBcc($this->container->getParameter("app.mailer.master_bcc"));
        }

        try {

            /** @var \Swift_Mailer $mailer */
            $mailer = $this->container->get('mailer');

            // Create a new temporary spool
            $hash = md5(uniqid(time()));
            /** @var \Swift_Transport_SpoolTransport $transport */
            $transport = $mailer->getTransport();
            $tempSpoolPath = $this->container->getParameter('swiftmailer.spool.default.file.path')."/".$hash."/";
            $tempSpool = new \Swift_FileSpool($tempSpoolPath);
            $transport->setSpool($tempSpool);

            // Send the email to generate the file
            $mailer->send($message);

            // Read the temporary spool path
            $files = scandir($tempSpoolPath, SORT_ASC);
            if(count($files) <= 0) {
                if($this->container->has('nti.logger')){
                    $this->container->get('nti.logger')->logError("Unable to find file in temporary spool...");
                }
                return false; // No files were created...
            }
            $filename = $files[0]; // SORT_ASC will guarantee .. and . are at the bottom

            // Copy the file prior to creating the entity in case there is an exception the email can still be sent
            if(is_dir($tempSpoolPath.$filename) || !file_exists($tempSpoolPath.$filename) || !copy($tempSpoolPath.$filename, $tempSpoolPath."/../".$filename)) {
                if($this->container->has('nti.logger')) {
                    $this->container->get('nti.logger')->logError("Unable to copy $filename to main spool folder...");
                }
                return false; // Unable to copy the file...
            }


            // Save the email and delete the hash directory
            $em = $this->container->get('doctrine')->getManager();
            $email = new Email();

            $from = (is_array($message->getFrom())) ? join(', ', array_keys($message->getFrom())) : $message->getFrom();
            $recipients = (is_array($message->getTo())) ? join(', ', array_keys($message->getTo())) : $message->getTo();
            $email->setFilename($filename);
            $email->setMessageFrom($from);
            $email->setMessageTo($recipients);
            $email->setMessageSubject($message->getSubject());
            $email->setMessageBody($message->getBody());
            $email->setFileContent(base64_encode(file_get_contents($tempSpoolPath."/".$filename)));

            $em->persist($email);
            $em->flush();

            @unlink($tempSpoolPath."/".$filename);
            @rmdir($tempSpoolPath);

            return true;

        } catch (\Exception $ex) {
            if($this->container->has('nti.logger')) {
                $this->container->get('nti.logger')->logException($ex);
                $this->container->get('nti.logger')->logError("An error occurred sending an email, see above exception for more");
            }
        }
        return false;

    }


    /**
     * Adds the file to the spool folder so that the
     * cron job resends it again.
     *
     * @param Email $email
     * @return $this
     */
    public function resend(Email $email) {
        $path = $this->container->getParameter('swiftmailer.spool.default.file.path');
        if(!fwrite(fopen($path."/".$email->getFilename(), "w+"), base64_decode($email->getFileContent()))) {
            if($this->container->has('nti.logger')) {
                $this->container->get('nti.logger')->logError("An error occurred creating the email file in the spool for resending.");
            }
        }
        $email->setStatus(Email::STATUS_QUEUE);
        $em = $this->container->get('doctrine')->getManager();
        try {
            $em->flush();
        } catch (\Exception $ex) {
            if($this->container->has('nti.logger')) {
                $this->container->get('nti.logger')->logException($ex);
                $this->container->get('nti.logger')->logError("An error occurred changing the status for resending...");
            }
        }

        return $this;
    }
}