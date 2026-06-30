<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Controller\Feed;

use BerryPath\ProductFeed\Model\Feed\Config as FeedConfig;
use BerryPath\ProductFeed\Model\Feed\Formatter;
use BerryPath\ProductFeed\Model\Feed\Generator;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Escaper;

class Preview extends Action implements HttpGetActionInterface
{
    private const PREVIEW_PRODUCT_LIMIT = 25;

    public function __construct(
        Context $context,
        private readonly FeedConfig $feedConfig,
        private readonly Generator $generator,
        private readonly Formatter $formatter,
        private readonly Escaper $escaper
    ) {
        parent::__construct($context);
    }

    public function execute(): Raw
    {
        $storeId = (int)$this->getRequest()->getParam('id');

        if (!$this->canGenerate($storeId)) {
            return $this->rawResponse((string)__('Product feed is not available.'), 'text/plain');
        }

        try {
            $productId = $this->getOptionalParam('pid');
            $isLimited = $productId === null;
            $feed = $this->generator->generate(
                $storeId,
                $productId,
                $isLimited ? self::PREVIEW_PRODUCT_LIMIT : null
            );
        } catch (\Throwable $exception) {
            return $this->rawResponse($exception->getMessage(), 'text/plain', 500);
        }

        return $this->rawResponse($this->renderPreview($feed, $isLimited, $storeId), 'text/html');
    }

    private function canGenerate(int $storeId): bool
    {
        return $storeId > 0
            && $this->feedConfig->isFeedEnabled($storeId);
    }

    private function getOptionalParam(string $key): ?string
    {
        $value = trim((string)$this->getRequest()->getParam($key));

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $feed
     */
    private function renderPreview(array $feed, bool $isLimited, int $storeId): string
    {
        $xml = $this->formatFeed($feed, $storeId);
        $notice = $isLimited
            ? (string)__('Preview limited to the first %1 products. The XML feed exports all products.', self::PREVIEW_PRODUCT_LIMIT)
            : '';

        return '<!doctype html><html><head><meta charset="utf-8"><title>Product Feed Preview</title>'
            . $this->getPreviewStyles()
            . '</head><body><main class="bp-preview">'
            . '<header class="bp-preview__header"><div><span>BerryPath</span><h1>'
            . $this->escape((string)__('Feed preview'))
            . '</h1></div><a href="'
            . $this->escape($this->feedConfig->getFeedUrl($storeId))
            . '" target="_blank" rel="noopener">'
            . $this->escape((string)__('Open XML feed'))
            . '</a></header>'
            . ($notice !== '' ? '<div class="bp-preview__notice">' . $this->escape($notice) . '</div>' : '')
            . '<pre class="bp-preview__xml">'
            . $this->escape($xml)
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
            . '.bp-preview__header a{background:#7a1c9d;color:#fff;font-weight:700;padding:10px 14px;text-decoration:none;}'
            . '.bp-preview__notice{background:#fff5df;border:1px solid #f0d28a;color:#5f4600;margin-bottom:16px;padding:12px 14px;}'
            . '.bp-preview__xml{background:#17111c;border:1px solid #e4d9ea;color:#f8f3fb;font-size:12px;line-height:1.5;margin:0;overflow:auto;padding:16px;white-space:pre;}'
            . '</style>';
    }

    /**
     * @param array<string, mixed> $feed
     */
    private function formatFeed(array $feed, int $storeId): string
    {
        if (!$this->feedConfig->isGoogleShoppingFeed($storeId)) {
            return $this->formatter->toXml($feed);
        }

        $currency = is_array($feed['config'] ?? null) ? (string)($feed['config']['currency'] ?? '') : '';

        return $this->formatter->toGoogleRss($feed, $this->feedConfig->getGoogleOptions($storeId, $currency));
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
        $result->setHeader('Content-Type', $contentType);
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $result->setHeader('Pragma', 'no-cache', true);
        $result->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
        $result->setContents($content);

        return $result;
    }
}
