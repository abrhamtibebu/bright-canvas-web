<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_e164_normalizes_ethiopian_numbers(): void
    {
        $this->assertSame('+251911666555', PhoneNumber::e164('0911666555'));
        $this->assertSame('+251911666555', PhoneNumber::e164('+251911666555'));
        $this->assertSame('+251911666555', PhoneNumber::e164('911666555'));
        $this->assertNull(PhoneNumber::e164(null));
        $this->assertNull(PhoneNumber::e164(''));
        $this->assertNull(PhoneNumber::e164('123'));
    }
}
