<?php

namespace Tests\Feature;

use App\Models\GhlConnection;
use App\Services\HighLevelClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\IntegrationHelpers;
use Tests\TestCase;

class HighLevelCustomFieldsTest extends TestCase
{
    use IntegrationHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureIntegration();
    }

    public function test_update_custom_fields_sends_put_request_with_correct_payload(): void
    {
        $conn = $this->connection('LOC_TEST');
        $capturedPayload = null;

        $this->httpFake([
            'https://services.leadconnectorhq.com/*' => function (Request $r) use (&$capturedPayload) {
                if ($r->method() === 'PUT' && str_contains($r->url(), '/contacts/CONT_123')) {
                    $capturedPayload = $r->data();

                    return Http::response(['contact' => ['id' => 'CONT_123'] + $capturedPayload]);
                }

                return Http::response(['location' => ['name' => 'Loc']]);
            },
        ]);

        $client = app(HighLevelClient::class);
        $fields = [
            ['id' => 'UUID_CARD', 'field_value' => '999888'],
            ['id' => 'UUID_BALANCE', 'field_value' => 25.50],
        ];

        $res = $client->updateCustomFields($conn, 'CONT_123', $fields);

        $this->assertSame('CONT_123', $res['id']);
        $this->assertNotNull($capturedPayload);
        $this->assertSame($fields, $capturedPayload['customFields']);
    }

    public function test_update_custom_fields_returns_immediately_when_fields_empty(): void
    {
        $conn = $this->connection('LOC_TEST');

        Http::preventStrayRequests();

        $client = app(HighLevelClient::class);
        $res = $client->updateCustomFields($conn, 'CONT_123', []);

        $this->assertSame('CONT_123', $res['id']);
    }

    public function test_get_custom_fields_fetches_and_returns_list(): void
    {
        $conn = $this->connection('LOC_TEST');

        $this->httpFake([
            'https://services.leadconnectorhq.com/*' => function (Request $r) {
                if (str_contains($r->url(), '/customFields')) {
                    return Http::response([
                        'customFields' => [
                            ['id' => 'UUID_1', 'name' => 'Card Number', 'dataType' => 'TEXT'],
                            ['id' => 'UUID_2', 'name' => 'Balance', 'dataType' => 'NUMERICAL'],
                        ],
                    ]);
                }

                return Http::response([]);
            },
        ]);

        $client = app(HighLevelClient::class);
        $fields = $client->getCustomFields($conn);

        $this->assertCount(2, $fields);
        $this->assertSame('UUID_1', $fields[0]['id']);
        $this->assertSame('Card Number', $fields[0]['name']);
    }
}

