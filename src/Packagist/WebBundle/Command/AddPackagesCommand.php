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

class AddPackagesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('packagist:add')
            ->setDefinition(array(
                new InputOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Overwrite existing packages'
            ),
            new InputOption(
                'vendor',
                null,
                InputOption::VALUE_OPTIONAL,
                'default vendor name'
            ),
            new InputOption(
                'repo-pattern',
                null,
                InputOption::VALUE_OPTIONAL,
                'pattern for repo url',
                'https://github.com/%s'
            ),
            new InputArgument(
                'packages',
                InputArgument::REQUIRED,
                'list of packages to add'
            )
        ))
            ->setDescription('Imports packages from packages.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $verbose = $input->getOption('verbose');
        $force = $input->getOption('force');
        $packages = $input->getArgument('packages');
        $packages = is_array($packages) ? $packages : array($packages);

        $doctrine = $this->getContainer()->get('doctrine');
        $router = $this->getContainer()->get('router');

        $io = $verbose
            ? new ConsoleIO(
                $input,
                $output,
                $this->getApplication()->getHelperSet()
            )
            : new BufferIO('');

        $em = $doctrine->getManager();
        $packagistPackages = array();
        foreach ($packages as $key => $name) {
            $fullName = $name;
            if ($vendor = $input->getOption('vendor')) {
                $fullName = "$vendor/$name";
            }
            if (!isset($packagistPackages[$fullName])) {
                $package = new Package();
                $package->setRepository(
                    sprintf($input->getOption('repo-pattern'), $fullName, $name, $vendor)
                );
                $package->setName($fullName);
                $packagistPackages[$fullName] = $package;
                $em->persist($package);
            }
        }
        $em->flush();
    }
}
