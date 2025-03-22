<?php
/**
 * Bonline Translation Module
 *
 * @category  Bonline
 * @package   Bonlineco_Translation
 */

namespace Bonlineco\Translation\Controller\Adminhtml\Manage;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

class LoadTranslations extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
    }

    /**
     * Check the permission to run it
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Bonlineco_Translation::manage');
    }

    /**
     * Load translations
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $success = false;
        $message = '';
        $translations = [];
        $totalCount = 0;

        try {
            $searchString = $this->getRequest()->getParam('search', '');
            $locale = $this->getRequest()->getParam('locale', '');
            $storeId = (int)$this->getRequest()->getParam('store_id', 0);
            $page = max(1, (int)$this->getRequest()->getParam('page', 1));
            $limit = max(1, (int)$this->getRequest()->getParam('limit', 20));

            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('bonlineco_translation');
            
            // Build the select query
            $select = $connection->select()->from($table);

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

            // Get total count
            $countSelect = clone $select;
            $countSelect->reset(\Magento\Framework\DB\Select::COLUMNS)
                ->reset(\Magento\Framework\DB\Select::LIMIT_COUNT)
                ->reset(\Magento\Framework\DB\Select::LIMIT_OFFSET)
                ->columns('COUNT(*)');
            $totalCount = (int)$connection->fetchOne($countSelect);

            // Add pagination
            $offset = ($page - 1) * $limit;
            $select->limit($limit, $offset);
            $select->order('id DESC');

            // Get results
            $items = $connection->fetchAll($select);
            
            // Prepare store names
            $storeNames = [0 => __('All Stores')];
            foreach ($this->storeManager->getStores() as $store) {
                $storeNames[$store->getId()] = $store->getName();
            }
            
            // Format translations
            foreach ($items as $item) {
                $item['store_name'] = isset($storeNames[$item['store_id']]) 
                    ? $storeNames[$item['store_id']] 
                    : __('Unknown Store');
                $translations[] = $item;
            }

            $success = true;
        } catch (\Exception $e) {
            $message = __('Error loading translations: %1', $e->getMessage());
        }

        return $result->setData([
            'success' => $success,
            'message' => $message,
            'translations' => $translations,
            'total_count' => $totalCount
        ]);
    }
}
