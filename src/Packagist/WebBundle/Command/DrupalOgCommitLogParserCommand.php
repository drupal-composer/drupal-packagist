<?php

/**
 * @file
 * Contains \Packagist\WebBundle\Command\DrupalOgCommitLogParserCommand.
 */

namespace Packagist\WebBundle\Command;

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

class DrupalOgCommitLogParserCommand extends ContainerAwareCommand
{
    const VENDOR = 'drupal';

    protected function configure()
    {
        $this->setName('packagist:drupal_org_parse_commitlog')
            ->setDescription('Updates packages with Drupal.org commit log information');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = new Client();
        $request = $client->get('https://www.drupal.org/commitlog?' . time());
        $response = $request->send();

        $crawler = new Crawler((string) $response->getBody());
        $packages = array();

        $crawler->filter('.commit-global')->each(function (Crawler $node, $i) use (&$packages) {
            $url = $node->filter('a')->extract(array('href'))[0];
            $commit = $url . ' ' . $node->filter(".commit-info a")->text();
            if (strpos($url, '/project/') === 0) {
                $packages[$commit] = substr($url, strlen('/project/'));
            }
        });

        $packages = array_reverse($packages);

        /**
         * @var $redis \Predis\Client
         */
        $redis = $this->getContainer()->get('snc_redis.default');
        $commitlog = $redis->lrange('commitlog', 0, 99);
        $commitlog = array();

        $diff = array_diff(array_keys($packages), $commitlog);

        if (empty($diff)) {
          return;
        }

        $redis->lpush('commitlog', $diff);
        $redis->ltrim('commitlog', 0, 99);

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
            $this->getApplication()->find('packagist:bulk_add')->run(
              new ArrayInput([
                'command' => 'packagist:bulk_add',
                'packages' => $tasks['add'],
                '--repo-pattern' => 'http://git.drupal.org/project/%2$s',
                '--vendor' => 'drupal'
              ]),
              $output
            );
        }

        if (isset($tasks['update'])) {
            $client = $this->getContainer()->get('old_sound_rabbit_mq.update_packages_rpc');
            $input->setInteractive(FALSE);
            foreach ($tasks['update'] as $name) {
                $name = self::VENDOR . '/' . $name;
                $output->write('Queuing job ' . $name, TRUE);
                $client->addRequest(
                  serialize(
                    array(
                      'flags' => Updater::UPDATE_EQUAL_REFS,
                      'package_name' => $name
                    )
                  ),
                  'update_packages',
                  $name
                );
            }
        }

    }
}
