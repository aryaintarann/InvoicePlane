<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DockerSchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        $path = dirname(__DIR__) . '/resources/docker/scheduler.php';
        $this->assertFileExists($path);
        require_once $path;
    }

    #[Test]
    public function it_calls_the_existing_authenticated_cron_without_following_redirects(): void
    {
        $history = [];
        $stack   = HandlerStack::create(new MockHandler([new Response(200)]));
        $stack->push(Middleware::history($history));
        $result = run_docker_scheduler_tick(new Client(['handler' => $stack]), 'private/key');
        $this->assertSame('processed', $result);
        $this->assertSame('http://app/index.php/invoices/cron/recur/private%2Fkey', (string) $history[0]['request']->getUri());
        $this->assertFalse($history[0]['options']['allow_redirects']);
        $this->assertLessThan(60, $history[0]['options']['timeout']);
        $this->assertStringNotContainsString('private', $result);
    }

    #[Test]
    public function it_waits_for_a_configured_key_without_making_an_unauthenticated_request(): void
    {
        $http = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);
        $this->assertSame('cron_key_missing', run_docker_scheduler_tick($http, ''));
    }

    #[Test]
    public function it_reports_failures_without_exposing_keys_or_response_bodies(): void
    {
        foreach ([new Response(302, ['Location' => 'https://external.example/private-key']), new Response(500, [], 'private-key'), new ConnectException('private-key', new Request('GET', 'http://app'))] as $response) {
            $http = new Client(['handler' => HandlerStack::create(new MockHandler([$response]))]);
            $this->assertSame('cron_request_failed', run_docker_scheduler_tick($http, 'private-key'));
        }
    }

    #[Test]
    public function it_can_process_the_next_tick_after_a_connection_failure(): void
    {
        $responses = [new ConnectException('private-key', new Request('GET', 'http://app')), new Response(200)];
        $http      = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
        $this->assertSame('cron_request_failed', run_docker_scheduler_tick($http, 'private-key'));
        $this->assertSame('processed', run_docker_scheduler_tick($http, 'private-key'));
    }
}
