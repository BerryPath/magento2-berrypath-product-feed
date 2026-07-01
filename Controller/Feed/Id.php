<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Controller\Feed;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;

class Id extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context
    ) {
        parent::__construct($context);
    }

    public function execute(): Raw
    {
        /** @var Raw $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHttpResponseCode(404);
        $result->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $result->setContents((string)__('Feed files are served from generated media files.'));

        return $result;
    }
}
