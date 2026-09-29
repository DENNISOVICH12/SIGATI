<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $requiredStrings = ['reporter_name', 'title', 'description', 'category'];
        $optionalStrings = ['reporter_email', 'reporter_phone'];
        $normalized = [];

        foreach ($requiredStrings as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = trim($this->input($field));
            }
        }

        foreach ($optionalStrings as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $normalized[$field] = $value === '' ? null : $value;
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'reporter_name' => [
                'required',
                'string',
                'min:2',
                'max:150',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (!preg_match('/\p{L}/u', $value)) {
                        $fail('El nombre del solicitante debe contener al menos una letra.');
                    }
                },
            ],
            'reporter_email' => ['nullable', 'string', 'email', 'max:254'],
            'reporter_phone' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^\+?[0-9 ()-]+$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $digits = preg_replace('/\D/', '', $value);
                    if (strlen($digits) < 7 || strlen($digits) > 15) {
                        $fail('El teléfono debe contener entre 7 y 15 dígitos.');
                    }
                },
            ],
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'category' => ['required', 'string', 'min:2', 'max:100'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'source' => ['nullable', Rule::in(['internal', 'qr', 'phone', 'email', 'manual'])],
        ];
    }

    public function messages(): array
    {
        return [
            'asset_id.integer' => 'Selecciona un activo válido.',
            'asset_id.exists' => 'El activo seleccionado no existe.',
            'reporter_name.required' => 'El nombre del solicitante es obligatorio.',
            'reporter_name.min' => 'El nombre del solicitante debe tener al menos 2 caracteres.',
            'reporter_name.max' => 'El nombre del solicitante no puede superar los 150 caracteres.',
            'reporter_email.email' => 'Ingresa un correo electrónico válido.',
            'reporter_email.max' => 'El correo no puede superar los 254 caracteres.',
            'reporter_phone.max' => 'El teléfono no puede superar los 30 caracteres.',
            'reporter_phone.regex' => 'Ingresa un número de teléfono válido.',
            'title.required' => 'El título es obligatorio.',
            'title.min' => 'El título debe tener al menos 5 caracteres.',
            'title.max' => 'El título no puede superar los 150 caracteres.',
            'description.required' => 'La descripción es obligatoria.',
            'description.min' => 'La descripción debe tener al menos 10 caracteres.',
            'description.max' => 'La descripción no puede superar los 5000 caracteres.',
            'category.required' => 'La categoría es obligatoria.',
            'category.min' => 'La categoría debe tener al menos 2 caracteres.',
            'category.max' => 'La categoría no puede superar los 100 caracteres.',
            'priority.in' => 'Selecciona una prioridad válida.',
        ];
    }
}
