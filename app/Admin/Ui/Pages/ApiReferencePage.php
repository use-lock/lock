<?php
declare(strict_types=1);

namespace App\Admin\Ui\Pages;

use App\Admin\Enums\ApiResource;
use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementApi;
use App\Admin\Ui\Remote\ApiReferenceTokens;
use App\Shared\Ui\Pages\AdminPage;
use Bambamboole\Spectacular\OpenApi\GroupedOpenApiDocuments;
use Dedoc\Scramble\CacheableGenerator;
use Lattice\ApiReference\ApiReference;
use Lattice\Core\Attributes\AsPage;
use Lattice\Ui\Components\Tab;
use Lattice\Ui\Components\Tabs;
use Lattice\Ui\PageSchema;

#[AsPage(route: '/admin/api', name: 'admin.api', can: ManagementScope::RealmsRead)]
final class ApiReferencePage extends AdminPage
{
    public const string REFERENCE_ID = 'admin-api-reference';

    public const string MANAGEMENT_REFERENCE_ID = 'management-api-reference';

    public function title(): string
    {
        return __('admin.api.heading');
    }

    public function render(PageSchema $schema, CacheableGenerator $generator, ManagementApi $api): PageSchema
    {
        $documents = GroupedOpenApiDocuments::create($generator());

        return $schema->schema([
            $this->stack(
                key: 'admin-api-page',
                heading: __('admin.api.heading'),
                description: __('admin.api.description'),
                schema: [
                    Tabs::make('api-references')->defaultValue('admin')->schema([
                        Tab::make('admin', ApiResource::Admin->label())->schema([
                            ApiReference::make(self::REFERENCE_ID)
                                ->id(self::REFERENCE_ID)
                                ->spec($documents['admin'])
                                ->hideHeader()
                                ->tokenSource(ApiReferenceTokens::KEY, audience: $api->audience(ApiResource::Admin)),
                        ]),
                        Tab::make('management', ApiResource::Management->label())->schema([
                            ApiReference::make(self::MANAGEMENT_REFERENCE_ID)
                                ->id(self::MANAGEMENT_REFERENCE_ID)
                                ->spec($documents['management'])
                                ->hideHeader()
                                ->tokenSource(ApiReferenceTokens::KEY, audience: $api->audience(ApiResource::Management)),
                        ]),
                        Tab::make('protocol', __('admin.api.protocol'))->schema([
                            ApiReference::make('protocol-api-reference')
                                ->spec($documents['auth'])
                                ->hideHeader(),
                        ]),
                    ]),
                ],
            ),
        ]);
    }
}
