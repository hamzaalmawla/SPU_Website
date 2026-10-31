<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\News\NewsArticle;
use App\Models\User\User;

/**
 * Authorizes news articles through the `news_articles.*` permissions. A user
 * with a faculty limit only reaches articles of those faculties.
 */
final class NewsArticlePolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'news_articles.view');
    }

    public function view(User $user, NewsArticle $article): bool
    {
        return $this->allowed($user, 'news_articles.view', $article);
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'news_articles.create');
    }

    public function update(User $user, NewsArticle $article): bool
    {
        return $this->allowed($user, 'news_articles.update', $article);
    }

    public function publish(User $user, NewsArticle $article): bool
    {
        return $this->allowed($user, 'news_articles.publish', $article);
    }

    public function delete(User $user, NewsArticle $article): bool
    {
        return $this->allowed($user, 'news_articles.delete', $article);
    }

    private function allowed(User $user, string $permission, NewsArticle $article): bool
    {
        $scope = $article->getAttribute('faculty_scope_slug');

        return $this->access->can($user, $permission)
            && $this->access->withinFacultyLimit($user, is_string($scope) ? $scope : null);
    }
}
