<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

class CommonController extends BaseApiController
{
    public function getClinics(Request $request)
    {
        $response = get_clinics($request);

        return $this->success('Clinics get successfully.', $response);
    }

    public function getUsers(Request $request)
    {
        $response = get_users($request);

        return $this->success('Users get successfully.', $response);
    }
}
