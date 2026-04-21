<?php

namespace App\Services\ScreenImport;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gọi Claude Haiku để map spreadsheet columns → Screen DB fields.
 *
 * Input: headers (list) + sample_rows (list of list) + optional user_comment + optional current_mapping
 * Output: [column_index => {field, confidence, reason, transform?}]
 *
 * Cache theo hash(headers+samples+comment+current) để tránh gọi lại khi user back-forward.
 */
class ColumnMappingAiService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const CACHE_TTL_MIN = 120;
    private const MAX_TOKENS = 2048;

    /** @var list<array{role:string, content:string}> */
    private array $lastMessages = [];

    /**
     * @param list<string>     $headers
     * @param list<list<mixed>> $sampleRows
     * @param string|null       $userComment      Natural-language refinement (Phase 2)
     * @param array|null        $currentMapping   Previous mapping being refined (Phase 2)
     *
     * @return array{mapping: array<int, array>, raw: string, tokens: array, cost_usd: float}
     */
    public function propose(array $headers, array $sampleRows, ?string $userComment = null, ?array $currentMapping = null): array
    {
        $apiKey = config('services.anthropic.key');
        if (! $apiKey) {
            throw new \RuntimeException('ANTHROPIC_API_KEY chưa set. Thêm vào .env hoặc map thủ công.');
        }

        $cacheKey = 'screen_import:ai_mapping:' . md5(json_encode([
            'headers'         => $headers,
            'sample_rows'     => $sampleRows,
            'user_comment'    => $userComment,
            'current_mapping' => $currentMapping,
            'schema_hash'     => md5(FieldCatalog::promptSchema()),
        ]));

        $cached = Cache::get($cacheKey);
        if ($cached) return $cached;

        $prompt = $this->buildPrompt($headers, $sampleRows, $userComment, $currentMapping);

        $response = Http::timeout(60)
            ->withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->post(self::ENDPOINT, [
                'model'      => config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                'max_tokens' => self::MAX_TOKENS,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Anthropic API error: HTTP ' . $response->status() . ' — ' . $response->body());
        }

        $body  = $response->json();
        $raw   = $body['content'][0]['text'] ?? '';
        $usage = $body['usage'] ?? [];

        $mapping = $this->parseMapping($raw, $headers);

        $tokensIn  = (int) ($usage['input_tokens']  ?? 0);
        $tokensOut = (int) ($usage['output_tokens'] ?? 0);
        $cost      = ($tokensIn / 1_000_000) * 1.0 + ($tokensOut / 1_000_000) * 5.0;

        $result = [
            'mapping'  => $mapping,
            'raw'      => $raw,
            'tokens'   => ['in' => $tokensIn, 'out' => $tokensOut],
            'cost_usd' => round($cost, 5),
        ];

        Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL_MIN));

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────

    private function buildPrompt(array $headers, array $sampleRows, ?string $comment, ?array $current): string
    {
        $schema = FieldCatalog::promptSchema();

        $headerLines = [];
        foreach ($headers as $i => $h) {
            $headerLines[] = "  [{$i}] \"{$h}\"";
        }
        $headerBlock = implode("\n", $headerLines);

        $sampleLines = [];
        foreach ($sampleRows as $ri => $row) {
            $cells = [];
            foreach ($row as $ci => $v) {
                $vs = $v === null ? 'null' : '"' . str_replace('"', '\"', mb_substr((string) $v, 0, 60)) . '"';
                $cells[] = "[{$ci}]={$vs}";
            }
            $sampleLines[] = "  Row " . ($ri + 1) . ': ' . implode(', ', $cells);
        }
        $sampleBlock = implode("\n", $sampleLines) ?: '  (no sample rows)';

        $currentBlock = '';
        if ($current) {
            $lines = [];
            foreach ($current as $idx => $m) {
                $f = is_array($m) ? ($m['field'] ?? null) : null;
                if ($f) $lines[] = "  [{$idx}] → {$f}";
            }
            if ($lines) {
                $currentBlock = "\n\nCURRENT MAPPING (to refine based on user comment):\n" . implode("\n", $lines);
            }
        }

        $commentBlock = $comment
            ? "\n\nUSER COMMENT (override / refine mapping based on this hint):\n  \"{$comment}\""
            : '';

        return <<<PROMPT
You are a column-mapping assistant for a Vietnamese DOOH/OOH advertising screen inventory system.

Given spreadsheet headers + sample rows, return the best DB field mapping for each column.
Pay attention to Vietnamese column names — users may use "Tên", "Mã", "Giá", "Kích thước", etc.

AVAILABLE DB FIELDS:
{$schema}

SPREADSHEET HEADERS (column index in brackets):
{$headerBlock}

SAMPLE DATA:
{$sampleBlock}{$currentBlock}{$commentBlock}

For each column, pick the single best matching field key OR return null if no match.
Confidence: 0..1 (1 = certain). Only map at 0.4+ confidence.
For compound columns (e.g. "1920x1080" → width_px + height_px), use "compound": ["key1","key2"] and describe split rule in reason.

Return ONE JSON object (no markdown fence), exactly this schema:

{
  "mappings": [
    {
      "column_index": 0,
      "header": "Tên màn hình",
      "field": "screens.name",
      "confidence": 0.95,
      "reason": "Direct match — 'Tên' = name"
    },
    {
      "column_index": 4,
      "header": "Kích thước",
      "field": null,
      "compound": ["spec.width_px", "spec.height_px"],
      "confidence": 0.85,
      "reason": "Values like '1920x1080' — split on 'x'",
      "transform": "split_by_x"
    }
  ]
}

CRITICAL: Only use field keys that appear in AVAILABLE DB FIELDS. Do not invent keys. Include EVERY column (use field:null if no match).
PROMPT;
    }

    /**
     * Parse LLM JSON response + filter out invalid field keys.
     *
     * @return array<int, array{field: ?string, confidence: float, reason: string, compound?: list<string>, transform?: string}>
     */
    private function parseMapping(string $text, array $headers): array
    {
        $decoded = $this->extractJson($text);
        if (! $decoded || ! isset($decoded['mappings']) || ! is_array($decoded['mappings'])) {
            Log::warning('ColumnMappingAiService: failed to parse LLM output', ['text' => $text]);
            return [];
        }

        $result = [];
        foreach ($decoded['mappings'] as $m) {
            $idx = $m['column_index'] ?? null;
            if (! is_int($idx) || $idx < 0 || $idx >= count($headers)) continue;

            $field    = $m['field']      ?? null;
            $compound = $m['compound']   ?? null;
            $conf     = (float) ($m['confidence'] ?? 0);
            $reason   = (string) ($m['reason'] ?? '');
            $tx       = $m['transform']  ?? null;

            // Validate field / compound against catalog
            if ($field && ! FieldCatalog::isValid($field)) {
                $field = null;
                $reason = "AI gợi field không hợp lệ — vui lòng map thủ công. ({$reason})";
            }

            if (is_array($compound)) {
                $compound = array_values(array_filter($compound, fn ($k) => FieldCatalog::isValid($k)));
                if (count($compound) < 2) $compound = null;
            }

            $result[$idx] = [
                'field'      => $field,
                'compound'   => $compound,
                'confidence' => max(0.0, min(1.0, $conf)),
                'reason'     => mb_substr($reason, 0, 200),
                'transform'  => is_string($tx) ? $tx : null,
                'header'     => $headers[$idx] ?? '',
            ];
        }
        return $result;
    }

    private function extractJson(string $text): ?array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded)) return $decoded;

        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) return $decoded;
        }

        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded)) return $decoded;
        }

        return null;
    }
}
