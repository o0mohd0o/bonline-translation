<?php
/**
 * Bonline Translation Module
 *
 * @category  Bonline
 * @package   Bonlineco_Translation
 */

namespace Bonlineco\Translation\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Model\ResourceModel\Db\Context;

class Translation extends AbstractDb
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @param Context $context
     * @param ResourceConnection $resourceConnection
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('translation', 'key_id');
    }

    /**
     * Get translations from translation table
     *
     * @param string $string
     * @param string $locale
     * @return string|null
     */
    public function getTranslation($string, $locale)
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('translation'), ['translate'])
            ->where('string = ?', $string)
            ->where('locale = ?', $locale);

        return $connection->fetchOne($select);
    }

    /**
     * Save translation
     *
     * @param string $string
     * @param string $translate
     * @param string $locale
     * @return bool
     */
    public function saveTranslation($string, $translate, $locale)
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('translation');

        $select = $connection->select()
            ->from($tableName, ['key_id'])
            ->where('string = ?', $string)
            ->where('locale = ?', $locale);

        $keyId = $connection->fetchOne($select);

        if ($keyId) {
            $connection->update(
                $tableName,
                ['translate' => $translate],
                ['key_id = ?' => $keyId]
            );
        } else {
            $connection->insert(
                $tableName,
                [
                    'string' => $string,
                    'translate' => $translate,
                    'locale' => $locale
                ]
            );
        }

        return true;
    }
}
