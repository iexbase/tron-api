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

namespace IEXBase\TronAPI\Support;

use IEXBase\TronAPI\Enum\ResourceType;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Value\Amount;

/**
 * Shares exact amount and protobuf-default rules across Stake 1.0 and 2.0 services.
 */
final class StakeHelper
{
    /**
     * Returns a positive native TRX amount denominated in sun.
     */
    public static function positiveSun(Amount $amount): int
    {
        if ($amount->decimals() !== Amount::TRX_DECIMALS || $amount->isZero()) {
            throw new ValidationException('Staking operations require a positive amount with six TRX decimals.');
        }

        return $amount->atomicInteger();
    }

    /**
     * Returns the Bandwidth enum field that protobuf may omit as its default.
     *
     * @return list<string>
     */
    public static function omittableResourceField(ResourceType $resource): array
    {
        return $resource === ResourceType::Bandwidth ? ['resource'] : [];
    }

    /**
     * Rejects TRON Power where a protocol operation supports only resources.
     */
    public static function requireBandwidthOrEnergy(ResourceType $resource, string $operation): void
    {
        if ($resource === ResourceType::TronPower) {
            throw new ValidationException(sprintf('%s supports only Bandwidth or Energy.', $operation));
        }
    }
}
