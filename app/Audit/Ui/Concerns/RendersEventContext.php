<?php
declare(strict_types=1);

namespace App\Audit\Ui\Concerns;

use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Size;

trait RendersEventContext
{
    /**
     * The provenance of a row first, then whatever the event carried. A value
     * nothing recorded is left out rather than rendered empty.
     *
     * @param  array<string, string|null>  $labelled
     * @param  array<string, mixed>  $context
     * @return array<int, Text>
     */
    private function detailLines(array $labelled, array $context): array
    {
        $lines = [];

        foreach ($labelled as $label => $value) {
            if ($value !== null && $value !== '') {
                $lines[] = $this->line($label.': '.$value);
            }
        }

        foreach ($context as $key => $value) {
            $lines[] = $this->line(str((string) $key)->headline().': '.$this->formatValue($value));
        }

        return $lines;
    }

    private function line(string $text): Text
    {
        return Text::make($text)->size(Size::Sm);
    }

    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            if (array_keys($value) === ['old', 'new']) {
                return $this->formatValue($value['old']).' → '.$this->formatValue($value['new']);
            }

            $parts = [];

            foreach ($value as $key => $item) {
                $formatted = $this->formatValue($item);
                $parts[] = is_int($key) ? $formatted : str($key)->headline().': '.$formatted;
            }

            return $parts === [] ? '—' : implode(', ', $parts);
        }

        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }
}
