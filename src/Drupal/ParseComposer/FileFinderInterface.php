<?php

namespace Drupal\ParseComposer;

interface FileFinderInterface
{
    function pathMatch($pattern);
    function fileContents($pattern);
}
