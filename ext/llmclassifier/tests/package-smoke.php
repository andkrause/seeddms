<?php

$extension = $argv[1];
spl_autoload_register(function ($class) {});
if (isset($argv[2])) {
    require $argv[2];
    // Force the host's shared dependencies to load before the SDK autoloader.
    class_exists(GuzzleHttp\Client::class);
    interface_exists(Psr\Http\Message\RequestInterface::class);
}
require $extension . '/vendor/autoload.php';
class SeedDMS_ExtBase {}
require $extension . '/class.llmclassifier.php';

foreach ([OpenAI::class, GuzzleHttp\Client::class, SeedDMS_LLMClient::class] as $class) {
    if (!class_exists($class)) { throw new RuntimeException('Missing packaged class: ' . $class); }
}
if (is_dir($extension . '/tests') || is_dir($extension . '/vendor/phpunit')) {
    throw new RuntimeException('Development files included in package');
}
if (!is_file($extension . '/vendor/openai-php/client/LICENSE.md')) {
    throw new RuntimeException('Missing SDK license');
}
$stack = GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler([
    new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], '{"id":"resp_test","object":"response","created_at":1730000000,"status":"completed","model":"deployment","parallel_tool_calls":false,"tool_choice":"auto","tools":[],"output":[{"id":"msg_test","type":"message","role":"assistant","status":"completed","content":[{"type":"output_text","text":"{\"name\":\"Invoice\",\"categories\":[],\"keywords\":[]}","annotations":[]}]}]}')
]));
$client = new SeedDMS_LLMClient('https://example.com/openai/v1/responses', 'test-key', 'deployment', null, null, null, 'responses', 'api-key', new GuzzleHttp\Client(['handler' => $stack]));
if ($client->chatCompletion('JSON', 'Document') !== ['name' => 'Invoice', 'categories' => [], 'keywords' => []]) {
    throw new RuntimeException('Packaged SDK classification failed');
}
echo "Package autoload smoke check passed\n";
