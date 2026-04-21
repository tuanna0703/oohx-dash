<?php

namespace App\Services\ScreenImport;

/**
 * Apply mapping to a raw spreadsheet row, producing a nested data array:
 *
 *   [
 *     'screens'   => ['name' => ..., 'external_id' => ...],
 *     'spec'      => ['width_px' => ..., 'height_px' => ...],
 *     'inventory' => ['floor_cpm' => ...],
 *     'site'      => ['external_id' => ..., 'city' => ...],
 *   ]
 *
 * Handles type coercion (int/float/bool/enum/time) + compound splits (e.g. "1920x1080").
 */
class RowTransformer
{
    /**
     * @param array<int, array{field:?string, compound?: ?list<string>, transform?: ?string}> $mapping
     * @param list<mixed> $row
     *
     * @return array{data: array<string, array<string, mixed>>, warnings: list<string>}
     */
    public function transform(array $mapping, array $row): array
    {
        $data     = ['screens' => [], 'spec' => [], 'inventory' => [], 'site' => []];
        $warnings = [];

        foreach ($mapping as $colIndex => $m) {
            $raw = $row[$colIndex] ?? null;
            if ($raw === null || trim((string) $raw) === '') continue;

            $field    = $m['field']     ?? null;
            $compound = $m['compound']  ?? null;
            $tx       = $m['transform'] ?? null;

            if ($compound) {
                $parts = $this->splitCompound((string) $raw, $tx);
                foreach ($compound as $i => $subKey) {
                    if (! isset($parts[$i])) continue;
                    $this->assignField($data, $subKey, $parts[$i], $warnings);
                }
                continue;
            }

            if ($field) {
                $this->assignField($data, $field, $raw, $warnings);
            }
        }

        return ['data' => $data, 'warnings' => $warnings];
    }

    /**
     * @param array<string, array<string, mixed>> $data
     */
    private function assignField(array &$data, string $key, mixed $raw, array &$warnings): void
    {
        $catalog = FieldCatalog::get($key);
        if (! $catalog) return;

        [$group, $col] = explode('.', $key, 2);

        // Normalize top-level group
        $groupKey = match ($group) {
            'screens'   => 'screens',
            'spec'      => 'spec',
            'inventory' => 'inventory',
            'site'      => 'site',
            default     => null,
        };
        if (! $groupKey) return;

        $coerced = $this->coerce($raw, $catalog, $warnings, $key);
        if ($coerced === null && ($catalog['required'] ?? false)) {
            $warnings[] = "Không parse được giá trị cho {$key}: " . mb_substr((string) $raw, 0, 30);
        }
        $data[$groupKey][$col] = $coerced;
    }

    private function coerce(mixed $raw, array $catalog, array &$warnings, string $key): mixed
    {
        $str = trim((string) $raw);
        if ($str === '') return null;

        switch ($catalog['type']) {
            case 'int':
                $n = $this->parseNumber($str);
                return $n !== null ? (int) $n : null;

            case 'float':
                return $this->parseNumber($str);

            case 'bool':
                return $this->parseBool($str);

            case 'enum':
                $norm = strtolower($str);
                foreach ($catalog['enum'] ?? [] as $allowed) {
                    if (strtolower($allowed) === $norm) return $allowed;
                }
                // Try Vietnamese synonyms for common enums
                $vnMap = [
                    'bật' => 'online', 'hoạt động' => 'online', 'active' => 'online',
                    'tắt' => 'offline', 'offline' => 'offline',
                    'bảo trì' => 'maintenance',
                    'dọc' => 'portrait', 'đứng' => 'portrait',
                    'ngang' => 'landscape', 'landscape' => 'landscape',
                    'vuông' => 'square',
                    'vnd' => 'VND', 'đồng' => 'VND', 'usd' => 'USD', 'đô' => 'USD',
                ];
                if (isset($vnMap[$norm]) && in_array($vnMap[$norm], $catalog['enum'] ?? [], true)) {
                    return $vnMap[$norm];
                }
                $warnings[] = "Enum {$key}: '{$str}' không khớp " . implode('|', $catalog['enum'] ?? []);
                return null;

            case 'time':
                return $this->parseTime($str);

            case 'string':
            case 'text':
            default:
                return $str;
        }
    }

    private function parseNumber(string $v): ?float
    {
        // Strip thousand separators (both , and .), normalize decimal
        // Vietnamese format: 1.234.567,89 → 1234567.89
        // English format:    1,234,567.89 → 1234567.89
        $cleaned = $v;
        // If has both comma + dot, treat the LAST of (',', '.') as decimal separator
        $hasComma = str_contains($cleaned, ',');
        $hasDot   = str_contains($cleaned, '.');
        if ($hasComma && $hasDot) {
            if (strrpos($cleaned, ',') > strrpos($cleaned, '.')) {
                // VN: dots are thousands, comma is decimal
                $cleaned = str_replace('.', '', $cleaned);
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                // EN: commas are thousands
                $cleaned = str_replace(',', '', $cleaned);
            }
        } elseif ($hasComma) {
            $parts = explode(',', $cleaned);
            if (count($parts) > 2) {
                // Multiple commas → EN thousands (1,234,567)
                $cleaned = str_replace(',', '', $cleaned);
            } elseif (count($parts) === 2 && strlen($parts[1]) === 3 && ctype_digit($parts[1])) {
                // Ambiguous single comma with 3-digit suffix → assume thousands
                $cleaned = str_replace(',', '', $cleaned);
            } else {
                // Otherwise treat comma as decimal (European)
                $cleaned = str_replace(',', '.', $cleaned);
            }
        } elseif ($hasDot) {
            // Single dot — could be decimal (1.5) or VN thousands (150.000, 1.234.567).
            // Multiple dots → definitely thousands. Single dot + exactly 3 digits after → assume VN thousands.
            $parts = explode('.', $cleaned);
            if (count($parts) > 2) {
                $cleaned = str_replace('.', '', $cleaned);
            } elseif (count($parts) === 2 && strlen($parts[1]) === 3 && ctype_digit($parts[1])) {
                $cleaned = str_replace('.', '', $cleaned);
            }
            // else: keep dot as decimal separator
        }

        $cleaned = preg_replace('/[^\d.\-]/', '', $cleaned);
        if ($cleaned === '' || $cleaned === '-' || $cleaned === '.') return null;
        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private function parseBool(string $v): ?bool
    {
        $norm = strtolower(trim($v));
        return match ($norm) {
            '1', 'true', 'yes', 'y', 'có', 'bật', 'on', 'active'        => true,
            '0', 'false', 'no', 'n', 'không', 'tắt', 'off', 'inactive'  => false,
            default => null,
        };
    }

    private function parseTime(string $v): ?string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $v, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
        }
        if (is_numeric($v)) {
            $h = (int) $v;
            if ($h >= 0 && $h <= 23) {
                return str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
            }
        }
        return null;
    }

    /**
     * Split compound values (e.g. "1920x1080" → [1920, 1080]).
     *
     * @return list<string>
     */
    private function splitCompound(string $raw, ?string $transform): array
    {
        $str = trim($raw);

        return match ($transform) {
            'split_by_x'     => preg_split('/\s*[xX×]\s*/', $str) ?: [$str],
            'split_by_comma' => array_map('trim', explode(',', $str)),
            'split_by_space' => preg_split('/\s+/', $str) ?: [$str],
            default          => preg_split('/\s*[xX×,\s]\s*/', $str) ?: [$str],
        };
    }
}
