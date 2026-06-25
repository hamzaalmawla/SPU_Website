<?php

declare(strict_types=1);

namespace App\Contracts\News;

interface NewsAdminWorkflowServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareArticleDataForCreate(array $data, ?int $userId): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareArticleDataForUpdate(int $articleId, array $data, ?int $userId): array;

    public function recordArticleCreated(int $articleId, ?int $userId): bool;

    /**
     * @param  array<string, mixed>  $before
     */
    public function recordArticleUpdated(int $articleId, ?int $userId, array $before): bool;

    public function recordCategoryCreated(int $categoryId, ?int $userId): bool;

    /**
     * @param  array<string, mixed>  $before
     */
    public function recordCategoryUpdated(int $categoryId, ?int $userId, array $before): bool;

    public function deleteCategory(int $categoryId, ?int $userId): bool;
}
