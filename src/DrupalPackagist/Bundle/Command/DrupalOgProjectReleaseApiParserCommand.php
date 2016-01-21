<?php

/**
 * @file
 * Contains \Packagist\WebBundle\Command\DrupalOgProjectReleaseApiParserCommand.
 */

namespace DrupalPackagist\Bundle\Command;

use Doctrine\ORM\NoResultException;
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

class DrupalOgProjectReleaseApiParserCommand extends ContainerAwareCommand
{
    const VENDOR = 'drupal';

    protected $maxRecords = 100;

    protected $redisKey = 'drupal_org_parse_project_release';

    protected function configure()
    {
        $this->setName('packagist:drupal_org_parse_project_release')
            ->setDescription('Updates packages with Drupal.org project_release information');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = new Client();
        $request = $client->get('https://www.drupal.org/api-d7/node.json?type=project_release&field_release_build_type=static&sort=created&direction=DESC');
        $response = $request->send();
        $releases = $response->json();

        $packages = array();
        foreach ($releases['list'] as $release) {
            list($name, $version) = explode(' ', $release['title']);
            $packages[$release['title']] = $name;
        }

        $packages = array_reverse($packages);

        /**
         * @var $redis \Predis\Client
         */
        $redis = $this->getContainer()->get('snc_redis.default');
        $queue = $redis->lrange($this->redisKey, 0, $this->maxRecords);

        $diff = array_diff(array_keys($packages), $queue);

        if (empty($diff)) {
          return;
        }

        $redis->lpush($this->redisKey, $diff);
        $redis->ltrim($this->redisKey, 0, $this->maxRecords);

        $diff = array_values(array_unique(array_filter($diff)));

        $process = array();
        foreach ($diff as $key) {
          $process[] = $packages[$key];
        }

        $packages = array_values(array_unique(array_filter($process)));
        $tasks = array();

        /** @var $packageRepo \Packagist\WebBundle\Entity\PackageRepository */
        $packageRepo = $this->getContainer()->get('doctrine')->getRepository('PackagistWebBundle:Package');

        /** @var $package\Packagist\WebBundle\Entity\Package */
        foreach ($packages as $name) {
            try {
                $packageRepo->findOneByName(self::VENDOR . '/' . $name);
                $tasks['update'][] = $name;
            }
            catch (NoResultException $e) {
                $tasks['add'][] = $name;
            }
        }

        if (isset($tasks['add'])) {
            $client = $this->getContainer()->get('old_sound_rabbit_mq.add_packages_producer');
            foreach ($tasks['add'] as $name) {
                $output->write('Queuing add job ' . self::VENDOR . '/' . $name, TRUE);
                $client->publish(
                  serialize(
                    array(
                      'package_name' => self::VENDOR . '/' . $name,
                      'url' => 'https://git.drupal.org/project/' . $name  . '.git'
                    )
                  )
                );
            }
        }

        if (isset($tasks['update'])) {
            $client = $this->getContainer()->get('old_sound_rabbit_mq.update_packages_producer');
            $input->setInteractive(FALSE);
            foreach ($tasks['update'] as $name) {
                $name = self::VENDOR . '/' . $name;
                $output->write('Queuing update job ' . $name, TRUE);
                $client->publish(
                  serialize(
                    array(
                      'flags' => Updater::UPDATE_EQUAL_REFS,
                      'package_name' => $name
                    )
                  )
                );
            }
        }

    }
}
