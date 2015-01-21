<?php

/**
 * @file
 * Contains \DrupalPackagist\Bundle\Package\Updater.
 */

namespace DrupalPackagist\Bundle\Package;

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
      Package $package,
      RepositoryInterface $repository,
      $flags = 0,
      \DateTime $start = null
    ) {
        $repository = Repository::create($repository);
        parent::update($package, $repository, $flags, $start);
    }
}
