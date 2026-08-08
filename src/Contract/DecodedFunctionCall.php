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

namespace IEXBase\TronAPI\Contract;

use IEXBase\TronAPI\Value\ByteString;
use JsonSerializable;

/**
 * Represents an ABI-resolved function selector and its decoded call arguments.
 */
final readonly class DecodedFunctionCall implements JsonSerializable
{
    /**
     * Stores the resolved function, ordered arguments, and complete call bytes.
     */
    public function __construct(
        public AbiEntry $function,
        public DecodedValues $arguments,
        public ByteString $data,
    ) {
    }

    /**
     * Returns the canonical function signature selected by the first four bytes.
     */
    public function signature(): string
    {
        return $this->function->signature();
    }

    /**
     * Returns one decoded argument by position or unique ABI parameter name.
     */
    public function argument(int|string $positionOrName): mixed
    {
        return $this->arguments->value($positionOrName);
    }

    /**
     * Serializes the resolved signature, decoded arguments, and original call data.
     *
     * @return array{signature: string, arguments: DecodedValues, data: ByteString}
     */
    public function jsonSerialize(): array
    {
        return [
            'signature' => $this->signature(),
            'arguments' => $this->arguments,
            'data' => $this->data,
        ];
    }
}
