<?php
/**
 * Bonline Translation Module
 *
 * @category  Bonline
 * @package   Bonlineco_Translation
 */

namespace Bonlineco\Translation\Model;

use Magento\Framework\Model\AbstractModel;
use Bonlineco\Translation\Model\ResourceModel\Translation as ResourceModel;

class Translation extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'bonlineco_translation';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }
}
