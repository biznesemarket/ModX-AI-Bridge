<?php

declare(strict_types=1);

namespace AIBridge\Security;

final class IpAllowlist
{
    public function allows(string $ip, array $rules): bool
    {
        if (!$rules) return true;
        foreach ($rules as $rule) {
            $rule = trim((string)$rule);
            if ($rule === $ip) return true;
            if (str_contains($rule, '/') && $this->inCidr($ip, $rule)) return true;
        }
        return false;
    }

    private function inCidr(string $ip, string $cidr): bool
    {
        [$subnet,$bits] = array_pad(explode('/', $cidr, 2), 2, null);
        $ipBin = @inet_pton($ip); $subBin = @inet_pton($subnet);
        if ($ipBin === false || $subBin === false || $bits === null || strlen($ipBin) !== strlen($subBin)) return false;
        $bits=(int)$bits; $bytes=intdiv($bits,8); $remainder=$bits%8;
        if ($bytes && substr($ipBin,0,$bytes)!==substr($subBin,0,$bytes)) return false;
        if (!$remainder) return true;
        $mask = chr((0xFF << (8-$remainder)) & 0xFF);
        return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subBin[$bytes]) & ord($mask));
    }
}
