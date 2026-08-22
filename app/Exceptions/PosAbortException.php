<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class PosAbortException extends HttpException
{
    public function __construct(
        int $statusCode,
        string $message = '',
        public readonly ?string $title = null,
        public readonly bool $showCta = true,
        array $headers = [],
    ) {
        parent::__construct($statusCode, $message, null, $headers);
    }
}
