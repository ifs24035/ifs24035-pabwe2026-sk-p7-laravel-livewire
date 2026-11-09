<?php

namespace Tests\Unit\Services;

use App\Features\Ai\Services\OpenAiStockGeneratorService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiStockGeneratorServiceTest extends TestCase
{
    public function test_empty_prompt_throws_exception(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Prompt tidak boleh kosong.');
        $service->generateStocks('   ');
    }

    public function test_successful_generation(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'stocks' => [
                                    [
                                        'name' => 'Beras Premium 5 Kg',
                                        'sku' => 'BRG-001',
                                        'description' => 'Beras premium',
                                        'quantity' => 20,
                                        'unit' => 'karung',
                                        'price' => 68000,
                                        'min_stock' => 10,
                                    ],
                                    [
                                        'name' => 'Gula 1 Kg',
                                        'sku' => '',
                                        'description' => null,
                                        'quantity' => 'abc',
                                        'unit' => '',
                                        'price' => 'gratis',
                                        'min_stock' => null,
                                    ],
                                    [
                                        'name' => 'Minyak 2 Liter',
                                        'quantity' => 4,
                                        'min_stock' => 10,
                                        'price' => -100,
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');
        $results = $service->generateStocks('Buatkan 3 barang', 3);

        Http::assertSent(function (Request $request) {
            $systemPrompt = $request['messages'][0]['content'];

            return str_contains($systemPrompt, 'inventory')
                && str_contains($systemPrompt, 'exactly 3')
                && $request->hasHeader('Authorization', 'Bearer mock-key');
        });

        $this->assertCount(3, $results);

        $this->assertEquals('Beras Premium 5 Kg', $results[0]->name);
        $this->assertEquals('BRG-001', $results[0]->sku);
        $this->assertEquals('Beras premium', $results[0]->description);
        $this->assertSame(20, $results[0]->quantity);
        $this->assertEquals('karung', $results[0]->unit);
        $this->assertSame(68000.0, $results[0]->price);
        $this->assertSame(10, $results[0]->minStock);
        $this->assertEquals('available', $results[0]->status);

        // Invalid / missing values fall back to safe defaults
        $this->assertEquals('Gula 1 Kg', $results[1]->name);
        $this->assertNull($results[1]->sku);
        $this->assertNull($results[1]->description);
        $this->assertSame(0, $results[1]->quantity);
        $this->assertEquals('pcs', $results[1]->unit);
        $this->assertNull($results[1]->price);
        $this->assertSame(5, $results[1]->minStock);
        $this->assertEquals('out_of_stock', $results[1]->status);

        // Low stock derived from quantity vs min_stock, negative price clamped to 0
        $this->assertEquals('low_stock', $results[2]->status);
        $this->assertSame(0.0, $results[2]->price);
    }

    public function test_api_failure_throws_exception(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Invalid API Key provided',
                ],
            ], 401),
        ]);

        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API Error: Invalid API Key provided');
        $service->generateStocks('Daftar stok toko');
    }

    public function test_api_failure_without_error_message_uses_status_code(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response('Server error', 500),
        ]);

        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API Error: Request failed with status code 500');
        $service->generateStocks('Daftar stok toko');
    }

    public function test_parse_content_with_markdown_fences(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');
        $raw = "```json\n[{\"name\": \"Code Fence Stock\", \"description\": \"Test desc\", \"quantity\": 9, \"min_stock\": 5}]\n```";

        $results = $service->parseContent($raw);
        $this->assertCount(1, $results);
        $this->assertEquals('Code Fence Stock', $results[0]->name);
        $this->assertEquals('available', $results[0]->status);
    }

    public function test_parse_content_invalid_json(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI mengembalikan format respon yang tidak valid.');
        $service->parseContent('Not valid JSON');
    }

    public function test_parse_content_empty_stocks(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tidak ada item stok yang berhasil di-generate dari AI.');
        $service->parseContent('{"stocks": []}');
    }

    public function test_parse_content_no_valid_items(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'mock-key', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Format item stok yang dikembalikan AI tidak sesuai spesifikasi.');
        $service->parseContent('{"stocks": [{"bad_key": "val"}]}');
    }

    public function test_constructor_handles_swapped_endpoint_and_key(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"stocks":[{"name":"Barang"}]}']]],
            ]),
        ]);

        // Key given as URL, endpoint given as secret
        $service = new OpenAiStockGeneratorService('sk-secret-key', 'https://ai.example.com/v1');
        $stocks = $service->generateStocks('Daftar barang');
        $this->assertCount(1, $stocks);
    }

    public function test_constructor_auto_prefixes_https(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"stocks":[{"name":"Barang"}]}']]],
            ]),
        ]);

        $service = new OpenAiStockGeneratorService('api.openai.com/v1', 'mock-key');
        $stocks = $service->generateStocks('Daftar barang');
        $this->assertCount(1, $stocks);
    }
}
