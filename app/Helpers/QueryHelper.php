<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use APp\Models\User;

if (!function_exists('addJoin')) {
    function addJoin($query, $request)
    {
        if ($request->has('trashed') && $request->trashed == true)
            $query = $query->withTrashed();
        elseif ($request->has('onlyTrashed') && $request->onlyTrashed == true)
            $query = $query->onlyTrashed();

        if (!$request->has('joinWith')) return $query;

        $relations = explode(',', str_replace(' ', '', $request->joinWith));

        foreach ($relations as $relation) {
            $joins = explode('~', $relation);

            if (count($joins) > 1) {
                // Handle relation with filters
                $filters = [];
                $columns = [];

                $filterParts = explode('-', $joins[1]);

                foreach ($filterParts as $filter) {
                    // Check if this part specifies columns (using @ symbol)
                    if (strpos($filter, '@') === 0) {
                        $columns = explode('|', substr($filter, 1));
                        continue;
                    }

                    $parts = explode(':', $filter);
                    $filters[] = [
                        'column' => $parts[0],
                        'value' => $parts[1]
                    ];
                }

                $query = $query->with([
                    $joins[0] => function ($q) use ($filters, $columns) {
                        // Apply filters
                        foreach ($filters as $filter) {
                            $q->where($filter['column'], $filter['value']);
                        }

                        // Select specific columns if specified
                        if (!empty($columns)) {
                            $q->select(array_merge(['id'], $columns));
                        }
                    }
                ]);

            } else {
                // Handle simple relation with optional columns
                $relationParts = explode('@', $joins[0]);
                $relationName = $relationParts[0];

                if (count($relationParts) > 1) {
                    // Relation with specific columns
                    $columns = explode('|', $relationParts[1]);

                    $query = $query->with([
                        $relationName => function ($q) use ($columns) {
                            $q->select(array_merge(['id'], $columns));
                        }
                    ]);
                } else {
                    // Regular relation
                    $query = $query->with($relationName);
                }

                if ($request->has('request_from') && $request->request_from === 'dropdown' && !$request->has('include_join') || $request->include_join != false) {
                    $query = $query->has($relationName);
                }
            }
        }

        if ($request->has('have_not_join'))
            $query = $query->doesntHave($request->have_not_join);

        if ($request->has('has_join'))
            $query = $query->has($request->has_join);

        return $query;
    }
}
