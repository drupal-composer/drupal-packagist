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

    public function __construct($doctrine, $router, $updater)
    {
        $this->doctrine = $doctrine;
        $this->router   = $router;
        $this->updater  = $updater;
    }

    public function execute(AMQPMessage $message)
    {
        $config = Factory::createConfig();
        $packageName = unserialize($message->body)['package_name'];
        $packageRepository = $this->doctrine
            ->getRepository('PackagistWebBundle:Package');
        if ($packageRepository->packageExists($packageName)) {
            $package = $packageRepository->getPackageByName($packageName);
            $cacheDir = $config->get('cache-repo-dir')
                .'/'.preg_replace('{[^a-z0-9.]}i', '-', $package->getRepository());
            if (file_exists($cacheDir)) {
                $loader = new ValidatingArrayLoader(new ArrayLoader());
                $output = new BufferIO('');
                $output->loadConfiguration($config);
                $updater = $this->updater;
                try {
                    $io = new BufferIO('');
                    $io->loadConfiguration($config);
                    $repository = new VcsRepository(
                        array('url' => $package->getRepository()),
                        $io,
                        $config
                    );
                    $repository->setLoader($loader);
                    echo "updating $packageName\n";
                    $updater->update(
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
                    if ($input->getOption('notify-failures')) {
                        if (!$this->container->get('packagist.package_manager')
                            ->notifyUpdateFailure($package, $e, $io->getOutput())
                        ) {
                            $output->write(
                                '<error>Failed to notify maintainers</error>'
                            );
                        }
                    }
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
                    return ConsumerInterface::MSG_REJECT;
                }
                $this->doctrine->getManager()->clear();
                return serialize(array(
                    'output' => $output->getOutput()
                ));
            }
            echo "nacking -- not on disk: $packageName\n";
        }
        else {
          echo "nacking -- does not exist: $packageName\n";
          return ConsumerInterface::MSG_REJECT;
        }
        return false;
    }
}
