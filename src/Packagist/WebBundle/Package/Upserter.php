<?php
namespace Packagist\WebBundle\Package;

use Composer\Repository\InvalidRepositoryException;
use Composer\Repository\VcsRepository;
use Composer\Factory;
use Composer\Package\Loader\ValidatingArrayLoader;
use Composer\Package\Loader\ArrayLoader;
use Composer\IO\BufferIO;
use Packagist\WebBundle\Entity\Package;
use PhpAmqpLib\Message\AMQPMessage;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use Drupal\ParseComposer\ReleaseInfoFactory;

class Upserter {

    private $container;
    private $doctrine;
    private $router;

    public function __construct($doctrine, $router, $updater)
    {
        $this->doctrine = $doctrine;
        $this->router   = $router;
        $this->updater  = $updater;
    }

    public function execute($url, $packageName, BufferIO $output)
    {
        $config = Factory::createConfig();
        $packageRepository = $this->doctrine
            ->getRepository('PackagistWebBundle:Package');
        $response = false;
        $em = $this->doctrine->getManager();
        // PackageRepository::packageExists() uses too much caching for us.
        $res = $em->createQuery("SELECT p.name FROM Packagist\WebBundle\Entity\Package p WHERE p.name = :name")
            ->setParameters(['name' => $packageName])
            ->getResult();
        if (empty($res)) {
            $output->write("adding $packageName");
            $package = new Package();
            $package->setRepository($url);
            $package->setName($packageName);
            $em->persist($package);
            $em->flush();
        }
        $releaseInfoFactory = new ReleaseInfoFactory();
        $releases = $releaseInfoFactory
            ->getReleaseInfo($packageName, [7, 8]);
        if (empty($releases)) {
            $output->write($err = "no valid releases for {$packageName}");
            throw new \Exception($err);
        }
        $package  = $packageRepository->getPackageByName($packageName);
        $loader   = new ValidatingArrayLoader(new ArrayLoader());
        try {
            $repository = new VcsRepository(
                array('url' => $package->getRepository()),
                $output,
                $config
            );
            $repository->setLoader($loader);
            $output->write("Updating $packageName");
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
            throw $e;
        }
    }
}
