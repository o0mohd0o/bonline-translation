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
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Magento\Framework\App\State;
use Psr\Log\LoggerInterface;

class DeployStatic extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * @var Cli
     */
    protected $cliApplication;
    
    /**
     * @var State
     */
    protected $appState;
    
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     * @param Cli $cliApplication
     * @param State $appState
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager,
        Cli $cliApplication,
        State $appState,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
        $this->cliApplication = $cliApplication;
        $this->appState = $appState;
        $this->logger = $logger;
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
     * Execute static content deployment command
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $success = false;
        $message = '';
        $output = '';

        try {
            $this->logger->info('Starting static content deployment process');
            
            $languages = $this->getRequest()->getParam('languages', []);
            $this->logger->info('Requested languages: ' . ($languages ? implode(', ', $languages) : 'none'));
            
            if (empty($languages)) {
                $this->logger->info('No languages specified, retrieving all store languages');
                // Get all languages if none specified
                foreach ($this->storeManager->getStores() as $store) {
                    $locale = $store->getConfig('general/locale/code');
                    $languages[] = $locale;
                    $this->logger->info('Added store locale: ' . $locale . ' from store ID: ' . $store->getId());
                }
                $languages = array_unique($languages);
                $this->logger->info('Final unique languages list: ' . implode(', ', $languages));
            }

            if (!empty($languages)) {
                $this->logger->info('Starting deployment for ' . count($languages) . ' languages');
                
                // Set area code to avoid "Area code is not set" error
                try {
                    $currentArea = $this->appState->getAreaCode();
                    $this->logger->info('Current area code: ' . $currentArea);
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->logger->info('Area code not set, setting to adminhtml');
                    $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
                }
                
                $this->logger->info('Starting static content deployment for languages: ' . implode(', ', $languages));
                $startTime = microtime(true);
                
                // Prepare command arguments
                $command = ['setup:static-content:deploy'];
                
                // Add each language as a separate argument
                foreach ($languages as $language) {
                    $command[] = $language;
                }
                
                // Add force flag
                $command[] = '-f';
                
                try {
                    $this->logger->info('Executing static content deployment command with force flag');
                    // Execute the command using Magento CLI
                    $output = $this->runMagentoCommand('setup:static-content:deploy', $languages, true);
                    $executionTime = microtime(true) - $startTime;
                    
                    $this->logger->info('Static content deployment completed in ' . round($executionTime, 2) . ' seconds');
                    $this->logger->info('Command output length: ' . strlen($output) . ' characters');
                    $this->logger->info('Command output preview: ' . substr($output, 0, 500) . '...');
                    
                    // Log memory usage after deployment
                    $memoryUsage = memory_get_usage(true);
                    $this->logger->info('Memory usage after deployment: ' . $this->formatBytes($memoryUsage));
                    
                    $success = true;
                    $message = __('Static content deployed successfully for languages: %1', implode(', ', $languages));
                    $message .= ' ' . __('(Execution time: %1 seconds)', round($executionTime, 2));
                    $this->logger->info('Success message: ' . $message);
                } catch (\Exception $e) {
                    $this->logger->error('Error during static content deployment: ' . $e->getMessage());
                    $this->logger->error('Exception trace: ' . $e->getTraceAsString());
                    throw $e; // Re-throw to be caught by outer try-catch
                }
            } else {
                $message = __('No languages selected for deployment');
                $this->logger->warning('No languages selected for deployment');
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception in static content deployment: ' . $e->getMessage());
            $this->logger->error('Exception class: ' . get_class($e));
            $this->logger->error('Exception trace: ' . $e->getTraceAsString());
            $message = __('Error deploying static content: %1', $e->getMessage());
            $output = $e->getMessage();
        }
        
        $this->logger->info('Deployment process finished. Success: ' . ($success ? 'Yes' : 'No'));
        $this->logger->info('Response message: ' . $message);

        // Log the response data
        $responseData = [
            'success' => $success,
            'message' => $message,
            'output_length' => strlen($output)
        ];
        $this->logger->info('Sending response: ' . json_encode($responseData));
        
        return $result->setData([
            'success' => $success,
            'message' => $message,
            'output' => $output
        ]);
    }
    
    /**
     * Run a Magento CLI command
     * 
     * @param string $commandName Command name
     * @param array $arguments Command arguments
     * @param bool $addForceFlag Whether to add force flag
     * @return string Output from command
     * @throws \Exception
     */
    protected function runMagentoCommand($commandName, array $arguments = [], $addForceFlag = false)
    {
        try {
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
            
            // For static content deployment, we need special handling
            if ($commandName === 'setup:static-content:deploy') {
                $this->logger->info('Executing static content deployment with force flag');
                
                // Start with a basic command array - first element must be the script name
                $args = ['bin/magento', $commandName];
                
                // Add all positional arguments
                foreach ($arguments as $arg) {
                    $args[] = $arg;
                    $this->logger->info('Added positional argument: ' . $arg);
                }
                
                // Add force flag if requested
                if ($addForceFlag) {
                    $args[] = '-f';
                    $this->logger->info('Added force flag');
                }
                
                // Log the command we're about to execute
                $this->logger->info('Executing command: ' . implode(' ', $args));
                $this->logger->info('Memory before command: ' . $this->formatBytes(memory_get_usage(true)));
                
                // Create input/output objects
                $input = new \Symfony\Component\Console\Input\ArgvInput($args);
                $input->setInteractive(false); // Ensure no interactive prompts
                $output = new BufferedOutput();
                
                // Execute the command
                $command = $this->cliApplication->find($commandName);
                $exitCode = $command->run($input, $output);
                $outputContent = $output->fetch();
                
                $this->logger->info('Command completed with exit code: ' . $exitCode);
                $this->logger->info('Memory after command: ' . $this->formatBytes(memory_get_usage(true)));
                
                // Only restore area if we changed it
                if ($areaWasChanged && isset($currentArea)) {
                    $this->appState->setAreaCode($currentArea);
                }
                
                if ($exitCode !== 0) {
                    $this->logger->error('Command execution failed: ' . $outputContent);
                    throw new \Exception('Command execution failed with code ' . $exitCode);
                }
                
                return $outputContent;
            } else {
                // For other commands, use a simpler approach
                $inputParams = ['command' => $commandName];
                
                // Add all arguments
                foreach ($arguments as $key => $value) {
                    if (is_string($key)) {
                        // Named argument
                        $inputParams[$key] = $value;
                    } else {
                        // Positional argument
                        $inputParams[] = $value;
                    }
                }
                
                // Create input and output objects
                $input = new ArrayInput($inputParams);
                $input->setInteractive(false); // Ensure no interactive prompts
                $output = new BufferedOutput();
                
                // Run the command
                $exitCode = $this->cliApplication->run($input, $output);
                $outputContent = $output->fetch();
                
                // Only restore area if we changed it
                if ($areaWasChanged && isset($currentArea)) {
                    $this->appState->setAreaCode($currentArea);
                }
                
                if ($exitCode !== 0) {
                    $this->logger->error('Command execution failed: ' . $outputContent);
                    throw new \Exception('Command execution failed with code ' . $exitCode);
                }
                
                return $outputContent;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error executing command: ' . $e->getMessage());
            throw new \Exception('Failed to execute Magento command: ' . $e->getMessage());
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
}
