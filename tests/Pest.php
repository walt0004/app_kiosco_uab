<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Pide datos a un endpoint de DataTables (modo servidor).
 *
 * @param  array<string, mixed>  $params
 */
function dataTable(Illuminate\Foundation\Testing\TestCase $test, string $route, array $params = []): TestResponse
{
    return $test->getJson(route($route, array_merge(['draw' => 1, 'start' => 0, 'length' => 25], $params)));
}

/** Todo el contenido de las filas devueltas, como texto, para comprobar si aparece algo. */
function dataTableText(TestResponse $response): string
{
    return json_encode($response->json('data'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
