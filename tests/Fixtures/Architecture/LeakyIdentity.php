<?php
declare(strict_types=1);

namespace Tests\Fixtures\Architecture;

use App\Roles\Actions\DeleteRole;
use Illuminate\Database\Eloquent\Model;

final class LeakyIdentity extends Model
{
    public function deleteRole(DeleteRole $delete): void {}
}
