# Replicata

Replicata is a simple Laravel package that provides a static class for replicating any Eloquent model along with its specified relationships.

## Installation

To install Replicata, use Composer:

```sh
composer require noodleware/replicata
```

## Usage

Replicata allows you to quickly replicate a model and its related data with a simple static method call.

### Example

```php
use Noodleware\Replicata\Replicata;

$model = Model::find(1);

$clonedModel = Replicata::replicate($model, ['relation1', 'relation2.subRelation1']);
```

This will:
- Clone the given model.
- Clone the specified relationships, including nested relationships if provided.

## Supported Relationship Types

Replicata supports the following relationship types:

- `BelongsToMany`
- `HasMany`
- `HasOne`
- `MorphMany`
- `MorphOne`
- `MorphToMany`

---

### License

This package is open-source under the [MIT License](LICENSE).

