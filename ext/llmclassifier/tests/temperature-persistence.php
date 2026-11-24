<?php

// Run in the built SeedDMS image; only a temporary copy of default settings is saved.
require '/var/seeddms/seeddms60x/www/inc/inc.ClassSettings.php';
require dirname(__DIR__) . '/vendor/autoload.php';
class SeedDMS_ExtBase {}
require dirname(__DIR__) . '/class.llmclassifier.php';
$file = tempnam(sys_get_temp_dir(), 'temperature-test-');
copy('/var/seeddms/seeddms60x/conf/settings.xml', $file);
try {
    foreach (['omit' => null, ' OMIT ' => null, '0.0' => 0.0, '' => null, '0' => null, '0.3' => 0.3] as $input => $expected) {
        $settings = new Seeddms\Seeddms\Settings($file);
        $settings->_extensions['llmclassifier'] = [
            '__disable__' => false, 'llm_endpoint' => 'https://example.com/openai/v1',
            'llm_model' => 'deployment', 'llm_api_format' => 'responses',
            'llm_auth_method' => 'none', 'llm_temperature' => (string)$input,
            'llm_reasoning_effort' => $input === '' ? '' : 'low', 'llm_max_output_tokens' => $input === '' ? '' : '1200',
            'pdftotext_path' => '/bin/true'
        ];
        if (!$settings->save()) { throw new RuntimeException('Settings save failed'); }
        $reloaded = new Seeddms\Seeddms\Settings($file);
        $classifier = new SeedDMS_DocumentClassifier($reloaded, new stdClass(), null);
        $adapter = (new ReflectionProperty($classifier, 'llmClient'))->getValue($classifier);
        $history = [];
        $stack = GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler([
            new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], file_get_contents(__DIR__ . '/fixtures/responses-success.json'))
        ]));
        $stack->push(GuzzleHttp\Middleware::history($history));
        (new ReflectionProperty($adapter, 'httpClient'))->setValue($adapter, new GuzzleHttp\Client(['handler' => $stack]));
        if ($adapter->chatCompletion('JSON', 'Text') === null) { throw new RuntimeException('Classification failed'); }
        $payload = json_decode((string)$history[0]['request']->getBody(), true);
        if ($expected === null ? array_key_exists('temperature', $payload) : ($payload['temperature'] ?? null) != $expected) {
            throw new RuntimeException('Unexpected persisted temperature for ' . json_encode($input));
        }
        if ($input === '' ? (array_key_exists('reasoning', $payload) || array_key_exists('max_output_tokens', $payload)) : (($payload['reasoning'] ?? null) !== ['effort' => 'low'] || ($payload['max_output_tokens'] ?? null) !== 1200)) {
            throw new RuntimeException('Reasoning controls did not survive settings save/reload');
        }
    }
    echo "SeedDMS save/reload temperature and reasoning checks passed (6 cases)\n";
    $_POST = ['action' => 'saveSettings', 'extensions' => ['llmclassifier' => ['llm_max_output_tokens' => '0']]];
    (new SeedDMS_ExtLLMClassifier())->init();
    $settings->_extensions['llmclassifier']['llm_max_output_tokens'] = $_POST['extensions']['llmclassifier']['llm_max_output_tokens'];
    $settings->save();
    $reloaded = new Seeddms\Seeddms\Settings($file);
    $classifier = new SeedDMS_DocumentClassifier($reloaded, new stdClass(), null);
    $adapter = (new ReflectionProperty($classifier, 'llmClient'))->getValue($classifier);
    $history = [];
    $stack = GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler([
        new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], file_get_contents(__DIR__ . '/fixtures/responses-success.json'))
    ]));
    $stack->push(GuzzleHttp\Middleware::history($history));
    (new ReflectionProperty($adapter, 'httpClient'))->setValue($adapter, new GuzzleHttp\Client(['handler' => $stack]));
    if ($adapter->chatCompletion('JSON', 'Text') !== null || count($history) !== 0) {
        throw new RuntimeException('Saved zero cap bypassed validation');
    }
    if (!isset($reloaded->_extensions['llmclassifier']['llm_max_output_tokens'])) {
        throw new RuntimeException('Invalid zero cap was discarded instead of preserved for validation');
    }
    echo "SeedDMS saved-zero cap rejection passed\n";
    $history = [];
    $success = new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], file_get_contents(__DIR__ . '/fixtures/responses-success.json'));
    $stack = GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler([
        $success,
        new GuzzleHttp\Exception\ConnectException('timeout', new GuzzleHttp\Psr7\Request('POST', 'https://example.com'), null, ['errno' => 28]),
        $success, $success
    ]));
    $stack->push(GuzzleHttp\Middleware::history($history));
    $settings->_extensions['llmclassifier']['llm_max_output_tokens'] = '1200';
    $settings->_extensions['llmclassifier']['llm_api_key'] = 'first-test-key';
    $settings->save();
    for ($i = 0; $i < 4; $i++) {
        if ($i === 3) {
            $settings->_extensions['llmclassifier']['llm_endpoint'] = 'https://updated.example/openai/v1';
            $settings->_extensions['llmclassifier']['llm_model'] = 'updated-model';
            $settings->_extensions['llmclassifier']['llm_api_key'] = 'updated-test-key';
            $settings->_extensions['llmclassifier']['llm_max_output_tokens'] = '';
            $settings->_extensions['llmclassifier']['llm_reasoning_effort'] = '';
            $settings->save();
        }
        $reloaded = new Seeddms\Seeddms\Settings($file);
        $classifier = new SeedDMS_DocumentClassifier($reloaded, new stdClass(), null);
        $adapter = (new ReflectionProperty($classifier, 'llmClient'))->getValue($classifier);
        (new ReflectionProperty($adapter, 'httpClient'))->setValue($adapter, new GuzzleHttp\Client(['handler' => $stack]));
        $result = $adapter->chatCompletion('JSON', 'Document');
        if (($result === null) !== ($i === 1)) { throw new RuntimeException('Unexpected intermittent recovery result'); }
        $request = $history[$i]['request'];
        $payload = json_decode((string)$request->getBody(), true);
        $expectedUrl = $i === 3 ? 'https://updated.example/openai/v1/responses' : 'https://example.com/openai/v1/responses';
        if ((string)$request->getUri() !== $expectedUrl || $payload['model'] !== ($i === 3 ? 'updated-model' : 'deployment')) {
            throw new RuntimeException('Persisted request configuration was stale');
        }
        if ($i < 3 && (string)$request->getBody() !== (string)$history[0]['request']->getBody()) {
            throw new RuntimeException('Unchanged persisted configuration changed after failure');
        }
        if ($i === 3 && (isset($payload['reasoning']) || isset($payload['max_output_tokens']))) {
            throw new RuntimeException('Cleared optional settings remained cached');
        }
        $key = (new ReflectionProperty($adapter, 'apiKey'))->getValue($adapter);
        if ($key !== ($i === 3 ? 'updated-test-key' : 'first-test-key')) {
            throw new RuntimeException('Persisted API key was stale');
        }
    }
    echo "SeedDMS unchanged settings recovery and updated settings reload passed\n";
} finally {
    unlink($file);
}
