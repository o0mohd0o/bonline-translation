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
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;

class Export extends Action
{
    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var Csv
     */
    protected $csvProcessor;
    
    /**
     * @var Filesystem
     */
    protected $_filesystem;

    /**
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param ResourceConnection $resourceConnection
     * @param Csv $csvProcessor
     */
    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        ResourceConnection $resourceConnection,
        Csv $csvProcessor,
        Filesystem $filesystem
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->resourceConnection = $resourceConnection;
        $this->csvProcessor = $csvProcessor;
        $this->_filesystem = $filesystem;
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
     * Export translations as CSV
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        try {
            $locale = $this->getRequest()->getParam('locale', '');
            $storeId = (int)$this->getRequest()->getParam('store_id', 0);
            
            // Set filename
            $filename = 'translations';
            if (!empty($locale)) {
                $filename .= '_' . $locale;
            }
            if ($storeId > 0) {
                $filename .= '_store_' . $storeId;
            }
            $filename .= '_' . date('Ymd_His') . '.csv';
            
            // Get translations
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('bonlineco_translation');
            
            $select = $connection->select()->from($table, ['string', 'translation', 'locale', 'store_id']);
            
            if (!empty($locale)) {
                $select->where('locale = ?', $locale);
            }
            
            if ($storeId > 0) {
                $select->where('store_id = ?', $storeId);
            }
            
            $translations = $connection->fetchAll($select);
            
            // Prepare CSV data
            $data = [];
            $data[] = ['string', 'translation', 'locale', 'store_id']; // Header
            
            foreach ($translations as $translation) {
                $data[] = [
                    $translation['string'],
                    $translation['translation'],
                    $translation['locale'],
                    $translation['store_id']
                ];
            }
            
            // Create a temporary file for the CSV data
            $tmpDir = $this->_filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $tmpPath = $tmpDir->getAbsolutePath('tmp/' . $filename);
            
            // Save the data to the temporary file
            $this->csvProcessor->saveData($tmpPath, $data);
            
            // Return the file for download
            return $this->fileFactory->create(
                $filename,
                [
                    'type' => 'filename',
                    'value' => 'tmp/' . $filename,
                    'rm' => true // Remove the file after download
                ],
                DirectoryList::VAR_DIR,
                'text/csv'
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error exporting translations: %1', $e->getMessage()));
            return $this->_redirect('*/*/index');
        }
    }
}
