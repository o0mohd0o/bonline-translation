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
use Magento\Framework\File\Csv;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;

class Import extends Action
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var Csv
     */
    protected $csvProcessor;

    /**
     * @var UploaderFactory
     */
    protected $uploaderFactory;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param Context $context
     * @param ResourceConnection $resourceConnection
     * @param Csv $csvProcessor
     * @param UploaderFactory $uploaderFactory
     * @param Filesystem $filesystem
     */
    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        Csv $csvProcessor,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem
    ) {
        parent::__construct($context);
        $this->resourceConnection = $resourceConnection;
        $this->csvProcessor = $csvProcessor;
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
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
     * Import translations from CSV
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('*/*/index');

        try {
            // Basic error checking for file upload
            if (!isset($_FILES['import_file']) || !isset($_FILES['import_file']['name']) || empty($_FILES['import_file']['name'])) {
                throw new \Exception(__('Please select a CSV file to import.'));
            }
            
            // Check for upload errors
            if (isset($_FILES['import_file']['error']) && $_FILES['import_file']['error'] > 0) {
                $errorMessages = [
                    1 => __('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
                    2 => __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
                    3 => __('The uploaded file was only partially uploaded'),
                    4 => __('No file was uploaded'),
                    6 => __('Missing a temporary folder'),
                    7 => __('Failed to write file to disk'),
                    8 => __('A PHP extension stopped the file upload')
                ];
                
                $errorMessage = isset($errorMessages[$_FILES['import_file']['error']]) 
                    ? $errorMessages[$_FILES['import_file']['error']] 
                    : __('Unknown upload error: %1', $_FILES['import_file']['error']);
                
                throw new \Exception($errorMessage);
            }

            // Upload file
            try {
                $uploader = $this->uploaderFactory->create(['fileId' => 'import_file']);
                $uploader->setAllowedExtensions(['csv']);
                $uploader->setAllowRenameFiles(true);
                $uploader->setFilesDispersion(false);
            } catch (\Exception $e) {
                throw new \Exception(__('Error initializing uploader: %1', $e->getMessage()));
            }

            try {
                $path = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)
                    ->getAbsolutePath('import/');
                
                if (!is_dir($path)) {
                    mkdir($path, 0777, true);
                }

                // Verify directory is writeable
                if (!is_writable($path)) {
                    throw new \Exception(__('Import directory is not writable: %1', $path));
                }
                
                $result = $uploader->save($path);
                $this->messageManager->addNoticeMessage('File saved to: ' . $path);
            } catch (\Exception $e) {
                throw new \Exception(__('Error saving file: %1', $e->getMessage()));
            }
            $file = $result['path'] . $result['file'];

            // Parse CSV
            $data = $this->csvProcessor->getData($file);
            
            // Check for header
            if (empty($data) || count($data) < 2) {
                throw new \Exception(__('The CSV file is empty or invalid.'));
            }
            
            // Get header and check format
            $header = array_shift($data);
            $requiredColumns = ['string', 'translation', 'locale', 'store_id'];
            
            // Ensure all required columns are present
            foreach ($requiredColumns as $column) {
                if (!in_array($column, $header)) {
                    throw new \Exception(__('The CSV file does not contain the required column: %1', $column));
                }
            }
            
            // Map indices to column names
            $mappedIndices = [];
            foreach ($requiredColumns as $column) {
                $index = array_search($column, $header);
                if ($index !== false) {
                    $mappedIndices[$column] = $index;
                }
            }

            // Import data
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('bonlineco_translation');
            $importedCount = 0;

            foreach ($data as $row) {
                // Skip empty rows
                if (empty($row[$mappedIndices['string']]) || empty($row[$mappedIndices['locale']])) {
                    continue;
                }

                $translationData = [
                    'string' => $row[$mappedIndices['string']],
                    'translation' => $row[$mappedIndices['translation']],
                    'locale' => $row[$mappedIndices['locale']],
                    'store_id' => (int)$row[$mappedIndices['store_id']],
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                // Check if translation already exists
                $select = $connection->select()
                    ->from($tableName, ['id'])
                    ->where('string = ?', $translationData['string'])
                    ->where('locale = ?', $translationData['locale'])
                    ->where('store_id = ?', $translationData['store_id']);
                
                $existingId = $connection->fetchOne($select);

                if ($existingId) {
                    // Update existing translation
                    $connection->update($tableName, $translationData, ['id = ?' => $existingId]);
                } else {
                    // Create new translation
                    $translationData['created_at'] = date('Y-m-d H:i:s');
                    $connection->insert($tableName, $translationData);
                }

                $importedCount++;
            }

            // Remove uploaded file
            unlink($file);

            $this->messageManager->addSuccessMessage(__('Successfully imported %1 translations.', $importedCount));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error importing translations: %1', $e->getMessage()));
        }

        return $resultRedirect;
    }
}
