<?php
/**
 * Copyright 2019 Colopl Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Colopl\Spanner\Concerns;

use Colopl\Spanner\TimestampBound\TimestampBoundInterface;
use Generator;

/**
 * @deprecated This trait will be removed in v7.
 */
trait ManagesStaleReads
{
    /**
     * @deprecated use cursorWithOptions() instead. This method will be removed in v7.
     * @param string $query
     * @param array<array-key, mixed> $bindings
     * @param TimestampBoundInterface|null $timestampBound
     * @return Generator<int, array<array-key, mixed>>
     */
    public function cursorWithTimestampBound($query, $bindings = [], TimestampBoundInterface $timestampBound = null): Generator
    {
        return $this->cursorWithOptions($query, $bindings, $timestampBound?->transactionOptions() ?? []);
    }

    /**
     * @deprecated use selectWithOptions() instead. This method will be removed in v7.
     * @param string $query
     * @param array<array-key, mixed> $bindings
     * @param TimestampBoundInterface|null $timestampBound
     * @return list<array<array-key, mixed>|null>
     */
    public function selectWithTimestampBound($query, $bindings = [], TimestampBoundInterface $timestampBound = null): array
    {
        return $this->selectWithOptions($query, $bindings, $timestampBound?->transactionOptions() ?? []);
    }

    /**
     * @deprecated use selectWithOptions() instead. This method will be removed in v7.
     * @param string $query
     * @param array<array-key, mixed> $bindings
     * @param TimestampBoundInterface|null $timestampBound
     * @return array<array-key, mixed>|null
     */
    public function selectOneWithTimestampBound($query, $bindings = [], TimestampBoundInterface $timestampBound = null): ?array
    {
        return $this->selectWithTimestampBound($query, $bindings, $timestampBound)[0] ?? null;
    }
}

