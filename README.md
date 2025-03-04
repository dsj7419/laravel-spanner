laravel-spanner
================

Laravel database driver for Google Cloud Spanner

[![License](https://img.shields.io/packagist/l/colopl/laravel-spanner.svg?style=flat-square)](https://github.com/colopl/laravel-spanner/blob/master/LICENSE)
[![Latest Stable Version](https://img.shields.io/packagist/v/colopl/laravel-spanner.svg?style=flat-square)](https://packagist.org/packages/colopl/laravel-spanner)
[![Minimum PHP Version](https://img.shields.io/packagist/php-v/colopl/laravel-spanner.svg?style=flat-square)](https://secure.php.net/)

## Requirements

- PHP >= 8.1
- Laravel >= 10
- [gRPC extension](https://cloud.google.com/php/grpc)
- [protobuf extension](https://cloud.google.com/php/grpc#install_the_protobuf_runtime_library) (not required, but strongly recommended)
- Currently only supports Spanner running GoogleSQL (PostgreSQL mode not supported) 

## Installation
Put JSON credential file path to env variable: `GOOGLE_APPLICATION_CREDENTIALS`

```
export GOOGLE_APPLICATION_CREDENTIALS=/path/to/key.json
```

Install via composer

```sh
composer require colopl/laravel-spanner
```

Add connection config to `config/database.php`

```php
[
    'connections' => [
        'spanner' => [
            'driver' => 'spanner',
            'instance' => '<Cloud Spanner instanceId here>',
            'database' => '<Cloud Spanner database name here>',
        ]
    ]
];
```

That's all. You can use database connection as usual.

```php
$conn = DB::connection('spanner');
$conn->...
```

## Additional Configurations
You can pass `SpannerClient` config and `CacheSessionPool` options as below.
For more information, please see [Google Client Library docs](http://googleapis.github.io/google-cloud-php/#/docs/google-cloud/latest/spanner/spannerclient?method=__construct)

```php
[
    'connections' => [
        'spanner' => [
            'driver' => 'spanner',
            'instance' => '<Cloud Spanner instanceId here>',
            'database' => '<Cloud Spanner database name here>',
            
            // Spanner Client configurations
            'client' => [
                'projectId' => 'xxx',
                ...
            ],
            
            // CacheSessionPool options
            'session_pool' => [
                'minSessions' => 10,
                'maxSessions' => 500,
            ],
        ]
    ]
];
```

## Unsupported features

- STRUCT data types
- Inserting/Updating JSON data types
- Explicit Read-only transaction (snapshot)

## Limitations

### Migrations
Most functions of `SchemaBuilder` (eg, `Schema` facade, and `Blueprint`) can be used.
However, `artisan migrate` command does not work since AUTO_INCREMENT does not exist in Google Cloud Spanner.

### Eloquent
If you use interleaved keys, you MUST define them in the `interleaveKeys` property or you won't be able to save. For more detailed instructions, see `Colopl\Spanner\Tests\Eloquent\ModelTest`.


## Additional Information

### Transactions
Google Cloud Spanner sometimes requests transaction retries (e.g. `UNAVAILABLE`, and `ABORTED`), even if the logic is correct. For that reason, please do not manage transactions manually.

You should always use the `transaction` method which handles retry requests internally.

```php
// BAD: Do not use transactions manually!!
try {
    DB::beginTransaction();
    ...
    DB::commit();
} catch (\Throwable $ex) {
    DB::rollBack();
}

// GOOD: You should always use transaction method
DB::transaction(function() {
    ...
});
```

Google Cloud Spanner creates transactions for all data operations even if you do not explicitly create transactions.

In particular, in the SELECT statement, the type of transaction varies depending on whether it is explicit or implicit.

```php
// implicit transaction (Read-only transaction)
$conn->select('SELECT ...');

// explicit transaction (Read-write transaction)
$conn->transaction(function() {
    $conn->select('SELECT ...');
});

// implicit transaction (Read-write transaction)
$conn->insert('INSERT ...');

// explicit transaction (Read-write transaction)
$conn->transaction(function() {
    $conn->insert('INSERT ...');
});
```

| Transaction type | **SELECT** statement | **INSERT/UPDATE/DELETE** statement |
| :--- | :--- | :--- |
| implicit transaction | **Read-only** transaction with **singleUse** option | **Read-write** transaction with **singleUse** option |
| explicit transaction | **Read-write** transaction | **Read-write** transaction |

For more information, see [Cloud Spanner Documentation about transactions](https://cloud.google.com/spanner/docs/transactions)

### Stale reads

You can use [Stale reads (timestamp bounds)](https://cloud.google.com/spanner/docs/timestamp-bounds) as below.

```php
// There are four types of timestamp bounds: ExactStaleness, MaxStaleness, MinReadTimestamp and ReadTimestamp.
$timestampBound = new ExactStaleness(10);

// by Connection
$connection->selectWithTimestampBound('SELECT ...', $bindings, $timestampBound);

// by Query Builder
$queryBuilder
    ->withStaleness($timestampBound)
    ->get();
```

Stale reads always runs as read-only transaction with `singleUse` option. So you can not run as read-write transaction.

### Data Boost

Data boost creates snapshot and runs the query in parallel without affecting existing workloads.

You can read more about it [here](https://cloud.google.com/spanner/docs/databoost/databoost-overview).

Below are some examples of how to use it.

```php
// Using Connection
$connection->selectWithOptions('SELECT ...', $bindings, ['dataBoostEnabled' => true]);

// Using Query Builder
$queryBuilder
    ->useDataBoost()
    ->get();
```

> [!NOTE]
> This creates a new session in the background which is not shared with the current session pool.
> This means, queries running with data boost will not be associated with transactions that may be taking place.

### Data Types
Some data types of Google Cloud Spanner does not have corresponding built-in type of PHP.
You can use following classes by [Google Cloud PHP Client](https://github.com/googleapis/google-cloud-php)

- BYTES: `Google\Cloud\Spanner\Bytes`
- DATE: `Google\Cloud\Spanner\Date`
- NUMERIC: `Google\Cloud\Spanner\Numeric`
- TIMESTAMP: `Google\Cloud\Spanner\Timestamp`

When fetching rows, the library coverts the following column types
- `Timestamp` -> [Carbon](https://laravel.com/api/10.x/Illuminate/Support/Carbon.html) with the default timezone in PHP
- `Numeric` -> `string`

Note that if you execute a query without QueryBuilder, it will not have these conversions.


### Partitioned DML
You can run partitioned DML as below.

```php
// by Connection
$connection->runPartitionedDml('UPDATE ...');


// by Query Builder
$queryBuilder->partitionedUpdate($values);
$queryBuilder->partitionedDelete();
```

However, Partitioned DML has some limitations. See [Cloud Spanner Documentation about Partitioned DML](https://cloud.google.com/spanner/docs/dml-partitioned#dml_and_partitioned_dml) for more information.


### Interleave
You can define [interleaved tables](https://cloud.google.com/spanner/docs/schema-and-data-model#creating_a_hierarchy_of_interleaved_tables) as below.

```php
$schemaBuilder->create('user_items', function (Blueprint $table) {
    $table->uuid('user_id');
    $table->uuid('id');
    $table->uuid('item_id');
    $table->integer('count');
    $table->timestamps();

    $table->primary(['user_id', 'id']);
    
    // interleaved table
    $table->interleaveInParent('users')->cascadeOnDelete();
    
    // interleaved index
    $table->index(['userId', 'created_at'])->interleaveIn('users');
});
```

### Row Deletion Policy

You can define [row deletion policy](https://cloud.google.com/spanner/docs/ttl/working-with-ttl) as below.

```php
$schemaBuilder->create('user', function (Blueprint $table) {
    $table->uuid('user_id');
    $table->timestamps();
    
    // create a policy
    $table->deleteRowsOlderThan(['updated_at'], 365);
});

$schemaBuilder->table('user', function (Blueprint $table) {
    // add policy
    $table->addRowDeletionPolicy('udpated_at', 100);

    // replace policy
    $table->replaceRowDeletionPolicy('udpated_at', 100);

    // drop policy
    $table->dropRowDeletionPolicy();
});
```

### Secondary Index Options

You can define Spanner specific index options like [null filtering](https://cloud.google.com/spanner/docs/secondary-indexes#null-indexing-disable) and [storing](https://cloud.google.com/spanner/docs/secondary-indexes#storing-clause) as below.

```php
$schemaBuilder->table('user_items', function (Blueprint $table) {
    $table->index('userId')
        // Interleave in parent table
        ->interleaveIn('user')
        // Add null filtering
        ->nullFiltered()
        // Add storing
        ->storing(['itemId', 'count']);
});
```

### Mutations

You can [insert, update, and delete data using mutations](https://cloud.google.com/spanner/docs/modify-mutation-api) to modify data instead of using DML to improve performance.

```
$queryBuilder->insertUsingMutation($values);
$queryBuilder->updateUsingMutation($values);
$queryBuilder->insertOrUpdateUsingMutation($values);
$queryBuilder->deleteUsingMutation($values);
```

Please note that mutation api does not work the same way as DML.
All mutations calls within a transaction are queued and sent as batch at the time you commit.
This means that if you make any modifications through the above functions and then try to SELECT the same records before committing, the returned results will not include any of the modifications you've made inside the transaction.


### SessionPool and AuthCache
In order to improve the performance of the first connection per request, we use [AuthCache](https://github.com/googleapis/google-cloud-php#caching-access-tokens) and [CacheSessionPool](https://googleapis.github.io/google-cloud-php/#/docs/google-cloud/latest/spanner/session/cachesessionpool).

By default, laravel-spanner uses [Filesystem Cache Adapter](https://symfony.com/doc/current/components/cache/adapters/filesystem_adapter.html) as the caching pool. If you want to use your own caching pool, you can extend ServiceProvider and inject it into the constructor of `Colopl\Spanner\Connection`.


### Queue Worker

After every job is processed, the connection will be disconnected so the session can be released into the session pool. 
This allows the session to be renewed (through `maintainSessionPool()`) or expire.


### Laravel Tinker
You can use [Laravel Tinker](https://github.com/laravel/tinker) with commands such as `php artisan tinker`.
But your session may hang when accessing Cloud Spanner. This is known gRPC issue that occurs when PHP forks a process.
The workaround is to add following line to `php.ini`.

```ini
grpc.enable_fork_support=1
```



## Development

### Testing
You can run tests on docker by the following command. Note that some environment variables must be set.
In order to set the variables, rename [.env.sample](./.env.sample) to `.env` and edit the values of the
defined variables.

| Name | Value |
| :-- | :--   |
| `GOOGLE_APPLICATION_CREDENTIALS` | The path of the service account key file with access privilege to Google Cloud Spanner instance |
| `DB_SPANNER_INSTANCE_ID` | Instance ID of your Google Cloud Spanner |
| `DB_SPANNER_DATABASE_ID` | Name of the database with in the Google Cloud Spanner instance |
| `DB_SPANNER_PROJECT_ID` | Not required if your credential includes the project ID  |

```sh
make test
```

## License
Apache 2.0 - See [LICENSE](./LICENSE) for more information.

