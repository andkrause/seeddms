<?php

// Run in the built SeedDMS image; only a temporary copy of default settings is saved.
require '/var/seeddms/seeddms60x/www/inc/inc.ClassSettings.php';
require dirname(__DIR__) . '/vendor/autoload.php';
class SeedDMS_ExtBase {}
require dirname(__DIR__) . '/class.llmclassifier.php';
$file = tempnam(sys_get_temp_dir(), 'temperature-test-');
copy('/var/seeddms/seeddms60x/conf/settings.xml', $file);
try {
    foreach (['omit' => null, ' OMIT ' => null, '0.0' => 0.0, '' => 0.3, '0' => 0.3] as $input => $expected) {
        $settings = new Seeddms\Seeddms\Settings($file);
        $settings->_extensions['llmclassifier'] = [
            '__disable__' => false, 'llm_endpoint' => 'https://example.com/openai/v1',
            'llm_model' => 'deployment', 'llm_api_format' => 'responses',
            'llm_auth_method' => 'none', 'llm_temperature' => (string)$input,
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
    }
    echo "SeedDMS save/reload temperature checks passed (5 cases)\n";
} finally {
    unlink($file);
}
