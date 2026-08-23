<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreActionRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:inventory_adjustment,price_change,product_deletion,transaction_correction,other'],
            'reason' => ['required', 'string', 'max:2000'],
            'target_type' => ['nullable', 'in:product,transaction'],
            'target_id' => ['nullable', 'integer', 'min:1'],
            'payload' => ['nullable', 'array'],
            'payload.action' => ['nullable', 'in:refund,void'],
            'payload.reason' => ['nullable', 'string', 'max:2000'],
            'payload.new_price' => ['nullable', 'numeric', 'min:0'],
            'payload.refund_amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
