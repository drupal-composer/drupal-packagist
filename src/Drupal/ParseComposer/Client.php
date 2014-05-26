<?php

namespace Drupal\ParseComposer;

use Guzzle\Http\Client as BaseClient;

class Client extends BaseClient
{
    public function get($uri = null, $headers = null, $options = array())
    {
        return parent::get($uri, $headers, $options)->send()->xml();
    }
}
