<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class AvailabilityRule implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $value > 0;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The fee must be greater than 0.';
    }
}
