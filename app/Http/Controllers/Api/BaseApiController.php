<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

use Carbon\Carbon;
use DB;
use Mail;
use Validator;
use Helper;
use Str;
use Auth;
use Storage;

use App\Traits\ResponseAPI;
use App\Traits\Authorizable;

abstract class BaseApiController extends Controller
{
    use ResponseAPI;

    protected $orderBy = 'id';
    protected $orderType = 'ASC';
    protected $maxPerPage = 50;
    protected $currentPage = 1;
    protected $model;
    protected $request;
    protected $resourceClass;
    protected $crud_name;
    protected $module;

    /**
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Model $model
    */
    public function __construct(Model $model = null, Request $request = null, $crud_name = null, $module = null)
    {
        $this->model = $model;
        $this->request = $request;
        $this->crud_name = $crud_name;
        $this->module = $module;
        $this->paginateOption($request);
        $this->setResourceClass();
    }

    // ------------------------------------------------INFO: Common-------------------------------------------------------
    public function index(Request $request)
    {
        $query = $this->addWhere($this->model);
        $query = $this->joinTable($query);
        $query = $this->selectColumns($query);
        $query = $this->searchByAll($query, $this->model);
        // $query = $this->addJoin($query);
        $query = addJoin($query, $request);
        $result = $this->resultType($query);
        return $this->handleIndexResponse($result);
        // return $this->success($this->crud_name.'s get successfully', $result);
    }

    public function show(string $id)
    {
        $query = $this->addWhere($this->model);

        // $result =  $this->addJoin($query)->find($id);
        $result =  addJoin($query, $this->request)->find($id);

        if (!$result)
            return $this->error('Not Found Error.', [], config('constants.HTTP_NOT_FOUND'));

        return $this->success($this->crud_name.' get successfully', $result);
    }

    public function destroy(Request $request, string $id)
    {
        $query = $this->addWhere($this->model);

        $result = $query->find($id);

        if (!$result)
            return $this->error('Not Found Error.', [], config('constants.HTTP_NOT_FOUND'));

        $class_name = class_basename($this->model);

        if($class_name == 'Donor' && $class_name == 'TaktiName'){
            // Delete File
            if(!empty($result->image))
                Helper::deleteFileIfExist('images', $result->image);
        }
        elseif($class_name == 'Transaction'){
                // Delete File
                if(!empty($item->file))
                    Helper::deleteFileIfExist('files', $item->file);
            }
        elseif($class_name == 'Facility'){
            // Delete File
            $old_file_name = Helper::getOnlyFileName($result->image);
            $path_thumbnail = config('project.storage.image.facility_thumbnail');

            if(!empty($result->image)){
                Helper::deleteFileIfExist('images', $result->image);
                Helper::deleteFileIfExist('images', $path_thumbnail . $old_file_name);
            }
        }
        elseif($class_name == 'FunctionType'){
                // Delete File
                $old_file_name = Helper::getOnlyFileName($item->image);
                $path_thumbnail = config('project.storage.image.function_type_thumbnail');
                $path_image_120x120 = config('project.storage.image.function_type_120x120');

                if(!empty($item->image)){
                    Helper::deleteFileIfExist('images', $item->image);
                    Helper::deleteFileIfExist('images', $path_thumbnail . $old_file_name);
                    Helper::deleteFileIfExist('images', $path_image_120x120 . $old_file_name);
                }
            }

        $data = $result;

        $result->delete();

        return $this->success($this->crud_name.' deleted successfully.', $data);
    }

