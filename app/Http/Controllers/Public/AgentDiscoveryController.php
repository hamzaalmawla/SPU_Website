<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Seo\AgentDiscoveryServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * /llms.txt and /.well-known/ard.json: where AI agents learn what this site is.
 */
final class AgentDiscoveryController extends Controller
{
    public function llms(AgentDiscoveryServiceInterface $agents): Response
    {
        return response($agents->llmsTxt(), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function ard(AgentDiscoveryServiceInterface $agents): JsonResponse
    {
        return response()
            ->json($agents->ardManifest(), 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
