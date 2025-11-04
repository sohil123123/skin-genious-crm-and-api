<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

use App\Models\TreatmentPlan;

class TreatmentPlanController extends BaseApiController
{
    public function __construct(TreatmentPlan $model, Request $request)
    {
        parent::__construct($model, $request, 'TreatmentPlan', 'Api');
    }
}
