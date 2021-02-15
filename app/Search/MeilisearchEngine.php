<?php


namespace App\Search;


use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Searchable;

class MeilisearchEngine extends \Meilisearch\Scout\Engines\MeilisearchEngine
{
    public function map(Builder $builder, $results, $model)
    {
        $hits = collect($results['hits'] ?? []);

        return parent::map($builder, $results, $model)->each(
            function (Searchable|Model $model) use ($results, $hits) {
                if ($hit = $hits->firstWhere('id', $model->getKey())) {
                    $model->withScoutMetadata('formatted', $hit['_formatted'] ?? null);
                    $model->withScoutMetadata('matches_info', $hit['_matchesInfo'] ?? null);
                }
            }
        );
    }
}
