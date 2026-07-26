<?php

namespace App\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;

class VariantWebhookRequest extends FormRequest
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

    /** @return array<string, mixed> */
    public function getVariant(): array
    {
        return $this->input('variant', []);
    }

    public function hasVariant(): bool
    {
        return filled($this->input('variant.id'));
    }

    public function getVariantId(): int|string|null
    {
        return $this->header('x-variant-id') ?? $this->integer('resource_id');
    }
}
