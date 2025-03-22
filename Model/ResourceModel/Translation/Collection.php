<?php
/**
 * Bonline Translation Module
 *
 * @category  Bonline
 * @package   Bonlineco_Translation
 */

namespace Bonlineco\Translation\Model\ResourceModel\Translation;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Bonlineco\Translation\Model\Translation as Model;
use Bonlineco\Translation\Model\ResourceModel\Translation as ResourceModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'key_id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
