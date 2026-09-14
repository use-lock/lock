<?php

declare(strict_types=1);

namespace App\Auth\Support;

use Carbon\CarbonImmutable;
use stdClass;

final readonly class BrowserSession
{
    public function __construct(
        public string $id,
        public ?string $ipAddress,
        public ?string $userAgent,
        public CarbonImmutable $lastActivity,
    ) {}

    public static function fromRow(stdClass $row): self
    {
        return new self(
            id: (string) $row->id,
            ipAddress: is_string($row->ip_address) ? $row->ip_address : null,
            userAgent: is_string($row->user_agent) ? $row->user_agent : null,
            lastActivity: CarbonImmutable::createFromTimestamp((int) $row->last_activity),
        );
    }

    public function browser(): ?string
    {
        $agent = (string) $this->userAgent;

        foreach ([
            'Edg/' => 'Microsoft Edge',
            'OPR/' => 'Opera',
            'Firefox/' => 'Firefox',
            'Chrome/' => 'Chrome',
            'Safari/' => 'Safari',
        ] as $needle => $name) {
            if (str_contains($agent, $needle)) {
                return $name;
            }
        }

        return null;
    }

    public function platform(): ?string
    {
        $agent = (string) $this->userAgent;

        foreach ([
            'iPhone' => 'iPhone',
            'iPad' => 'iPad',
            'Android' => 'Android',
            'Windows' => 'Windows',
            'Macintosh' => 'macOS',
            'CrOS' => 'ChromeOS',
            'Linux' => 'Linux',
        ] as $needle => $name) {
            if (str_contains($agent, $needle)) {
                return $name;
            }
        }

        return null;
    }
}
