<?php

/**
 * @file
 * Contains \Packagist\WebBundle\Command\DrupalOgCommitLogParserCommand.
 */

namespace DrupalPackagist\Bundle\Command;

use Doctrine\ORM\NoResultException;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Guzzle\Service\Client;
use Packagist\WebBundle\Package\Updater;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Repository\VcsRepository;
use Composer\IO\BufferIO;
use Composer\IO\ConsoleIO;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DomCrawler\Crawler;

class DrupalOrgModuleIndexParserCommand extends ContainerAwareCommand
{

    const VENDOR = 'drupal';

    protected function configure()
    {
        $this->setName('packagist:drupal_org_module_index_parser');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $packages = array();
        $client = new Client();

        $urls = array(
          'https://www.drupal.org/project/project_distribution/index?project-status=full&drupal_core=103', // 7.x
          'https://www.drupal.org/project/project_distribution/index?project-status=full&drupal_core=7234', // 8.x
          'https://www.drupal.org/project/project_module/index?project-status=full&drupal_core=103', // 7.x
          'https://www.drupal.org/project/project_module/index?project-status=full&drupal_core=7234', // 8.x
          'https://www.drupal.org/project/project_theme/index?project-status=full&drupal_core=103', // 7.x
          'https://www.drupal.org/project/project_theme/index?project-status=full&drupal_core=7234', // 8.x
        );

        foreach ($urls as $url) {
            $request = $client->get($url);
            $response = $request->send();

            $crawler = new Crawler((string) $response->getBody());
            $crawler->filter('.view-project-index .views-field-title a')
              ->each(function (Crawler $node, $i) use (&$packages) {
                  $name = $node->extract('href')[0];
                  $packages[$name] = str_replace('/project/', '', $name);
              });
        }

        $client = $this->getContainer()
          ->get('old_sound_rabbit_mq.add_packages_producer');
        foreach ($packages as $name) {
            $output->write('Queuing add job ' . self::VENDOR . '/' . $name,
              true);
            $client->publish(
              serialize(
                array(
                  'package_name' => self::VENDOR . '/' . $name,
                  'url' => 'http://git.drupal.org/project/' . $name . '.git'
                )
              )
            );
        }
    }

}
