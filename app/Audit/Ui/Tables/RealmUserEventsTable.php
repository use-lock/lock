<?php
declare(strict_types=1);

namespace App\Audit\Ui\Tables;

use App\Admin\Enums\ManagementScope;
use App\Audit\Enums\UserEventCategory;
use App\Audit\Enums\UserEventType;
use App\Audit\Models\UserEvent;
use App\Audit\Ui\Fragments\RealmUserEventContextFragment;
use App\Realms\Models\Realm;
use Illuminate\Database\Eloquent\Builder;
use Lattice\Core\Enums\ColorName;
use Lattice\Fragments\Components\Fragment;
use Lattice\Table\Attributes\AsTable;
use Lattice\Table\Columns\BadgeColumn;
use Lattice\Table\Columns\TextColumn;
use Lattice\Table\Filters\SelectFilter;
use Lattice\Table\Filters\TernaryFilter;
use Lattice\Table\Sources\Eloquent\EloquentTableDefinition;
use Lattice\Table\TableQuery;
use Lattice\Ui\Enums\ColumnWidth;

/**
 * @extends EloquentTableDefinition<UserEvent>
 */
#[AsTable('admin.realm-user-events', can: ManagementScope::UserEventsRead)]
final class RealmUserEventsTable extends EloquentTableDefinition
{
    /**
     * @return Builder<UserEvent>
     */
    public function builder(TableQuery $query): Builder
    {
        $builder = UserEvent::forRealm($this->contextModel('realm', Realm::class))->with('user');

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
            BadgeColumn::make('type')
                ->label(__('audit.events.columns.type'))
                ->width(ColumnWidth::Lg)
                ->colors($this->typeColors()),
            TextColumn::make('type_label')->label(__('audit.events.columns.description')),
            TextColumn::make('ip')->label(__('audit.events.columns.ip')),
        ];
    }

    /**
     * A failure has to stand out while scrolling, so everything else stays
     * quiet.
     *
     * @return array<string, ColorName>
     */
    private function typeColors(): array
    {
        $colors = [];

        foreach (UserEventType::cases() as $type) {
            $colors[$type->value] = match (true) {
                $type->isFailure() => ColorName::Red,
                $type->category() === UserEventCategory::Admin => ColorName::Blue,
                default => ColorName::Gray,
            };
        }

        return $colors;
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('category')->label(__('audit.events.filters.category'))->options(UserEventCategory::options()),
            SelectFilter::make('type')->label(__('audit.events.filters.type'))->options(UserEventType::options())->multiple(),
            TernaryFilter::make('failure')->label(__('audit.events.filters.failure')),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    #[\Override]
    public function rowDetail(array $row): Fragment
    {
        return Fragment::lazy(RealmUserEventContextFragment::class, ['userEvent' => $row['id']]);
    }

    public function emptyLabel(): string
    {
        return __('audit.pages.realm-user.empty');
    }
}
