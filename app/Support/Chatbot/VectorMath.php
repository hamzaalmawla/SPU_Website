<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

/**
 * Packs embeddings as little-endian float32 and scores them.
 *
 * Vectors are normalized to unit length before packing, so cosine similarity
 * is a plain dot product at query time.
 */
final class VectorMath
{
    /**
     * @param  list<float>  $vector
     */
    public static function pack(array $vector): string
    {
        return pack('g*', ...self::normalize($vector));
    }

    /**
     * @return list<float>
     */
    public static function unpack(string $packed): array
    {
        if ($packed === '' || strlen($packed) % 4 !== 0) {
            return [];
        }

        $values = unpack('g*', $packed);

        return $values === false ? [] : array_values($values);
    }

    /**
     * @param  list<float>  $vector
     * @return list<float>
     */
    public static function normalize(array $vector): array
    {
        $sum = 0.0;
        foreach ($vector as $value) {
            $sum += $value * $value;
        }

        if ($sum <= 0.0) {
            return array_map(static fn (): float => 0.0, $vector);
        }

        $length = sqrt($sum);

        return array_map(static fn (float|int $value): float => (float) $value / $length, $vector);
    }

    /**
     * Dot product of two unit vectors of the same length (cosine similarity).
     *
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function dot(array $a, array $b): float
    {
        $count = min(count($a), count($b));
        $sum = 0.0;
        for ($index = 0; $index < $count; $index++) {
            $sum += $a[$index] * $b[$index];
        }

        return $sum;
    }
}
