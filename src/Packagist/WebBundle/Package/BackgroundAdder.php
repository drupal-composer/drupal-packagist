<?php

namespace Packagist\WebBundle\Package;

use Packagist\WebBundle\Entity\Package;
use PhpAmqpLib\Message\AMQPMessage;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;

class BackgroundAdder implements ConsumerInterface {

    private $doctrine;

    public function __construct($doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public function execute(AMQPMessage $message)
    {
        $body = unserialize($message->body);
        $packageRepository = $this->doctrine
            ->getRepository('PackagistWebBundle:Package');
        $em = $this->doctrine->getEntityManager();
        if (!$packageRepository->packageExists($body['package_name'])) {
            echo "acking {$body['package_name']}\n";
            $package = new Package();
            $package->setRepository($body['url']);
            $package->setName($body['package_name']);
            $em->persist($package);
            $em->flush();
            return serialize(array('output' => 'Added '. $body['package_name']));
        }
        echo "nacking {$body['package_name']}\n";
        $em->clear();
        return ConsumerInterface::MSG_REJECT;
    }
}
