<?php

namespace Drupal\ParseComposer;

class InfoFile
{
    private $name;
    private $info;

    /**
     * @param string $name machine name of Drupal project
     * @param string $info valid Drupal .info file contents
     */
    public function __construct($name, $info)
    {
        $this->name = $name;
        $this->info = \drupal_parse_info_format($info);
    }

    /**
     * @param string $dependency a valid .info dependencies value
     */
    public function constraint($dependency)
    {
        $matches = array();
        preg_match(
            '/([a-z_]*)\s*(\(([^\)]+)*\))*/',
            $dependency,
            $matches
        );
        list($all, $project, $v, $versionConstraints) = array_pad($matches, 4, '');
        $project = trim($project);
        if (empty($versionConstraints)) {
            return array('drupal/'.$project => Constraint::loose(new Version($this->info['core'][0])));
        }
        foreach (preg_split('/[, ]+/', $versionConstraints) as $versionConstraint) {
            preg_match(
                '/([><=]*)([0-9a-z\.\-]*)/',
                $versionConstraint,
                $matches
            );
            list($all, $symbols, $version) = $matches;
            $versionString = (string) new Version($version);
            $version = str_replace('unstable', 'patch', $versionString);
            $constraints[] = $symbols.$version;
        }
        return array('drupal/'.$project => implode(',', $constraints));
    }

    /**
     * @return array $info composer-compatible info for the info file
     */
    public function packageInfo()
    {
        $deps = isset($this->info['dependencies']) ? $this->info['dependencies'] : array();
        $deps = is_array($deps) ? $deps : array($deps);
        $info = array(
          'name' => 'drupal/'.$this->name,
          'description' => $this->info['description'],
          'require' => $this->constraint('drupal'),
        );
        foreach($deps as $dep) {
            $info['require'] += $this->constraint($dep);
        }
        return $info;
    }

    public function drupalInfo()
    {
        return $this->info;
    }
}
