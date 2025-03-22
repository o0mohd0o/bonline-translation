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
use Magento\Framework\Shell;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

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
     * @var Shell
     */
    protected $shell;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param TypeListInterface $cacheTypeList
     * @param Shell $shell
     * @param Filesystem $filesystem
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        TypeListInterface $cacheTypeList,
        Shell $shell,
        Filesystem $filesystem
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cacheTypeList = $cacheTypeList;
        $this->shell = $shell;
        $this->filesystem = $filesystem;
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
            // Clean generated code directories
            $rootDir = $this->filesystem->getDirectoryRead(DirectoryList::ROOT)->getAbsolutePath();
            $command = "rm -frv " . $rootDir . "var/cache " . $rootDir . "var/page_cache " . $rootDir . "generated " . $rootDir . "pub/static/frontend";
            $this->shell->execute($command);

            // Clean cache through Magento
            $this->cacheTypeList->cleanType('config');
            $this->cacheTypeList->cleanType('layout');
            $this->cacheTypeList->cleanType('block_html');
            $this->cacheTypeList->cleanType('collections');
            $this->cacheTypeList->cleanType('reflection');
            $this->cacheTypeList->cleanType('db_ddl');
            $this->cacheTypeList->cleanType('compiled_config');
            $this->cacheTypeList->cleanType('eav');
            $this->cacheTypeList->cleanType('config_integration');
            $this->cacheTypeList->cleanType('config_integration_api');
            $this->cacheTypeList->cleanType('full_page');
            $this->cacheTypeList->cleanType('translate');

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
}
