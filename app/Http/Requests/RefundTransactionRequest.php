<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin', 'manager') ?? false;
    }

    public function rules(): array
    {
        return [
            'refund_amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:' . $this->route('transaction')->total,
            ],
            'refund_reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'refund_amount.max' => 'Refund amount cannot exceed the original transaction total.',
        ];
    }
}
