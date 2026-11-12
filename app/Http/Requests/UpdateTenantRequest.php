<?php

namespace App\Http\Requests;

class UpdateTenantRequest extends StoreTenantRequest
{
    public function rules(): array
    {
        return $this->tenantRules($this->route('tenant'));
    }
}
