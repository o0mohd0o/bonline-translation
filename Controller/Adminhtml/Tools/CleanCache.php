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
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Magento\Framework\App\State;
use Psr\Log\LoggerInterface;

class CleanCache extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var Filesystem
     */
    protected $filesystem;
    
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
     * @param TypeListInterface $cacheTypeList
     * @param Filesystem $filesystem
     * @param Cli $cliApplication
     * @param State $appState
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        TypeListInterface $cacheTypeList,
        Filesystem $filesystem,
        Cli $cliApplication,
        State $appState,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cacheTypeList = $cacheTypeList;
        $this->filesystem = $filesystem;
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
     * Execute clean cache command
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $success = false;
        $message = '';

        try {
            // Set area code to avoid "Area code is not set" error
            try {
                $this->appState->getAreaCode();
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
            }
            
            $this->logger->info('Starting cache cleaning process');
            
            // Clean cache using Magento CLI commands
            $cacheTypes = [
                'config',
                'layout',
                'block_html',
                'collections',
                'reflection',
                'db_ddl',
                'compiled_config',
                'eav',
                'config_integration',
                'config_integration_api',
                'full_page',
                'translate'
            ];
            
            // Run cache:clean command
            $this->logger->info('Running cache:clean command for types: ' . implode(', ', $cacheTypes));
            $cleanResult = $this->runMagentoCommand('cache:clean', $cacheTypes);
            $this->logger->info('Cache clean result: ' . $cleanResult);
            
            // Clean generated code and static content
            $this->logger->info('Cleaning generated code');
            $cleanCodeResult = $this->runMagentoCommand('setup:di:compile:status', []);
            $this->logger->info('Generated code status: ' . $cleanCodeResult);
            
            // Clean static content
            $this->logger->info('Cleaning static content');
            $cleanStaticResult = $this->runMagentoCommand('setup:static-content:clean', []);
            $this->logger->info('Static content clean result: ' . $cleanStaticResult);

            $success = true;
            $message = __('Translation cache cleaned successfully');
        } catch (\Exception $e) {
            $message = __('Error cleaning cache: %1', $e->getMessage());
        }

        return $result->setData([
            'success' => $success,
            'message' => $message
        ]);
    }
    
    /**
     * Run a Magento CLI command
     * 
     * @param string $commandName Command name
     * @param array $arguments Command arguments
     * @return string Output from command
     * @throws \Exception
     */
    protected function runMagentoCommand($commandName, array $arguments = [])
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
            
            // Create input parameters
            $inputParams = ['command' => $commandName];
            
            // Add arguments
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
            $this->logger->error('Error executing command: ' . $e->getMessage());
            throw new \Exception('Failed to execute Magento command: ' . $e->getMessage());
        }
    }
}
