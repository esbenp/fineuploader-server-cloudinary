<?php

namespace Optimus\FineuploaderServer\Http;

use Optimus\FineuploaderServer\File\File;
use Optimus\FineuploaderServer\Http\UrlResolverInterface;

class CloudinaryUrlResolver implements UrlResolverInterface {

    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function resolve(File $file)
    {
        $parametersArray = $this->generateParametersArray($file);
        $cloudinaryResponse = $file->getMeta('cloudinary_response');

        $parametersArray['secure'] = true;
        $parametersArray['version'] = $cloudinaryResponse['version'];
        if ($cloudinaryResponse['resource_type'] === 'image') {
            $parametersArray['format'] = $cloudinaryResponse['format'];
        }

        return cloudinary_url($cloudinaryResponse['public_id'], $parametersArray);
    }

    private function generateParametersArray(File $file)
    {
        $types = [
            'width',
            'height',
            'crop',
            'gravity'
        ];
        $return = [];

        foreach($types as $metaKey) {
            if ($file->hasMeta($metaKey)) {
                $return[$metaKey] = $file->getMeta($metaKey);
            }
        }

        return $return;
    }

}
