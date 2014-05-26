<?php

namespace Drupal\ParseComposer;

class Makefile
{

    public function __construct($data)
    {
        $this->makeInfo = \drupal_parse_info_format($data);
    }

    public function getMakeInfo($path = array())
    {
        $path = is_array($path) ? $path : func_get_args();
        $info = $this->makeInfo;
        foreach ($path as $key) {
            if (isset($info[$key])) {
                $info = $info[$key];
            }
            else {
                return false;
            }
        }
        return $info;
    }

    public function getVersion($project)
    {
        return $this->getVersionFromPath(
            array('projects', $project, 'version')
        );
    }

    public function getVersionFromTag($project)
    {
        return $this->getVersionFromPath(
            array('projects', $project, 'download', 'tag')
        );
    }

    public function getVersionFromBranch($project)
    {
        if ($branch = $this->getMakeInfo(
            array('projects', $project, 'download', 'branch')
        )) {
            return "dev-$branch";
        }
        return false;
    }

    public function coreConstraint()
    {
        return Constraint::loose(new Version($this->makeInfo['core'][0]));
    }

    public function getConstraint($project)
    {
        switch (true) {
        case ($constraint = $this->getVersion($project)):
        case ($constraint = $this->getVersionFromTag($project)):
        case ($constraint = $this->getVersionFromBranch($project)):
        case ($constraint = $this->coreConstraint()):
        default:
            return $constraint;
        }
    }

    private function getVersionFromPath(array $path)
    {
        if ($versionString = $this->getMakeInfo($path)) {
            return $this->makeVersion($versionString, $path[1]);
        }
        return false;
    }

    private function makeVersion($versionString, $name = null)
    {
        $version = new Version($this->makeInfo['core'][0], ($name == 'drupal'));
        $version->parse($versionString);
        return (string) $version;
    }
}
