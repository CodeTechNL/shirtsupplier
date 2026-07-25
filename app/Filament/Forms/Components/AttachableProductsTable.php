<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class AttachableProductsTable extends Field
{
    protected string $view = 'filament.forms.components.attachable-products-table';

    protected int|Closure|null $rowsUsing = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);
    }

    /**
     * The callback that resolves the rows to display, evaluated on every render
     * so the table reacts to the sibling search field.
     */
    public function rowsUsing(Closure $callback): static
    {
        $this->rowsUsing = $callback;

        return $this;
    }

    /**
     * @return array<int, array{id: int, title: string, codes: string, visible: bool}>
     */
    public function getRows(): array
    {
        return $this->evaluate($this->rowsUsing) ?? [];
    }
}
