<?php

namespace Tests\Concerns;

use App\Models\Project;
use Illuminate\Testing\TestResponse;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

trait LoadsPackageTree
{
    /**
     * The package list's deferred tree, asked for the way the page asks for it once it has opened.
     *
     * @return TestResponse<Response>
     */
    protected function getPackageTree(Project $project): TestResponse
    {
        return $this->get(route('projects.packages.index', $project), self::packageTreeHeaders());
    }

    /**
     * @return array<string, string>
     */
    protected static function packageTreeHeaders(): array
    {
        return [
            Header::PARTIAL_COMPONENT => 'projects/packages/Index',
            Header::PARTIAL_ONLY => 'packages,today',
        ];
    }
}
