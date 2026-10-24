<?php

namespace Tests\Unit;

use App\Models\TicketType;
use App\Models\Voucher;
use App\Support\Money;
use Tests\TestCase;

class MoneyAndVoucherTest extends TestCase
{
    public function test_to_cents_parses_common_swiss_formats(): void
    {
        $this->assertSame(2500, Money::toCents('25'));
        $this->assertSame(2550, Money::toCents('25.5'));
        $this->assertSame(2550, Money::toCents('25,50'));
        $this->assertSame(125000, Money::toCents("1'250.00"));
        $this->assertSame(0, Money::toCents('0'));
        $this->assertNull(Money::toCents('abc'));
        $this->assertNull(Money::toCents('-5'));
        $this->assertNull(Money::toCents('12.345'));
        $this->assertNull(Money::toCents(''));
    }

    public function test_format_and_input(): void
    {
        $this->assertSame("CHF 1'250.00", Money::format(125000));
        $this->assertSame('EUR 5.05', Money::format(505, 'EUR'));
        $this->assertSame('25.00', Money::input(2500));
        $this->assertSame('', Money::input(null));
    }

    public function test_voucher_discounts(): void
    {
        $percent = new Voucher(['type' => 'percent', 'value' => 15]);
        $fixed = new Voucher(['type' => 'fixed', 'value' => 700]);

        $this->assertSame(375, $percent->discountFor(2500));
        $this->assertSame(700, $fixed->discountFor(2500));
        $this->assertSame(500, $fixed->discountFor(500), 'Rabatt darf den Preis nicht übersteigen');
    }

    public function test_voucher_redeemability(): void
    {
        $v = new Voucher(['is_active' => true, 'max_uses' => 2, 'used_count' => 1]);
        $this->assertTrue($v->isRedeemable());

        $v->used_count = 2;
        $this->assertFalse($v->isRedeemable());

        $expired = new Voucher(['is_active' => true, 'valid_until' => now()->subDay()]);
        $this->assertFalse($expired->isRedeemable());

        $notYet = new Voucher(['is_active' => true, 'valid_from' => now()->addDay()]);
        $this->assertFalse($notYet->isRedeemable());

        $off = new Voucher(['is_active' => false]);
        $this->assertFalse($off->isRedeemable());
    }

    public function test_voucher_restricted_to_ticket_type(): void
    {
        $type = new TicketType(['event_id' => 1]);
        $type->id = 5;
        $other = new TicketType(['event_id' => 1]);
        $other->id = 6;

        $v = new Voucher(['is_active' => true, 'ticket_type_id' => 5]);

        $this->assertTrue($v->isRedeemable($type));
        $this->assertFalse($v->isRedeemable($other));
    }
}
