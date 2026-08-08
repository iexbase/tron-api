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

namespace IEXBase\TronAPI\Enum;

/**
 * Enumerates the public TRON networks supported by official hosted endpoints.
 */
enum Network: string
{
    case Mainnet = 'mainnet';
    case Shasta = 'shasta';
    case Nile = 'nile';
    case Custom = 'custom';

    /**
     * Returns the official hosted HTTP base URI for this public network.
     *
     * @throws \LogicException When called for a custom network.
     */
    public function defaultHttpUri(): string
    {
        return match ($this) {
            self::Mainnet => 'https://api.trongrid.io',
            self::Shasta => 'https://api.shasta.trongrid.io',
            self::Nile => 'https://nile.trongrid.io',
            self::Custom => throw new \LogicException('A custom network has no default HTTP URI.'),
        };
    }
}
