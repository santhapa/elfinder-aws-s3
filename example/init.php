<?php

require '../vendor/autoload.php';

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;

$aws_config = [
    "key" => "access_key",
    "secret" => "secret_key",
    'version' => 'latest',
    'region' => 'us-east-1',
    "bucket" => "bucket_name",
    "endpoint" => "host_url",
];

$client = new S3Client([
    'version' => 'latest',
    'region' => 'us-east-1',
    'endpoint' => $aws_config['endpoint'],
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key' => $aws_config['key'],
        'secret' => $aws_config['secret'],
    ],
]);
$prefix = '';
$aws_url = "host_public_url_or_cdn_url";
$aws_url = $prefix ? $aws_url.'/'.$prefix : $aws_url;
$adapter = new AwsS3V3Adapter($client, $aws_config["bucket"], $prefix);
$filesystem = new Filesystem($adapter, ['url' => $aws_url] );

$opts = array(
    'roots' => [
        [
            'driver' => 'AwsS3Flysystem',
            'alias' => 'storage',//Change to anything you like
            'filesystem' => $filesystem,
            'URL' => $aws_url,
            'tmbURL' => 'self',
        ]

    ]
);

// run elFinder
$connector = new elFinderConnector(new elFinder($opts));
$connector->run();