<?php
declare(strict_types=1);

namespace App\Admin\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementApi;
use App\Admin\Ui\Remote\ApiReferenceTokens;
use App\Shared\Ui\Pages\AdminPage;
use Dedoc\Scramble\CacheableGenerator;
use Lattice\ApiReference\ApiReference;
use Lattice\Core\Attributes\AsPage;
use Lattice\Ui\PageSchema;

/**
 * The document is generated from the routes themselves, so it cannot drift from
 * the API. `app:deploy` warms the cache the generator reads; a cold cache
 * generates on the first render instead.
 */
#[AsPage(route: '/admin/api', name: 'admin.api', can: ManagementScope::RealmsRead)]
final class ApiReferencePage extends AdminPage
{
    /** The node the playground's sealed references are bound to. */
    public const string REFERENCE_ID = 'admin-api-reference';

    public function title(): string
    {
        return __('admin.api.heading');
    }

    public function render(PageSchema $schema, CacheableGenerator $generator, ManagementApi $api): PageSchema
    {
        return $schema->schema([
            $this->stack(
                key: 'admin-api-page',
                heading: __('admin.api.heading'),
                description: __('admin.api.description'),
                schema: [
                    ApiReference::make(self::REFERENCE_ID)
                        ->id(self::REFERENCE_ID)
                        ->spec($generator())
                        ->hideHeader()
                        ->tokenSource(ApiReferenceTokens::KEY, audience: $api->audience()),
                ],
            ),
        ]);
    }
}
