<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessLogicException;
use App\Models\InstrumentSet;
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
    public function generate_payload_with_manufacturing_date_and_expiry(): void
    {
        $product = $this->makeProduct('REF-001');
        $lot = $this->makeProductLot($product, 'LOT-ABC', '2026-06-15', '2027-06-30');

        $payload = $this->service->generatePayload($lot);

        $this->assertSame('V=1;REF=REF-001;LOT=LOT-ABC;MFG=2026-06-15;EXP=2027-06-30', $payload);
    }

    #[Test]
    public function generate_payload_without_manufacturing_date_uses_dash(): void
    {
        $product = $this->makeProduct('REF-002');
        $lot = $this->makeProductLot($product, 'LOT-DEF', null, '2027-01-01');

        $payload = $this->service->generatePayload($lot);

        $this->assertSame('V=1;REF=REF-002;LOT=LOT-DEF;MFG=-;EXP=2027-01-01', $payload);
    }

    #[Test]
    public function generate_payload_without_expiry_uses_dash(): void
    {
        $product = $this->makeProduct('REF-003');
        $lot = $this->makeProductLot($product, 'LOT-GHI', '2026-02-10', null);

        $payload = $this->service->generatePayload($lot);

        $this->assertSame('V=1;REF=REF-003;LOT=LOT-GHI;MFG=2026-02-10;EXP=-', $payload);
    }

    #[Test]
    public function generate_payload_without_manufacturing_date_and_without_expiry(): void
    {
        $product = $this->makeProduct('REF-004');
        $lot = $this->makeProductLot($product, 'LOT-JKL', null, null);

        $payload = $this->service->generatePayload($lot);

        $this->assertSame('V=1;REF=REF-004;LOT=LOT-JKL;MFG=-;EXP=-', $payload);
    }

    #[Test]
    public function generate_payload_uses_instrument_set_code_when_lot_belongs_to_set(): void
    {
        $instrumentSet = $this->makeInstrumentSet('SET-001');
        $lot = $this->makeInstrumentSetLot($instrumentSet, 'LOT-SET-001', '2026-03-01', '2027-03-01');

        $payload = $this->service->generatePayload($lot);

        $this->assertSame('V=1;REF=SET-001;LOT=LOT-SET-001;MFG=2026-03-01;EXP=2027-03-01', $payload);
    }

    #[Test]
    public function generate_payload_throws_when_ref_num_is_empty(): void
    {
        $product = $this->makeProduct('');
        $lot = $this->makeProductLot($product, 'LOT-001', null, null);

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/ref_num|lot_number/i');

        $this->service->generatePayload($lot);
    }

    #[Test]
    public function generate_payload_throws_when_lot_number_is_empty(): void
    {
        $product = $this->makeProduct('REF-005');
        $lot = $this->makeProductLot($product, '', null, null);

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
        $result = $this->service->validatePayload('V=1;REF=REF-X;LOT=LOT-Y;MFG=-;EXP=2027-06-30');

        $this->assertSame('1', $result['version']);
        $this->assertSame('REF-X', $result['ref']);
        $this->assertSame('LOT-Y', $result['lot']);
        $this->assertSame('-', $result['mfg']);
        $this->assertSame('2027-06-30', $result['exp']);
    }

    #[Test]
    public function validate_payload_accepts_legacy_batch_segment_as_mfg(): void
    {
        $result = $this->service->validatePayload('V=1;REF=REF-X;LOT=LOT-Y;BATCH=2026-01-10;EXP=2027-06-30');

        $this->assertSame('2026-01-10', $result['mfg']);
    }

    #[Test]
    public function validate_payload_throws_for_missing_required_field(): void
    {
        // Missing MFG and EXP
        $this->expectException(BusinessLogicException::class);
        $this->service->validatePayload('V=1;REF=R;LOT=L');
    }

    #[Test]
    public function validate_payload_throws_for_unsupported_version(): void
    {
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessageMatches('/version/i');

        $this->service->validatePayload('V=2;REF=R;LOT=L;MFG=-;EXP=-');
    }

    #[Test]
    public function validate_payload_throws_for_segment_without_equals(): void
    {
        $this->expectException(BusinessLogicException::class);

        $this->service->validatePayload('V=1;REF=R;INVALIDNOEQUALS;LOT=L;MFG=-;EXP=-');
    }

    #[Test]
    public function validate_payload_throws_for_empty_required_field_value(): void
    {
        $this->expectException(BusinessLogicException::class);

        // LOT is present but empty
        $this->service->validatePayload('V=1;REF=R;LOT=;MFG=-;EXP=-');
    }

    #[Test]
    public function build_tspl_payload_falls_back_to_ref_when_product_name_is_missing(): void
    {
        $product = $this->makeProduct('REF-TSPL-001', null);
        $lot = $this->makeProductLot($product, 'LOT-TSPL-001', null, '2027-01-01');

        $tspl = $this->service->buildTsplPayload('V=1;REF=REF-TSPL-001;LOT=LOT-TSPL-001;MFG=-;EXP=2027-01-01', $lot);

        $this->assertStringContainsString('TEXT 8,140,"2",0,1,1,"REF-TSPL-001"', $tspl);
        $this->assertStringNotContainsString('TEXT 8,140,"2",0,1,1,"-"', $tspl);
        $this->assertStringContainsString('TEXT 8,205,"2",0,1,1,"EXP: 2027-01-01"', $tspl);
        $this->assertStringNotContainsString('MFG:', $tspl);
    }

    #[Test]
    public function build_tspl_payload_places_mfg_on_same_row_as_exp(): void
    {
        $product = $this->makeProduct('REF-TSPL-002', 'Print Layout Product');
        $lot = $this->makeProductLot($product, 'LOT-TSPL-002', '2026-07-18', '2030-06-30');

        $tspl = $this->service->buildTsplPayload('V=1;REF=REF-TSPL-002;LOT=LOT-TSPL-002;MFG=2026-07-18;EXP=2030-06-30', $lot);

        $this->assertStringContainsString('TEXT 8,205,"1",0,1,1,"EXP: 2030-06-30"', $tspl);
        $this->assertStringContainsString('TEXT 210,205,"1",0,1,1,"MFG: 2026-07-18"', $tspl);
        $this->assertStringNotContainsString('TEXT 8,225', $tspl);
    }

    #[Test]
    public function build_tspl_payload_omits_date_row_when_exp_and_mfg_are_missing(): void
    {
        $product = $this->makeProduct('REF-TSPL-003', 'No Date Product');
        $lot = $this->makeProductLot($product, 'LOT-TSPL-003', null, null);

        $tspl = $this->service->buildTsplPayload('V=1;REF=REF-TSPL-003;LOT=LOT-TSPL-003;MFG=-;EXP=-', $lot);

        $this->assertStringContainsString('TEXT 8,140,"2",0,1,1,"No Date Product"', $tspl);
        $this->assertStringContainsString('TEXT 8,163,"2",0,1,1,"REF: REF-TSPL-003"', $tspl);
        $this->assertStringNotContainsString('EXP:', $tspl);
        $this->assertStringNotContainsString('MFG:', $tspl);
    }

    #[Test]
    public function build_tspl_payload_uses_compact_detail_template_when_product_name_exceeds_23_characters(): void
    {
        $product = $this->makeProduct('REF-TSPL-004', 'Long Product Name Over 23');
        $lot = $this->makeProductLot($product, 'LOT-TSPL-004', '2026-07-18', '2030-06-30');

        $tspl = $this->service->buildTsplPayload('V=1;REF=REF-TSPL-004;LOT=LOT-TSPL-004;MFG=2026-07-18;EXP=2030-06-30', $lot);

        $this->assertStringContainsString('TEXT 8,140,"1",0,1,1,"Long Product Name Over 23"', $tspl);
        $this->assertStringContainsString('TEXT 8,163,"1",0,1,1,"REF: REF-TSPL-004"', $tspl);
        $this->assertStringContainsString('TEXT 8,183,"1",0,1,1,"LOT: LOT-TSPL-004"', $tspl);
        $this->assertStringContainsString('TEXT 8,205,"1",0,1,1,"EXP: 2030-06-30"', $tspl);
        $this->assertStringContainsString('TEXT 210,205,"1",0,1,1,"MFG: 2026-07-18"', $tspl);
    }

    #[Test]
    public function build_tspl_payload_sanitizes_non_latin_symbols_for_printer_safe_output(): void
    {
        $product = $this->makeProduct('REF-TSPL-005', 'Hamstring Tendon Stripper, Φ7mm, Curved');
        $lot = $this->makeProductLot($product, 'LOT-TSPL-005', null, '2030-06-30');

        $tspl = $this->service->buildTsplPayload('V=1;REF=REF-TSPL-005;LOT=LOT-TSPL-005;MFG=-;EXP=2030-06-30', $lot);

        $this->assertStringNotContainsString('Φ', $tspl);
        $this->assertStringContainsString('Hamstring Tendon Stripper,', $tspl);
        $this->assertStringContainsString('7mm', $tspl);
    }

    // -------------------------------------------------------------------------
    // Helpers (build model stubs without touching DB)
    // -------------------------------------------------------------------------

    private function makeProduct(string $refNum, ?string $productName = 'Test Product'): Product
    {
        $product = new Product([
            'ref_num' => $refNum,
            'product_name' => $productName,
        ]);
        return $product;
    }

    private function makeProductLot(Product $product, string $lotNumber, ?string $manufacturingDate, ?string $expiry): Lot
    {
        $lot = new Lot([
            'product_id'          => 1, // Fake ID
            'lot_number'          => $lotNumber,
            'manufacturing_date' => $manufacturingDate,
            'expiry_date'         => $expiry,
        ]);

        if ($manufacturingDate !== null) {
            $lot->manufacturing_date = \Illuminate\Support\Carbon::parse($manufacturingDate);
        }

        if ($expiry !== null) {
            $lot->expiry_date = \Illuminate\Support\Carbon::parse($expiry);
        }

        $lot->setRelation('product', $product);

        return $lot;
    }

    private function makeInstrumentSet(string $setCode): InstrumentSet
    {
        return new InstrumentSet(['set_code' => $setCode]);
    }

    private function makeInstrumentSetLot(InstrumentSet $instrumentSet, string $lotNumber, ?string $manufacturingDate, ?string $expiry): Lot
    {
        $lot = new Lot([
            'instrument_set_id'   => 1, // Fake ID
            'lot_number'          => $lotNumber,
            'manufacturing_date'  => $manufacturingDate,
            'expiry_date'         => $expiry,
        ]);

        if ($manufacturingDate !== null) {
            $lot->manufacturing_date = \Illuminate\Support\Carbon::parse($manufacturingDate);
        }

        if ($expiry !== null) {
            $lot->expiry_date = \Illuminate\Support\Carbon::parse($expiry);
        }

        $lot->setRelation('instrumentSet', $instrumentSet);

        return $lot;
    }
}
