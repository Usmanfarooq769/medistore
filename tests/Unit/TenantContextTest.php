<?php

namespace Tests\Unit;

use App\Services\TenantContext;
use PHPUnit\Framework\TestCase;

class TenantContextTest extends TestCase
{
    public function test_tenant_context_can_be_set_and_cleared(): void
    {
        $context = new TenantContext();
        $tenant = (object) ['id' => 42, 'name' => 'Test Pharmacy'];

        $this->assertFalse($context->isResolved());
        $context->set($tenant);
        $this->assertTrue($context->isResolved());
        $this->assertSame(42, $context->id());
        $this->assertSame('Test Pharmacy', $context->tenant()->name);

        $context->clear();
        $this->assertFalse($context->isResolved());
        $this->assertNull($context->id());
    }
}
