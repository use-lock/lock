<?php
declare(strict_types=1);

namespace App\Audit\Ui\Tables;

use App\Audit\Models\AdminEvent;
use App\Audit\Support\AdminEventTypes;
use App\Shared\Audit\Enums\AdminEventCategory;
use Illuminate\Database\Eloquent\Builder;
use Lattice\Fragments\Components\Fragment;
use Lattice\Table\Columns\BadgeColumn;
use Lattice\Table\Columns\TextColumn;
use Lattice\Table\Filters\SelectFilter;
use Lattice\Table\Sources\Eloquent\EloquentTableDefinition;
use Lattice\Table\TableQuery;
use Lattice\Ui\Enums\ColumnWidth;

/**
 * @extends EloquentTableDefinition<AdminEvent>
 */
abstract class AdminEventsTable extends EloquentTableDefinition
{
    /**
     * @return Builder<AdminEvent>
     */
    abstract protected function scopedEvents(): Builder;

    abstract protected function langPrefix(): string;

    /**
     * The categories this trail can hold, so its filters offer nothing that
     * would never match.
     *
     * @return list<AdminEventCategory>
     */
    abstract protected function categories(): array;

    /**
     * @param  array<string, mixed>  $row
     */
    abstract protected function contextFragment(array $row): Fragment;

    /**
     * @return Builder<AdminEvent>
     */
    public function builder(TableQuery $query): Builder
    {
        $builder = $this->scopedEvents()->with('user');

        if ($query->sorts === []) {
            $builder->latest('occurred_at');
        }

        return $builder;
    }

    public function columns(): array
    {
        return [
            TextColumn::make('occurred_at')->label(__('audit.events.columns.when'))->dateTime()->sortable(),
            TextColumn::make('actor_name')->label(__('audit.events.columns.actor')),
            BadgeColumn::make('type')->label(__('audit.events.columns.type'))->width(ColumnWidth::Lg),
            TextColumn::make('type_label')->label(__('audit.events.columns.description')),
            TextColumn::make('ip')->label(__('audit.events.columns.ip')),
        ];
    }

    public function filters(): array
    {
        $categories = $this->categories();

        return [
            SelectFilter::make('category')
                ->label(__('audit.events.filters.category'))
                ->options(array_intersect_key(
                    AdminEventCategory::options(),
                    array_flip(array_map(fn (AdminEventCategory $category): string => $category->value, $categories)),
                )),
            SelectFilter::make('type')
                ->label(__('audit.events.filters.type'))
                ->options(AdminEventTypes::options(...$categories))
                ->multiple(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function rowDetail(array $row): Fragment
    {
        return $this->contextFragment($row);
    }

    public function emptyLabel(): string
    {
        return __($this->langPrefix().'.empty');
    }
}
