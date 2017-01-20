<?php

namespace NTI\EmailBundle\Command;

use Doctrine\ORM\EntityManager;
use NTI\EmailBundle\Entity\Email;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        $spoolFolder = $this->getContainer()->getParameter('swiftmailer.spool.default.file.path');
        $em = $this->getContainer()->get('doctrine')->getManager();
        $emails = $em->getRepository('NTIEmailBundle:Email')->findEmailsToCheck();

        if(count($emails) <= 0) {
            $output->writeln("No emails to check...");
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