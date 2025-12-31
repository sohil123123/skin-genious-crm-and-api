<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Models\User;

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

// -------------------INFO: Common Filter functions -----------------------------------
if (!function_exists('addWhere')) {
    function addWhere($query, $request, $filter = [])
    {
        $filters = collect($request->has('filterArray') ? json_decode($request->filterArray, true) ?? []: []);

        if (!empty($filter))
            $filters = collect($filter)->merge($filters);

        if ($request->has(['filterBy', 'filterValue'])) {
            $filters->push([
                'column' => $request->filterBy,
                'value' => $request->filterValue,
                'condition' => $request->filterCondition ?? '=',
                'method' => $request->filterMethod ?? 'where',
            ]);
        }
        return filterByArray($query, $filters);
    }
}

if (!function_exists('filterByArray')) {
    function filterByArray($query, $filters)
    {
        // NOTE: return if filters is empty
        if ($filters->isEmpty()) return $query;

        // NOTE: Separate "OR" conditions from "AND" conditions
        $orConditions = $filters->filter(fn($f) => Str::lower($f['method'] ?? 'where') === 'or_where')->values()->all();
        $andConditions = $filters->filter(fn($f) => Str::lower($f['method'] ?? 'where') === 'where')->values()->all();

        // NOTE: Apply "OR" conditions first (grouped in parentheses)
        if (!empty($orConditions)) {
            $query = $query->where(function ($query) use ($orConditions) {
                applyConditions($query, $orConditions, 'orWhere');
            });
        }
        // NOTE: Apply "AND" conditions
        $query = applyConditions($query, $andConditions, 'where');

        return $query;
    }
}

if (!function_exists('applyConditions')) {
    function applyConditions($query, array $conditions, string $defaultMethod)
    {
        foreach ($conditions as $filter) {
            $column = Str::of($filter['column'])->trim()->replace(' ', '')->__toString();

            // NOTE: Skip if column doesn't exist in the table
            // if (!Schema::hasColumn($query->getModel()->getTable(), $column)) continue;

            $type = (isset($filter['type']) && $filter['type']) ? Str::lower($filter['type']) : null;

            $query = matchCondition(
                $query,
                $column,
                $filter['condition'] ?? '=',
                $filter['value'] ?? null,
                $defaultMethod,
                $type,
            );
        }

        return $query;
    }
}

if (!function_exists('matchCondition')) {
    function matchCondition($query, $column, $condition, $value, $method, $type = null)
    {
        $condition = Str::lower($condition);
        $value = Str::of($value)->trim();

        // INFO: Handle date-specific conditions first
        if (in_array($type, ['date', 'month', 'year', 'day']))
            return handleDateCondition($query, $column, $condition, $value, $method, $type);

        // INFO: Rest of your existing condition handling...
        return handleStandardCondition($query, $column, $condition, $value, $method);
    }
}

if (!function_exists('handleDateCondition')) {
    function handleDateCondition($query, $column, $condition, $value, $method, $dateType)
    {
        if ($dateType == 'date' && in_array($condition, ['between', 'not_between'])){
            $filterMethod = $method . ucfirst(Str::camel($condition));
            $dates = array_map('trim', explode(',', $value));

            $startDate = Carbon::parse($dates[0])->startOfDay();
            $endDate = Carbon::parse($dates[1])->endOfDay();

            return $query->$filterMethod($column, [$startDate, $endDate]);
        }

        // INFO: Handle simple comparisons (date, month, year, day)
        $filterMethod = $method . ucfirst(Str::camel($dateType));

        // INFO: Map conditions to operators
        $operator = match($condition) {
            'eq', '=' => '=',
            'lt', '<' => '<',
            'lteq', '<=' => '<=',
            'gt', '>' => '>',
            'gteq', '>=' => '>=',
            default => null,
        };

        $value = ($dateType == 'date') ? Carbon::parse($value)->format('Y-m-d') : $value->value();

        return $operator ? $query->$filterMethod($column, $operator, $value) : $query;
    }
}

if (!function_exists('handleStandardCondition')) {
    function handleStandardCondition($query, $column, $condition, $value, $method)
    {
        $conditionHandlers = [
            'eq' => fn($q) => $q->where($column, '=', $value),
            '=' => fn($q) => $q->where($column, '=', $value),
            'lt' => fn($q) => $q->where($column, '<', $value),
            '<' => fn($q) => $q->where($column, '<', $value),
            '<=' => fn($q) => $q->where($column, '<=', $value),
            'gt' => fn($q) => $q->where($column, '>', $value),
            '>' => fn($q) => $q->where($column, '>', $value),
            '>=' => fn($q) => $q->where($column, '>=', $value),
            'contains' => fn($q) => $q->where($column, 'LIKE', '%'.$value.'%'),
            'like' => fn($q) => $q->where($column, 'LIKE', '%'.$value.'%'),
            'not_contains' => fn($q) => $q->where($column, 'NOT LIKE', '%'.$value.'%'),
            'not_like' => fn($q) => $q->where($column, 'NOT LIKE', '%'.$value.'%'),
            'start_with' => fn($q) => $q->where($column, 'LIKE', $value.'%'),
            'not_start_with' => fn($q) => $q->where($column, 'NOT LIKE', $value.'%'),
            'end_with' => fn($q) => $q->where($column, 'LIKE', '%'.$value),
            'not_end_with' => fn($q) => $q->where($column, 'NOT LIKE', '%'.$value),
            'between' => fn($q) => applyArrayCondition($q, $column, $value, $method, 'Between'),
            'not_between' => fn($q) => applyArrayCondition($q, $column, $value, $method, 'NotBetween'),
            'in' => fn($q) => applyArrayCondition($q, $column, $value, $method, 'In'),
            'not_in' => fn($q) => applyArrayCondition($q, $column, $value, $method, 'NotIn'),
            'include' => fn($q) => applyArrayCondition($q, $column, $value, $method, 'In'),
            'exclude' => fn($q) => applyArrayCondition($q, $column, $value, $method, 'NotIn'),
            'null' => fn($q) => $q->whereNull($column),
            'not_null' => fn($q) => $q->whereNotNull($column),
        ];

        $handler = $conditionHandlers[$condition] ?? fn($q) => $q;
        return $handler($query);
    }
}

if (!function_exists('applyArrayCondition')) {
    function applyArrayCondition($query, $column, $value, $method, $suffix)
    {
        $values = array_map('trim', explode(',', $value));
        $method = $method . $suffix;

        return $query->$method($column, $values);
    }
}