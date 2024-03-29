<?php

namespace Optimus\FineuploaderServer\Storage;

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\Upload\UploadApi;
use Optimus\FineuploaderServer\Config\Config;
use Optimus\FineuploaderServer\File\File;
use Optimus\FineuploaderServer\File\Edition;
use Optimus\FineuploaderServer\File\RootFile;
use Optimus\FineuploaderServer\Storage\UrlResolverTrait;

class CloudinaryStorage implements StorageInterface {

    use UrlResolverTrait;

    private $config;

    private $uploaderConfig;

    private $cloudinary;

    private $urlResolver;

    public function __construct(array $config, Config $uploaderConfig, $urlResolver)
    {
        $this->config = $this->mergeConfigWithDefault($config);
        $this->uploaderConfig = $uploaderConfig;
        $this->urlResolver = $urlResolver;

        Configuration::instance([
            'cloud' => [
                'cloud_name' => $config['cloud_name'],
                'api_key' => $config['api_key'],
                'api_secret' => $config['api_secret']
            ]
        ]);
    }

    public function store(File $file, $path)
    {
        $response = $this->upload($file->getPathname(), $path);

        // Resolves to upload path, e.g. users/1/adsadasd.jpg
        $file->setName(sprintf('%s.%s', $response['public_id'], $file->getExtension()));
        $file->setUrl($response['secure_url']);
        // Set the type here, otherwise getType will be called after the temp
        // file has been deleted.
        $file->setType($response['resource_type'] === 'image' ? 'image' : 'file');

        if (!isset($this->config['editions']) || !is_array($this->config['editions'])) {
            $this->config['editions'] = [];
        }

        $thumbnailsConfig = $this->uploaderConfig->get('thumbnails');
        $this->config['editions']['thumbnail'] = [
            'crop' => $thumbnailsConfig['crop'],
            'height' => $thumbnailsConfig['height'],
            'width' => $thumbnailsConfig['width']
        ];

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
        $file = new RootFile($filename);
        $public_id = str_replace('.'.$file->getExtension(), '', $filename);

        $api = new AdminApi();
        $response = $api->deleteAssets($public_id);

        return true;
    }

    public function get(RootFile $file)
    {
        try {
            $api = new AdminApi();
            $result = $api->asset(
                sprintf('%s/%s',
                $file->getUploaderPath(),
                $file->getBasename('.'.$file->getExtension()))
            );

            $file->setType($result['resource_type'] === 'image' ? 'image' : 'file');

            if ($result['resource_type'] === 'image') {
                $config = $this->uploaderConfig->get('thumbnails');

                $edition = new Edition("thumbnail", null, $file->getUploaderPath(), [
                    'type' => 'image',
                    'height' => $config['height'],
                    'width' => $config['width'],
                    'crop' => $config['crop'],
                    'cloudinary_response' => $result
                ], true);
                $edition->setUrl($this->resolveUrl($edition));

                $file->addEdition($edition);
            }

            return $file;
        } catch(Api\NotFound $e) {
            return [
                'error' => 'S0001' // session root file does not exist
            ];
        }
    }

    public function move($from, $to)
    {
        try {
            $uploader = new UploadApi();
            $response = $uploader->rename($from, $to);

            return $response;
        } catch(Cloudinary\Error $e) {
            return [
                'error' => 'M0001'
            ];
        }
    }

    private function upload($file, $path)
    {
        $uploader = new UploadApi();
        return $uploader->upload($file, [
            'folder' => $path,
            'eager' => array_values($this->config['eager']),
            'resource_type' => 'auto'
        ]);
    }

    private function mergeConfigWithDefault(array $config)
    {
        return array_merge([
            'eager' => []
        ], $config);
    }

}
