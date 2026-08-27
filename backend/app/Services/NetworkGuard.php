<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessNetwork;

/**
 * Vendos nëse një kërkesë vjen "nga WiFi-i i punës".
 *
 * Shfletuesi nuk e jep dot emrin e rrjetit (SSID) - kjo është e bllokuar nga
 * çdo browser për arsye privatësie. Prandaj biznesi regjistron adresën IP
 * publike të zyrës: çdo pajisje e lidhur me atë WiFi del në internet me të
 * njëjtën IP, ndaj përputhja e IP-së është ekuivalente me "je në atë rrjet".
 *
 * Pranohen IP të vetme ("88.99.12.34") dhe range CIDR ("192.168.1.0/24"),
 * për IPv4 dhe IPv6.
 */
class NetworkGuard
{
    /** A lejohet check-in-i nga kjo IP për këtë biznes? */
    public function allows(Business $business, ?string $ip): bool
    {
        if (! $business->requiresNetwork()) {
            return true;
        }

        if (! $ip) {
            return false;
        }

        return $business->networks()
            ->where('is_active', true)
            ->get()
            ->contains(fn (BusinessNetwork $n) => $this->matches($ip, $n->ip_range));
    }

    /** Rrjeti i parë që përputhet, që t'i themi punonjësit ku është. */
    public function matchingNetwork(Business $business, ?string $ip): ?BusinessNetwork
    {
        if (! $ip) {
            return null;
        }

        return $business->networks()
            ->where('is_active', true)
            ->get()
            ->first(fn (BusinessNetwork $n) => $this->matches($ip, $n->ip_range));
    }

    /** A bie IP-ja brenda një IP-je të vetme ose një CIDR-i? */
    public function matches(string $ip, string $range): bool
    {
        $range = trim($range);

        if ($range === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (! str_contains($range, '/')) {
            return $this->normalize($ip) === $this->normalize($range);
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $bits = (int) $bits;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton(trim($subnet));

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        // IPv4 dhe IPv6 nuk krahasohen mes tyre.
        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maxBits = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($wholeBytes > 0 && substr($ipBin, 0, $wholeBytes) !== substr($subnetBin, 0, $wholeBytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainder) & 0xFF;

        return (ord($ipBin[$wholeBytes]) & $mask) === (ord($subnetBin[$wholeBytes]) & $mask);
    }

    /** "::ffff:127.0.0.1" dhe "127.0.0.1" janë e njëjta gjë. */
    private function normalize(string $ip): string
    {
        $binary = @inet_pton($ip);

        return $binary === false ? $ip : (inet_ntop($binary) ?: $ip);
    }

    /** Validim i formatit kur admini shton një rrjet. */
    public function isValidRange(string $range): bool
    {
        $range = trim($range);

        if (! str_contains($range, '/')) {
            return (bool) filter_var($range, FILTER_VALIDATE_IP);
        }

        [$subnet, $bits] = explode('/', $range, 2);

        if (! filter_var(trim($subnet), FILTER_VALIDATE_IP) || ! ctype_digit(trim($bits))) {
            return false;
        }

        $max = filter_var(trim($subnet), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;

        return (int) $bits >= 0 && (int) $bits <= $max;
    }
}
