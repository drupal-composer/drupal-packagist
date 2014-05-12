<?php

namespace Drupal\ParseComposer;

use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Repository\VcsRepository;
use Doctrine\ORM\EntityManager;
use Packagist\WebBundle\Entity\Package;

class UpdaterTest extends \PHPUnit_Framework_TestCase
{
    public function testDoesNotCrash()
    {
        $doctrine = $this->getMock('Symfony\Bridge\Doctrine\RegistryInterface');
        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
          ->disableOriginalConstructor()
          ->getMock();
        $doctrine->expects($this->any())
          ->method('getManager')
          ->will($this->returnValue($em));
        \date_default_timezone_set('UTC');
        $updater = new Updater($doctrine);
        $collection = new \Doctrine\Common\Collections\ArrayCollection();
        $package = new Package();
        $config = Factory::createConfig();
        $io = new BufferIO('');
        $io->loadConfiguration($config);
        $config = Factory::createConfig();
        $repository = new VcsRepository(['url' => 'http://git.drupal.org/project/views'], $io, $config);
        $updater->update($package, $repository);
        $repository = new VcsRepository(['url' => 'http://git.drupal.org/project/panopoly'], $io, $config);
        $updater->update($package, $repository);
    }
}
