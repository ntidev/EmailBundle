<?php

namespace NTI\EmailBundle\Command;

use NTI\EmailBundle\Entity\Email;
use NTI\EmailBundle\Repository\EmailRepository;
use NTI\EmailBundle\Entity\Smtp;
use Swift_Spool;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ResendCommand extends Command
{
    private $container;

    /** @var EntityManager */
    private $em;

    /** @var EmailRepository */
    private $emailRepository;

    public function __construct(ContainerInterface $container, EntityManagerInterface $em, EmailRepository $emailRepository)
    {
        $this->container = $container;
        $this->em = $em;
        $this->emailRepository = $emailRepository;

        parent::__construct();
    }


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
        try{

        	$id = $input->getArgument("emailId");
        	if(!$id) {
            		$output->writeln("<error>The Email ID is required.</error>");
            		return;
        	}

        	$email = $this->emailRepository->find($id);
        	if(!$email) {
            		$output->writeln("<error>The Email was not found.</error>");
            		return;
        	}

        	$smtpService = $this->container->get('nti.mailer');

        	$smtpService->resend($email);
        	$output->writeln("<success>The Email was moved to the queue.</success>");
		return 0;
	} catch(\Exception $e){
		return 1;
	}
    }
}
