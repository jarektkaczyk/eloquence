# Sofa/Eloquence

[![Build Status](https://travis-ci.org/jarektkaczyk/eloquence.svg)](https://travis-ci.org/jarektkaczyk/eloquence) [![Coverage Status](https://coveralls.io/repos/jarektkaczyk/eloquence/badge.svg)](https://coveralls.io/r/jarektkaczyk/eloquence) [![Code Quality](https://scrutinizer-ci.com/g/jarektkaczyk/eloquence/badges/quality-score.png)](https://scrutinizer-ci.com/g/jarektkaczyk/eloquence) [![Downloads](https://poser.pugx.org/sofa/eloquence/downloads)](https://packagist.org/packages/sofa/eloquence) [![stable](https://poser.pugx.org/sofa/eloquence/v/stable.svg)](https://packagist.org/packages/sofa/eloquence)

Easy and flexible extensions for the [Eloquent ORM (Laravel 5.3)](https://laravel.com/docs/5.3/eloquent).

**If I'm saving you some time with my work, you can back me up on [Patreon page](https://patreon.com/jarektkaczyk).**

For older versions of Illuminate/Laravel please use:
- 5.2.* -> [5.2](https://github.com/jarektkaczyk/eloquence/tree/5.2) branch.
- 5.1.* -> [5.1](https://github.com/jarektkaczyk/eloquence/tree/5.1) branch.
- 5.0.* -> [0.4](https://github.com/jarektkaczyk/eloquence/tree/0.4) branch.

Currently available extensions:

1. `Searchable` query - crazy-simple fulltext search through any related model (based on https://github.com/nicolaslopezj/searchable only written from scratch & greatly improved)
1. `Validable` - self-validating models
2. `Mappable` -map attributes to table fields and/or related models
3. `Metable` - meta attributes made easy
4. `Mutable` - flexible attribute get/set mutators with quick setup (with help of [Romain Lanz](https://github.com/RomainLanz))
5. `Mutator` - pipe-based mutating

**Check the [documentation](https://github.com/jarektkaczyk/eloquence/wiki) for installation and usage info, [website](http://softonsofa.com/tag/eloquence/) for examples and [API reference](http://jarektkaczyk.github.io/eloquence-api)**

## Contribution

All contributions are welcome, PRs must be **tested** and **PSR-2 compliant**.

To validate your builds before committing use the following composer command:
```bash
composer test
```
