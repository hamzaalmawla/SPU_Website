<?php

declare(strict_types=1);

namespace App\Contracts\Seo;

/**
 * Files that tell AI agents what this site is and where its content lives:
 * /llms.txt (a Markdown guide, https://llmstxt.org) and
 * /.well-known/ard.json (an Agentic Resource Discovery manifest).
 *
 * Both are built from published data only (the header menus and the footer
 * settings), so they never name a draft page.
 */
interface AgentDiscoveryServiceInterface
{
    public function llmsTxt(): string;

    /**
     * @return array<string, mixed>
     */
    public function ardManifest(): array;
}
