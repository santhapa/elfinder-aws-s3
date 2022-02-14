## elFinder Flysystem Driver
> This package is referenced from https://github.com/barryvdh/elfinder-flysystem-driver. Since it does not provides support for PHP8 and https://github.com/thephpleague/flysystem v3, modified it to work for same.

> This package adds a VolumeDriver for elFinder to use Flysystem as a root in your system. You need to have elFinder 2.1 installed.
> You can download the source or nightlies from https://github.com/Studio-42/elFinder.

Require this package in your composer.json and update composer.

    composer require santhapa/elfinder-aws-s3

This will require Flysystem and AwsS3 adapter is included along with this package. For other adapters to fit your purpose.
See https://github.com/thephpleague/flysystem for more information.

### Basic usage
Please refer `example/init.php` for connector configuration. It provides options for configuring Aws S3 as filesystem.

You can use any filesystem and configure according to your need.
