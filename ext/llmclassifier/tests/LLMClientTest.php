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

    private function client($reply, string $endpoint = 'https://resource.services.ai.azure.com/openai/v1', $format = 'responses', $auth = 'api-key', $temperature = null, $version = null, $key = 'test-key', $model = 'deployment', $effort = null, $cap = null): SeedDMS_LLMClient {
        $stack = HandlerStack::create(new MockHandler([$reply]));
        $stack->push(Middleware::history($this->history));
        return new SeedDMS_LLMClient($endpoint, $key, $model, $temperature, $version, $this->logger, $format, $auth, new Client(['handler' => $stack]), $effort, $cap);
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

    public static function blankEndpoints(): array { return [[''], ['   ']]; }

    #[DataProvider('blankEndpoints')]
    public function testBlankEndpointCannotFallBackToPublicOpenAI($endpoint): void {
        $this->assertNull($this->client($this->success(), endpoint: $endpoint, auth: 'bearer')->chatCompletion('JSON', 'Private document'));
        $this->assertCount(0, $this->history);
        $this->assertNotEmpty($this->logger->messages);
        $this->assertStringContainsString('endpoint', implode(' ', $this->logger->messages));
    }

    public static function malformedClassifications(): array {
        return [
            ['[]'], ['{"name":"Changed title"}'],
            ['{"name":{},"categories":[],"keywords":[]}'],
            ['{"name":"Changed title","categories":[{}],"keywords":[]}'],
            ['{"name":"Changed title","categories":[],"keywords":[{}]}'],
            ['{"name":"Changed title","categories":{},"keywords":[]}'],
            ['{"name":"Changed title","categories":[],"keywords":{"a":"Steuer"}}']
        ];
    }

    #[DataProvider('malformedClassifications')]
    public function testMalformedClassificationCannotChangeMetadata($json): void {
        $data = json_decode($this->fixture('responses-success'), true);
        $data['output'][1]['content'][0]['text'] = $json;
        $result = $this->client(new Response(200, ['Content-Type' => 'application/json'], json_encode($data)))->chatCompletion('JSON', 'Document');
        $this->assertNull($result);
        $this->assertNotEmpty($this->logger->messages);
        $classifier = new SeedDMS_DocumentClassifier((object)['_extensions' => []], new stdClass(), null);
        $document = new class {
            public string $name = 'Original title';
            public function getID() { return 1; }
            public function getName() { return $this->name; }
            public function setName($name) { $this->name = $name; return true; }
        };
        $this->assertFalse($classifier->applyClassification($document, $result));
        $this->assertSame('Original title', $document->name);
    }

    public function testLegacyCommaSeparatedKeywordsRemainCompatible(): void {
        $data = json_decode($this->fixture('chat-success'), true);
        $data['choices'][0]['message']['content'] = '{"name":"Invoice","categories":["Invoices"],"keywords":"Steuer, invoice"}';
        $result = $this->client(new Response(200, ['Content-Type' => 'application/json'], json_encode($data)), endpoint: 'https://example.com/v1', format: 'chat_completions')->chatCompletion('JSON', 'Text');
        $this->assertSame(['name' => 'Invoice', 'categories' => ['Invoices'], 'keywords' => 'Steuer, invoice'], $result);
    }

    public static function nonFinalChatReasons(): array {
        return [['content_filter'], ['length'], ['tool_calls'], ['function_call'], [null]];
    }

    #[DataProvider('nonFinalChatReasons')]
    public function testChatMustFinishNormallyBeforeApplyingJson($reason): void {
        $data = json_decode($this->fixture('chat-success'), true);
        $data['choices'][0]['finish_reason'] = $reason;
        $this->assertNull($this->client(new Response(200, ['Content-Type' => 'application/json'], json_encode($data)), endpoint: 'https://example.com/v1', format: 'chat_completions')->chatCompletion('JSON', 'Text'));
        $this->assertNotEmpty($this->logger->messages);
    }

    public static function reasoningModes(): array {
        $cases = [];
        foreach (['responses', 'chat_completions', 'azure_chat_completions'] as $format) {
            foreach ([null, '', 'none', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max'] as $effort) {
                $cases[] = [$format, $effort, $effort === null ? null : ($effort === '' ? '' : ' 1200 ')];
            }
            $cases[] = [$format, null, 1200];
            $cases[] = [$format, 'low', null];
        }
        return $cases;
    }

    #[DataProvider('reasoningModes')]
    public function testReasoningControlsMapByApiFormat($format, $effort, $cap): void {
        $this->assertNotNull($this->client($this->success($format === 'responses' ? 'responses-success' : 'chat-success'), format: $format, effort: $effort, cap: $cap)->chatCompletion('JSON', 'Text'));
        $body = json_decode((string)$this->history[0]['request']->getBody(), true);
        $this->assertArrayNotHasKey('max_tokens', $body);
        $effortKey = $format === 'responses' ? 'reasoning' : 'reasoning_effort';
        $capKey = $format === 'responses' ? 'max_output_tokens' : 'max_completion_tokens';
        $this->assertArrayNotHasKey($format === 'responses' ? 'reasoning_effort' : 'reasoning', $body);
        $this->assertArrayNotHasKey($format === 'responses' ? 'max_completion_tokens' : 'max_output_tokens', $body);
        if ($effort === null || $effort === '') {
            $this->assertArrayNotHasKey($effortKey, $body);
        } else {
            $this->assertSame($format === 'responses' ? ['effort' => $effort] : $effort, $body[$effortKey] ?? null);
        }
        if ($cap === null || $cap === '') {
            $this->assertArrayNotHasKey($capKey, $body);
        } else {
            $this->assertSame(1200, $body[$capKey] ?? null);
        }
    }

    public static function invalidReasoningControls(): array {
        return [['bad', null], [null, 0], [null, '0000'], [null, -1], [null, 1.5], [null, '1e3'], [null, 'garbage'], [null, '999999999999999999999999999999'], [[], null], [null, []]];
    }

    #[DataProvider('invalidReasoningControls')]
    public function testInvalidReasoningControlsDoNotSend($effort, $cap): void {
        $this->assertNull($this->client($this->success(), effort: $effort, cap: $cap)->chatCompletion('JSON', 'Text'));
        $this->assertCount(0, $this->history);
        $this->assertNotEmpty($this->logger->messages);
    }

    public function testDirectClientDefaultOmitsTemperature(): void {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([$this->success('chat-success')]));
        $stack->push(Middleware::history($history));
        $client = new SeedDMS_LLMClient('https://example.com/v1', '', 'deployment');
        (new ReflectionProperty($client, 'httpClient'))->setValue($client, new Client(['handler' => $stack]));
        $this->assertNotNull($client->chatCompletion('JSON', 'Text'));
        $this->assertArrayNotHasKey('temperature', json_decode((string)$history[0]['request']->getBody(), true));
    }

    public static function decimalCaps(): array { return [['01200', 1200], ['0001', 1]]; }

    #[DataProvider('decimalCaps')]
    public function testTokenCapsUseDecimalLeadingZeros($cap, $expected): void {
        $this->assertNotNull($this->client($this->success(), cap: $cap)->chatCompletion('JSON', 'Text'));
        $body = json_decode((string)$this->history[0]['request']->getBody(), true);
        $this->assertSame($expected, $body['max_output_tokens']);
    }
}
