<?php

/*
 * This file is part of Satis.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Drupal\ParseComposer;

function includeIfExists($file)
{
    if (file_exists($file)) {
        return include $file;
    }
}

if (!includeIfExists(__DIR__.'/vendor/drupal/drupal/includes/common.inc')
    && !includeIfExists(__DIR__.'/../../drupal/drupal/includes/common.inc')
) {
    die('You must set up the project dependencies, run the following commands:'.PHP_EOL.
        'curl -s http://getcomposer.org/installer | php'.PHP_EOL.
        'php composer.phar install'.PHP_EOL);
}
