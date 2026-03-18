<?php
// config/cloudinary.php
require_once BASE_PATH . '/vendor/autoload.php';

function uploadToCloudinary(string $tmpPath, string $folder): string {
    $cloudinary = new \Cloudinary\Cloudinary([
        'cloud' => [
            'cloud_name' => $_ENV['CLOUDINARY_CLOUD_NAME'],
            'api_key'    => $_ENV['CLOUDINARY_API_KEY'],
            'api_secret' => $_ENV['CLOUDINARY_API_SECRET'],
        ],
        'url' => ['secure' => true],
    ]);

    $result = $cloudinary->uploadApi()->upload($tmpPath, [
        'folder'         => 'homi/' . $folder,
        'resource_type'  => 'auto',
    ]);

    return $result['secure_url'];
}