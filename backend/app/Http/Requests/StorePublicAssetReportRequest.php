<?php

namespace App\Http\Requests;

use App\Http\Requests\StoreTicketRequest;
use Illuminate\Validation\Rule;

class StorePublicAssetReportRequest extends StoreTicketRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (is_string($this->input('problem'))) {
            $this->merge(['problem' => trim($this->input('problem'))]);
        }
    }

    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['asset_id'], $rules['title'], $rules['category'], $rules['priority'], $rules['source']);
        $rules['problem'] = ['required', 'string', 'min:2', 'max:100', Rule::notIn(['0'])];

        return $rules;
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'problem.required' => 'Selecciona o describe el tipo de problema.',
            'problem.min' => 'El problema debe tener al menos 2 caracteres.',
            'problem.max' => 'El problema no puede superar los 100 caracteres.',
        ];
    }
}
