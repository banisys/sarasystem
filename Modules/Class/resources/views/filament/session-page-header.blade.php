<x-filament-panels::header
    :breadcrumbs="filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : []"
    :heading="$this->getHeading()"
    :subheading="$this->getSubheading()"
/>
