<?php

namespace App\Services;

/** Dünne Hülle um DNS-Abfragen (in Tests ersetzbar). */
class Dns
{
    /** @return string[] CNAME-Ziele, kleingeschrieben, ohne abschliessenden Punkt */
    public function cname(string $host): array
    {
        return $this->query($host, DNS_CNAME, 'target');
    }

    /** @return string[] */
    public function a(string $host): array
    {
        return $this->query($host, DNS_A, 'ip');
    }

    private function query(string $host, int $type, string $field): array
    {
        $records = @dns_get_record($host, $type);

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_unique(array_map(fn ($r) => strtolower(rtrim((string) $r[$field], '.')), $records)));
    }
}
