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
use Psr\Log\LoggerInterface;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Magento\Framework\MessageQueue\PublisherInterface;

/**
 * Deploy Translations Controller
 * 
 * Handles the deployment of translations to CSV files and static content
 */
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
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var State
     */
    protected $appState;

    /**
     * @var Cli
     */
    protected $cliApplication;

    /**
     * @var PublisherInterface
     */
    protected $messageBus;

    /**
     * @param Context $context
     * @param ResourceConnection $resourceConnection
     * @param Filesystem $filesystem
     * @param File $fileDriver
     * @param JsonFactory $resultJsonFactory
     * @param StoreManagerInterface $storeManager
     * @param PageFactory $resultPageFactory
     * @param LoggerInterface $logger
     * @param State $appState
     * @param Cli $cliApplication
     * @param PublisherInterface $messageBus
     */
    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        Filesystem $filesystem,
        File $fileDriver,
        JsonFactory $resultJsonFactory,
        StoreManagerInterface $storeManager,
        PageFactory $resultPageFactory,
        LoggerInterface $logger,
        State $appState,
        Cli $cliApplication,
        PublisherInterface $messageBus
    ) {
        parent::__construct($context);
        $this->resourceConnection = $resourceConnection;
        $this->filesystem = $filesystem;
        $this->fileDriver = $fileDriver;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->storeManager = $storeManager;
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        $this->appState = $appState;
        $this->cliApplication = $cliApplication;
        $this->messageBus = $messageBus;
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
     * Path to the status file for live logging
     */
    const STATUS_FILE_PATH = 'var/translation_deployment_status.json';

    /**
     * Deploy translations to CSV files
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        // Set area code to avoid "Area code is not set" error
        try {
            $this->appState->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        }
        
        $result = $this->resultJsonFactory->create();
        $isAjax = $this->getRequest()->getParam('isAjax', false);
        $action = $this->getRequest()->getParam('action', 'start');
        $success = false;
        $message = '';
        $details = [];
        $outputContent = '';
        $executionTime = 0;

        // Add debug log at the beginning of the execute method
        $this->logger->info('DeployTranslations::execute started, isAjax: ' . ($isAjax ? 'true' : 'false') . ', action: ' . $action);
        $this->logger->info('Memory usage at start: ' . $this->formatBytes(memory_get_usage(true)));
        $startTime = microtime(true);
        
        // Handle status check request
        if ($action === 'status') {
            return $this->getDeploymentStatus();
        }
            
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
            
            // Get all locales for deployment
            $locales = array_keys($translationsByLocale);
            
            // Deploy all locales at once for all areas and themes
            // Each locale needs to be a separate argument
            $command = ['setup:static-content:deploy'];
            
            // Add each locale as a separate argument
            foreach ($locales as $locale) {
                $command[] = $locale;
            }
            
            // Add force flag at the end
            $command[] = '-f';
            
            // Log that we're about to execute the command
            $this->logger->info('Starting static content deployment with command: ' . json_encode($command));
            $deployStartTime = microtime(true);
            
            // Execute the command and capture detailed results
            try {
                // Run the static content deployment command
                $outputContent = $this->_runMagentoCommand($command);
                $deployEndTime = microtime(true);
                $executionTime = $deployEndTime - $deployStartTime;
                
                // Log the command output
                $this->logger->info('Static content deployment output length: ' . strlen($outputContent));
                $this->logger->info('Static content deployment output preview: ' . substr($outputContent, 0, 500) . '...');
                $this->logger->info('Static content deployment execution time: ' . $executionTime . ' seconds');
                
                // Add details about the deployment
                $details[] = __('Static content deployed for all areas and themes for locales: %1', implode(', ', $locales));
                
                // Clear translation cache using Magento commands
                $this->logger->info('Starting cache cleaning');
                $cacheTypes = ['translate', 'full_page', 'block_html', 'layout', 'config'];
                $this->logger->info('Cleaning cache types: ' . implode(', ', $cacheTypes));
                $cacheResult = $this->_runMagentoCommand(['cache:clean', ...$cacheTypes]);
                $this->logger->info('Cache cleaning output: ' . $cacheResult);
                $details[] = __('Translation cache cleared');
                
                // Also clean generated static files
                $this->logger->info('Cleaning generated static files');
                $cleanStaticResult = $this->_runMagentoCommand(['setup:static-content:clean']);
                $this->logger->info('Static content cleaning output: ' . $cleanStaticResult);
                $details[] = __('Generated static files cleaned');
                
                $success = true;
                $message = __('Translations have been deployed successfully for languages: %1 (Execution time: %2 seconds)', 
                    implode(', ', $locales), 
                    number_format($executionTime, 2)
                );
            } catch (\Exception $e) {
                $this->logger->error('Error during command execution: ' . $e->getMessage());
                throw $e; // Re-throw to be caught by the outer try-catch
            }
        } catch (\Exception $e) {
            $success = false;
            $message = __('An error occurred while deploying translations: %1', $e->getMessage());
            $this->logger->error('Translation deployment error: ' . $e->getMessage());
            // Log the stack trace for debugging
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
        }

        $endTime = microtime(true);
        $totalExecutionTime = $endTime - $startTime;
        $this->logger->info('Total execution time: ' . $totalExecutionTime . ' seconds');
        $this->logger->info('Memory usage at end: ' . $this->formatBytes(memory_get_usage(true)));

        if ($isAjax) {
            // Log the response data for debugging
            $this->logger->info('Sending AJAX response with success: ' . ($success ? 'true' : 'false'));
            $this->logger->info('Response message: ' . $message);
            
            // For 'start' action, return a different response format
            if ($action === 'start') {
                $responseData = [
                    'status' => 'started',
                    'message' => __('Translation deployment started'),
                ];
                $this->logger->info('Returning start response: ' . json_encode($responseData));
                $result->setData($responseData);
                return $result;
            }
            
            // For regular execution, use the standard response format
            $responseData = [
                'success' => (bool)$success,
                'message' => $message,
                'output' => isset($outputContent) ? $outputContent : '',
                'execution_time' => $totalExecutionTime,
                'status' => 'complete'
            ];
            
            // Make sure output is not null
            if (!isset($responseData['output']) || $responseData['output'] === null) {
                $responseData['output'] = '';
            }
            
            // Log the full response data
            $this->logger->info('Full response data: ' . json_encode($responseData));
            $this->logger->info('Output content length: ' . strlen($responseData['output']));
            
            // Try a different approach - let's use Magento's Result interface but with more logging
            $this->logger->info('Using Magento Result interface with detailed logging');
            $this->logger->info('Memory before response: ' . $this->formatBytes(memory_get_usage(true)));
            
            try {
                // Create a JSON result
                $this->logger->info('Creating JSON result object');
                $resultJson = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
                $this->logger->info('JSON result object created');
                
                // Ensure we have valid output
                if (!isset($responseData['output'])) {
                    $responseData['output'] = '';
                }
                
                // Log the response data
                $this->logger->info('Setting response data keys: ' . implode(', ', array_keys($responseData)));
                $this->logger->info('Output content length: ' . strlen($responseData['output']));
                
                // Set the data
                $resultJson->setData($responseData);
                $this->logger->info('Response data set');
                
                // Set headers explicitly
                $this->logger->info('Setting headers');
                $resultJson->setHeader('Content-Type', 'application/json', true);
                $this->logger->info('Headers set');
                
                // Log that we're about to return
                $this->logger->info('About to return JSON response');
                
                // Return the result
                return $resultJson;
            } catch (\Exception $e) {
                $this->logger->error('Exception during response generation: ' . $e->getMessage());
                $this->logger->error('Exception trace: ' . $e->getTraceAsString());
                
                // Even if we have an exception, try to return something
                $this->logger->info('Creating error JSON result');
                $resultJson = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
                $this->logger->info('Setting error response data');
                $resultJson->setData([
                    'success' => false,
                    'message' => 'An error occurred: ' . $e->getMessage(),
                    'output' => isset($outputContent) ? $outputContent : 'Error occurred during response generation'
                ]);
                $this->logger->info('Error response data set');
                $this->logger->info('About to return error JSON response');
                return $resultJson;
            }
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
            // Direct approach using Magento's CLI application
            $commandName = array_shift($command); // First element is the command name
            
            // Check if area code is already set
            $areaWasChanged = false;
            try {
                $currentArea = $this->appState->getAreaCode();
                // If area is not adminhtml, change it
                if ($currentArea !== \Magento\Framework\App\Area::AREA_ADMINHTML) {
                    $areaWasChanged = true;
                    $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
                }
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                // Area code is not set, so set it
                $areaWasChanged = true;
                $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
            }
            
            try {
                
                // Create a properly structured input with just the command
                $inputParams = ['command' => $commandName];
                
                // For static content deployment, we need special handling
                if ($commandName === 'setup:static-content:deploy') {
                    $this->logger->info('Executing static content deployment with force flag');
                    
                    // For static content deployment, use the simplest possible approach
                    // that works across all Magento versions
                    
                    // Start with a basic command array - first element must be the script name
                    $args = ['bin/magento', $commandName, '-f']; // Always include force flag
                    
                    // Process command arguments
                    foreach ($command as $key => $value) {
                        if (is_numeric($key) && is_string($value)) {
                            // Skip the -f flag if it's already there
                            if ($value !== '-f') {
                                $args[] = $value; // Add positional arguments (like locales)
                                $this->logger->info('Added positional argument: ' . $value);
                            }
                        } elseif (is_string($key) && substr($key, 0, 2) === '--') {
                            $args[] = $key; // Add the option name
                            
                            // Add the option value if it's not a boolean flag
                            if ($value !== true && $value !== '') {
                                if (is_array($value)) {
                                    // For array values, add each one separately
                                    foreach ($value as $val) {
                                        $args[] = $val;
                                    }
                                } else {
                                    $args[] = $value;
                                }
                            }
                            $this->logger->info('Added option: ' . $key);
                        }
                    }
                    
                    // We're not adding area by default anymore as we want to deploy all areas
                    // Check if area is specified in the command
                    $hasAreaOption = false;
                    foreach ($command as $key => $value) {
                        if (is_string($key) && $key === '--area') {
                            $hasAreaOption = true;
                            break;
                        }
                    }
                    
                    // Only add area if explicitly specified in the command
                    if ($hasAreaOption) {
                        foreach ($command as $key => $value) {
                            if (is_string($key) && $key === '--area') {
                                $args[] = '--area';
                                $args[] = $value;
                                $this->logger->info('Added area: ' . $value);
                                break;
                            }
                        }
                    }
                    
                    // We're not adding theme by default anymore as we want to deploy all themes
                    // Check if theme is specified in the command
                    $hasThemeOption = false;
                    foreach ($command as $key => $value) {
                        if (is_string($key) && $key === '--theme') {
                            $hasThemeOption = true;
                            break;
                        }
                    }
                    
                    // Only add theme if explicitly specified in the command
                    if ($hasThemeOption) {
                        foreach ($command as $key => $value) {
                            if (is_string($key) && $key === '--theme') {
                                $args[] = '--theme';
                                $args[] = $value;
                                $this->logger->info('Added theme: ' . $value);
                                break;
                            }
                        }
                    }
                    
                    // Log the command we're about to execute
                    $this->logger->info('Executing command: ' . implode(' ', $args));
                    $this->logger->info('Memory before command: ' . $this->formatBytes(memory_get_usage(true)));
                    $startTime = microtime(true);
                    
                    // Create a simple input/output for the command
                    $input = new \Symfony\Component\Console\Input\ArgvInput($args);
                    $input->setInteractive(false); // Ensure no interactive prompts
                    $output = new BufferedOutput();
                    
                    // Execute the command with detailed error handling
                    try {
                        // Run the command directly to avoid command name confusion
                        $command = $this->cliApplication->find($commandName);
                        $exitCode = $command->run($input, $output);
                        $outputContent = $output->fetch();
                        $endTime = microtime(true);
                        $this->logger->info('Command completed with exit code: ' . $exitCode);
                        $this->logger->info('Command execution time: ' . ($endTime - $startTime) . ' seconds');
                        $this->logger->info('Memory after command: ' . $this->formatBytes(memory_get_usage(true)));
                        $this->logger->info('Command output: ' . $outputContent);
                    } catch (\Exception $e) {
                        // Log the detailed exception
                        $this->logger->error('Exception during command execution: ' . $e->getMessage());
                        $this->logger->error('Exception trace: ' . $e->getTraceAsString());
                        throw $e; // Re-throw the exception
                    }
                    
                    // Only restore area if we changed it
                    if ($areaWasChanged && isset($currentArea)) {
                        $this->appState->setAreaCode($currentArea);
                    }
                    
                    if ($exitCode !== 0) {
                        $this->logger->error('Static content deployment failed with exit code: ' . $exitCode);
                        throw new \Exception('Static content deployment failed with code ' . $exitCode . ': ' . substr($outputContent, 0, 200));
                    }
                    
                    return $outputContent;
                } else {
                    // For other commands, we'll use a simpler approach
                    // Add all named parameters
                    foreach ($command as $key => $value) {
                        if (is_string($key) && substr($key, 0, 2) === '--') {
                            $paramName = substr($key, 2); // Remove the -- prefix
                            $inputParams[$paramName] = $value;
                        } elseif (is_string($value) && substr($value, 0, 2) === '--') {
                            // This is a flag parameter like --no-interaction
                            $paramName = substr($value, 2); // Remove the -- prefix
                            $inputParams[$paramName] = true;
                        }
                    }
                }
                
                // Create input and output objects
                $input = new ArrayInput($inputParams);
                $output = new BufferedOutput();
                
                // Run the command directly
                $exitCode = $this->cliApplication->run($input, $output);
                
                // Only restore area if we changed it
                if ($areaWasChanged && isset($currentArea)) {
                    $this->appState->setAreaCode($currentArea);
                }
                
                if ($exitCode !== 0) {
                    $this->logger->error('Command execution failed: ' . $output->fetch());
                    throw new \Exception('Command execution failed with code ' . $exitCode);
                }
                
                return $output->fetch();
            } catch (\Exception $e) {
                // Only restore area if we changed it
                if ($areaWasChanged && isset($currentArea)) {
                    $this->appState->setAreaCode($currentArea);
                }
                
                // Log the error for debugging
                $this->logger->error('Error executing command: ' . $e->getMessage());
                
                // Instead of using shell_exec, we'll try a different approach with the CLI application
                // Create a new instance of the CLI application if the current one failed
                try {
                    $cliApp = new \Magento\Framework\Console\Cli();
                    
                    // Reconstruct the command
                    $args = ['bin/magento', $commandName];
                    foreach ($command as $arg) {
                        $args[] = $arg;
                    }
                    
                    // Create input/output objects
                    $input = new \Symfony\Component\Console\Input\ArgvInput($args);
                    $input->setInteractive(false);
                    $output = new BufferedOutput();
                    
                    // Run the command
                    $exitCode = $cliApp->doRun($input, $output);
                    $outputContent = $output->fetch();
                    
                    if ($exitCode === 0) {
                        return $outputContent;
                    }
                    
                    $this->logger->info('Alternative CLI execution completed with exit code: ' . $exitCode);
                } catch (\Exception $innerEx) {
                    $this->logger->error('Alternative CLI execution failed: ' . $innerEx->getMessage());
                }
                
                throw new \Exception('Failed to execute Magento command: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            throw new \Exception('Error running Magento command: ' . $e->getMessage());
        }
    }
    
    /**
     * Format bytes to human-readable format
     *
     * @param int $bytes Number of bytes
     * @param int $precision Precision of formatting
     * @return string Formatted size
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Initialize the status file for live logging
     *
     * @param string $initialMessage
     * @return void
     */
    private function initializeStatusFile($initialMessage = '')
    {
        $statusData = [
            'status' => 'running',
            'message' => 'Initializing translation deployment',
            'output' => $initialMessage,
            'timestamp' => microtime(true),
            'execution_time' => 0
        ];
        
        $this->logger->info('Initializing status file with message: ' . $initialMessage);
        
        try {
            $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $varDir->writeFile(self::STATUS_FILE_PATH, json_encode($statusData));
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize status file: ' . $e->getMessage());
        }
    }
    
    /**
     * Update the status file with new information
     *
     * @param string $status
     * @param string $message
     * @param string $output
     * @param float $executionTime
     * @return void
     */
    private function updateStatusFile($status, $message, $output, $executionTime = 0)
    {
        $statusData = [
            'status' => $status,
            'message' => $message,
            'output' => $output,
            'timestamp' => microtime(true),
            'execution_time' => $executionTime
        ];
        
        try {
            $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $varDir->writeFile(self::STATUS_FILE_PATH, json_encode($statusData));
            $this->logger->info('Updated status file: status=' . $status . ', message=' . $message);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update status file: ' . $e->getMessage());
        }
    }
    
    /**
     * Get the current deployment status
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function getDeploymentStatus()
    {
        $result = $this->resultJsonFactory->create();
        $statusData = [
            'status' => 'unknown',
            'message' => 'Status information not available',
            'output' => ''
        ];
        
        try {
            $varDir = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
            if ($varDir->isExist(self::STATUS_FILE_PATH)) {
                $statusJson = $varDir->readFile(self::STATUS_FILE_PATH);
                $statusData = json_decode($statusJson, true) ?: $statusData;
                $this->logger->info('Retrieved status: ' . $statusData['status']);
            } else {
                $this->logger->warning('Status file does not exist');
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to read status file: ' . $e->getMessage());
            $statusData['message'] = 'Error reading status: ' . $e->getMessage();
        }
        
        return $result->setData($statusData);
    }
    
    /**
     * Run a Magento command with live output capture
     *
     * @param string|array $command
     * @return string
     * @throws \Exception
     */
    private function _runMagentoCommandWithLiveOutput($command)
    {
        $commandName = is_array($command) ? $command[0] : $command;
        $this->logger->info('Running Magento command with live output: ' . $commandName);
        
        // Store the current output
        $currentOutput = '';
        
        try {
            // Prepare command parameters
            $inputParams = ['command' => $commandName];
            
            // Add additional parameters if command is an array
            if (is_array($command) && count($command) > 1) {
                for ($i = 1; $i < count($command); $i++) {
                    $param = $command[$i];
                    if (is_string($param) && strpos($param, '--') === 0) {
                        // Handle flag parameters (--no-interaction)
                        $paramName = substr($param, 2);
                        $inputParams[$paramName] = true;
                    } elseif (isset($command[$i - 1]) && is_string($command[$i - 1]) && strpos($command[$i - 1], '--') === 0) {
                        // Handle value parameters (--theme=Magento/luma)
                        $paramName = substr($command[$i - 1], 2);
                        $inputParams[$paramName] = $param;
                    }
                }
            }
            
            // Create input and output objects
            $input = new ArrayInput($inputParams);
            $output = new BufferedOutput();
            
            // Check if we need to change the area code
            $areaWasChanged = false;
            $currentArea = null;
            
            try {
                $currentArea = $this->appState->getAreaCode();
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $areaWasChanged = true;
                $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);
            }
            
            // Run the command with progress updates
            $startTime = microtime(true);
            $exitCode = $this->cliApplication->run($input, $output);
            $endTime = microtime(true);
            
            // Get the output
            $currentOutput = $output->fetch();
            
            // Update the status file with the current output
            $this->updateStatusFile('running', 'Command running: ' . $commandName, $currentOutput);
            
            // Only restore area if we changed it
            if ($areaWasChanged && isset($currentArea)) {
                $this->appState->setAreaCode($currentArea);
            }
            
            if ($exitCode !== 0) {
                $this->logger->error('Command execution failed with exit code: ' . $exitCode);
                $this->updateStatusFile('error', 'Command failed: ' . $commandName, $currentOutput);
                throw new \Exception('Command execution failed with code ' . $exitCode);
            }
            
            $this->logger->info('Command completed successfully in ' . ($endTime - $startTime) . ' seconds');
            return $currentOutput;
            
        } catch (\Exception $e) {
            $this->logger->error('Error executing command with live output: ' . $e->getMessage());
            $this->updateStatusFile('error', 'Error: ' . $e->getMessage(), $currentOutput);
            throw new \Exception('Error running Magento command: ' . $e->getMessage());
        }
    }
}
