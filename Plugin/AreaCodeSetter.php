<?php
/**
 * Bonline Translation Module
 *
 * @category  Bonline
 * @package   Bonlineco_Translation
 */

namespace Bonlineco\Translation\Plugin;

use Magento\Framework\App\State;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;

class AreaCodeSetter
{
    /**
     * @var State
     */
    private $appState;

    /**
     * @param State $appState
     */
    public function __construct(
        State $appState
    ) {
        $this->appState = $appState;
    }

    /**
     * Set area code before controller execution
     *
     * @param ActionInterface $subject
     * @param RequestInterface $request
     * @return array
     */
    public function beforeDispatch(
        ActionInterface $subject,
        RequestInterface $request
    ) {
        try {
            $this->appState->getAreaCode();
        } catch (LocalizedException $e) {
            // If area code is not set, set it to adminhtml for admin controllers
            if (strpos(get_class($subject), '\Adminhtml\\') !== false) {
                $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
            } else {
                $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);
            }
        }
        
        return [$request];
    }
}
