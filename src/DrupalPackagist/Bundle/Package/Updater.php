<?php

/**
 * @file
 * Contains \DrupalPackagist\Bundle\Package\Updater.
 */

namespace DrupalPackagist\Bundle\Package;

use Composer\Config;
use Composer\IO\IOInterface;
use Packagist\WebBundle\Package\Updater as PackagistUpdater;
use Packagist\WebBundle\Entity\Package;
use Composer\Repository\RepositoryInterface;
use Drupal\ParseComposer\Repository;

class Updater extends PackagistUpdater
{

    /**
     * {@inheritdoc}
     */
    public function update(
      IOInterface $io,
      Config $config,
      Package $package,
      RepositoryInterface $repository,
      $flags = 0,
      \DateTime $start = null
    ) {
        $repository = Repository::create($repository);
        parent::update($io, $config, $package, $repository, $flags, $start);
    }
}
