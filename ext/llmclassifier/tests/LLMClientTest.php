<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LLMClientTest extends TestCase {
    private array $history = [];
    private object $logger;

    protected function setUp(): void {
        $this->logger = new class {
            public array $messages = [];
            public function log($message, $level): void { $this->messages[] = $message; }
        };
    }

    private function fixture(string $name): string {
        return file_get_contents(__DIR__ . '/fixtures/' . $name . '.json');
    }

    private function client($reply, string $endpoint = 'https://resource.services.ai.azure.com/openai/v1', $format = 'responses', $auth = 'api-key', $temperature = 0.3, $version = null, $key = 'test-key', $model = 'deployment'): SeedDMS_LLMClient {
        $stack = HandlerStack::create(new MockHandler([$reply]));
        $stack->push(Middleware::history($this->history));
        return new SeedDMS_LLMClient($endpoint, $key, $model, $temperature, $version, $this->logger, $format, $auth, new Client(['handler' => $stack]));
    }

    private function success(string $fixture = 'responses-success'): Response {
        return new Response(200, ['Content-Type' => 'application/json'], $this->fixture($fixture));
    }

    public static function endpoints(): array {
        return array_map(fn ($suffix) => ['https://resource.services.ai.azure.com/openai/v1' . $suffix], ['', '/', '/responses', '/responses/']);
    }

    #[DataProvider('endpoints')]
    public function testFoundryRequestAndReasoningBeforeText(string $endpoint): void {
        $result = $this->client($this->success(), $endpoint)->chatCompletion('Return classification JSON.', 'Document text');
        $this->assertSame(['name' => 'Invoice', 'categories' => ['Invoices'], 'keywords' => ['Steuer']], $result);
        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://resource.services.ai.azure.com/openai/v1/responses', (string)$request->getUri());
        $this->assertSame('test-key', $request->getHeaderLine('api-key'));
        $this->assertFalse($request->hasHeader('Authorization'));
        $body = json_decode((string)$request->getBody(), true);
        $this->assertSame('deployment', $body['model']);
        $this->assertSame([
            ['role' => 'system', 'content' => 'Return classification JSON.'],
            ['role' => 'user', 'content' => 'Document text']
        ], $body['input']);
        $this->assertFalse($body['store']);
        $this->assertArrayNotHasKey('messages', $body);
        $this->assertArrayNotHasKey('response_format', $body);
        $this->assertSame('json_schema', $body['text']['format']['type']);
        $this->assertTrue($body['text']['format']['strict']);
        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
                'keywords' => ['type' => 'array', 'items' => ['type' => 'string']]
            ],
            'required' => ['name', 'categories', 'keywords'],
            'additionalProperties' => false
        ], $body['text']['format']['schema']);
    }

    public static function chatModes(): array {
        return [
            ['https://api.openai.com/v1', 'chat_completions', 'bearer', 'test-key', null, 'deployment', 'https://api.openai.com/v1/chat/completions', true, 'Bearer test-key', ''],
            ['https://resource.openai.azure.com', 'azure_chat_completions', 'api-key', 'test-key', '2024-10-21', 'deployment name', 'https://resource.openai.azure.com/openai/deployments/deployment+name/chat/completions?api-version=2024-10-21', false, '', 'test-key'],
            ['https://resource.openai.azure.com', null, null, 'test-key', null, 'deployment', 'https://resource.openai.azure.com/openai/deployments/deployment/chat/completions?api-version=2024-02-15-preview', false, '', 'test-key'],
            ['http://localhost:11434/v1', null, null, '', null, 'deployment', 'http://localhost:11434/v1/chat/completions', true, '', ''],
            ['https://resource.openai.azure.com/openai/v1', 'chat_completions', 'none', 'test-key', null, 'deployment', 'https://resource.openai.azure.com/openai/v1/chat/completions', true, '', ''],
            ['https://resource.openai.azure.com', 'azure_chat_completions', 'bearer', 'test-key', null, 'deployment', 'https://resource.openai.azure.com/openai/deployments/deployment/chat/completions?api-version=2024-02-15-preview', false, 'Bearer test-key', '']
        ];
    }

    #[DataProvider('chatModes')]
    public function testChatCompatibility($endpoint, $format, $auth, $key, $version, $model, $url, $hasModel, $bearer, $apiKey): void {
        $this->assertNotNull($this->client($this->success('chat-success'), $endpoint, $format, $auth, 0.3, $version, $key, $model)->chatCompletion('System JSON', 'Document'));
        $request = $this->history[0]['request'];
        $this->assertSame($url, (string)$request->getUri());
        $this->assertSame($bearer, $request->getHeaderLine('Authorization'));
        $this->assertSame($apiKey, $request->getHeaderLine('api-key'));
        $body = json_decode((string)$request->getBody(), true);
        $this->assertSame($hasModel, array_key_exists('model', $body));
        $this->assertSame(['type' => 'json_object'], $body['response_format']);
        $this->assertSame('System JSON', $body['messages'][0]['content']);
    }

    public static function authModes(): array { return [['bearer', 'Bearer test-key', ''], ['none', '', '']]; }

    #[DataProvider('authModes')]
    public function testExplicitFoundryAuthentication($auth, $bearer, $key): void {
        $this->assertNotNull($this->client($this->success(), auth: $auth)->chatCompletion('JSON', 'Text'));
        $this->assertSame($bearer, $this->history[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame($key, $this->history[0]['request']->getHeaderLine('api-key'));
    }

    public static function temperatures(): array { return [[null], [0.0], [0.3]]; }

    #[DataProvider('temperatures')]
    public function testTemperatureOmissionAndZero($temperature): void {
        $this->assertNotNull($this->client($this->success(), temperature: $temperature)->chatCompletion('JSON', 'Text'));
        $body = json_decode((string)$this->history[0]['request']->getBody(), true);
        if ($temperature === null) {
            $this->assertArrayNotHasKey('temperature', $body);
        } else {
            $this->assertEquals($temperature, $body['temperature']);
        }
    }

    public static function invalidModes(): array { return [['wrong', 'api-key'], ['responses', 'wrong']]; }

    #[DataProvider('invalidModes')]
    public function testInvalidConfigurationDoesNotSend($format, $auth): void {
        $this->assertNull($this->client($this->success(), format: $format, auth: $auth)->chatCompletion('JSON', 'Text'));
        $this->assertCount(0, $this->history);
        $this->assertNotEmpty($this->logger->messages);
    }

    public static function unsuccessfulResponses(): array {
        return [['responses-refusal'], ['responses-incomplete']];
    }

    #[DataProvider('unsuccessfulResponses')]
    public function testUnsuccessfulResponsesReturnNull($fixture): void {
        $this->assertNull($this->client($this->success($fixture))->chatCompletion('JSON', 'Text'));
        $this->assertNotEmpty($this->logger->messages);
    }

    public static function brokenResponses(): array {
        return [['failed'], ['empty'], ['invalid-json'], ['invalid-classification'], ['message-incomplete'], ['mixed-refusal']];
    }

    #[DataProvider('brokenResponses')]
    public function testBrokenResponsesNeverReturnClassification($kind): void {
        $data = json_decode($this->fixture('responses-success'), true);
        switch ($kind) {
            case 'failed': $data['status'] = 'failed'; break;
            case 'empty': $data['output'] = []; break;
            case 'invalid-json': $data['output'][1]['content'][0]['text'] = 'test-key secret document'; break;
            case 'invalid-classification': $data['output'][1]['content'][0]['text'] = '"not an object"'; break;
            case 'message-incomplete': $data['output'][1]['status'] = 'incomplete'; break;
            case 'mixed-refusal': $data['output'][1]['content'][] = ['type' => 'refusal', 'refusal' => 'test-key secret document']; break;
        }
        $this->assertNull($this->client(new Response(200, ['Content-Type' => 'application/json'], json_encode($data)))->chatCompletion('JSON', 'Text'));
        $this->assertNotEmpty($this->logger->messages);
        $this->assertStringNotContainsString('test-key', implode(' ', $this->logger->messages));
        $this->assertStringNotContainsString('secret document', implode(' ', $this->logger->messages));
    }

    public static function httpFailures(): array { return [[401], [429], [500], [200]]; }

    #[DataProvider('httpFailures')]
    public function testTransportFailuresAreLoggedWithoutSecrets($code): void {
        $reply = new Response($code, ['Content-Type' => 'application/json'], $code === 200 ? 'not json' : '{"error":{"message":"test-key secret document","type":"error","code":"failure"}}');
        $this->assertNull($this->client($reply)->chatCompletion('JSON', 'Text'));
        $this->assertNotEmpty($this->logger->messages);
        $this->assertStringNotContainsString('test-key', implode(' ', $this->logger->messages));
        $this->assertStringNotContainsString('secret document', implode(' ', $this->logger->messages));
    }

    public function testConnectionFailureReturnsNull(): void {
        $this->assertNull($this->client(new ConnectException('test-key', new Request('POST', 'https://example.com')))->chatCompletion('JSON', 'Text'));
        $this->assertNotEmpty($this->logger->messages);
        $this->assertStringNotContainsString('test-key', implode(' ', $this->logger->messages));
    }

    public function testDefaultTransportUses120SecondTimeout(): void {
        $client = new SeedDMS_LLMClient('https://example.com/v1', '', 'deployment');
        $method = new ReflectionMethod($client, 'getHttpClient');
        $this->assertSame(120, $method->invoke($client)->getConfig('timeout'));
    }
}
