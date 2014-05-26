<?php

namespace Drupal\ParseComposer;

class Project
{

    public function __construct(
        $name,
        FileFinderInterface $finder,
        $core,
        array $releases = array()
    )
    {
        $this->name     = $name;
        $this->finder   = $finder;
        $this->releases = $releases;
        $this->core     = $core;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDrupalInformation()
    {
        $projectMap = $projectNames = $paths = $make = array();
        $paths = $this->finder->pathMatch(
            function($path) {
                $parts = explode('.', basename($path));
                return in_array(end($parts), ['info', 'make']);
            }
        );
        foreach ($paths as $path) {
            $parts = explode('.', $path);
            $projectName = @current(explode('.', end(explode('/', $path))));
            if (end($parts) === 'info' && !strpos($projectName, 'test')) {
                $projectMap[$projectName] = new InfoFile(
                    $projectName,
                    $this->finder->fileContents($path),
                    $this->core
                );
            }
            if (end($parts) === 'make' && empty(array_intersect($parts, ['dev', 'release', 'build']))) {
                $make[$projectName] = new Makefile(
                    $this->finder->fileContents($path)
                );
            }
        }
        if (empty($projectMap)) {
            return;
        }
        if ('drupal' == $this->name) {
            $projectMap['drupal'] = clone($projectMap['system']);
        }
        foreach ($projectMap as $name => $info) {
            $composerMap[$name] = $info->packageInfo();
            foreach ($make as $makefile) {
                foreach (($makefile->getMakeInfo('projects') ?: []) as $name => $project) {
                    $composerMap[$this->name]['require']['drupal/'.$name] = $makefile->getConstraint($name);
                }
            }
        }
        if (
            $releaseInfo = $this->getReleaseInfo(
                $this->core
            )
        ) {
            $composerMap[$this->name]['type'] = $releaseInfo->getProjectType();
            $composerMap[$this->name]['require']['composer/installers'] = '~1.0';
        }
        return $composerMap;
    }

    public function getReleaseInfo($core)
    {
        if (($core > 6) && ($this->name !== 'drupal')) {
            if (!isset($this->releases[$core])) {
                $this->releases[$core] = new ReleaseInfo($this->name, $core);
            }
            return $this->releases[$core];
        }
    }
}
