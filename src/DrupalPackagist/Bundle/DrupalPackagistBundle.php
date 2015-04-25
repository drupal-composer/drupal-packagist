<?php

namespace DrupalPackagist\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class DrupalPackagistBundle extends Bundle
{
    public function getParent()
    {
        return 'PackagistWebBundle';
    }
}
