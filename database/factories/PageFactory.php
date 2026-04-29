<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
final class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'landing',
            'template' => 'default',
            'slug' => $this->faker->unique()->slug(2),
            'status' => 'draft',
            'sort_order' => 0,
            'is_enabled' => true,
            'show_in_breadcrumbs' => true,
            'show_in_nav' => true,
            'is_homepage_shell' => false,
        ];
    }
}
