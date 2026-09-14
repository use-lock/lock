<?php
declare(strict_types=1);

namespace App\Shared\Audit\Enums;

enum AdminEventCategory: string
{
    case Realm = 'realm';
    case User = 'user';
    case Client = 'client';
    case Role = 'role';
    case Resource = 'resource';

    public function label(): string
    {
        return __('audit.admin.categories.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $category) {
            $options[$category->value] = $category->label();
        }

        return $options;
    }
}
