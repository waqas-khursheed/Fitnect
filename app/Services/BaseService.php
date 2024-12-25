<?php

namespace App\Services;
use Illuminate\Support\Facades\Validator;

class BaseService
{
    protected function validate(array $data, array $rules = []): bool
    {
        Validator::make($data, $rules)->validate();
        return true;
    }
}
