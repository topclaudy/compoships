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

If for some reasons you can't derive your models from `Awobaz\Compoships\Database\Eloquent\Model`, you may take advantage of the `Awobaz\Compoships\Compoships` trait. Simply use the trait in your models.
 
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

### Factories

Chances are that you may need factories for your Compoships models. If so, you will provably need to use
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
## Supported relationships

**Compoships** only supports the following Laravel's Eloquent relationships:

* hasOne
* HasMany
* belongsTo

Also please note that while **nullable columns are supported by Compoships**, relationships with only null values are not currently possible.

## Support for nullable columns in 2.x

Version 2.x brings support for nullable columns. The results may now be different than on version 1.x when a column is null on a relationship, so we bumped the version to 2.x, as this might be a breaking change.

## Disclaimer

**Compoships** doesn't bring support for composite keys in Laravel's Eloquent. This package only offers the ability to specify relationships based on more than one column. In a Laravel project, it's recommended for all models' tables to have a single primary key. But there are situations where you'll need to match many columns in the definition of a relationship even when your models' tables have a single primary key.

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

## Authors

* [Claudin J. Daniel](https://github.com/topclaudy) - *Initial work*

## Support This Project

<a href='https://paypal.me/awobaz' target='_blank'><img height='35' style='border:0px;height:46px;' src='https://az743702.vo.msecnd.net/cdn/kofi3.png?v=0' border='0' alt='Buy Me a Coffee via Paypal' />

## Sponsored by

* [Awobaz](https://awobaz.com) - Web/Mobile agency based in Montreal, Canada

## License

**Compoships** is licensed under the [MIT License](http://opensource.org/licenses/MIT).
