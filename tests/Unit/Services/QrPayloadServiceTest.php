<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessLogicException;
use App\Models\Lot;
use App\Models\Product;
use App\Services\QrLabel\QrPayloadService;
use PHPUnit\Framework\Attributes\Test;

class QrPayloadServiceTest extends \Tests\TestCase
{
    private QrPayloadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QrPayloadService();
    }

    // -------------------------------------------------------------------------
    // generatePayload
    // -------------------------------------------------------------------------

    #[Test]
    public function generate_payload_with_batch_and_expiry(): void
    {
        $product = $this->makeProduct('REF-001');
        $lot = $this->makeLot($product, 'LOT-ABC', 'BATCH-X', '2027-06-30');

        $payload = $this->service->generatePayload($lot);

        $this->assertSame('V=1;REF=REF-001;LOT=LOT-ABC;BATCH=BATCH-X;EXP=2027-06-30', $payload);
    }

    #[Test]
    public function generate_payload_without_batch_uses_dash(): void
    {
        $product = $this->makeProduct('REF-002');
        $lot = $this->makeLot($product, 'LOT-DEF', null, '2027-01-01');

        $payload = $this->service->generatePayload($lot);

        $this->assertSame('V=1;REF=REF-002;LOT=LOT-DEF;BATCH=-;EXP=2027-01-01', $payload);
    }

    #[Test]
    public function generate_payload_without_expiry_uses_dash(): void
    {
        $product = $this->makeProduct('REF-003');
        $lot = $this->makeLot($product, 'LOT-GHI', 'BATCH-Y', null);

        $payload = $this->service->generatePayload($lot);

        $this->assertSame('V=1;REF=REF-003;LOT=LOT-GHI;BATCH=BATCH-Y;EXP=-', $payload);
    }

    #[Test]
    public function generate_payload_without_batch_and_without_expiry(): void
    {
        $product = $this->makeProduct('REF-004');
        $lot = $this->makeLot($product, 'LOT-JKL', null, null);

        $payload = $this->service->generatePayload($lot);

        $this->assertSame('V=1;REF=REF-004;LOT=LOT-JKL;BATCH=-;EXP=-', $payload);
    }

    #[Test]
    public function generate_payload_throws_when_ref_num_is_empty(): void
    {
        $product = $this->makeProduct('');
        $lot = $this->makeLot($product, 'LOT-001', null, null);

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/ref_num|lot_number/i');

        $this->service->generatePayload($lot);
    }

    #[Test]
    public function generate_payload_throws_when_lot_number_is_empty(): void
    {
        $product = $this->makeProduct('REF-005');
        $lot = $this->makeLot($product, '', null, null);

        $this->expectException(BusinessLogicException::class);

        $this->service->generatePayload($lot);
    }

    #[Test]
    public function generate_payload_throws_when_product_is_null(): void
    {
        $lot = new Lot(['lot_number' => 'LOT-001']);
        $lot->setRelation('product', null);

        $this->expectException(BusinessLogicException::class);

        $this->service->generatePayload($lot);
    }

    // -------------------------------------------------------------------------
    // validatePayload
    // -------------------------------------------------------------------------

    #[Test]
    public function validate_payload_returns_parsed_segments_for_valid_input(): void
    {
        $result = $this->service->validatePayload('V=1;REF=REF-X;LOT=LOT-Y;BATCH=-;EXP=2027-06-30');

        $this->assertSame('1', $result['version']);
        $this->assertSame('REF-X', $result['ref']);
        $this->assertSame('LOT-Y', $result['lot']);
        $this->assertSame('-', $result['batch']);
        $this->assertSame('2027-06-30', $result['exp']);
    }

    #[Test]
    public function validate_payload_throws_for_missing_required_field(): void
    {
        // Missing BATCH and EXP
        $this->expectException(BusinessLogicException::class);
        $this->service->validatePayload('V=1;REF=R;LOT=L');
    }

    #[Test]
    public function validate_payload_throws_for_unsupported_version(): void
    {
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/version/i');

        $this->service->validatePayload('V=2;REF=R;LOT=L;BATCH=-;EXP=-');
    }

    #[Test]
    public function validate_payload_throws_for_segment_without_equals(): void
    {
        $this->expectException(BusinessLogicException::class);

        $this->service->validatePayload('V=1;REF=R;INVALIDNOEQUALS;LOT=L;BATCH=-;EXP=-');
    }

    #[Test]
    public function validate_payload_throws_for_empty_required_field_value(): void
    {
        $this->expectException(BusinessLogicException::class);

        // LOT is present but empty
        $this->service->validatePayload('V=1;REF=R;LOT=;BATCH=-;EXP=-');
    }

    // -------------------------------------------------------------------------
    // Helpers (build model stubs without touching DB)
    // -------------------------------------------------------------------------

    private function makeProduct(string $refNum): Product
    {
        $product = new Product(['ref_num' => $refNum]);
        return $product;
    }

    private function makeLot(Product $product, string $lotNumber, ?string $batch, ?string $expiry): Lot
    {
        $lot = new Lot([
            'product_id'          => 1, // Fake ID
            'lot_number'          => $lotNumber,
            'supplier_batch_code' => $batch,
            'expiry_date'         => $expiry,
        ]);

        // Manually cast expiry_date the same way Eloquent would
        if ($expiry !== null) {
            $lot->expiry_date = \Illuminate\Support\Carbon::parse($expiry);
        }

        $lot->setRelation('product', $product);

        return $lot;
    }
}
