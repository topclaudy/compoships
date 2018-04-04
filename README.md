Compoships
==========

**Compoships** offers the ability to specify relationships based on two (or more) columns in Laravel 5's Eloquent. The need to match multiple columns in the definition of an Eloquent relationship often arises when working with third party or pre existing schema/database. Check the discussion [here](https://laravel.io/forum/08-02-2014-belongsto-relationship-with-2-foreign-keys).

## The problem

Eloquent doesn't support composite keys. As a consequence, there is no way to define a relationship from one model to another by matching more than one column. Trying to use `where clauses` (like in the example below) won't work when eager loading the relationship because at the time the relationship is processed **$this->f2** is null. 

```php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Foo extends Model
{
    public function bars()
    {
        return $this->hasMany('Bar', 'f1', 'f1')->where('f2', $this->f2);
    }
}
```

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
 
**Note:** To define a multiple keys relationship from a model *A* to another model *B*, **both models must either extend `Awobaz\Compoships\Database\Eloquent\Model` or use the `Awobaz\Compoships\Compoships` trait**

### Syntax

... and now we can define a relationship from a model *A* to another model *B* by matching two or more columns (by passing an arrays of fields instead of a string). 

```php
namespace App;

use Illuminate\Database\Eloquent\Model;

class A extends Model
{
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

Thank you for considering contributing to **Compoships**! The following steps are recommended to contribute:

* Fork this repository.
* Add new features, fix bug or bring improvements.
* Make a pull request.

### Contributor Code of Conduct

As contributors and maintainers of this project, we pledge to respect all people who contribute through reporting issues,
posting feature requests, updating documentation, submitting pull requests or patches, and other activities.

We are committed to making participation in this project a harassment-free experience for everyone, regardless of level
of experience, gender, gender identity and expression, sexual orientation, disability, personal appearance, body size,
race, age, or religion.

Examples of unacceptable behavior by participants include the use of sexual language or imagery, derogatory comments or
personal attacks, trolling, public or private harassment, insults, or other unprofessional conduct.

Project maintainers have the right and responsibility to remove, edit, or reject comments, commits, code, wiki edits,
issues, and other contributions that are not aligned to this Code of Conduct. Project maintainers who do not follow the
Code of Conduct may be removed from the project team.

Instances of abusive, harassing, or otherwise unacceptable behavior may be reported by opening an issue or contacting one
or more of the project maintainers.

This Code of Conduct is adapted from the [Contributor Covenant](http:contributor-covenant.org), version 1.0.0, available at [http://contributor-covenant.org/version/1/0/0/](http://contributor-covenant.org/version/1/0/0/)

## TODO

* Unit Tests

## Author

* [Claudin J. Daniel](https://github.com/topclaudy)

## License

**Compoships** is licensed under the [MIT License](http://opensource.org/licenses/MIT).