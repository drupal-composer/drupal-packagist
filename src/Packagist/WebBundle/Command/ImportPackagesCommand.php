<?php

namespace Packagist\WebBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Packagist\WebBundle\Package\Updater;
use Packagist\WebBundle\Entity\Package;
use Composer\Repository\VcsRepository;
use Composer\Factory;
use Composer\Package\Loader\ValidatingArrayLoader;
use Composer\Package\Loader\ArrayLoader;
use Composer\IO\BufferIO;
use Composer\IO\ConsoleIO;
use Composer\Repository\InvalidRepositoryException;
use Composer\Repository\ComposerRepository;

class ImportPackagesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('packagist:import')
            ->setDefinition(array(
              new InputOption(
              'force',
              null,
              InputOption::VALUE_NONE,
              'Overwrite existing packages'
            ),
            new InputArgument(
              'packages.json',
              InputArgument::REQUIRED,
              'Path to packages.json to import'
            )
          ))
            ->setDescription('Imports packages from packages.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $verbose = $input->getOption('verbose');
        $force = $input->getOption('force');
        $packagesJson = $input->getArgument('packages.json');

        $doctrine = $this->getContainer()->get('doctrine');
        $router = $this->getContainer()->get('router');

        $io = $verbose
            ? new ConsoleIO(
                $input,
                $output,
                $this->getApplication()->getHelperSet()
            )
            : new BufferIO('');

        $repository = new ComposerRepository(
            array(
                'url' => $packagesJson
            ),
            $io,
            Factory::createConfig()
        );


        $composerPackages = function ($repository) {
            if ($repository->hasProviders()) {
                foreach($repository->getProviderNames() as $packageName) {
                    foreach($repository->findPackages($packageName) as $package) {
                        yield $package;
                    }
                }
            }
            else {
                foreach($repository->getPackages() as $package) {
                    yield $package;
                }
            }
        };
        $packagistPackages = array();
        $em = $doctrine->getManager();
        foreach ($composerPackages($repository) as $composerPackage) {
            // @todo move this into drupal/parse-composer
            $name = strtolower($composerPackage->getPrettyName());
            if (!isset($packagistPackages[$name])) {
                $io->write("Importing $name");
                $package = new Package();
                $package->setRepository($composerPackage->getSourceUrl());
                $package->setName($name);
                $packagistPackages[$name] = $package;
                $io->write("Importing $name");
                $em->persist($package);
            }
        }
        $em->flush();
    }
}
