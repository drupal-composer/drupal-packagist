<?php

namespace Packagist\WebBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Packagist\WebBundle\Entity\Package;
use Composer\Repository\VcsRepository;
use Composer\IO\BufferIO;
use Composer\IO\ConsoleIO;

class BulkAddPackagesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('packagist:bulk_add')
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
        $packageArgs = $input->getArgument('packages');
        $packages = [];
        foreach ($packageArgs as $key => $packageArg) {
            if (file_exists($packageArg)) {
                $packageArg = preg_split(
                    '/\s+/',
                    file_get_contents($packageArg)
                );
            }
            else {
                $packageArg = [$packageArg];
            }
            $packages = array_merge($packages, $packageArg);
        }
        $io = $verbose
            ? new ConsoleIO(
                $input,
                $output,
                $this->getApplication()->getHelperSet()
            )
            : new BufferIO('');
        $producer = $this->getContainer()
            ->get('old_sound_rabbit_mq.add_packages_producer');
        foreach ($packages as $name) {
            $fullName = $name;
            if ($vendor = $input->getOption('vendor')) {
                $fullName = "$vendor/$name";
            }
            $io->write('queuing '.$fullName);
            $producer->publish(
                serialize(
                    array(
                        'url' => sprintf(
                            $input->getOption('repo-pattern'),
                            $fullName,
                            $name,
                            $vendor
                        ),
                        'package_name' => $fullName
                    )
                )
            );
        }
    }
}
