<?php

declare(strict_types=1);

use IPTools\Exception\IpException;
use IPTools\Models\IpRange;
use IPTools\ParseFlags;
use IPTools\Parser;
use IPTools\PropertyTrait;
use IPTools\Storage\AddressCodec;
use PHPUnit\Framework\TestCase;

final class DxCoverageTest extends TestCase
{
    public function test_ip_range_model_uses_default_table_and_casts(): void
    {
        $model = new IpRange;

        $this->assertSame('ip_ranges', $model->getTable());
        $this->assertFalse($model->timestamps);
        $this->assertSame('array', $model->getCasts()['metadata']);
    }

    public function test_property_trait_errors_include_context(): void
    {
        $probe = new PropertyTraitProbe;

        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });

        try {
            $probe->missing;
            $this->fail('Expected undefined property read to trigger an error');
        } catch (ErrorException $exception) {
            $this->assertStringContainsString('PropertyTraitProbe::$missing', $exception->getMessage());
        }

        try {
            $probe->missing = 'value';
            $this->fail('Expected undefined property write to trigger an error');
        } catch (ErrorException $exception) {
            $this->assertStringContainsString('PropertyTraitProbe::$missing', $exception->getMessage());
        } finally {
            restore_error_handler();
        }

        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = [$severity, $message];

            return true;
        });

        try {
            $value = $probe->unknown;
            $probe->unknown = 'x';
        } finally {
            restore_error_handler();
        }

        $this->assertNull($value);
        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('PropertyTraitProbe::$unknown', $warnings[0][1]);
        $this->assertStringContainsString('PropertyTraitProbe::$unknown', $warnings[1][1]);
    }

    public function test_address_codec_rejects_invalid_binary_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address binary value must be exactly 16 bytes');

        AddressCodec::from16('short', 4);
    }

    public function test_address_codec_rejects_invalid_version(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Version must be 4 or 6');

        AddressCodec::from16(str_repeat("\x00", 16), 5);
    }

    public function test_parser_rejects_empty_inputs_and_invalid_zone_rules(): void
    {
        $this->expectException(IpException::class);
        $this->expectExceptionMessage('Invalid IP address format');
        Parser::ip('   ');
    }

    public function test_parser_rejects_zone_id_when_not_allowed(): void
    {
        $this->expectException(IpException::class);
        $this->expectExceptionMessage('Zone identifiers are not allowed');

        Parser::ip('fe80::1%eth0', ParseFlags::STRICT);
    }

    public function test_parser_rejects_empty_zone_id(): void
    {
        $this->expectException(IpException::class);
        $this->expectExceptionMessage('Invalid IPv6 zone identifier');

        Parser::ip('fe80::1%', ParseFlags::ALLOW_ZONE_ID);
    }

    public function test_parser_range_rejects_empty_input(): void
    {
        $this->expectException(IpException::class);
        $this->expectExceptionMessage('Invalid range/network format');

        Parser::range(' ');
    }

    public function test_parser_any_routes_space_delimited_networks_to_range_parser(): void
    {
        $result = Parser::any('10.0.0.0 255.255.255.0');

        $this->assertSame('10.0.0.0/24', (string) $result);
    }

    public function test_parser_non_quad_ipv4_validation_paths(): void
    {
        $result = Parser::ip('10.1', ParseFlags::ALLOW_NON_QUAD_IPV4);
        $this->assertSame('10.0.0.1', (string) $result->ip);

        $this->expectException(IpException::class);
        $this->expectExceptionMessage('Invalid non-quad IPv4 format');
        Parser::ip('10.999', ParseFlags::ALLOW_NON_QUAD_IPV4);
    }
}

final class PropertyTraitProbe
{
    use PropertyTrait;

    public function getKnown(): string
    {
        return 'known';
    }
}
