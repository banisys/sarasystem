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

    /*
    |--------------------------------------------------------------------------
    | Learner label
    |--------------------------------------------------------------------------
    |
    | What a learner (Student module) is called: "دانش‌آموز" in a school,
    | "دانشجو" in a university, "هنرآموز" or "زبان‌آموز" in an institute,
    | "کارآموز" in a company. See docs/decisions/0018-student-preregistration.md.
    |
    */

    'learner_label' => env('LEARNER_LABEL', 'فراگیر'),

    'learner_plural_label' => env('LEARNER_PLURAL_LABEL', 'فراگیران'),

];
