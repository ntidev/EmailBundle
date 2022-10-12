<?php

namespace NTI\EmailBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use NTI\EmailBundle\Service\Mailer;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CheckCommand extends Command
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct();
    }


    protected function configure()
    {
        $this
            ->setName('nti:email:check')
            ->setDescription('Check the spool folder for changes in the email statuses')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
       try{
            $this->container->get('nti.mailer')->check($output);
            return 0;
        } catch(\Exception $e){
            return 1;
        }
    }

}
