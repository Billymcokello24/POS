<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StkPushRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'phone' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'reference' => 'required|string|max:50'
        ];
    }
}
