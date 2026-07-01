<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Controller\Feed;

use BerryPath\ProductFeed\Model\Feed\Config as FeedConfig;
use BerryPath\ProductFeed\Model\Feed\FileStorage;
use BerryPath\ProductFeed\Model\Feed\Formatter;
use BerryPath\ProductFeed\Model\Feed\Generator;
use BerryPath\ProductFeed\Model\Config\Source\OutputFormat;
use BerryPath\ProductFeed\Model\Profile;
use BerryPath\ProductFeed\Model\ProfileRepository;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Escaper;
use Psr\Log\LoggerInterface;

class Preview extends Action implements HttpGetActionInterface
{
    private const PREVIEW_PRODUCT_LIMIT = 25;

    public function __construct(
        Context $context,
        private readonly FeedConfig $feedConfig,
        private readonly Generator $generator,
        private readonly Formatter $formatter,
        private readonly FileStorage $fileStorage,
        private readonly Escaper $escaper,
        private readonly ProfileRepository $profileRepository,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Raw
    {
        $profile = $this->getProfile();

        if ($profile === null || !$this->canGenerate($profile)) {
            return $this->rawResponse((string)__('Product feed is not available.'), 'text/plain');
        }

        try {
            $productId = $this->getOptionalParam('pid');
            $isLimited = $productId === null;
            $storeId = $profile->getStoreId();
            $feed = $this->generator->generate(
                $storeId,
                $productId,
                $isLimited ? self::PREVIEW_PRODUCT_LIMIT : null,
                $profile
            );
        } catch (\Throwable $exception) {
            $this->logger->error('BerryPath ProductFeed preview failed.', ['exception' => $exception]);

            return $this->rawResponse((string)__('Unable to render the feed preview.'), 'text/plain', 500);
        }

        return $this->rawResponse($this->renderPreview($feed, $isLimited, $profile), 'text/html');
    }

    private function getProfile(): ?Profile
    {
        $id = (int)$this->getRequest()->getParam('id');
        if ($id <= 0) {
            return null;
        }

        try {
            return $this->profileRepository->getById($id);
        } catch (\Throwable) {
            return $this->profileRepository->getFirstActiveByStoreId($id);
        }
    }

    private function canGenerate(Profile $profile): bool
    {
        return $profile->isActive()
            && $profile->getStoreId() > 0
            && $this->feedConfig->isFeedEnabled($profile->getStoreId());
    }

    private function getOptionalParam(string $key): ?string
    {
        $value = trim((string)$this->getRequest()->getParam($key));

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $feed
     */
    private function renderPreview(array $feed, bool $isLimited, Profile $profile): string
    {
        $content = $this->formatFeed($feed, $profile);
        $formatLabel = strtoupper($this->feedConfig->getOutputFormat($profile->getStoreId(), $profile));
        $notice = $isLimited
            ? (string)__('Preview limited to the first %1 products. The feed exports all products.', self::PREVIEW_PRODUCT_LIMIT)
            : '';

        $openFeedHtml = $this->fileStorage->exists($profile)
            ? '<a href="' . $this->escape($this->fileStorage->getUrl($profile)) . '" target="_blank" rel="noopener">'
                . $this->escape((string)__('Open feed')) . '</a>'
            : '<span class="bp-preview__not-generated">' . $this->escape((string)__('Not generated')) . '</span>';

        return '<!doctype html><html><head><meta charset="utf-8"><title>Product Feed Preview</title>'
            . $this->getPreviewStyles()
            . '</head><body><main class="bp-preview">'
            . '<header class="bp-preview__header"><div><span>BerryPath</span><h1>'
            . $this->escape((string)__('Feed preview')) . ' <small>' . $this->escape($formatLabel) . '</small>'
            . '</h1></div>' . $openFeedHtml . '</header>'
            . ($notice !== '' ? '<div class="bp-preview__notice">' . $this->escape($notice) . '</div>' : '')
            . '<pre class="bp-preview__content">'
            . $this->escape($content)
            . '</pre></main></body></html>';
    }

    private function getPreviewStyles(): string
    {
        return '<style>'
            . 'body{background:#f7f4fa;color:#303030;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;margin:0;}'
            . '.bp-preview{margin:0 auto;max-width:1280px;padding:32px;}'
            . '.bp-preview__header{align-items:center;display:flex;gap:16px;justify-content:space-between;margin-bottom:18px;}'
            . '.bp-preview__header span{color:#7a1c9d;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;}'
            . '.bp-preview__header h1{font-size:28px;line-height:1.2;margin:3px 0 0;}'
            . '.bp-preview__header h1 small{color:#7a1c9d;font-size:14px;font-weight:700;margin-left:8px;}'
            . '.bp-preview__header a{background:#7a1c9d;color:#fff;font-weight:700;padding:10px 14px;text-decoration:none;}'
            . '.bp-preview__not-generated{background:#fff;border:1px solid #e4d9ea;color:#514943;font-weight:700;padding:10px 14px;}'
            . '.bp-preview__notice{background:#fff5df;border:1px solid #f0d28a;color:#5f4600;margin-bottom:16px;padding:12px 14px;}'
            . '.bp-preview__content{background:#17111c;border:1px solid #e4d9ea;color:#f8f3fb;font-size:12px;line-height:1.5;margin:0;overflow:auto;padding:16px;white-space:pre;}'
            . '</style>';
    }

    /**
     * @param array<string, mixed> $feed
     */
    private function formatFeed(array $feed, Profile $profile): string
    {
        $storeId = $profile->getStoreId();
        $format = $this->feedConfig->getOutputFormat($storeId, $profile);
        $feedType = $this->feedConfig->getFeedType($storeId, $profile);
        $options = $this->getGoogleOptions($feed, $profile);

        if ($format === OutputFormat::JSON) {
            return $this->formatter->toChannelJson($feed, $feedType, $options);
        }

        if ($format === OutputFormat::CSV) {
            return $this->formatter->toChannelCsv($feed, $feedType, $options);
        }

        return $this->formatter->toChannelXml($feed, $feedType, $options);
    }

    /**
     * @param array<string, mixed> $feed
     * @return array{use_cdata: bool, condition: string, shipping: array{country: string, service: string, price: string}|null}
     */
    private function getGoogleOptions(array $feed, Profile $profile): array
    {
        $currency = is_array($feed['config'] ?? null) ? (string)($feed['config']['currency'] ?? '') : '';

        return $this->feedConfig->getGoogleOptions($profile->getStoreId(), $currency, $profile);
    }

    private function escape(string $value): string
    {
        return $this->escaper->escapeHtml($value);
    }

    private function rawResponse(string $content, string $contentType, int $statusCode = 200): Raw
    {
        /** @var Raw $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHttpResponseCode($statusCode);
        $result->setContents($content);

        foreach ($this->getPreviewHeaders($contentType) as $header => $value) {
            $result->setHeader($header, $value, true);
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function getPreviewHeaders(string $contentType): array
    {
        return [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, no-cache, no-store, max-age=0, must-revalidate',
            'Expires' => '0',
            'Pragma' => 'no-cache',
        ];
    }
}
