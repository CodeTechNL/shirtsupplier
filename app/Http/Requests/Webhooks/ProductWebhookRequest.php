<?php

namespace App\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;

class ProductWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function getLanguage(): string
    {
        return $this->route('language');
    }

    public function getProduct(): array
    {
        return $this->input('product', []);
    }

    public function getProductId(): int|string|null
    {
        return $this->header('x-product-id') ?? $this->integer('resource_id');
    }
}
