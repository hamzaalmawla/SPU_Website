<?php

declare(strict_types=1);

namespace App\Filament\Components;

use Closure;
use Filament\Forms\Components\Field;

final class FocalPointPicker extends Field
{
    protected string $view = 'filament.forms.components.focal-point-picker';

    protected string $focalYPath = 'focal_y';

    protected string $displayFitPath = 'display_fit';

    protected string|Closure|null $imageUrl = null;

    public function focalYPath(string $path): static
    {
        $this->focalYPath = $path;

        return $this;
    }

    public function displayFitPath(string $path): static
    {
        $this->displayFitPath = $path;

        return $this;
    }

    public function imageUrl(string|Closure|null $url): static
    {
        $this->imageUrl = $url;

        return $this;
    }

    public function getFocalYPath(): string
    {
        return $this->focalYPath;
    }

    public function getDisplayFitPath(): string
    {
        return $this->displayFitPath;
    }

    public function getImageUrl(): ?string
    {
        $url = $this->evaluate($this->imageUrl);

        return is_string($url) && $url !== '' ? $url : null;
    }
}
