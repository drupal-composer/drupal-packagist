<?php
namespace Packagist\WebBundle\Package;

use Composer\Repository\VcsRepository;
use Composer\Factory;
use Composer\Package\Loader\ValidatingArrayLoader;
use Composer\Package\Loader\ArrayLoader;
use Composer\IO\BufferIO;
use Packagist\WebBundle\Entity\Package;
use PhpAmqpLib\Message\AMQPMessage;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use Drupal\ParseComposer\ReleaseInfoFactory;

class BackgroundUpsertConsumer implements ConsumerInterface {

    private $upserter;

    public function __construct(Upserter $upserter)
    {
        $this->upserter = $upserter;
    }

    public function execute(AMQPMessage $message)
    {
        $body     = unserialize($message->body);
        $config   = Factory::createConfig();
        $output   = new BufferIO('');
        $output->loadConfiguration($config);
        $response = false;
        try {
            $this->upserter->execute($body['url'], $body['package_name'], $output);
        }
        catch (\Exception $e) {
            echo $output->getOutput();
            return ConsumerInterface::MSG_REJECT;
        }
        echo $output->getOutput();
        return serialize(array(
            'output' => $output->getOutput()
        ));
    }
}
