<?php
namespace Packagist\Package;

use Composer\Repository\VcsRepository;
use Composer\Factory;
use Composer\Package\Loader\ValidatingArrayLoader;
use Composer\Package\Loader\ArrayLoader;
use Composer\IO\BufferIO;
use PhpAmqpLib\Message\AMQPMessage;

class BackgroundUpdater implements ConsumerInterface {

    private $container;
    private $doctrine;
    private $router;

    public function __construct($container)
    {
        $this->container = $container;
        $this->doctrine = $this->getContainer()->get('doctrine');
        $this->router = $this->getContainer()->get('router');
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
                $output->writeln('<error>Broken repository in '.$this->router->generate('view_package', array('name' => $package->getName()), true).': '.$e->getMessage().'</error>');
                if ($input->getOption('notify-failures')) {
                    if (!$this->container->get('packagist.package_manager')->notifyUpdateFailure($package, $e, $io->getOutput())) {
                        $output->writeln('<error>Failed to notify maintainers</error>');
                    }
                }
            } catch (\Exception $e) {
                $output->writeln('<error>Error updating '.$this->router->generate('view_package', array('name' => $package->getName()), true).' ['.get_class($e).']: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine().'</error>');
            }
            $this->doctrine->getManager()->clear();
        }
        return serialize(array(
            'output' => $output->getOutput()
        ));
    }
}
