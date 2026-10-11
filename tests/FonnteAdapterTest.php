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

final class FonnteAdapterTest extends TestCase
{
    #[Test]
    public function it_sends_one_recipient_with_tls_timeout_and_no_link_preview(): void
    {
        $history = [];
        $stack   = HandlerStack::create(new MockHandler([new Response(200, [], '{"status":true,"id":["message-1"]}')]));
        $stack->push(Middleware::history($history));
        $adapter = new Fonnte_adapter('secret', new Client(['handler' => $stack]));
        $this->assertSame(['status' => 'accepted', 'provider_id' => 'message-1', 'reason' => ''], $adapter->send('6281234567890', 'Invoice link'));
        $this->assertSame('https://api.fonnte.com/send', (string) $history[0]['request']->getUri());
        $this->assertSame('secret', $history[0]['request']->getHeaderLine('Authorization'));
        parse_str((string) $history[0]['request']->getBody(), $body);
        $this->assertSame('6281234567890', $body['target']);
        $this->assertSame('false', $body['preview']);
        $this->assertSame('true', $body['connectOnly']);
        $this->assertTrue($history[0]['options']['verify']);
        $this->assertLessThanOrEqual(10, $history[0]['options']['timeout']);
    }

    #[Test]
    public function it_retries_only_explicit_provider_rejections(): void
    {
        foreach (['Invalid token', 'device disconnected'] as $detail) {
            $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], json_encode(['status' => false, 'reason' => $detail]))]))]);
            $result = (new Fonnte_adapter('secret', $client))->send('6281234567890', 'https://private-link');
            $this->assertSame('rejected', $result['status']);
            $this->assertStringNotContainsString($detail, $result['reason']);
            $this->assertStringNotContainsString('secret', $result['reason']);
        }
    }

    #[Test]
    public function it_marks_timeouts_malformed_responses_and_server_errors_uncertain(): void
    {
        foreach ([new ConnectException('secret private-link', new Request('POST', 'https://api.fonnte.com/send')), new Response(200, [], 'not json'), new Response(500), new Response(200, [], '{"status":true}'), new Response(200, [], '{"status":true,"id":"malformed-id"}')] as $response) {
            $client = new Client(['handler' => HandlerStack::create(new MockHandler([$response]))]);
            $result = (new Fonnte_adapter('secret', $client))->send('6281234567890', 'https://private-link');
            $this->assertSame('uncertain', $result['status']);
            $this->assertStringNotContainsString('secret', $result['reason']);
            $this->assertStringNotContainsString('private-link', $result['reason']);
        }
    }

    #[Test]
    public function it_rejects_missing_credentials_without_http(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);
        $this->assertSame('rejected', (new Fonnte_adapter('', $client))->send('6281234567890', 'Test')['status']);
    }

    #[Test]
    public function it_recognizes_the_documented_uppercase_status_for_invalid_tokens(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], '{"Status":false,"reason":"token invalid"}')]))]);
        $this->assertSame('rejected', (new Fonnte_adapter('secret', $client))->send('6281234567890', 'Test')['status']);
    }
}
