<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\News\NewsArticle;
use App\Models\User\User;

final class NewsArticlePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function create(User $user): bool
    {
        return in_array($user->role_slug, ['editor', 'faculty_editor'], true);
    }

    public function delete(User $user, NewsArticle $article): bool
    {
        return $this->update($user, $article);
    }

    public function manage(User $user): bool
    {
        return in_array($user->role_slug, ['editor', 'faculty_editor'], true);
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

        if ($user->role_slug === 'faculty_editor') {
            $userScope = is_string($user->faculty_scope_slug) ? $user->faculty_scope_slug : '';
            $articleScope = $article->getAttribute('faculty_scope_slug');

            return $userScope !== '' && is_string($articleScope) && $articleScope === $userScope;
        }

        return false;
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role_slug, ['editor', 'faculty_editor'], true);
    }

    public function view(User $user, NewsArticle $article): bool
    {
        return $this->update($user, $article);
    }
}