    public function bulkRemove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "ids" => "required|array",
        ]);
        if($validator->fails())
            return $this->error('Validation Error.', $validator->errors(), VALIDATION_ERROR);

        $result = $this->model->whereIn('id',$request->ids);
        if(!$result->exists())
            return $this->error('Data Not Found.', [], HTTP_NOT_FOUND);

        $data = $result->get();

        foreach ($data as $item) {
            $class_name = class_basename($this->model);

            if($class_name == 'Donor'){
                // Delete File
                if(!empty($item->image))
                    Helper::deleteFileIfExist('images', $item->image);
            }
            elseif($class_name == 'Transaction'){
                // Delete File
                if(!empty($item->file))
                    Helper::deleteFileIfExist('files', $item->file);
            }
            elseif($class_name == 'Facility'){
                // Delete File
                $old_file_name = Helper::getOnlyFileName($item->image);
                $path_thumbnail = config('project.storage.image.facility_thumbnail');

                if(!empty($item->image)){
                    Helper::deleteFileIfExist('images', $item->image);
                    Helper::deleteFileIfExist('images', $path_thumbnail . $old_file_name);
                }
            }
            elseif($class_name == 'FunctionType'){
                // Delete File
                $old_file_name = Helper::getOnlyFileName($item->image);
                $path_thumbnail = config('project.storage.image.function_type_thumbnail');
                $path_image_120x120 = config('project.storage.image.function_type_120x120');

                if(!empty($item->image)){
                    Helper::deleteFileIfExist('images', $item->image);
                    Helper::deleteFileIfExist('images', $path_thumbnail . $old_file_name);
                    Helper::deleteFileIfExist('images', $path_image_120x120 . $old_file_name);
                }
            }
        }


        $result->delete();

        return $this->success($this->crud_name.' deleted successfully.', $data);
    }

    public function status(Request $request, $id)
    {
        $result = addJoin($this->model, $request)->find(Helper::decodeHashids($id))
        ?? addJoin($this->model, $request)->find($id);

        if (!$result)
            return $this->error('Not Found Error.', [], config('constants.HTTP_NOT_FOUND'));

        if($result->is_active)
            $result->is_active = false;
        else
            $result->is_active = true;

        $result->save();
        return $this->success($this->crud_name.' status updated successfully.', $result->fresh());
    }

    // -----------------------------------------------------INFO: Other function-----------------------------------------------
    public function resultType($query, $orderByRaw = null)
    {
        if($orderByRaw){
            $query = $query->orderByRaw($orderByRaw);
        }

        $order_by_columns = remove_empty_value(array_map('trim', explode(',', $this->orderBy)));
        $order_type_values = remove_empty_value(array_map('trim', explode(',', $this->orderType)));

        foreach ($order_by_columns as $key => $column) {
            if(isset($order_type_values[$key]) && $orderType = $order_type_values[$key]){
                $query = $query->orderBy($column, $orderType);
            }
        }

        if($this->request->has('groupBy')){
            $group_by_columns = remove_empty_value(array_map('trim', explode(',', $this->request->groupBy)));
            foreach ($group_by_columns as $key => $column) {
                if($column != ''){
                    $query = $query->groupBy($column);
                }
            }
        }

        if ($this->request->has('page'))
            return $query = $query->paginate($this->maxPerPage);
        else
            return $query = $query->get();
    }

    private function paginateOption(Request $request)
    {
        if ($request->has('page') and $request->page > 0) {
            $this->currentPage = $request->get('page');
        }

        if($request->has('orderBy') && $request->orderBy != ''){
            $this->orderBy = $request->orderBy;
        }
        if($request->has('orderType') && $request->orderType != ''){
            $this->orderType = $request->orderType;
        }

        if($request->has('limit') && $request->limit > 0){
            $this->maxPerPage = $request->limit;
        }

    }

    protected function setResourceClass()
    {
        $modelName = class_basename($this->model);
        $resourceClass = "App\Http\Resources\\{$modelName}Resource";

        if (class_exists($resourceClass)) {
            $this->resourceClass = $resourceClass;
        } else {
            $this->resourceClass = \App\Http\Resources\BaseResource::class;
        }
    }

    protected function handleIndexResponse($result)
    {
        if ($result instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $result = array_merge(
                $result->toArray(),
                ['data' => $this->resourceClass::collection($result->items())->resolve()]
            );
            return $this->success('Records retrieved successfully', $result);

            // $resource = $this->resourceClass::collection($result);
            // $transformed = $resource->response()->getData(true);
            // // dd($transformed);

            // return $this->success('Records retrieved successfully', [
            //     'data' => $transformed['data'],
            //     'pagination' => $transformed['meta'] ?? null
            // ]);
        }

        $collection = $this->resourceClass::collection($result);
        return $this->success('Records retrieved successfully', $collection);
    }

    // -------------------INFO: Common Filter functions -----------------------------------
    protected function addWhere($query, $filter = [])
    {
        $filters = collect($this->request->has('filterArray')
        ? json_decode($this->request->filterArray, true) ?? []
        : []);

        if (!empty($filter))
            $filters = collect($filter)->merge($filters);

        if ($this->request->has(['filterBy', 'filterValue'])) {
            $filters->push([
                'column' => $this->request->filterBy,
                'value' => $this->request->filterValue,
                'condition' => $this->request->filterCondition ?? '=',
                'method' => $this->request->filterMethod ?? 'where',
            ]);
        }
        return $this->filterByArrayNew($query, $filters);
    }

    protected function filterByArrayNew($query, $filters)
    {
        // NOTE: return if filters is empty
        if ($filters->isEmpty()) return $query;

        // NOTE: Separate "OR" conditions from "AND" conditions
        $orConditions = $filters->filter(fn($f) => Str::lower($f['method'] ?? 'where') === 'or_where')->values()->all();
        $andConditions = $filters->filter(fn($f) => Str::lower($f['method'] ?? 'where') === 'where')->values()->all();

        // NOTE: Apply "OR" conditions first (grouped in parentheses)
        if (!empty($orConditions)) {
            $query = $query->where(function ($query) use ($orConditions) {
                $this->applyConditions($query, $orConditions, 'orWhere');
            });
        }
        // NOTE: Apply "AND" conditions
        $query = $this->applyConditions($query, $andConditions, 'where');

        return $query;
    }

    protected function applyConditions($query, array $conditions, string $defaultMethod)
    {
        foreach ($conditions as $filter) {
            $column = Str::of($filter['column'])->trim()->replace(' ', '')->__toString();

            // NOTE: Skip if column doesn't exist in the table
            // if (!Schema::hasColumn($query->getModel()->getTable(), $column)) continue;

            $type = (isset($filter['type']) && $filter['type']) ? Str::lower($filter['type']) : null;

            $query = $this->matchCondition(
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

    protected function matchCondition($query, $column, $condition, $value, $method, $type = null)
    {
        $condition = Str::lower($condition);
        $value = Str::of($value)->trim();

        // INFO: Handle date-specific conditions first
        if (in_array($type, ['date', 'month', 'year', 'day']))
            return $this->handleDateCondition($query, $column, $condition, $value, $method, $type);

        // INFO: Rest of your existing condition handling...
        return $this->handleStandardCondition($query, $column, $condition, $value, $method);
    }

    protected function handleDateCondition($query, $column, $condition, $value, $method, $dateType)
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

    protected function handleStandardCondition($query, $column, $condition, $value, $method)
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
            'between' => fn($q) => $this->applyArrayCondition($q, $column, $value, $method, 'Between'),
            'not_between' => fn($q) => $this->applyArrayCondition($q, $column, $value, $method, 'NotBetween'),
            'in' => fn($q) => $this->applyArrayCondition($q, $column, $value, $method, 'In'),
            'not_in' => fn($q) => $this->applyArrayCondition($q, $column, $value, $method, 'NotIn'),
            'include' => fn($q) => $this->applyArrayCondition($q, $column, $value, $method, 'In'),
            'exclude' => fn($q) => $this->applyArrayCondition($q, $column, $value, $method, 'NotIn'),
            'null' => fn($q) => $q->whereNull($column),
            'not_null' => fn($q) => $q->whereNotNull($column),
        ];

        $handler = $conditionHandlers[$condition] ?? fn($q) => $q;
        return $handler($query);
    }

    protected function applyArrayCondition($query, $column, $value, $method, $suffix)
    {
        $values = array_map('trim', explode(',', $value));
        $method = $method . $suffix;

        return $query->$method($column, $values);
    }


    // -------------------INFO: Globle Search------------------------
    protected function searchByAll($query, $model)
    {
        if (!$this->request->has('filter')) return $query;

        $filter = $this->request->filter;

        // INFO: Apply when filter_fields
        if ($this->request->has('filterFields')) {
            $searchFields = $this->getSearchFields($model);
            $query = $query->where(function ($qry) use ($searchFields, $filter) {
                $this->applyFieldConditions($qry, $searchFields, $filter);
            });
        }

        // INFO: Apply when filter_join
        if ($this->request->has('filter_join')) {
            $joinFilterType = $this->request->has('filterFields') ? 'orWhereHas' : 'whereHas';
            $relations = array_map('trim', explode(',', $this->request->filter_join)); // Convert collection to array
            foreach ($relations as $i => $relation_name) {
                $table_name = Helper::getTableFromRelation($model, $relation_name);
                $searchFields = $this->getSearchFieldsForJoinTable($table_name);

                $joinFilterType = ($i == 0) ? $joinFilterType : 'orWhereHas';

                $query = $query->$joinFilterType($relation_name, function ($qry) use ($filter, $searchFields) {
                    $this->applyFieldConditions($qry, $searchFields, $filter);
                });
            }
        }

        // INFO: Apply when filter_join_fields
        if ($this->request->has('filter_join_fields')) {
            $relations = explode(',', str_replace(' ', '', $this->request->filter_join_fields));
            $joinFilterType = ($this->request->has('filterFields') || $this->request->has('filter_join')) ? 'orWhereHas' : 'whereHas';

            foreach ($relations as $i => $relation) {
                $parts = explode('~', $relation);
                if (count($parts) < 2) continue;

                [$join, $joinFieldsString] = $parts;
                $searchFields = explode('-', $joinFieldsString);

                $joinFilterType = ($i == 0) ? $joinFilterType : 'orWhereHas';

                $query = $query->$joinFilterType($join, function ($qry) use ($filter, $searchFields) {
                    $this->applyFieldConditions($qry, $searchFields, $filter);
                });
            }
        }

        // dd($query->toSql());
        // \Log::info($query->toSql(), $query->getBindings());
        return $query;
    }

    protected function getSearchFields($model)
    {
        if ($this->request->has('filterFields') && $this->request->filterFields != 'all')
            return explode(',', $this->request->filterFields);

        $excludeTypes = [
            'datetime', 'timestamp', 'time', 'date',
            'int', 'int unsigned', 'bigint unsigned', 'int(10) unsigned', 'int(11)', 'tinyint(1)'
        ];

        // $table = $this->request->has('filter_join') ? $this->request->filter_join : $model->getTable();
        $table = $model->getTable();
        $searchFields = [];
        $columns = DB::select("SHOW FULL COLUMNS FROM $table");
        foreach ($columns as $column) {
            if (!in_array($column->Type, $excludeTypes) && !($table == 'users' && $column->Field === 'password'))
                $searchFields[] = $column->Field;
        }
        return $searchFields;
    }

    protected function getSearchFieldsForJoinTable($tableName)
    {
        $excludeTypes = [
            'datetime', 'timestamp', 'time', 'date',
            'int', 'int unsigned', 'bigint unsigned', 'int(10) unsigned', 'int(11)', 'tinyint(1)'
        ];

        $searchFields = [];
        $columns = DB::select("SHOW FULL COLUMNS FROM $tableName");

        foreach ($columns as $column) {
            if (!in_array($column->Type, $excludeTypes) && !($tableName == 'users' && $column->Field === 'password')) {
                $searchFields[] = $column->Field;
            }
        }

        return $searchFields;
    }

    protected function applyFieldConditions($query, $fields, $filter)
    {
        foreach ($fields as $i => $column) {
            $method = $i === 0 ? 'where' : 'orWhere';
            $query->$method(trim($column), 'like', '%' . $filter . '%');
        }
    }


    // -------------------INFO: With Join ------------------------
    // protected function addJoin($query)
    // {
    //     if ($this->request->has('trashed') && $this->request->trashed == true)
    //         $query = $query->withTrashed();
    //     elseif ($this->request->has('onlyTrashed') && $this->request->onlyTrashed == true)
    //         $query = $query->onlyTrashed();

    //     if (!$this->request->has('joinWith')) return $query;

    //     $relations = explode(',', str_replace(' ', '', $this->request->joinWith));
    //     foreach ($relations as $relation) {
    //         $joins = explode('~', $relation);
    //         if (count($joins) > 1){
    //             $filters = [];
    //             $filterParts = explode('-', $joins[1]);
    //             foreach ($filterParts as $filter) {
    //                 $parts = explode(':', $filter);
    //                 $filters[] = [
    //                     'column' => $parts[0],
    //                     'value' => ($joins[0] == 'default_column_settings' && $parts[0] == 'model_type') ? Helper::getModelType($parts[1]) : $parts[1]
    //                 ];
    //             }
    //             $query = $query->with([
    //                 $joins[0] => function ($q) use ($filters) {
    //                     foreach ($filters as $filter) {
    //                         $q->where($filter['column'], $filter['value']);
    //                     }
    //                 }
    //             ]);
    //         }else{
    //             $query = $query->with($joins[0]);

    //             if ($this->request->has('request_from') && $this->request->request_from === 'dropdown' && !$this->request->has('include_join') || $this->request->include_join != false) {
    //                 $query = $query->has($joins[0]);
    //             }
    //         }
    //     }

    //     if ($this->request->has('have_not_join'))
    //         $query = $query->doesntHave($this->request->have_not_join);

    //     if ($this->request->has('has_join'))
    //         $query = $query->has($this->request->has_join);

    //     return $query;
    // }

    // protected function addJoin($query)
    // {
    //     if ($this->request->has('trashed') && $this->request->trashed == true)
    //         $query = $query->withTrashed();
    //     elseif ($this->request->has('onlyTrashed') && $this->request->onlyTrashed == true)
    //         $query = $query->onlyTrashed();

    //     if (!$this->request->has('joinWith')) return $query;

    //     $relations = explode(',', str_replace(' ', '', $this->request->joinWith));

    //     foreach ($relations as $relation) {
    //         $joins = explode('~', $relation);

    //         if (count($joins) > 1) {
    //             // Handle relation with filters
    //             $filters = [];
    //             $columns = [];

    //             $filterParts = explode('-', $joins[1]);

    //             foreach ($filterParts as $filter) {
    //                 // Check if this part specifies columns (using @ symbol)
    //                 if (strpos($filter, '@') === 0) {
    //                     $columns = explode('|', substr($filter, 1));
    //                     continue;
    //                 }

    //                 $parts = explode(':', $filter);
    //                 $filters[] = [
    //                     'column' => $parts[0],
    //                     'value' => $parts[1]
    //                 ];
    //             }

    //             $query = $query->with([
    //                 $joins[0] => function ($q) use ($filters, $columns) {
    //                     // Apply filters
    //                     foreach ($filters as $filter) {
    //                         $q->where($filter['column'], $filter['value']);
    //                     }

    //                     // Select specific columns if specified
    //                     if (!empty($columns)) {
    //                         $q->select(array_merge(['id'], $columns));
    //                     }
    //                 }
    //             ]);

    //         } else {
    //             // Handle simple relation with optional columns
    //             $relationParts = explode('@', $joins[0]);
    //             $relationName = $relationParts[0];

    //             if (count($relationParts) > 1) {
    //                 // Relation with specific columns
    //                 $columns = explode('|', $relationParts[1]);

    //                 $query = $query->with([
    //                     $relationName => function ($q) use ($columns) {
    //                         $q->select(array_merge(['id'], $columns));
    //                     }
    //                 ]);
    //             } else {
    //                 // Regular relation
    //                 $query = $query->with($relationName);
    //             }

    //             if ($this->request->has('request_from') && $this->request->request_from === 'dropdown' && !$this->request->has('include_join') || $this->request->include_join != false) {
    //                 $query = $query->has($relationName);
    //             }
    //         }
    //     }

    //     if ($this->request->has('have_not_join'))
    //         $query = $query->doesntHave($this->request->have_not_join);

    //     if ($this->request->has('has_join'))
    //         $query = $query->has($this->request->has_join);

    //     return $query;
    // }

    // -------------------INFO: Add Join ------------------------
    protected function joinTable($query, $join = [])
    {
        $joins = collect($this->request->has('joins')
        ? json_decode($this->request->joins, true) ?? []
        : []);

        if (!empty($join))
            $joins = collect($join)->merge($joins);

        // NOTE: return if joins is empty
        $joins = $joins->all();
        if (empty($joins)) return $query;

        foreach ($joins as $join) {
            $method = '';
            if(isset($join['type']) && $join['type'] !== 'inner')
                $method = $join['type'];

            $query = $query->{$method . 'Join'}(
                $join['table'],
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }
        return $query;
    }

    // -------------------INFO: Select Columns ------------------------
    protected function selectColumns($query, $select_coumns = [])
    {
        $select = collect($this->request->has('select')
        ? json_decode($this->request->select, true) ?? []
        : []);

        $select = $select->merge(collect($select_coumns))->unique();

        // NOTE: return if select is empty
        if($select->isEmpty()) return $query;

        $select = $select
        ->map(function ($item) {
            if (str_contains($item, ' AS ') || str_contains($item, '(')) {
                return DB::raw($item);
            }
            return $item;
        })
        ->toArray();
        return $query->select($select);
    }

}
?>
