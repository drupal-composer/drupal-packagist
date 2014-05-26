<?php

namespace Drupal\ParseComposer;

class ReleaseInfo
{
    private $releaseUrl = 'http://updates.drupal.org/release-history';

    public function __construct($projectName, $version, Client $client = null)
    {
        $this->client = $client ?: new Client();
        $this->load($projectName, $version);
    }

    public function load($projectName, $version)
    {
        $this->xml = $this->client->get(
            sprintf(
                '%s/%s/%d.x',
                $this->releaseUrl,
                $projectName,
                $version
            )
        );
    }

    public function getProjectType()
    {
        $projectTypes = array(
            'profile' => 'Distributions',
            'profile-legacy' => 'Installation profiles',
            'module' => 'Modules',
            'theme' => 'Themes'
        );
        $typesXpath = '/project/terms/term[name="Projects"]';
        $type = 'module';
        if ($types = $this->xml->xpath($typesXpath)) {
            $type = array_search($types[0]->value, $projectTypes);
            $type = ($type == 'profile-legacy') ? 'profile' : $type;
        }

        return "drupal-$type";
    }
}
