Casterlith DataMapper ORM
========================

[Github](https://github.com/merry-goblin/casterlith-composer)

### Sample

[Sample & documentation & unit tests](https://github.com/merry-goblin/casterlith)

### Install

- composer require merry-goblin/casterlith:"dev-master"

### Version of DBAL

If you want to force compatibility with PHP 5.3 no matter your version of PHP installed replace in composer.json

```
    "require": {
        "php": ">=5.3.0"
    },
```

by

```
    "require": {
        "php": ">=5.3.0",
        "doctrine/dbal": "^2.5"
    },
```

--------------------------

author : [alexandre keller](https://github.com/merry-goblin)
