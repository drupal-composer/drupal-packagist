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
        $loader = new ValidatingArrayLoader(new ArrayLoader());
        $output = new BufferIO('');
        $output->loadConfiguration($config);

        $body = unserialize($message->body);
        $response = false;

        $em = $this->doctrine->getManager();
        $res = $em->createQuery("SELECT p.name FROM Packagist\WebBundle\Entity\Package p WHERE p.name = :name")
            ->setParameters(['name' => $body['package_name']])
            ->getResult();
        if (empty($res)) {
            $output->write("adding {$body['package_name']}");
            $package = new Package();
            $package->setRepository($body['url']);
            $package->setName($body['package_name']);
            $em->persist($package);
            $em->flush();
        }
        $releaseInfoFactory = new ReleaseInfoFactory();
        $releases = $releaseInfoFactory
            ->getReleaseInfo($body['package_name'], [7, 8]);
        if (empty($releases)) {
            $output->write("no valid releases for {$body['package_name']}");
            return ConsumerInterface::MSG_REJECT;
        }
        $package = $this->doctrine
            ->getRepository('PackagistWebBundle:Package')
            ->getPackageByName($body['package_name']);
        try {
            $repository = new VcsRepository(
                array('url' => $package->getRepository()),
                $output,
                $config
            );
            $repository->setLoader($loader);
            $output->write("updating {$body['package_name']}");
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
