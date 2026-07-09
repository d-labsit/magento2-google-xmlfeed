<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders a "Generate Now" button on the config page that regenerates the feed
 * for the current store scope via an admin AJAX call.
 */
class GenerateButton extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $storeId = (int) $this->getRequest()->getParam('store', 0);
        $url = $this->getUrl('dlabsit_googlefeed/generate/index', ['store' => $storeId]);

        /** @var Button $button */
        $button = $this->getLayout()->createBlock(Button::class)
            ->setData([
                'id' => 'dlabsit_googlefeed_generate_btn',
                'label' => __('Generate Now'),
                'type' => 'button',
            ]);

        $result = '<span id="dlabsit_googlefeed_generate_result" style="margin-left:12px;"></span>';

        $script = <<<HTML
<script>
require(['jquery', 'Magento_Ui/js/modal/alert'], function ($, alert) {
    $('#dlabsit_googlefeed_generate_btn').on('click', function () {
        var btn = $(this), out = $('#dlabsit_googlefeed_generate_result');
        btn.prop('disabled', true);
        out.text('Generating…').css('color', '');
        $.ajax({ url: '{$url}', type: 'GET', dataType: 'json', showLoader: true })
            .done(function (r) {
                out.text(r.message || '').css('color', r.success ? '#28a745' : '#e02b27');
            })
            .fail(function () {
                out.text('Request failed.').css('color', '#e02b27');
            })
            .always(function () { btn.prop('disabled', false); });
    });
});
</script>
HTML;

        return $button->toHtml() . $result . $script;
    }
}
