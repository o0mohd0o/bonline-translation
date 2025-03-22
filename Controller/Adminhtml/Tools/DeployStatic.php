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
use Magento\Framework\Shell;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;

class DeployStatic extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Shell
     */
    protected $shell;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Shell $shell
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Shell $shell,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->shell = $shell;
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
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
            $languages = $this->getRequest()->getParam('languages', []);
            if (empty($languages)) {
                // Get all languages if none specified
                foreach ($this->storeManager->getStores() as $store) {
                    $languages[] = $store->getConfig('general/locale/code');
                }
                $languages = array_unique($languages);
            }

            if (!empty($languages)) {
                $rootDir = $this->filesystem->getDirectoryRead(DirectoryList::ROOT)->getAbsolutePath();
                $languageParams = implode(' ', $languages);
                $command = "php " . $rootDir . "bin/magento setup:static-content:deploy " . $languageParams . " -f";
                $output = $this->shell->execute($command);
                $success = true;
                $message = __('Static content deployed successfully for languages: %1', implode(', ', $languages));
            } else {
                $message = __('No languages selected for deployment');
            }
        } catch (\Exception $e) {
            $message = __('Error deploying static content: %1', $e->getMessage());
            $output = $e->getMessage();
        }

        return $result->setData([
            'success' => $success,
            'message' => $message,
            'output' => $output
        ]);
    }
}
