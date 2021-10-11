<?php

namespace Tests\Feature\Base;

use Illuminate\Foundation\Testing\DatabaseTransactions;

trait Filterable
{
    /**
     * Visit the given URI with a GET request.
     *
     * @param  string  $uri
     * @param  array  $headers
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    abstract function get($uri, array $headers = []);

    public function assertFilter(string $url, array $filter, array $expectedIds)
    {
        $filter = http_build_query(['filter' => $filter]);
        $response = $this->get($url . '?' . $filter);
        $response->assertOk();
        $content = json_decode($response->getContent());
        $collection = collect($content);
        self::assertEqualsCanonicalizing(
            $expectedIds,
            $collection->pluck('id')->toArray()
        );
    }
}
