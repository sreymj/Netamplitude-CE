<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Core
 * @copyright   Copyright (c) 2010 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Url rewrite resource model class
 *
 *
 * @category   Mage
 * @package    Mage_Core
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Mage_Core_Model_Mysql4_Url_Rewrite extends Mage_Core_Model_Mysql4_Abstract
{
    protected $_tagTable;

    protected function _construct()
    {
        $this->_init('core/url_rewrite', 'url_rewrite_id');
        $this->_tagTable = $this->getTable('url_rewrite_tag');
    }

    /**
     * Initialize unique fields
     *
     * @return Mage_Core_Model_Mysql4_Abstract
     */
    protected function _initUniqueFields()
    {
        $this->_uniqueFields = array(
            array(
                'field' => array('id_path','store_id','is_system'),
                'title' => Mage::helper('core')->__('ID Path for Specified Store')
            ),
            array(
                 'field' => array('request_path','store_id'),
                 'title' => Mage::helper('core')->__('Request Path for Specified Store'),
            )
        );
        return $this;
    }

    /**
     * Retrieve select object for load object data
     *
     * @param string $field
     * @param mixed $value
     * @return Zend_Db_Select
     */
    protected function _getLoadSelect($field, $value, $object)
    {
        /* @var $select Varien_Db_Select */
        $select = parent::_getLoadSelect($field, $value, $object);

        if (!is_null($object->getStoreId())) {
            $select->where('store_id IN(?)', array(0, $object->getStoreId()));
            $select->order('store_id desc');
            $select->limit(1);
        }

        return $select;
    }

    /**
     * Retrieve request_path using id_path and current store's id.
     *
     * @param string $idPath
     * @param int|Mage_Core_Model_Store $store
     * @return string|false
     */
    public function getRequestPathByIdPath($idPath, $store)
    {
        if ($store instanceof Mage_Core_Model_Store) {
            $storeId = (int)$store->getId();
        } else {
            $storeId = (int)$store;
        }

        $select = $this->_getReadAdapter()->select();
        /* @var $select Zend_Db_Select */
        $select->from(array('main_table' => $this->getMainTable()), 'request_path')
            ->where('main_table.store_id = ?', $storeId)
            ->where('main_table.id_path = ?', $idPath)
            ->limit(1);

        return $this->_getReadAdapter()->fetchOne($select);
    }

    /**
     * Load rewrite information for request
     * If $path is array - we must load all possible records and choose one matching earlier record in array
     *
     * @param   Mage_Core_Model_Url_Rewrite $object
     * @param   array|string $path
     * @return  Mage_Core_Model_Mysql4_Url_Rewrite
     */
    public function loadByRequestPath(Mage_Core_Model_Url_Rewrite $object, $path)
    {
        if (!is_array($path)) {
            $path = array($path);
        }

        // Form select
        $read = $this->_getReadAdapter();
        $select = $read->select()
            ->from($this->getMainTable())
            ->where($this->getMainTable() . '.request_path IN (?)', $path)
            ->where('store_id IN(?)', array(0, $object->getStoreId()));

        $items = $read->fetchAll($select);

        // Go through all found records and choose one with lowest penalty - earlier path in array, concrete store
        $mapPenalty = array_flip(array_values($path)); // we got mapping array(path => index), lower index - better
        $currentPenalty = null;
        $foundItem = null;
        foreach ($items as $item) {
            $penalty = $mapPenalty[$item['request_path']] << 1 + ($item['store_id'] ? 0 : 1);
            if (!$foundItem || $currentPenalty > $penalty) {
                $foundItem = $item;
                $currentPenalty = $penalty;
                if (!$currentPenalty) {
                    break; // Found best matching item with zero penalty, no reason to continue
                }
            }
        }

        // Set data and finish loading
        if ($foundItem) {
            $object->setData($foundItem);
        }

        // Finish
        $this->unserializeFields($object);
        $this->_afterLoad($object);

        return $this;
    }

}