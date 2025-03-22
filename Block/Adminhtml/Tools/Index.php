<?php
/**
 * Bonline Translation Module
 *
 * @category  Bonline
 * @package   Bonlineco_Translation
 */

namespace Bonlineco\Translation\Block\Adminhtml\Tools;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

class Index extends Template
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->storeManager = $storeManager;
    }

    /**
     * Get all available languages/locales
     *
     * @return array
     */
    public function getAvailableLocales()
    {
        $locales = [];
        foreach ($this->storeManager->getStores() as $store) {
            $locale = $store->getConfig('general/locale/code');
            $locales[$locale] = $locale;
        }
        return $locales;
    }

    /**
     * Get clean cache URL
     *
     * @return string
     */
    public function getCleanCacheUrl()
    {
        return $this->getUrl('bonlineco_translation/tools/cleancache');
    }

    /**
     * Get deploy static URL
     *
     * @return string
     */
    public function getDeployStaticUrl()
    {
        return $this->getUrl('bonlineco_translation/tools/deploystatic');
    }

    /**
     * Get deploy translations URL
     *
     * @return string
     */
    public function getDeployTranslationsUrl()
    {
        return $this->getUrl('bonlineco_translation/tools/deploytranslations');
    }
}
