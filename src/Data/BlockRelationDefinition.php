<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Data;

use Closure;
use Illuminate\Database\Eloquent\Model;

final readonly class BlockRelationDefinition
{
    /**
     * @param  class-string<Model>|null  $model
     * @param  array<class-string<Model>, string>  $morphTypes
     */
    public function __construct(
        public string $name,
        public ?string $model,
        public string $keyAttribute,
        public bool $multiple,
        public ?Closure $modifyQueryUsing = null,
        public array $morphTypes = [],
        public ?string $morphTypeField = null,
        public ?string $morphIdField = null,
    ) {}

    public function isMorphTo(): bool
    {
        return $this->morphTypes !== [];
    }
}
