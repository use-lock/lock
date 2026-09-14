<?php

declare(strict_types=1);

namespace App\Audit\Enums;

enum UserEventCategory: string
{
    case Auth = 'auth';
    case OAuth = 'oauth';
    case Admin = 'admin';

    public function label(): string
    {
        return __('audit.user.categories.'.$this->value);
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
