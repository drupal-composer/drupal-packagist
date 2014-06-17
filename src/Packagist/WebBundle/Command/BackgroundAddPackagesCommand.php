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

class BackgroundAddPackagesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('packagist:bg_add')
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
                    InputArgument::REQUIRED|InputArgument::IS_ARRAY,
                    'list of packages to add'
                )
            ))->setDescription('Imports packages from packages.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $verbose = $input->getOption('verbose');
        $force = $input->getOption('force');
        $packages = $input->getArgument('packages');
        $packages = is_array($packages) ? $packages : array($packages);
        $io = $verbose
            ? new ConsoleIO(
                $input,
                $output,
                $this->getApplication()->getHelperSet()
            )
            : new BufferIO('');
        $client = $this->getContainer()
            ->get('old_sound_rabbit_mq.add_packages_rpc');
        foreach ($packages as $key => $name) {
            $fullName = $name;
            if ($vendor = $input->getOption('vendor')) {
                $fullName = "$vendor/$name";
            }
            $io->write('queuing '.$fullName);
            $client->addRequest(
                serialize(
                    array(
                        'url' => sprintf(
                            $input->getOption('repo-pattern'),
                            $fullName,
                            $name,
                            $vendor
                        ),
                        'package_name' => $name
                    )
                ),
                'add_packages',
                $name
            );
        }
        $io->write('waiting...');
        foreach ($client->getReplies() as $result) {
            $output->write(unserialize($result)['output']);
        }
    }
}
