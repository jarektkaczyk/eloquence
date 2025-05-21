# Sofa/Eloquence

[![GitHub Tests Action Status](https://github.com/jarektkaczyk/eloquence/workflows/Tests/badge.svg)](https://github.com/jarektkaczyk/eloquence/actions?query=workflow%3Atests+branch%3Amaster) [![Downloads](https://poser.pugx.org/sofa/eloquence/downloads)](https://packagist.org/packages/sofa/eloquence) [![stable](https://poser.pugx.org/sofa/eloquence/v/stable.svg)](https://packagist.org/packages/sofa/eloquence)

Easy and flexible extensions for the [Eloquent ORM](https://laravel.com/docs/eloquent).

Currently available extensions:

1. [Searchable](https://github.com/jarektkaczyk/eloquence-base) query - crazy-simple fulltext search through any related model 
1. [Validable](https://github.com/jarektkaczyk/eloquence-validable) - self-validating models
2. [Mappable](https://github.com/jarektkaczyk/eloquence-mappable) -map attributes to table fields and/or related models
3. [Metable](https://github.com/jarektkaczyk/eloquence-metable) - meta attributes made easy
4. [Mutable](https://github.com/jarektkaczyk/eloquence-mutable) - flexible attribute get/set mutators with quick setup 
5. [Mutator](https://github.com/jarektkaczyk/eloquence-mutable) - pipe-based mutating

By installing this package you get aforementioned extensions. Alternatively you can pull just single extension:

```bash
# get all extensions
composer require sofa/eloquence 

# get single extension, eg. Metable
composer require sofa/eloquence-metable
```

**Check the [documentation](https://github.com/jarektkaczyk/eloquence/wiki) for installation and usage info, [API reference](http://jarektkaczyk.github.io/eloquence-api)**

## Contribution

Shout out to all the Contributors!

All contributions are welcome, PRs must be **tested** and **PSR-2 compliant** - refer to particular extension repository.
