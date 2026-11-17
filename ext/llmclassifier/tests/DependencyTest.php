<?php

use PHPUnit\Framework\TestCase;

final class DependencyTest extends TestCase {
    public function testExtensionLoadsWithExistingAutoloader(): void {
        $this->assertTrue(class_exists(OpenAI::class));
        $this->assertTrue(class_exists(GuzzleHttp\Client::class));
        $this->assertTrue(class_exists(SeedDMS_LLMClient::class));
    }
}
