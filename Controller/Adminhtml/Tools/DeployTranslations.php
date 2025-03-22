<?php
/**
 * Bonline Translation Module
 *
 * @category  Bonline
 * @package   Bonlineco_Translation
 */

namespace Bonlineco\Translation\Controller\Adminhtml\Tools;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\Result\PageFactory;

class DeployTranslations extends Action
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var File
     */
    protected $fileDriver;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param ResourceConnection $resourceConnection
     * @param Filesystem $filesystem
     * @param File $fileDriver
     * @param JsonFactory $resultJsonFactory
     * @param StoreManagerInterface $storeManager
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        Filesystem $filesystem,
        File $fileDriver,
        JsonFactory $resultJsonFactory,
        StoreManagerInterface $storeManager,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resourceConnection = $resourceConnection;
        $this->filesystem = $filesystem;
        $this->fileDriver = $fileDriver;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->storeManager = $storeManager;
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Check the permission to run it
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Bonlineco_Translation::tools');
    }

    /**
     * Deploy translations to CSV files
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $isAjax = $this->getRequest()->getParam('isAjax', false);
        $success = false;
        $message = '';
        $details = [];

        try {
            // Get all translations grouped by locale
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('bonlineco_translation');
            
            $select = $connection->select()
                ->from($table, ['locale', 'string', 'translation', 'store_id']);
            
            $data = $connection->fetchAll($select);
            
            // Group translations by locale
            $translationsByLocale = [];
            foreach ($data as $item) {
                $locale = $item['locale'];
                if (!isset($translationsByLocale[$locale])) {
                    $translationsByLocale[$locale] = [];
                }
                
                // If storeId is 0, it applies to all stores
                // Otherwise, it's specific to a store
                $storeId = (int)$item['store_id'];
                
                if ($storeId === 0) {
                    // Global translation
                    if (!isset($translationsByLocale[$locale]['global'])) {
                        $translationsByLocale[$locale]['global'] = [];
                    }
                    $translationsByLocale[$locale]['global'][$item['string']] = $item['translation'];
                } else {
                    // Store-specific translation
                    if (!isset($translationsByLocale[$locale][$storeId])) {
                        $translationsByLocale[$locale][$storeId] = [];
                    }
                    $translationsByLocale[$locale][$storeId][$item['string']] = $item['translation'];
                }
            }
            
            // Create translation files
            // Each locale will have a file in app/i18n/bonlineco/[locale]/
            // For example: app/i18n/bonlineco/en_US/en_US.csv
            $i18nDir = $this->filesystem->getDirectoryWrite(DirectoryList::APP)->getAbsolutePath('i18n/bonlineco');
            
            if (!$this->fileDriver->isDirectory($i18nDir)) {
                $this->fileDriver->createDirectory($i18nDir, 0755);
            }
            
            foreach ($translationsByLocale as $locale => $translations) {
                // Create locale directory
                $localeDir = $i18nDir . '/' . $locale;
                if (!$this->fileDriver->isDirectory($localeDir)) {
                    $this->fileDriver->createDirectory($localeDir, 0755);
                }
                
                // Create registration.php file if it doesn't exist
                $registrationFile = $localeDir . '/registration.php';
                if (!$this->fileDriver->isExists($registrationFile)) {
                    $registrationContent = "<?php
/**
 * {$locale} language pack registration
 */
