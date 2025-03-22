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

class Delete extends Action
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
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resourceConnection = $resourceConnection;
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
     * Delete translation
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $success = false;
        $message = '';

        try {
            $id = (int)$this->getRequest()->getParam('id', 0);

            if ($id <= 0) {
                throw new \Exception(__('Invalid translation ID.'));
            }

            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('bonlineco_translation');

            $connection->delete($tableName, ['id = ?' => $id]);
            $success = true;
            $message = __('Translation deleted successfully.');
        } catch (\Exception $e) {
            $message = __('Error deleting translation: %1', $e->getMessage());
        }

        return $result->setData([
            'success' => $success,
            'message' => $message
        ]);
    }
}
