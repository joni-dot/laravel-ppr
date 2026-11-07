<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use RuntimeException;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_scripts_are_limited_to_the_app_and_a_fresh_nonce()
    {
        $response = $this->get(route('login'));
        $policy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]{40}';/", $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("base-uri 'self'", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);

        preg_match("/'nonce-([^']+)'/", $policy, $matches);
        $response->assertSee('<script nonce="'.$matches[1].'">', escape: false);

        $nextPolicy = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');
        $this->assertNotSame($policy, $nextPolicy, 'Every page load must get a new nonce.');
    }

    public function test_inline_scripts_are_not_allowed()
    {
        $policy = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

        preg_match('/script-src ([^;]+)/', $policy, $scriptSources);
        $this->assertStringNotContainsString('unsafe-inline', $scriptSources[1]);
    }

    public function test_signed_in_pages_have_the_policy_too()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertHeader('Content-Security-Policy');
    }

    public function test_https_requests_upgrade_insecure_resources()
    {
        $policy = (string) $this->get(str_replace('http://', 'https://', route('login')))->headers->get('Content-Security-Policy');

        $this->assertStringEndsWith('; upgrade-insecure-requests', $policy);
    }

    public function test_the_vite_dev_server_is_allowed_only_while_it_runs()
    {
        // A separate file, so a developer's real running dev server is never touched.
        $hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        File::put($hotFile, 'http://localhost:5173');
        Vite::useHotFile($hotFile);

        try {
            $policy = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');
        } finally {
            File::delete($hotFile);
        }

        $this->assertStringContainsString("script-src 'self' 'nonce-", $policy);
        $this->assertStringContainsString('http://localhost:5173', $policy);
        $this->assertStringContainsString('ws://localhost:5173', $policy);

        $this->assertStringNotContainsString('localhost:5173', (string) $this->get(route('login'))->headers->get('Content-Security-Policy'));
    }

    public function test_the_detailed_error_page_is_left_alone_only_in_debug_mode()
    {
        Route::get('/__csp-test-error', fn () => throw new RuntimeException('Boom'));

        config(['app.debug' => true]);
        $this->get('/__csp-test-error')->assertServerError()->assertHeaderMissing('Content-Security-Policy');

        config(['app.debug' => false]);
        $this->get('/__csp-test-error')->assertServerError()->assertHeader('Content-Security-Policy');
    }
}
