<?php

namespace Drupal\ParseComposer;

class Constraint
{

    public function __construct(Version $version)
    {
        $this->version = $version;
    }

    public function getLoose()
    {
        return "{$this->version->getCore()}.*";
    }

    public static function loose(Version $version)
    {
        $constraint = new static($version);
        return $constraint->getLoose();
    }
}