\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::LANGUAGE,
    'bonlineco_{$this->_normalizeLocale($locale)}',
    __DIR__
);"; 
                    $this->fileDriver->filePutContents($registrationFile, $registrationContent);
                }
                
                // Create language.xml file if it doesn't exist
                $languageFile = $localeDir . '/language.xml';
                if (!$this->fileDriver->isExists($languageFile)) {
                    $normalizedLocale = $this->_normalizeLocale($locale);
                    $languageContent = "<?xml version=\"1.0\"?>
<language xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:noNamespaceSchemaLocation=\"urn:magento:framework:App/Language/package.xsd\">
    <code>{$locale}</code>
    <vendor>bonlineco</vendor>
    <package>{$normalizedLocale}</package>
    <sort_order>100</sort_order>
</language>";
                    $this->fileDriver->filePutContents($languageFile, $languageContent);
                }
                
                // Create global translations file
                $csvContent = '';
                
                // Add global translations
                if (isset($translations['global'])) {
                    foreach ($translations['global'] as $string => $translation) {
                        $csvContent .= '"' . str_replace('"', '""', $string) . '","' . 
                            str_replace('"', '""', $translation) . '"' . PHP_EOL;
                    }
                }
                
                // Add store-specific translations, which override global translations
                foreach ($translations as $storeId => $storeTranslations) {
                    if ($storeId === 'global') {
                        continue; // Skip global translations, we've already added them
                    }
                    
                    foreach ($storeTranslations as $string => $translation) {
                        // Find existing entry for this string and replace it, or add new one
                        $replaced = false;
                        $pattern = '/"' . str_replace('"', '""', preg_quote($string, '/')) . '","[^"]*"/';
                        $replacement = '"' . str_replace('"', '""', $string) . '","' . str_replace('"', '""', $translation) . '"';
                        
                        // Only replace the exact match
                        $csvContent = preg_replace($pattern, $replacement, $csvContent, 1, $count);
                        if ($count === 0) {
                            // No match found, add as new entry
                            $csvContent .= '"' . str_replace('"', '""', $string) . '","' . 
                                str_replace('"', '""', $translation) . '"' . PHP_EOL;
                        }
                    }
                }
                
                // Write CSV file
                $csvFile = $localeDir . '/' . $locale . '.csv';
                $this->fileDriver->filePutContents($csvFile, $csvContent);
                
                $details[] = __('Created translation file for locale %1: %2', $locale, $csvFile);
            }
            
            // Trigger static content deployment for each locale
            foreach (array_keys($translationsByLocale) as $locale) {
                $this->_runMagentoCommand(['setup:static-content:deploy', '-f', $locale]);
                $details[] = __('Static content deployed for locale %1', $locale);
            }
            
            // Clear translation cache
            $this->_runMagentoCommand(['cache:clean', 'translate', 'full_page']);
            $details[] = __('Translation cache cleared');
            
            $success = true;
            $message = __('Translations have been deployed successfully.');
        } catch (\Exception $e) {
            $message = __('Error deploying translations: %1', $e->getMessage());
        }

        if ($isAjax) {
            return $result->setData([
                'success' => $success,
                'message' => $message,
                'details' => $details
            ]);
        } else {
            if ($success) {
                $this->messageManager->addSuccessMessage($message);
                foreach ($details as $detail) {
                    $this->messageManager->addNoticeMessage($detail);
                }
            } else {
                $this->messageManager->addErrorMessage($message);
            }
            return $this->_redirect('*/*/index');
        }
    }
    
    /**
     * Normalize locale code for use in package name
     * 
     * @param string $locale
     * @return string
     */
    protected function _normalizeLocale($locale)
    {
        return strtolower(str_replace('_', '-', $locale));
    }
    
    /**
     * Run a Magento CLI command
     * 
     * @param array $command Command and arguments
     * @return string Output from command
     */
    protected function _runMagentoCommand($command)
    {
        try {
            // Use a more reliable approach - call Magento CLI directly
            // without relying on PHP_BINARY which might point to php-fpm
            $magentoBin = BP . '/bin/magento';
            
            // Safer method - shell script is executable and has the right shebang
            $fullCommand = $magentoBin . ' ' . implode(' ', $command);
            
            $output = '';
            $returnVar = 0;
            
            exec($fullCommand . ' 2>&1', $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new \Exception('Command execution failed with code ' . $returnVar . ': ' . implode("\n", $output));
            }
            
            return implode("\n", $output);
        } catch (\Exception $e) {
            throw new \Exception('Error running Magento command: ' . $e->getMessage());
        }
    }
}
