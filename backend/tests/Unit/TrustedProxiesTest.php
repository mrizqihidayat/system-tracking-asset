<?php

namespace Tests\Unit;

use App\Support\TrustedProxies;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrustedProxiesTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: list<string>}>
     */
    public static function valueProvider(): array
    {
        return [
            'env tidak ada' => [null, []],
            'env kosong' => ['', []],
            'satu IP' => ['10.0.0.1', ['10.0.0.1']],
            'IP dan CIDR dengan spasi' => [' 10.0.0.1 , 192.168.0.0/16 ', ['10.0.0.1', '192.168.0.0/16']],
            'IPv6' => ['2001:db8::1, 2001:db8::/32', ['2001:db8::1', '2001:db8::/32']],
            'bintang diabaikan' => ['*', []],
            'dua bintang diabaikan' => ['**', []],
            'REMOTE_ADDR diabaikan' => ['REMOTE_ADDR', []],
            'semua IPv4 diabaikan' => ['0.0.0.0/0', []],
            'semua IPv6 diabaikan' => ['::/0', []],
            'bintang tidak menggugurkan IP sah' => ['10.0.0.1,*', ['10.0.0.1']],
            'teks acak diabaikan' => ['proxy.local, 10.0.0', []],
            'prefix di luar rentang diabaikan' => ['10.0.0.0/33, 10.0.0.0/abc, 2001:db8::/129', []],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('valueProvider')]
    public function test_only_specific_ip_or_cidr_is_trusted(?string $value, array $expected): void
    {
        $this->assertSame($expected, TrustedProxies::parse($value));
    }
}
