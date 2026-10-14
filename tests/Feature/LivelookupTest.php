<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class LivelookupTest extends TestCase
{
    /**
     * @dataProvider lookupOptions
     */
    public function test_lookup_uses_nonempty_search_options(string $commandClass, array $options, string $expectedSearch): void
    {
        $client = Mockery::mock('overload:GuzzleHttp\\Client');
        $client->shouldReceive('post')->andReturnUsing(fn () => new Response(200, [], '{"access_token":"test-token"}'));

        $query = null;
        $graph = Mockery::mock('overload:Microsoft\\Graph\\Graph');
        $graph->shouldReceive('setBaseUrl', 'setApiVersion', 'setAccessToken')->andReturnSelf();
        $graph->shouldReceive('createRequest')->andReturnUsing(function ($method, $url) use (&$query, $graph) {
            $query = $url;

            return $graph;
        });
        $graph->shouldReceive('addHeaders', 'setReturnType', 'setTimeout')->andReturnSelf();
        $graph->shouldReceive('execute')->andReturn([]);

        $command = new $commandClass();
        $command->setLaravel($this->app);
        ob_start();
        try {
            $exitCode = $command->run(new ArrayInput($options), new BufferedOutput());
        } finally {
            ob_end_clean();
        }

        $this->assertSame(0, $exitCode);
        $this->assertSame($expectedSearch, substr($query, strpos($query, '&$'.(str_contains($commandClass, 'Fuzzy') ? 'search' : 'filter')) + 1));
    }

    public static function lookupOptions(): array
    {
        $cases = [
            'omitted email' => [['--last_name' => 'Albus'], "surName eq 'Albus'", 'surName:Albus'],
            'empty email' => [['--email' => '', '--last_name' => 'Albus'], "surName eq 'Albus'", 'surName:Albus'],
            'windows empty email and first name' => [['--email' => "''", '--first_name' => "''", '--last_name' => 'Albus'], "surName eq 'Albus'", 'surName:Albus'],
            'windows empty email and surname' => [['--email' => "''", '--last_name' => "''", '--first_name' => 'Billy'], "givenName eq 'Billy'", 'givenName:Billy'],
            'real email wins' => [['--email' => 'billy@example.com', '--last_name' => 'Albus', '--first_name' => 'Billy'], "mail eq 'billy@example.com'", 'mail:billy@example.com'],
            'both names' => [['--email' => "''", '--last_name' => 'Albus', '--first_name' => 'Billy'], "surName eq 'Albus'", 'givenName:Billy" AND "surName:Albus'],
            'apostrophe preserved' => [['--email' => "''", '--last_name' => "O'Brien"], "surName eq 'O'Brien'", "surName:O'Brien"],
        ];

        $data = [];
        foreach ($cases as $name => [$options, $exact, $fuzzy]) {
            $data['exact '.$name] = [\App\Commands\Livelookup::class, $options, '$filter='.$exact];
            $data['fuzzy '.$name] = [\App\Commands\LivelookupFuzzy::class, $options, '$search="'.$fuzzy.'"'];
        }

        return $data;
    }
}
