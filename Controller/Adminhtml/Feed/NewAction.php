<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'BerryPath_ProductFeed::feeds';

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        return $this->resultRedirectFactory->create()->setPath('berrypath/feed/edit');
    }
}
