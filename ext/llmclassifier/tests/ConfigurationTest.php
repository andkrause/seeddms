<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase {
    public function testSelectableModesAndTranslatedOptions(): void {
        require dirname(__DIR__) . '/conf.php';
        require dirname(__DIR__) . '/lang.php';
        $config = $EXT_CONF['llmclassifier']['config'];
        $this->assertArrayHasKey('llm_api_format', $config);
        $this->assertArrayHasKey('llm_auth_method', $config);
        $this->assertSame(['responses', 'chat_completions', 'azure_chat_completions'], array_keys($config['llm_api_format']['options']));
        $this->assertSame(['api-key', 'bearer', 'none'], array_keys($config['llm_auth_method']['options']));
        foreach (['llm_api_format', 'llm_auth_method'] as $key) {
            $this->assertSame('select', $config[$key]['type']);
            $this->assertTrue($config[$key]['allow_empty']);
            foreach (['de_DE', 'en_GB'] as $locale) {
                $this->assertNotEmpty($__lang[$locale][$key]);
                $this->assertNotEmpty($__lang[$locale][$key . '_desc']);
                foreach ($config[$key]['options'] as $label) {
                    $this->assertNotEmpty($__lang[$locale]['llmclassifier_' . $label]);
                }
            }
        }
        $this->assertSame('8.2.0-', $EXT_CONF['llmclassifier']['constraints']['depends']['php']);
        $this->assertSame('1.1.1', $EXT_CONF['llmclassifier']['version']);
    }

    public static function settings(): array {
        return [[[], false, null], [['llm_temperature' => ''], false, null], [['llm_temperature' => '0'], true, 0.0], [['llm_temperature' => 'omit'], false, null], [['llm_temperature' => ' OMIT '], false, null], [['llm_temperature' => '0.0'], true, 0.0], [['llm_temperature' => '0.3'], true, 0.3]];
    }

    #[DataProvider('settings')]
    public function testClassifierPassesExplicitSettingsAndTemperature($extra, $hasTemperature, $temperature): void {
        $settings = (object)['_extensions' => ['llmclassifier' => $extra + [
            'llm_endpoint' => 'https://resource.services.ai.azure.com/openai/v1/responses',
            'llm_api_key' => 'test-key', 'llm_model' => 'deployment',
            'llm_api_format' => 'responses', 'llm_auth_method' => 'api-key',
            'llm_reasoning_effort' => 'low', 'llm_max_output_tokens' => '1200',
            'pdftotext_path' => '/bin/true'
        ]]];
        $classifier = new SeedDMS_DocumentClassifier($settings, new stdClass(), null);
        $property = new ReflectionProperty($classifier, 'llmClient');
        $adapter = $property->getValue($classifier);
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], file_get_contents(__DIR__ . '/fixtures/responses-success.json'))]));
        $stack->push(Middleware::history($history));
        (new ReflectionProperty($adapter, 'httpClient'))->setValue($adapter, new Client(['handler' => $stack]));
        $this->assertNotNull($adapter->chatCompletion('JSON', 'Document'));
        $request = $history[0]['request'];
        $this->assertSame('/openai/v1/responses', $request->getUri()->getPath());
        $this->assertSame('test-key', $request->getHeaderLine('api-key'));
        $this->assertFalse($request->hasHeader('Authorization'));
        $body = json_decode((string)$request->getBody(), true);
        $this->assertSame($hasTemperature, array_key_exists('temperature', $body));
        $this->assertSame(['effort' => 'low'], $body['reasoning'] ?? null);
        $this->assertSame(1200, $body['max_output_tokens'] ?? null);
        if ($hasTemperature) { $this->assertEquals($temperature, $body['temperature']); }
    }

    public function testReasoningSettingsHaveTranslatedOptions(): void {
        require dirname(__DIR__) . '/conf.php';
        require dirname(__DIR__) . '/lang.php';
        $config = $EXT_CONF['llmclassifier']['config'];
        $this->assertArrayHasKey('llm_reasoning_effort', $config);
        $this->assertArrayHasKey('llm_max_output_tokens', $config);
        $this->assertTrue($config['llm_reasoning_effort']['allow_empty']);
        $this->assertSame(['none', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max'], array_keys($config['llm_reasoning_effort']['options']));
        foreach (['de_DE', 'en_GB'] as $locale) {
            foreach (['llm_reasoning_effort', 'llm_max_output_tokens'] as $key) {
                $this->assertNotEmpty($__lang[$locale][$key . '_desc']);
            }
            foreach ($config['llm_reasoning_effort']['options'] as $label) {
                $this->assertNotEmpty($__lang[$locale]['llmclassifier_' . $label]);
            }
        }
    }

    public function testFreshClassifiersUseCurrentSettingsWithoutCrossInstanceCache(): void {
        $settings = (object)['_extensions' => ['llmclassifier' => [
            'llm_endpoint' => 'https://first.example/openai/v1', 'llm_api_key' => 'first-key',
            'llm_model' => 'first-model', 'llm_api_format' => 'responses', 'llm_auth_method' => 'api-key',
            'llm_reasoning_effort' => 'low', 'llm_max_output_tokens' => '1200', 'pdftotext_path' => '/bin/true'
        ]]];
        $history = [];
        $stack = HandlerStack::create(new MockHandler(array_fill(0, 3, new Response(200, ['Content-Type' => 'application/json'], file_get_contents(__DIR__ . '/fixtures/responses-success.json')))));
        $stack->push(Middleware::history($history));
        for ($i = 0; $i < 3; $i++) {
            if ($i === 2) {
                $settings->_extensions['llmclassifier'] = [
                    'llm_endpoint' => 'https://second.example/openai/v1', 'llm_api_key' => 'second-key',
                    'llm_model' => 'second-model', 'llm_api_format' => 'responses', 'llm_auth_method' => 'bearer',
                    'pdftotext_path' => '/bin/true'
                ];
            }
            $classifier = new SeedDMS_DocumentClassifier($settings, new stdClass(), null);
            $adapter = (new ReflectionProperty($classifier, 'llmClient'))->getValue($classifier);
            (new ReflectionProperty($adapter, 'httpClient'))->setValue($adapter, new Client(['handler' => $stack]));
            $this->assertNotNull($adapter->chatCompletion('JSON', 'Document'));
            $request = $history[$i]['request'];
            $body = json_decode((string)$request->getBody(), true);
            $this->assertSame($i === 2 ? 'https://second.example/openai/v1/responses' : 'https://first.example/openai/v1/responses', (string)$request->getUri());
            $this->assertSame($i === 2 ? '' : 'first-key', $request->getHeaderLine('api-key'));
            $this->assertSame($i === 2 ? 'Bearer second-key' : '', $request->getHeaderLine('Authorization'));
            $this->assertSame($i === 2 ? 'second-model' : 'first-model', $body['model']);
            if ($i === 2) {
                $this->assertArrayNotHasKey('reasoning', $body);
                $this->assertArrayNotHasKey('max_output_tokens', $body);
            } else {
                $this->assertSame(['effort' => 'low'], $body['reasoning']);
                $this->assertSame(1200, $body['max_output_tokens']);
            }
            $this->assertArrayNotHasKey('temperature', $body);
        }
    }
}
