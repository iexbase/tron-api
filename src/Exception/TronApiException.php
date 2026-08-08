<?php

declare(strict_types=1);

/**
 * TronAPI 6.0
 *
 * Copyright (c) 2018-2026 iEXBase.
 *
 * @author  Shamsudin Serderov <steein.shamsudin@gmail.com>
 * @license https://github.com/iexbase/tron-api/blob/master/LICENSE MIT License
 * @link    https://github.com/iexbase/tron-api
 */

namespace IEXBase\TronAPI\Exception;

use RuntimeException;

/**
 * Provides the common base type for every exception raised by TronAPI.
 *
 * Consumers can catch this type at an application boundary while still using
 * the more specific subclasses when recovery depends on the failure category.
 */
class TronApiException extends RuntimeException
{
}
