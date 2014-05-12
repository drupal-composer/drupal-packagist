<?php

namespace Drupal\ParseComposer;

class Version
{

    private $core;
    private $major = 0;
    private $minor = 0;
    private $extra;

    public function __construct($version)
    {
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
        case 3:
            list($this->core, $version, $this->extra) = $parts;
            break;
        case 2:
            list($version, $this->extra) = $parts;
            break;
        case 1:
            list($version) = $parts;
            break;
        }
        list($this->major, $this->minor) = explode('.', $version);
        if ($this->minor === 'x') {
            $this->extra = 'dev';
        }
    }
}
