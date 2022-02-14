<?php

namespace SanThapa\elFinderFlysystem;

use elFinder;
use Intervention\Image\ImageManager;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToRetrieveMetadata;

/**
 * Flysystem volume driver
 */
class VolumeDriver extends \elFinderVolumeDriver
{
    /**
     * Driver id
     * Must be started from letter and contains [a-z0-9]
     * Used as part of volume id
     *
     * @var string
     **/
    protected $driverId = 's3fls';

    /**
     * @var FilesystemOperator
     */
    protected $fs;

    /**
     *
     */
    public function __construct()
    {
        $opts = array(
            'filesystem' => null,
            'glideURL' => null,
            'glideKey' => null,
            'imageManager' => null,
            'cache' => false,   // 'session', 'memory' or false
            'fscache' => null,      // The Flysystem cache
            'checkSubfolders' => false, // Disable for performance
        );

        $this->options = array_merge($this->options, $opts);
    }

    /**
     * @param array $opts
     * @return bool
     * @throws \elFinderAbortException
     */
    public function mount(array $opts)
    {
        // If path is not set, use the root
        if (!isset($opts['path']) || $opts['path'] === '') {
            $opts['path'] = '/';
        }

        return parent::mount($opts);
    }

    /**
     * @return bool
     */
    protected function init()
    {
        $this->fs = $this->options['filesystem'];
        if (!($this->fs instanceof FilesystemOperator)) {
            return $this->setError('A filesystem instance is required');
        }

        $this->root = $this->options['path'];

        if ($this->options['imageManager']) {
            $this->imageManager = $this->options['imageManager'];
        } else {
            $this->imageManager = new ImageManager();
        }

        // enable command archive
        $this->options['useRemoteArchive'] = true;

        return true;
    }

    /**
     * @param $path
     * @return string
     */
    protected function _dirname($path)
    {
        return Util::dirname($path) ?: '/';
    }

    /**
     * @param $path
     * @return string
     */
    protected function _basename($path)
    {
        return basename($path);
    }

    /**
     * @param $dir
     * @param $name
     * @return string
     */
    protected function _joinPath($dir, $name)
    {
        return Util::normalizePath($dir . $this->separator . $name);
    }

    /**
     * @param $path
     * @return string
     */
    protected function _normpath($path)
    {
        return $path;
    }

    /**
     * @param $path
     * @return string
     */
    protected function _relpath($path)
    {
        return $path;
    }

    /**
     * @param $path
     * @return string
     */
    protected function _abspath($path)
    {
        return $path;
    }

    /**
     * @param $path
     * @return string
     */
    protected function _path($path)
    {
        return $this->rootName . $this->separator . $path;
    }

    /**
     * @param $path
     * @param $parent
     * @return bool
     */
    protected function _inpath($path, $parent)
    {
        return $path == $parent || strpos($path, $parent . '/') === 0;
    }

    /**
     * @param $path
     * @return array
     * @throws \League\Flysystem\FilesystemException
     */
    protected function _stat($path)
    {
        $stat = array(
            'size' => 0,
            'ts' => time(),
            'read' => true,
            'write' => true,
            'locked' => false,
            'hidden' => false,
            'mime' => 'directory',
        );

        // If root, just return from above
        if ($this->root == $path) {
            $stat['name'] = $this->root;
            return $stat;
        }

        // If not exists, return empty
        if (!$this->fs->has($path)) {

            // Check if the parent doesn't have this path
            if ($this->_dirExists($path)) {
                return $stat;
            }

            // Neither a file or directory exist, return empty
            return array();
        }

        $stat['name'] = basename($path);

        // Get timestamp/size if available
        try {
            $stat['ts'] = $this->fs->lastModified($path);
        } catch (UnableToRetrieveMetadata $e) {
            $stat['ts'] = '';
        }

        try {
            $stat['size'] = $this->fs->fileSize($path);
        }  catch (UnableToRetrieveMetadata $e) {
            $stat['size'] = '';
        }

        // Check if file, if so, check mimetype when available
        if ($this->fs->has($path)) {
            try {
                $stat['mime'] = $this->fs->mimeType($path);
            } catch (UnableToRetrieveMetadata $e) {
                $stat['mime'] = 'directory';
            }
        }

        return $stat;
    }

