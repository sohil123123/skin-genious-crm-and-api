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
    use ResponseAPI, Authorizable;

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
        $query = addWhere($this->model, $request);
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
        $query = addWhere($this->model, $this->request);

        // $result =  $this->addJoin($query)->find($id);
        $result =  addJoin($query, $this->request)->find($id);

        if (!$result)
            return $this->error('Not Found Error.', [], config('constants.HTTP_NOT_FOUND'));

        return $this->success($this->crud_name.' get successfully', $result);
    }

    public function destroy(Request $request, string $id)
    {
        $query = addWhere($this->model, $request);

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
