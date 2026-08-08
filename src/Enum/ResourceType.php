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
 * Enumerates Stake 2.0 resources, including migrated TRON Power unstaking.
 */
enum ResourceType: string
{
    case Bandwidth = 'BANDWIDTH';
    case Energy = 'ENERGY';
    case TronPower = 'TRON_POWER';

    /**
     * Returns the protobuf enum number used by Stake 2.0 query endpoints.
     */
    public function code(): int
    {
        return match ($this) {
            self::Bandwidth => 0,
            self::Energy => 1,
            self::TronPower => 2,
        };
    }
}