    /**
     * @param $path
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    protected function _subdirs($path)
    {
        $contents = $this->fs->listContents($path)->filter(fn (StorageAttributes $attributes) => $attributes->isDir());

        return $contents !== null;
    }

    /**
     * @param $path
     * @param $mime
     * @return false|mixed|string
     * @throws \ImagickException
     * @throws \elFinderAbortException
     */
    protected function _dimensions($path, $mime)
    {
        $ret = false;
        if ($imgsize = $this->getImageSize($path, $mime)) {
            $ret = $imgsize['dimensions'];
        }
        return $ret;
    }

    /**
     * @param $path
     * @return array
     * @throws \League\Flysystem\FilesystemException
     */
    protected function _scandir($path)
    {
        $paths = array();
        foreach ($this->fs->listContents($path, false) as $object) {
            if ($object) {
                $paths[] = $object['path'];
            }
        }
        return $paths;
    }

    /**
     * @param $path
     * @param $mode
     * @return false|resource
     * @throws \League\Flysystem\FilesystemException
     */
    protected function _fopen($path, $mode = "rb")
    {
        return $this->fs->readStream($path);
    }

    /**
     * @param $fp
     * @param $path
     * @return bool
     */
    protected function _fclose($fp, $path = '')
    {
        return @fclose($fp);
    }

    /**
     * @param $path
     * @param $name
     * @return bool|string
     * @throws \League\Flysystem\FilesystemException
     */
    protected function _mkdir($path, $name)
    {
        $path = $this->_joinPath($path, $name);

        if ($this->fs->createDirectory($path) === false) {
            return false;
        }

        return $path;
    }

    /**
     * @param $path
     * @param $name
     * @return bool|string
     * @throws \League\Flysystem\FilesystemException
     */
    protected function _mkfile($path, $name)
    {
        $path = $this->_joinPath($path, $name);

        if ($this->fs->write($path, '') === false) {
            return false;
        }

        return $path;
    }

    /**
     * @param $source
     * @param $targetDir
     * @param $name
     * @return false
     */
    protected function _symlink($source, $targetDir, $name)
    {
        return false;
    }

    /**
     * @param $source
     * @param $targetDir
     * @param $name
     * @return bool|string
     * @throws \League\Flysystem\FilesystemException
     */
    protected function _copy($source, $targetDir, $name)
    {
        $path = $this->_joinPath($targetDir, $name);

        if ($this->fs->copy($source, $path) === false) {
            return false;
        }

        return $path;
    }

    /**
     * @param $source
     * @param $targetDir
     * @param $name
     * @return bool|string
     * @throws \League\Flysystem\FilesystemException
     */
    protected function _move($source, $targetDir, $name)
    {
        $path = $this->_joinPath($targetDir, $name);

        if ($this->fs->move($source, $path) === false) {
            return false;
        }

        return $path;
    }

    /**
     * @param $path
     * @return bool|void
     * @throws \League\Flysystem\FilesystemException
     */
    protected function _unlink($path)
    {
        $this->fs->delete($path);
    }

    /**
     * @param $path
     * @return bool|void
     * @throws \League\Flysystem\FilesystemException
     */
    protected function _rmdir($path)
    {
        $this->fs->deleteDirectory($path);
    }

    /**
     * @param $fp
     * @param $dir
     * @param $name
     * @param $stat
     * @return bool|string
     * @throws \League\Flysystem\FilesystemException
     */
    protected function _save($fp, $dir, $name, $stat)
    {
        $path = $this->_joinPath($dir, $name);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $config = [];
        if (isset(self::$mimetypes[$ext])) {
            $config['mimetype'] = self::$mimetypes[$ext];
        }

        if (isset($this->options['visibility'])) {
            $config['visibility'] = $this->options['visibility'];
        }

        if ($this->fs->writeStream($path, $fp, $config) === false) {
            return false;
        }

        return $path;
    }

