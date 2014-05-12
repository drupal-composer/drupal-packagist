<?php

namespace Drupal\ParseComposer;

use Composer\Repository\Vcs\GitDriver as BaseDriver;

class GitDriver extends BaseDriver
{

    /**
     * {@inheritDoc}
     */
    public function getComposerInformation($identifier)
    {
        $composer = array();
        try {
            $composer = parent::getComposerInformation($identifier);
        } catch (TransportException $e) {
            // There is not composer.json file in the root
        }

        if (NULL != ($drupalInformation = $this->getDrupalInformation($identifier))) {
            $topInformation = $drupalInformation[$this->drupalProjectName];
            $composer['require'] = isset($composer['require']) ? $composer['require'] : array();
            foreach ($drupalInformation as $name => $info) {
                $composer['require'] = array_merge($composer['require'], $info['require']);
            }
            foreach (array_keys($drupalInformation) as $name) {
                if (isset($composer['require']["drupal/$name"])) {
                    unset($composer['require']["drupal/$name"]);
                }
                if ($name != $this->drupalProjectName) {
                    $composer['replace']["drupal/$name"] = 'self.version';
                }
            }
            foreach (array('name', 'description') as $top) {
                $composer[$top] = isset($composer[$top]) ? $composer[$top] : $topInformation[$top];
            }
            unset($composer['require'][$composer['name']]);
        }
        return $composer;
    }

    /**
     * {@inheritDoc}
     */
    public function getBranches()
    {
        foreach (parent::getBranches() as $branch => $hash) {
            $branches[$this->drupalSemVer($branch)] = $hash;
        }
        return $branches;
    }

    /**
     * {@inheritDoc}
     */
    public function getTags()
    {
        foreach (parent::getTags() as $tag => $hash) {
            $tags[$this->drupalSemVer($tag)] = $hash;
        }
        return $tags;
    }

    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
        $this->drupalDistUrlPattern = 'http://ftp.drupal.org/files/projects/%s-%s.zip';
        parent::initialize();
    }

    private function drupalSemVer($version)
    {
        $parts = preg_split('/[.x-]+/', $version);
        $numbers = array_filter($parts, 'is_numeric');
        $extra = implode('', array_diff($parts, $numbers));
        return implode('.', $numbers) . (empty($extra) ? '' : "-$extra");
    }

    private function getDrupalInformation($identifier)
    {
        $projectMap = $projectNames = $paths = $make = array();
        $this->process->execute(
            sprintf('git ls-tree -r %s --name-only', $identifier),
            $out,
            $this->repoDir
        );
        foreach ($this->process->splitLines($out) as $path) {
            $parts = explode('.', $path);
            $projectName = @current(explode('.', end(explode('/', $path))));
            if (end($parts) === 'info' && !strpos($projectName, 'test')) {
                $projectMap[$projectName] = new InfoFile(
                    $projectName,
                    $this->fileContents($identifier, $path)
                );
            }
            if (end($parts) === 'make') {
                $make[$projectName] = new MakeFile(
                    $this->fileContents($identifier, $path)
                );
            }
        }
        if ('drupal' == $this->drupalProjectName) {
            $projectMap['drupal'] = clone($projectMap['system']);
        }
        foreach ($projectMap as $name => $info) {
            $composerMap[$name] = $info->packageInfo();
            foreach ($composerMap[$name]['require'] as $dep => $constraint) {
                $composerMap[$name]['require'][$dep] = $constraint;
            }
            foreach ($make as $makefile) {
                foreach ($makefile->getMakeInfo('projects') as $name => $project) {
                    $composerMap[$this->drupalProjectName]['require']['drupal/'.$name] = $makefile->getConstraint($name);
                }
            }
        }
        return $composerMap;
    }

    private function fileContents($identifier, $path)
    {
        $resource = sprintf("%s:%s", escapeshellarg($identifier), $path);
        $this->process->execute(
            "git show $resource",
            $out,
            $this->repoDir
        );
        return $out;
    }

    /**
     * {@inheritDoc}
     */
    public function getDist($identifier)
    {
        $distVersion = FALSE;
        foreach (array('tags', 'branches') as $refs) {
            $map = array_flip($this->$refs);
            if (!$distVersion) {
                $distVersion = isset($map[$identifier]) ? $map[$identifier] : FALSE;
            }
        }
        if ($distVersion) {
            return array(
                'type' => 'zip',
                'url' => sprintf($this->drupalDistUrlPattern, $this->drupalProjectName, $distVersion)
            );
        }
    }
}
