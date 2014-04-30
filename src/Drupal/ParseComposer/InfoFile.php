<?php

namespace Drupal\ParseComposer;

require_once __DIR__.'/common.inc';

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
            return array('drupal/'.$project => '*');
        }
        foreach (preg_split('/[, ]+/', $versionConstraints) as $versionConstraint) {
            preg_match(
                '/([><=]*)([0-9a-z\.\-]*)/',
                $versionConstraint,
                $matches
            );
            list($all, $symbols, $version) = $matches;
            $versionParts = preg_split('/[-\.x]+/', $version);
            $versionNumbers = array_filter($versionParts, 'is_numeric');
            $extra = array_diff($versionParts, $versionNumbers);
            if (count($versionNumbers) > 2 ) {
                array_shift($versionNumbers);
            }
            else {
                $versionNumbers = array_pad($versionNumbers, 2, 0);
            }
            $versionString = implode('.', array_merge($versionNumbers, $extra));
            $version = str_replace('unstable', 'patch', $versionString);
            $constraints[] = $symbols.$version.($extra ? '-'.$extra : '');
        }
        return array('drupal/'.$project => implode(',', $constraints));
    }

    /**
     * @return array $info composer-compatible info for the info file
     */
    public function packageInfo()
    {
        $deps = isset($this->info['dependencies']) ? $this->info['dependencies'] : array();
        $deps = is_array($deps) ? $deps : ($deps);
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
