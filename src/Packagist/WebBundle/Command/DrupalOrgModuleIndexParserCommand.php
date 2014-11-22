<?php

/**
 * @file
 * Contains \Packagist\WebBundle\Command\DrupalOgCommitLogParserCommand.
 */

namespace Packagist\WebBundle\Command;

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

class DrupalOrgModuleIndexParserCommand extends ContainerAwareCommand {

  const VENDOR = 'drupal';

  protected function configure() {
    $this->setName('packagist:drupal_org_module_index_parser');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $doctrine = $this->getContainer()->get('doctrine');
    /**
     * @var \Doctrine\ORM\EntityManager $em ;
     */
    $em = $doctrine->getEntityManager();
    $queue = $this->getContainer()
      ->get('old_sound_rabbit_mq.update_packages_rpc');
    $client = new Client();

    $request = $client->get('https://www.drupal.org/project/project_module/index?project-status=full&drupal_core=103');
    $response = $request->send();

    $packages = array();

    $crawler = new Crawler((string) $response->getBody());
    $crawler->filter('.view-project-index .views-field-title a')
      ->each(function (Crawler $node, $i) use (&$packages) {
        $packages[] = str_replace('/project/', '', $node->extract('href')[0]);
      });

    $client = $this->getContainer()
      ->get('old_sound_rabbit_mq.add_packages_rpc');

    foreach ($packages as $package) {
      $output->write('Queue ' . $package);
      $client->addRequest(
        serialize(
          array(
            'url' => "http://git.drupal.org/project/{$package}.git",
            'package_name' => static::VENDOR . '/' . $package
          )
        ),
        'add_packages',
        $package
      );
    }
  }

}
