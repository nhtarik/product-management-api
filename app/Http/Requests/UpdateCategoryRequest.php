<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
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
        $categoryId = $this->route('category')->id ?? null;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($categoryId),
            ],
            'subcategories'     => 'nullable|array',
            'subcategories.*'   => 'string|required_with:subcategories|max:255',
            'parent_id'         => 'nullable|numeric|exists:categories,id',
            'image'             => 'nullable|image|mimes:jpg,jpeg,png|max:3072',
            'subcat_image'      => 'nullable|image|mimes:jpg,jpeg,png|max:3072'
        ];
    }
}
