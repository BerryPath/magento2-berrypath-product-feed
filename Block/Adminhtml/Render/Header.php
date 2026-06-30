<?php

declare(strict_types=1);

namespace BerryPath\ProductFeed\Block\Adminhtml\Render;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Header extends Field
{
    protected $_template = 'BerryPath_ProductFeed::system/config/fieldset/header.phtml';

    public function render(AbstractElement $element): string
    {
        $element->addClass('berrypath');

        return $this->toHtml();
    }
}
