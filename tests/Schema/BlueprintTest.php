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

namespace Colopl\Spanner\Tests\Schema;

use Colopl\Spanner\Schema\Blueprint;
use Colopl\Spanner\Schema\Grammar;
use Colopl\Spanner\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Ramsey\Uuid\Uuid;

class BlueprintTest extends TestCase
{
    public function test_create_with_all_valid_types(): void
    {
        $conn = $this->getDefaultConnection();
        $tableName = $this->generateTableName();
        $blueprint = new Blueprint($tableName, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('int');
            $table->float('float');
            $table->decimal('decimal');
            $table->string('name');
            $table->char('char');
            $table->text('text');
            $table->mediumText('medium_text');
            $table->longText('long_text');
            $table->dateTime('started_at');
            $table->binary('blob');
            $table->json('json');
            $table->integerArray('int_array')->nullable();
            $table->booleanArray('bool_array')->nullable();
            $table->floatArray('float_array')->nullable();
            $table->decimalArray('decimal_array')->nullable();
            $table->stringArray('string_array_undef')->nullable();
            $table->stringArray('string_array_1', 1)->nullable();
            $table->stringArray('string_array_max', 'max')->nullable();
            $table->timestampArray('timestamp_array')->nullable();
            $table->timestamps();
        });
        $blueprint->create();

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
            "create table `{$tableName}` (" . implode(', ', [
                '`id` string(36) not null',
                '`int` int64 not null',
                '`float` float64 not null',
                '`decimal` numeric not null',
                '`name` string(255) not null',
                '`char` string(255) not null',
                '`text` string(max) not null',
                '`medium_text` string(max) not null',
                '`long_text` string(max) not null',
                '`started_at` timestamp not null',
                '`blob` bytes(255) not null',
                '`json` json not null',
                '`int_array` array<int64>',
                '`bool_array` array<bool>',
                '`float_array` array<float64>',
                '`decimal_array` array<numeric>',
                '`string_array_undef` array<string(255)>',
                '`string_array_1` array<string(1)>',
                '`string_array_max` array<string(max)>',
                '`timestamp_array` array<timestamp>',
                '`created_at` timestamp, `updated_at` timestamp',
            ]) . ') primary key (`id`)',
        ], $statements);
    }

    public function test_drop(): void
    {
        $conn = $this->getDefaultConnection();
        $tableName = $this->generateTableName();
        $blueprint = new Blueprint($tableName, function (Blueprint $table) {
            $table->drop();
        });

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
            "drop table `{$tableName}`",
        ], $statements);
    }

    public function test_dropIfExists(): void
    {
        $conn = $this->getDefaultConnection();
        $tableName = $this->generateTableName();
        $blueprint = new Blueprint($tableName, function (Blueprint $table) {
            $table->dropIfExists();
        });

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
            "drop table if exists `{$tableName}`",
        ], $statements);

        $this->assertNotNull($conn->runDdlBatch($statements));
    }

    public function test_adding_columns(): void
    {
        $conn = $this->getDefaultConnection();
        $tableName = $this->generateTableName();
        $blueprint = new Blueprint($tableName, function (Blueprint $table) {
            $table->string('description', 255);
            $table->integer('value');
        });

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
            "alter table `{$tableName}` add column `description` string(255) not null",
            "alter table `{$tableName}` add column `value` int64 not null",
        ], $statements);
    }

    public function test_change_column(): void
    {
        $conn = $this->getDefaultConnection();
        $tableName = $this->generateTableName();
        $blueprint = new Blueprint($tableName, function (Blueprint $table) {
            $table->string('description', 512)->change();
            $table->float('value')->change();
        });

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
            "alter table `{$tableName}` alter column `description` string(512) not null",
            "alter table `{$tableName}` alter column `value` float64 not null",
        ], $statements);
    }

    public function test_dropColumn(): void
    {
        $conn = $this->getDefaultConnection();
        $tableName = $this->generateTableName();
        $blueprint = new Blueprint($tableName, function (Blueprint $table) {
            $table->dropColumn('description');
        });

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
            "alter table `{$tableName}` drop column `description`",
        ], $statements);
    }

    public function test_create_indexes(): void
    {
        $conn = $this->getDefaultConnection();
        $tableName = $this->generateTableName();
        $indexPrefix = Str::snake($tableName);
        $blueprint = new Blueprint($tableName, function (Blueprint $table) {
            $table->unique('name');
            $table->index('createdAt');
        });

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
            "create unique index `{$indexPrefix}_name_unique` on `{$tableName}` (`name`)",
            "create index `{$indexPrefix}_createdat_index` on `{$tableName}` (`createdAt`)",
        ], $statements);
    }

    public function test_dropIndex(): void
    {
        $conn = $this->getDefaultConnection();
        $tableName = $this->generateTableName();
        $indexPrefix = Str::snake($tableName);
        $blueprint = new Blueprint($tableName, function (Blueprint $table) use ($indexPrefix) {
            $table->dropUnique($indexPrefix . '_name_unique');
            $table->dropIndex($indexPrefix . '_createdat_index');
        });

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
            "drop index `{$indexPrefix}_name_unique`",
            "drop index `{$indexPrefix}_createdat_index`",
        ], $statements);
    }

    public function test_dropForeign(): void
    {
        $conn = $this->getDefaultConnection();
        $tableName = $this->generateTableName();
        $foreignPrefix = Str::snake($tableName);
        $blueprint = new Blueprint($tableName, function (Blueprint $table) use ($foreignPrefix) {
            $table->dropForeign('fk_' . $foreignPrefix);
        });

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
            "alter table `{$tableName}` drop constraint `fk_{$foreignPrefix}`",
        ], $statements);
    }

    public function test_composite_primary_key(): void
    {
        $conn = $this->getDefaultConnection();
        $tableName = $this->generateTableName();
        $blueprint = new Blueprint($tableName, function (Blueprint $table) {
            $table->uuid('id');
            $table->integer('number');
            $table->string('name');
            $table->primary(['id', 'number']);
        });
        $blueprint->create();

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
            "create table `{$tableName}` (`id` string(36) not null, `number` int64 not null, `name` string(255) not null) primary key (`id`, `number`)"
        ], $statements);
    }

    public function test_interleaving(): void
    {
        $conn = $this->getDefaultConnection();
        $parentTableName = $this->generateTableName('Parent');
        $childTableName = $this->generateTableName('Child');

        $blueprint = new Blueprint($childTableName, function (Blueprint $table) use ($parentTableName) {
            $table->uuid('id');
            $table->uuid('pid');
            $table->string('name');
            $table->primary('pid');
            $table->interleaveInParent($parentTableName);
        });
        $blueprint->create();

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
            "create table `{$childTableName}` (`id` string(36) not null, `pid` string(36) not null, `name` string(255) not null) primary key (`pid`), interleave in parent `{$parentTableName}`",
        ], $statements);

        $blueprint = new Blueprint($childTableName, function (Blueprint $table) use ($parentTableName) {
            $table->uuid('id');
            $table->uuid('pid');
            $table->string('name');

            $table->primary('pid');
            $table->interleaveInParent($parentTableName)->cascadeOnDelete();
        });
        $blueprint->create();

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
            "create table `{$childTableName}` (`id` string(36) not null, `pid` string(36) not null, `name` string(255) not null) primary key (`pid`), interleave in parent `{$parentTableName}` on delete cascade"
        ], $statements);
    }

    public function test_create_with_row_deletion_policy(): void
    {
        $conn = $this->getDefaultConnection();
        $conn->useDefaultSchemaGrammar();
        $grammar = $conn->getSchemaGrammar();
        $tableName = $this->generateTableName();
        $blueprint = new Blueprint($tableName, function (Blueprint $table) {
            $table->uuid('id');
            $table->primary('id');
            $table->dateTime('t')->nullable();
            $table->deleteRowsOlderThan('t', 100);
        });
        $blueprint->create();
        $blueprint->build($conn, $grammar);

        $statements = $blueprint->toSql($conn, $grammar);
        $this->assertSame([
            "create table `{$tableName}` (`id` string(36) not null, `t` timestamp) primary key (`id`), row deletion policy (older_than(t, interval 100 day))",
        ], $statements);
    }

    public function test_add_row_deletion_policy(): void
    {
        $conn = $this->getDefaultConnection();
        $conn->useDefaultSchemaGrammar();
        $grammar = $conn->getSchemaGrammar();
        $tableName = $this->generateTableName();
        $blueprint1 = new Blueprint($tableName, function (Blueprint $table) {
            $table->create();
            $table->uuid('id');
            $table->primary('id');
            $table->dateTime('t')->nullable();
        });
        $blueprint1->build($conn, $grammar);
        $blueprint2 = new Blueprint($tableName, function (Blueprint $table) {
            $table->addRowDeletionPolicy('t', 200);
        });
        $blueprint2->build($conn, $grammar);

        $statements = $blueprint2->toSql($conn, $grammar);
        $this->assertSame([
            "alter table `{$tableName}` add row deletion policy (older_than(`t`, interval 200 day))",
        ], $statements);
    }

    public function test_replace_row_deletion_policy(): void
    {
        $conn = $this->getDefaultConnection();
        $conn->useDefaultSchemaGrammar();
        $grammar = $conn->getSchemaGrammar();
        $tableName = $this->generateTableName();
        $blueprint1 = new Blueprint($tableName, function (Blueprint $table) {
            $table->create();
            $table->uuid('id');
            $table->primary('id');
            $table->dateTime('t')->nullable();
            $table->deleteRowsOlderThan('t', 100);
        });
        $blueprint1->build($conn, $grammar);
        $blueprint2 = new Blueprint($tableName, function (Blueprint $table) {
            $table->replaceRowDeletionPolicy('t', 200);
        });
        $blueprint2->build($conn, $grammar);

        $statements = $blueprint2->toSql($conn, $grammar);
        $this->assertSame([
            "alter table `{$tableName}` replace row deletion policy (older_than(`t`, interval 200 day))",
        ], $statements);
    }

    public function test_drop_row_deletion_policy(): void
    {
        $conn = $this->getDefaultConnection();
        $conn->useDefaultSchemaGrammar();
        $grammar = $conn->getSchemaGrammar();
        $tableName = $this->generateTableName();
        $blueprint1 = new Blueprint($tableName, function (Blueprint $table) {
            $table->create();
            $table->uuid('id');
            $table->primary('id');
            $table->dateTime('created_at')->nullable();
            $table->deleteRowsOlderThan('created_at', 100);
        });
        $blueprint1->build($conn, $grammar);
        $blueprint2 = new Blueprint($tableName, function (Blueprint $table) {
            $table->dropRowDeletionPolicy();
        });
        $blueprint2->build($conn, $grammar);

        $statements = $blueprint2->toSql($conn, $grammar);
        $this->assertSame([
            "alter table `{$tableName}` drop row deletion policy",
        ], $statements);
    }

    public function test_default_values(): void
    {
        $conn = $this->getDefaultConnection();
        $conn->useDefaultSchemaGrammar();
        $grammar = $conn->getSchemaGrammar();
        $tableName = $this->generateTableName();

        $blueprint = new Blueprint($tableName, function (Blueprint $table) {
            $table->uuid('id');
            $table->integer('null')->default(null)->nullable();
            $table->integer('int')->default(1);
            $table->bigInteger('bigint')->default(1);
            $table->float('float')->default(0.1);
            $table->double('double')->default(0.1);
            $table->decimal('decimal')->default(123.456);
            $table->boolean('bool')->default(true);
            $table->string('string')->default('a');
            $table->text('string_max')->default('a');
            $table->char('char')->default('a');
            $table->mediumText('medium_text')->default('a');
            $table->longText('long_text')->default('a');
            $table->float('raw')->default(DB::raw('1.1'));
            $table->json('json')->default(DB::raw('json "[1,2,3]"'));
            $table->date('date_as_string')->default('2022-01-01');
            $table->date('date_as_carbon')->default(new Carbon('2022-01-01'));
            $table->dateTime('time_as_string')->default('2022-01-01');
            $table->dateTime('time_as_carbon')->default(new Carbon('2022-01-01'));
            $table->dateTime('current_time')->useCurrent();
            $table->timestamp('current_time_as_ts')->useCurrent();
            $table->integerArray('int_array')->default([1, 2]);
            $table->booleanArray('bool_array')->default([false, true]);
            $table->floatArray('float_array')->default([2.2, 3.3]);
            $table->stringArray('string_array', 1)->default(['a', 'b']);
            $table->dateArray('date_array')->default(['2022-01-01']);
            $table->timestampArray('timestamp_array')->default(['2022-01-01']);
            $table->primary('id');
        });
        $blueprint->create();

        $statements = $blueprint->toSql($conn, $grammar);
        $this->assertSame([
            "create table `{$tableName}` (" . implode(', ', [
                '`id` string(36) not null',
                '`null` int64',
                '`int` int64 not null default (1)',
                '`bigint` int64 not null default (1)',
                '`float` float64 not null default (0.1)',
                '`double` float64 not null default (0.1)',
                '`decimal` numeric not null default (123.456)',
                '`bool` bool not null default (true)',
                '`string` string(255) not null default ("a")',
                '`string_max` string(max) not null default ("a")',
                '`char` string(255) not null default ("a")',
                '`medium_text` string(max) not null default ("a")',
                '`long_text` string(max) not null default ("a")',
                '`raw` float64 not null default (1.1)',
                '`json` json not null default (json "[1,2,3]")',
                '`date_as_string` date not null default (DATE "2022-01-01")',
                '`date_as_carbon` date not null default (DATE "2022-01-01")',
                '`time_as_string` timestamp not null default (TIMESTAMP "2022-01-01T00:00:00.000000+00:00")',
                '`time_as_carbon` timestamp not null default (TIMESTAMP "2022-01-01T00:00:00.000000+00:00")',
                '`current_time` timestamp not null default (CURRENT_TIMESTAMP())',
                '`current_time_as_ts` timestamp not null default (CURRENT_TIMESTAMP())',
                '`int_array` array<int64> not null default ([1, 2])',
                '`bool_array` array<bool> not null default ([false, true])',
                '`float_array` array<float64> not null default ([2.2, 3.3])',
                '`string_array` array<string(1)> not null default (["a", "b"])',
                '`date_array` array<date> not null default ([DATE "2022-01-01"])',
                '`timestamp_array` array<timestamp> not null default ([TIMESTAMP "2022-01-01T00:00:00.000000+00:00"])',
            ]) . ') primary key (`id`)',
        ], $statements);

        $blueprint->build($conn, $grammar);
        $query = $conn->table($tableName);
        $query->insert(['id' => Uuid::uuid4()->toString()]);
        /** @var array<string, mixed> $result */
        $result = $query->sole();

        $dateFormat = $grammar->getDateFormat();

        $this->assertSame(null, $result['null']);
        $this->assertSame(1, $result['int']);
        $this->assertSame(0.1, $result['float']);
        $this->assertSame(true, $result['bool']);
        $this->assertSame('a', $result['string']);
        $this->assertSame('a', $result['string_max']);
        $this->assertSame('a', $result['medium_text']);
        $this->assertSame('a', $result['long_text']);
        $this->assertSame('[1,2,3]', $result['json']);
        $this->assertSame(1.1, $result['raw']);
        $this->assertSame('2022-01-01T00:00:00.000000+00:00', $result['date_as_string']->get()->format($dateFormat));
        $this->assertSame('2022-01-01T00:00:00.000000+00:00', $result['date_as_carbon']->get()->format($dateFormat));
        $this->assertSame('2022-01-01T00:00:00.000000+00:00', $result['time_as_string']->format($dateFormat));
        $this->assertSame('2022-01-01T00:00:00.000000+00:00', $result['time_as_carbon']->format($dateFormat));
        $this->assertInstanceOf(Carbon::class, $result['current_time']);
        $this->assertSame([1, 2], $result['int_array']);
        $this->assertSame([false, true], $result['bool_array']);
        $this->assertSame([2.2, 3.3], $result['float_array']);
        $this->assertSame(['a', 'b'], $result['string_array']);
    }

    public function test_index_with_interleave(): void
    {
        $conn = $this->getDefaultConnection();
        $blueprint = new Blueprint('UserItem', function (Blueprint $table) {
            $table->index(['userId', 'createdAt'])->interleaveIn('User');
            $table->index(['userId', 'updatedAt'])->interleave('User');
        });

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
                'create index `useritem_userid_createdat_index` on `UserItem` (`userId`, `createdAt`), interleave in `User`',
                'create index `useritem_userid_updatedat_index` on `UserItem` (`userId`, `updatedAt`), interleave in `User`',
            ],
            $statements
        );
    }

    public function test_index_with_storing(): void
    {
        $conn = $this->getDefaultConnection();

        $blueprint = new Blueprint('UserItem', function (Blueprint $table) {
            $table->index(['userId', 'createdAt'])->storing(['itemId', 'count']);
        });

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
            'create index `useritem_userid_createdat_index` on `UserItem` (`userId`, `createdAt`) storing (`itemId`, `count`)',
        ], $statements);
    }

    public function test_null_filtered_index(): void
    {
        $conn = $this->getDefaultConnection();
        $blueprint = new Blueprint('UserItem', function (Blueprint $table) {
            $table->index(['userId'])->nullFiltered();
        });

        $statements = $blueprint->toSql($conn, new Grammar());
        $this->assertSame([
            'create null_filtered index `useritem_userid_index` on `UserItem` (`userId`)'
        ], $statements);
    }
}
