<?php

namespace Drupal\ParseComposer;

use Packagist\WebBundle\Package\Updater as BaseUpdater;
use Packagist\WebBundle\Entity\Package;
use Composer\Repository\RepositoryInterface;

class Updater extends BaseUpdater
{

    /**
     * {@inheritdoc}
     */
    public function update(Package $package, RepositoryInterface $repository, $flags = 0, \DateTime $start = null)
    {
        $repository = Repository::create($repository);
        return parent::update($package, $repository, $flags, $start);
    }
}
