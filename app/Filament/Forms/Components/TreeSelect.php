<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Select;

/** Select values from a hierarchy supplied by the caller. */
class TreeSelect extends Select
{
    protected string $view = 'filament.forms.components.tree-select';

    protected array|Closure $treeNodes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchable()
            ->native(false)
            ->options(fn (): array => $this->getTreeOptionLabels($this->getTreeNodes()));
    }

    /** @param list<array{id: int|string, label: string, children?: array}>|Closure $nodes */
    public function nodes(array|Closure $nodes): static
    {
        $this->treeNodes = $nodes;

        return $this;
    }

    /** @return list<array{id: int|string, label: string, children?: array}> */
    public function getTreeNodes(): array
    {
        return $this->evaluate($this->treeNodes);
    }

    /**
     * @param  list<array{id: int|string, label: string, children?: array}>  $nodes
     * @return array<int|string, string>
     */
    public function getTreeOptionLabels(array $nodes): array
    {
        return array_map(fn (array $path): string => implode(' / ', $path), $this->getTreeOptionPaths($nodes));
    }

    /**
     * @param  list<array{id: int|string, label: string, children?: array}>  $nodes
     * @param  list<string>  $ancestors
     * @return array<int|string, list<string>>
     */
    public function getTreeOptionPaths(array $nodes, array $ancestors = []): array
    {
        $paths = [];
        foreach ($nodes as $node) {
            $path = [...$ancestors, $node['label']];
            $paths[$node['id']] = $path;
            $paths += $this->getTreeOptionPaths($node['children'] ?? [], $path);
        }

        return $paths;
    }
}
