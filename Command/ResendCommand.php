<?php

namespace NTI\EmailBundle\Command;

use NTI\EmailBundle\Entity\Email;
use NTI\EmailBundle\Entity\Smtp;
use Swift_Spool;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ResendCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('nti:email:resend')
            ->setDescription("Resends an email by putting it in the spool folder again and changing the status to QUEUE")
            ->addArgument("emailId", InputArgument::REQUIRED, "The Email Id to be resent")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument("emailId");
        if(!$id) {
            $output->writeln("<error>The Email ID is required.</error>");
            return;
        }

        $em = $this->getContainer()->get('doctrine')->getManager();
        $email = $em->getRepository('NTIEmailBundle:Email')->find($id);
        if(!$email) {
            $output->writeln("<error>The Email was not found.</error>");
            return;
        }

        $smtpService = $this->getContainer()->get('nti.mailer');

        $smtpService->resend($email);
        $output->writeln("<success>The Email was moved to the queue.</success>");
    }
}