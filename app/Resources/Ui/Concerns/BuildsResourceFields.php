<?php
declare(strict_types=1);

namespace App\Resources\Ui\Concerns;

use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use App\Resources\Support\ResourceIdentifier;
use Illuminate\Validation\Rule;
use Lattice\Form\Components\TextInput;

trait BuildsResourceFields
{
    /**
     * The identifier is the RFC 8707 `resource` a client asks for: a path
     * relative to the realm issuer, or an absolute URI naming a resource server
     * the realm does not host.
     *
     * @return array<int, TextInput>
     */
    private function resourceFields(Realm $realm, ?Resource $resource): array
    {
        $unique = Rule::unique(Resource::class, 'identifier')->where('realm_id', $realm->id);

        return [
            TextInput::make('identifier', __('resources.fields.identifier.label'))
                ->value($resource?->identifier, editable: true)
                ->required()
                ->helperText(__('resources.fields.identifier.help-text'))
                ->rules(['string', 'max:255', $resource instanceof Resource ? $unique->ignore($resource) : $unique])
                ->rules([new ResourceIdentifier]),
            TextInput::make('name', __('resources.fields.name.label'))
                ->value($resource?->name, editable: true)
                ->required()
                ->rules(['string', 'max:255']),
        ];
    }
}
