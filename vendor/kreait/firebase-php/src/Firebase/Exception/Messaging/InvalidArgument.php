<?php

declare(strict_types=1);

namespace Kreait\Firebase\Exception\Messaging;

use InvalidArgumentException;
use Kreait\Firebase\Exception\HasRequestAndResponse;
use Kreait\Firebase\Exception\MessagingException;

final class InvalidArgument extends InvalidArgumentException implements MessagingException
{
    use HasRequestAndResponse;

    public function errors(): array
    {
        return [];
    }
}
