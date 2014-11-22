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

class UpdateGitRepositoryUrlCommand extends ContainerAwareCommand
{
    const VENDOR = 'drupal';

    protected function configure()
    {
        $this->setName('packagist:drupal_org_update_repository_url')
          ->setDefinition(array(
            new InputArgument('package', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Package name to update')
          ));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $doctrine = $this->getContainer()->get('doctrine');
        /**
         * @var \Doctrine\ORM\EntityManager $em;
         */
        $em = $doctrine->getEntityManager();
        $queue = $this->getContainer()->get('old_sound_rabbit_mq.update_packages_rpc');
        $client = new Client();

        $verbose = $input->getOption('verbose');

        $packages = array_filter($input->getArgument('package'));
        if (!empty($packages)) {
          $names = $packages;
          $packages = array();
          $repository = $em->getRepository('Packagist\WebBundle\Entity\Package');
          foreach ($names as $name) {
            $packages[] = $repository->findOneBy(array('name' => $name));
          }
        }
        else {
          // $query = $em->createQuery("SELECT p FROM Packagist\WebBundle\Entity\Package p LEFT JOIN p.versions v WHERE p.type IS NULL AND v.id IS NULL ORDER BY p.updatedAt ASC");
          $query = $em->createQuery("SELECT p FROM Packagist\WebBundle\Entity\Package p WHERE p.type IS NULL ORDER BY p.updatedAt ASC");
          $packages = $query->getResult();
        }

        /**
         * @var \Packagist\WebBundle\Entity\Package[] $packages
         */
        foreach ($packages as $package) {
          $output->write('Crawl drupal.org/project/' . $package->getName(), TRUE);
          $content = NULL;

          try {
            $request = $client->get('https://www.drupal.org/project/' . $package->getPackageName());
            $response = $request->send();
            $content = (string) $response->getBody();
          }
          catch (ClientErrorResponseException $e) {
            $output->write($e->getMessage());
            continue;
          }

          $crawler = new Crawler($content);
          $result = $crawler->filter('#block-drupalorg-project-development a')
            ->reduce(function (Crawler $node, $i) {
              return $node->text() == 'Repository viewer';
            })
            ->attr('href');

          if (!empty($result)) {
            $package->setRepository(str_replace('drupalcode.org', 'git.drupal.org', $result));
            $em->persist($package);
            $em->flush();

            // Push to update queue.
            $output->write('Queuing job ' . $package->getName(), TRUE);
            $queue->addRequest(
              serialize(
                array(
                  'flags' => Updater::UPDATE_EQUAL_REFS,
                  'package_name' => $package->getName()
                )
              ),
              'update_packages',
              $package->getName()
            );
          }
          else {
            $output->write('Git Repo url for ' . $package->getName() . ' not found.');
            $package->setCrawledAt(new \DateTime());
            $em->persist($package);
            $em->flush();
          }
        }
    }
}
