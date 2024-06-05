<?php

namespace App\Actions\Common;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

trait BaseQueryBuilderConfig
{
    public function getTableFields(): array
    {
        return array_merge($this->getFillable(), [
            $this->getKeyName(),
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
        ]);
    }

    public function getAllowedFilters(): array
    {
        $exactFilters = $this->getExactFilters();

        $tableFields = $this->getTableFields();

        foreach ($this->discardedFieldsInFilter as $fields) {
            $index = array_search($fields, $this->getTableFields());
            unset($tableFields[$index]);
        }

        return Collection::make($tableFields)
            ->map(function (string $field) use ($exactFilters) {
                return in_array($field, $exactFilters) ? AllowedFilter::exact($field) :
                    AllowedFilter::partial($field);
            })
            ->merge($this->getRelationshipFilters())
            ->merge($this->getExtraFilters())
            ->toArray();
    }

    protected function getRelationshipFilters(): array
    {
        $relationshipFilters = [];
        foreach ($this->allowedRelationshipFilters as $relationship) {
            $chunks = explode(':', $relationship);
            $relationship = $chunks[0];

            $fields = [];
            if (count($chunks) === 2 && (bool) $chunks[1]) {
                $fields = explode(',', $chunks[1]);
            }

            $relationshipFields = $this->getRelationshipFields($relationship);
            $model = $relationshipFields['model'];
            $relationshipFields = $relationshipFields['relationshipFields'];

            if (count($fields) === 0) {
                $fields = $relationshipFields;
            } else {
                $fields = array_intersect($fields, $relationshipFields);
            }

            foreach ($fields as $field) {
                $relationshipFilters[] = AllowedFilter::custom(
                    $relationship.'.'.$field,
                    new BaseRelationshipFilter(
                        $relationship,
                        $field,
                        in_array($field, $model->getExactFilters())
                    )
                );
            }
        }

        return $relationshipFilters;
    }

    private function getRelationshipFields(string $relationship): array
    {
        if (str_contains($relationship, '.')) {
            $model = $this;
            // nested relationship
            $relationships = explode('.', $relationship);
            foreach ($relationships as $relationship) {
                $relationship = Str::camel($relationship);
                $model = $model->$relationship()->getModel();
            }
        } else {
            $relationship = Str::camel($relationship);
            /** @var static $model */
            $model = $this->$relationship()->getModel();
        }

        return [
            'model' => $model,
            'relationshipFields' => $model->getTableFields(),
        ];
    }

    protected function getExtraFilters(): array
    {
        return [];
    }

    public function getAllowedSorts(): array
    {
        return Collection::make($this->getTableFields())
            ->map(function (string $field) {
                return AllowedSort::field($field);
            })
            ->merge($this->getExtraSorts())
            ->toArray();
    }

    protected function getExtraSorts(): array
    {
        return [];
    }

    public function getAllowedFields(): array
    {
        return $this->getTableFields();
    }

    public function getAllowedAppends(): array
    {
        return $this->allowedAppends;
    }

    public function getAllowedIncludes(): array
    {
        return array_merge($this->allowedIncludes, $this->getExtraIncludes());
    }

    protected function getExtraIncludes(): array
    {
        return [];
    }
}
