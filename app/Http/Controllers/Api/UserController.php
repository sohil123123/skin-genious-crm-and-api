<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Role;

use App\Http\Requests\UserRequest;

class UserController extends BaseApiController
{
    public function __construct(User $model, Request $request)
    {
        parent::__construct($model, $request, 'User', 'Api');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $this->model;
        $query = $query->role('client');

        $query = $this->addWhere($query);
        $query = $this->joinTable($query);
        $query = $this->selectColumns($query);
        $query = $this->searchByAll($query, $this->model);
        $query = $this->addJoin($query);
        $query = $this->resultType($query);
        return $this->success($this->crud_name.'s get successfully', $query);
    }

    public function show(string $id)
    {
        $query = $this->model->role('client');
        $query = $this->addWhere($query);
        $result = $this->addJoin($query)->find($id);

        if (!$result)
            return $this->error('Not Found Error.', [], config('constants.HTTP_NOT_FOUND'));

        return $this->success($this->crud_name.' get successfully', $result);
    }

}
