<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Model\Feed;

use BerryPath\ProductFeed\Model\Config\Source\OutputFormat;
use BerryPath\ProductFeed\Model\Profile;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class FileStorage
{
    private const BASE_PATH = 'berrypath/product-feed';

    private WriteInterface $mediaDirectory;

    public function __construct(
        Filesystem $filesystem,
        private readonly Config $feedConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    public function save(Profile $profile, string $content): void
    {
        $this->mediaDirectory->create(self::BASE_PATH);
        $this->mediaDirectory->writeFile($this->getPath($profile), $content);
    }

    public function exists(Profile $profile): bool
    {
        return $this->mediaDirectory->isExist($this->getPath($profile));
    }

    public function read(Profile $profile): string
    {
        $content = $this->mediaDirectory->readFile($this->getPath($profile));

        return is_string($content) ? $content : '';
    }

    public function delete(Profile $profile): void
    {
        foreach ([OutputFormat::XML, OutputFormat::CSV, OutputFormat::JSON] as $format) {
            $path = $this->getPathForFormat((int)$profile->getId(), $format);
            if ($this->mediaDirectory->isExist($path)) {
                $this->mediaDirectory->delete($path);
            }
        }
    }

    public function getAbsolutePath(Profile $profile): string
    {
        return $this->mediaDirectory->getAbsolutePath($this->getPath($profile));
    }

    public function getFileName(Profile $profile): string
    {
        return basename($this->getPath($profile));
    }

    public function getUrl(Profile $profile): string
    {
        return rtrim(
            $this->storeManager->getStore($profile->getStoreId())->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        ) . '/' . $this->getPath($profile);
    }

    private function getPath(Profile $profile): string
    {
        return $this->getPathForFormat(
            (int)$profile->getId(),
            $this->feedConfig->getOutputFormat($profile->getStoreId(), $profile)
        );
    }

    private function getPathForFormat(int $profileId, string $format): string
    {
        return sprintf('%s/feed_%d.%s', self::BASE_PATH, $profileId, $format);
    }
}
