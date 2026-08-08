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

namespace IEXBase\TronAPI\Http;

/**
 * Defines the only operation required from HTTP transports and test doubles.
 */
interface TransportInterface
{
    /**
     * Sends one absolute request and returns its unparsed HTTP response.
     */
    public function send(HttpRequest $request): HttpResponse;
}
