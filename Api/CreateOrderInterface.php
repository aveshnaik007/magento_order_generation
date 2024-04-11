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

interface CreateOrderInterface
{
    public function generateOrder($orderData);
}
