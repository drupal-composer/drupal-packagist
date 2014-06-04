<?php
namespace Packagist\WebBundle\Package;

use Composer\Repository\VcsRepository;
use Composer\Factory;
use Composer\Package\Loader\ValidatingArrayLoader;
use Composer\Package\Loader\ArrayLoader;
use Composer\IO\BufferIO;
use PhpAmqpLib\Message\AMQPMessage;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;

class BackgroundUpdater implements ConsumerInterface {

    private $container;
    private $doctrine;
    private $router;

    public function __construct($container)
    {
        $this->container = $container;
        $this->doctrine = $container->get('doctrine');
        $this->router = $container->get('router');
    }

    public function execute(AMQPMessage $message)
    {
        $parameters = unserialize($message->body);
        $loader = new ValidatingArrayLoader(new ArrayLoader());
        $config = Factory::createConfig();
        $packages = $this->doctrine
            ->getRepository('PackagistWebBundle:Package')
            ->getPackagesWithVersions($parameters['package_ids']);
        $updater = $this->container->get('packagist.package_updater');
	$output = new BufferIO('');
	$output->loadConfiguration($config);
        $start = new \DateTime();
        foreach ($packages as $package) {
            try {
                $io = new BufferIO('');
                $io->loadConfiguration($config);
                $repository = new VcsRepository(
                    array('url' => $package->getRepository()),
                    $io,
                    $config
                );
                $repository->setLoader($loader);
                $updater->update(
                    $package,
                    $repository,
                    $parameters['flags'],
                    $start
                );
            } catch (InvalidRepositoryException $e) {
                $output->write('<error>Broken repository in '.$this->router->generate('view_package', array('name' => $package->getName()), true).': '.$e->getMessage().'</error>');
                if ($input->getOption('notify-failures')) {
                    if (!$this->container->get('packagist.package_manager')->notifyUpdateFailure($package, $e, $io->getOutput())) {
                        $output->write('<error>Failed to notify maintainers</error>');
                    }
                }
            } catch (\Exception $e) {
                $output->write('<error>Error updating '.$this->router->generate('view_package', array('name' => $package->getName()), true).' ['.get_class($e).']: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine().'</error>');
            }
            $this->doctrine->getManager()->clear();
        }
        unset($packages);
        return serialize(array(
            'output' => $output->getOutput()
        ));
    }
}
