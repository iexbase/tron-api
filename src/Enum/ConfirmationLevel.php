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
 * Selects latest FullNode state or irreversible SolidityNode state explicitly.
 */
enum ConfirmationLevel: string
{
    case Latest = 'latest';
    case Confirmed = 'confirmed';

    /**
     * Returns whether this level reads irreversible SolidityNode state.
     */
    public function isConfirmed(): bool
    {
        return $this === self::Confirmed;
    }
}
