<?php

use Webkul\DataTransfer\Helpers\Exporters\AbstractExporter;
use Webkul\Product\Models\Product;
use Webkul\Shopify\Helpers\Exporters\Product\Exporter;

/**
 * The Shopify product export runs two core paths:
 *
 *  - bulk: Shopify Bulk Operations API (async submit + poll + follow-up phases).
 *    High fixed latency, excellent throughput — the right unit for large catalogs.
 *  - sequential: direct per-product GraphQL calls. Low latency per product —
 *    the right unit for a handful of products, where the bulk fixed cost dominates.
 *
 * shouldUseBulkCorePath() decides between them from the export's product count
 * against the `shopify-bulk-operations.bulk_threshold` config value.
 */
function makeExporterWithFilters(array $filters): Exporter
{
    $exporter = app(Exporter::class);

    $property = (new ReflectionClass(AbstractExporter::class))->getProperty('filters');
    $property->setAccessible(true);
    $property->setValue($exporter, $filters);

    return $exporter;
}

function shouldUseBulkCorePath(Exporter $exporter): bool
{
    $method = new ReflectionMethod($exporter, 'shouldUseBulkCorePath');
    $method->setAccessible(true);

    return $method->invoke($exporter);
}

it('uses the sequential path when the product count is below the bulk threshold', function () {
    config(['shopify-bulk-operations.bulk_threshold' => 10]);

    $skus = Product::factory()->count(5)->create()->pluck('sku')->all();

    $exporter = makeExporterWithFilters(['productfilter' => implode(',', $skus)]);

    expect(shouldUseBulkCorePath($exporter))->toBeFalse();
});

it('uses the bulk path when the product count reaches the bulk threshold', function () {
    config(['shopify-bulk-operations.bulk_threshold' => 3]);

    $skus = Product::factory()->count(3)->create()->pluck('sku')->all();

    $exporter = makeExporterWithFilters(['productfilter' => implode(',', $skus)]);

    expect(shouldUseBulkCorePath($exporter))->toBeTrue();
});

it('always uses the bulk path when the threshold is disabled with zero', function () {
    config(['shopify-bulk-operations.bulk_threshold' => 0]);

    $skus = Product::factory()->count(1)->create()->pluck('sku')->all();

    $exporter = makeExporterWithFilters(['productfilter' => implode(',', $skus)]);

    expect(shouldUseBulkCorePath($exporter))->toBeTrue();
});
