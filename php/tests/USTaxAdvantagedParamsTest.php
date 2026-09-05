<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/USTaxAdvantagedParams.php';

use USTaxAdvantagedParams\AccountType;
use USTaxAdvantagedParams\CalculationStatus;
use USTaxAdvantagedParams\ConversionType;
use USTaxAdvantagedParams\FilingStatus;
use USTaxAdvantagedParams\AccountBuilder;
use USTaxAdvantagedParams\ParameterException;
use USTaxAdvantagedParams\ScenarioBuilder;
use USTaxAdvantagedParams\USTaxAdvantagedParams as U;
use USTaxAdvantagedParams\UnsupportedTaxYearException;

/** @var array<string,Closure():void> $tests */
$tests = [];

function test(string $name, Closure $body): void
{
    global $tests;
    $tests[$name] = $body;
}

function failTest(string $message): never
{
    throw new RuntimeException($message);
}

function assertSameValue(mixed $expected, mixed $actual, string $message = ''): void
{
    if (is_float($expected) || is_float($actual)) {
        if (is_numeric($expected) && is_numeric($actual) && abs((float) $expected - (float) $actual) < 0.005) {
            return;
        }
    }
    if ($expected !== $actual) {
        failTest(($message !== '' ? $message . ': ' : '')
            . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertTrue(bool $condition, string $message = 'assertion failed'): void
{
    if (!$condition) {
        failTest($message);
    }
}

/** @param array<string,mixed> $result
 *  @return array<string,mixed>
 */
function accountResult(array $result, string $id): array
{
    foreach ($result['accounts'] as $account) {
        if ($account['accountId'] === $id) {
            return $account;
        }
    }
    failTest("Missing account result {$id}");
}

/** @param list<array<string,mixed>> $diagnostics */
function hasDiagnostic(array $diagnostics, string $code): bool
{
    foreach ($diagnostics as $diagnostic) {
        if (($diagnostic['code'] ?? null) === $code) {
            return true;
        }
    }
    return false;
}

/** @param list<array<string,mixed>> $persons
 *  @param list<array<string,mixed>> $accounts
 *  @param list<array<string,mixed>> $conversions
 *  @return array<string,mixed>
 */
function scenario(
    int $year,
    array $persons,
    array $accounts = [],
    string $filingStatus = 'S',
    array $conversions = [],
): array {
    return U::calculate([
        'taxYear' => $year,
        'filingStatus' => $filingStatus,
        'persons' => $persons,
        'accounts' => $accounts,
        'conversions' => $conversions,
    ]);
}

test('supports 1975 through 2026 without extrapolation', function (): void {
    assertSameValue(['minimum' => 1975, 'maximum' => 2026], U::supportedTaxYears());
    assertSameValue(1500, U::parametersForYear(1975)['ira']['baseContributionLimit']);
    assertSameValue(7500, U::parametersForYear(2026)['ira']['baseContributionLimit']);
    assertSameValue([
        'annualLimit' => 3000,
        'lifetimeLimit' => 15000,
        'serviceLimitPerYear' => 5000,
    ], U::parametersForYear(2026)['special403b15YearCatchUp']);
    try {
        U::parametersForYear(2027);
        failTest('Expected UnsupportedTaxYearException');
    } catch (UnsupportedTaxYearException) {
    }
});

test('normalizes common aliases', function (): void {
    assertSameValue(FilingStatus::SINGLE->value, U::normalizeFilingStatus('S'));
    assertSameValue(FilingStatus::MARRIED_FILING_JOINTLY->value, U::normalizeFilingStatus('MFJ'));
    assertSameValue(FilingStatus::HEAD_OF_HOUSEHOLD->value, U::normalizeFilingStatus('HOH'));
    assertSameValue(AccountType::TRADITIONAL_401K->value, U::normalizeAccountType('401(k)'));
    assertSameValue(AccountType::GOVERNMENTAL_457B->value, U::normalizeAccountType('457b'));
});

test('builder pattern calculates an ordinary 2026 401k', function (): void {
    $result = ScenarioBuilder::forTaxYear(2026)
        ->filingStatus('S')
        ->taxpayer('t', static fn ($person) => $person->bornIn(1980)->w2Compensation(200000))
        ->account('k', 't', '401k', static fn (AccountBuilder $plan) =>
            $plan->employer('e')->planCompensation(200000))
        ->calculate();
    $k = accountResult($result, 'k');
    assertSameValue(72000, $k['statutoryMaximumAnnualContribution']);
    assertSameValue(24500, $k['maximumAnnualContributionBasedOnInputs']);
    assertSameValue(47500, $k['planTermDependentCapacity']);
    assertSameValue(CalculationStatus::DETERMINATE_WITH_ASSUMPTIONS->value, $k['status']);
});

test('2026 age 60 to 63 high wage catch-up is Roth', function (): void {
    $result = scenario(2026, [[
        'id' => 't', 'birthYear' => 1965,
        'compensation' => ['w2Compensation' => 250000],
        'priorYearFicaWagesByEmployer' => ['e' => 150001],
    ]], [[
        'id' => 'k', 'ownerId' => 't', 'type' => '401k', 'employerId' => 'e',
        'planRules' => [
            'planCompensation' => 250000,
            'permitsRothContributions' => true,
            'permitsRothCatchUp' => true,
        ],
    ]]);
    $k = accountResult($result, 'k');
    assertSameValue(24500, $k['contributionComponents']['employeePreTaxDeferral']);
    assertSameValue(11250, $k['contributionComponents']['employeeRothCatchUp']);
    assertSameValue(0, $k['contributionComponents']['employeePreTaxCatchUp']);
    assertSameValue(35750, $k['maximumAnnualContributionBasedOnInputs']);
});

test('reports the quantified amount of an existing contribution above an account ceiling', function (): void {
    $result = scenario(2026, [[
        'id' => 't',
        'birthYear' => 1980,
        'compensation' => ['iraCompensation' => 50000],
        'coveredByEmployerRetirementPlan' => false,
        'magi' => ['traditionalIraDeduction' => 50000, 'rothIra' => 50000],
    ]], [[
        'id' => 'ira',
        'ownerId' => 't',
        'type' => 'traditional_ira',
        'existingContributions' => ['deductibleIra' => 20000],
    ]]);
    // IRC 219(b)(5)(A)'s 2026 $7,500 limit leaves $12,500 excessive.
    assertSameValue(12500, accountResult($result, 'ira')['excessContribution']);
});

test('high wage catch-up is unavailable without plan Roth catch-up', function (): void {
    $result = scenario(2026, [[
        'id' => 't', 'birthYear' => 1965,
        'compensation' => ['w2Compensation' => 250000],
        'priorYearFicaWagesByEmployer' => ['e' => 200000],
    ]], [[
        'id' => 'k', 'ownerId' => 't', 'type' => '401k', 'employerId' => 'e',
        'planRules' => ['planCompensation' => 250000],
    ]]);
    $k = accountResult($result, 'k');
    assertSameValue(24500, $k['contributionComponents']['employeePreTaxDeferral']);
    assertSameValue(0, $k['contributionComponents']['employeePreTaxCatchUp']);
    assertSameValue(0, $k['contributionComponents']['employeeRothCatchUp']);
    assertTrue(hasDiagnostic($k['diagnostics'], 'HIGH_WAGE_CATCH_UP_REQUIRES_ROTH_BUT_PLAN_DOES_NOT_OFFER_IT'));
});

test('Roth IRA MFJ phase-out', function (): void {
    $result = scenario(2026, [
        ['id' => 't', 'role' => 'taxpayer', 'birthYear' => 1980, 'compensation' => ['iraCompensation' => 100000], 'magi' => ['rothIra' => 247000], 'coveredByEmployerRetirementPlan' => false],
        ['id' => 's', 'role' => 'spouse', 'birthYear' => 1980, 'compensation' => ['iraCompensation' => 100000], 'magi' => ['rothIra' => 247000], 'coveredByEmployerRetirementPlan' => false],
    ], [['id' => 'roth', 'ownerId' => 't', 'type' => 'roth_ira']], 'MFJ');
    assertSameValue(3750, accountResult($result, 'roth')['contributionComponents']['rothIra']);
});

test('traditional IRA deduction phases out without reducing total contribution', function (): void {
    $result = scenario(2026, [
        ['id' => 't', 'role' => 'taxpayer', 'birthYear' => 1980, 'compensation' => ['iraCompensation' => 100000], 'magi' => ['traditionalIraDeduction' => 139000], 'coveredByEmployerRetirementPlan' => true],
        ['id' => 's', 'role' => 'spouse', 'birthYear' => 1980, 'compensation' => ['iraCompensation' => 100000], 'coveredByEmployerRetirementPlan' => false],
    ], [['id' => 'ira', 'ownerId' => 't', 'type' => 'traditional_ira']], 'MFJ');
    $ira = accountResult($result, 'ira');
    assertSameValue(3750, $ira['contributionComponents']['deductibleIra']);
    assertSameValue(3750, $ira['contributionComponents']['nondeductibleIra']);
    assertSameValue(7500, $ira['maximumAnnualContributionBasedOnInputs']);
});

test('traditional and Roth IRA share owner pool', function (): void {
    $result = scenario(2026, [[
        'id' => 't', 'birthYear' => 1980,
        'compensation' => ['iraCompensation' => 100000],
        'magi' => ['rothIra' => 158000, 'traditionalIraDeduction' => 50000],
        'coveredByEmployerRetirementPlan' => false,
    ]], [
        ['id' => 'roth', 'ownerId' => 't', 'type' => 'roth_ira', 'priority' => 1],
        ['id' => 'traditional', 'ownerId' => 't', 'type' => 'traditional_ira', 'priority' => 2],
    ]);
    assertSameValue(5000, accountResult($result, 'roth')['contributionComponents']['rothIra']);
    assertSameValue(2500, accountResult($result, 'traditional')['contributionComponents']['deductibleIra']);
});

test('401k and 457b limits are separate', function (): void {
    $result = scenario(2026, [['id' => 't', 'birthYear' => 1980, 'compensation' => ['w2Compensation' => 200000]]], [
        ['id' => 'k', 'ownerId' => 't', 'type' => '401k', 'employerId' => 'private', 'planRules' => ['planCompensation' => 200000]],
        ['id' => 'g457', 'ownerId' => 't', 'type' => '457b', 'employerId' => 'government', 'planRules' => ['includibleCompensation457' => 200000]],
    ]);
    assertSameValue(24500, accountResult($result, 'k')['contributionComponents']['employeePreTaxDeferral']);
    assertSameValue(24500, accountResult($result, 'g457')['contributionComponents']['employeePreTaxDeferral']);
});

test('two 401k plans share 402g and retain separate 415c groups', function (): void {
    $result = scenario(2026, [['id' => 't', 'birthYear' => 1980, 'compensation' => ['w2Compensation' => 300000]]], [
        ['id' => 'first', 'ownerId' => 't', 'type' => '401k', 'employerId' => 'a', 'priority' => 1, 'planRules' => ['planCompensation' => 150000]],
        ['id' => 'second', 'ownerId' => 't', 'type' => '401k', 'employerId' => 'b', 'priority' => 2, 'planRules' => ['planCompensation' => 150000]],
    ]);
    assertSameValue(24500, accountResult($result, 'first')['contributionComponents']['employeePreTaxDeferral']);
    assertSameValue(0, accountResult($result, 'second')['contributionComponents']['employeePreTaxDeferral']);
});

test('mega backdoor fills remaining 415c space', function (): void {
    $result = scenario(2026, [['id' => 't', 'birthYear' => 1980, 'compensation' => ['w2Compensation' => 200000]]], [[
        'id' => 'k', 'ownerId' => 't', 'type' => '401k', 'employerId' => 'e',
        'planRules' => ['planCompensation' => 200000, 'expectedEmployerContribution' => 10000, 'permitsAfterTaxEmployeeContributions' => true],
    ]]);
    $k = accountResult($result, 'k');
    assertSameValue(24500, $k['contributionComponents']['employeePreTaxDeferral']);
    assertSameValue(10000, $k['contributionComponents']['employerPreTax']);
    assertSameValue(37500, $k['contributionComponents']['employeeAfterTax']);
    assertSameValue(72000, $k['maximumAnnualContributionBasedOnInputs']);
});

test('self employed solo 401k uses 20 percent equivalent rate', function (): void {
    $result = scenario(2026, [['id' => 't', 'birthYear' => 1980, 'compensation' => ['selfEmploymentNetEarnings' => 200000]]], [[
        'id' => 'solo', 'ownerId' => 't', 'type' => 'solo_401k',
        'planRules' => ['isSelfEmployedOwner' => true, 'netEarningsFromSelfEmploymentAfterHalfSETax' => 200000, 'planCompensation' => 200000, 'permitsAfterTaxEmployeeContributions' => true],
    ]]);
    $solo = accountResult($result, 'solo');
    assertSameValue(24500, $solo['contributionComponents']['employeePreTaxDeferral']);
    assertSameValue(40000, $solo['contributionComponents']['employerPreTax']);
    assertSameValue(7500, $solo['contributionComponents']['employeeAfterTax']);
});

test('self employed SEP uses 20 percent equivalent rate', function (): void {
    $result = scenario(2026, [['id' => 't', 'birthYear' => 1980, 'compensation' => ['selfEmploymentNetEarnings' => 200000]]], [[
        'id' => 'sep', 'ownerId' => 't', 'type' => 'sep_ira',
        'planRules' => ['isSelfEmployedOwner' => true, 'netEarningsFromSelfEmploymentAfterHalfSETax' => 200000, 'planCompensation' => 200000],
    ]]);
    assertSameValue(40000, accountResult($result, 'sep')['contributionComponents']['employerPreTax']);
});

test('403b 15-year catch-up', function (): void {
    $result = scenario(2026, [['id' => 't', 'birthYear' => 1980, 'compensation' => ['w2Compensation' => 200000]]], [[
        'id' => 'b', 'ownerId' => 't', 'type' => '403b', 'employerId' => 'school',
        'planRules' => ['planCompensation' => 200000, 'special403bCatchUp' => ['eligible' => true, 'yearsOfService' => 15, 'priorElectiveDeferrals' => 20000, 'priorSpecialCatchUpUsed' => 0]],
    ]]);
    $b = accountResult($result, 'b');
    assertSameValue(24500, $b['contributionComponents']['employeePreTaxDeferral']);
    assertSameValue(3000, $b['contributionComponents']['special403bCatchUp']);
});

test('457b special catch-up selected when larger', function (): void {
    $result = scenario(2026, [[
        'id' => 't', 'birthYear' => 1970,
        'compensation' => ['w2Compensation' => 150000],
        'priorYearFicaWagesByEmployer' => ['gov' => 100000],
    ]], [[
        'id' => 'g457', 'ownerId' => 't', 'type' => 'governmental_457b', 'employerId' => 'gov',
        'planRules' => ['includibleCompensation457' => 150000, 'section457SpecialCatchUp' => ['eligible' => true, 'unusedDeferralsFromPriorYears' => 30000]],
    ]]);
    $g = accountResult($result, 'g457');
    assertSameValue(24500, $g['contributionComponents']['employeePreTaxDeferral']);
    assertSameValue(24500, $g['contributionComponents']['special457CatchUp']);
    assertSameValue(49000, $g['maximumAnnualContributionBasedOnInputs']);
});

test('1994 historical limits', function (): void {
    $result = scenario(1994, [['id' => 't', 'birthYear' => 1960, 'compensation' => ['w2Compensation' => 100000]]], [[
        'id' => 'k', 'ownerId' => 't', 'type' => '401k', 'employerId' => 'e', 'planRules' => ['planCompensation' => 100000],
    ]]);
    $k = accountResult($result, 'k');
    assertSameValue(9240, $k['contributionComponents']['employeePreTaxDeferral']);
    assertSameValue(25000, $k['statutoryMaximumAnnualContribution']);
});

test('1985 401k is indeterminate', function (): void {
    $result = scenario(1985, [['id' => 't', 'birthYear' => 1950, 'compensation' => ['w2Compensation' => 100000]]], [[
        'id' => 'k', 'ownerId' => 't', 'type' => '401k', 'employerId' => 'e', 'planRules' => ['planCompensation' => 100000],
    ]]);
    $k = accountResult($result, 'k');
    assertSameValue(CalculationStatus::INDETERMINATE->value, $k['status']);
    assertSameValue(null, $k['statutoryMaximumAnnualContribution']);
});

test('1981 active participant is ineligible for IRA', function (): void {
    $result = scenario(1981, [['id' => 't', 'birthYear' => 1950, 'compensation' => ['iraCompensation' => 20000], 'coveredByEmployerRetirementPlan' => true]], [[
        'id' => 'ira', 'ownerId' => 't', 'type' => 'traditional_ira',
    ]]);
    $ira = accountResult($result, 'ira');
    assertSameValue(0, $ira['maximumAnnualContributionBasedOnInputs']);
    assertSameValue(CalculationStatus::INELIGIBLE->value, $ira['status']);
});

test('1982 one earner spousal IRA allows 2000 to the spousal account when the worker contributes nothing', function (): void {
    // IRC 219(c)(2) (ERTA 1981): min(2250, compensation) minus the working
    // spouse's own-IRA deduction, never more than 2000 to the spousal account.
    $result = scenario(1982, [
        ['id' => 't', 'role' => 'taxpayer', 'birthYear' => 1950, 'compensation' => ['iraCompensation' => 20000], 'coveredByEmployerRetirementPlan' => true],
        ['id' => 's', 'role' => 'spouse', 'birthYear' => 1950, 'compensation' => ['iraCompensation' => 0], 'coveredByEmployerRetirementPlan' => false],
    ], [['id' => 'spouse-ira', 'ownerId' => 's', 'type' => 'traditional_ira']], 'MFJ');
    assertSameValue(2000, accountResult($result, 'spouse-ira')['contributionComponents']['deductibleIra']);
});

test('1982 one earner spousal IRA is limited to the 2250 household residue after the worker uses 2000', function (): void {
    $result = scenario(1982, [
        ['id' => 't', 'role' => 'taxpayer', 'birthYear' => 1950, 'compensation' => ['iraCompensation' => 20000], 'coveredByEmployerRetirementPlan' => true],
        ['id' => 's', 'role' => 'spouse', 'birthYear' => 1950, 'compensation' => ['iraCompensation' => 0], 'coveredByEmployerRetirementPlan' => false],
    ], [
        ['id' => 'own-ira', 'ownerId' => 't', 'type' => 'traditional_ira', 'existingContributions' => ['deductibleIra' => 2000]],
        ['id' => 'spouse-ira', 'ownerId' => 's', 'type' => 'traditional_ira'],
    ], 'MFJ');
    assertSameValue(250, accountResult($result, 'spouse-ira')['maximumAnnualContributionBasedOnInputs']);
});

test('2019 traditional IRA age 70.5 restriction', function (): void {
    $result = scenario(2019, [['id' => 't', 'birthDate' => '1948-01-01', 'compensation' => ['iraCompensation' => 100000], 'coveredByEmployerRetirementPlan' => false]], [[
        'id' => 'ira', 'ownerId' => 't', 'type' => 'traditional_ira',
    ]]);
    $ira = accountResult($result, 'ira');
    assertSameValue(CalculationStatus::INELIGIBLE->value, $ira['status']);
    assertSameValue(0, $ira['maximumAnnualContributionBasedOnInputs']);
});

test('IRA conversion applies Form 8606 pro rata basis', function (): void {
    $result = scenario(2026, [[
        'id' => 't', 'birthYear' => 1980,
        'traditionalSepSimpleIraBasis' => 20000,
        'yearEndTraditionalSepSimpleIraValue' => 80000,
        'otherTraditionalSepSimpleIraDistributions' => 0,
    ]], [], 'S', [[
        'id' => 'c', 'ownerId' => 't', 'type' => 'ira_to_roth_ira', 'amount' => 20000,
    ]]);
    $conversion = $result['conversions'][0];
    assertSameValue(4000, $conversion['nontaxableBasisAmount']);
    assertSameValue(16000, $conversion['taxableAmount']);
    assertSameValue(false, $conversion['consumesAnnualContributionLimit']);
});

test('in plan Roth rollover taxes pre-tax portion only', function (): void {
    $result = scenario(2026, [['id' => 't', 'birthYear' => 1980, 'compensation' => ['w2Compensation' => 100000]]], [[
        'id' => 'k', 'ownerId' => 't', 'type' => '401k', 'employerId' => 'e',
        'planRules' => ['planCompensation' => 100000, 'permitsInPlanRothRollover' => true],
    ]], 'S', [[
        'id' => 'c', 'ownerId' => 't', 'type' => 'in_plan_roth_rollover', 'amount' => 50000,
        'sourceAccountId' => 'k', 'afterTaxBasisInConvertedAmount' => 10000,
    ]]);
    assertSameValue(40000, $result['conversions'][0]['taxableAmount']);
    assertSameValue(10000, $result['conversions'][0]['nontaxableBasisAmount']);
});

test('cash balance contribution remains indeterminate', function (): void {
    $result = scenario(2026, [['id' => 't', 'birthYear' => 1980, 'compensation' => ['w2Compensation' => 200000]]], [[
        'id' => 'cb', 'ownerId' => 't', 'type' => 'cash_balance_plan', 'employerId' => 'e',
    ]]);
    $cb = accountResult($result, 'cb');
    assertSameValue(null, $cb['statutoryMaximumAnnualContribution']);
    assertSameValue(CalculationStatus::INDETERMINATE->value, $cb['status']);
});

test('2026 enhanced SIMPLE and age 60 to 63 catch-up', function (): void {
    $result = scenario(2026, [[
        'id' => 't', 'birthYear' => 1965,
        'compensation' => ['w2Compensation' => 100000],
        'priorYearFicaWagesByEmployer' => ['e' => 100000],
    ]], [[
        'id' => 'simple', 'ownerId' => 't', 'type' => 'simple_ira', 'employerId' => 'e',
        'planRules' => ['planCompensation' => 100000, 'simpleEnhancedLimitEligible' => true, 'simpleEmployerContributionMethod' => 'match_3_percent'],
    ]]);
    $simple = accountResult($result, 'simple');
    assertSameValue(18100, $simple['contributionComponents']['employeePreTaxDeferral']);
    assertSameValue(5250, $simple['contributionComponents']['employeePreTaxCatchUp']);
    assertSameValue(3000, $simple['contributionComponents']['employerPreTax']);
    assertSameValue(26350, $simple['maximumAnnualContributionBasedOnInputs']);
});

test('self employed plan deduction excludes IRA deduction classification', function (): void {
    $result = scenario(2026, [[
        'id' => 't',
        'birthYear' => 1980,
        'compensation' => [
            'selfEmploymentNetEarnings' => 200000,
            'iraCompensation' => 200000,
        ],
        'magi' => ['traditionalIraDeduction' => 50000],
        'coveredByEmployerRetirementPlan' => false,
    ]], [
        [
            'id' => 'solo', 'ownerId' => 't', 'type' => 'solo_401k',
            'planRules' => [
                'isSelfEmployedOwner' => true,
                'netEarningsFromSelfEmploymentAfterHalfSETax' => 200000,
                'planCompensation' => 200000,
            ],
        ],
        ['id' => 'ira', 'ownerId' => 't', 'type' => 'traditional_ira'],
    ]);
    $solo = accountResult($result, 'solo');
    $ira = accountResult($result, 'ira');
    assertSameValue(64500, $solo['federalTaxEffects']['selfEmployedRetirementDeduction']);
    assertSameValue(64500, $solo['federalTaxEffects']['federalAgiReduction']);
    assertSameValue(0, $ira['federalTaxEffects']['selfEmployedRetirementDeduction']);
    assertSameValue(7500, $ira['federalTaxEffects']['federalAgiReduction']);
});

test('pre 2010 MFS taxpayer living apart may convert under MAGI ceiling', function (): void {
    $result = scenario(2009, [[
        'id' => 't',
        'birthYear' => 1980,
        'livedWithSpouseDuringYear' => false,
        'magi' => ['rothConversion' => 90000],
        'traditionalSepSimpleIraBasis' => 10000,
        'yearEndTraditionalSepSimpleIraValue' => 10000,
    ]], [], 'MFS', [[
        'id' => 'c', 'ownerId' => 't', 'type' => 'ira_to_roth_ira', 'amount' => 10000,
    ]]);
    assertSameValue(CalculationStatus::DETERMINATE->value, $result['conversions'][0]['status']);
    assertSameValue(5000, $result['conversions'][0]['nontaxableBasisAmount']);
    assertSameValue(5000, $result['conversions'][0]['taxableAmount']);
});

test('additional SIMPLE nonelective contribution is capped at 10 percent compensation', function (): void {
    $result = scenario(2026, [[
        'id' => 't', 'birthYear' => 1980, 'compensation' => ['w2Compensation' => 20000],
    ]], [[
        'id' => 'simple', 'ownerId' => 't', 'type' => 'simple_ira', 'employerId' => 'e',
        'planRules' => [
            'planCompensation' => 20000,
            'simpleEmployerContributionMethod' => 'nonelective_2_percent',
            'simpleAdditionalNonelectiveContribution' => 5300,
        ],
    ]]);
    $simple = accountResult($result, 'simple');
    assertSameValue(2400, $simple['contributionComponents']['employerPreTax']);
    assertSameValue(19600, $simple['statutoryMaximumAnnualContribution']);
    assertTrue(hasDiagnostic($simple['diagnostics'], 'SIMPLE_ADDITIONAL_NONELECTIVE_CONTRIBUTION_CAPPED'));
});

test('SIMPLE IRA catch-up remains pre-tax under 408p exclusion', function (): void {
    $result = scenario(2026, [[
        'id' => 't', 'birthYear' => 1965, 'compensation' => ['w2Compensation' => 100000],
    ]], [[
        'id' => 'simple', 'ownerId' => 't', 'type' => 'simple_ira', 'employerId' => 'e',
        'planRules' => [
            'planCompensation' => 100000,
            'simpleEmployerContributionMethod' => 'match_3_percent',
        ],
    ]]);
    $simple = accountResult($result, 'simple');
    assertSameValue(5250, $simple['contributionComponents']['employeePreTaxCatchUp']);
    assertSameValue(0, $simple['contributionComponents']['employeeRothCatchUp']);
    assertTrue(!hasDiagnostic($simple['diagnostics'], 'PRIOR_YEAR_FICA_WAGES_REQUIRED_FOR_ROTH_CATCH_UP_CLASSIFICATION'));
});

test('multiple 403b accounts share one 15-year catch-up pool', function (): void {
    $special = [
        'eligible' => true,
        'yearsOfService' => 15,
        'priorElectiveDeferrals' => 20000,
        'priorSpecialCatchUpUsed' => 0,
    ];
    $result = scenario(2026, [[
        'id' => 't', 'birthYear' => 1980, 'compensation' => ['w2Compensation' => 300000],
    ]], [
        [
            'id' => 'first', 'ownerId' => 't', 'type' => 'traditional_403b',
            'employerId' => 'school-a', 'priority' => 1,
            'planRules' => ['planCompensation' => 150000, 'special403bCatchUp' => $special],
        ],
        [
            'id' => 'second', 'ownerId' => 't', 'type' => 'traditional_403b',
            'employerId' => 'school-b', 'priority' => 2,
            'planRules' => ['planCompensation' => 150000, 'special403bCatchUp' => $special],
        ],
    ]);
    assertSameValue(3000, accountResult($result, 'first')['contributionComponents']['special403bCatchUp']);
    assertSameValue(0, accountResult($result, 'second')['contributionComponents']['special403bCatchUp']);
});

test('Roth employer contributions are rejected before 2023', function (): void {
    $result = scenario(2022, [[
        'id' => 't', 'birthYear' => 1980, 'compensation' => ['w2Compensation' => 100000],
    ]], [[
        'id' => 'k', 'ownerId' => 't', 'type' => 'traditional_401k', 'employerId' => 'e',
        'planRules' => [
            'planCompensation' => 100000,
            'expectedEmployerContribution' => 10000,
            'employerContributionTaxTreatment' => 'roth',
        ],
    ]]);
    $k = accountResult($result, 'k');
    assertSameValue(0, $k['contributionComponents']['employerRoth']);
    assertSameValue(CalculationStatus::INDETERMINATE->value, $k['status']);
    assertTrue(hasDiagnostic($k['diagnostics'], 'ROTH_EMPLOYER_CONTRIBUTIONS_NOT_AVAILABLE_FOR_YEAR'));
});

test('multiple IRA conversions do not over-allocate basis by pennies', function (): void {
    $result = scenario(2026, [[
        'id' => 't',
        'birthYear' => 1980,
        'traditionalSepSimpleIraBasis' => 1,
        'yearEndTraditionalSepSimpleIraValue' => 147,
    ]], [], 'S', [
        ['id' => 'c1', 'ownerId' => 't', 'type' => 'ira_to_roth_ira', 'amount' => 1],
        ['id' => 'c2', 'ownerId' => 't', 'type' => 'ira_to_roth_ira', 'amount' => 1],
        ['id' => 'c3', 'ownerId' => 't', 'type' => 'ira_to_roth_ira', 'amount' => 1],
    ]);
    assertSameValue(0.01, $result['conversions'][0]['nontaxableBasisAmount']);
    assertSameValue(0.01, $result['conversions'][1]['nontaxableBasisAmount']);
    assertSameValue(0, $result['conversions'][2]['nontaxableBasisAmount']);
    assertSameValue(0.02, array_sum(array_column($result['conversions'], 'nontaxableBasisAmount')));
});

test('duplicate taxpayer or spouse roles are rejected', function (): void {
    try {
        scenario(2026, [
            ['id' => 't1', 'role' => 'taxpayer', 'birthYear' => 1980],
            ['id' => 't2', 'role' => 'taxpayer', 'birthYear' => 1981],
        ], [], 'MFJ');
        failTest('Expected ParameterException');
    } catch (ParameterException $error) {
        assertSameValue('DUPLICATE_PERSON_ROLE', $error->errorCode);
    }
});

test('ambiguous M alias emits diagnostic', function (): void {
    $result = scenario(2026, [
        ['id' => 't', 'role' => 'taxpayer', 'birthYear' => 1980],
        ['id' => 's', 'role' => 'spouse', 'birthYear' => 1980],
    ], [], 'M');
    assertSameValue(FilingStatus::MARRIED_FILING_JOINTLY->value, $result['filingStatus']);
    assertTrue(hasDiagnostic($result['diagnostics'], 'AMBIGUOUS_M_ALIAS_ASSUMED_MFJ'));
});


test('1997 common-law SEP applies the 401(a)(17) compensation ceiling before the 15% rate', function (): void {
    $result = scenario(1997, [[
        'id' => 't', 'birthYear' => 1960, 'compensation' => ['w2Compensation' => 500000],
    ]], [[
        'id' => 'sep', 'ownerId' => 't', 'type' => 'sep_ira', 'employerId' => 'e',
        'planRules' => ['planCompensation' => 500000],
    ]]);
    $sep = accountResult($result, 'sep');
    assertSameValue(24000, $sep['statutoryMaximumAnnualContribution']);
    assertSameValue(24000, $sep['contributionComponents']['employerPreTax']);
});

test('1997 employer nonelective formula applies the 401(a)(17) compensation ceiling', function (): void {
    $result = scenario(1997, [[
        'id' => 't', 'birthYear' => 1960, 'compensation' => ['w2Compensation' => 500000],
    ]], [[
        'id' => 'profit-sharing', 'ownerId' => 't', 'type' => 'profit_sharing_plan', 'employerId' => 'e',
        'planRules' => ['planCompensation' => 500000, 'employerNonelectiveRate' => 0.15],
    ]]);
    $plan = accountResult($result, 'profit-sharing');
    assertSameValue(30000, $plan['statutoryMaximumAnnualContribution']);
    assertSameValue(24000, $plan['contributionComponents']['employerPreTax']);
});

test('1997 employer match uses recognized compensation without capping employee elective deferrals', function (): void {
    $result = scenario(1997, [[
        'id' => 't', 'birthYear' => 1960, 'compensation' => ['w2Compensation' => 500000],
    ]], [[
        'id' => 'k', 'ownerId' => 't', 'type' => 'traditional_401k', 'employerId' => 'e',
        'planRules' => [
            'planCompensation' => 500000,
            'employerMatchRate' => 1,
            'employerMatchCompensationFraction' => 0.01,
        ],
    ]]);
    $k = accountResult($result, 'k');
    assertSameValue(9500, $k['contributionComponents']['employeePreTaxDeferral']);
    assertSameValue(1600, $k['contributionComponents']['employerPreTax']);
});

test('1997 self-employed SEP applies reduced-rate and recognized-compensation worksheet ceilings', function (): void {
    $result = scenario(1997, [[
        'id' => 't', 'birthYear' => 1960,
        'compensation' => ['selfEmploymentNetEarnings' => 500000],
    ]], [[
        'id' => 'sep', 'ownerId' => 't', 'type' => 'sep_ira', 'employerId' => 'sole-proprietor',
        'planRules' => [
            'planCompensation' => 500000,
            'isSelfEmployedOwner' => true,
            'netEarningsFromSelfEmploymentAfterHalfSETax' => 500000,
        ],
    ]]);
    $sep = accountResult($result, 'sep');
    assertSameValue(24000, $sep['statutoryMaximumAnnualContribution']);
    assertSameValue(24000, $sep['contributionComponents']['employerPreTax']);
});


test('1997 self-employed qualified-plan formula applies reduced-rate and recognized-compensation ceilings', function (): void {
    $result = scenario(1997, [[
        'id' => 't', 'birthYear' => 1960,
        'compensation' => ['selfEmploymentNetEarnings' => 500000],
    ]], [[
        'id' => 'profit-sharing', 'ownerId' => 't', 'type' => 'profit_sharing_plan',
        'employerId' => 'sole-proprietor',
        'planRules' => [
            'planCompensation' => 500000,
            'isSelfEmployedOwner' => true,
            'netEarningsFromSelfEmploymentAfterHalfSETax' => 500000,
        ],
    ]]);
    $plan = accountResult($result, 'profit-sharing');
    assertSameValue(30000, $plan['statutoryMaximumAnnualContribution']);
    assertSameValue(24000, $plan['contributionComponents']['employerPreTax']);
});

test('exposes the IRC 125 and IRC 129 parameter table without extrapolating it', function (): void {
    // The table starts where IRC 129 does, not where its dollar ceiling does: a
    // year can exist with no statutory ceiling, and that is a state rather than
    // an absence. Absence now means only "the program did not exist".
    assertSameValue(['minimum' => 1982, 'maximum' => 2026], U::supportedFsaTaxYears());
    assertSameValue('statutory_dollar_limit', U::fsaParametersForYear(2026)['healthFsa']['state']);
    assertSameValue(3400, U::fsaParametersForYear(2026)['healthFsa']['salaryReductionLimit']);
    assertSameValue(7500, U::fsaParametersForYear(2026)['dependentCare']['exclusionLimit']);
    assertSameValue(
        'available_without_statutory_dollar_limit',
        U::fsaParametersForYear(2012)['healthFsa']['state'],
    );
    assertSameValue(null, U::fsaParametersForYear(2012)['healthFsa']['salaryReductionLimit']);
    assertSameValue(
        'available_without_statutory_dollar_limit',
        U::fsaParametersForYear(1986)['dependentCare']['state'],
    );
    assertSameValue(null, U::fsaParametersForYear(1986)['dependentCare']['exclusionLimit']);
    assertSameValue(null, U::fsaParametersForYear(1981));
    $ids = array_column(U::fsaSourceMetadata(), 'id');
    assertTrue(in_array('pl-119-21', $ids, true), 'Pub. L. 119-21 must be listed as an FSA source');
});

test('rejects a bare FSA account type but accepts each unambiguous spelling', function (): void {
    assertSameValue(AccountType::HEALTH_FSA->value, U::normalizeAccountType('health fsa'));
    assertSameValue(AccountType::HEALTH_FSA->value, U::normalizeAccountType('Medical-FSA'));
    try {
        U::normalizeAccountType('FSA');
        failTest('Expected ParameterException');
    } catch (ParameterException $error) {
        assertSameValue('INVALID_ACCOUNT_TYPE', $error->errorCode);
        assertTrue(str_contains($error->getMessage(), 'health_fsa'), 'message must name health_fsa');
        assertTrue(str_contains($error->getMessage(), 'dependent_care_fsa'), 'message must name dependent_care_fsa');
    }
});

test('validates health FSA plan facts before calculating anything', function (): void {
    $cases = [
        'INVALID_HEALTH_FSA_PURPOSE' => ['purpose' => 'general'],
        'INVALID_BOOLEAN' => ['offersCarryover' => 'yes'],
        'INVALID_MONEY' => ['priorYearUnusedAmount' => -1],
    ];
    foreach ($cases as $expectedCode => $rules) {
        try {
            scenario(2026, [['id' => 't', 'birthYear' => 1980]], [[
                'id' => 'f', 'ownerId' => 't', 'type' => 'health_fsa',
                'planRules' => ['healthFsa' => $rules],
            ]]);
            failTest("Expected ParameterException {$expectedCode}");
        } catch (ParameterException $error) {
            assertSameValue($expectedCode, $error->errorCode);
        }
    }
    try {
        scenario(2026, [['id' => 't', 'birthYear' => 1980]], [[
            'id' => 'f', 'ownerId' => 't', 'type' => 'health_fsa',
            'planRules' => ['healthFsa' => 3400],
        ]]);
        failTest('Expected ParameterException INVALID_INPUT_OBJECT');
    } catch (ParameterException $error) {
        assertSameValue('INVALID_INPUT_OBJECT', $error->errorCode);
    }
});

test('the health FSA builder reaches every IRC 125(i) plan fact', function (): void {
    $result = ScenarioBuilder::forTaxYear(2026)
        ->taxpayer('t', static fn ($person) => $person->bornIn(1985)->w2Compensation(150000))
        ->addAccount(
            (new AccountBuilder('f', 't', AccountType::HEALTH_FSA))
                ->employer('e')
                ->healthFsaPurpose('post_deductible')
                ->healthFsaCarryover(true, 700)
                ->healthFsaEmployerFlexCredit(250, false)
                ->healthFsaCalendarPlanYear(),
        )
        ->calculate();
    $fsa = accountResult($result, 'f');
    assertSameValue(CalculationStatus::DETERMINATE->value, $fsa['status']);
    assertSameValue(3400.0, $fsa['statutoryMaximumAnnualContribution']);
    assertSameValue('post_deductible', $fsa['healthFsa']['purpose']);
    assertSameValue(false, $fsa['healthFsa']['disqualifiesHsaEligibility']);
    assertSameValue(660.0, $fsa['healthFsa']['carryoverFromPriorYear']);
    assertSameValue(40.0, $fsa['healthFsa']['forfeitedAmount']);
    assertSameValue(0.0, $fsa['healthFsa']['employerFlexCreditCountedAgainstLimit']);
});

test('validates IRC 129 earned income facts before calculating anything', function (): void {
    // The IRC 129(b)(1) facts describe the people on the return, not the
    // program, so they are validated on the person rather than on plan rules.
    $personCases = [
        'INVALID_MONEY' => ['dependentCareEarnedIncome' => -1],
        'INVALID_BOOLEAN' => ['isStudentOrIncapableOfSelfCare' => 'yes'],
    ];
    foreach ($personCases as $expectedCode => $extra) {
        try {
            scenario(2026, [array_merge(['id' => 't', 'birthYear' => 1985], $extra)], [[
                'id' => 'd', 'ownerId' => 't', 'type' => 'dependent_care_fsa',
            ]]);
            failTest("Expected ParameterException {$expectedCode}");
        } catch (ParameterException $error) {
            assertSameValue($expectedCode, $error->errorCode);
        }
    }
    try {
        scenario(2026, [['id' => 't', 'birthYear' => 1985]], [[
            'id' => 'd', 'ownerId' => 't', 'type' => 'dependent_care_fsa',
            'planRules' => ['dependentCareFsa' => ['planDocumentLimit' => -1]],
        ]]);
        failTest('Expected ParameterException INVALID_MONEY');
    } catch (ParameterException $error) {
        assertSameValue('INVALID_MONEY', $error->errorCode);
    }
    assertSameValue(AccountType::DEPENDENT_CARE_FSA->value, U::normalizeAccountType('DCAP'));
    assertSameValue(AccountType::DEPENDENT_CARE_FSA->value, U::normalizeAccountType('dependent care assistance'));
});

test('the dependent care builder reaches the IRC 129(b) earned income facts', function (): void {
    $result = ScenarioBuilder::forTaxYear(2026)
        ->filingStatus(FilingStatus::MARRIED_FILING_JOINTLY)
        ->taxpayer('t', static fn ($person) => $person->bornIn(1985)->dependentCareEarnedIncome(90000))
        ->spouse('s', static fn ($person) => $person->bornIn(1986)->dependentCareEarnedIncome(4000))
        ->addAccount(
            (new AccountBuilder('d', 't', AccountType::DEPENDENT_CARE_FSA))
                ->employer('e'),
        )
        ->calculate();
    $dc = accountResult($result, 'd');
    assertSameValue(CalculationStatus::DETERMINATE->value, $dc['status']);
    // The statutory maximum is the IRC 129(a)(2)(A) amount; what the supplied
    // earned income allows within it is the input-based maximum.
    assertSameValue(7500.0, $dc['statutoryMaximumAnnualContribution']);
    assertSameValue(4000.0, $dc['maximumAnnualContributionBasedOnInputs']);
    assertSameValue(7500.0, $dc['dependentCareFsa']['statutoryExclusion']);
    assertSameValue(4000.0, $dc['dependentCareFsa']['earnedIncomeLimitation']);
    assertSameValue(0.0, $dc['federalTaxEffects']['federalAgiReduction']);
    assertSameValue(4000.0, $dc['federalTaxEffects']['ficaWageReduction']);
});


test('the IRC 223(b)(5)(B)(ii) division diagnostic does not claim a shared limit it is not reporting', function (): void {
    // Both spouses hold family coverage all year in 2005, when IRC 223(b)(2) still
    // capped each month by the plan's annual deductible, and the spouse's family
    // plan states 400 -- below the 2005 family minimum of 2000 (Rev. Proc.
    // 2004-71). That impeaches the division under Notice 2004-50 Q&A-31 *and*,
    // because a family plan is a candidate for the IRC 223(b)(5)(A) lowest
    // deductible, leaves the amount being divided undeterminable too. Both
    // diagnostics fire, and the division one must not end by saying the shared
    // limit still reports the limitation when the pool beside it is null.
    $result = U::calculate([
        'taxYear' => 2005,
        'filingStatus' => FilingStatus::MARRIED_FILING_JOINTLY->value,
        'persons' => [['id' => 't', 'birthYear' => 1970], ['id' => 's', 'birthYear' => 1972]],
        'accounts' => [
            ['id' => 'a', 'ownerId' => 't', 'type' => 'hsa', 'planRules' => ['hsa' => [
                'coverageTier' => 'family', 'hdhpAnnualDeductible' => 5000,
            ]]],
            ['id' => 'b', 'ownerId' => 's', 'type' => 'hsa', 'planRules' => ['hsa' => [
                'coverageTier' => 'family', 'hdhpAnnualDeductible' => 400,
            ]]],
        ],
    ]);
    $codes = array_column($result['diagnostics'], 'code');
    if (!in_array('HSA_SHARED_FAMILY_LIMIT_INDETERMINATE', $codes, true)) {
        failTest('expected HSA_SHARED_FAMILY_LIMIT_INDETERMINATE');
    }
    $division = null;
    foreach ($result['diagnostics'] as $entry) {
        if ($entry['code'] === 'HSA_FAMILY_LIMIT_DIVISION_INDETERMINATE') {
            $division = $entry;
        }
    }
    if ($division === null) {
        failTest('expected the division diagnostic');
    }
    $pool = null;
    foreach ($result['accounts'][0]['sharedLimits'] as $entry) {
        if ($entry['id'] === 'hsa223b5:t|s') {
            $pool = $entry;
        }
    }
    assertSameValue(null, $pool['limit']);
    if (str_contains($division['message'], 'shared limit still reports it')) {
        failTest('division diagnostic claims a limit that is null: ' . $division['message']);
    }
    if (!str_contains($division['message'], 'HSA_SHARED_FAMILY_LIMIT_INDETERMINATE')) {
        failTest('division diagnostic does not point at the amount diagnostic');
    }
});

$failed = 0;
$started = microtime(true);
foreach ($tests as $name => $body) {
    try {
        $body();
        fwrite(STDOUT, "ok - {$name}\n");
    } catch (Throwable $error) {
        $failed++;
        fwrite(STDERR, "not ok - {$name}\n  " . $error->getMessage() . "\n");
    }
}
$elapsed = number_format(microtime(true) - $started, 3);
fwrite(STDOUT, sprintf("\n%d tests, %d failed (%ss)\n", count($tests), $failed, $elapsed));
exit($failed === 0 ? 0 : 1);
