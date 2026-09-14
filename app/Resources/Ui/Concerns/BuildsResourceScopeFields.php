<?php
declare(strict_types=1);

namespace App\Resources\Ui\Concerns;

use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;
use Illuminate\Validation\Rule;
use Lattice\Form\Components\TextInput;

trait BuildsResourceScopeFields
{
    /**
     * The value travels in the space-separated `scope` parameter, so it is kept
     * to an alphabet without whitespace.
     *
     * @return array<int, TextInput>
     */
    private function resourceScopeFields(Resource $resource, ?ResourceScope $scope): array
    {
        $unique = Rule::unique(ResourceScope::class, 'value')->where('resource_id', $resource->id);

        return [
            TextInput::make('value', __('resources.scopes.fields.value.label'))
                ->value($scope?->value, editable: true)
                ->required()
                ->helperText(__('resources.scopes.fields.value.help-text'))
                ->rules(['string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:\/-]*$/', $scope instanceof ResourceScope ? $unique->ignore($scope) : $unique])
                ->message('regex', __('resources.scopes.validation.value-format')),
            TextInput::make('description', __('resources.scopes.fields.description.label'))
                ->value($scope?->description, editable: true)
                ->helperText(__('resources.scopes.fields.description.help-text'))
                ->rules(['nullable', 'string', 'max:255']),
        ];
    }
}
