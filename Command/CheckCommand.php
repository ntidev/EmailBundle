<?php

namespace NTI\EmailBundle\Command;

use NTI\EmailBundle\Entity\Email;
use NTI\EmailBundle\Entity\Smtp;
use Swift_Spool;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('nti:email:check')
            ->setDescription('Check the spool folder for changes in the email statuses')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();

        /** @var Smtp $smtp */
        $smtp = $em->getRepository('NTIEmailBundle:Smtp')->findOneBy(array("environment" => $this->getContainer()->getParameter('environment')));

        if (!$smtp) {
            if ($this->getContainer()->has('nti.logger')) {
                $this->getContainer()->get('nti.logger')->logError("Unable to find an SMTP configuration for this environment.");
            }
            return false;
        }


        $spoolFolder = $this->getContainer()->getParameter('swiftmailer.spool.default.file.path');

        // Send Emails
        //create an instance of the spool object pointing to the right position in the filesystem
        $spool = new \Swift_FileSpool($spoolFolder);

        //create a new instance of Swift_SpoolTransport that accept an argument as Swift_FileSpool
        $transport = \Swift_SpoolTransport::newInstance($spool);

        //now create an instance of the transport you usually use with swiftmailer
        //to send real-time email
        $realTransport = \Swift_SmtpTransport::newInstance(
            $smtp->getHost(),
            $smtp->getPort(),
            $smtp->getEncryption()
        )
        ->setUsername($smtp->getUser())
        ->setPassword($smtp->getPassword());

        /** @var \Swift_FileSpool $spool */
        $spool = $transport->getSpool();
        $spool->setMessageLimit(10);
        $spool->setTimeLimit(100);

        $sent = 0;

        try {
            $sent = $spool->flushQueue($realTransport);
        } catch (\Exception $ex) {
            if($this->getContainer()->has('nti.logger')){
                $this->getContainer()->get('nti.logger')->logException($ex);
            }
        }

        $output->writeln("Sent ".$sent." emails.");

        // Check email statuses
        $emails = $em->getRepository('NTIEmailBundle:Email')->findEmailsToCheck();

        if(count($emails) <= 0) {
            $output->writeln("No emails to check...");
            return;
        }

        /** @var Email $email */
        foreach($emails as $email) {

            // Check if it is sending
            if(file_exists($spoolFolder."/".$email->getFilename().".sending")) {
                $retry = new \DateTime();
                $retry->sub(new \DateInterval('PT1M'));
                if($email->getLastCheck() < $retry) {
                    // Retry
                    if($email->getRetryCount() >= 5) {
                        $email->setLastCheck(new \DateTime());
                        $email->setRetryCount(0); // Reset count
                        $email->setStatus(Email::STATUS_FAILURE);
                        continue;
                    } else {
                        @rename($spoolFolder."/".$email->getFilename().".sending", $spoolFolder."/".$email->getFilename());
                        $count = $email->getRetryCount() ? $email->getRetryCount() : 1;
                        $email->setRetryCount($count + 1);
                        $email->setStatus(Email::STATUS_QUEUE);
                        continue;
                    }
                }
                $email->setStatus(Email::STATUS_SENDING);
                continue;
            }

            // Update last check date
            $email->setLastCheck(new \DateTime());


            // Check if it's creating, in this case, the mailer didn't get to create the
            // file inside the hash folder before the code reaches it, therefore we have to check
            // the hash folder so we can finish that process here
            if($email->getStatus() == Email::STATUS_CREATING) {
                $filename = null;
                $tempSpoolPath = $email->getPath().$email->getHash()."/";
                // Read the temporary spool path
                $files = scandir($tempSpoolPath, SORT_ASC);
                if(count($files) <= 0) {
                    if($this->getContainer()->has('nti.logger')){
                        $this->getContainer()->get('nti.logger')->logError("Unable to find file in temporary spool...");
                    }
                }

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
                    // Log the error and proceed with the process, the check command will take care of moving
                    // the file if the $mailer->send() still hasn't created the file
                    if($this->getContainer()->has('nti.logger')) {
                        $this->getContainer()->get('nti.logger')->logException($ex);
                        $this->getContainer()->get('nti.logger')->logError("An error occurred copying the file $filename from $tempSpoolPath to the main spool folder...");
                    }
                }
            }

            // C1heck if it failed
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
            if($this->getContainer()->has('nti.logger')) {
                $this->getContainer()->get('nti.logger')->logDebug("Finished checking ".count($emails)." emails.");
            }
        } catch (\Exception $ex) {
            $output->writeln("An error occurred while checking the emails..");
            if($this->getContainer()->has('nti.logger')) {
                $this->getContainer()->get('nti.logger')->logException($ex);
            }
        }

    }
}