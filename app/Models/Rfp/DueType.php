<?php

namespace App\Models\Rfp;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\Rfp\DueTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * How fast an RFP must be processed (Urgent, Standard). Decides the RFP due date.
 */
#[Table('rfp_due_types')]
#[Fillable(['code', 'name', 'description', 'processing_days', 'business_days_only', 'is_default', 'sort_order', 'is_active'])]
class DueType extends Model
{
    /** @use HasFactory<DueTypeFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Only one due type is pre-selected on new RFPs
        static::saved(function (DueType $dueType): void {
            if ($dueType->is_default && ($dueType->wasRecentlyCreated || $dueType->wasChanged('is_default'))) {
                static::query()->whereKeyNot($dueType->getKey())->update(['is_default' => false]);
            }
        });
    }

    /**
     * Due date for a request raised on the given day (today by default).
     */
    public function dueDateFrom(?CarbonInterface $from = null): CarbonImmutable
    {
        $from = CarbonImmutable::parse($from ?? today())->startOfDay();

        return $this->business_days_only
            ? $from->addWeekdays($this->processing_days)
            : $from->addDays($this->processing_days);
    }

    /**
     * @return HasMany<Rfp, $this>
     */
    public function rfps(): HasMany
    {
        return $this->hasMany(Rfp::class, 'rfp_due_type_id');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processing_days' => 'integer',
            'business_days_only' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
