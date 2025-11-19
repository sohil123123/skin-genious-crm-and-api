<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Models\Clinic;
use APp\Models\User;

if (!function_exists('remove_empty_value')) {
    function remove_empty_value($array){
        return array_values(array_filter($array));
    }
}

if (!function_exists('get_clinics')) {
    function get_clinics($request){
        $query = Clinic::active()->orderby('name');
        
        $query = addJoin($query, $request);

        if($request->has('id') && $request->id)
            $query = $query->where('id', $request->id);

        if($request->has('search') && $request->search)
            $query = $query->where('name', 'like', '%' .$request->search. '%');

        if($request->has('is_dropdown') && $request->is_dropdown)
            $query = $query->select(DB::raw(config('project.mysql_ucwords').' AS label, id AS value'));

        if($request->has('take') && $request->take)
            $query = $query->take($request->take);

        return ($request->has('is_first') && $request->is_first) ? $query->first() : $query->get();
    }
}

if (!function_exists('get_users')) {
    function get_users($request){
        $query = User::active()->orderby('first_name');
        
        $query = $query->whereHas('roles', fn ($q) => $q->where('name', '<>', 'super_admin'));

        $query = addJoin($query, $request);

        if($request->has('id') && $request->id)
            $query = $query->where('id', $request->id);

        if($request->has('role') && $request->role)
            $query = $query->whereHas('roles', fn ($q) => $q->where('name', $request->role));

        if($request->has('search') && $request->search)
            $query = $query->where('first_name', 'like', '%' .$request->search. '%');

        if($request->has('clinic_id') && $request->clinic_id)
            $query = $query->where('clinic_id', $request->clinic_id);

        if($request->has('is_dropdown') && $request->is_dropdown)
            $query = $query->select(DB::raw(config('project.mysql_user_ucwords').' AS label, id AS value'));

        if($request->has('take') && $request->take)
            $query = $query->take($request->take);

        return ($request->has('is_first') && $request->is_first) ? $query->first() : $query->get();
    }
}