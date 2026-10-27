<?php

namespace Modules\Document\Providers;

use App\Documents\ApplicantDocuments;
use App\Workflow\WorkflowStepExtensions;
use Modules\Document\Services\PublicFormDocuments;
use Modules\Document\Workflow\DocumentStepExtension;
use Nwidart\Modules\Support\ModuleServiceProvider;

class DocumentServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Document';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'document';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        // Applicants upload documents in the public forms (ADR 0019).
        $this->app->bind(ApplicantDocuments::class, PublicFormDocuments::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Steps can ask for documents to be uploaded or checked (ADR 0019).
        WorkflowStepExtensions::register(new DocumentStepExtension);
    }
}
