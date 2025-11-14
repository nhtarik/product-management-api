<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
        return [
            'name'          => 'required|string|max:255|unique:products,name',
            'slug'          => 'required|string|max:255|unique:products,slug',
            'description'   => 'nullable|string',
            'price'         => 'required|numeric|min:0',
            'stock'         => 'required|numeric|min:0',
            'sku'           => 'nullable|numeric|min:0',
            'is_active'     => 'required|boolean',
            'categories'    => 'nullable|array',
            'categories.*'  => 'exists:categories,id',
            'image'         => 'nullable|image|mimes:jpg,jpeg,png|max:3072'
        ];
    }
}
