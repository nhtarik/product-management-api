<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
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
            'name'          => 'required_without:subcategories|string|max:255|unique:categories,name',
            'parent_id'     => 'nullable|numeric|exists:categories,id',
            'image'         => 'nullable|image|mimes:jpg,jpeg,png|max:3072',
            'subcat_image'  => 'nullable|image|mimes:jpg,jpeg,png|max:3072',

            // Subcategories can be an array of strings
            'subcategories'     => 'nullable|array',
            'subcategories.*'   => 'required_with:subcategories|max:255|unique:categories,name',
        ];
    }
}
