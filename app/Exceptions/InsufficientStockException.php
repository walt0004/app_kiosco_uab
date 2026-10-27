<?php

namespace App\Exceptions;

use App\Models\Product;
use DomainException;

class InsufficientStockException extends DomainException
{
    public function __construct(
        public readonly Product $product,
        public readonly float $requested,
        public readonly float $available,
    ) {
        parent::__construct(sprintf(
            'Stock insuficiente de "%s": disponible %s, solicitado %s.',
            $product->name,
            rtrim(rtrim(number_format($available, 3, ',', '.'), '0'), ','),
            rtrim(rtrim(number_format($requested, 3, ',', '.'), '0'), ','),
        ));
    }
}
