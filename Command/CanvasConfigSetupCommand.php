<?php
namespace BridgewaterCollege\Bundle\CanvasApiBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

// Additional Includes:
use BridgewaterCollege\Bundle\CanvasApiBundle\Utils\CanvasApiServiceHandler;

class CanvasConfigSetupCommand extends Command
{
    private $canvasApiServiceHandler;

    public function __construct(CanvasApiServiceHandler $canvasApiServiceHandler)
    {
        $this->canvasApiServiceHandler = $canvasApiServiceHandler;
        // you *must* call the parent constructor
        parent::__construct();
    }

    protected function configure()
    {
        // to run manually: php bin/console canvas-api:create-canvas-configuration
        $this
            ->setName('canvas-api:create-canvas-configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('There can be only one configuration per environment, using the same env such as (dev/prod) again will overwrite an existing config. Do you want to continue (y/n): ', false);
        if (!$helper->ask($input, $output, $question)) {
            $output->writeln("System: Aborting...\r\n");
            return;
        }

        $question = new Question('Please enter the configuration\'s env name e.g. (dev, prod): ');
        while(!$envName = $helper->ask($input, $output, $question)) {
            $output->writeln('Warning! env name must be set');
        }

        $question = new Question('Please enter your canvas api access token: ');
        while(!$apiToken = $helper->ask($input, $output, $question)) {
            $output->writeln('Warning! the canvas api access token must be set');
        }

        $question = new Question('Please enter your canvas instance url: ');
        while(!$url = $helper->ask($input, $output, $question)) {
            $output->writeln('Warning! the canvas instance url must be set');
        }

        $this->canvasApiServiceHandler->setCanvasConfiguration($envName, $apiToken, $url);
    }
}