<?php

namespace Picqer\BolRetailerV8\Model;

// This class is auto generated by OpenApi\ModelGenerator
class BundlePrice extends AbstractModel
{
    /**
     * Returns the definition of the model: an associative array with field names as key and
     * field definition as value. The field definition contains of
     * model: Model class or null if it is a scalar type
     * array: Boolean whether it is an array
     * @return array The model definition
     */
    public function getModelDefinition(): array
    {
        return [
            'quantity' => [ 'model' => null, 'array' => false ],
            'unitPrice' => [ 'model' => null, 'array' => false ],
        ];
    }

    /**
     * @var int The minimum quantity a customer must order in order to receive discount. The element with value 1 must
     * at least be present. In case of using more elements, the respective quantities must be in increasing order.
     */
    public $quantity;

    /**
     * @var float The price per single unit including VAT in case the customer orders at least the quantity provided.
     * When using more than 1 price, the respective prices must be in decreasing order using 2 decimal precision and dot
     * separated.
     */
    public $unitPrice;
}
