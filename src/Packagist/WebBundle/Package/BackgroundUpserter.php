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

class BackgroundUpserter implements ConsumerInterface {

    private $container;
    private $doctrine;
    private $router;

    public function __construct($doctrine, $router, $updater)
    {
        $this->doctrine = $doctrine;
        $this->router   = $router;
        $this->updater  = $updater;
    }

    public function execute(AMQPMessage $message)
    {
        $config = Factory::createConfig();
        $body = unserialize($message->body);
        $packageRepository = $this->doctrine
            ->getRepository('PackagistWebBundle:Package');
        $response = false;
        $em = $this->doctrine->getManager();
        // PackageRepository::packageExists() uses too much caching for us.
        $res = $em->createQuery("SELECT p.name FROM Packagist\WebBundle\Entity\Package p WHERE p.name = :name")
            ->setParameters(['name' => $body['package_name']])
            ->getResult();
        if (empty($res)) {
            echo "adding {$body['package_name']}\n";
            $package = new Package();
            $package->setRepository($body['url']);
            $package->setName($body['package_name']);
            $em->persist($package);
            $em->flush();
        }
        $package  = $packageRepository->getPackageByName($body['package_name']);
        $loader   = new ValidatingArrayLoader(new ArrayLoader());
        $output   = new BufferIO('');
        $output->loadConfiguration($config);
        try {
            $repository = new VcsRepository(
                array('url' => $package->getRepository()),
                $output,
                $config
            );
            $repository->setLoader($loader);
            echo "updating {$body['package_name']}\n";
            $this->updater->update(
                $package,
                $repository
            );
            $output->write('Updated '.$package->getName());
        }
        catch (InvalidRepositoryException $e) {
            $output->write(
                '<error>Broken repository in '
                .$this->router->generate(
                    'view_package',
                    array('name' => $package->getName())
                    , true
                ).': '.$e->getMessage().'</error>'
            );
            throw $e;
        }
        catch (\Exception $e) {
            $output->write(
                '<error>Error updating '.$this->router->generate(
                    'view_package',
                    array('name' => $package->getName()),
                    true
                ).' ['.get_class($e).']: '.$e->getMessage().' at '
                .$e->getFile().':'.$e->getLine().'</error>');
            echo $output->getOutput();
            return ConsumerInterface::MSG_REJECT;
        }
        $response = serialize(array(
            'output' => $output->getOutput()
        ));
        echo $output->getOutput();
        return $response;
    }
}
