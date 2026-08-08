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

namespace IEXBase\TronAPI\Asset;

use DateTimeImmutable;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Support\IntegerHelper;
use IEXBase\TronAPI\Support\TextHelper;
use IEXBase\TronAPI\Value\Amount;

/**
 * Captures all user-approved fields for one native TRC-10 issuance.
 */
final readonly class AssetIssuanceRequest
{
    /** @var list<FrozenSupply> */
    private array $frozenSupplies;

    /**
     * Validates metadata, supply, sale ratio, schedule, quotas, and locks.
     *
     * @param list<FrozenSupply> $frozenSupplies Optional time-locked supply portions.
     */
    public function __construct(
        public string $name,
        public string $symbol,
        public Amount $totalSupply,
        public int $trxRatio,
        public int $tokenRatio,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $description,
        public string $url,
        public int $freeAssetNetLimit = 0,
        public int $publicFreeAssetNetLimit = 0,
        array $frozenSupplies = [],
    ) {
        TextHelper::utf8($name, 'TRC-10 name', 32);
        TextHelper::utf8($symbol, 'TRC-10 symbol', 16);
        TextHelper::utf8($description, 'TRC-10 description', 200, true);
        TextHelper::webUrl($url, 'TRC-10 URL');
        if ($totalSupply->isZero() || $totalSupply->decimals() > 6) {
            throw new ValidationException('TRC-10 total supply must be positive and use no more than six decimals.');
        }
        IntegerHelper::positive($trxRatio, 'TRC-10 TRX ratio');
        IntegerHelper::positive($tokenRatio, 'TRC-10 token ratio');
        IntegerHelper::nonNegative($freeAssetNetLimit, 'TRC-10 free asset bandwidth limit');
        IntegerHelper::nonNegative($publicFreeAssetNetLimit, 'TRC-10 public bandwidth limit');
        if ($endsAt <= $startsAt) {
            throw new ValidationException('A TRC-10 issuance end time must be later than its start time.');
        }

        $frozenTotal = Amount::fromAtomic(0, $totalSupply->decimals());
        foreach ($frozenSupplies as $supply) {
            if ($supply->amount->decimals() !== $totalSupply->decimals()) {
                throw new ValidationException('Frozen TRC-10 supply must use the issuance precision.');
            }
            $frozenTotal = $frozenTotal->add($supply->amount);
        }
        if ($frozenTotal->compare($totalSupply) > 0) {
            throw new ValidationException('Frozen TRC-10 supply cannot exceed total supply.');
        }

        $this->frozenSupplies = $frozenSupplies;
    }

    /**
     * Returns the exact fields shared by the request and verified transaction intent.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = [
            'name' => $this->name,
            'abbr' => $this->symbol,
            'total_supply' => $this->totalSupply->atomicInteger(),
            'trx_num' => $this->trxRatio,
            'num' => $this->tokenRatio,
            'precision' => $this->totalSupply->decimals(),
            'start_time' => (int) $this->startsAt->format('Uv'),
            'end_time' => (int) $this->endsAt->format('Uv'),
            'description' => $this->description,
            'url' => $this->url,
            'free_asset_net_limit' => $this->freeAssetNetLimit,
            'public_free_asset_net_limit' => $this->publicFreeAssetNetLimit,
        ];
        if ($this->frozenSupplies !== []) {
            $fields['frozen_supply'] = array_map(
                static fn (FrozenSupply $supply): array => $supply->toNodeData(),
                $this->frozenSupplies,
            );
        }

        return $fields;
    }

    /**
     * Returns the configured frozen supply portions.
     *
     * @return list<FrozenSupply>
     */
    public function frozenSupplies(): array
    {
        return $this->frozenSupplies;
    }
}
