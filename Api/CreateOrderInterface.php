<?php

/**
 *
 * @category  Custom Development
 * @email     contactus@learningmagento.com
 * @author    Avesh Naik
 * @website   learningmagento.com
 * @Date      03-04-2024
 */

namespace Learningmagento\Ordergeneration\Api;

/**
 * Interface to generate an order
 */
interface CreateOrderInterface
{
    /**
     * This method generate a fresh order
     *
     * @param array $orderData
     * @return mixed
     */
    public function generateOrder($orderData);
}
