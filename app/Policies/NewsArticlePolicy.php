<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\News\NewsArticle;
use App\Models\User\User;

final class NewsArticlePolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function create(User $user): bool
    {
        return $user->role_slug === 'editor' || $this->access->grantedFaculties($user) !== [];
    }

    public function delete(User $user, NewsArticle $article): bool
    {
        return $this->update($user, $article);
    }

    public function manage(User $user): bool
    {
        return $user->role_slug === 'editor' || $this->access->grantedFaculties($user) !== [];
    }

    public function publish(User $user, NewsArticle $article): bool
    {
        return $user->role_slug === 'editor';
    }

    public function update(User $user, NewsArticle $article): bool
    {
        if ($user->role_slug === 'editor') {
            return true;
        }

        $articleScope = $article->getAttribute('faculty_scope_slug');

        return $user->isLimitedEditor() && is_string($articleScope) && $this->access->canManageFaculty($user, $articleScope);
    }

    public function viewAny(User $user): bool
    {
        return $user->role_slug === 'editor' || $this->access->grantedFaculties($user) !== [];
    }

    public function view(User $user, NewsArticle $article): bool
    {
        return $this->update($user, $article);
    }
}
