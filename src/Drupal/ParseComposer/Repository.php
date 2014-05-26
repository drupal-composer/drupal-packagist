<?php

namespace Drupal\ParseComposer;

use Composer\Repository\VcsRepository;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Config;

class Repository extends VcsRepository
{
    public function __construct(array $repoConfig, IOInterface $io, Config $config, EventDispatcher $dispatcher = null, array $drivers = null)
    {
        $drivers = array('git' => 'Drupal\ParseComposer\GitDriver');
        $repoConfig['type'] = 'git';
        parent::__construct($repoConfig, $io, $config, $dispatcher, $drivers);
        $parts = preg_split('{[/:]}', $this->url);
        $last = end($parts);
        $this->repoConfig['drupalProjectName'] = current(explode('.', $last));
    }

    public static function create(VcsRepository $repository)
    {
        return new static(
            $repository->repoConfig,
            $repository->io,
            $repository->config
        );
    }

    public function hadInvalidBranches()
    {
        return FALSE;
    }
}
