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
        $client->shouldReceive('post')->once()->andReturnUsing(fn () => new Response(200, [], '{"access_token":"test-token"}'));

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

    /**
     * @dataProvider transferFailures
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_transfer_failures_return_safe_xml(string $commandClass, string $stage, string $failure): void
    {
        $secret = 'fixture-secret+&<hidden>';
        $request = new \GuzzleHttp\Psr7\Request('POST', 'https://example.test/token', [
            'Authorization' => 'Bearer fixture-token',
        ], http_build_query(['client_secret' => $secret]));
        $message = 'Sensitive diagnostic '.$secret;
        $exception = match ($failure) {
            'client' => new \GuzzleHttp\Exception\ClientException($message, $request, new Response(401, [], $secret)),
            'server' => new \GuzzleHttp\Exception\ServerException($message, $request, new Response(503, [], $secret)),
            'connect' => new \GuzzleHttp\Exception\ConnectException($message, $request),
            'transfer' => new \GuzzleHttp\Exception\TransferException($message),
        };

        $client = Mockery::mock('overload:GuzzleHttp\\Client');
        if ($stage === 'token') {
            $client->shouldReceive('post')->once()->andThrow($exception);
        } else {
            $client->shouldReceive('post')->once()->andReturn(new Response(200, [], '{"access_token":"fixture-token"}'));
            $graph = Mockery::mock('overload:Microsoft\\Graph\\Graph');
            $graph->shouldReceive('setBaseUrl', 'setApiVersion', 'setAccessToken', 'createRequest', 'addHeaders', 'setReturnType', 'setTimeout')->andReturnSelf();
            $graph->shouldReceive('execute')->andThrow($exception);
        }

        $command = new $commandClass();
        $command->setLaravel($this->app);
        $console = new BufferedOutput();
        ob_start();
        try {
            $exitCode = $command->run(new ArrayInput(['--email' => 'test@example.com']), $console);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $this->assertSame(1, $exitCode);
        $this->assertSame('', $console->fetch());
        $xml = simplexml_load_string($output);
        $this->assertNotFalse($xml);
        $this->assertSame('livelookup', $xml->getName());
        $this->assertNotEmpty((string) $xml->error);
        $this->assertStringNotContainsString($secret, $output);
        $this->assertStringNotContainsString(urlencode($secret), $output);
        $this->assertStringNotContainsString('fixture-token', $output);
        $this->assertStringNotContainsString('Sensitive diagnostic', $output);
        $this->assertStringNotContainsString('example.test', $output);
    }

    public static function transferFailures(): array
    {
        $cases = [];
        foreach ([\App\Commands\Livelookup::class, \App\Commands\LivelookupFuzzy::class] as $command) {
            foreach (['token', 'graph'] as $stage) {
                foreach (['client', 'server', 'connect', 'transfer'] as $failure) {
                    $cases[$command.' '.$stage.' '.$failure] = [$command, $stage, $failure];
                }
            }
        }

        return $cases;
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
