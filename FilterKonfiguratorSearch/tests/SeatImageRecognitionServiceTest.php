<?php

namespace Plugin\FilterKonfiguratorSearch\Tests;

require_once __DIR__ . '/../src/Service/SeatImageRecognitionService.php';

use PHPUnit\Framework\TestCase;
use Plugin\FilterKonfiguratorSearch\Service\SeatImageRecognitionService;

class SeatImageRecognitionServiceTest extends TestCase
{
    /** @test */
    public function it_parses_direct_structured_output(): void
    {
        $result = SeatImageRecognitionService::parseResponse([
            'output_text' => \json_encode([
                'seat_code' => '3',
                'confidence' => 91,
                'reason' => 'Kopfstütze und Armlehnen stimmen überein.',
            ]),
        ]);

        $this->assertSame('3', $result['seat_code']);
        $this->assertSame(91, $result['confidence']);
    }

    /** @test */
    public function it_parses_nested_responses_api_output(): void
    {
        $result = SeatImageRecognitionService::parseResponse([
            'output' => [[
                'content' => [[
                    'type' => 'output_text',
                    'text' => '{"seat_code":"10","confidence":78,"reason":"Integrierte Kopfstütze."}',
                ]],
            ]],
        ]);

        $this->assertSame('10', $result['seat_code']);
        $this->assertSame(78, $result['confidence']);
    }

    /** @test */
    public function it_selects_the_highest_independent_similarity_score(): void
    {
        $scores = \array_fill_keys(\array_map('strval', \range(1, 14)), 10);
        $scores['3'] = 94;
        $scores['5'] = 61;

        $result = SeatImageRecognitionService::parseResponse([
            'output_text' => \json_encode([
                'scores' => $scores,
                'reason' => 'Referenz 3 stimmt am besten überein.',
            ]),
        ]);

        $this->assertSame('3', $result['seat_code']);
        $this->assertSame(94, $result['confidence']);
    }

    /** @test */
    public function it_does_not_auto_select_an_ambiguous_score(): void
    {
        $scores = \array_fill_keys(\array_map('strval', \range(1, 14)), 10);
        $scores['1'] = 90;
        $scores['2'] = 87;

        $result = SeatImageRecognitionService::parseResponse([
            'output_text' => \json_encode([
                'scores' => $scores,
                'reason' => 'İki referans birbirine çok yakın.',
            ]),
        ]);

        $this->assertSame('1', $result['seat_code']);
        $this->assertSame(54, $result['confidence']);
    }

    /** @test */
    public function it_rejects_unknown_seat_codes(): void
    {
        $this->expectException(\RuntimeException::class);

        SeatImageRecognitionService::parseResponse([
            'output_text' => '{"seat_code":"99","confidence":100,"reason":"invalid"}',
        ]);
    }

    /** @test */
    public function it_rejects_invalid_json(): void
    {
        $this->expectException(\RuntimeException::class);

        SeatImageRecognitionService::parseResponse(['output_text' => 'not-json']);
    }

    /** @test */
    public function it_maps_provider_errors_to_safe_diagnostic_codes(): void
    {
        $this->assertSame(
            'openai_auth',
            SeatImageRecognitionService::classifyApiError(401, 'invalid_api_key', 'invalid_request_error')[0]
        );
        $this->assertSame(
            'openai_quota',
            SeatImageRecognitionService::classifyApiError(429, 'insufficient_quota', 'insufficient_quota')[0]
        );
        $this->assertSame(
            'openai_request',
            SeatImageRecognitionService::classifyApiError(400, '', 'invalid_request_error')[0]
        );
    }
}
