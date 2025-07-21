<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class ProductRequest extends FormRequest
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
    // In a config file or class
    const INSTOCK_STATUSES = [
        'available', 'unavailable', 'preorder', 'soldout', 'discontinued',
        'backorder', 'out_of_stock', 'limited_edition', 'seasonal',
        'custom_order', 'made_to_order', 'special_order',
        'bulk_order', 'preorder_only', 'limited_stock',
    ];



    public function rules(): array
    {
        return [
            'category_id' => 'nullable|exists:categories,id',
            'tag_ids'     => 'nullable|array',
            'tag_ids.*'   => 'nullable|exists:tags,id',
            'instock_status'  => ['nullable', 'string', Rule::in(self::INSTOCK_STATUSES)],
        ];
    }
}
