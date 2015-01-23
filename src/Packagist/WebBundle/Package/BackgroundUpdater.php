<?php
namespace Packagist\WebBundle\Package;

use Packagist\WebBundle\Command\UpdatePackagesCommand;
use PhpAmqpLib\Message\AMQPMessage;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BackgroundUpdater implements ConsumerInterface
{

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function execute(AMQPMessage $message)
    {
        $data = unserialize($message->body);
        $package = $data['package_name'];

        $command = new UpdatePackagesCommand();
        $command->setContainer($this->container);
        $input = new ArrayInput(array(
          'package' => $package,
        ));

        $output = new NullOutput();
        $resultCode = $command->run($input, $output);
    }
}
