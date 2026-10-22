<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the Content Security Policy and other security headers to every response.
 *
 * Scripts and stylesheets need a per-request nonce (Vite and Livewire add it to their tags).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->policy($nonce));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }

    private function policy(string $nonce): string
    {
        $analytics = $this->analyticsOrigin();
        $dev = $this->viteDevServer();
        $devSocket = $dev ? preg_replace('#^http#', 'ws', $dev) : null;

        $directives = [
            'default-src' => ["'self'"],
            // 'unsafe-eval' is a deliberate trade-off: standard Alpine.js (used by Livewire)
            // evaluates its expressions at runtime. All user text is escaped by Blade, so there
            // is no user-controlled markup for an attacker to inject expressions into.
            'script-src' => ["'self'", "'nonce-{$nonce}'", "'unsafe-eval'", $analytics, $dev],
            // In development Vite injects CSS through <style> tags it creates itself.
            'style-src' => ["'self'", $dev ? "'unsafe-inline'" : "'nonce-{$nonce}'", $dev],
            // Inline style attributes carry the document's accent colour, font and logo size.
            'style-src-attr' => ["'unsafe-inline'"],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", $dev],
            'connect-src' => ["'self'", $analytics, $dev, $devSocket],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $name) => $name.' '.implode(' ', array_filter($sources)))
            ->implode('; ');
    }

    private function analyticsOrigin(): ?string
    {
        $url = config('services.umami.script_url');

        if (! is_string($url) || ! config('services.umami.website_id')) {
            return null;
        }

        $parts = parse_url($url);

        return isset($parts['scheme'], $parts['host'])
            ? "{$parts['scheme']}://{$parts['host']}".(isset($parts['port']) ? ":{$parts['port']}" : '')
            : null;
    }

    /**
     * The Vite dev server's origin while `npm run dev` is running, so hot reloading works.
     */
    private function viteDevServer(): ?string
    {
        if (! app()->isLocal() || ! Vite::isRunningHot()) {
            return null;
        }

        return rtrim((string) file_get_contents(Vite::hotFile()));
    }
}
