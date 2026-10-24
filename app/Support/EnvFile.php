<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/** Liest und schreibt die .env-Datei sicher (Werte werden bei Bedarf korrekt maskiert, Rest der Datei bleibt erhalten). */
class EnvFile
{
    public function __construct(private string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function writable(): bool
    {
        return $this->exists() ? is_writable($this->path) : is_writable(dirname($this->path));
    }

    public function get(string $key): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        $parsed = \Dotenv\Dotenv::parse((string) file_get_contents($this->path));

        return $parsed[$key] ?? null;
    }

    /** @param array<string,string|int|bool|null> $values */
    public function set(array $values): void
    {
        $content = $this->exists() ? (string) file_get_contents($this->path) : '';
        $lineEnd = str_contains($content, "\r\n") ? "\r\n" : "\n";

        foreach ($values as $key => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new InvalidArgumentException("Ungültiger Schlüssel: $key");
            }

            $line = $key.'='.self::format($value);
            $pattern = '/^[ \t]*(?:export[ \t]+)?'.preg_quote($key, '/').'[ \t]*=.*$/m';

            if (preg_match($pattern, $content)) {
                // Callback statt String, damit «$» und «\» im Wert nicht als Rückverweis gelten
                $content = preg_replace_callback($pattern, fn () => $line, $content, 1);
            } else {
                $content = rtrim($content, "\r\n").($content === '' ? '' : $lineEnd).$line.$lineEnd;
            }
        }

        $this->write($content);
    }

    /** Formatiert einen Wert so, dass ihn der .env-Parser unverändert zurückliefert. */
    public static function format(string|int|bool|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\0")) {
            throw new InvalidArgumentException('Zeilenumbrüche sind in .env-Werten nicht erlaubt.');
        }

        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+-]+$/', $value)) {
            return $value;
        }

        // Einfache Anführungszeichen kennen in dotenv keine Escape-Folgen: alles ist wörtlich, nur ' selbst geht nicht.
        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }

        // Enthält der Wert ein ', in doppelte Anführungszeichen; dort müssen \, " und $ maskiert werden.
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    private function write(string $content): void
    {
        $tmp = $this->path.'.tmp'.bin2hex(random_bytes(3));

        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new RuntimeException('Die Datei .env kann nicht geschrieben werden. Bitte Schreibrechte prüfen.');
        }

        @chmod($tmp, 0640);

        if (! @rename($tmp, $this->path)) {
            @unlink($tmp);

            throw new RuntimeException('Die Datei .env kann nicht geschrieben werden. Bitte Schreibrechte prüfen.');
        }
    }
}
