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
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;

class MassDelete extends Action
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @param Context $context
     * @param ResourceConnection $resourceConnection
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->resourceConnection = $resourceConnection;
        $this->resultJsonFactory = $resultJsonFactory;
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
     * Mass delete action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $success = false;
        $message = '';
        $deletedCount = 0;

        try {
            $ids = $this->getRequest()->getParam('ids', []);
            
            if (!is_array($ids) || empty($ids)) {
                throw new \Exception(__('Please select translations to delete.'));
            }

            // Make sure all IDs are integers
            $ids = array_map('intval', $ids);
            
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('bonlineco_translation');
            
            // Delete selected translations
            $deletedCount = $connection->delete($tableName, ['id IN (?)' => $ids]);
            
            $success = true;
            $message = __('Successfully deleted %1 translation(s).', $deletedCount);
        } catch (\Exception $e) {
            $message = __('Error deleting translations: %1', $e->getMessage());
        }

        return $result->setData([
            'success' => $success,
            'message' => $message,
            'deleted_count' => $deletedCount
        ]);
    }
}
