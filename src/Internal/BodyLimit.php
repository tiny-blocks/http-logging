<?php

declare(strict_types=1);

namespace TinyBlocks\Http\Logging\Internal;

use TinyBlocks\Http\Logging\Exceptions\BodyLimitOutOfRange;

final readonly class BodyLimit
{
    private const string ELLIPSIS = '…';
    private const int QUOTES_BYTES = 2;
    private const int DEFAULT_BYTES = 2048;
    private const int BRACKETS_BYTES = 2;
    private const int ENCODING_FLAGS = (
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
    );
    private const int SEPARATOR_BYTES = 1;

    private function __construct(private int $bytes)
    {
    }

    private static function cut(string $text, int $available): string
    {
        $characters = [];
        $budget = ($available - BodyLimit::sizeOf(value: self::ELLIPSIS));

        foreach (mb_str_split(mb_strcut($text, 0, $budget)) as $character) {
            $size = (BodyLimit::sizeOf(value: $character) - self::QUOTES_BYTES);

            if ($size > $budget) {
                break;
            }

            $characters[] = $character;
            $budget -= $size;
        }

        $template = '%s%s';

        return sprintf($template, implode('', $characters), self::ELLIPSIS);
    }

    public static function from(?int $bytes): BodyLimit
    {
        $limit = ($bytes ?? self::DEFAULT_BYTES);

        if ($limit < 0) {
            $template = 'Body limit must be zero or more bytes, got %d.';

            throw new BodyLimitOutOfRange(message: sprintf($template, $limit));
        }

        return new BodyLimit(bytes: $limit);
    }

    private static function closed(int|string $key, array $excerpt, array|string|null $fragment): array
    {
        return is_null($fragment) ? $excerpt : ($excerpt + [$key => $fragment]);
    }

    private static function sizeOf(mixed $value): int
    {
        return strlen(json_encode($value, self::ENCODING_FLAGS));
    }

    private static function framingOf(int|string $key, bool $isList, bool $isFirst): int
    {
        $separator = $isFirst ? 0 : self::SEPARATOR_BYTES;
        $name = $isList ? 0 : (BodyLimit::sizeOf(value: (string)$key) + self::SEPARATOR_BYTES);

        return ($separator + $name);
    }

    public function contextOf(?array $body): array
    {
        if (empty($body)) {
            return [];
        }

        $size = BodyLimit::sizeOf(value: $body);

        if ($size <= $this->bytes) {
            return ['body' => $body];
        }

        $excerpt = $this->excerptOf(budget: ($this->bytes - self::BRACKETS_BYTES), elements: $body);

        return ['body' => $excerpt, 'body_truncated_from_bytes' => $size];
    }

    private function excerptOf(int $budget, array $elements): array
    {
        $excerpt = [];
        $isList = array_is_list($elements);

        foreach ($elements as $key => $element) {
            $available = ($budget - BodyLimit::framingOf(key: $key, isList: $isList, isFirst: empty($excerpt)));
            $size = BodyLimit::sizeOf(value: $element);

            if ($size > $available) {
                $fragment = $this->fragmentOf(element: $element, available: $available);

                return BodyLimit::closed(key: $key, excerpt: $excerpt, fragment: $fragment);
            }

            $excerpt[$key] = $element;
            $budget = ($available - $size);
        }

        return $excerpt;
    }

    private function fragmentOf(mixed $element, int $available): array|string|null
    {
        if (is_array($element) && $available >= self::BRACKETS_BYTES) {
            return $this->excerptOf(budget: ($available - self::BRACKETS_BYTES), elements: $element);
        }

        if (is_string($element) && $available >= BodyLimit::sizeOf(value: self::ELLIPSIS)) {
            return BodyLimit::cut(text: $element, available: $available);
        }

        return null;
    }
}
