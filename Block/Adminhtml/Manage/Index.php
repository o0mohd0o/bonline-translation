<?php
/**
 * Bonline Translation Module
 *
 * @category  Bonline
 * @package   Bonlineco_Translation
 */

namespace Bonlineco\Translation\Block\Adminhtml\Manage;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class Index extends Template
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var AdapterInterface
     */
    protected $connection;

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param array $data
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->storeManager = $storeManager;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $resourceConnection->getConnection();
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
     * Get all stores
     *
     * @return array
     */
    public function getStores()
    {
        return $this->storeManager->getStores();
    }

    /**
     * Get translations
     *
     * @param string $searchString
     * @param string $locale
     * @param int $storeId
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getTranslations($searchString = '', $locale = '', $storeId = 0, $page = 1, $limit = 20)
    {
        $table = $this->resourceConnection->getTableName('bonlineco_translation');
        $select = $this->connection->select()
            ->from($table);

        if (!empty($searchString)) {
            $select->where('string LIKE ?', '%' . $searchString . '%')
                ->orWhere('translation LIKE ?', '%' . $searchString . '%');
        }

        if (!empty($locale)) {
            $select->where('locale = ?', $locale);
        }

        if ($storeId > 0) {
            $select->where('store_id = ?', $storeId);
        }

        // Add pagination
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $limit;
        $select->limit($limit, $offset);

        // Order by ID descending
        $select->order('id DESC');

        return $this->connection->fetchAll($select);
    }

    /**
     * Count total translations
     *
     * @param string $searchString
     * @param string $locale
     * @param int $storeId
     * @return int
     */
    public function countTranslations($searchString = '', $locale = '', $storeId = 0)
    {
        $table = $this->resourceConnection->getTableName('bonlineco_translation');
        $select = $this->connection->select()
            ->from($table, ['COUNT(*)']);

        if (!empty($searchString)) {
            $select->where('string LIKE ?', '%' . $searchString . '%')
                ->orWhere('translation LIKE ?', '%' . $searchString . '%');
        }

        if (!empty($locale)) {
            $select->where('locale = ?', $locale);
        }

        if ($storeId > 0) {
            $select->where('store_id = ?', $storeId);
        }

        return (int)$this->connection->fetchOne($select);
    }

    /**
     * Get save translation URL
     *
     * @return string
     */
    public function getSaveTranslationUrl()
    {
        return $this->getUrl('bonlineco_translation/manage/save');
    }

    /**
     * Get delete translation URL
     *
     * @return string
     */
    public function getDeleteTranslationUrl()
    {
        return $this->getUrl('bonlineco_translation/manage/delete');
    }

    /**
     * Get mass delete URL
     *
     * @return string
     */
    public function getMassDeleteUrl()
    {
        return $this->getUrl('bonlineco_translation/manage/massDelete');
    }

    /**
     * Get load translations URL
     *
     * @return string
     */
    public function getLoadTranslationsUrl()
    {
        return $this->getUrl('bonlineco_translation/manage/loadTranslations');
    }
}
