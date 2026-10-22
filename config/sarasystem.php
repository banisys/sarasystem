<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant label
    |--------------------------------------------------------------------------
    |
    | What a tenant is called in this installation's UI: a school chain uses
    | "مدرسه", a company or language institute "شعبه" or "واحد".
    | See docs/decisions/0002-tenancy-model.md.
    |
    */

    'tenant_label' => env('TENANT_LABEL', 'شعبه'),

    'tenant_plural_label' => env('TENANT_PLURAL_LABEL', 'شعبه‌ها'),

];
