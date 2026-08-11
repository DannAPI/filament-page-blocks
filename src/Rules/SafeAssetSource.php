<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Rules;

use Closure;
use DannAPI\FilamentPageBlocks\Support\AssetUrlResolver;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class SafeAssetSource implements ValidationRule
{
    public function __construct(private ?string $type = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! app(AssetUrlResolver::class)->accepts($value, $this->type)) {
            $fail('The :attribute must be an existing public/storage path or a safe HTTPS asset URL.');
        }
    }
}
