<?php

namespace App\Services;

use stdClass;

class TenantContext
{
    private ?object $tenant = null;

    public function set(object $tenant): void { $this->tenant = $tenant; }
    public function clear(): void { $this->tenant = null; }
    public function tenant(): ?object { return $this->tenant; }
    public function id(): ?int { return $this->tenant ? (int) $this->tenant->id : null; }
    public function isResolved(): bool { return $this->tenant !== null; }
}
