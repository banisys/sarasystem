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

    /*
    |--------------------------------------------------------------------------
    | Guardian role
    |--------------------------------------------------------------------------
    |
    | The role a parent or guardian gets in their ward's tenant when they are
    | linked to the ward, so they can sign in there and see the ward's
    | classes. See docs/decisions/0027-guardians.md.
    |
    */
    'guardian_role' => env('GUARDIAN_ROLE', 'parent'),

    'guardian_role_label' => env('GUARDIAN_ROLE_LABEL', 'والد'),
];
