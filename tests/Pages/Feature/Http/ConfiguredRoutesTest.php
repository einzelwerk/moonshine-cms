<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Tests\Feature\Http;

use Ewk\Cms\Pages\Tests\TestCase;
use Illuminate\Support\Facades\Route;

/**
 * The endpoints belong to the package, so the package registers them — but
 * where they hang and what runs before them is the decision of the
 * installation. This is the test that the three keys of `cms-pages.routes` are
 * actually read and not merely documented.
 *
 * A plain test class and not a Pest file: the configuration has to be in place
 * before the provider boots, which is what `defineEnvironment()` is for.
 */
final class ConfiguredRoutesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cms-pages.routes', [
            'prefix' => 'cms-api/v9',
            'middleware' => ['api'],
            'block_throttle' => '7,1',
        ]);
    }

    public function test_the_prefix_of_the_configuration_carries_both_endpoints(): void
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === 'cms.resolve' || $name === 'cms.blocks.show') {
                $uris[$name] = $route->uri();
            }
        }

        self::assertSame([
            'cms.resolve' => 'cms-api/v9/resolve',
            'cms.blocks.show' => 'cms-api/v9/blocks/{uuid}',
        ], $uris);

        // Nothing answers at the default prefix any more: the configuration
        // replaces it instead of adding to it.
        $this->getJson('/api/v1/resolve?path=/')->assertNotFound();
        $this->getJson('/cms-api/v9/resolve?path=%2F')->assertNotFound()
            ->assertHeader('Vary', 'Accept-Language');
    }

    public function test_the_rate_limit_of_the_configuration_guards_the_endpoint_of_one_block(): void
    {
        // The limiter counts in the cache, which outlives a single test in a
        // shared store.
        $this->app->make('cache')->flush();

        $response = $this->getJson('/cms-api/v9/blocks/11111111-2222-3333-4444-555555555555?path=/nowhere');

        $response->assertNotFound();
        $response->assertHeader('X-RateLimit-Limit', 7);

        // The resolver is not limited: it answers a whole page and is the one
        // endpoint a visitor cannot avoid.
        $this->getJson('/cms-api/v9/resolve?path=/nowhere')
            ->assertNotFound()
            ->assertHeaderMissing('X-RateLimit-Limit');
    }
}
