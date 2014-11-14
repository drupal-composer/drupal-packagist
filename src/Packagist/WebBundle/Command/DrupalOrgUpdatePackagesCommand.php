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
use FastFeed\Factory;
use Symfony\Component\Console\Input\ArrayInput;

class DrupalOrgUpdatePackagesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('packagist:drupal_org_update')
            ->setDescription('Updates packages with Drupal.org rss information');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $parser = Factory::create();
        $parser->addFeed(
            'drupalOrg7x',
            'https://www.drupal.org/taxonomy/term/103/feed'
        );
        foreach ($parser->fetch('drupalOrg7x') as $item) {
            $update[current(explode(' ', $item->getName()))] = true;
        }
        $this->getApplication()->find('packagist:upsert')->run(
            new ArrayInput([
                'command' => 'packagist:upsert',
                'packages' => array_keys($update),
                '--repo-pattern' => 'http://git.drupal.org/project/%2$s'
            ]),
            $output
        );
    }
}
