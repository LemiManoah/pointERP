<?php

declare(strict_types=1);

use App\Services\BoqWorkbookReader;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

it('accounts for the complete client workbook scope and partial pricing without committing client files', function (): void {
    $unpricedPath = getenv('BOQ_CLIENT_UNPRICED_PATH');
    $pricedPath = getenv('BOQ_CLIENT_PRICED_PATH');
    if (! $unpricedPath || ! $pricedPath) {
        $this->markTestSkipped('Set BOQ_CLIENT_UNPRICED_PATH and BOQ_CLIENT_PRICED_PATH to run the private workbook acceptance check.');
    }

    expect(is_file($unpricedPath))->toBeTrue()->and(is_file($pricedPath))->toBeTrue();
    $read = resolve(BoqWorkbookReader::class);
    $unpriced = $read->read($unpricedPath);
    $priced = $read->read($pricedPath);
    expect($unpriced)->toHaveCount(42)->and($priced)->toHaveCount(42)
        ->and(collect($unpriced)->where('hidden', true)->count())->toBe(3);
    $candidates = function (array $sheets): array {
        $result = [];
        foreach ($sheets as $sheet) {
            if ($sheet['hidden']) {
                continue;
            }

            if (! preg_match('/^(?:Bill No\.\s*(?:2\.|[34568]\b)|Series\s)/i', $sheet['name'])) {
                continue;
            }

            foreach ($sheet['rows'] as $number => $row) {
                $description = $row['C']['value'] ?? '';
                $unit = $row['D']['value'] ?? '';
                $quantity = $row['E']['value'] ?? '';
                if ($description === '') {
                    continue;
                }

                if ($unit === '') {
                    continue;
                }

                if (! is_numeric($quantity)) {
                    continue;
                }

                $result[$sheet['name'].':'.$number] = [
                    'scope' => [$row['B']['value'] ?? '', $description, $unit, $quantity],
                    'quantity' => $quantity, 'rate' => $row['F']['value'] ?? '', 'amount' => $row['G']['value'] ?? '',
                ];
            }
        }

        return $result;
    };
    $scope = $candidates($unpriced);
    $proposal = $candidates($priced);
    expect($scope)->toHaveCount(904)->and(array_keys($proposal))->toBe(array_keys($scope));
    $total = BigDecimal::zero();
    $pricedCount = 0;
    foreach ($scope as $key => $item) {
        expect($item['rate'])->toBe('')->and($proposal[$key]['scope'])->toBe($item['scope']);
        $price = $proposal[$key];
        if ($price['rate'] === '') {
            continue;
        }

        $pricedCount++;
        $amount = BigDecimal::of($price['quantity'])->multipliedBy($price['rate']);
        expect($amount->minus($price['amount'])->abs()->isLessThanOrEqualTo('0.01'))->toBeTrue();
        $total = $total->plus($amount);
    }

    expect($pricedCount)->toBe(223)->and((string) $total->toScale(2, RoundingMode::HalfUp))->toBe('4666437400.00');
});
