<?php

/**
 *
 * @category  Custom Development
 * @email     contactus@learningmagento.com
 * @author    Avesh Naik
 * @website   learningmagento.com
 * @Date      06-04-2024
 */

namespace Learningmagento\Ordergeneration\Model\Config;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class Stores extends AbstractSource
{
    protected $storeRepository;

    public function __construct(
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository
    ) {
        $this->storeRepository = $storeRepository;
    }

    /**
     * Retrieve All options
     *
     * @return array
     */
    public function getAllOptions()
    {
        $stores = $this->storeRepository ->getList();
        $storeList = [];
        foreach ($stores as $store) {
            $storeList[] = [
                'label' => $store->getName(),
                'value' => $store->getId()
            ];
        }
        return $storeList;
    }
}
