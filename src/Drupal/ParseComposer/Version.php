<?php

namespace Drupal\ParseComposer;

class Version
{

    private $core;
    private $major = 0;
    private $minor = 0;
    private $extra;

    public function __construct($version, $isCore = false)
    {
        $this->isCore = $isCore;
        if (strlen($version) === 1) {
            $this->core = (int) $version;
        }
        else {
            $this->parse($version);
        }
    }

    public function __toString()
    {
        return $this->getSemver();
    }

    public static function valid($version)
    {
        return !!preg_match(
            '/^\d+\.[0-9x]+(-\d+\.[0-9x]+)*(-[a-z]+\d*)*$/',
            $version
        );
    }

    public function getCore()
    {
        return $this->core;
    }

    public function getSemver()
    {
        return sprintf('%d.%d.%s', $this->core, $this->major, $this->minor)
            . ($this->extra ? "-{$this->extra}" : '');
    }

    public function parse($versionString)
    {
        $parts = explode('-', $versionString);
        switch (count($parts)) {
        case 1:
            list($version) = $parts;
            break;
        case 2:
            if ($this->core || $this->isCore) {
                list($version, $extra) = $parts;
            }
            else {
                list($this->core, $version) = $parts;
            }
            break;
        case 3:
        default:
            list($this->core, $version, $this->extra) = $parts;
        }
        if ($this->isCore) {
            list($this->core, $this->major) = explode('.', $version);
        }
        else {
            list($this->major, $this->minor) = explode('.', $version);
        }
        if ($this->minor === 'x') {
            $this->extra = 'dev';
        }
        $this->core = intval($this->core);
    }
}
