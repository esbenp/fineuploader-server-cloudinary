<?php

namespace Optimus\FineuploaderServer\Storage;

use Cloudinary;
use Cloudinary\Api;
use Cloudinary\Uploader;
use Optimus\FineuploaderServer\File\File;
use Optimus\FineuploaderServer\File\Edition;
use Optimus\FineuploaderServer\Storage\UrlResolverTrait;

class CloudinaryStorage implements StorageInterface {

    use UrlResolverTrait;

    private $config;

    private $cloudinary;

    private $urlResolver;

    public function __construct(array $config, $urlResolver)
    {
        $this->config = $this->mergeConfigWithDefault($config);
        $this->urlResolver = $urlResolver;

        Cloudinary::config([
            'cloud_name' => $config['cloud_name'],
            'api_key' => $config['api_key'],
            'api_secret' => $config['api_secret']
        ]);
    }

    public function store(File $file, $path)
    {
        $response = $this->upload($file->getPathname(), $path);

        $file->setName($response['public_id']);
        $file->setUrl($response['secure_url']);

        foreach($this->config['editions'] as $key => $edition) {
            $edition = new Edition($key, null, $path, [
                'type' => 'image',
                'height' => $edition['height'],
                'width' => $edition['width'],
                'crop' => $edition['crop'],
                'cloudinary_response' => $response
            ], false);

            $url = $this->resolveUrl($edition);

            $edition->setUrl($url);

            $file->addEdition($edition);
        }

        return $file;
    }

    // Filename should be equal the public id
    public function delete($filename)
    {
        $api = new Cloudinary\Api();
        $response = $api->delete_resources($filename)['deleted'][$filename];

        if ($response !== 'deleted') {
            http_response_code(404);
            exit;
        }

        return true;
    }

    public function __destruct()
    {
        Cloudinary::reset_config();
    }

    private function upload($file, $path)
    {
        return Uploader::upload($file, [
            'folder' => $path,
            'eager' => array_values($this->config['eager'])
        ]);
    }

    private function mergeConfigWithDefault(array $config)
    {
        return array_merge([
            'eager' => []
        ], $config);
    }

}
