<?php

namespace Noodleware\Replicata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Replicata
{
    /**
     * Deep duplicate a model along with specified relationships.
     *
     * @param  Model  $model  The model to duplicate.
     * @param  array  $relations  Array of relationship paths to duplicate, e.g., ['lines', 'lines.items'].
     * @return Model The duplicated model.
     */
    public static function replicate(Model $model, array $relations = []): Model
    {
        // Create a shallow copy of the model (excluding the primary key, etc.)
        $newModel = $model->replicate();
        $newModel->push(); // Save to generate an ID for nested relations

        // Process each relation path provided.
        foreach ($relations as $relationPath) {
            static::duplicateRelation($model, $newModel, $relationPath);
        }

        return $newModel;
    }

    /**
     * Duplicate a relationship for a given relation path.
     *
     * Supports dot notation for nested relationships.
     *
     * @param  Model  $original  The original model.
     * @param  Model  $new  The duplicated model.
     * @param  string  $relationPath  Dot-separated relation path, e.g., 'lines.items'.
     */
    protected static function duplicateRelation(Model $original, Model $new, string $relationPath): void
    {
        $parts = explode('.', $relationPath);
        $relationName = array_shift($parts);

        if (! method_exists($original, $relationName)) {
            return;
        }

        $relation = $original->$relationName();

        // Map relation types to handlers using a consistent duplication logic.
        $handlers = [
            // Singular relationships
            HasOne::class => function ($relation, Model $original, Model $new, string $relationName, array $nestedParts) {
                $related = $original->$relationName;
                if ($related) {
                    $newRelated = self::duplicateRelated($related, $nestedParts);
                    $new->$relationName()->save($newRelated);
                }
            },
            MorphOne::class => function ($relation, Model $original, Model $new, string $relationName, array $nestedParts) {
                $related = $original->$relationName;
                if ($related) {
                    $newRelated = self::duplicateRelated($related, $nestedParts);
                    $new->$relationName()->save($newRelated);
                }
            },
            // Collection relationships
            HasMany::class => function ($relation, Model $original, Model $new, string $relationName, array $nestedParts) {
                foreach ($original->$relationName as $related) {
                    $newRelated = self::duplicateRelated($related, $nestedParts);
                    $new->$relationName()->save($newRelated);
                }
            },
            MorphMany::class => function ($relation, Model $original, Model $new, string $relationName, array $nestedParts) {
                foreach ($original->$relationName as $related) {
                    $newRelated = self::duplicateRelated($related, $nestedParts);
                    $new->$relationName()->save($newRelated);
                }
            },
            // Many-to-many relationships (extracted logic)
            BelongsToMany::class => function ($relation, Model $original, Model $new, string $relationName, array $nestedParts) {
                self::duplicateManyToMany($original, $new, $relationName, $nestedParts);
            },
            MorphToMany::class => function ($relation, Model $original, Model $new, string $relationName, array $nestedParts) {
                self::duplicateManyToMany($original, $new, $relationName, $nestedParts);
            },
        ];

        // Loop through the mapping and execute the first matching handler.
        foreach ($handlers as $class => $handler) {
            if ($relation instanceof $class) {
                $handler($relation, $original, $new, $relationName, $parts);
                break;
            }
        }
    }

    /**
     * Duplicate a many-to-many relationship.
     */
    private static function duplicateManyToMany(Model $original, Model $new, string $relationName, array $nestedParts): void
    {
        $collection = $original->$relationName;
        $newIds = [];
        foreach ($collection as $related) {
            $newRelated = self::duplicateRelated($related, $nestedParts);
            $newIds[] = $newRelated->id;
        }
        $new->$relationName()->sync($newIds);
    }

    /**
     * Duplicate a related model with optional nested relations.
     *
     * @param  Model  $related  The related model to duplicate.
     * @param  array  $nestedParts  An array of nested relation parts.
     * @return Model The duplicated related model.
     */
    private static function duplicateRelated(Model $related, array $nestedParts): Model
    {
        $newRelated = $related->replicate();
        $newRelated->push();
        if (! empty($nestedParts)) {
            static::duplicateRelation($related, $newRelated, implode('.', $nestedParts));
        }

        return $newRelated;
    }
}
