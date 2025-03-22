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
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
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
     * Index action
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Bonlineco_Translation::tools');
        $resultPage->addBreadcrumb(__('Translation'), __('Translation'));
        $resultPage->addBreadcrumb(__('Translation Tools'), __('Translation Tools'));
        $resultPage->getConfig()->getTitle()->prepend(__('Translation Tools'));

        return $resultPage;
    }
}
