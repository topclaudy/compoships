Compoships
==========

**Compoships** offers the ability to specify relationships based on two (or more) columns in Laravel 5's Eloquent. The need to match multiple columns in the definition of an Eloquent relationship often arises when working with third party or pre existing schema/database. 

## The problem

Eloquent doesn't support composite keys. As a consequence, there is no way to define a relationship from one model to another by matching more than one column. Trying to use `where clauses` (like in the example below) won't work when eager loading the relationship because at the time the relationship is processed **$this->f2** is null. 

```php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Foo extends Model
{
    public function bars()
    {
        //WON'T WORK WITH EAGER LOADING!!!
        return $this->hasMany('Bar', 'f1', 'f1')->where('f2', $this->f2);
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
        return $this->hasMany('B', ['f1', 'f2'], ['f1', 'f2']);
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
        return $this->belongsTo('A', ['f1', 'f2'], ['f1', 'f2']);
    }
}
```
## Supported relationships

**Compoships** only supports the following Laravel 5's Eloquent relationships:

* hasOne
* HasMany
* belongsTo

## Disclaimer

**Compoships** doesn't bring support for composite keys in Laravel 5's Eloquent. This package only offers the ability to specify relationships based on more than one column. We believe that all models' tables should have a single primary key. But there are situations where you'll need to match many columns in the definition of a relationship even when your models' tables have a single primary key.

## Contributing

Please read [CONTRIBUTING.md](https://github.com/topclaudy/compoships/blob/master/CONTRIBUTING.md) for details on our code of conduct, and the process for submitting pull requests.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [tags on this repository](https://github.com/topclaudy/compoships/tags).

## Unit Tests

In order to run the test suite, install the development dependencies:

```bash
$ composer install --dev
```

Then, run the following command:

```bash
$ vendor/bin/phpunit
```

## Authors

* [Claudin J. Daniel](https://github.com/topclaudy) - *Initial work*

## License

**Compoships** is licensed under the [MIT License](http://opensource.org/licenses/MIT).