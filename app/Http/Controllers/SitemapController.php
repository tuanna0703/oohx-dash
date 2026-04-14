<?php

namespace App\Http\Controllers;

use App\Models\Owner;
use App\Models\Product;
use App\Models\Screen;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = Cache::remember('sitemap:xml', 3600, function () {
            $urls = collect();

            // Static pages
            $urls->push(['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily']);
            $urls->push(['loc' => url('/explore'), 'priority' => '0.9', 'changefreq' => 'daily']);
            $urls->push(['loc' => url('/map'), 'priority' => '0.8', 'changefreq' => 'daily']);
            $urls->push(['loc' => url('/owners'), 'priority' => '0.8', 'changefreq' => 'weekly']);
            $urls->push(['loc' => url('/products'), 'priority' => '0.8', 'changefreq' => 'daily']);
            $urls->push(['loc' => url('/agency'), 'priority' => '0.6', 'changefreq' => 'monthly']);

            // Screens
            Screen::withoutGlobalScope('owner_scope')
                ->where('active', true)
                ->whereNotNull('slug')
                ->select('slug', 'updated_at')
                ->orderByDesc('updated_at')
                ->chunk(500, function ($screens) use ($urls) {
                    foreach ($screens as $screen) {
                        $urls->push([
                            'loc'        => url('/explore/' . $screen->slug),
                            'lastmod'    => $screen->updated_at->toW3cString(),
                            'priority'   => '0.7',
                            'changefreq' => 'weekly',
                        ]);
                    }
                });

            // Owners
            Owner::where('status', 'active')
                ->whereNotNull('slug')
                ->select('slug', 'updated_at')
                ->each(function ($owner) use ($urls) {
                    $urls->push([
                        'loc'        => url('/owners/' . $owner->slug),
                        'lastmod'    => $owner->updated_at->toW3cString(),
                        'priority'   => '0.8',
                        'changefreq' => 'weekly',
                    ]);
                });

            // Products
            Product::withoutGlobalScope('owner_scope')
                ->where('status', 'active')
                ->whereNotNull('slug')
                ->select('slug', 'updated_at')
                ->each(function ($product) use ($urls) {
                    $urls->push([
                        'loc'        => url('/products/' . $product->slug),
                        'lastmod'    => $product->updated_at->toW3cString(),
                        'priority'   => '0.7',
                        'changefreq' => 'weekly',
                    ]);
                });

            // Build XML
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            foreach ($urls as $url) {
                $xml .= "  <url>\n";
                $xml .= "    <loc>" . htmlspecialchars($url['loc']) . "</loc>\n";
                if (isset($url['lastmod'])) {
                    $xml .= "    <lastmod>" . $url['lastmod'] . "</lastmod>\n";
                }
                $xml .= "    <changefreq>" . $url['changefreq'] . "</changefreq>\n";
                $xml .= "    <priority>" . $url['priority'] . "</priority>\n";
                $xml .= "  </url>\n";
            }

            $xml .= '</urlset>';

            return $xml;
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
