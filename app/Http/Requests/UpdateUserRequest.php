<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get ID from route parameter because it's an update request
        $id = $this->route('id');

        return [
            'name' => 'nullable|string',
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($id)],
            'role' => 'nullable|in:admin,staff,user',
            'password' => 'nullable|min:6',
        ];
    }
}
