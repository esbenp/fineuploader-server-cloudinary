<?php

namespace Optimus\FineuploaderServer\Storage;

use SplFileInfo;
use Cloudinary;
use Cloudinary\Uploader;

class CloudinaryStorage implements StorageInterface {

    private $config;

    private $cloudinary;

    public function __construct(array $config)
    {
        $this->config = $config;

        Cloudinary::config([
            'cloud_name' => $config['cloud_name'],
            'api_key' => $config['api_key'],
            'api_secret' => $config['api_secret']
        ]);
    }

    public function store(SplFileInfo $file, $path)
    {
        $response = Uploader::upload($file->getPathname(), [
            'folder' => $path
        ]);

        return $response['secure_url'];
    }

    public function __destruct()
    {
        Cloudinary::reset_config();
    }

}
