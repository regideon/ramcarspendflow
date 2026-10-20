<?php

namespace App\Services\Rfp;

use Closure;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * One urgency chip above an RFP list: opens `tab` and, when it has a scope, narrows the table to it.
 */
final readonly class QueueChip
{
    /**
     * @param  (Closure(Builder): Builder)|null  $scope
     */
    public function __construct(
        public string $key,
        public string $label,
        public Heroicon $icon,
        public string $color,
        public string $tab,
        public ?Closure $scope = null,
        public bool $showsAmount = false,
    ) {}

    public function apply(Builder $query): Builder
    {
        return $this->scope ? ($this->scope)($query) : $query;
    }

    /**
     * Whether picking the chip filters the table (otherwise it only switches tab).
     */
    public function filters(): bool
    {
        return $this->scope !== null;
    }
}