    /**
     * @param $path
     * @return false|string
     * @throws \League\Flysystem\FilesystemException
     */
    protected function _getContents($path)
    {
        return $this->fs->read($path);
    }

    /**
     * @param $path
     * @param $content
     * @return bool|void
     * @throws \League\Flysystem\FilesystemException
     */
    protected function _filePutContents($path, $content)
    {
        $this->fs->write($path, $content);
    }

    /**
     * @param $path
     * @param $arc
     * @return false
     */
    protected function _extract($path, $arc)
    {
        return false;
    }

    /**
     * @param $dir
     * @param $files
     * @param $name
     * @param $arc
     * @return false
     */
    protected function _archive($dir, $files, $name, $arc)
    {
        return false;
    }

    /**
     * @return void
     */
    protected function _checkArchivers()
    {
        return;
    }

    /**
     * @param $path
     * @param $mode
     * @return false
     */
    protected function _chmod($path, $mode)
    {
        return false;
    }

    /**
     * @param $hash
     * @param $width
     * @param $height
     * @param $x
     * @param $y
     * @param $mode
     * @param $bg
     * @param $degree
     * @param $jpgQuality
     * @return array|bool|bool[]|string
     * @throws \League\Flysystem\FilesystemException
     * @throws \elFinderAbortException
     */
    public function resize($hash, $width, $height, $x, $y, $mode = 'resize', $bg = '', $degree = 0, $jpgQuality = null)
    {
        if ($this->commandDisabled('resize')) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }

        if (($file = $this->file($hash)) == false) {
            return $this->setError(elFinder::ERROR_FILE_NOT_FOUND);
        }

        if (!$file['write'] || !$file['read']) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }

        $path = $this->decode($hash);
        if (!$this->canResize($path, $file)) {
            return $this->setError(elFinder::ERROR_UNSUPPORT_TYPE);
        }

        if (!$image = $this->imageManager->make($this->_getContents($path))) {
            return false;
        }

        switch ($mode) {
            case 'propresize':
                $image->resize($width, $height, function ($constraint) {
                    $constraint->aspectRatio();
                });
                break;

            case 'crop':
                $image->crop($width, $height, $x, $y);
                break;

            case 'fitsquare':
                $image->fit($width, $height, null, 'center');
                break;

            case 'rotate':
                $image->rotate($degree);
                break;

            default:
                $image->resize($width, $height);
                break;
        }

        if ($jpgQuality && $image->mime() === 'image/jpeg') {
            $result = (string)$image->encode('jpg', $jpgQuality);
        } else {
            $result = (string)$image->encode();
        }
        $this->_filePutContents($path, $result);
        if ($result ) {
            $this->rmTmb($file);
            $this->clearstatcache();
            $stat = $this->stat($path);
            $stat['width'] = $image->width();
            $stat['height'] = $image->height();
            return $stat;
        }

        return false;
    }

    /**
     * @param $path
     * @param $mime
     * @return array|false
     * @throws \League\Flysystem\FilesystemException
     */
    public function getImageSize($path, $mime = '')
    {
        $size = false;
        if ($mime === '' || strtolower(substr($mime, 0, 5)) === 'image') {
            if ($data = $this->_getContents($path)) {
                if ($size = @getimagesizefromstring($data)) {
                    $size['dimensions'] = $size[0] . 'x' . $size[1];
                }
            }
        }
        return $size;
    }

    /**
     * @param $path
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    protected function _dirExists($path)
    {
        $dir = $this->_dirname($path);
        $basename = basename($path);

        foreach ($this->fs->listContents($dir) as $meta) {
            if ($meta && $meta->type() !== 'file' && $meta->path() === $basename) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    protected function getIcon()
    {
        $icon = 'volume_icon_local.png';

        $parentUrl = defined('ELFINDER_IMG_PARENT_URL') ? (rtrim(ELFINDER_IMG_PARENT_URL, '/') . '/') : '';
        return $parentUrl . 'img/' . $icon;
    }
}