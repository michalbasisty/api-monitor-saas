<?php

namespace App\Exception;

/**
 * Exception thrown when a store is not found
 */
class StoreNotFoundException extends EcommerceException
{
    public function __construct(string $storeId)
    {
        parent::__construct(
            'STORE_NOT_FOUND',
            "Store with ID '{$storeId}' not found",
            404
        );
    }
}
