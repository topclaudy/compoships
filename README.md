Compoships
==========

**Compoships** offers the ability to specify relationships based on two (or more) columns in Laravel's Eloquent ORM. The need to match multiple columns in the definition of an Eloquent relationship often arises when working with third party or pre existing schema/database. 

## The problem

Eloquent doesn't support composite keys. As a consequence, there is no way to define a relationship from one model to another by matching more than one column. Trying to use `where clauses` (like in the example below) won't work when eager loading the relationship because at the time the relationship is processed **$this->team_id** is null. 

```php
namespace App;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    public function tasks()
    {
        //WON'T WORK WITH EAGER LOADING!!!
        return $this->hasMany(Task::class)->where('team_id', $this->team_id);
    }
}
```

#### Related discussions:

* [Relationship on multiple keys](https://laracasts.com/discuss/channels/eloquent/relationship-on-multiple-keys)
* [Querying relations with extra conditions not working as expected](https://github.com/laravel/framework/issues/1272)
* [Querying relations with extra conditions in Eager Loading not working](https://github.com/laravel/framework/issues/19488)
* [BelongsTo relationship with 2 foreign keys](https://laravel.io/forum/08-02-2014-belongsto-relationship-with-2-foreign-keys)
* [Laravel Eloquent: multiple foreign keys for relationship](https://stackoverflow.com/questions/48077890/laravel-eloquent-multiple-foreign-keys-for-relationship/49834070#49834070)
* [Laravel hasMany association with multiple columns](https://stackoverflow.com/questions/32471084/laravel-hasmany-association-with-multiple-columns)

## Installation

The recommended way to install **Compoships** is through [Composer](http://getcomposer.org/)

```bash
$ composer require awobaz/compoships
```
## Usage

### Using the `Awobaz\Compoships\Database\Eloquent\Model` class

Simply make your model class derive from the `Awobaz\Compoships\Database\Eloquent\Model` base class. The `Awobaz\Compoships\Database\Eloquent\Model` extends the `Eloquent` base class without changing its core functionality.

### Using the `Awobaz\Compoships\Compoships` trait

If for some reason you can't derive your models from `Awobaz\Compoships\Database\Eloquent\Model`, you may take advantage of the `Awobaz\Compoships\Compoships` trait. Simply use the trait in your models.
 
**Note:** To define a multi-columns relationship from a model *A* to another model *B*, **both models must either extend `Awobaz\Compoships\Database\Eloquent\Model` or use the `Awobaz\Compoships\Compoships` trait**

### Syntax

... and now we can define a relationship from a model *A* to another model *B* by matching two or more columns (by passing an array of columns instead of a string). 

```php
namespace App;

use Illuminate\Database\Eloquent\Model;

class A extends Model
{
    use \Awobaz\Compoships\Compoships;
    
    public function b()
    {
        return $this->hasMany('B', ['foreignKey1', 'foreignKey2'], ['localKey1', 'localKey2']);
    }
}
```

We can use the same syntax to define the inverse of the relationship:

```php
namespace App;

use Illuminate\Database\Eloquent\Model;

class B extends Model
{
    use \Awobaz\Compoships\Compoships;

    public function a()
    {
        return $this->belongsTo('A', ['foreignKey1', 'foreignKey2'], ['ownerKey1', 'ownerKey2']);
    }
}
```

We can also define many-to-many relationships with composite keys through a pivot table:

```php
namespace App;

use Illuminate\Database\Eloquent\Model;

class A extends Model
{
    use \Awobaz\Compoships\Compoships;

    public function b()
    {
        return $this->belongsToMany(
            B::class,
            'a_b',                                  // pivot table
            ['a_foreignKey1', 'a_foreignKey2'],     // foreign pivot keys for A
            ['b_foreignKey1', 'b_foreignKey2'],     // foreign pivot keys for B
            ['localKey1', 'localKey2'],             // local keys on A
            ['localKey1', 'localKey2']              // local keys on B
        );
    }
}
```

All standard `belongsToMany` operations work with composite keys: `attach()`, `detach()`, `sync()`, `toggle()`, `withPivot()`, `withTimestamps()`, eager loading, and existence queries (`has()`, `whereHas()`).

#### Composite-key input shapes for `attach()` and `sync()`

Two input shapes are supported for `attach()`, `sync()`, `syncWithoutDetaching()`, and `toggle()` on composite-key relations.

A list of composite tuples (each tuple is an array aligned with the related-pivot-key columns):

```php
$team->projects()->attach([
    ['EU', 2],
    ['US', 1],
]);
```

A map of `json_encode($tuple) => $perRowAttributes`, equivalent to Laravel's single-key `[id => attributes]` shape. The key must be the JSON encoding of the composite tuple, produced via `json_encode([...])`. Per-row attributes override any shared bulk attributes on key conflict, and any per-row attribute keys colliding with the foreign-pivot-key columns are silently dropped to prevent overriding the parent linkage.

```php
$team->projects()->attach([
    json_encode(['EU', 2]) => ['role' => 'reviewer'],
    json_encode(['US', 1]) => ['role' => 'lead'],
], ['note' => 'bulk applied to all']);
```

Passing an associative array key that is not a JSON-encoded tuple of the correct arity throws `Awobaz\Compoships\Exceptions\InvalidUsageException`.

A third shape, `[$scalarId => $attributes]`, uses the scalar as the value of the **first** related-pivot-key column and reads the remaining key columns from the per-row or shared attributes. `attach()`, `sync()`, and `toggle()` all read it the same way; `[5 => []]` means "scalar id 5 with no per-row attributes".

```php
// relatedPivotKey = ['project_division_id', 'project_region_code']
$team->projectsByDivision()->sync([
    5 => ['project_region_code' => 'US', 'role' => 'lead'],
]);
```

Note the asymmetry between `attach()` and `detach()` for a flat list of scalars: `attach(['US', 'EU'])` is two scalar ids (each needing the remaining key columns from attributes), while `detach(['US', 2])` is read as one tuple when its length matches the related-pivot-key arity. Use explicit tuples (`[['US', 2]]`) when in doubt.

### Factories

Chances are that you may need factories for your Compoships models. If so, you will probably need to use
Factory methods to create relationship models. For example, by using the ->has() method. Just use the
``Awobaz\Compoships\Database\Eloquent\Factories\ComposhipsFactory`` trait in your factory classes to be able
to use relationships correctly.

### Example

As an example, let's pretend we have a task list with categories, managed by several teams of users where:
* a task belongs to a category
* a task is assigned to a team
* a team has many users
* a user belongs to one team
* a user is responsible for one category of tasks

The user responsible for a particular task is the user _currently_ in charge for the category inside the team.

```php
namespace App;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use \Awobaz\Compoships\Compoships;
    
    public function tasks()
    {
        return $this->hasMany(Task::class, ['team_id', 'category_id'], ['team_id', 'category_id']);
    }
}
```

Again, same syntax to define the inverse of the relationship:

```php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use \Awobaz\Compoships\Compoships;

    public function user()
    {
        return $this->belongsTo(User::class, ['team_id', 'category_id'], ['team_id', 'category_id']);
    }
}
```

For a many-to-many scenario, imagine users can be assigned to projects, where both the user and the project are identified by a composite key (`team_id` and `department_id`):

```php
namespace App;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use \Awobaz\Compoships\Compoships;

    public function projects()
    {
        return $this->belongsToMany(
            Project::class,
            'project_user',
            ['user_team_id', 'user_department_id'],
            ['project_team_id', 'project_department_id'],
            ['team_id', 'department_id'],
            ['team_id', 'department_id']
        );
    }
}
```
## Supported relationships

**Compoships** supports the following Laravel Eloquent relationships:

* hasOne
* hasMany
* belongsTo
* belongsToMany

**Nullable columns** are supported: a `null` key component matches `IS NULL` on the related side, on both lazy and eager loading. A key whose components are **all** `null` matches nothing and issues no query.

**Note on `belongsToMany`:** Custom pivot models (via `using()`) with composite keys are supported. Your custom pivot class should extend `Awobaz\Compoships\Database\Eloquent\Relations\Pivot` instead of Laravel's base `Pivot` class to ensure correct behavior for save, delete, and queue operations.

**Note on SQL Server:** row-value `IN` is not available, so composite `whereIn` compiles to an exact `((a = ? AND b = ?) OR ...)` group and `NOT IN` to its negation. The SQL is covered by grammar-level tests, but no SQL Server instance runs in CI; please report regressions.

**Note on expression keys:** a foreign key list may contain a `DB::raw()` expression for lazy loading and query constraints. Writing through such a relation (`create()`, `save()`, `upsert()`) or eager loading it throws `InvalidUsageException`, because an expression names no attribute to write or match.

**Note on custom grammars:** the package installs its own query grammar only while the connection carries Laravel's stock grammar for the driver. A grammar installed by your application or another package is left in place; eager-load `limit()` on a composite relation then throws `InvalidUsageException` until that grammar uses `Awobaz\Compoships\Database\Grammar\Concerns\CompileRowNumber`.

**Note on factories:** `Factory::has()` supports composite relations and applies the relation's `withAttributes()` values (on illuminate/database 12.15+). `Factory::for()` with a composite `belongsTo` is not supported.

## Composite primary keys

By default, Eloquent builds the WHERE clause for `UPDATE`, `DELETE`, and `refresh()` / `fresh()` using only the scalar `$primaryKey`. On tables whose primary key spans multiple columns (such as `(id, tenant_id)` in multi-tenant or partitioned schemas), `$model->save()` on a hydrated row emits a query like `UPDATE table SET ... WHERE id = ?`, missing the discriminator. The same scalar id can exist under another discriminator value, so the operation silently targets the wrong row.

**Compoships** lets you opt into composite primary key handling on the write path. Declare a `$compositeKey` property on the model that enumerates every column in the primary key (including the scalar slot named by `$primaryKey`). Keep `$primaryKey` as the scalar column name so `getKey()`, `Model::find($id)`, route model binding, and queue serialization continue to work unchanged.

```php
namespace App;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;

class TenantUser extends Model
{
    use Compoships;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $compositeKey = ['id', 'tenant_id'];

    protected $guarded = [];
}
```

With this declaration, the following operations scope their WHERE clause by every column in `$compositeKey`:

* `save()` and `update()` on a hydrated model.
* `delete()` (including soft deletes via `SoftDeletes::runSoftDelete()`).
* `refresh()` and `fresh()`.

For example:

```php
$user = TenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
$user->name = 'Alice';
$user->save();
// UPDATE tenant_users SET name = ? WHERE id = ? AND tenant_id = ?
```

The following operations are **not** affected. They remain identical to stock Eloquent.

* `Model::find($id)` looks up by the scalar primary key only.
* `firstOrCreate`, `updateOrCreate`, and similar helpers build their own WHERE clauses from user input.
* Route model binding by single id continues to use the scalar key.

Queue serialization (via `Illuminate\Queue\SerializesModels`, used by queueable jobs, events, and notifications) participates in composite handling for **single-model** properties on the job: `getQueueableId()` returns a JSON-encoded array of the composite key columns, and `newQueryForRestoration()` decodes it back into a query that scopes by every key column on the worker side. Round-tripping a single composite-keyed model through the queue reloads the exact composite row that was queued. Old queued payloads predating this feature (with a scalar id) continue to restore via the parent path, so no queue drain is required on upgrade.

**Collection round-trip requires the `QueueableCompositeCollection` wrapper.** A raw `Illuminate\Database\Eloquent\Collection` of composite-keyed models on a job property will restore as an empty collection. The cause is in Laravel's `restoreCollection`: it re-keys loaded models by scalar `getKey()` and looks up by the original queued ids (our JSON-encoded composite strings), so the lookup keys never match. The package ships a wrapper class that sidesteps the issue by capturing composite-key tuples at queue time and rebuilding the collection via composite-aware query at restore time:

```php
use Awobaz\Compoships\Queue\QueueableCompositeCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Queue\SerializesModels;

class ProcessUsers
{
    use SerializesModels;

    public QueueableCompositeCollection $users;

    public function __construct(Collection $users)
    {
        $this->users = QueueableCompositeCollection::for($users);
    }

    public function handle(): void
    {
        $users = $this->users->restore();
        // ...
    }
}
```

The wrapper preserves the original collection order, eager-loaded relations, the model's connection, and the model's collection class. It accepts filtered or re-keyed collections. It rejects mixed-class collections (throws `LogicException`) and models that do not use the trait, declare no `$compositeKey`, or declare one that omits the primary key (throws `InvalidUsageException`) at wrap time. The wrapper is opaque to `SerializesModels`, so PHP's standard serialization captures its state directly. Each call to `restore()` issues one database query without global scopes and on the write connection, exactly like `SerializesModels::restoreCollection()`, so a member soft-deleted after queueing is still restored.

If you declare `$compositeKey` on a model whose array does not contain the value of `$primaryKey`, the trait throws `Awobaz\Compoships\Exceptions\InvalidUsageException` on the first save, delete, or refresh. The array must enumerate the whole primary key.

If you mutate a discriminator column in memory before calling `save()` (for example, `$user->tenant_id = $newTenant`), the UPDATE still targets the row as it exists in storage. The WHERE clause uses the original raw value from `$model->original`, then the SET clause writes the new value.

Nullable composite-key columns are supported. When the original raw value of a column is `null`, the trait emits `WHERE column IS NULL` rather than binding `null` into a `=` predicate (which SQL evaluates as never-true). This makes `$compositeKey` safe to use as a composite scoping key for tables that use a `UNIQUE(...)` index with a nullable discriminator rather than a strict composite primary key.

#### Note for consumers with their own override

If your model already overrides `setKeysForSaveQuery()` or `setKeysForSelectQuery()`, call `parent::setKeysForSaveQuery($query)` (and the select equivalent) first to inherit the composite key handling. Without `parent::`, the override loses the composite WHERE silently.

## Behaviour changes in 3.2.0

3.2.0 fixes a set of correctness issues found in an audit of the relation, query-builder and queue code. Most are invisible unless you hit the bug, but these change observable behaviour:

- Eager loading a composite relation whose key has a `null` component now returns the same rows as lazy loading (previously the eager path returned nothing for such parents). Keys whose components are all `null` no longer issue a query.
- `create()`, `createMany()`, `save()`, and `saveMany()` on `hasOne`/`hasMany` relations (scalar and composite) now honour `withAttributes()` and set the inverse relation, via Laravel's `setForeignAttributesForCreate()` hook; the package's own `save()`/`create()` copies are gone.
- A composite `belongsTo` on a child whose foreign key attributes are missing or all `null` resolves to the default model without querying, instead of returning an arbitrary parent.
- `sync()`/`toggle()` on composite `belongsToMany` read `[$scalarId => $attributes]` entries the same way `attach()` does, and insert new rows in one statement.
- `touch()` and `$touches` on composite relations update exactly the related rows identified by their composite key.
- Composite `whereIn` on SQL Server compiles an exact predicate (see the SQL Server note above); on every driver, unqualified column names are no longer table-prefixed by the builder.
- A custom query grammar on the connection is preserved instead of being replaced by the package grammar.
- `QueueableCompositeCollection::restore()` runs without global scopes and on the write connection.
- Eager loading or writing through a relation with an expression foreign key throws `InvalidUsageException` instead of silently loading nothing or failing inside the database.
- Composite pivot queueable ids are JSON; the 3.1.x colon format is still decoded until 4.0.
- Misconfigured key arrays (different lengths, or an array paired with a scalar) throw `InvalidUsageException` when the relation is defined.

## Support for nullable columns in 2.x

Version 2.x brings support for nullable columns. The results may now be different than on version 1.x when a column is null on a relationship, so we bumped the version to 2.x, as this might be a breaking change.

## Scope

**Compoships** targets two specific gaps in Eloquent:

1. Defining `hasOne`, `hasMany`, `belongsTo`, and `belongsToMany` relationships across multiple columns.
2. Scoping the write path (`save`, `update`, `delete`, `refresh`, `fresh`) by every column of a composite primary key on models that opt in via `$compositeKey`.

The package does not re-implement Laravel's primary-key handling end-to-end. The following continue to use the scalar `$primaryKey`:

* `Model::find($id)` lookups.
* Route model binding.

Queue serialization (`Illuminate\Queue\SerializesModels`) is supported for composite-keyed models. See the "Composite primary keys" section for the round-trip contract.

Builder-level bulk operations (`Model::query()->...->update(...)`) continue to use whatever WHERE clauses you build. For composite-key bulk patterns, the custom Query Builder's tuple `whereIn` works directly:

```php
Model::whereIn(['id', 'tenant_id'], [['u1', 't1'], ['u2', 't2']])->update(['name' => 'X']);
```

Most Laravel applications work best with a single scalar primary key. Compoships exists for the cases where the schema is not under your control (third-party databases, legacy systems, partitioned or multi-tenant tables) or where matching multiple columns in a relationship definition is unavoidable.

## Contributing

Please read [CONTRIBUTING.md](https://github.com/topclaudy/compoships/blob/master/CONTRIBUTING.md) for details on our code of conduct, and the process for submitting pull requests.


[![](https://sourcerer.io/fame/topclaudy/topclaudy/compoships/images/0)](https://sourcerer.io/fame/topclaudy/topclaudy/compoships/links/0)
[![](https://sourcerer.io/fame/topclaudy/topclaudy/compoships/images/1)](https://sourcerer.io/fame/topclaudy/topclaudy/compoships/links/1)
[![](https://sourcerer.io/fame/topclaudy/topclaudy/compoships/images/2)](https://sourcerer.io/fame/topclaudy/topclaudy/compoships/links/2)
[![](https://sourcerer.io/fame/topclaudy/topclaudy/compoships/images/3)](https://sourcerer.io/fame/topclaudy/topclaudy/compoships/links/3)
[![](https://sourcerer.io/fame/topclaudy/topclaudy/compoships/images/4)](https://sourcerer.io/fame/topclaudy/topclaudy/compoships/links/4)
[![](https://sourcerer.io/fame/topclaudy/topclaudy/compoships/images/5)](https://sourcerer.io/fame/topclaudy/topclaudy/compoships/links/5)
[![](https://sourcerer.io/fame/topclaudy/topclaudy/compoships/images/6)](https://sourcerer.io/fame/topclaudy/topclaudy/compoships/links/6)


## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [tags on this repository](https://github.com/topclaudy/compoships/tags).

## Unit Tests

To run unit tests you have to use PHPUnit

Install compoships repository
```bash
git clone https://github.com/topclaudy/compoships.git
cd compoships
composer install
```
Run PHPUnit
```bash
./vendor/bin/phpunit
```

### Running the full CI matrix locally

The package is tested against multiple Laravel and PHP versions in CI. To reproduce that matrix on your machine without setting up each PHP version manually, use the bundled Docker runner:

```bash
./run-matrix-tests.sh
```

The script mirrors `.github/workflows/run-tests.yml` exactly. It iterates over every Laravel and PHP combination, installs the requested Laravel version with Composer inside an ephemeral Docker container, runs PHPUnit, and prints a pass/fail summary at the end. One extra job installs the lowest dependency versions admitted by `composer.json` (`--prefer-lowest` on PHP 8.2 with Laravel 12) so the declared floors stay true. Docker is the only prerequisite.

You can narrow the run to a subset by passing a filter argument that matches against the matrix label (`L<laravel> - PHP<php> - <stability>`):

```bash
./run-matrix-tests.sh "12.*"          # only Laravel 12 combinations
./run-matrix-tests.sh "PHP8.4"        # only PHP 8.4 combinations
./run-matrix-tests.sh "prefer-lowest" # only the lowest-dependency job
```

## Authors

* [Claudin J. Daniel](https://github.com/topclaudy) - *Initial work*

## Support This Project

<a href='https://paypal.me/awobaz' target='_blank'><img height='35' style='border:0px;height:46px;' src='https://az743702.vo.msecnd.net/cdn/kofi3.png?v=0' border='0' alt='Buy Me a Coffee via Paypal' />

## License

**Compoships** is licensed under the [MIT License](http://opensource.org/licenses/MIT).
