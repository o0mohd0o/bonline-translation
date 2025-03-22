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

class Save extends Action
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
     * Save translation
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
            $string = $this->getRequest()->getParam('string', '');
            $translation = $this->getRequest()->getParam('translation', '');
            $locale = $this->getRequest()->getParam('locale', '');
            $storeId = (int)$this->getRequest()->getParam('store_id', 0);

            if (empty($string) || empty($locale)) {
                throw new \Exception(__('Original string and locale are required.'));
            }

            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('bonlineco_translation');

            $data = [
                'string' => $string,
                'translation' => $translation,
                'locale' => $locale,
                'store_id' => $storeId,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($id > 0) {
                // Update existing translation
                $connection->update($tableName, $data, ['id = ?' => $id]);
                $message = __('Translation updated successfully.');
            } else {
                // Check if translation already exists
                $select = $connection->select()
                    ->from($tableName)
                    ->where('string = ?', $string)
                    ->where('locale = ?', $locale)
                    ->where('store_id = ?', $storeId);

                $existingId = $connection->fetchOne($select);

                if ($existingId) {
                    // Update existing translation
                    $connection->update($tableName, $data, ['id = ?' => $existingId]);
                    $message = __('Translation updated successfully.');
                } else {
                    // Create new translation
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $connection->insert($tableName, $data);
                    $message = __('Translation added successfully.');
                }
            }

            $success = true;
        } catch (\Exception $e) {
            $message = __('Error saving translation: %1', $e->getMessage());
        }

        return $result->setData([
            'success' => $success,
            'message' => $message
        ]);
    }
}
