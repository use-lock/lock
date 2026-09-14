<?php
declare(strict_types=1);

namespace App\Admin\Enums;

enum BootstrapOutcome: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Unchanged = 'unchanged';
}
