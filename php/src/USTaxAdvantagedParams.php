<?php

declare(strict_types=1);

namespace USTaxAdvantagedParams;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use RuntimeException;


enum FilingStatus: string
{
    case SINGLE = 'single';
    case MARRIED_FILING_JOINTLY = 'married_filing_jointly';
    case MARRIED_FILING_SEPARATELY = 'married_filing_separately';
    case HEAD_OF_HOUSEHOLD = 'head_of_household';
    case QUALIFYING_SURVIVING_SPOUSE = 'qualifying_surviving_spouse';
}

enum AccountType: string
{
    case TRADITIONAL_IRA = 'traditional_ira';
    case ROTH_IRA = 'roth_ira';
    case ROLLOVER_IRA = 'rollover_ira';
    case PAYROLL_DEDUCTION_IRA = 'payroll_deduction_ira';
    case DEEMED_TRADITIONAL_IRA = 'deemed_traditional_ira';
    case DEEMED_ROTH_IRA = 'deemed_roth_ira';
    case INHERITED_TRADITIONAL_IRA = 'inherited_traditional_ira';
    case INHERITED_ROTH_IRA = 'inherited_roth_ira';
    case SEP_IRA = 'sep_ira';
    case ROTH_SEP_IRA = 'roth_sep_ira';
    case SIMPLE_IRA = 'simple_ira';
    case ROTH_SIMPLE_IRA = 'roth_simple_ira';
    case SARSEP_IRA = 'sarsep_ira';
    case TRADITIONAL_401K = 'traditional_401k';
    case ROTH_401K = 'roth_401k';
    case SOLO_401K = 'solo_401k';
    case ROTH_SOLO_401K = 'roth_solo_401k';
    case SIMPLE_401K = 'simple_401k';
    case ROTH_SIMPLE_401K = 'roth_simple_401k';
    case STARTER_401K = 'starter_401k';
    /**
     * IRC 402A(e) pension-linked emergency savings account, as included in a
     * qualified trust under IRC 401(a) — IRC 402A(f)(1)(A) — or a plan under
     * IRC 403(b) — IRC 402A(f)(1)(B). Modeled as the designated Roth account
     * IRC 402A(e)(1)(A)(i) says it is treated as, so its contributions run
     * against IRC 402(g) and IRC 415(c). The third host at IRC 402A(f)(1)(C) is
     * GOVERNMENTAL_457B_PENSION_LINKED_EMERGENCY_SAVINGS, which runs against
     * neither.
     */
    case PENSION_LINKED_EMERGENCY_SAVINGS = 'pension_linked_emergency_savings';
    case TRADITIONAL_403B = 'traditional_403b';
    case ROTH_403B = 'roth_403b';
    case SAFE_HARBOR_403B_DEFERRAL_ONLY = 'safe_harbor_403b_deferral_only';
    case GOVERNMENTAL_457B = 'governmental_457b';
    case ROTH_GOVERNMENTAL_457B = 'roth_governmental_457b';
    /**
     * IRC 402A(e) pension-linked emergency savings account hosted in an eligible
     * deferred compensation plan of a governmental employer — the third
     * "applicable retirement plan" at IRC 402A(f)(1)(C). It is not the same
     * calculation as the other two hosts wearing a different label: deferrals
     * under such a plan are not IRC 402(g)(3) elective deferrals, so they run
     * against the IRC 457(e)(15) applicable dollar amount through
     * IRC 457(b)(2)(A) rather than IRC 402(g)(1), and IRC 415(a)(1) and (a)(2)
     * do not reach an IRC 457(b) plan at all, so no annual-additions limit
     * applies to it.
     */
    case GOVERNMENTAL_457B_PENSION_LINKED_EMERGENCY_SAVINGS = 'governmental_457b_pension_linked_emergency_savings';
    case NONGOVERNMENTAL_457B = 'nongovernmental_457b';
    case SECTION_457F = 'section_457f';
    case TRADITIONAL_TSP = 'traditional_tsp';
    case ROTH_TSP = 'roth_tsp';
    case SECTION_401A = 'section_401a';
    case PROFIT_SHARING_PLAN = 'profit_sharing_plan';
    case MONEY_PURCHASE_PLAN = 'money_purchase_plan';
    case KEOGH_PLAN = 'keogh_plan';
    case ESOP = 'esop';
    case DEFINED_BENEFIT_PLAN = 'defined_benefit_plan';
    case CASH_BALANCE_PLAN = 'cash_balance_plan';
    case HSA = 'hsa';
    case HEALTH_FSA = 'health_fsa';
    case DEPENDENT_CARE_FSA = 'dependent_care_fsa';
}

enum ConversionType: string
{
    case IRA_TO_ROTH_IRA = 'ira_to_roth_ira';
    case QUALIFIED_PLAN_TO_ROTH_IRA = 'qualified_plan_to_roth_ira';
    case IN_PLAN_ROTH_ROLLOVER = 'in_plan_roth_rollover';
}

enum CalculationStatus: string
{
    case DETERMINATE = 'determinate';
    case DETERMINATE_WITH_ASSUMPTIONS = 'determinate_with_assumptions';
    case INDETERMINATE = 'indeterminate';
    case UNAVAILABLE = 'unavailable';
    case INELIGIBLE = 'ineligible';
}

enum DiagnosticSeverity: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
}

class ParameterException extends InvalidArgumentException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}

final class UnsupportedTaxYearException extends ParameterException
{
    public function __construct(int $year, int $minimum, int $maximum)
    {
        parent::__construct(
            'UNSUPPORTED_TAX_YEAR',
            "Tax year {$year} is not supported. Supported years are {$minimum}-{$maximum}; future years are never extrapolated.",
        );
    }
}

final class PersonBuilder
{
    /** @var array<string,mixed> */
    private array $value;

    public function __construct(string $id)
    {
        $this->value = [
            'id' => $id,
            'compensation' => [],
            'magi' => [],
            'priorYearFicaWagesByEmployer' => [],
        ];
    }

    public function asTaxpayer(): self
    {
        $this->value['role'] = 'taxpayer';
        return $this;
    }

    public function asSpouse(): self
    {
        $this->value['role'] = 'spouse';
        return $this;
    }

    public function role(string $role): self
    {
        $this->value['role'] = $role;
        return $this;
    }

    public function bornOn(string $birthDate): self
    {
        $this->value['birthDate'] = $birthDate;
        unset($this->value['birthYear']);
        return $this;
    }

    public function bornIn(int $birthYear): self
    {
        $this->value['birthYear'] = $birthYear;
        unset($this->value['birthDate']);
        return $this;
    }

    public function iraCompensation(float|int $amount): self
    {
        $this->value['compensation']['iraCompensation'] = $amount;
        return $this;
    }

    public function w2Compensation(float|int $amount): self
    {
        $this->value['compensation']['w2Compensation'] = $amount;
        return $this;
    }

    public function selfEmploymentNetEarnings(float|int $amount): self
    {
        $this->value['compensation']['selfEmploymentNetEarnings'] = $amount;
        return $this;
    }

    public function rothIraMagi(float|int $amount): self
    {
        $this->value['magi']['rothIra'] = $amount;
        return $this;
    }

    public function traditionalIraDeductionMagi(float|int $amount): self
    {
        $this->value['magi']['traditionalIraDeduction'] = $amount;
        return $this;
    }

    public function rothConversionMagi(float|int $amount): self
    {
        $this->value['magi']['rothConversion'] = $amount;
        return $this;
    }

    /** IRC 129(b)(1) earned income of this person for the taxable year. */
    public function dependentCareEarnedIncome(float|int $amount): self
    {
        $this->value['dependentCareEarnedIncome'] = (float) $amount;
        return $this;
    }

    /** IRC 129(b)(2): this person is a student or incapable of self-care, so IRC 21(d)(2) deems earned income. */
    public function studentOrIncapableOfSelfCare(bool $applies = true): self
    {
        $this->value['isStudentOrIncapableOfSelfCare'] = $applies;
        return $this;
    }

    public function coveredByEmployerPlan(bool $covered = true): self
    {
        $this->value['coveredByEmployerRetirementPlan'] = $covered;
        return $this;
    }

    public function livedWithSpouseDuringYear(bool $livedTogether = true): self
    {
        $this->value['livedWithSpouseDuringYear'] = $livedTogether;
        return $this;
    }

    public function priorYearFicaWages(string $employerId, float|int $amount): self
    {
        $this->value['priorYearFicaWagesByEmployer'][$employerId] = $amount;
        return $this;
    }

    public function aggregateTraditionalSepSimpleIraBasis(float|int $amount): self
    {
        $this->value['traditionalSepSimpleIraBasis'] = $amount;
        return $this;
    }

    public function yearEndTraditionalSepSimpleIraValue(float|int $amount): self
    {
        $this->value['yearEndTraditionalSepSimpleIraValue'] = $amount;
        return $this;
    }

    public function otherTraditionalSepSimpleIraDistributions(float|int $amount): self
    {
        $this->value['otherTraditionalSepSimpleIraDistributions'] = $amount;
        return $this;
    }

    /**
     * IRC 223(c)(2) coverage this person held, with an optional list of eligible
     * months (1-12). Needed on a spouse who owns no health savings account,
     * because IRC 223(b)(5)(A) reads the couple's coverage, not their accounts.
     *
     * @param list<int>|null $eligibleMonths
     */
    public function hsaCoverage(string $tier, ?array $eligibleMonths = null): self
    {
        $this->value['hsaCoverage']['coverageTier'] = $tier;
        if ($eligibleMonths !== null) {
            $this->value['hsaCoverage']['eligibleMonths'] = array_values($eligibleMonths);
        }
        return $this;
    }

    /** IRC 223(b)(2) per-month coverage for this person, for a year in which the tier changes.
     *  @param list<array{month:int,coverage:string}> $coverage
     */
    public function hsaMonthlyCoverage(array $coverage): self
    {
        $this->value['hsaCoverage']['monthlyCoverage'] = array_values($coverage);
        return $this;
    }

    /** Records that this person held no high deductible health plan coverage in any month. */
    public function noHsaCoverage(): self
    {
        $this->value['hsaCoverage'] = [];
        return $this;
    }

    /** The person's plan annual deductible, which IRC 223(b)(5)(A) reads for 2004-2006. */
    public function hsaHdhpAnnualDeductible(float|int $amount): self
    {
        $this->value['hsaCoverage']['hdhpAnnualDeductible'] = $amount;
        return $this;
    }

    /**
     * The aggregate amount paid for the taxable year to Archer MSAs of this
     * person, which IRC 223(b)(4)(A) — or IRC 223(b)(5)(B)(i) for a married
     * couple — subtracts from the HSA limitation.
     */
    public function archerMsaContributions(float|int $amount): self
    {
        $this->value['archerMsaContributions'] = $amount;
        return $this;
    }

    /**
     * The aggregate amount contributed to this person's health savings accounts
     * for the taxable year under IRC 408(d)(9), which IRC 223(b)(4)(C) subtracts
     * from that person's own HSA limitation — married or not, and after any IRC
     * 223(b)(5)(B)(ii) division.
     */
    public function qualifiedHsaFundingDistributions(float|int $amount): self
    {
        $this->value['qualifiedHsaFundingDistributions'] = $amount;
        return $this;
    }

    /** @return array<string,mixed> */
    public function build(): array
    {
        return Engine::copy($this->value);
    }
}

final class AccountBuilder
{
    /** @var array<string,mixed> */
    private array $value;

    public function __construct(string $id, string $ownerId, AccountType|string $type)
    {
        $this->value = [
            'id' => $id,
            'ownerId' => $ownerId,
            'type' => $type instanceof AccountType ? $type->value : $type,
            'planRules' => [],
            'existingContributions' => [],
        ];
    }

    public function owner(string $ownerId): self
    {
        $this->value['ownerId'] = $ownerId;
        return $this;
    }

    public function accountType(AccountType|string $type): self
    {
        $this->value['type'] = $type instanceof AccountType ? $type->value : $type;
        return $this;
    }

    public function employer(string $employerId): self
    {
        $this->value['employerId'] = $employerId;
        return $this;
    }

    public function priority(int $priority): self
    {
        $this->value['priority'] = $priority;
        return $this;
    }

    public function planCompensation(float|int $amount): self
    {
        $this->value['planRules']['planCompensation'] = $amount;
        return $this;
    }

    public function includible457Compensation(float|int $amount): self
    {
        $this->value['planRules']['includibleCompensation457'] = $amount;
        return $this;
    }

    public function annualAdditionsGroup(string $groupId): self
    {
        $this->value['planRules']['annualAdditionsGroupId'] = $groupId;
        return $this;
    }

    public function planDocumentEmployeeLimit(float|int $amount): self
    {
        $this->value['planRules']['planDocumentEmployeeDeferralLimit'] = $amount;
        return $this;
    }

    public function planDocumentAnnualAdditionsLimit(float|int $amount): self
    {
        $this->value['planRules']['planDocumentAnnualAdditionsLimit'] = $amount;
        return $this;
    }

    /**
     * Portion of an IRC 402A(e) pension-linked emergency savings account balance
     * already attributable to participant contributions. Required for a
     * `pension_linked_emergency_savings` account; pass 0 for a new one.
     */
    public function pensionLinkedEmergencySavingsBalance(float|int $amount): self
    {
        $this->value['planRules']['pensionLinkedEmergencySavingsParticipantContributionBalance'] = $amount;
        return $this;
    }

    public function permitsRothContributions(bool $permits = true): self
    {
        $this->value['planRules']['permitsRothContributions'] = $permits;
        return $this;
    }

    public function permitsRothCatchUp(bool $permits = true): self
    {
        $this->value['planRules']['permitsRothCatchUp'] = $permits;
        return $this;
    }

    public function permitsAfterTaxContributions(bool $permits = true): self
    {
        $this->value['planRules']['permitsAfterTaxEmployeeContributions'] = $permits;
        return $this;
    }

    public function permitsInPlanRothRollover(bool $permits = true): self
    {
        $this->value['planRules']['permitsInPlanRothRollover'] = $permits;
        return $this;
    }

    public function contributionPreference(string $preference): self
    {
        $this->value['planRules']['contributionPreference'] = $preference;
        return $this;
    }

    public function expectedEmployerContribution(float|int $amount, ?string $taxTreatment = null): self
    {
        $this->value['planRules']['expectedEmployerContribution'] = $amount;
        if ($taxTreatment !== null) {
            $this->value['planRules']['employerContributionTaxTreatment'] = $taxTreatment;
        }
        return $this;
    }

    public function employerMatch(float $matchRate, float $compensationFraction): self
    {
        $this->value['planRules']['employerMatchRate'] = $matchRate;
        $this->value['planRules']['employerMatchCompensationFraction'] = $compensationFraction;
        return $this;
    }

    public function employerNonelective(float $rate): self
    {
        $this->value['planRules']['employerNonelectiveRate'] = $rate;
        return $this;
    }

    public function employerContributionTaxTreatment(string $treatment): self
    {
        $this->value['planRules']['employerContributionTaxTreatment'] = $treatment;
        return $this;
    }

    public function simpleEmployerMethod(string $method, float|int|null $customAmount = null): self
    {
        $this->value['planRules']['simpleEmployerContributionMethod'] = $method;
        if ($customAmount !== null) {
            $this->value['planRules']['simpleCustomEmployerContribution'] = $customAmount;
        }
        return $this;
    }

    public function simpleEnhancedLimitEligible(bool $eligible = true): self
    {
        $this->value['planRules']['simpleEnhancedLimitEligible'] = $eligible;
        return $this;
    }

    public function simpleAdditionalNonelectiveContribution(float|int $amount): self
    {
        $this->value['planRules']['simpleAdditionalNonelectiveContribution'] = $amount;
        return $this;
    }

    public function selfEmployedOwner(float|int $netEarningsAfterHalfSelfEmploymentTax): self
    {
        $this->value['planRules']['isSelfEmployedOwner'] = true;
        $this->value['planRules']['netEarningsFromSelfEmploymentAfterHalfSETax'] = $netEarningsAfterHalfSelfEmploymentTax;
        return $this;
    }

    /** @param array<string,mixed> $input */
    public function special403bCatchUp(array $input): self
    {
        $this->value['planRules']['special403bCatchUp'] = $input;
        return $this;
    }

    /** @param array<string,mixed> $input */
    public function special457CatchUp(array $input): self
    {
        $this->value['planRules']['section457SpecialCatchUp'] = $input;
        return $this;
    }

    /** IRC 223 coverage tier, with an optional list of eligible months (1-12).
     *  @param list<int>|null $eligibleMonths
     */
    public function hsaCoverage(string $tier, ?array $eligibleMonths = null): self
    {
        $this->value['planRules']['hsa']['coverageTier'] = $tier;
        if ($eligibleMonths !== null) {
            $this->value['planRules']['hsa']['eligibleMonths'] = array_values($eligibleMonths);
        }
        return $this;
    }

    /** IRC 223(b)(2) per-month coverage, for a year in which the tier changes.
     *  @param list<array{month:int,coverage:string}> $coverage
     */
    public function hsaMonthlyCoverage(array $coverage): self
    {
        $this->value['planRules']['hsa']['monthlyCoverage'] = array_values($coverage);
        return $this;
    }

    /** Required for 2004-2006, when IRC 223(b)(2) capped the monthly limitation by the deductible. */
    public function hsaHdhpAnnualDeductible(float|int $amount): self
    {
        $this->value['planRules']['hsa']['hdhpAnnualDeductible'] = $amount;
        return $this;
    }

    /** Elect the IRC 223(b)(8) last-month rule. Omit the argument to leave the testing period unresolved. */
    public function hsaLastMonthRule(?bool $testingPeriodSatisfied = null): self
    {
        $this->value['planRules']['hsa']['useLastMonthRule'] = true;
        if ($testingPeriodSatisfied !== null) {
            $this->value['planRules']['hsa']['testingPeriodSatisfied'] = $testingPeriodSatisfied;
        }
        return $this;
    }

    /** IRC 223(b)(8)(B)(ii): the testing period was failed because of death or disability. */
    public function hsaTestingPeriodFailureByDeathOrDisability(bool $failed = true): self
    {
        $this->value['planRules']['hsa']['testingPeriodFailureByDeathOrDisability'] = $failed;
        return $this;
    }

    /** IRC 223(b)(5)(B)(ii) agreed share of the single family limit, 0 through 1. */
    public function hsaFamilyLimitShare(float $share): self
    {
        $this->value['planRules']['hsa']['familyLimitShare'] = $share;
        return $this;
    }

    /** Rev. Rul. 2004-45 classification of a health FSA. */
    public function healthFsaPurpose(string $purpose): self
    {
        $this->value['planRules']['healthFsa'] ??= [];
        $this->value['planRules']['healthFsa']['purpose'] = $purpose;
        return $this;
    }

    /** Notice 2013-71 carryover. Mutually exclusive with healthFsaGracePeriod(). */
    public function healthFsaCarryover(bool $offers, float|int|null $priorYearUnusedAmount = null): self
    {
        $this->value['planRules']['healthFsa'] ??= [];
        $this->value['planRules']['healthFsa']['offersCarryover'] = $offers;
        if ($priorYearUnusedAmount !== null) {
            $this->value['planRules']['healthFsa']['priorYearUnusedAmount'] = (float) $priorYearUnusedAmount;
        }
        return $this;
    }

    /** Prop. Treas. Reg. 1.125-1(e) grace period. Mutually exclusive with healthFsaCarryover(). */
    public function healthFsaGracePeriod(bool $offers = true, float|int|null $priorYearUnusedAmount = null): self
    {
        $this->value['planRules']['healthFsa'] ??= [];
        $this->value['planRules']['healthFsa']['offersGracePeriod'] = $offers;
        if ($priorYearUnusedAmount !== null) {
            $this->value['planRules']['healthFsa']['priorYearUnusedAmount'] = (float) $priorYearUnusedAmount;
        }
        return $this;
    }

    /** Non-elective employer flex credits, and whether they could be elected as cash. */
    public function healthFsaEmployerFlexCredit(float|int $amount, ?bool $electableAsCash = null): self
    {
        $this->value['planRules']['healthFsa'] ??= [];
        $this->value['planRules']['healthFsa']['employerFlexCredit'] = (float) $amount;
        if ($electableAsCash !== null) {
            $this->value['planRules']['healthFsa']['flexCreditElectableAsCash'] = $electableAsCash;
        }
        return $this;
    }

    /** A limit the plan document imposes below the IRC 125(i) ceiling. */
    public function healthFsaPlanDocumentLimit(float|int $amount): self
    {
        $this->value['planRules']['healthFsa'] ??= [];
        $this->value['planRules']['healthFsa']['planDocumentLimit'] = (float) $amount;
        return $this;
    }

    /** Whether the cafeteria plan year is the calendar year; false makes the IRC 125(i) figure indeterminate. */
    public function healthFsaCalendarPlanYear(bool $isCalendarYear = true): self
    {
        $this->value['planRules']['healthFsa'] ??= [];
        $this->value['planRules']['healthFsa']['planYearIsCalendarYear'] = $isCalendarYear;
        return $this;
    }

    /** A lower dependent care maximum the employer's plan itself allows. */
    public function dependentCarePlanDocumentLimit(float|int $limit): self
    {
        $this->value['planRules']['dependentCareFsa'] ??= [];
        $this->value['planRules']['dependentCareFsa']['planDocumentLimit'] = (float) $limit;
        return $this;
    }

    public function grandfatheredSarsep(bool $grandfathered = true): self
    {
        $this->value['planRules']['grandfatheredSarsep'] = $grandfathered;
        return $this;
    }

    /** @param array<string,float|int> $contributions */
    public function existing(array $contributions): self
    {
        $this->value['existingContributions'] = $contributions;
        return $this;
    }

    /** @return array<string,mixed> */
    public function build(): array
    {
        return Engine::copy($this->value);
    }
}

final class RothConversionBuilder
{
    /** @var array<string,mixed> */
    private array $value;

    public function __construct(
        string $id,
        string $ownerId,
        ConversionType|string $type,
        float|int $amount,
    ) {
        $this->value = [
            'id' => $id,
            'ownerId' => $ownerId,
            'type' => $type instanceof ConversionType ? $type->value : $type,
            'amount' => $amount,
        ];
    }

    public function afterTaxBasis(float|int $amount): self
    {
        $this->value['afterTaxBasisInConvertedAmount'] = $amount;
        return $this;
    }

    public function aggregateIraBasis(float|int $amount): self
    {
        $this->value['aggregateIraBasisOverride'] = $amount;
        return $this;
    }

    public function yearEndAggregateIraValue(float|int $amount): self
    {
        $this->value['yearEndAggregateIraValueOverride'] = $amount;
        return $this;
    }

    public function otherwiseDistributable(bool $eligible = true): self
    {
        $this->value['otherwiseDistributableAmount'] = $eligible;
        return $this;
    }

    public function sourceAccount(string $accountId): self
    {
        $this->value['sourceAccountId'] = $accountId;
        return $this;
    }

    /** @return array<string,mixed> */
    public function build(): array
    {
        return Engine::copy($this->value);
    }
}

final class Scenario
{
    /** @param array<string,mixed> $input */
    public function __construct(private readonly array $input)
    {
    }

    /** @return array<string,mixed> */
    public function calculate(): array
    {
        return USTaxAdvantagedParams::calculate(Engine::copy($this->input));
    }

    /** @return array<string,mixed> */
    public function toInput(): array
    {
        return Engine::copy($this->input);
    }
}

final class ScenarioBuilder
{
    /** @var array<string,mixed> */
    private array $value;

    public static function forTaxYear(int $taxYear): self
    {
        return new self($taxYear);
    }

    public function __construct(int $taxYear)
    {
        $this->value = [
            'taxYear' => $taxYear,
            'filingStatus' => FilingStatus::SINGLE->value,
            'persons' => [],
            'accounts' => [],
            'conversions' => [],
        ];
    }

    public function filingStatus(FilingStatus|string $status): self
    {
        $this->value['filingStatus'] = $status instanceof FilingStatus ? $status->value : $status;
        return $this;
    }

    public function addPerson(PersonBuilder|array $person): self
    {
        $this->value['persons'][] = $person instanceof PersonBuilder ? $person->build() : Engine::copy($person);
        return $this;
    }

    public function taxpayer(string $id, ?callable $configure = null): self
    {
        $builder = (new PersonBuilder($id))->asTaxpayer();
        if ($configure !== null) {
            $configure($builder);
        }
        return $this->addPerson($builder);
    }

    public function spouse(string $id, ?callable $configure = null): self
    {
        $builder = (new PersonBuilder($id))->asSpouse();
        if ($configure !== null) {
            $configure($builder);
        }
        return $this->addPerson($builder);
    }

    public function addAccount(AccountBuilder|array $account): self
    {
        $this->value['accounts'][] = $account instanceof AccountBuilder
            ? $account->build()
            : Engine::copy($account);
        return $this;
    }

    public function account(
        string $id,
        string $ownerId,
        AccountType|string $type,
        ?callable $configure = null,
    ): self {
        $builder = new AccountBuilder($id, $ownerId, $type);
        if ($configure !== null) {
            $configure($builder);
        }
        return $this->addAccount($builder);
    }

    public function addConversion(RothConversionBuilder|array $conversion): self
    {
        $this->value['conversions'][] = $conversion instanceof RothConversionBuilder
            ? $conversion->build()
            : Engine::copy($conversion);
        return $this;
    }

    public function conversion(
        string $id,
        string $ownerId,
        ConversionType|string $type,
        float|int $amount,
        ?callable $configure = null,
    ): self {
        $builder = new RothConversionBuilder($id, $ownerId, $type, $amount);
        if ($configure !== null) {
            $configure($builder);
        }
        return $this->addConversion($builder);
    }

    public function build(): Scenario
    {
        return new Scenario(Engine::copy($this->value));
    }

    /** @return array<string,mixed> */
    public function calculate(): array
    {
        return $this->build()->calculate();
    }

    /** @return array<string,mixed> */
    public function toInput(): array
    {
        return Engine::copy($this->value);
    }
}

final class USTaxAdvantagedParams
{
    public const PACKAGE_NAME = 'us-tax-advantaged-params';
    public const ENGINE_VERSION = '0.4.1';

    private static ?array $parameters = null;

    /** @var array<string,mixed>|null */
    private static ?array $hsaParameters = null;

    /** @var array<string,mixed>|null */
    private static ?array $fsaParameters = null;

    /* <generated-parameters> */
private const PARAMETER_JSON = <<<'JSON'
{
  "schemaVersion": 1,
  "package": "us-tax-advantaged-params",
  "generatedThroughTaxYear": 2026,
  "supportedTaxYears": {
    "minimum": 1975,
    "maximum": 2026
  },
  "moneyUnit": "USD",
  "rounding": {
    "iraPhaseoutIncrement": 10,
    "iraPositiveReducedMinimum": 200
  },
  "historicalCoveragePolicy": {
    "description": "The dataset starts with the first generally available IRA contribution year. For pre-1987 employer-plan years lacking a universal modern IRC 402(g) or fully encoded IRC 415 limit, the engines return an indeterminate statutory maximum rather than inventing a number.",
    "pre1987EmployerPlanLimitStatus": "requires_plan_document_and_historical_law_facts"
  },
  "sources": [
    {
      "id": "irs-notice-2025-67",
      "title": "Notice 2025-67, 2026 retirement-plan cost-of-living adjustments",
      "url": "https://www.irs.gov/pub/irs-irbs/irb25-49.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-notice-2024-80",
      "title": "Notice 2024-80, 2025 retirement-plan cost-of-living adjustments",
      "url": "https://www.irs.gov/pub/irs-irbs/irb24-47.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-coda-manual-2002",
      "title": "IRS Cash or Deferred Arrangements manual historical limitation table",
      "url": "https://www.irs.gov/pub/irs-tege/codas.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-sep-sarsep-audit",
      "title": "IRS SEP/SARSEP Audit Techniques",
      "url": "https://www.irs.gov/pub/irs-tege/epche1303.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-soi-ira-1983",
      "title": "IRS SOI Bulletin describing 1981-and-earlier and 1982 IRA limits",
      "url": "https://www.irs.gov/pub/irs-soi/83rpsumbul.pdf",
      "authority": "IRS"
    },
    {
      "id": "dol-401k-history",
      "title": "U.S. Department of Labor 401(k) plan history",
      "url": "https://www.dol.gov/agencies/ebsa/about-ebsa/our-activities/resource-center/faqs/401k-plans",
      "authority": "DOL"
    },
    {
      "id": "irs-notice-2001-56",
      "title": "Notice 2001-56, compensation limitation under IRC 401(a)(17)",
      "url": "https://www.irs.gov/pub/irs-drop/n-01-56.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-employee-plans-news-fall-2009",
      "title": "Employee Plans News, Fall 2009: compensation and elective-deferral limits",
      "url": "https://www.irs.gov/pub/irs-tege/fall09.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-pub-535-2001",
      "title": "Publication 535 (2001), deduction worksheet for self-employed retirement-plan contributions",
      "url": "https://www.irs.gov/pub/irs-prior/p535--2001.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-sarsep-fix-it-guide-contribution-limits",
      "title": "SARSEP Fix-It Guide: contribution-limit and compensation rules",
      "url": "https://www.irs.gov/retirement-plans/sarsep-fix-it-guide-total-contributions-employee-elective-deferrals-and-nonelective-employer-contributions-exceeded-the-maximum-legal-limits",
      "authority": "IRS"
    },
    {
      "id": "usc-26-402",
      "title": "26 U.S.C. § 402(g)(7), special rule for certain 403(b) organizations",
      "url": "https://uscode.house.gov/view.xhtml?req=granuleid:USC-prelim-title26-section402&num=0&edition=prelim",
      "authority": "U.S. House Office of the Law Revision Counsel"
    },
    {
      "id": "irs-pub-571-2001",
      "title": "IRS Publication 571 (2001), Tax-Sheltered Annuity Plans (403(b) Plans), maximum exclusion allowance and maximum amount contributable",
      "url": "https://www.irs.gov/pub/irs-prior/p571--2001.pdf",
      "authority": "IRS"
    },
    {
      "id": "pl-107-16",
      "title": "Economic Growth and Tax Relief Reconciliation Act of 2001, Pub. L. 107-16, 115 Stat. 38 (section 632 repeal of the IRC 403(b)(2) exclusion allowance and the IRC 415(c)(4) elections)",
      "url": "https://www.govinfo.gov/content/pkg/PLAW-107publ16/pdf/PLAW-107publ16.pdf",
      "authority": "U.S. Congress"
    },
    {
      "id": "usc-26-402A",
      "title": "26 U.S.C. § 402A(e), pension-linked emergency savings accounts, including the § 402A(e)(3)(A)(i) limitation and its post-2024 adjustment rule",
      "url": "https://uscode.house.gov/view.xhtml?req=granuleid:USC-prelim-title26-section402A&num=0&edition=prelim",
      "authority": "U.S. House Office of the Law Revision Counsel"
    }
  ],
  "years": {
    "1975": {
      "year": 1975,
      "ira": {
        "baseContributionLimit": 1500,
        "age50CatchUp": 0,
        "compensationFraction": 0.15,
        "universalEligibility": false,
        "nondeductibleContributionAvailable": false,
        "spousalIraAvailable": false,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": null,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": null,
      "annualAdditionsCompensationFraction": null,
      "annualCompensation401a17": null,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": false,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": false,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": false,
        "baseDeferralLimit": null,
        "includibleCompensationFraction": null,
        "specialLastThreeYearsMaximum": null,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": false,
        "simpleIra": false,
        "traditional401k": false,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": false,
        "nongovernmental457b": false,
        "traditionalTsp": false,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": null,
        "traditionalIraSpouseCovered": null,
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1976": {
      "year": 1976,
      "ira": {
        "baseContributionLimit": 1500,
        "age50CatchUp": 0,
        "compensationFraction": 0.15,
        "universalEligibility": false,
        "nondeductibleContributionAvailable": false,
        "spousalIraAvailable": false,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": null,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": null,
      "annualAdditionsCompensationFraction": null,
      "annualCompensation401a17": null,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": false,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": false,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": false,
        "baseDeferralLimit": null,
        "includibleCompensationFraction": null,
        "specialLastThreeYearsMaximum": null,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": false,
        "simpleIra": false,
        "traditional401k": false,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": false,
        "nongovernmental457b": false,
        "traditionalTsp": false,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": null,
        "traditionalIraSpouseCovered": null,
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1977": {
      "year": 1977,
      "ira": {
        "baseContributionLimit": 1500,
        "age50CatchUp": 0,
        "compensationFraction": 0.15,
        "universalEligibility": false,
        "nondeductibleContributionAvailable": false,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": true,
        "oneEarnerHouseholdCombinedLimit": 1750,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": null,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": null,
      "annualAdditionsCompensationFraction": null,
      "annualCompensation401a17": null,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": false,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": false,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": false,
        "baseDeferralLimit": null,
        "includibleCompensationFraction": null,
        "specialLastThreeYearsMaximum": null,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": false,
        "simpleIra": false,
        "traditional401k": false,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": false,
        "nongovernmental457b": false,
        "traditionalTsp": false,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": null,
        "traditionalIraSpouseCovered": null,
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1978": {
      "year": 1978,
      "ira": {
        "baseContributionLimit": 1500,
        "age50CatchUp": 0,
        "compensationFraction": 0.15,
        "universalEligibility": false,
        "nondeductibleContributionAvailable": false,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": true,
        "oneEarnerHouseholdCombinedLimit": 1750,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": null,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": null,
      "annualAdditionsCompensationFraction": null,
      "annualCompensation401a17": null,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": false,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": false,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": false,
        "baseDeferralLimit": null,
        "includibleCompensationFraction": null,
        "specialLastThreeYearsMaximum": null,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": false,
        "simpleIra": false,
        "traditional401k": false,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": false,
        "nongovernmental457b": false,
        "traditionalTsp": false,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": null,
        "traditionalIraSpouseCovered": null,
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1979": {
      "year": 1979,
      "ira": {
        "baseContributionLimit": 1500,
        "age50CatchUp": 0,
        "compensationFraction": 0.15,
        "universalEligibility": false,
        "nondeductibleContributionAvailable": false,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": true,
        "oneEarnerHouseholdCombinedLimit": 1750,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": null,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": null,
      "annualAdditionsCompensationFraction": null,
      "annualCompensation401a17": null,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": false,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": false,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": false,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": null,
        "traditionalIraSpouseCovered": null,
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1980": {
      "year": 1980,
      "ira": {
        "baseContributionLimit": 1500,
        "age50CatchUp": 0,
        "compensationFraction": 0.15,
        "universalEligibility": false,
        "nondeductibleContributionAvailable": false,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": true,
        "oneEarnerHouseholdCombinedLimit": 1750,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": null,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": null,
      "annualAdditionsCompensationFraction": null,
      "annualCompensation401a17": null,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": false,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": false,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": null,
        "traditionalIraSpouseCovered": null,
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1981": {
      "year": 1981,
      "ira": {
        "baseContributionLimit": 1500,
        "age50CatchUp": 0,
        "compensationFraction": 0.15,
        "universalEligibility": false,
        "nondeductibleContributionAvailable": false,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": true,
        "oneEarnerHouseholdCombinedLimit": 1750,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": null,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": null,
      "annualAdditionsCompensationFraction": null,
      "annualCompensation401a17": null,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": false,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": false,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": null,
        "traditionalIraSpouseCovered": null,
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1982": {
      "year": 1982,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": false,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": null,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": null,
      "annualAdditionsCompensationFraction": null,
      "annualCompensation401a17": null,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": false,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": false,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": null,
        "traditionalIraSpouseCovered": null,
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1983": {
      "year": 1983,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": false,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": null,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": null,
      "annualAdditionsCompensationFraction": null,
      "annualCompensation401a17": null,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": false,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": false,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": null,
        "traditionalIraSpouseCovered": null,
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1984": {
      "year": 1984,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": false,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": null,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": null,
      "annualAdditionsCompensationFraction": null,
      "annualCompensation401a17": null,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": false,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": false,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": null,
        "traditionalIraSpouseCovered": null,
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1985": {
      "year": 1985,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": false,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": null,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": null,
      "annualAdditionsCompensationFraction": null,
      "annualCompensation401a17": null,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": false,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": false,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": null,
        "traditionalIraSpouseCovered": null,
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1986": {
      "year": 1986,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": false,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": null,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": null,
      "annualAdditionsCompensationFraction": null,
      "annualCompensation401a17": null,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": false,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": false,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": null,
        "traditionalIraSpouseCovered": null,
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1987": {
      "year": 1987,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": 7000,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 30000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": null,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": true,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            25000,
            35000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1988": {
      "year": 1988,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": 7313,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 30000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": null,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": true,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            25000,
            35000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1989": {
      "year": 1989,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": 7627,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 30000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": 200000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": true,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            25000,
            35000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1990": {
      "year": 1990,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": 7979,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 30000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": 209200,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": true,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            25000,
            35000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1991": {
      "year": 1991,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": 8475,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 30000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": 222220,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": true,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            25000,
            35000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1992": {
      "year": 1992,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": 8728,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 30000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": 228860,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": true,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            25000,
            35000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1993": {
      "year": 1993,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": 8994,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 30000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": 235840,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": true,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            25000,
            35000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1994": {
      "year": 1994,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": 9240,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 30000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": 150000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": true,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            25000,
            35000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1995": {
      "year": 1995,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": 9240,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 30000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": 150000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": true,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            25000,
            35000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1996": {
      "year": 1996,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": 2000,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": 2250,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": 9500,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 30000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": 150000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": null,
        "newSarsepMayBeEstablished": true,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": false,
        "salaryReductionLimit": null,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": false,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            25000,
            35000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1997": {
      "year": 1997,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": false
      },
      "electiveDeferral402g": 9500,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 30000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": 160000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": 400,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 6000,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 7500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": false,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            25000,
            35000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            40000,
            50000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": null
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1998": {
      "year": 1998,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 10000,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 30000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": 160000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": 400,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 6000,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 8000,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            30000,
            40000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            50000,
            60000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            95000,
            110000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "1999": {
      "year": 1999,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 10000,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 30000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": 160000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": 400,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 6000,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 8000,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            31000,
            41000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            51000,
            61000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            95000,
            110000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2000": {
      "year": 2000,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 10500,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 30000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": 170000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": 450,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 6000,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 8000,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            32000,
            42000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            52000,
            62000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            95000,
            110000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2001": {
      "year": 2001,
      "ira": {
        "baseContributionLimit": 2000,
        "age50CatchUp": 0,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 10500,
      "generalAge50CatchUp": 0,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 35000,
      "annualAdditionsCompensationFraction": 0.25,
      "annualCompensation401a17": 170000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.15,
        "selfEmployedEquivalentRate": 0.13043478260869565,
        "minimumEligibleCompensation": 450,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 6500,
        "generalAge50CatchUp": 0,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 8500,
        "includibleCompensationFraction": 0.3333333333333333,
        "specialLastThreeYearsMaximum": 15000,
        "governmentalAge50CatchUp": 0,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            33000,
            43000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            53000,
            63000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            95000,
            110000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2002": {
      "year": 2002,
      "ira": {
        "baseContributionLimit": 3000,
        "age50CatchUp": 500,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 11000,
      "generalAge50CatchUp": 1000,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 40000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 200000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 450,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 7000,
        "generalAge50CatchUp": 500,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 11000,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 22000,
        "governmentalAge50CatchUp": 1000,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            34000,
            44000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            54000,
            64000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            95000,
            110000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2003": {
      "year": 2003,
      "ira": {
        "baseContributionLimit": 3000,
        "age50CatchUp": 500,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 12000,
      "generalAge50CatchUp": 2000,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 40000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 200000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 450,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 8000,
        "generalAge50CatchUp": 1000,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 12000,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 24000,
        "governmentalAge50CatchUp": 2000,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            40000,
            50000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            60000,
            70000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            95000,
            110000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2004": {
      "year": 2004,
      "ira": {
        "baseContributionLimit": 3000,
        "age50CatchUp": 500,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 13000,
      "generalAge50CatchUp": 3000,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 41000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 205000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 450,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 9000,
        "generalAge50CatchUp": 1500,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 13000,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 26000,
        "governmentalAge50CatchUp": 3000,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            45000,
            55000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            65000,
            75000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            95000,
            110000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2005": {
      "year": 2005,
      "ira": {
        "baseContributionLimit": 4000,
        "age50CatchUp": 500,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 14000,
      "generalAge50CatchUp": 4000,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 42000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 210000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 450,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 10000,
        "generalAge50CatchUp": 2000,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 14000,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 28000,
        "governmentalAge50CatchUp": 4000,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": false,
        "traditional403b": true,
        "designatedRoth403b": false,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            50000,
            60000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            70000,
            80000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            95000,
            110000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2006": {
      "year": 2006,
      "ira": {
        "baseContributionLimit": 4000,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 15000,
      "generalAge50CatchUp": 5000,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 44000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 220000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 450,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 10000,
        "generalAge50CatchUp": 2500,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 15000,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 30000,
        "governmentalAge50CatchUp": 5000,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            50000,
            60000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            75000,
            85000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            95000,
            110000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            150000,
            160000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2007": {
      "year": 2007,
      "ira": {
        "baseContributionLimit": 4000,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 15500,
      "generalAge50CatchUp": 5000,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 45000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 225000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 500,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 10500,
        "generalAge50CatchUp": 2500,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 15500,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 31000,
        "governmentalAge50CatchUp": 5000,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            52000,
            62000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            83000,
            103000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            156000,
            166000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            99000,
            114000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            156000,
            166000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2008": {
      "year": 2008,
      "ira": {
        "baseContributionLimit": 5000,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 15500,
      "generalAge50CatchUp": 5000,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 46000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 230000,
      "definedBenefitAnnualBenefit415b": null,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 500,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 10500,
        "generalAge50CatchUp": 2500,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 15500,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 31000,
        "governmentalAge50CatchUp": 5000,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            53000,
            63000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            85000,
            105000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            159000,
            169000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            101000,
            116000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            159000,
            169000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2009": {
      "year": 2009,
      "ira": {
        "baseContributionLimit": 5000,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 16500,
      "generalAge50CatchUp": 5500,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 49000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 245000,
      "definedBenefitAnnualBenefit415b": 195000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 550,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 11500,
        "generalAge50CatchUp": 2500,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 16500,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 33000,
        "governmentalAge50CatchUp": 5500,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            55000,
            65000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            89000,
            109000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            166000,
            176000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            105000,
            120000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            166000,
            176000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2010": {
      "year": 2010,
      "ira": {
        "baseContributionLimit": 5000,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 16500,
      "generalAge50CatchUp": 5500,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 49000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 245000,
      "definedBenefitAnnualBenefit415b": 195000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 550,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 11500,
        "generalAge50CatchUp": 2500,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 16500,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 33000,
        "governmentalAge50CatchUp": 5500,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": false
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            56000,
            66000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            89000,
            109000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            167000,
            177000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            105000,
            120000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            167000,
            177000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2011": {
      "year": 2011,
      "ira": {
        "baseContributionLimit": 5000,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 16500,
      "generalAge50CatchUp": 5500,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 49000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 245000,
      "definedBenefitAnnualBenefit415b": 195000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 550,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 11500,
        "generalAge50CatchUp": 2500,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 16500,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 33000,
        "governmentalAge50CatchUp": 5500,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": false,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            56000,
            66000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            90000,
            110000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            169000,
            179000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            107000,
            122000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            169000,
            179000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2012": {
      "year": 2012,
      "ira": {
        "baseContributionLimit": 5000,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 17000,
      "generalAge50CatchUp": 5500,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 50000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 250000,
      "definedBenefitAnnualBenefit415b": 200000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 550,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 11500,
        "generalAge50CatchUp": 2500,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 17000,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 34000,
        "governmentalAge50CatchUp": 5500,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            58000,
            68000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            92000,
            112000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            173000,
            183000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            110000,
            125000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            173000,
            183000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2013": {
      "year": 2013,
      "ira": {
        "baseContributionLimit": 5500,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 17500,
      "generalAge50CatchUp": 5500,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 51000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 255000,
      "definedBenefitAnnualBenefit415b": 205000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 550,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 12000,
        "generalAge50CatchUp": 2500,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 17500,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 35000,
        "governmentalAge50CatchUp": 5500,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            59000,
            69000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            95000,
            115000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            178000,
            188000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            112000,
            127000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            178000,
            188000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2014": {
      "year": 2014,
      "ira": {
        "baseContributionLimit": 5500,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 17500,
      "generalAge50CatchUp": 5500,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 52000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 260000,
      "definedBenefitAnnualBenefit415b": 210000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 550,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 12000,
        "generalAge50CatchUp": 2500,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 17500,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 35000,
        "governmentalAge50CatchUp": 5500,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            60000,
            70000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            96000,
            116000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            181000,
            191000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            114000,
            129000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            181000,
            191000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2015": {
      "year": 2015,
      "ira": {
        "baseContributionLimit": 5500,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 18000,
      "generalAge50CatchUp": 6000,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 53000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 265000,
      "definedBenefitAnnualBenefit415b": 210000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 600,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 12500,
        "generalAge50CatchUp": 3000,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 18000,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 36000,
        "governmentalAge50CatchUp": 6000,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            61000,
            71000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            98000,
            118000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            183000,
            193000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            116000,
            131000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            183000,
            193000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2016": {
      "year": 2016,
      "ira": {
        "baseContributionLimit": 5500,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 18000,
      "generalAge50CatchUp": 6000,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 53000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 265000,
      "definedBenefitAnnualBenefit415b": 210000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 600,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 12500,
        "generalAge50CatchUp": 3000,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 18000,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 36000,
        "governmentalAge50CatchUp": 6000,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            61000,
            71000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            98000,
            118000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            184000,
            194000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            117000,
            132000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            184000,
            194000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2017": {
      "year": 2017,
      "ira": {
        "baseContributionLimit": 5500,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 18000,
      "generalAge50CatchUp": 6000,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 54000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 270000,
      "definedBenefitAnnualBenefit415b": 215000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 600,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 12500,
        "generalAge50CatchUp": 3000,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 18000,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 36000,
        "governmentalAge50CatchUp": 6000,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            62000,
            72000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            99000,
            119000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            186000,
            196000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            118000,
            133000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            186000,
            196000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2018": {
      "year": 2018,
      "ira": {
        "baseContributionLimit": 5500,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 18500,
      "generalAge50CatchUp": 6000,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 55000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 275000,
      "definedBenefitAnnualBenefit415b": 220000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 600,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 12500,
        "generalAge50CatchUp": 3000,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 18500,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 37000,
        "governmentalAge50CatchUp": 6000,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            63000,
            73000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            101000,
            121000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            189000,
            199000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            120000,
            135000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            189000,
            199000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2019": {
      "year": 2019,
      "ira": {
        "baseContributionLimit": 6000,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": true,
        "rothAvailable": true
      },
      "electiveDeferral402g": 19000,
      "generalAge50CatchUp": 6000,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 56000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 280000,
      "definedBenefitAnnualBenefit415b": 225000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 600,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 13000,
        "generalAge50CatchUp": 3000,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 19000,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 38000,
        "governmentalAge50CatchUp": 6000,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            64000,
            74000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            103000,
            123000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            193000,
            203000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            122000,
            137000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            193000,
            203000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2020": {
      "year": 2020,
      "ira": {
        "baseContributionLimit": 6000,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": false,
        "rothAvailable": true
      },
      "electiveDeferral402g": 19500,
      "generalAge50CatchUp": 6500,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 57000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 285000,
      "definedBenefitAnnualBenefit415b": 230000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 600,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 13500,
        "generalAge50CatchUp": 3000,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 19500,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 39000,
        "governmentalAge50CatchUp": 6500,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            65000,
            75000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            104000,
            124000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            196000,
            206000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            124000,
            139000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            196000,
            206000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2021": {
      "year": 2021,
      "ira": {
        "baseContributionLimit": 6000,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": false,
        "rothAvailable": true
      },
      "electiveDeferral402g": 19500,
      "generalAge50CatchUp": 6500,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 58000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 290000,
      "definedBenefitAnnualBenefit415b": 230000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 650,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 13500,
        "generalAge50CatchUp": 3000,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 19500,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 39000,
        "governmentalAge50CatchUp": 6500,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            66000,
            76000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            105000,
            125000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            198000,
            208000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            125000,
            140000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            198000,
            208000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2022": {
      "year": 2022,
      "ira": {
        "baseContributionLimit": 6000,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": false,
        "rothAvailable": true
      },
      "electiveDeferral402g": 20500,
      "generalAge50CatchUp": 6500,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 61000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 305000,
      "definedBenefitAnnualBenefit415b": 245000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 650,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": false
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 14000,
        "generalAge50CatchUp": 3000,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 20500,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 41000,
        "governmentalAge50CatchUp": 6500,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": false,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            68000,
            78000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            109000,
            129000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            204000,
            214000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            129000,
            144000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            204000,
            214000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2023": {
      "year": 2023,
      "ira": {
        "baseContributionLimit": 6500,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": false,
        "rothAvailable": true
      },
      "electiveDeferral402g": 22500,
      "generalAge50CatchUp": 7500,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 66000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 330000,
      "definedBenefitAnnualBenefit415b": 265000,
      "pensionLinkedEmergencySavingsBalanceCap402A": null,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 750,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": true
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 15500,
        "generalAge50CatchUp": 3500,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": null,
        "certainPlanAge50CatchUp": null,
        "additionalNonelectiveContributionCap": null
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 22500,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 45000,
        "governmentalAge50CatchUp": 7500,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": false,
        "baseDeferralLimit": null,
        "age50CatchUp": 0
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": true,
        "starter401kOrSafeHarbor403b": false,
        "pensionLinkedEmergencySavings": false
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            73000,
            83000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            116000,
            136000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            218000,
            228000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            138000,
            153000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            218000,
            228000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2024": {
      "year": 2024,
      "ira": {
        "baseContributionLimit": 7000,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": false,
        "rothAvailable": true
      },
      "electiveDeferral402g": 23000,
      "generalAge50CatchUp": 7500,
      "age60To63CatchUp": null,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 69000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 345000,
      "definedBenefitAnnualBenefit415b": 275000,
      "pensionLinkedEmergencySavingsBalanceCap402A": 2500,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 750,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": true
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 16000,
        "generalAge50CatchUp": 3500,
        "age60To63CatchUp": null,
        "certainPlanEnhancedSalaryReductionLimit": 17600,
        "certainPlanAge50CatchUp": 3850,
        "additionalNonelectiveContributionCap": 5000
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 23000,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 46000,
        "governmentalAge50CatchUp": 7500,
        "governmentalAge60To63CatchUp": null,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": true,
        "baseDeferralLimit": 6000,
        "age50CatchUp": 1000
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": true,
        "starter401kOrSafeHarbor403b": true,
        "pensionLinkedEmergencySavings": true
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            77000,
            87000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            123000,
            143000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            230000,
            240000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            146000,
            161000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            230000,
            240000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2025": {
      "year": 2025,
      "ira": {
        "baseContributionLimit": 7000,
        "age50CatchUp": 1000,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": false,
        "rothAvailable": true
      },
      "electiveDeferral402g": 23500,
      "generalAge50CatchUp": 7500,
      "age60To63CatchUp": 11250,
      "rothCatchUpPriorYearFicaWageThreshold": null,
      "annualAdditions415c": 70000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 350000,
      "definedBenefitAnnualBenefit415b": 280000,
      "pensionLinkedEmergencySavingsBalanceCap402A": 2500,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 750,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": true
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 16500,
        "generalAge50CatchUp": 3500,
        "age60To63CatchUp": 5250,
        "certainPlanEnhancedSalaryReductionLimit": 17600,
        "certainPlanAge50CatchUp": 3850,
        "additionalNonelectiveContributionCap": 5100
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 23500,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 47000,
        "governmentalAge50CatchUp": 7500,
        "governmentalAge60To63CatchUp": 11250,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": true,
        "baseDeferralLimit": 6000,
        "age50CatchUp": 1000
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": true,
        "starter401kOrSafeHarbor403b": true,
        "pensionLinkedEmergencySavings": true
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            79000,
            89000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            126000,
            146000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            236000,
            246000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            150000,
            165000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            236000,
            246000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    },
    "2026": {
      "year": 2026,
      "ira": {
        "baseContributionLimit": 7500,
        "age50CatchUp": 1100,
        "compensationFraction": 1,
        "universalEligibility": true,
        "nondeductibleContributionAvailable": true,
        "spousalIraAvailable": true,
        "nonworkingSpouseIndividualLimit": null,
        "spousalDeductionIsTwiceTheLesserOfContributions": false,
        "oneEarnerHouseholdCombinedLimit": null,
        "traditionalContributionAge70HalfRestriction": false,
        "rothAvailable": true
      },
      "electiveDeferral402g": 24500,
      "generalAge50CatchUp": 8000,
      "age60To63CatchUp": 11250,
      "rothCatchUpPriorYearFicaWageThreshold": 150000,
      "annualAdditions415c": 72000,
      "annualAdditionsCompensationFraction": 1,
      "annualCompensation401a17": 360000,
      "definedBenefitAnnualBenefit415b": 290000,
      "pensionLinkedEmergencySavingsBalanceCap402A": 2600,
      "sep": {
        "available": true,
        "maximumEmployerContributionRate": 0.25,
        "selfEmployedEquivalentRate": 0.2,
        "minimumEligibleCompensation": 800,
        "newSarsepMayBeEstablished": false,
        "grandfatheredSarsepMayOperate": true,
        "rothSepAvailable": true
      },
      "simple": {
        "available": true,
        "salaryReductionLimit": 17000,
        "generalAge50CatchUp": 4000,
        "age60To63CatchUp": 5250,
        "certainPlanEnhancedSalaryReductionLimit": 18100,
        "certainPlanAge50CatchUp": 3850,
        "additionalNonelectiveContributionCap": 5300
      },
      "section457b": {
        "available": true,
        "baseDeferralLimit": 24500,
        "includibleCompensationFraction": 1,
        "specialLastThreeYearsMaximum": 49000,
        "governmentalAge50CatchUp": 8000,
        "governmentalAge60To63CatchUp": 11250,
        "designatedRothAvailableForGovernmentalPlans": true
      },
      "starterDeferralOnly": {
        "available": true,
        "baseDeferralLimit": 6000,
        "age50CatchUp": 1100
      },
      "availability": {
        "traditionalIra": true,
        "rothIra": true,
        "sepIra": true,
        "simpleIra": true,
        "traditional401k": true,
        "designatedRoth401k": true,
        "traditional403b": true,
        "designatedRoth403b": true,
        "governmental457b": true,
        "nongovernmental457b": true,
        "traditionalTsp": true,
        "rothTsp": true,
        "rothSimpleOrSep": true,
        "starter401kOrSafeHarbor403b": true,
        "pensionLinkedEmergencySavings": true
      },
      "phaseouts": {
        "traditionalIraCovered": {
          "singleOrHeadOfHousehold": [
            81000,
            91000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            129000,
            149000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "traditionalIraSpouseCovered": {
          "marriedFilingJointly": [
            242000,
            252000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        },
        "rothIra": {
          "singleOrHeadOfHousehold": [
            153000,
            168000
          ],
          "marriedFilingJointlyOrQualifyingSurvivingSpouse": [
            242000,
            252000
          ],
          "marriedFilingSeparatelyLivingTogether": [
            0,
            10000
          ]
        }
      },
      "special403b15YearCatchUp": {
        "annualLimit": 3000,
        "lifetimeLimit": 15000,
        "serviceLimitPerYear": 5000
      }
    }
  }
}
JSON;
/* </generated-parameters> */

    /* <generated-hsa-parameters> */
private const HSA_PARAMETER_JSON = <<<'JSON'
{
  "schemaVersion": 1,
  "package": "us-tax-advantaged-params",
  "generatedThroughTaxYear": 2026,
  "supportedTaxYears": {
    "minimum": 2004,
    "maximum": 2026
  },
  "moneyUnit": "USD",
  "proration": {
    "method": "sum_annual_amounts_for_eligible_months_then_divide_by_twelve",
    "monthsInYear": 12
  },
  "historicalCoveragePolicy": {
    "description": "Health savings accounts were created by the Medicare Prescription Drug, Improvement, and Modernization Act of 2003 section 1201, effective for taxable years beginning after December 31, 2003. The table therefore starts at 2004 and is never extrapolated forward: a tax year with no published revenue procedure returns an unavailable status and a diagnostic rather than an inflation-projected amount.",
    "preTaxRelief2006DeductibleCap": "For 2004 through 2006, IRC 223(b)(2) capped each month\u0027s limitation at 1/12 of the lesser of the plan\u0027s annual deductible and the statutory dollar amount. The Tax Relief and Health Care Act of 2006 section 303 removed that cap for taxable years beginning after 2006. In the capped years the engine requires the plan\u0027s annual deductible and returns an indeterminate result without it.",
    "lastMonthRuleEffectiveDate": "The IRC 223(b)(8) last-month rule was added by the Tax Relief and Health Care Act of 2006 section 305, effective for taxable years beginning after December 31, 2006. Electing it for an earlier year produces a diagnostic and the ordinary month-by-month limitation."
  },
  "sources": [
    {
      "id": "usc-26-223",
      "title": "26 U.S.C. 223, Health savings accounts (2023 edition of the United States Code)",
      "url": "https://www.govinfo.gov/content/pkg/USCODE-2023-title26/pdf/USCODE-2023-title26-subtitleA-chap1-subchapB-partVII-sec223.pdf",
      "authority": "U.S. Government Publishing Office"
    },
    {
      "id": "irs-notice-2004-2",
      "title": "Notice 2004-2, Health Savings Accounts (2004 amounts and the IRC 223(b)(3) catch-up schedule)",
      "url": "https://www.irs.gov/pub/irs-drop/n-04-2.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2004-71",
      "title": "Rev. Proc. 2004-71, section 3.22, 2005 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-04-71.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2005-70",
      "title": "Rev. Proc. 2005-70, section 3.22, 2006 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-05-70.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2006-53",
      "title": "Rev. Proc. 2006-53, section 3.24, 2007 high deductible health plan amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-06-53.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2007-36",
      "title": "Rev. Proc. 2007-36, restated 2007 and new 2008 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-07-36.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2008-29",
      "title": "Rev. Proc. 2008-29, 2009 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-08-29.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2009-29",
      "title": "Rev. Proc. 2009-29, 2010 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-09-29.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2010-22",
      "title": "Rev. Proc. 2010-22, 2011 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-10-22.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2011-32",
      "title": "Rev. Proc. 2011-32, 2012 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-11-32.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2012-26",
      "title": "Rev. Proc. 2012-26, 2013 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-12-26.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2013-25",
      "title": "Rev. Proc. 2013-25, 2014 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-13-25.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2014-30",
      "title": "Rev. Proc. 2014-30, 2015 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-14-30.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2015-30",
      "title": "Rev. Proc. 2015-30, 2016 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-15-30.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2016-28",
      "title": "Rev. Proc. 2016-28, 2017 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-16-28.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2017-37",
      "title": "Rev. Proc. 2017-37, 2018 health savings account amounts as first announced",
      "url": "https://www.irs.gov/pub/irs-drop/rp-17-37.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2018-18",
      "title": "Rev. Proc. 2018-18 (Internal Revenue Bulletin 2018-10), which briefly reduced the 2018 family limit to $6,850",
      "url": "https://www.irs.gov/pub/irs-irbs/irb18-10.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2018-27",
      "title": "Rev. Proc. 2018-27, restoring $6,900 as the operative 2018 family limit",
      "url": "https://www.irs.gov/pub/irs-drop/rp-18-27.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2018-30",
      "title": "Rev. Proc. 2018-30, 2019 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-18-30.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2019-25",
      "title": "Rev. Proc. 2019-25 (Internal Revenue Bulletin 2019-22), 2020 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-irbs/irb19-22.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2020-32",
      "title": "Rev. Proc. 2020-32, 2021 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-20-32.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2021-25",
      "title": "Rev. Proc. 2021-25, 2022 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-21-25.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2022-24",
      "title": "Rev. Proc. 2022-24, 2023 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-22-24.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2023-23",
      "title": "Rev. Proc. 2023-23, 2024 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-23-23.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2024-25",
      "title": "Rev. Proc. 2024-25, 2025 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-24-25.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2025-19",
      "title": "Rev. Proc. 2025-19, 2026 health savings account amounts",
      "url": "https://www.irs.gov/pub/irs-drop/rp-25-19.pdf",
      "authority": "IRS"
    }
  ],
  "years": {
    "2004": {
      "year": 2004,
      "annualContributionLimit": {
        "selfOnly": 2600,
        "family": 5150
      },
      "additionalContributionAmountAge55": 500,
      "contributionLimitCappedByHdhpAnnualDeductible": true,
      "lastMonthRuleAvailable": false,
      "testingPeriodMonths": null,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1000,
          "family": 2000
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 5000,
          "family": 10000
        }
      }
    },
    "2005": {
      "year": 2005,
      "annualContributionLimit": {
        "selfOnly": 2650,
        "family": 5250
      },
      "additionalContributionAmountAge55": 600,
      "contributionLimitCappedByHdhpAnnualDeductible": true,
      "lastMonthRuleAvailable": false,
      "testingPeriodMonths": null,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1000,
          "family": 2000
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 5100,
          "family": 10200
        }
      }
    },
    "2006": {
      "year": 2006,
      "annualContributionLimit": {
        "selfOnly": 2700,
        "family": 5450
      },
      "additionalContributionAmountAge55": 700,
      "contributionLimitCappedByHdhpAnnualDeductible": true,
      "lastMonthRuleAvailable": false,
      "testingPeriodMonths": null,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1050,
          "family": 2100
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 5250,
          "family": 10500
        }
      }
    },
    "2007": {
      "year": 2007,
      "annualContributionLimit": {
        "selfOnly": 2850,
        "family": 5650
      },
      "additionalContributionAmountAge55": 800,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1100,
          "family": 2200
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 5500,
          "family": 11000
        }
      }
    },
    "2008": {
      "year": 2008,
      "annualContributionLimit": {
        "selfOnly": 2900,
        "family": 5800
      },
      "additionalContributionAmountAge55": 900,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1100,
          "family": 2200
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 5600,
          "family": 11200
        }
      }
    },
    "2009": {
      "year": 2009,
      "annualContributionLimit": {
        "selfOnly": 3000,
        "family": 5950
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1150,
          "family": 2300
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 5800,
          "family": 11600
        }
      }
    },
    "2010": {
      "year": 2010,
      "annualContributionLimit": {
        "selfOnly": 3050,
        "family": 6150
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1200,
          "family": 2400
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 5950,
          "family": 11900
        }
      }
    },
    "2011": {
      "year": 2011,
      "annualContributionLimit": {
        "selfOnly": 3050,
        "family": 6150
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1200,
          "family": 2400
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 5950,
          "family": 11900
        }
      }
    },
    "2012": {
      "year": 2012,
      "annualContributionLimit": {
        "selfOnly": 3100,
        "family": 6250
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1200,
          "family": 2400
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 6050,
          "family": 12100
        }
      }
    },
    "2013": {
      "year": 2013,
      "annualContributionLimit": {
        "selfOnly": 3250,
        "family": 6450
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1250,
          "family": 2500
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 6250,
          "family": 12500
        }
      }
    },
    "2014": {
      "year": 2014,
      "annualContributionLimit": {
        "selfOnly": 3300,
        "family": 6550
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1250,
          "family": 2500
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 6350,
          "family": 12700
        }
      }
    },
    "2015": {
      "year": 2015,
      "annualContributionLimit": {
        "selfOnly": 3350,
        "family": 6650
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1300,
          "family": 2600
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 6450,
          "family": 12900
        }
      }
    },
    "2016": {
      "year": 2016,
      "annualContributionLimit": {
        "selfOnly": 3350,
        "family": 6750
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1300,
          "family": 2600
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 6550,
          "family": 13100
        }
      }
    },
    "2017": {
      "year": 2017,
      "annualContributionLimit": {
        "selfOnly": 3400,
        "family": 6750
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1300,
          "family": 2600
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 6550,
          "family": 13100
        }
      }
    },
    "2018": {
      "year": 2018,
      "annualContributionLimit": {
        "selfOnly": 3450,
        "family": 6900
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1350,
          "family": 2700
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 6650,
          "family": 13300
        }
      }
    },
    "2019": {
      "year": 2019,
      "annualContributionLimit": {
        "selfOnly": 3500,
        "family": 7000
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1350,
          "family": 2700
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 6750,
          "family": 13500
        }
      }
    },
    "2020": {
      "year": 2020,
      "annualContributionLimit": {
        "selfOnly": 3550,
        "family": 7100
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1400,
          "family": 2800
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 6900,
          "family": 13800
        }
      }
    },
    "2021": {
      "year": 2021,
      "annualContributionLimit": {
        "selfOnly": 3600,
        "family": 7200
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1400,
          "family": 2800
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 7000,
          "family": 14000
        }
      }
    },
    "2022": {
      "year": 2022,
      "annualContributionLimit": {
        "selfOnly": 3650,
        "family": 7300
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1400,
          "family": 2800
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 7050,
          "family": 14100
        }
      }
    },
    "2023": {
      "year": 2023,
      "annualContributionLimit": {
        "selfOnly": 3850,
        "family": 7750
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1500,
          "family": 3000
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 7500,
          "family": 15000
        }
      }
    },
    "2024": {
      "year": 2024,
      "annualContributionLimit": {
        "selfOnly": 4150,
        "family": 8300
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1600,
          "family": 3200
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 8050,
          "family": 16100
        }
      }
    },
    "2025": {
      "year": 2025,
      "annualContributionLimit": {
        "selfOnly": 4300,
        "family": 8550
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1650,
          "family": 3300
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 8300,
          "family": 16600
        }
      }
    },
    "2026": {
      "year": 2026,
      "annualContributionLimit": {
        "selfOnly": 4400,
        "family": 8750
      },
      "additionalContributionAmountAge55": 1000,
      "contributionLimitCappedByHdhpAnnualDeductible": false,
      "lastMonthRuleAvailable": true,
      "testingPeriodMonths": 13,
      "hdhp": {
        "minimumAnnualDeductible": {
          "selfOnly": 1700,
          "family": 3400
        },
        "maximumAnnualOutOfPocket": {
          "selfOnly": 8500,
          "family": 17000
        }
      }
    }
  }
}
JSON;
/* </generated-hsa-parameters> */

    /* <generated-fsa-parameters> */
private const FSA_PARAMETER_JSON = <<<'JSON'
{
  "schemaVersion": 1,
  "package": "us-tax-advantaged-params",
  "generatedThroughTaxYear": 2026,
  "supportedTaxYears": {
    "minimum": 1982,
    "maximum": 2026
  },
  "moneyUnit": "USD",
  "historicalCoveragePolicy": {
    "description": "IRC 129(a)(2)(A) fixes the dependent care assistance exclusion, and IRC 125(i) fixes the health flexible spending arrangement salary-reduction limit. The table starts at 1987, the first taxable year for which Pub. L. 99-514 section 1163 supplied a dependent care dollar limitation, and is never extrapolated forward: a tax year with no published figure returns an unavailable or indeterminate status and a diagnostic rather than a projected amount.",
    "healthFsaFirstYear": "IRC 125(i) was added by the Patient Protection and Affordable Care Act, Pub. L. 111-148, section 9005, amended by section 10902 of that Act and by section 1403(b) of the Health Care and Education Reconciliation Act of 2010, Pub. L. 111-152. Notice 2012-40 reads its effective date as applying to plan years beginning after December 31, 2012. Before that there was no statutory salary-reduction ceiling at all, only whatever the plan document imposed, so healthFsa is null for 1987 through 2012 and the engine returns an indeterminate result rather than a fabricated ceiling.",
    "planYearVersusTaxYear": "Notice 2012-40 section III holds that the term \"taxable year\" in IRC 125(i) refers to the plan year of the cafeteria plan, so the statutory limit runs on a plan-year basis. Every annual revenue procedure nonetheless publishes the figure \"for taxable years beginning in\" the year, and this package is keyed by tax year throughout. Rows are therefore keyed by tax year and are exact for a calendar-year plan. For a non-calendar plan year the applicable figure depends on the plan year start date, which is a fact this engine does not hold, so it diagnoses rather than assuming.",
    "healthFsaCarryoverBelongsToSourceYear": "Notice 2013-71 section III created the carryover as a plan option at a fixed $500, and Notice 2020-33 section III.A raised it to 20 percent of the IRC 125(i) limit for the plan year and indexed it with that limit. Both phrase it as the maximum unused amount FROM a plan year carried to the immediately following plan year, so carryoverLimit belongs to the year the funds came from and never to the year they land in. Notice 2013-71 also holds that the carryover does not count against or otherwise affect the IRC 125(i) limit of the receiving year, and that a plan may offer a carryover or a grace period but not both.",
    "section214ReliefNotModelled": "Section 214 of the Consolidated Appropriations Act, 2021, Pub. L. 116-260, implemented by Notice 2021-15, permitted a plan to carry over ALL unused amounts from plan years ending in 2020 and in 2021, and permitted a dependent care carryover that is otherwise forbidden. It is entirely a plan option that the engine cannot read, so it is not modelled; a carryover computed out of a 2020 or 2021 plan year carries a diagnostic saying so rather than silently applying the ordinary cap.",
    "dependentCareNotIndexed": "The IRC 129(a)(2)(A) amounts are statutory and carry no inflation adjustment, which is why they never appear in the annual revenue procedures. They changed twice: Pub. L. 117-2 section 9632 substituted $10,500 (half that for a married separate return) for taxable years beginning after December 31, 2020 and before January 1, 2022, and Pub. L. 119-21 section 70404 substituted $7,500 ($3,750) for taxable years beginning after December 31, 2025. Each year is encoded as its own row so the 2021 increase and its reversion are both data rather than a rule."
  },
  "sources": [
    {
      "id": "usc-26-125",
      "title": "26 U.S.C. 125, Cafeteria plans (2024 edition of the United States Code)",
      "url": "https://www.govinfo.gov/content/pkg/USCODE-2024-title26/pdf/USCODE-2024-title26-subtitleA-chap1-subchapB-partIII-sec125.pdf",
      "authority": "U.S. Government Publishing Office"
    },
    {
      "id": "usc-26-129",
      "title": "26 U.S.C. 129, Dependent care assistance programs (2024 edition of the United States Code)",
      "url": "https://www.govinfo.gov/content/pkg/USCODE-2024-title26/pdf/USCODE-2024-title26-subtitleA-chap1-subchapB-partIII-sec129.pdf",
      "authority": "U.S. Government Publishing Office"
    },
    {
      "id": "pl-117-2",
      "title": "American Rescue Plan Act of 2021, Pub. L. 117-2, section 9632 (2021-only dependent care exclusion)",
      "url": "https://www.govinfo.gov/content/pkg/PLAW-117publ2/pdf/PLAW-117publ2.pdf",
      "authority": "U.S. Government Publishing Office"
    },
    {
      "id": "pl-119-21",
      "title": "Pub. L. 119-21, section 70404 (dependent care exclusion raised for taxable years beginning after 2025)",
      "url": "https://www.govinfo.gov/content/pkg/PLAW-119publ21/pdf/PLAW-119publ21.pdf",
      "authority": "U.S. Government Publishing Office"
    },
    {
      "id": "irs-notice-2012-40",
      "title": "Notice 2012-40, the $2,500 IRC 125(i) limit, its plan-year basis, and the per-employer rule",
      "url": "https://www.irs.gov/pub/irs-drop/n-12-40.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-notice-2013-71",
      "title": "Notice 2013-71, modification of the use-or-lose rule to permit a $500 health FSA carryover",
      "url": "https://www.irs.gov/pub/irs-drop/n-13-71.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-notice-2020-33",
      "title": "Notice 2020-33, carryover indexed at 20 percent of the IRC 125(i) limit",
      "url": "https://www.irs.gov/pub/irs-drop/n-20-33.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-notice-2005-42",
      "title": "Notice 2005-42, the grace period of up to two months and 15 days",
      "url": "https://www.irs.gov/pub/irs-drop/n-05-42.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-notice-2005-86",
      "title": "Notice 2005-86, health savings account eligibility during a cafeteria plan grace period",
      "url": "https://www.irs.gov/pub/irs-drop/n-05-86.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-notice-2021-15",
      "title": "Notice 2021-15, the Consolidated Appropriations Act, 2021 section 214 temporary carryover relief",
      "url": "https://www.irs.gov/pub/irs-drop/n-21-15.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-notice-2021-26",
      "title": "Notice 2021-26, the ARPA section 9632 dependent care increase and its interaction with section 214 carryovers",
      "url": "https://www.irs.gov/pub/irs-drop/n-21-26.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-rul-2004-45",
      "title": "Rev. Rul. 2004-45, general-purpose, limited-purpose, and post-deductible health FSAs under IRC 223(c)(1)(A)(ii)",
      "url": "https://www.irs.gov/pub/irs-drop/rr-04-45.pdf",
      "authority": "IRS"
    },
    {
      "id": "fr-72-43938",
      "title": "72 Fed. Reg. 43938 (Aug. 6, 2007), proposed regulations under IRC 125, including Prop. Treas. Reg. 1.125-1(e) and 1.125-5(b)",
      "url": "https://www.govinfo.gov/content/pkg/FR-2007-08-06/pdf/E7-14827.pdf",
      "authority": "U.S. Government Publishing Office"
    },
    {
      "id": "irs-rev-proc-2012-41",
      "title": "Internal Revenue Bulletin 2012-45, carrying Rev. Proc. 2012-41, whose 2013 adjusted items contain no Cafeteria Plans entry",
      "url": "https://www.irs.gov/pub/irs-irbs/irb12-45.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2013-35",
      "title": "Rev. Proc. 2013-35, section 3.15, 2014 cafeteria plan amount",
      "url": "https://www.irs.gov/pub/irs-drop/rp-13-35.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2014-61",
      "title": "Rev. Proc. 2014-61, section 3.16, 2015 cafeteria plan amount",
      "url": "https://www.irs.gov/pub/irs-drop/rp-14-61.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2015-53",
      "title": "Rev. Proc. 2015-53, section 3.16, 2016 cafeteria plan amount",
      "url": "https://www.irs.gov/pub/irs-drop/rp-15-53.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2016-55",
      "title": "Rev. Proc. 2016-55, section 3.16, 2017 cafeteria plan amount",
      "url": "https://www.irs.gov/pub/irs-drop/rp-16-55.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2017-58",
      "title": "Rev. Proc. 2017-58, section 3.16, 2018 cafeteria plan amount",
      "url": "https://www.irs.gov/pub/irs-drop/rp-17-58.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2018-18",
      "title": "Internal Revenue Bulletin 2018-10, carrying Rev. Proc. 2018-18, which reissued 2018 figures after the Tax Cuts and Jobs Act without touching section 3.16",
      "url": "https://www.irs.gov/pub/irs-irbs/irb18-10.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2018-57",
      "title": "Rev. Proc. 2018-57, section 3.17, 2019 cafeteria plan amount",
      "url": "https://www.irs.gov/pub/irs-drop/rp-18-57.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2019-44",
      "title": "Rev. Proc. 2019-44, section 3.17, 2020 cafeteria plan amount",
      "url": "https://www.irs.gov/pub/irs-drop/rp-19-44.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2020-45",
      "title": "Rev. Proc. 2020-45, section 3.17, 2021 cafeteria plan amount and maximum carryover",
      "url": "https://www.irs.gov/pub/irs-drop/rp-20-45.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2021-45",
      "title": "Rev. Proc. 2021-45, section 3.16, 2022 cafeteria plan amount and maximum carryover",
      "url": "https://www.irs.gov/pub/irs-drop/rp-21-45.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2022-38",
      "title": "Rev. Proc. 2022-38, section 3.16, 2023 cafeteria plan amount and maximum carryover",
      "url": "https://www.irs.gov/pub/irs-drop/rp-22-38.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2023-34",
      "title": "Rev. Proc. 2023-34, section 3.16, 2024 cafeteria plan amount and maximum carryover",
      "url": "https://www.irs.gov/pub/irs-drop/rp-23-34.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2024-40",
      "title": "Rev. Proc. 2024-40, section 3.16, 2025 cafeteria plan amount and maximum carryover",
      "url": "https://www.irs.gov/pub/irs-drop/rp-24-40.pdf",
      "authority": "IRS"
    },
    {
      "id": "irs-rev-proc-2025-32",
      "title": "Rev. Proc. 2025-32, section 3.15, 2026 cafeteria plan amount and maximum carryover",
      "url": "https://www.irs.gov/pub/irs-drop/rp-25-32.pdf",
      "authority": "IRS"
    },
    {
      "id": "pl-97-34",
      "title": "Economic Recovery Tax Act of 1981, Pub. L. 97-34, section 124 (adds IRC 129; section 124(f) effective for taxable years beginning after December 31, 1981)",
      "url": "https://www.govinfo.gov/content/pkg/STATUTE-95/pdf/STATUTE-95-Pg172.pdf",
      "authority": "U.S. Government Publishing Office",
      "retrieval": "linked, not committed: the Statutes at Large volume is ~30 MB. The same file is committed in evidence/retirement-limits/sources/statute-95-pg172.pdf and is hash-verified there, so the bytes are attested in this repository without a second copy."
    },
    {
      "id": "pl-99-514",
      "title": "Tax Reform Act of 1986, Pub. L. 99-514, section 1163(a) (adds the IRC 129(a)(2)(A) dollar limitation for taxable years beginning after December 31, 1986)",
      "url": "https://www.govinfo.gov/content/pkg/STATUTE-100/pdf/STATUTE-100-Pg2085.pdf",
      "authority": "U.S. Government Publishing Office",
      "retrieval": "linked, not committed: the Statutes at Large volume is ~138 MB, which is disproportionate to one effective-date sentence. The same effective date is carried by the amendment notes in the committed usc-26-129.pdf, which is what the corpus verifies against."
    }
  ],
  "years": {
    "1982": {
      "year": 1982,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "available_without_statutory_dollar_limit",
        "exclusionLimit": null,
        "marriedFilingSeparatelyExclusionLimit": null
      }
    },
    "1983": {
      "year": 1983,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "available_without_statutory_dollar_limit",
        "exclusionLimit": null,
        "marriedFilingSeparatelyExclusionLimit": null
      }
    },
    "1984": {
      "year": 1984,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "available_without_statutory_dollar_limit",
        "exclusionLimit": null,
        "marriedFilingSeparatelyExclusionLimit": null
      }
    },
    "1985": {
      "year": 1985,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "available_without_statutory_dollar_limit",
        "exclusionLimit": null,
        "marriedFilingSeparatelyExclusionLimit": null
      }
    },
    "1986": {
      "year": 1986,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "available_without_statutory_dollar_limit",
        "exclusionLimit": null,
        "marriedFilingSeparatelyExclusionLimit": null
      }
    },
    "1987": {
      "year": 1987,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "1988": {
      "year": 1988,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "1989": {
      "year": 1989,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "1990": {
      "year": 1990,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "1991": {
      "year": 1991,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "1992": {
      "year": 1992,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "1993": {
      "year": 1993,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "1994": {
      "year": 1994,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "1995": {
      "year": 1995,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "1996": {
      "year": 1996,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "1997": {
      "year": 1997,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "1998": {
      "year": 1998,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "1999": {
      "year": 1999,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2000": {
      "year": 2000,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2001": {
      "year": 2001,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2002": {
      "year": 2002,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2003": {
      "year": 2003,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2004": {
      "year": 2004,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2005": {
      "year": 2005,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2006": {
      "year": 2006,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2007": {
      "year": 2007,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2008": {
      "year": 2008,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2009": {
      "year": 2009,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2010": {
      "year": 2010,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2011": {
      "year": 2011,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2012": {
      "year": 2012,
      "healthFsa": {
        "state": "available_without_statutory_dollar_limit",
        "salaryReductionLimit": null,
        "carryoverLimit": null
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2013": {
      "year": 2013,
      "healthFsa": {
        "state": "statutory_dollar_limit",
        "salaryReductionLimit": 2500,
        "carryoverLimit": 500
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2014": {
      "year": 2014,
      "healthFsa": {
        "state": "statutory_dollar_limit",
        "salaryReductionLimit": 2500,
        "carryoverLimit": 500
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2015": {
      "year": 2015,
      "healthFsa": {
        "state": "statutory_dollar_limit",
        "salaryReductionLimit": 2550,
        "carryoverLimit": 500
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2016": {
      "year": 2016,
      "healthFsa": {
        "state": "statutory_dollar_limit",
        "salaryReductionLimit": 2550,
        "carryoverLimit": 500
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2017": {
      "year": 2017,
      "healthFsa": {
        "state": "statutory_dollar_limit",
        "salaryReductionLimit": 2600,
        "carryoverLimit": 500
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2018": {
      "year": 2018,
      "healthFsa": {
        "state": "statutory_dollar_limit",
        "salaryReductionLimit": 2650,
        "carryoverLimit": 500
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2019": {
      "year": 2019,
      "healthFsa": {
        "state": "statutory_dollar_limit",
        "salaryReductionLimit": 2700,
        "carryoverLimit": 500
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2020": {
      "year": 2020,
      "healthFsa": {
        "state": "statutory_dollar_limit",
        "salaryReductionLimit": 2750,
        "carryoverLimit": 550
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2021": {
      "year": 2021,
      "healthFsa": {
        "state": "statutory_dollar_limit",
        "salaryReductionLimit": 2750,
        "carryoverLimit": 550
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 10500,
        "marriedFilingSeparatelyExclusionLimit": 5250
      }
    },
    "2022": {
      "year": 2022,
      "healthFsa": {
        "state": "statutory_dollar_limit",
        "salaryReductionLimit": 2850,
        "carryoverLimit": 570
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2023": {
      "year": 2023,
      "healthFsa": {
        "state": "statutory_dollar_limit",
        "salaryReductionLimit": 3050,
        "carryoverLimit": 610
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2024": {
      "year": 2024,
      "healthFsa": {
        "state": "statutory_dollar_limit",
        "salaryReductionLimit": 3200,
        "carryoverLimit": 640
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2025": {
      "year": 2025,
      "healthFsa": {
        "state": "statutory_dollar_limit",
        "salaryReductionLimit": 3300,
        "carryoverLimit": 660
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 5000,
        "marriedFilingSeparatelyExclusionLimit": 2500
      }
    },
    "2026": {
      "year": 2026,
      "healthFsa": {
        "state": "statutory_dollar_limit",
        "salaryReductionLimit": 3400,
        "carryoverLimit": 680
      },
      "dependentCare": {
        "state": "statutory_dollar_limit",
        "exclusionLimit": 7500,
        "marriedFilingSeparatelyExclusionLimit": 3750
      }
    }
  },
  "dollarLimitStates": {
    "unavailable": "The program did not exist for the tax year. A year below supportedTaxYears.minimum is unavailable by absence: IRC 129 was added by Pub. L. 97-34 section 124 for taxable years beginning after December 31, 1981.",
    "available_without_statutory_dollar_limit": "The program existed and its exclusion applied, but no universal statutory dollar ceiling did. The statutory maximum is null; a maximum computed from caller-supplied plan terms or earned income is still a real answer and is reported as such.",
    "statutory_dollar_limit": "A published statutory dollar ceiling applies and is encoded."
  }
}
JSON;
/* </generated-fsa-parameters> */

    public static function forTaxYear(int $taxYear): ScenarioBuilder
    {
        return ScenarioBuilder::forTaxYear($taxYear);
    }

    /** @param array<string,mixed> $input
     *  @return array<string,mixed>
     */
    public static function calculate(array $input): array
    {
        return Engine::calculate($input, self::data(), self::hsaData(), self::fsaData());
    }

    /** @return array<string,mixed> */
    public static function parametersForYear(int $taxYear): array
    {
        $data = self::data();
        $minimum = (int) $data['supportedTaxYears']['minimum'];
        $maximum = (int) $data['supportedTaxYears']['maximum'];
        if ($taxYear < $minimum || $taxYear > $maximum || !isset($data['years'][(string) $taxYear])) {
            throw new UnsupportedTaxYearException($taxYear, $minimum, $maximum);
        }
        return Engine::copy($data['years'][(string) $taxYear]);
    }

    /** @return array{minimum:int,maximum:int} */
    public static function supportedTaxYears(): array
    {
        $supported = self::data()['supportedTaxYears'];
        return ['minimum' => (int) $supported['minimum'], 'maximum' => (int) $supported['maximum']];
    }

    public static function generatedThroughTaxYear(): int
    {
        return (int) self::data()['generatedThroughTaxYear'];
    }

    public static function normalizeFilingStatus(FilingStatus|string $status): string
    {
        $diagnostics = [];
        return Engine::parseFilingStatus($status, $diagnostics);
    }

    public static function normalizeAccountType(AccountType|string $type): string
    {
        return Engine::parseAccountType($type);
    }

    /** @return list<array<string,string>> */
    public static function sourceMetadata(): array
    {
        return Engine::copy(self::data()['sources']);
    }

    /** IRC 223 parameters, or null for a year with no encoded revenue procedure.
     *  @return array<string,mixed>|null
     */
    public static function hsaParametersForYear(int $taxYear): ?array
    {
        return Engine::hsaParametersForYear(self::hsaData(), $taxYear);
    }

    /** @return array{minimum:int,maximum:int} */
    public static function supportedHsaTaxYears(): array
    {
        $supported = self::hsaData()['supportedTaxYears'];
        return ['minimum' => (int) $supported['minimum'], 'maximum' => (int) $supported['maximum']];
    }

    /** @return list<array<string,string>> */
    public static function hsaSourceMetadata(): array
    {
        return Engine::copy(self::hsaData()['sources']);
    }

    /** IRC 125 and IRC 129 parameters, or null for a year with no encoded figures.
     *  @return array<string,mixed>|null
     */
    public static function fsaParametersForYear(int $taxYear): ?array
    {
        return Engine::fsaParametersForYear(self::fsaData(), $taxYear);
    }

    /** @return array{minimum:int,maximum:int} */
    public static function supportedFsaTaxYears(): array
    {
        $supported = self::fsaData()['supportedTaxYears'];
        return ['minimum' => (int) $supported['minimum'], 'maximum' => (int) $supported['maximum']];
    }

    /** @return list<array<string,string>> */
    public static function fsaSourceMetadata(): array
    {
        return Engine::copy(self::fsaData()['sources']);
    }

    /** @return array<string,mixed> */
    private static function data(): array
    {
        if (self::$parameters === null) {
            try {
                self::$parameters = json_decode(self::PARAMETER_JSON, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Embedded retirement parameter JSON is invalid.', 0, $exception);
            }
        }
        return self::$parameters;
    }

    /** @return array<string,mixed> */
    private static function hsaData(): array
    {
        if (self::$hsaParameters === null) {
            try {
                self::$hsaParameters = json_decode(self::HSA_PARAMETER_JSON, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Embedded HSA parameter JSON is invalid.', 0, $exception);
            }
        }
        return self::$hsaParameters;
    }

    /** @return array<string,mixed> */
    private static function fsaData(): array
    {
        if (self::$fsaParameters === null) {
            try {
                self::$fsaParameters = json_decode(self::FSA_PARAMETER_JSON, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Embedded flexible spending arrangement parameter JSON is invalid.', 0, $exception);
            }
        }
        return self::$fsaParameters;
    }
}

/** @internal */
final class Engine
{
    /** @param array<string,mixed> $input
     *  @param array<string,mixed> $data
     *  @return array<string,mixed>
     */
    public static function calculate(array $input, array $data, array $hsaData, array $fsaData): array
    {
        $scenarioDiagnostics = [];
        $rawTaxYear = $input['taxYear'] ?? null;
        if (!is_int($rawTaxYear) && !(is_float($rawTaxYear) && is_finite($rawTaxYear) && floor($rawTaxYear) === $rawTaxYear)) {
            throw new ParameterException('INVALID_TAX_YEAR', 'taxYear must be an integer.');
        }
        $taxYear = (int) $rawTaxYear;
        $minimum = (int) $data['supportedTaxYears']['minimum'];
        $maximum = (int) $data['supportedTaxYears']['maximum'];
        if ($taxYear < $minimum || $taxYear > $maximum || !isset($data['years'][(string) $taxYear])) {
            throw new UnsupportedTaxYearException($taxYear, $minimum, $maximum);
        }
        $parameters = self::copy($data['years'][(string) $taxYear]);
        $hsaParameters = self::hsaParametersForYear($hsaData, $taxYear);
        $fsaParameters = self::fsaParametersForYear($fsaData, $taxYear);
        $filingStatus = self::parseFilingStatus(
            $input['filingStatus'] ?? null,
            $scenarioDiagnostics,
            array_key_exists('filingStatus', $input),
        );
        self::booleanFlag($input, 'treatedAsUnmarriedUnderSection21e4', 'treatedAsUnmarriedUnderSection21e4');
        $treatedAsUnmarriedUnderSection21e4 = array_key_exists('treatedAsUnmarriedUnderSection21e4', $input)
            ? ($input['treatedAsUnmarriedUnderSection21e4'] === true)
            : null;
        $persons = self::normalizePersons($input['persons'] ?? null);
        $accounts = self::normalizeAccounts($input['accounts'] ?? null, $persons);
        $context = self::createContext(
            $taxYear,
            $filingStatus,
            $treatedAsUnmarriedUnderSection21e4,
            $parameters,
            $hsaParameters,
            $fsaParameters,
            $persons,
            $accounts,
            $scenarioDiagnostics,
            $data,
            $hsaData,
            $fsaData,
        );

        $allocationOrder = $accounts;
        usort(
            $allocationOrder,
            static fn (array $left, array $right): int =>
                (($left['priority'] ?? 100) <=> ($right['priority'] ?? 100))
                ?: ($left['inputIndex'] <=> $right['inputIndex']),
        );

        $byId = [];
        foreach ($allocationOrder as $account) {
            $outcome = self::allocateAccount($context, $account);
            $traits = self::traits($account['type']);
            $existingAnnual = self::sumComponents($account['existingContributions']);
            $annualMaximum = self::sumComponents($outcome['annualComponents']);
            $additionalMaximum = self::sumComponents($outcome['additionalComponents']);
            $excessContribution = $outcome['statutoryMaximum'] === null
                ? null
                : self::nonnegative($existingAnnual - (float) $outcome['statutoryMaximum']);
            $diagnostics = $outcome['diagnostics'];
            if (
                $outcome['statutoryMaximum'] !== null
                && $annualMaximum > (float) $outcome['statutoryMaximum'] + 0.009
            ) {
                $diagnostics[] = self::diagnostic(
                    'SUPPLIED_EXISTING_CONTRIBUTIONS_EXCEED_ACCOUNT_MAXIMUM',
                    DiagnosticSeverity::ERROR,
                    'The annual amount $' . self::localeNumber($annualMaximum) . ' exceeds the calculated account '
                        . 'ceiling of $' . self::localeNumber((float) $outcome['statutoryMaximum']) . '. Shared-limit '
                        . 'records should also be reviewed for excess contributions across accounts.',
                    "accounts.{$account['id']}.existingContributions",
                );
            }
            foreach ($outcome['sharedLimits'] as $shared) {
                // A pool whose draw is undeterminable reports no remainder, and an
                // excess is a statement about the draw. Asserting one from a used
                // the engine never computed is how a compliant taxpayer came to be
                // accused.
                if (
                    $shared['limit'] !== null
                    && $shared['usedBeforeAccount'] !== null
                    && $shared['remainingAfterAccount'] !== null
                    && $shared['usedBeforeAccount'] > $shared['limit'] + 0.009
                ) {
                    $diagnostics[] = self::diagnostic(
                        'SUPPLIED_EXISTING_CONTRIBUTIONS_EXCEED_SHARED_LIMIT',
                        DiagnosticSeverity::ERROR,
                        "Existing contributions already exceed the {$shared['legalLimit']}.",
                        "accounts.{$account['id']}.existingContributions",
                    );
                }
            }
            $finalStatus = $outcome['status'];
            if (self::hasError($diagnostics)
                && !in_array($finalStatus, [CalculationStatus::UNAVAILABLE->value, CalculationStatus::INELIGIBLE->value], true)
            ) {
                $finalStatus = CalculationStatus::INDETERMINATE->value;
            }
            $result = [
                'accountId' => $account['id'],
                'accountType' => $account['type'],
                'ownerId' => $account['ownerId'],
                'status' => $finalStatus,
                'statutoryMaximumAnnualContribution' => $outcome['statutoryMaximum'],
                'maximumAnnualContributionBasedOnInputs' =>
                    $outcome['status'] === CalculationStatus::INDETERMINATE->value && $annualMaximum === 0.0
                        ? null
                        : $annualMaximum,
                'maximumAdditionalContributionBasedOnInputs' =>
                    $outcome['status'] === CalculationStatus::INDETERMINATE->value && $additionalMaximum === 0.0
                        ? null
                        : $additionalMaximum,
                'existingAnnualContribution' => $existingAnnual,
                'excessContribution' => $excessContribution,
                'contributionComponents' => $outcome['annualComponents'],
                'planTermDependentCapacity' => $outcome['planTermDependentCapacity'],
                'federalTaxEffects' => self::accountTaxEffects(
                    $outcome,
                    $traits,
                    $account['planRules'],
                    $diagnostics,
                ),
                'sharedLimits' => $outcome['sharedLimits'],
                'diagnostics' => $diagnostics,
            ];
            if (isset($account['employerId'])) {
                $result['employerId'] = $account['employerId'];
            }
            if (isset($outcome['hsaDetail'])) {
                $result['hsa'] = $outcome['hsaDetail'];
            }
            if (isset($outcome['definedBenefitDetail'])) {
                $result['definedBenefit'] = $outcome['definedBenefitDetail'];
            }
            if (isset($outcome['healthFsaDetail'])) {
                $result['healthFsa'] = $outcome['healthFsaDetail'];
            }
            if (isset($outcome['dependentCareDetail'])) {
                $result['dependentCareFsa'] = $outcome['dependentCareDetail'];
            }
            $byId[$account['id']] = $result;
        }

        $accountResults = [];
        foreach ($accounts as $account) {
            $accountResults[] = $byId[$account['id']];
        }
        $conversions = self::normalizeConversions($input['conversions'] ?? null, $persons, $context['accountsById']);
        $conversionResults = self::calculateConversions($context, $conversions, $accountResults);
        $allDiagnostics = $scenarioDiagnostics;
        foreach ($accountResults as $accountResult) {
            array_push($allDiagnostics, ...$accountResult['diagnostics']);
        }
        foreach ($conversionResults as $conversionResult) {
            array_push($allDiagnostics, ...$conversionResult['diagnostics']);
        }

        return [
            'package' => USTaxAdvantagedParams::PACKAGE_NAME,
            'engineVersion' => USTaxAdvantagedParams::ENGINE_VERSION,
            'taxYear' => $taxYear,
            'filingStatus' => $filingStatus,
            'parameters' => $parameters,
            'hsaParameters' => $hsaParameters,
            'fsaParameters' => $fsaParameters,
            'accounts' => $accountResults,
            'conversions' => $conversionResults,
            'totals' => self::totals($accountResults, $conversionResults),
            'diagnostics' => $allDiagnostics,
        ];
    }

    /** @template T
     *  @param T $value
     *  @return T
     */
    public static function copy(mixed $value): mixed
    {
        try {
            return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to copy retirement-calculation input.', 0, $exception);
        }
    }

    /** @param list<array<string,mixed>> $diagnostics */
    public static function parseFilingStatus(mixed $value, array &$diagnostics, bool $present = true): string
    {
        if ($value instanceof FilingStatus) {
            return $value->value;
        }
        if (in_array($value, array_column(FilingStatus::cases(), 'value'), true)) {
            return $value;
        }
        if (!is_string($value)) {
            throw new ParameterException(
                'INVALID_FILING_STATUS',
                'Filing status must be a string, but received ' . self::describeInputValue($value, $present) . '.',
            );
        }
        $token = self::normalizeToken($value);
        $aliases = [
            'S' => FilingStatus::SINGLE->value,
            'SINGLE' => FilingStatus::SINGLE->value,
            'UNMARRIED' => FilingStatus::SINGLE->value,
            'M' => FilingStatus::MARRIED_FILING_JOINTLY->value,
            'MFJ' => FilingStatus::MARRIED_FILING_JOINTLY->value,
            'MARRIED' => FilingStatus::MARRIED_FILING_JOINTLY->value,
            'MARRIED_FILING_JOINTLY' => FilingStatus::MARRIED_FILING_JOINTLY->value,
            'JOINT' => FilingStatus::MARRIED_FILING_JOINTLY->value,
            'MFS' => FilingStatus::MARRIED_FILING_SEPARATELY->value,
            'MARRIED_FILING_SEPARATELY' => FilingStatus::MARRIED_FILING_SEPARATELY->value,
            'SEPARATE' => FilingStatus::MARRIED_FILING_SEPARATELY->value,
            'HOH' => FilingStatus::HEAD_OF_HOUSEHOLD->value,
            'HEAD_OF_HOUSEHOLD' => FilingStatus::HEAD_OF_HOUSEHOLD->value,
            'QSS' => FilingStatus::QUALIFYING_SURVIVING_SPOUSE->value,
            'QW' => FilingStatus::QUALIFYING_SURVIVING_SPOUSE->value,
            'QUALIFYING_WIDOW' => FilingStatus::QUALIFYING_SURVIVING_SPOUSE->value,
            'QUALIFYING_WIDOWER' => FilingStatus::QUALIFYING_SURVIVING_SPOUSE->value,
            'QUALIFYING_SURVIVING_SPOUSE' => FilingStatus::QUALIFYING_SURVIVING_SPOUSE->value,
        ];
        if (!isset($aliases[$token])) {
            throw new ParameterException('INVALID_FILING_STATUS', "Unsupported filing status: {$value}");
        }
        if ($token === 'M') {
            $diagnostics[] = self::diagnostic(
                'AMBIGUOUS_M_ALIAS_ASSUMED_MFJ',
                DiagnosticSeverity::WARNING,
                'Filing-status alias "M" was interpreted as married filing jointly. Use MFJ or MFS to be explicit.',
                'filingStatus',
            );
        }
        return $aliases[$token];
    }

    public static function parseAccountType(mixed $value, bool $present = true): string
    {
        if ($value instanceof AccountType) {
            return $value->value;
        }
        if (in_array($value, array_column(AccountType::cases(), 'value'), true)) {
            return $value;
        }
        if (!is_string($value)) {
            throw new ParameterException(
                'INVALID_ACCOUNT_TYPE',
                'Account type must be a string, but received ' . self::describeInputValue($value, $present) . '.',
            );
        }
        $aliases = [
            'IRA' => AccountType::TRADITIONAL_IRA->value,
            'TRADITIONAL_IRA' => AccountType::TRADITIONAL_IRA->value,
            'ROTH_IRA' => AccountType::ROTH_IRA->value,
            'ROLLOVER_IRA' => AccountType::ROLLOVER_IRA->value,
            'PAYROLL_DEDUCTION_IRA' => AccountType::PAYROLL_DEDUCTION_IRA->value,
            'DEEMED_IRA' => AccountType::DEEMED_TRADITIONAL_IRA->value,
            'DEEMED_TRADITIONAL_IRA' => AccountType::DEEMED_TRADITIONAL_IRA->value,
            'DEEMED_ROTH_IRA' => AccountType::DEEMED_ROTH_IRA->value,
            'INHERITED_IRA' => AccountType::INHERITED_TRADITIONAL_IRA->value,
            'INHERITED_TRADITIONAL_IRA' => AccountType::INHERITED_TRADITIONAL_IRA->value,
            'INHERITED_ROTH_IRA' => AccountType::INHERITED_ROTH_IRA->value,
            'SEP' => AccountType::SEP_IRA->value,
            'SEP_IRA' => AccountType::SEP_IRA->value,
            'ROTH_SEP' => AccountType::ROTH_SEP_IRA->value,
            'ROTH_SEP_IRA' => AccountType::ROTH_SEP_IRA->value,
            'SIMPLE' => AccountType::SIMPLE_IRA->value,
            'SIMPLE_IRA' => AccountType::SIMPLE_IRA->value,
            'ROTH_SIMPLE' => AccountType::ROTH_SIMPLE_IRA->value,
            'ROTH_SIMPLE_IRA' => AccountType::ROTH_SIMPLE_IRA->value,
            'SARSEP' => AccountType::SARSEP_IRA->value,
            'SARSEP_IRA' => AccountType::SARSEP_IRA->value,
            '401K' => AccountType::TRADITIONAL_401K->value,
            'TRADITIONAL_401K' => AccountType::TRADITIONAL_401K->value,
            'ROTH_401K' => AccountType::ROTH_401K->value,
            'SOLO_401K' => AccountType::SOLO_401K->value,
            'INDIVIDUAL_401K' => AccountType::SOLO_401K->value,
            'ROTH_SOLO_401K' => AccountType::ROTH_SOLO_401K->value,
            'SIMPLE_401K' => AccountType::SIMPLE_401K->value,
            'ROTH_SIMPLE_401K' => AccountType::ROTH_SIMPLE_401K->value,
            'STARTER_401K' => AccountType::STARTER_401K->value,
            'PLESA' => AccountType::PENSION_LINKED_EMERGENCY_SAVINGS->value,
            'GOVERNMENTAL_457B_PLESA' => AccountType::GOVERNMENTAL_457B_PENSION_LINKED_EMERGENCY_SAVINGS->value,
            'PLESA_457B' => AccountType::GOVERNMENTAL_457B_PENSION_LINKED_EMERGENCY_SAVINGS->value,
            '457B_PLESA' => AccountType::GOVERNMENTAL_457B_PENSION_LINKED_EMERGENCY_SAVINGS->value,
            'PENSION_LINKED_EMERGENCY_SAVINGS' => AccountType::PENSION_LINKED_EMERGENCY_SAVINGS->value,
            'PENSION_LINKED_EMERGENCY_SAVINGS_ACCOUNT' => AccountType::PENSION_LINKED_EMERGENCY_SAVINGS->value,
            '403B' => AccountType::TRADITIONAL_403B->value,
            'TRADITIONAL_403B' => AccountType::TRADITIONAL_403B->value,
            'ROTH_403B' => AccountType::ROTH_403B->value,
            'SAFE_HARBOR_403B_DEFERRAL_ONLY' => AccountType::SAFE_HARBOR_403B_DEFERRAL_ONLY->value,
            '457' => AccountType::GOVERNMENTAL_457B->value,
            '457B' => AccountType::GOVERNMENTAL_457B->value,
            'GOVERNMENTAL_457B' => AccountType::GOVERNMENTAL_457B->value,
            'ROTH_GOVERNMENTAL_457B' => AccountType::ROTH_GOVERNMENTAL_457B->value,
            'NONGOVERNMENTAL_457B' => AccountType::NONGOVERNMENTAL_457B->value,
            '457F' => AccountType::SECTION_457F->value,
            'SECTION_457F' => AccountType::SECTION_457F->value,
            'TSP' => AccountType::TRADITIONAL_TSP->value,
            'TRADITIONAL_TSP' => AccountType::TRADITIONAL_TSP->value,
            'ROTH_TSP' => AccountType::ROTH_TSP->value,
            '401A' => AccountType::SECTION_401A->value,
            'SECTION_401A' => AccountType::SECTION_401A->value,
            'PROFIT_SHARING' => AccountType::PROFIT_SHARING_PLAN->value,
            'PROFIT_SHARING_PLAN' => AccountType::PROFIT_SHARING_PLAN->value,
            'MONEY_PURCHASE' => AccountType::MONEY_PURCHASE_PLAN->value,
            'MONEY_PURCHASE_PLAN' => AccountType::MONEY_PURCHASE_PLAN->value,
            'KEOGH' => AccountType::KEOGH_PLAN->value,
            'KEOGH_PLAN' => AccountType::KEOGH_PLAN->value,
            'ESOP' => AccountType::ESOP->value,
            'DB' => AccountType::DEFINED_BENEFIT_PLAN->value,
            'PENSION' => AccountType::DEFINED_BENEFIT_PLAN->value,
            'DEFINED_BENEFIT' => AccountType::DEFINED_BENEFIT_PLAN->value,
            'DEFINED_BENEFIT_PLAN' => AccountType::DEFINED_BENEFIT_PLAN->value,
            'CASH_BALANCE' => AccountType::CASH_BALANCE_PLAN->value,
            'CASH_BALANCE_PLAN' => AccountType::CASH_BALANCE_PLAN->value,
            'HSA' => AccountType::HSA->value,
            'HEALTH_SAVINGS_ACCOUNT' => AccountType::HSA->value,
            'SECTION_223' => AccountType::HSA->value,
            // A bare "FSA" is deliberately absent. It names a health FSA and a
            // dependent care FSA equally well, and the two are different
            // accounts under different Code sections with different limits and
            // different household aggregation, so aliasing it would silently
            // pick one. It falls through to INVALID_ACCOUNT_TYPE, which names
            // both spellings in the message.
            'HEALTH_FSA' => AccountType::HEALTH_FSA->value,
            'HEALTHCARE_FSA' => AccountType::HEALTH_FSA->value,
            'HEALTH_CARE_FSA' => AccountType::HEALTH_FSA->value,
            'MEDICAL_FSA' => AccountType::HEALTH_FSA->value,
            'HEALTH_FLEXIBLE_SPENDING_ARRANGEMENT' => AccountType::HEALTH_FSA->value,
            'SECTION_125_HEALTH_FSA' => AccountType::HEALTH_FSA->value,
            'DEPENDENT_CARE_FSA' => AccountType::DEPENDENT_CARE_FSA->value,
            'DEPENDENT_CARE_ACCOUNT' => AccountType::DEPENDENT_CARE_FSA->value,
            'DEPENDENT_CARE_ASSISTANCE' => AccountType::DEPENDENT_CARE_FSA->value,
            'DEPENDENT_CARE_ASSISTANCE_PROGRAM' => AccountType::DEPENDENT_CARE_FSA->value,
            'DCAP' => AccountType::DEPENDENT_CARE_FSA->value,
            'DCFSA' => AccountType::DEPENDENT_CARE_FSA->value,
            'SECTION_129' => AccountType::DEPENDENT_CARE_FSA->value,
        ];
        $token = self::normalizeToken($value);
        if (!isset($aliases[$token])) {
            // "FSA" alone is ambiguous rather than unknown: it names a health
            // FSA under IRC 125(i) and a dependent care FSA under IRC 129
            // equally well, and those carry different limits and different
            // household aggregation.
            if (in_array($token, ['FSA', 'FLEXIBLE_SPENDING_ARRANGEMENT', 'FLEXIBLE_SPENDING_ACCOUNT'], true)) {
                throw new ParameterException(
                    'INVALID_ACCOUNT_TYPE',
                    "Ambiguous account type: {$value}. Use \"health_fsa\" for an IRC 125(i) health flexible spending "
                        . 'arrangement or "dependent_care_fsa" for an IRC 129 dependent care assistance program.',
                );
            }
            throw new ParameterException('INVALID_ACCOUNT_TYPE', "Unsupported retirement account type: {$value}");
        }
        return $aliases[$token];
    }

    public static function parseConversionType(mixed $value, bool $present = true): string
    {
        if ($value instanceof ConversionType) {
            return $value->value;
        }
        if (in_array($value, array_column(ConversionType::cases(), 'value'), true)) {
            return $value;
        }
        if (!is_string($value)) {
            throw new ParameterException(
                'INVALID_CONVERSION_TYPE',
                'Roth conversion type must be a string, but received '
                    . self::describeInputValue($value, $present) . '.',
            );
        }
        $aliases = [
            'IRA_TO_ROTH' => ConversionType::IRA_TO_ROTH_IRA->value,
            'IRA_TO_ROTH_IRA' => ConversionType::IRA_TO_ROTH_IRA->value,
            'ROTH_CONVERSION' => ConversionType::IRA_TO_ROTH_IRA->value,
            'QUALIFIED_PLAN_TO_ROTH_IRA' => ConversionType::QUALIFIED_PLAN_TO_ROTH_IRA->value,
            'PLAN_TO_ROTH_IRA' => ConversionType::QUALIFIED_PLAN_TO_ROTH_IRA->value,
            'IN_PLAN_ROTH_ROLLOVER' => ConversionType::IN_PLAN_ROTH_ROLLOVER->value,
            'IN_PLAN_ROTH_CONVERSION' => ConversionType::IN_PLAN_ROTH_ROLLOVER->value,
        ];
        $token = self::normalizeToken($value);
        if (!isset($aliases[$token])) {
            throw new ParameterException('INVALID_CONVERSION_TYPE', "Unsupported Roth conversion type: {$value}");
        }
        return $aliases[$token];
    }

    /**
     * A language-neutral description of a value's shape, so both engines word the
     * same rejection identically. Arrays and objects collapse into one token
     * because a JSON {} and a JSON [] are indistinguishable once decoded here,
     * and a message that depended on telling them apart could not be matched.
     */
    private static function describeInputValue(mixed $value, bool $present = true): string
    {
        if (!$present) {
            return 'no value';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return 'a boolean';
        }
        if (is_int($value) || is_float($value)) {
            return 'a number';
        }
        if (is_string($value)) {
            return 'a string';
        }
        return 'a structured value';
    }

    /** The value as a JSON list, or null when it is not one.
     *  @return list<mixed>|null
     */
    private static function toInputList(mixed $value): ?array
    {
        return is_array($value) && array_is_list($value) ? $value : null;
    }

    /** A non-empty string after trimming, or null. Used for every caller-supplied identifier. */
    private static function trimmedIdentifier(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Optional identifier fields must be non-empty strings, for the same reason flag
     * fields must be actual booleans: JavaScript and PHP disagree about "0", about 0,
     * and about "", so a coerced identifier makes the answer depend on the runtime
     * rather than on the input.
     *
     * employerId is the one that costs money. It selects the prior-year FICA wage
     * figure for the IRC 414(v)(7)(A) test, so a value one runtime reads as present
     * and the other as absent is the difference between a classified catch-up and an
     * indeterminate account. A numeric 0 did exactly that. Absent stays absent --
     * a missing key and null both mean the caller supplied nothing -- but anything
     * else present must be a usable identifier rather than something to be coerced
     * into one.
     *
     * @param array<string,mixed> $container
     */
    private static function optionalIdentifier(
        array $container,
        string $key,
        string $path,
        string $code,
    ): ?string {
        if (!array_key_exists($key, $container) || $container[$key] === null) {
            return null;
        }
        $value = $container[$key];
        if (!is_string($value) || $value === '') {
            throw new ParameterException($code, "{$path} must be a non-empty string when supplied.");
        }
        return $value;
    }

    /**
     * Flag fields must be actual booleans. JavaScript and PHP disagree about the
     * truthiness of "0" and of an empty array, so coercing one would make the
     * answer depend on the runtime rather than on the input.
     *
     * @param array<string,mixed> $container
     */
    private static function booleanFlag(array $container, string $key, string $path): void
    {
        if (!array_key_exists($key, $container)) {
            return;
        }
        if (!is_bool($container[$key])) {
            throw new ParameterException('INVALID_BOOLEAN', "{$path} must be a boolean.");
        }
    }

    /** Structured input fields must be objects; a scalar in their place is silently ignored otherwise.
     *  @param array<string,mixed> $container
     */
    private static function requireInputObject(array $container, string $key, string $path): void
    {
        if (!array_key_exists($key, $container)) {
            return;
        }
        if (!is_array($container[$key])) {
            throw new ParameterException('INVALID_INPUT_OBJECT', "{$path} must be an object.");
        }
    }

    private static function normalizeToken(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = str_replace(['(', ')'], '', $value);
        $value = (string) preg_replace('/[\-.\/\s]+/', '_', $value);
        $value = (string) preg_replace('/_+/', '_', $value);
        return trim($value, '_');
    }

    /** @return array<string,mixed> */
    private static function traits(string $type): array
    {
        $base = [
            'family' => '',
            'availabilityKey' => null,
            'designatedRoth' => false,
            'shares402g' => false,
            'uses415c' => false,
            'permitsAgeCatchUpByStatute' => false,
            'governmental457' => false,
            'is403b' => false,
            'isStarter' => false,
            'isPlesa' => false,
            'isSimple' => false,
            'isSarsep' => false,
            'employerOnly' => false,
        ];
        $traditionalIras = [
            AccountType::TRADITIONAL_IRA->value,
            AccountType::ROLLOVER_IRA->value,
            AccountType::PAYROLL_DEDUCTION_IRA->value,
            AccountType::DEEMED_TRADITIONAL_IRA->value,
        ];
        if (in_array($type, $traditionalIras, true)) {
            return array_replace($base, ['family' => 'regular_traditional_ira', 'availabilityKey' => 'traditionalIra']);
        }
        if (in_array($type, [AccountType::ROTH_IRA->value, AccountType::DEEMED_ROTH_IRA->value], true)) {
            return array_replace($base, ['family' => 'regular_roth_ira', 'availabilityKey' => 'rothIra', 'designatedRoth' => true]);
        }
        if (in_array($type, [AccountType::INHERITED_TRADITIONAL_IRA->value, AccountType::INHERITED_ROTH_IRA->value], true)) {
            $roth = $type === AccountType::INHERITED_ROTH_IRA->value;
            return array_replace($base, [
                'family' => 'inherited_ira',
                'availabilityKey' => $roth ? 'rothIra' : 'traditionalIra',
                'designatedRoth' => $roth,
            ]);
        }
        if (in_array($type, [AccountType::SEP_IRA->value, AccountType::ROTH_SEP_IRA->value], true)) {
            $roth = $type === AccountType::ROTH_SEP_IRA->value;
            return array_replace($base, [
                'family' => 'sep',
                'availabilityKey' => $roth ? 'rothSimpleOrSep' : 'sepIra',
                'designatedRoth' => $roth,
                'uses415c' => true,
                'employerOnly' => true,
            ]);
        }
        if (in_array($type, [AccountType::SIMPLE_IRA->value, AccountType::ROTH_SIMPLE_IRA->value], true)) {
            $roth = $type === AccountType::ROTH_SIMPLE_IRA->value;
            return array_replace($base, [
                'family' => 'simple',
                'availabilityKey' => $roth ? 'rothSimpleOrSep' : 'simpleIra',
                'designatedRoth' => $roth,
                'shares402g' => true,
                'permitsAgeCatchUpByStatute' => true,
                'isSimple' => true,
            ]);
        }
        if ($type === AccountType::SARSEP_IRA->value) {
            return array_replace($base, [
                'family' => 'qualified_elective',
                'availabilityKey' => 'sepIra',
                'shares402g' => true,
                'uses415c' => true,
                'permitsAgeCatchUpByStatute' => true,
                'isSarsep' => true,
            ]);
        }
        $qualified = [
            AccountType::TRADITIONAL_401K->value => ['traditional401k', false],
            AccountType::ROTH_401K->value => ['designatedRoth401k', true],
            AccountType::SOLO_401K->value => ['traditional401k', false],
            AccountType::ROTH_SOLO_401K->value => ['designatedRoth401k', true],
            AccountType::TRADITIONAL_TSP->value => ['traditionalTsp', false],
            AccountType::ROTH_TSP->value => ['rothTsp', true],
            AccountType::TRADITIONAL_403B->value => ['traditional403b', false],
            AccountType::ROTH_403B->value => ['designatedRoth403b', true],
        ];
        if (isset($qualified[$type])) {
            [$key, $roth] = $qualified[$type];
            return array_replace($base, [
                'family' => 'qualified_elective',
                'availabilityKey' => $key,
                'designatedRoth' => $roth,
                'shares402g' => true,
                'uses415c' => true,
                'permitsAgeCatchUpByStatute' => true,
                'is403b' => in_array($type, [AccountType::TRADITIONAL_403B->value, AccountType::ROTH_403B->value], true),
            ]);
        }
        if (in_array($type, [AccountType::SIMPLE_401K->value, AccountType::ROTH_SIMPLE_401K->value], true)) {
            return array_replace($base, [
                'family' => 'qualified_elective',
                'availabilityKey' => $type === AccountType::ROTH_SIMPLE_401K->value ? 'designatedRoth401k' : 'traditional401k',
                'designatedRoth' => $type === AccountType::ROTH_SIMPLE_401K->value,
                'shares402g' => true,
                'uses415c' => true,
                'permitsAgeCatchUpByStatute' => true,
                'isSimple' => true,
            ]);
        }
        if (in_array($type, [AccountType::STARTER_401K->value, AccountType::SAFE_HARBOR_403B_DEFERRAL_ONLY->value], true)) {
            return array_replace($base, [
                'family' => 'qualified_elective',
                'availabilityKey' => 'starter401kOrSafeHarbor403b',
                'shares402g' => true,
                'uses415c' => true,
                'permitsAgeCatchUpByStatute' => true,
                'isStarter' => true,
                'is403b' => $type === AccountType::SAFE_HARBOR_403B_DEFERRAL_ONLY->value,
            ]);
        }
        // IRC 402A(e)(1)(A)(i) treats a pension-linked emergency savings account as
        // a designated Roth account for purposes of this title, so its contributions
        // are elective deferrals sharing the IRC 402(g) limit — IRC 402A(e)(9)
        // confirms it by ordering excess deferrals distributed under IRC 402(g)(2)(A)
        // out of the emergency account first — and annual additions under IRC 415(c).
        // IRC 402A(e)(3)(A) bars a contribution only "to the extent such
        // contribution would cause the portion of the account balance attributable
        // to participant contributions to exceed" the cap: a gate on a balance,
        // indifferent to how the contribution is characterised. IRC 414(v) relieves
        // a *deferral* limit at the plan, and 26 CFR 1.414(v)-1(b)(1)(i) lists the
        // limits it relieves — IRC 401(a)(30), 402(h), 403(b), 408, 415(c) and
        // 457(b)(2) — every one a plan- or employee-level limit and none of them
        // account-level. The two compose, and both still bind: a contribution past
        // an applicable host limit may be treated as a catch-up under
        // 26 CFR 1.414(v)-1(a)(1), which requires that the plan so treat it, while
        // the balance cap continues to bar anything above the account-local room.
        //
        // As everywhere else in this engine, capacity is derived from age and this
        // trait rather than from a plan-document catch-up election, so this states
        // that the statute permits it, under the package-wide assumption that the
        // represented plan permits and characterises what is modelled. A PLESA
        // contribution does not become a catch-up merely because the host's base
        // pool is exhausted.
        if ($type === AccountType::PENSION_LINKED_EMERGENCY_SAVINGS->value) {
            return array_replace($base, [
                'family' => 'qualified_elective',
                'availabilityKey' => 'pensionLinkedEmergencySavings',
                'designatedRoth' => true,
                'shares402g' => true,
                'uses415c' => true,
                'isPlesa' => true,
                'permitsAgeCatchUpByStatute' => true,
            ]);
        }
        // IRC 402A(f)(1)(C) makes an eligible deferred compensation plan (as defined
        // in IRC 457(b)) of an eligible employer described in IRC 457(e)(1)(A) the
        // third applicable retirement plan that may include a pension-linked
        // emergency savings account, and IRC 402A(f)(2)(B) reads "elective deferral"
        // for this purpose to include a deferral under such a plan.
        //
        // Two limits that reach the other two hosts do not reach this one, which is
        // why it is a separate account type rather than another permitted plan type
        // on the existing one:
        //
        // - IRC 402(g)(3) enumerates elective deferrals exhaustively — IRC 401(k)
        //   arrangements, IRC 402(h)(1)(B), IRC 403(b) salary-reduction annuities and
        //   IRC 408(p)(2)(A)(i) — and no IRC 457(b) deferral appears among them, so
        //   these contributions are outside IRC 402(g)(1) and run instead against the
        //   IRC 457(e)(15) applicable dollar amount that IRC 457(b)(2)(A) imposes.
        //   IRC 402A(e)(9), which orders excess deferrals distributed under
        //   IRC 402(g)(2)(A) out of the emergency account first, is therefore not the
        //   authority it is for the other hosts and is deliberately not restated here.
        // - IRC 415(a)(1) reaches a trust that is part of a pension, profitsharing or
        //   stock bonus plan and IRC 415(a)(2) extends it to IRC 403(a) annuity plans,
        //   IRC 403(b) annuity contracts and IRC 408(k) simplified employee pensions.
        //   An IRC 457(b) plan appears in neither, so there is no annual-additions
        //   group for this account to join.
        //
        // Both catch-ups this host offers compose with the balance cap, for the reason
        // the other hosts' catch-up does. IRC 414(v)(6)(A)(ii) makes an IRC 457(b) plan
        // of an IRC 457(e)(1)(A) employer an applicable employer plan, and
        // 26 CFR 1.414(v)-1(b)(1)(i) lists IRC 457(b)(2) among the limits the catch-up
        // relieves; the IRC 457(b)(3) last-three-years catch-up likewise raises "the
        // ceiling set forth in paragraph (2)". Every one of those is a limit on
        // deferrals under the plan. IRC 402A(e)(3)(A) is not: it bars a contribution
        // that would carry the participant-contribution portion of the *account
        // balance* past the cap, whatever the contribution is called. So the two bind
        // together rather than one displacing the other, and the room is drawn by base
        // deferrals and by whichever catch-up applies alike.
        //
        // Which catch-up that is remains the existing IRC 457(e)(18) question, and
        // IRC 414(v)(6)(C) states it from the other side: this plan is not an
        // applicable employer plan for a year in which a higher limitation applies
        // under IRC 457(b)(3). The two are alternatives, never a sum.
        if ($type === AccountType::GOVERNMENTAL_457B_PENSION_LINKED_EMERGENCY_SAVINGS->value) {
            return array_replace($base, [
                'family' => 'section457',
                'availabilityKey' => 'pensionLinkedEmergencySavings',
                'designatedRoth' => true,
                'governmental457' => true,
                'isPlesa' => true,
                'permitsAgeCatchUpByStatute' => true,
            ]);
        }
        if (in_array($type, [AccountType::GOVERNMENTAL_457B->value, AccountType::ROTH_GOVERNMENTAL_457B->value, AccountType::NONGOVERNMENTAL_457B->value], true)) {
            $governmental = $type !== AccountType::NONGOVERNMENTAL_457B->value;
            return array_replace($base, [
                'family' => 'section457',
                'availabilityKey' => $governmental ? 'governmental457b' : 'nongovernmental457b',
                'designatedRoth' => $type === AccountType::ROTH_GOVERNMENTAL_457B->value,
                'permitsAgeCatchUpByStatute' => $governmental,
                'governmental457' => $governmental,
            ]);
        }
        if ($type === AccountType::SECTION_457F->value) {
            return array_replace($base, ['family' => 'section457f']);
        }
        if (in_array($type, [
            AccountType::SECTION_401A->value,
            AccountType::PROFIT_SHARING_PLAN->value,
            AccountType::MONEY_PURCHASE_PLAN->value,
            AccountType::KEOGH_PLAN->value,
            AccountType::ESOP->value,
        ], true)) {
            return array_replace($base, ['family' => 'annual_additions_only', 'uses415c' => true, 'employerOnly' => true]);
        }
        if (in_array($type, [AccountType::DEFINED_BENEFIT_PLAN->value, AccountType::CASH_BALANCE_PLAN->value], true)) {
            return array_replace($base, ['family' => 'defined_benefit', 'employerOnly' => true]);
        }
        if ($type === AccountType::HSA->value) {
            return array_replace($base, ['family' => 'hsa']);
        }
        if ($type === AccountType::HEALTH_FSA->value) {
            return array_replace($base, ['family' => 'health_fsa']);
        }
        if ($type === AccountType::DEPENDENT_CARE_FSA->value) {
            return array_replace($base, ['family' => 'dependent_care_fsa']);
        }
        throw new ParameterException('INVALID_ACCOUNT_TYPE', "Unsupported retirement account type: {$type}");
    }

    /** @return array<string,mixed> */
    private static function diagnostic(
        string $code,
        DiagnosticSeverity $severity,
        string $message,
        ?string $path = null,
        ?string $legalReference = null,
    ): array {
        $result = ['code' => $code, 'severity' => $severity->value, 'message' => $message];
        if ($path !== null) {
            $result['path'] = $path;
        }
        if ($legalReference !== null) {
            $result['legalReference'] = $legalReference;
        }
        return $result;
    }

    /** @param list<array<string,mixed>> $diagnostics */
    private static function hasError(array $diagnostics): bool
    {
        foreach ($diagnostics as $diagnostic) {
            if (($diagnostic['severity'] ?? null) === DiagnosticSeverity::ERROR->value) {
                return true;
            }
        }
        return false;
    }

    private static function money(mixed $value, string $path, float $default = 0.0): float
    {
        if ($value === null) {
            return $default;
        }
        if (!is_int($value) && !is_float($value)) {
            throw new ParameterException('INVALID_MONEY', "{$path} must be a finite, nonnegative number.");
        }
        $number = (float) $value;
        if (!is_finite($number) || $number < 0) {
            throw new ParameterException('INVALID_MONEY', "{$path} must be a finite, nonnegative number.");
        }
        return self::roundMoney($number);
    }

    private static function rate(mixed $value, string $path, float $default = 0.0): float
    {
        if ($value === null) {
            return $default;
        }
        if (!is_int($value) && !is_float($value)) {
            throw new ParameterException('INVALID_RATE', "{$path} must be a number from 0 through 1.");
        }
        $number = (float) $value;
        if (!is_finite($number) || $number < 0 || $number > 1) {
            throw new ParameterException('INVALID_RATE', "{$path} must be a number from 0 through 1.");
        }
        return $number;
    }

    private static function roundMoney(float $value): float
    {
        /*
         * Mirrors the TypeScript Math.round((value + Number.EPSILON) * 100) / 100
         * exactly. PHP's round($value, 2) rounds the decimal value of the double
         * and diverges from the JavaScript expression on third-decimal ties
         * (for example 14643.575), so the scaling and the round-half-up toward
         * positive infinity are reproduced operation for operation.
         */
        $scaled = ($value + PHP_FLOAT_EPSILON) * 100;
        $floor = floor($scaled);
        return ($scaled - $floor >= 0.5 ? $floor + 1.0 : $floor) / 100;
    }

    private static function floorMoney(float $value): float
    {
        return floor(($value + PHP_FLOAT_EPSILON) * 100) / 100;
    }

    private static function nonnegative(float $value): float
    {
        return self::roundMoney(max(0.0, $value));
    }

    private static function minMoney(float|int|null ...$values): float
    {
        $finite = [];
        foreach ($values as $value) {
            if ($value !== null) {
                $finite[] = (float) $value;
            }
        }
        return $finite === [] ? 0.0 : min($finite);
    }

    /** @return array<string,float> */
    private static function zeroComponents(): array
    {
        return [
            'employeePreTaxDeferral' => 0.0,
            'employeeRothDeferral' => 0.0,
            'employeePreTaxCatchUp' => 0.0,
            'employeeRothCatchUp' => 0.0,
            'employeeAfterTax' => 0.0,
            'employerPreTax' => 0.0,
            'employerRoth' => 0.0,
            'deductibleIra' => 0.0,
            'nondeductibleIra' => 0.0,
            'rothIra' => 0.0,
            'special403bCatchUp' => 0.0,
            'special457CatchUp' => 0.0,
            'special457RothCatchUp' => 0.0,
            'unclassifiedIra' => 0.0,
            'hsaDeductible' => 0.0,
            'hsaEmployerOrCafeteria' => 0.0,
            'healthFsaSalaryReduction' => 0.0,
            'dependentCareAssistanceProvided' => 0.0,
            'dependentCareIncludibleInIncome' => 0.0,
        ];
    }

    /** @param array<string,mixed> $source
     *  @return array<string,float>
     */
    private static function components(array $source = []): array
    {
        $result = self::zeroComponents();
        foreach (array_keys($result) as $key) {
            // Both are derived splits computed by the engine rather than
            // supplied inputs, in the same way unclassifiedIra is.
            if ($key === 'unclassifiedIra' || $key === 'dependentCareIncludibleInIncome') {
                continue;
            }
            $result[$key] = self::money($source[$key] ?? null, "existing.{$key}");
        }
        return $result;
    }

    /** @param array<string,float> $components */
    private static function sumComponents(array $components): float
    {
        return self::roundMoney(array_sum($components));
    }

    /**
     * The diagnostic that records a caller-supplied tax-treatment election which
     * IRC 402A(e)(1)(A)(i) does not leave open on a pension-linked emergency
     * savings account. The election is disregarded rather than honoured, because
     * honouring it would allocate a pre-tax contribution to an account the statute
     * treats as a designated Roth account; saying so keeps the disregard visible to
     * a caller who supplied the field believing it would take effect.
     *
     * @param array<string,mixed> $account
     * @return array<string,mixed>|null
     */
    private static function pensionLinkedEmergencySavingsRothTreatmentDiagnostic(array $account): ?array
    {
        $rules = $account['planRules'];
        $stated = null;
        if (($rules['contributionPreference'] ?? null) === 'pretax_first') {
            $stated = 'planRules.contributionPreference is pretax_first';
        } elseif (($rules['permitsRothContributions'] ?? null) === false) {
            $stated = 'planRules.permitsRothContributions is false';
        } elseif (($rules['permitsRothCatchUp'] ?? null) === false) {
            $stated = 'planRules.permitsRothCatchUp is false';
        }
        if ($stated === null) {
            return null;
        }

        return self::diagnostic(
            'PENSION_LINKED_EMERGENCY_SAVINGS_CONTRIBUTIONS_ARE_ALWAYS_ROTH',
            DiagnosticSeverity::INFO,
            $stated . ', but IRC 402A(e)(1)(A)(i) treats a pension-linked emergency savings account as a '
                . 'designated Roth account for purposes of the whole title, so every participant contribution '
                . 'to it is a designated Roth contribution. The supplied election was disregarded; no amount '
                . 'was allocated as a pre-tax contribution.',
            "accounts.{$account['id']}.planRules",
            'IRC 402A(e)(1)(A)(i)',
        );
    }

    /**
     * The one diagnostic that says an age-based workplace catch-up cannot be
     * sized because the participant's age is unknown. Shared, because more than
     * one allocation path has to be able to raise it.
     *
     * @return array<string,mixed>
     */
    private static function workplaceCatchUpAgeDiagnostic(string $personId): array
    {
        return self::diagnostic(
            'BIRTH_YEAR_OR_DATE_REQUIRED_FOR_WORKPLACE_CATCH_UP',
            DiagnosticSeverity::ERROR,
            'Birth year or birth date is required to determine the maximum age-based workplace catch-up contribution.',
            "persons.{$personId}",
        );
    }

    /** @return array<string,mixed> */
    private static function section457UnreconciledCatchUpDiagnostic(string $accountId): array
    {
        return self::diagnostic(
            'SECTION_457_CATCH_UP_ALLOCATION_BLOCKED_BY_UNRECONCILED_EXISTING_CONTRIBUTIONS',
            DiagnosticSeverity::ERROR,
            "No further IRC 457 catch-up is allocated while this participant's existing catch-up contributions cannot be reconciled to one permitted method and its participant-wide limit. Review the catch-up components on every IRC 457 account before relying on this account's remaining capacity.",
            "accounts.{$accountId}.existingContributions",
            '26 CFR 1.457-5(a); 26 CFR 1.457-5(b)',
        );
    }

    /** @return array<string,mixed> */
    private static function section457MutuallyExclusiveCatchUpDiagnostic(
        string $accountId,
        float $existingAgeCatchUp,
        float $existingSpecialCatchUp,
    ): array {
        return self::diagnostic(
            'SECTION_457_CATCH_UP_METHODS_ARE_MUTUALLY_EXCLUSIVE',
            DiagnosticSeverity::ERROR,
            'Existing contributions record $'
                . self::localeNumber($existingAgeCatchUp)
                . ' of IRC 414(v) age-based catch-up and $'
                . self::localeNumber($existingSpecialCatchUp)
                . ' of IRC 457(b)(3) special catch-up for this participant. 26 CFR 1.457-5(a)'
                . ' states the individual limitation as the basic annual limitation plus either'
                . ' the age 50 catch-up or the special 457 catch-up, taking into account the'
                . ' combined annual deferral under all eligible plans, and 1.457-5(b) applies it'
                . ' on an aggregate basis across every employer; IRC 457(e)(18) likewise gives'
                . ' the greater of the two methods and never their sum. Record each existing'
                . ' contribution under the single method actually used.',
            "accounts.{$accountId}.existingContributions",
            '26 CFR 1.457-5(a); IRC 457(e)(18)',
        );
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $account
     * @param array<string,mixed> $traits
     * @param array<string,mixed> $resolution
     * @param array<string,float> $ceilings
     * @param list<array<string,mixed>> $diagnostics
     */
    private static function appendSection457ExistingCatchUpDiagnostics(
        array $context,
        array $account,
        array $traits,
        array $resolution,
        array $ceilings,
        array &$diagnostics,
    ): bool {
        $diagnosticCountBefore = count($diagnostics);
        $accountExistingAgeCatchUp = self::ageCatchUps($account['existingContributions']);
        $accountExistingSpecialCatchUp = self::roundMoney(
            $account['existingContributions']['special457CatchUp']
                + $account['existingContributions']['special457RothCatchUp'],
        );
        $selectedExistingCatchUp = match ($resolution['mode']) {
            'age' => $resolution['existingAgeCatchUp'],
            'special' => $resolution['existingSpecialCatchUp'],
            default => 0.0,
        };
        $unselectedExistingCatchUp = self::roundMoney(
            $resolution['existingAgeCatchUp'] + $resolution['existingSpecialCatchUp']
                - $selectedExistingCatchUp,
        );
        $accountSelectedExistingCatchUp = match ($resolution['mode']) {
            'age' => $accountExistingAgeCatchUp,
            'special' => $accountExistingSpecialCatchUp,
            default => 0.0,
        };
        $accountUnselectedExistingCatchUp = self::roundMoney(
            $accountExistingAgeCatchUp + $accountExistingSpecialCatchUp
                - $accountSelectedExistingCatchUp,
        );
        $selectedMethodName = $resolution['mode'] === 'age'
            ? 'IRC 414(v) age-based'
            : 'IRC 457(b)(3) special';

        if (
            $resolution['existingAgeCatchUp'] > 0.0
            && $resolution['existingSpecialCatchUp'] > 0.0
            && ($accountExistingAgeCatchUp > 0.0 || $accountExistingSpecialCatchUp > 0.0)
        ) {
            $diagnostics[] = self::section457MutuallyExclusiveCatchUpDiagnostic(
                $account['id'],
                $resolution['existingAgeCatchUp'],
                $resolution['existingSpecialCatchUp'],
            );
        } elseif (
            $resolution['mode'] !== 'indeterminate'
            && $unselectedExistingCatchUp > 0.0
            && $accountUnselectedExistingCatchUp > 0.0
        ) {
            $diagnostics[] = self::diagnostic(
                'SECTION_457_CATCH_UP_RECORDED_UNDER_UNSELECTED_METHOD',
                DiagnosticSeverity::ERROR,
                $resolution['mode'] === 'none'
                    ? 'Existing contributions record $'
                        . self::localeNumber($unselectedExistingCatchUp)
                        . ' of IRC 457 catch-up, but no catch-up method applies to this participant'
                        . " for {$context['taxYear']}: no plan supplied provides the IRC 457(b)(3)"
                        . ' catch-up, and no eligible governmental plan offers an IRC 414(v) amount'
                        . " the participant's age and compensation reach. Record the contribution"
                        . ' under the limitation it was actually made under, or supply the facts'
                        . ' that make a method apply.'
                    : 'Existing contributions record $'
                        . self::localeNumber($unselectedExistingCatchUp)
                        . ' of catch-up under the method that does not apply. 26 CFR'
                        . " 1.457-4(c)(2)(ii) selects the {$selectedMethodName} catch-up for this"
                        . ' participant, and makes that a determination rather than an election: the'
                        . ' age 50 catch-up "does not apply for any taxable year for which a higher'
                        . ' limitation applies" under the special 457 catch-up, and IRC 414(v)(6)(C)'
                        . ' states the same rule from the other side. Record the contribution under'
                        . ' the method actually used, or supply the plan facts that make the other'
                        . ' one apply.',
                "accounts.{$account['id']}.existingContributions",
                '26 CFR 1.457-4(c)(2)(ii); IRC 414(v)(6)(C)',
            );
        }
        if (
            $resolution['mode'] !== 'indeterminate'
            && $selectedExistingCatchUp > $resolution['headroom']
            && $accountSelectedExistingCatchUp > 0.0
        ) {
            $diagnostics[] = self::diagnostic(
                'SECTION_457_EXISTING_CATCH_UP_EXCEEDS_PARTICIPANT_LIMIT',
                DiagnosticSeverity::ERROR,
                'Existing contributions record $'
                    . self::localeNumber($selectedExistingCatchUp)
                    . " of {$selectedMethodName} catch-up across this participant's IRC 457 plans,"
                    . ' against the $'
                    . self::localeNumber($resolution['headroom'])
                    . ' that 26 CFR 1.457-5(a) allows above the basic annual limitation. 1.457-5(b)'
                    . ' determines the amounts "on an aggregate basis" across the eligible plans of'
                    . ' every employer, so the excess is the participant\'s even where no single'
                    . ' plan exceeds its own ceiling.',
                "accounts.{$account['id']}.existingContributions",
                '26 CFR 1.457-5(a); 26 CFR 1.457-5(b)',
            );
        }
        if (
            $accountExistingAgeCatchUp > 0.0
            && !(!empty($traits['governmental457']) && !empty($traits['permitsAgeCatchUpByStatute']))
        ) {
            $diagnostics[] = self::diagnostic(
                'SECTION_457_AGE_CATCH_UP_NOT_AVAILABLE_ON_PLAN',
                DiagnosticSeverity::ERROR,
                'Existing contributions record $'
                    . self::localeNumber($accountExistingAgeCatchUp)
                    . ' of IRC 414(v) age-based catch-up on this account, but IRC 414(v)(6)(A)(ii)'
                    . ' makes only an eligible governmental IRC 457(b) plan an applicable employer'
                    . ' plan, so this plan cannot host one. Record the contribution under the'
                    . ' limitation it was actually made under.',
                "accounts.{$account['id']}.existingContributions",
                'IRC 414(v)(6)(A)(ii)',
            );
        }
        $accountProvidesSpecialCatchUp = is_array($account['planRules']['section457SpecialCatchUp'] ?? null)
            && !empty($account['planRules']['section457SpecialCatchUp']['eligible']);
        if ($accountExistingSpecialCatchUp > 0.0 && !$accountProvidesSpecialCatchUp) {
            $diagnostics[] = self::diagnostic(
                'SECTION_457_SPECIAL_CATCH_UP_NOT_PROVIDED_BY_PLAN',
                DiagnosticSeverity::ERROR,
                'Existing contributions record $'
                    . self::localeNumber($accountExistingSpecialCatchUp)
                    . ' of IRC 457(b)(3) special catch-up on this account, but no such plan'
                    . ' provision was supplied for it. 26 CFR 1.457-5(c) counts the special'
                    . ' catch-up only to the extent an annual deferral is made "as a result of plan'
                    . ' provisions permitted under Sec. 1.457-4(c)(3)". Supply'
                    . ' planRules.section457SpecialCatchUp for this plan, or record the contribution'
                    . ' under the limitation it was actually made under.',
                "accounts.{$account['id']}.existingContributions",
                '26 CFR 1.457-5(c)',
            );
        } elseif ($accountExistingSpecialCatchUp > $ceilings['specialAdditional']) {
            $diagnostics[] = self::diagnostic(
                'SECTION_457_SPECIAL_CATCH_UP_EXCEEDS_PLAN_AMOUNT',
                DiagnosticSeverity::ERROR,
                'Existing contributions record $'
                    . self::localeNumber($accountExistingSpecialCatchUp)
                    . ' of IRC 457(b)(3) special catch-up on this account, above the $'
                    . self::localeNumber($ceilings['specialAdditional'])
                    . ' its own plan ceiling provides above the basic annual limitation. 26 CFR'
                    . ' 1.457-4(c)(3)(i) caps that ceiling at the lesser of twice the IRC 457(e)(15)'
                    . ' amount and the (c)(3)(ii) underutilized limitation, and 1.457-5(c)'
                    . ' recognises the amount only under the plan whose provisions produce it.',
                "accounts.{$account['id']}.existingContributions",
                '26 CFR 1.457-4(c)(3)(i); 26 CFR 1.457-5(c)',
            );
        }

        return count($diagnostics) > $diagnosticCountBefore;
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $account
     * @param array<string,mixed> $resolution
     * @param array<string,float> $ceilings
     * @param array<string,mixed> $basePool
     * @param array<string,mixed> $catchUpPool
     */
    private static function section457PlesaCatchUpCapacityUpperBoundBeforeBalance(
        array $context,
        array $account,
        array $resolution,
        array $ceilings,
        array $basePool,
        array $catchUpPool,
    ): float {
        if (!isset($resolution['eligibleAccountIds'][$account['id']])) {
            return 0.0;
        }
        $statutoryPlesaCap = $context['parameters']['pensionLinkedEmergencySavingsBalanceCap402A'];
        if ($statutoryPlesaCap === null) {
            return 0.0;
        }
        $sponsorCap = $account['planRules']['planDocumentEmployeeDeferralLimit'] ?? null;
        $effectivePlesaCap = $sponsorCap === null
            ? (float) $statutoryPlesaCap
            : self::minMoney(
                (float) $statutoryPlesaCap,
                self::money($sponsorCap, "{$account['id']}.planDocumentEmployeeDeferralLimit"),
            );
        $existing = $account['existingContributions'];
        $regularBeforeEmployee = self::roundMoney(
            self::baseDeferrals($existing)
                + $existing['employeeAfterTax']
                + $existing['employerPreTax']
                + $existing['employerRoth'],
        );
        $baseCapacity = self::minMoney(
            self::poolRemaining($basePool),
            self::nonnegative($ceilings['basicPlanCeiling'] - $regularBeforeEmployee),
            $effectivePlesaCap,
        );
        $plesaRoomAfterBase = self::nonnegative($effectivePlesaCap - $baseCapacity);
        $compensationBeforeBase = self::nonnegative(
            $ceilings['includibleCompensation']
                - self::baseDeferrals($existing)
                - self::ageCatchUps($existing)
                - $existing['special457CatchUp']
                - $existing['special457RothCatchUp']
                - $existing['employerPreTax']
                - $existing['employerRoth'],
        );
        $compensationAfterBase = self::nonnegative($compensationBeforeBase - $baseCapacity);
        $accountExistingCatchUp = match ($resolution['mode']) {
            'age' => self::ageCatchUps($existing),
            'special' => self::roundMoney(
                $existing['special457CatchUp'] + $existing['special457RothCatchUp'],
            ),
            default => 0.0,
        };
        $accountCatchUpCeiling = match ($resolution['mode']) {
            'age' => $ceilings['ageAdditional'],
            'special' => $ceilings['specialAdditional'],
            default => 0.0,
        };

        return self::minMoney(
            self::poolRemaining($catchUpPool),
            $compensationAfterBase,
            self::nonnegative($accountCatchUpCeiling - $accountExistingCatchUp),
            $plesaRoomAfterBase,
        );
    }

    /** @param array<string,float> $components */
    private static function baseDeferrals(array $components): float
    {
        return self::roundMoney($components['employeePreTaxDeferral'] + $components['employeeRothDeferral']);
    }

    /** @param array<string,float> $components */
    private static function ageCatchUps(array $components): float
    {
        return self::roundMoney($components['employeePreTaxCatchUp'] + $components['employeeRothCatchUp']);
    }

    /** @param array<string,float> $components */
    private static function annualAdditions(array $components): float
    {
        return self::roundMoney(
            $components['employeePreTaxDeferral']
            + $components['employeeRothDeferral']
            + $components['employeeAfterTax']
            + $components['employerPreTax']
            + $components['employerRoth']
            + $components['special403bCatchUp'],
        );
    }

    /** @return array<string,mixed> */
    private static function zeroTaxEffects(): array
    {
        return [
            'federalAgiReduction' => 0.0,
            'federalAgiIncrease' => 0.0,
            'federalTaxableIncomeReduction' => 0.0,
            'formW2Box1WageReduction' => 0.0,
            'ficaWageReduction' => 0.0,
            'selfEmployedRetirementDeduction' => 0.0,
            'nondeductibleContribution' => 0.0,
            'afterTaxOrRothContribution' => 0.0,
            'taxableRothConversion' => 0.0,
            'notes' => [],
        ];
    }

    /** @param array<string,float> $components
     *  @param array<string,mixed> $traits
     *  @param array<string,mixed> $planRules
     *  @return array<string,mixed>
     */
    /**
     * The components record what the scenario supplied, which is a fact even
     * when the account cannot be sized. The tax treatment of those amounts is
     * not always a fact, and two cases are settled enough to report rather than
     * assume.
     *
     * An `unavailable` account is one whose type did not exist for the tax
     * year, so a contribution to it cannot carry that type's exclusion or
     * deduction. IRC 223 was added for taxable years beginning after 2003, so a
     * 2003 cafeteria-plan HSA contribution is not an IRC 106(d) exclusion no
     * matter what it is called.
     *
     * An election above the IRC 125(i) limit costs the plan its IRC 125 status
     * entirely under Notice 2012-40 section III, not merely as to the excess,
     * so the IRC 125(a) exclusion fails for the whole health FSA salary
     * reduction.
     *
     * `indeterminate` on its own is deliberately not in this list. An unknown
     * IRC 415(c) limit does not undo a pre-tax deferral's effect on AGI, and a
     * pre-2013 health FSA excluded salary reductions under IRC 125(a) perfectly
     * well while having no IRC 125(i) ceiling to report.
     */
    private static function accountTaxEffects(
        array $outcome,
        array $traits,
        array $planRules,
        array $diagnostics,
    ): array {
        if ($outcome['status'] === CalculationStatus::UNAVAILABLE->value) {
            $suppressed = self::zeroTaxEffects();
            $suppressed['notes'][] = 'This account type did not exist for the tax year, so no exclusion or '
                . 'deduction of its kind is reported for the amounts supplied. The amounts themselves remain '
                . 'in contributionComponents.';

            return $suppressed;
        }
        $section125QualificationFailed = false;
        foreach ($diagnostics as $entry) {
            if (($entry['code'] ?? null) === 'HEALTH_FSA_ELECTION_EXCEEDS_SECTION_125I_LIMIT') {
                $section125QualificationFailed = true;
                break;
            }
        }
        if (!$section125QualificationFailed) {
            return self::contributionTaxEffects($outcome['annualComponents'], $traits, $planRules);
        }
        $withoutHealthFsa = $outcome['annualComponents'];
        $withoutHealthFsa['healthFsaSalaryReduction'] = 0.0;
        $effects = self::contributionTaxEffects($withoutHealthFsa, $traits, $planRules);
        $effects['notes'][] = 'IRC 125(i) makes a health flexible spending arrangement a qualified benefit only '
            . 'if the plan provides that an employee may not elect salary reduction contributions above the '
            . 'limit. Notice 2012-40 section III holds that a plan failing to comply is not an IRC 125 cafeteria '
            . 'plan at all, so the IRC 125(a) exclusion fails for the entire salary reduction rather than for the '
            . 'excess alone. No wage exclusion is reported for it. The election remains in contributionComponents, '
            . 'and this engine does not extend the consequence to other arrangements that may be under the same '
            . 'cafeteria plan.';

        return $effects;
    }

    private static function contributionTaxEffects(array $components, array $traits, array $planRules): array
    {
        $result = self::zeroTaxEffects();
        $pretaxEmployee = self::roundMoney(
            $components['employeePreTaxDeferral']
            + $components['employeePreTaxCatchUp']
            + $components['special403bCatchUp']
            + $components['special457CatchUp'],
        );
        $deductibleIra = $components['deductibleIra'];
        $selfEmployedPlanDeduction = !empty($planRules['isSelfEmployedOwner'])
            ? self::roundMoney($pretaxEmployee + $components['employerPreTax'])
            : 0.0;
        $selfEmployedEmployer = !empty($planRules['isSelfEmployedOwner']) ? $components['employerPreTax'] : 0.0;
        $hsaDeduction = $components['hsaDeductible'];
        $hsaExclusion = $components['hsaEmployerOrCafeteria'];
        // IRC 125(a) keeps a salary reduction contribution out of gross income
        // entirely, so it is an exclusion and never an above-the-line
        // deduction. It therefore follows the IRC 106(d) path exactly: out of
        // Form W-2 box 1 and out of social security and medicare wages, and
        // absent from federalAgiReduction, because the money was never included
        // rather than reduced.
        $cafeteriaExclusion = self::roundMoney(
            $components['healthFsaSalaryReduction'] + $components['dependentCareAssistanceProvided'],
        );
        $result['formW2Box1WageReduction'] = self::roundMoney(
            (!empty($planRules['isSelfEmployedOwner']) ? 0.0 : $pretaxEmployee) + $hsaExclusion + $cafeteriaExclusion,
        );
        $result['selfEmployedRetirementDeduction'] = $selfEmployedPlanDeduction;
        $result['federalAgiReduction'] = self::roundMoney(
            $pretaxEmployee + $selfEmployedEmployer + $deductibleIra + $hsaDeduction,
        );
        $result['federalTaxableIncomeReduction'] = $result['federalAgiReduction'];
        $result['ficaWageReduction'] = self::roundMoney($hsaExclusion + $cafeteriaExclusion);
        $result['nondeductibleContribution'] = self::roundMoney(
            $components['nondeductibleIra'] + $components['unclassifiedIra'],
        );
        $result['afterTaxOrRothContribution'] = self::roundMoney(
            $components['employeeRothDeferral']
            + $components['employeeRothCatchUp']
            + $components['employeeAfterTax']
            + $components['employerRoth']
            + $components['rothIra']
            + $components['special457RothCatchUp'],
        );
        if ($pretaxEmployee > 0 && empty($planRules['isSelfEmployedOwner'])) {
            $result['notes'][] = 'Pre-tax salary deferrals generally reduce Form W-2 box 1 wages but not Social Security or Medicare wages.';
        }
        if ($traits['family'] === 'regular_traditional_ira' && $deductibleIra > 0) {
            $result['notes'][] = 'A deductible traditional IRA contribution is an above-the-line federal adjustment to income.';
        }
        if ($components['employerRoth'] > 0) {
            $result['federalAgiIncrease'] = self::roundMoney($result['federalAgiIncrease'] + $components['employerRoth']);
            $result['notes'][] = 'A designated Roth employer contribution is generally included in current federal taxable income.';
        }
        if ($result['afterTaxOrRothContribution'] > 0) {
            $result['notes'][] = 'Roth and voluntary after-tax contributions do not reduce current federal AGI.';
        }
        if ($hsaDeduction > 0) {
            $result['notes'][] = 'An HSA contribution made by the account beneficiary is an above-the-line federal deduction under IRC 223(a).';
        }
        if ($hsaExclusion > 0) {
            $result['notes'][] = 'Employer and cafeteria-plan HSA contributions are excluded from gross income under IRC 106(d) '
                . 'and are outside Form W-2 box 1 and Social Security and Medicare wages (Notice 2004-2 A-19). They were never '
                . 'included rather than reduced, and they reduce the IRC 223(a) deduction under IRC 223(b)(4)(B).';
        }
        if ($components['healthFsaSalaryReduction'] > 0) {
            $result['notes'][] = 'Health flexible spending arrangement salary reduction contributions are excluded from '
                . 'gross income under IRC 125(a) and are outside Form W-2 box 1 and Social Security and Medicare wages '
                . '(IRC 3121(a)(5)(G)). They are an exclusion rather than a deduction - the money never entered gross '
                . 'income - so they do not appear in federalAgiReduction.';
        }
        if ($components['dependentCareAssistanceProvided'] > 0) {
            $result['notes'][] = 'Dependent care assistance within the IRC 129(a)(2) limitation is excluded from gross '
                . 'income under IRC 129(a)(1) and is outside Form W-2 box 1 and Social Security and Medicare wages '
                . '(IRC 3121(a)(18)); it is reported in Form W-2 box 10. It is an exclusion rather than a deduction, '
                . 'so it does not appear in federalAgiReduction.';
        }
        if ($components['dependentCareIncludibleInIncome'] > 0) {
            $result['notes'][] = 'Dependent care assistance above the IRC 129(a)(2) limitation is included in gross '
                . 'income under IRC 129(a)(2)(B) in the taxable year the services were provided, so only the '
                . 'excludable part reduces Form W-2 box 1 and Social Security and Medicare wages.';
        }
        return $result;
    }

    /** @return array<string,array<string,mixed>> */
    private static function normalizePersons(mixed $personsInput): array
    {
        $persons = self::toInputList($personsInput);
        if ($persons === null || $persons === []) {
            throw new ParameterException('PERSON_REQUIRED', 'At least one person is required.');
        }
        $result = [];
        foreach ($persons as $index => $input) {
            if (!is_array($input)) {
                throw new ParameterException('INVALID_PERSON', "persons[{$index}] must be an object/associative array.");
            }
            $id = self::trimmedIdentifier($input['id'] ?? null);
            if ($id === null) {
                throw new ParameterException('PERSON_ID_REQUIRED', "persons[{$index}].id is required.");
            }
            if (isset($result[$id])) {
                throw new ParameterException('DUPLICATE_PERSON_ID', "Duplicate person ID: {$id}");
            }
            if (array_key_exists('birthYear', $input)) {
                $birthYear = $input['birthYear'];
                if (!is_int($birthYear) || $birthYear < 1800 || $birthYear > 3000) {
                    throw new ParameterException('INVALID_BIRTH_YEAR', "persons[{$index}].birthYear is invalid.");
                }
            }
            if (isset($input['birthDate'])) {
                self::validateIsoDate((string) $input['birthDate'], "persons[{$index}].birthDate");
            }
            self::requireInputObject($input, 'compensation', "persons[{$index}].compensation");
            self::requireInputObject($input, 'magi', "persons[{$index}].magi");
            self::requireInputObject(
                $input,
                'priorYearFicaWagesByEmployer',
                "persons[{$index}].priorYearFicaWagesByEmployer",
            );
            $compensation = is_array($input['compensation'] ?? null) ? $input['compensation'] : [];
            foreach (['iraCompensation', 'w2Compensation', 'selfEmploymentNetEarnings'] as $key) {
                if (array_key_exists($key, $compensation)) {
                    $compensation[$key] = self::money($compensation[$key], "persons[{$index}].compensation.{$key}");
                }
            }
            $magi = is_array($input['magi'] ?? null) ? $input['magi'] : [];
            foreach (['rothIra', 'traditionalIraDeduction', 'rothConversion'] as $key) {
                if (array_key_exists($key, $magi)) {
                    $magi[$key] = self::money($magi[$key], "persons[{$index}].magi.{$key}");
                }
            }
            $wages = [];
            foreach (($input['priorYearFicaWagesByEmployer'] ?? []) as $employerId => $amount) {
                $wages[(string) $employerId] = self::money(
                    $amount,
                    "persons[{$index}].priorYearFicaWagesByEmployer.{$employerId}",
                );
            }
            self::requireInputObject($input, 'hsaCoverage', "persons[{$index}].hsaCoverage");
            if (array_key_exists('hsaCoverage', $input)) {
                self::validateHsaCoverage($input['hsaCoverage'], "persons[{$index}].hsaCoverage");
            }
            self::booleanFlag(
                $input,
                'coveredByEmployerRetirementPlan',
                "persons[{$index}].coveredByEmployerRetirementPlan",
            );
            self::booleanFlag($input, 'livedWithSpouseDuringYear', "persons[{$index}].livedWithSpouseDuringYear");
            self::booleanFlag(
                $input,
                'isStudentOrIncapableOfSelfCare',
                "persons[{$index}].isStudentOrIncapableOfSelfCare",
            );
            self::money($input['dependentCareEarnedIncome'] ?? null, "persons[{$index}].dependentCareEarnedIncome");
            $role = $input['role'] ?? ($index === 0 ? 'taxpayer' : ($index === 1 ? 'spouse' : 'other'));
            if (!in_array($role, ['taxpayer', 'spouse', 'other'], true)) {
                throw new ParameterException(
                    'INVALID_PERSON_ROLE',
                    "persons[{$index}].role must be taxpayer, spouse, or other.",
                );
            }
            $normalized = $input;
            $normalized['id'] = $id;
            $normalized['role'] = $role;
            $normalized['compensation'] = $compensation;
            $normalized['magi'] = $magi;
            $normalized['priorYearFicaWagesByEmployer'] = $wages;
            foreach (
                [
                    'traditionalSepSimpleIraBasis',
                    'yearEndTraditionalSepSimpleIraValue',
                    'otherTraditionalSepSimpleIraDistributions',
                    'archerMsaContributions',
                    'qualifiedHsaFundingDistributions',
                ]
                as $key
            ) {
                if (array_key_exists($key, $input)) {
                    $normalized[$key] = self::money($input[$key], "persons[{$index}].{$key}");
                }
            }
            $result[$id] = $normalized;
        }
        foreach (['taxpayer', 'spouse'] as $role) {
            $matching = array_values(array_filter(
                $result,
                static fn (array $person): bool => ($person['role'] ?? null) === $role,
            ));
            if (count($matching) > 1) {
                $ids = implode(', ', array_column($matching, 'id'));
                throw new ParameterException(
                    'DUPLICATE_PERSON_ROLE',
                    "Only one person may have the {$role} role; found {$ids}.",
                );
            }
        }
        return $result;
    }

    /** @param list<array<string,mixed>> $accounts
     *  @param array<string,array<string,mixed>> $persons
     *  @return list<array<string,mixed>>
     */
    private static function normalizeAccounts(mixed $accountsInput, array $persons): array
    {
        $accounts = $accountsInput === null ? [] : self::toInputList($accountsInput);
        if ($accounts === null) {
            throw new ParameterException('INVALID_ACCOUNTS', 'accounts must be an array.');
        }
        $ids = [];
        $result = [];
        foreach ($accounts as $index => $input) {
            if (!is_array($input)) {
                throw new ParameterException('INVALID_ACCOUNT', "accounts[{$index}] must be an object/associative array.");
            }
            $id = self::trimmedIdentifier($input['id'] ?? null);
            if ($id === null) {
                throw new ParameterException('ACCOUNT_ID_REQUIRED', "accounts[{$index}].id is required.");
            }
            if (isset($ids[$id])) {
                throw new ParameterException('DUPLICATE_ACCOUNT_ID', "Duplicate account ID: {$id}");
            }
            $ids[$id] = true;
            $ownerId = self::trimmedIdentifier($input['ownerId'] ?? null);
            if ($ownerId === null) {
                throw new ParameterException('ACCOUNT_OWNER_REQUIRED', "accounts[{$index}].ownerId is required.");
            }
            if (!isset($persons[$ownerId])) {
                throw new ParameterException(
                    'UNKNOWN_ACCOUNT_OWNER',
                    "Account {$id} references unknown owner {$ownerId}.",
                );
            }
            self::requireInputObject($input, 'planRules', "accounts[{$index}].planRules");
            self::requireInputObject($input, 'existingContributions', "accounts[{$index}].existingContributions");
            $planRules = is_array($input['planRules'] ?? null) ? $input['planRules'] : [];
            self::validatePlanRules($planRules, "accounts[{$index}].planRules");
            $employerId = self::optionalIdentifier(
                $input,
                'employerId',
                "accounts[{$index}].employerId",
                'INVALID_EMPLOYER_ID',
            );
            $normalized = $input;
            $normalized['id'] = $id;
            $normalized['ownerId'] = $ownerId;
            if ($employerId === null) {
                unset($normalized['employerId']);
            } else {
                $normalized['employerId'] = $employerId;
            }
            $normalized['type'] = self::parseAccountType(
                $input['type'] ?? null,
                array_key_exists('type', $input),
            );
            $normalized['priority'] = isset($input['priority']) ? (int) $input['priority'] : 100;
            $normalized['planRules'] = $planRules;
            $normalized['existingContributions'] = self::components(
                is_array($input['existingContributions'] ?? null) ? $input['existingContributions'] : [],
            );
            $normalized['inputIndex'] = $index;
            $result[] = $normalized;
        }
        return $result;
    }

    /** @param array<string,mixed> $rules */
    private static function validatePlanRules(array $rules, string $path): void
    {
        self::optionalIdentifier(
            $rules,
            'annualAdditionsGroupId',
            "{$path}.annualAdditionsGroupId",
            'INVALID_ANNUAL_ADDITIONS_GROUP_ID',
        );
        foreach (
            [
                'planCompensation',
                'includibleCompensation457',
                'planDocumentEmployeeDeferralLimit',
                'planDocumentAnnualAdditionsLimit',
                'expectedEmployerContribution',
                'simpleCustomEmployerContribution',
                'netEarningsFromSelfEmploymentAfterHalfSETax',
                'simpleAdditionalNonelectiveContribution',
                'pensionLinkedEmergencySavingsParticipantContributionBalance',
            ] as $key
        ) {
            if (array_key_exists($key, $rules)) {
                self::money($rules[$key], "{$path}.{$key}");
            }
        }
        foreach (['employerMatchRate', 'employerMatchCompensationFraction', 'employerNonelectiveRate'] as $key) {
            if (array_key_exists($key, $rules)) {
                self::rate($rules[$key], "{$path}.{$key}");
            }
        }
        foreach (
            [
                'permitsRothContributions',
                'permitsRothCatchUp',
                'permitsAfterTaxEmployeeContributions',
                'permitsInPlanRothRollover',
                'simpleEnhancedLimitEligible',
                'isSelfEmployedOwner',
                'grandfatheredSarsep',
            ] as $key
        ) {
            self::booleanFlag($rules, $key, "{$path}.{$key}");
        }
        self::requireInputObject($rules, 'special403bCatchUp', "{$path}.special403bCatchUp");
        self::requireInputObject($rules, 'section457SpecialCatchUp', "{$path}.section457SpecialCatchUp");
        self::requireInputObject($rules, 'hsa', "{$path}.hsa");
        self::requireInputObject($rules, 'healthFsa', "{$path}.healthFsa");
        self::requireInputObject($rules, 'dependentCareFsa', "{$path}.dependentCareFsa");
        if (array_key_exists('simpleEmployerContributionMethod', $rules) && !in_array(
            $rules['simpleEmployerContributionMethod'],
            ['match_3_percent', 'nonelective_2_percent', 'custom'],
            true,
        )) {
            throw new ParameterException(
                'INVALID_SIMPLE_EMPLOYER_CONTRIBUTION_METHOD',
                "{$path}.simpleEmployerContributionMethod is invalid.",
            );
        }
        if (isset($rules['special403bCatchUp']) && is_array($rules['special403bCatchUp'])) {
            $special = $rules['special403bCatchUp'];
            self::booleanFlag($special, 'eligible', "{$path}.special403bCatchUp.eligible");
            $years = $special['yearsOfService'] ?? null;
            if ((!is_int($years) && !is_float($years)) || !is_finite((float) $years) || (float) $years < 0) {
                throw new ParameterException(
                    'INVALID_YEARS_OF_SERVICE',
                    "{$path}.special403bCatchUp.yearsOfService is invalid.",
                );
            }
            self::money($special['priorElectiveDeferrals'] ?? null, "{$path}.special403bCatchUp.priorElectiveDeferrals");
            self::money($special['priorSpecialCatchUpUsed'] ?? null, "{$path}.special403bCatchUp.priorSpecialCatchUpUsed");
        }
        if (isset($rules['section457SpecialCatchUp']) && is_array($rules['section457SpecialCatchUp'])) {
            self::booleanFlag(
                $rules['section457SpecialCatchUp'],
                'eligible',
                "{$path}.section457SpecialCatchUp.eligible",
            );
            self::money(
                $rules['section457SpecialCatchUp']['unusedDeferralsFromPriorYears'] ?? null,
                "{$path}.section457SpecialCatchUp.unusedDeferralsFromPriorYears",
            );
        }
        if (array_key_exists('contributionPreference', $rules) && !in_array(
            $rules['contributionPreference'],
            ['account_type', 'pretax_first', 'roth_first'],
            true,
        )) {
            throw new ParameterException(
                'INVALID_CONTRIBUTION_PREFERENCE',
                "{$path}.contributionPreference is invalid.",
            );
        }
        if (array_key_exists('employerContributionTaxTreatment', $rules) && !in_array(
            $rules['employerContributionTaxTreatment'],
            ['pretax', 'roth'],
            true,
        )) {
            throw new ParameterException(
                'INVALID_EMPLOYER_CONTRIBUTION_TAX_TREATMENT',
                "{$path}.employerContributionTaxTreatment is invalid.",
            );
        }
        if (isset($rules['hsa']) && is_array($rules['hsa'])) {
            self::validateHsaRules($rules['hsa'], "{$path}.hsa");
        }
        if (isset($rules['healthFsa']) && is_array($rules['healthFsa'])) {
            self::validateHealthFsaRules($rules['healthFsa'], "{$path}.healthFsa");
        }
        if (isset($rules['dependentCareFsa']) && is_array($rules['dependentCareFsa'])) {
            self::validateDependentCareFsaRules($rules['dependentCareFsa'], "{$path}.dependentCareFsa");
        }
    }

    /** @param array<string,mixed> $rules */
    private static function validateDependentCareFsaRules(array $rules, string $path): void
    {
        self::money($rules['planDocumentLimit'] ?? null, "{$path}.planDocumentLimit");
    }

    /** @param array<string,mixed> $rules */
    private static function validateHealthFsaRules(array $rules, string $path): void
    {
        if (array_key_exists('purpose', $rules) && !in_array(
            $rules['purpose'],
            ['general_purpose', 'limited_purpose', 'post_deductible'],
            true,
        )) {
            throw new ParameterException(
                'INVALID_HEALTH_FSA_PURPOSE',
                "{$path}.purpose must be \"general_purpose\", \"limited_purpose\", or \"post_deductible\".",
            );
        }
        self::booleanFlag($rules, 'offersCarryover', "{$path}.offersCarryover");
        self::booleanFlag($rules, 'offersGracePeriod', "{$path}.offersGracePeriod");
        self::booleanFlag($rules, 'flexCreditElectableAsCash', "{$path}.flexCreditElectableAsCash");
        self::booleanFlag($rules, 'planYearIsCalendarYear', "{$path}.planYearIsCalendarYear");
        self::money($rules['priorYearUnusedAmount'] ?? null, "{$path}.priorYearUnusedAmount");
        self::money($rules['employerFlexCredit'] ?? null, "{$path}.employerFlexCredit");
        self::money($rules['planDocumentLimit'] ?? null, "{$path}.planDocumentLimit");
    }

    private static function parseHsaCoverageTier(mixed $value, string $path): string
    {
        if (!is_string($value) || !in_array($value, ['self_only', 'family'], true)) {
            throw new ParameterException(
                'INVALID_HSA_COVERAGE_TIER',
                "{$path} must be \"self_only\" or \"family\".",
            );
        }
        return $value;
    }

    private static function validateHsaMonth(mixed $value, string $path): int
    {
        if (!is_int($value) && !is_float($value)) {
            throw new ParameterException('INVALID_HSA_MONTH', "{$path} must be an integer from 1 through 12.");
        }
        $number = (float) $value;
        if (!is_finite($number) || floor($number) !== $number || $number < 1 || $number > 12) {
            throw new ParameterException('INVALID_HSA_MONTH', "{$path} must be an integer from 1 through 12.");
        }
        return (int) $number;
    }

    /** @param array<string,mixed> $rules */
    private static function validateHsaCoverage(array $rules, string $path): void
    {
        $hasMonthly = array_key_exists('monthlyCoverage', $rules);
        $hasTierForm = array_key_exists('coverageTier', $rules) || array_key_exists('eligibleMonths', $rules);
        if ($hasMonthly && $hasTierForm) {
            throw new ParameterException(
                'INVALID_HSA_COVERAGE_INPUT',
                "{$path} must supply either monthlyCoverage or coverageTier/eligibleMonths, not both.",
            );
        }
        if (array_key_exists('coverageTier', $rules)) {
            self::parseHsaCoverageTier($rules['coverageTier'], "{$path}.coverageTier");
        }
        if (array_key_exists('eligibleMonths', $rules)) {
            if (!is_array($rules['eligibleMonths']) || !array_is_list($rules['eligibleMonths'])) {
                throw new ParameterException('INVALID_HSA_ELIGIBLE_MONTHS', "{$path}.eligibleMonths must be an array.");
            }
            $seen = [];
            foreach ($rules['eligibleMonths'] as $index => $month) {
                $value = self::validateHsaMonth($month, "{$path}.eligibleMonths[{$index}]");
                if (isset($seen[$value])) {
                    throw new ParameterException(
                        'DUPLICATE_HSA_MONTH',
                        "{$path}.eligibleMonths lists month {$value} more than once.",
                    );
                }
                $seen[$value] = true;
            }
        }
        if ($hasMonthly) {
            if (!is_array($rules['monthlyCoverage']) || !array_is_list($rules['monthlyCoverage'])) {
                throw new ParameterException(
                    'INVALID_HSA_MONTHLY_COVERAGE',
                    "{$path}.monthlyCoverage must be an array.",
                );
            }
            $seen = [];
            foreach ($rules['monthlyCoverage'] as $index => $entry) {
                if (!is_array($entry)) {
                    throw new ParameterException(
                        'INVALID_HSA_MONTHLY_COVERAGE',
                        "{$path}.monthlyCoverage[{$index}] must be an object.",
                    );
                }
                $month = self::validateHsaMonth(
                    $entry['month'] ?? null,
                    "{$path}.monthlyCoverage[{$index}].month",
                );
                if (isset($seen[$month])) {
                    throw new ParameterException(
                        'DUPLICATE_HSA_MONTH',
                        "{$path}.monthlyCoverage lists month {$month} more than once.",
                    );
                }
                $seen[$month] = true;
                self::parseHsaCoverageTier(
                    $entry['coverage'] ?? null,
                    "{$path}.monthlyCoverage[{$index}].coverage",
                );
            }
        }
        self::money($rules['hdhpAnnualDeductible'] ?? null, "{$path}.hdhpAnnualDeductible");
    }

    /** @param array<string,mixed> $rules */
    private static function validateHsaRules(array $rules, string $path): void
    {
        self::validateHsaCoverage($rules, $path);
        self::booleanFlag($rules, 'useLastMonthRule', "{$path}.useLastMonthRule");
        self::booleanFlag($rules, 'testingPeriodSatisfied', "{$path}.testingPeriodSatisfied");
        self::booleanFlag(
            $rules,
            'testingPeriodFailureByDeathOrDisability',
            "{$path}.testingPeriodFailureByDeathOrDisability",
        );
        if (array_key_exists('familyLimitShare', $rules)) {
            self::rate($rules['familyLimitShare'], "{$path}.familyLimitShare");
        }
    }

    private static function validateIsoDate(string $value, string $path): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new ParameterException('INVALID_DATE', "{$path} must use YYYY-MM-DD.");
        }
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (!checkdate($month, $day, $year)) {
            throw new ParameterException('INVALID_DATE', "{$path} is not a valid calendar date.");
        }
    }

    /** @param array<string,mixed> $person */
    private static function ageAtEndOfTaxYear(array $person, int $taxYear): ?int
    {
        if (isset($person['birthDate'])) {
            return $taxYear - (int) substr((string) $person['birthDate'], 0, 4);
        }
        if (isset($person['birthYear'])) {
            return $taxYear - (int) $person['birthYear'];
        }
        return null;
    }

    /** @param array<string,mixed> $person */
    private static function reachesAge70HalfByYearEnd(array $person, int $taxYear): ?bool
    {
        if (isset($person['birthDate'])) {
            $birth = new DateTimeImmutable((string) $person['birthDate'], new DateTimeZone('UTC'));
            $seventyHalf = $birth->modify('+70 years')->modify('+6 months');
            $yearEnd = new DateTimeImmutable("{$taxYear}-12-31", new DateTimeZone('UTC'));
            return $seventyHalf <= $yearEnd;
        }
        if (isset($person['birthYear'])) {
            $age = $taxYear - (int) $person['birthYear'];
            if ($age >= 71) {
                return true;
            }
            if ($age <= 69) {
                return false;
            }
        }
        return null;
    }

    /** @param array<string,mixed> $person */
    private static function iraCompensation(array $person): float
    {
        $compensation = $person['compensation'];
        if (array_key_exists('iraCompensation', $compensation)) {
            return self::money($compensation['iraCompensation'], "{$person['id']}.compensation.iraCompensation");
        }
        return self::roundMoney(
            self::money($compensation['w2Compensation'] ?? null, "{$person['id']}.compensation.w2Compensation")
            + self::money(
                $compensation['selfEmploymentNetEarnings'] ?? null,
                "{$person['id']}.compensation.selfEmploymentNetEarnings",
            ),
        );
    }

    /** @param array<string,mixed> $account
     *  @param array<string,mixed> $person
     */
    private static function planCompensation(array $account, array $person): float
    {
        $rules = $account['planRules'];
        if (array_key_exists('planCompensation', $rules)) {
            return self::money($rules['planCompensation'], "{$account['id']}.planRules.planCompensation");
        }
        if (!empty($rules['isSelfEmployedOwner'])) {
            return self::money(
                $rules['netEarningsFromSelfEmploymentAfterHalfSETax']
                    ?? $person['compensation']['selfEmploymentNetEarnings']
                    ?? null,
                "{$account['id']}.selfEmploymentCompensation",
            );
        }
        return self::money(
            $person['compensation']['w2Compensation'] ?? $person['compensation']['iraCompensation'] ?? null,
            "{$account['id']}.planCompensationDefault",
        );
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @param array<string,mixed> $person
     */
    private static function recognizedCompensationForEmployerAllocation(
        array $context,
        array $account,
        array $person,
    ): float {
        $compensation = self::planCompensation($account, $person);
        $statutoryLimit = $context['parameters']['annualCompensation401a17'];
        return $statutoryLimit === null
            ? $compensation
            : self::minMoney($compensation, (float) $statutoryLimit);
    }

    /** @param array<string,mixed> $account */
    private static function groupIdForAccount(array $account): string
    {
        $employerGroup = $account['planRules']['annualAdditionsGroupId']
            ?? $account['employerId']
            ?? "account:{$account['id']}";
        return "{$account['ownerId']}:{$employerGroup}";
    }

    /** @param array<string,mixed> $parameters
     *  @param array<string,mixed> $traits
     */
    private static function availabilityForAccount(array $parameters, array $traits): bool
    {
        if ($traits['availabilityKey'] !== null
            && ($parameters['availability'][$traits['availabilityKey']] ?? false) !== true) {
            return false;
        }
        if ($traits['family'] === 'section457' && $traits['governmental457'] && $traits['designatedRoth']) {
            return ($parameters['section457b']['designatedRothAvailableForGovernmentalPlans'] ?? false) === true;
        }
        return true;
    }

    /** @param array<string,mixed> $parameters
     *  @param array<string,mixed> $person
     *  @param array<string,mixed> $traits
     */
    private static function workplaceCatchUpLimit(array $parameters, array $person, array $traits): float
    {
        $age = self::ageAtEndOfTaxYear($person, (int) $parameters['year']);
        if (empty($traits['permitsAgeCatchUpByStatute']) || $age === null || $age < 50) {
            return 0.0;
        }
        if (!empty($traits['isStarter'])) {
            return (float) $parameters['starterDeferralOnly']['age50CatchUp'];
        }
        if (!empty($traits['isSimple'])) {
            if ($age >= 60 && $age <= 63 && $parameters['simple']['age60To63CatchUp'] !== null) {
                return (float) $parameters['simple']['age60To63CatchUp'];
            }
            return (float) $parameters['simple']['generalAge50CatchUp'];
        }
        if ($age >= 60 && $age <= 63 && $parameters['age60To63CatchUp'] !== null) {
            return (float) $parameters['age60To63CatchUp'];
        }
        if ($traits['family'] === 'section457') {
            return (float) $parameters['section457b']['governmentalAge50CatchUp'];
        }
        return (float) $parameters['generalAge50CatchUp'];
    }

    /** @param array<string,mixed> $parameters
     *  @param array<string,mixed> $person
     */
    /**
     * The largest IRC 414(v) catch-up this account could take in this year at any
     * age. Used only where the participant's age is unknown, to decide whether it
     * could matter: the owner's catch-up pool cannot answer that question, because
     * its own limit is sized from the same unknown age and so reads empty exactly
     * when the question is open.
     *
     * @param array<string,mixed> $parameters
     * @param array<string,mixed> $traits
     */
    private static function maximumAgeCatchUpLimitForYear(array $parameters, array $traits): float
    {
        if (empty($traits['permitsAgeCatchUpByStatute'])) {
            return 0.0;
        }
        $atFifty = ($traits['family'] ?? null) === 'section457'
            ? $parameters['section457b']['governmentalAge50CatchUp']
            : $parameters['generalAge50CatchUp'];
        // IRC 414(v)(2)(E) gives ages 60 through 63 a larger figure where the year
        // encodes one, and workplaceCatchUpLimit prefers it over every family's own
        // age-50 amount, so it is part of the maximum.
        return max((float) $atFifty, (float) ($parameters['age60To63CatchUp'] ?? 0));
    }

    private static function ownerGeneralCatchUpLimit(array $parameters, array $person): float
    {
        $age = self::ageAtEndOfTaxYear($person, (int) $parameters['year']);
        if ($age === null || $age < 50) {
            return 0.0;
        }
        if ($age >= 60 && $age <= 63 && $parameters['age60To63CatchUp'] !== null) {
            return (float) $parameters['age60To63CatchUp'];
        }
        return (float) $parameters['generalAge50CatchUp'];
    }

    /** @param array<string,mixed>|null $range
     *  @return array{0:float,1:float}|null
     */
    private static function rangeForFilingStatus(
        ?array $range,
        string $status,
        bool $livedWithSpouseDuringYear,
        bool $spouseCoveredRange,
    ): ?array {
        if ($range === null) {
            return null;
        }
        if ($status === FilingStatus::MARRIED_FILING_JOINTLY->value) {
            $value = $spouseCoveredRange
                ? ($range['marriedFilingJointly'] ?? $range['marriedFilingJointlyOrQualifyingSurvivingSpouse'] ?? null)
                : ($range['marriedFilingJointlyOrQualifyingSurvivingSpouse'] ?? $range['marriedFilingJointly'] ?? null);
            return $value === null ? null : [(float) $value[0], (float) $value[1]];
        }
        if ($status === FilingStatus::QUALIFYING_SURVIVING_SPOUSE->value) {
            $value = $range['marriedFilingJointlyOrQualifyingSurvivingSpouse'] ?? null;
            return $value === null ? null : [(float) $value[0], (float) $value[1]];
        }
        if ($status === FilingStatus::MARRIED_FILING_SEPARATELY->value && $livedWithSpouseDuringYear) {
            $value = $range['marriedFilingSeparatelyLivingTogether'] ?? null;
            return $value === null ? null : [(float) $value[0], (float) $value[1]];
        }
        $value = $range['singleOrHeadOfHousehold'] ?? null;
        return $value === null ? null : [(float) $value[0], (float) $value[1]];
    }

    /** @param array{0:float,1:float}|null $range
     *  @param array<string,mixed> $rounding
     */
    private static function phaseoutReducedLimit(
        float $unreducedLimit,
        float $magi,
        ?array $range,
        array $rounding,
    ): float {
        if ($range === null) {
            return $unreducedLimit;
        }
        [$lower, $upper] = $range;
        if ($magi <= $lower) {
            return $unreducedLimit;
        }
        if ($magi >= $upper) {
            return 0.0;
        }
        $raw = $unreducedLimit * (($upper - $magi) / ($upper - $lower));
        $increment = (float) $rounding['iraPhaseoutIncrement'];
        $roundedUp = ceil($raw / $increment) * $increment;
        return self::roundMoney(max((float) $rounding['iraPositiveReducedMinimum'], $roundedUp));
    }

    /** @param array<string,mixed> $person
     *  @param array<string,mixed> $parameters
     */
    private static function personalIraStatutoryLimit(array $person, array $parameters): ?float
    {
        $age = self::ageAtEndOfTaxYear($person, (int) $parameters['year']);
        if ($age === null) {
            return null;
        }
        return self::roundMoney(
            (float) $parameters['ira']['baseContributionLimit']
            + ($age >= 50 ? (float) $parameters['ira']['age50CatchUp'] : 0.0),
        );
    }

    /** @param array<string,mixed> $person */
    private static function livedWithSpouse(array $person, string $filingStatus): bool
    {
        if (array_key_exists('livedWithSpouseDuringYear', $person)) {
            return (bool) $person['livedWithSpouseDuringYear'];
        }
        return $filingStatus === FilingStatus::MARRIED_FILING_SEPARATELY->value;
    }

    /** @param array<string,array<string,mixed>> $persons
     *  @param array<string,mixed> $person
     *  @return array<string,mixed>|null
     */
    private static function spouseForPerson(array $persons, array $person): ?array
    {
        $targetRole = ($person['role'] ?? null) === 'taxpayer'
            ? 'spouse'
            : ((($person['role'] ?? null) === 'spouse') ? 'taxpayer' : null);
        if ($targetRole === null) {
            return null;
        }
        foreach ($persons as $candidate) {
            if (($candidate['role'] ?? null) === $targetRole) {
                return $candidate;
            }
        }
        return null;
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $person
     */
    private static function traditionalIraDeductionLimit(array $context, array $person, ?float $personalLimit): ?float
    {
        if ($personalLimit === null) {
            return null;
        }
        $parameters = $context['parameters'];
        $filingStatus = $context['filingStatus'];
        $selfCoverage = $person['coveredByEmployerRetirementPlan'] ?? null;
        if (!$parameters['ira']['universalEligibility']) {
            if (!array_key_exists('coveredByEmployerRetirementPlan', $person)) {
                return null;
            }
            return $selfCoverage ? 0.0 : $personalLimit;
        }
        if ((int) $parameters['year'] < 1987) {
            return $personalLimit;
        }
        $spouse = self::spouseForPerson($context['persons'], $person);
        $livingTogether = self::livedWithSpouse($person, $filingStatus);
        $spouseCoverageRelevant = (
            $filingStatus === FilingStatus::MARRIED_FILING_JOINTLY->value
            || ($filingStatus === FilingStatus::MARRIED_FILING_SEPARATELY->value && $livingTogether)
        ) && $spouse !== null;
        if (!array_key_exists('coveredByEmployerRetirementPlan', $person)) {
            return null;
        }
        $applicableRange = null;
        $useSpouseCoveredRange = false;
        if ($selfCoverage) {
            $applicableRange = $parameters['phaseouts']['traditionalIraCovered'];
        } elseif ($spouseCoverageRelevant) {
            if (!array_key_exists('coveredByEmployerRetirementPlan', $spouse)) {
                return null;
            }
            if ($spouse['coveredByEmployerRetirementPlan']) {
                $applicableRange = $parameters['phaseouts']['traditionalIraSpouseCovered'];
                $useSpouseCoveredRange = true;
            }
        }
        if ($applicableRange === null) {
            return $personalLimit;
        }
        if (!array_key_exists('traditionalIraDeduction', $person['magi'])) {
            return null;
        }
        return self::phaseoutReducedLimit(
            $personalLimit,
            self::money($person['magi']['traditionalIraDeduction'], "{$person['id']}.magi.traditionalIraDeduction"),
            self::rangeForFilingStatus(
                $applicableRange,
                $filingStatus,
                $livingTogether,
                $useSpouseCoveredRange,
            ),
            $context['rounding'],
        );
    }

    /** @param array<string,mixed> $parameters
     *  @param array<string,array<string,mixed>> $persons
     *  @param list<array<string,mixed>> $accounts
     *  @param list<array<string,mixed>> $scenarioDiagnostics
     *  @param array<string,mixed> $data
     *  @return array<string,mixed>
     */
    private static function createContext(
        int $taxYear,
        string $filingStatus,
        ?bool $treatedAsUnmarriedUnderSection21e4,
        array $parameters,
        ?array $hsaParameters,
        ?array $fsaParameters,
        array $persons,
        array $accounts,
        array &$scenarioDiagnostics,
        array $data,
        array $hsaData,
        array $fsaData,
    ): array {
        $accountsById = [];
        foreach ($accounts as $account) {
            $accountsById[$account['id']] = $account;
        }
        $context = [
            'taxYear' => $taxYear,
            'filingStatus' => $filingStatus,
            'treatedAsUnmarriedUnderSection21e4' => $treatedAsUnmarriedUnderSection21e4,
            'parameters' => $parameters,
            'hsaParameters' => $hsaParameters,
            'hsaSupportedTaxYears' => $hsaData['supportedTaxYears'],
            'fsaParameters' => $fsaParameters,
            'fsaSupportedTaxYears' => $fsaData['supportedTaxYears'],
            'fsaData' => $fsaData,
            'persons' => $persons,
            'accountsById' => $accountsById,
            'scenarioDiagnostics' => &$scenarioDiagnostics,
            'rounding' => $data['rounding'],
            'iraOwnerPools' => [],
            'iraCompensationPools' => [],
            'iraRothEligibilityPools' => [],
            'iraDeductionPools' => [],
            'elective402gPools' => [],
            'catchUpPools' => [],
            'plesaPools' => [],
            'special403bCatchUpPools' => [],
            'annualAdditionsPools' => [],
            'section457BasePools' => [],
            'section457CatchUpPools' => [],
            'section457SpecialCatchUpPools' => [],
            'section457CatchUpResolutions' => [],
            'hsaBasePools' => [],
            'hsaCatchUpPools' => [],
            'hsaFamilyPools' => [],
            'hsaPlans' => [],
            'healthFsaPools' => [],
            'healthFsaPlans' => [],
            'dependentCarePools' => [],
            'dependentCarePlans' => [],
            'dependentCareEarnedIncomeCeilings' => [],
            'section220TwiceTheLesserOwners' => [],
        ];
        self::initializeIraPools($context, $accounts);
        self::initializeElectiveDeferralPools($context, $accounts);
        self::initializeAnnualAdditionsPools($context, $accounts);
        self::initializeSection457Pools($context, $accounts);
        // Health FSA facts are read by the IRC 223 interaction, so the
        // arrangements must be resolved before the health savings accounts that
        // consult them.
        self::initializeHealthFsaPools($context, $accounts);
        self::initializeDependentCarePools($context, $accounts);
        self::initializeHsaPools($context, $accounts);
        return $context;
    }

    /** @param array<string,mixed> $context
     *  @param list<array<string,mixed>> $accounts
     */
    private static function initializeIraPools(array &$context, array $accounts): void
    {
        $parameters = $context['parameters'];
        $taxpayerAndSpouse = array_values(array_filter(
            $context['persons'],
            static fn (array $person): bool => in_array($person['role'] ?? null, ['taxpayer', 'spouse'], true),
        ));
        $shareSpousalCompensation =
            $context['filingStatus'] === FilingStatus::MARRIED_FILING_JOINTLY->value
            && count($taxpayerAndSpouse) >= 2;
        if ($shareSpousalCompensation) {
            $combinedCompensation = self::roundMoney(array_sum(array_map(self::iraCompensation(...), $taxpayerAndSpouse)));
            $earningCount = count(array_filter($taxpayerAndSpouse, static fn (array $person): bool => self::iraCompensation($person) > 0));
            $householdLimit = self::roundMoney($combinedCompensation * (float) $parameters['ira']['compensationFraction']);
            if ($earningCount === 1 && $parameters['ira']['oneEarnerHouseholdCombinedLimit'] !== null) {
                $householdLimit = min($householdLimit, (float) $parameters['ira']['oneEarnerHouseholdCombinedLimit']);
            }
            $sumPersonal = 0.0;
            $allKnown = true;
            foreach ($taxpayerAndSpouse as $person) {
                $limit = self::personalIraStatutoryLimit($person, $parameters);
                if ($limit === null) {
                    $allKnown = false;
                } else {
                    $sumPersonal += $limit;
                }
            }
            if ($allKnown) {
                $householdLimit = min($householdLimit, $sumPersonal);
            }
            $context['iraCompensationPools']['ira-household'] = [
                'id' => 'ira-household',
                'legalLimit' => 'IRC 219(c) joint-return compensation limit',
                'limit' => self::roundMoney($householdLimit),
                'used' => 0.0,
            ];
        }

        foreach ($context['persons'] as $person) {
            $statutory = self::personalIraStatutoryLimit($person, $parameters);
            $ownCompensation = self::iraCompensation($person);
            $isHouseholdMember = $shareSpousalCompensation
                && in_array($person['role'] ?? null, ['taxpayer', 'spouse'], true);
            $compensationPoolId = $isHouseholdMember ? 'ira-household' : "ira-compensation:{$person['id']}";
            if (!$isHouseholdMember) {
                $context['iraCompensationPools'][$compensationPoolId] = [
                    'id' => $compensationPoolId,
                    'legalLimit' => 'IRC 219(b) compensation limit',
                    'limit' => $statutory === null
                        ? null
                        : self::minMoney($statutory, $ownCompensation * (float) $parameters['ira']['compensationFraction']),
                    'used' => 0.0,
                ];
            }
            $personalLimit = $statutory;
            if ($personalLimit !== null && $isHouseholdMember && $context['taxYear'] < 1997 && $ownCompensation === 0.0) {
                // A null limit means "not encoded as a universal figure", which
                // is not a limit of zero. Coercing it gave PHP a 0.0 ceiling
                // where TypeScript carried the null through to indeterminate.
                $personalLimit = $parameters['ira']['spousalIraAvailable']
                    ? ($parameters['ira']['nonworkingSpouseIndividualLimit'] === null
                        ? null
                        : (float) $parameters['ira']['nonworkingSpouseIndividualLimit'])
                    : 0.0;
                if ($parameters['ira']['spousalIraAvailable']
                    && ($parameters['ira']['spousalDeductionIsTwiceTheLesserOfContributions'] ?? false)
                ) {
                    $context['section220TwiceTheLesserOwners'][(string) $person['id']] = true;
                }
            }
            if ($personalLimit !== null && $isHouseholdMember && $context['taxYear'] < 1997 && $ownCompensation > 0.0) {
                $personalLimit = self::minMoney(
                    $personalLimit,
                    $ownCompensation * (float) $parameters['ira']['compensationFraction'],
                );
            }
            $context['iraOwnerPools'][$person['id']] = [
                'id' => "ira-owner:{$person['id']}",
                'legalLimit' => 'IRC 219(b) aggregate traditional and Roth IRA contribution limit',
                'limit' => $personalLimit,
                'used' => 0.0,
                'blocked' => false,
                'compensationPoolId' => $compensationPoolId,
            ];
            $rothEligibilityLimit = 0.0;
            if ($parameters['ira']['rothAvailable']) {
                if ($personalLimit === null || !array_key_exists('rothIra', $person['magi'])) {
                    $rothEligibilityLimit = null;
                } else {
                    $rothEligibilityLimit = self::phaseoutReducedLimit(
                        $personalLimit,
                        self::money($person['magi']['rothIra'], "{$person['id']}.magi.rothIra"),
                        self::rangeForFilingStatus(
                            $parameters['phaseouts']['rothIra'],
                            $context['filingStatus'],
                            self::livedWithSpouse($person, $context['filingStatus']),
                            false,
                        ),
                        $context['rounding'],
                    );
                }
            }
            $context['iraRothEligibilityPools'][$person['id']] = [
                'id' => "roth-ira-eligibility:{$person['id']}",
                'legalLimit' => 'IRC 408A(c)(3) direct Roth IRA MAGI limit',
                'limit' => $rothEligibilityLimit,
                'used' => 0.0,
            ];
            $context['iraDeductionPools'][$person['id']] = [
                'id' => "traditional-ira-deduction:{$person['id']}",
                'legalLimit' => 'IRC 219(g) traditional IRA deduction limit',
                'limit' => self::traditionalIraDeductionLimit($context, $person, $personalLimit),
                'used' => 0.0,
            ];
        }

        foreach ($accounts as $account) {
            $traits = self::traits($account['type']);
            if (!in_array($traits['family'], ['regular_traditional_ira', 'regular_roth_ira'], true)) {
                continue;
            }
            $existing = self::regularIraContributionAmount($account['existingContributions']);
            if (!isset($context['iraOwnerPools'][$account['ownerId']])) {
                continue;
            }
            $ownerPool =& $context['iraOwnerPools'][$account['ownerId']];
            $ownerPool['used'] = self::roundMoney($ownerPool['used'] + $existing);
            $compensationPoolId = $ownerPool['compensationPoolId'];
            $context['iraCompensationPools'][$compensationPoolId]['used'] = self::roundMoney(
                $context['iraCompensationPools'][$compensationPoolId]['used'] + $existing,
            );
            $context['iraRothEligibilityPools'][$account['ownerId']]['used'] = self::roundMoney(
                $context['iraRothEligibilityPools'][$account['ownerId']]['used']
                + $account['existingContributions']['rothIra'],
            );
            $context['iraDeductionPools'][$account['ownerId']]['used'] = self::roundMoney(
                $context['iraDeductionPools'][$account['ownerId']]['used']
                + $account['existingContributions']['deductibleIra'],
            );
            unset($ownerPool);
        }
    }

    /** @param array<string,mixed> $context
     *  @param list<array<string,mixed>> $accounts
     */
    private static function initializeElectiveDeferralPools(array &$context, array $accounts): void
    {
        foreach ($context['persons'] as $person) {
            $id = $person['id'];
            $context['elective402gPools'][$id] = [
                'id' => "402g:{$id}",
                'legalLimit' => 'IRC 402(g) aggregate elective-deferral limit',
                'limit' => $context['parameters']['electiveDeferral402g'] === null
                    ? null
                    : (float) $context['parameters']['electiveDeferral402g'],
                'used' => 0.0,
            ];
            $context['catchUpPools'][$id] = [
                'id' => "414v:{$id}",
                'legalLimit' => 'IRC 414(v) aggregate age-based catch-up limit',
                'limit' => self::ownerGeneralCatchUpLimit($context['parameters'], $person),
                'used' => 0.0,
            ];
            $context['special403bCatchUpPools'][$id] = [
                'id' => "402g7:{$id}",
                'legalLimit' => 'IRC 402(g)(7) aggregate 403(b) 15-year catch-up limit',
                'limit' => (float) $context['parameters']['special403b15YearCatchUp']['annualLimit'],
                'used' => 0.0,
            ];
        }
        foreach ($accounts as $account) {
            $traits = self::traits($account['type']);
            if (empty($traits['shares402g'])) {
                continue;
            }
            $ownerId = $account['ownerId'];
            $context['elective402gPools'][$ownerId]['used'] = self::roundMoney(
                $context['elective402gPools'][$ownerId]['used']
                + self::baseDeferrals($account['existingContributions']),
            );
            $context['catchUpPools'][$ownerId]['used'] = self::roundMoney(
                $context['catchUpPools'][$ownerId]['used']
                + self::ageCatchUps($account['existingContributions']),
            );
            if (!empty($traits['is403b'])) {
                $context['special403bCatchUpPools'][$ownerId]['used'] = self::roundMoney(
                    $context['special403bCatchUpPools'][$ownerId]['used']
                    + $account['existingContributions']['special403bCatchUp'],
                );
            }
        }
    }

    /** @param array<string,mixed> $context
     *  @param list<array<string,mixed>> $accounts
     */
    private static function initializeAnnualAdditionsPools(array &$context, array $accounts): void
    {
        $groups = [];
        foreach ($accounts as $account) {
            if (empty(self::traits($account['type'])['uses415c'])) {
                continue;
            }
            $groupId = self::groupIdForAccount($account);
            $groups[$groupId][] = $account;
        }
        foreach ($groups as $groupId => $members) {
            $recognizedCompensation = 0.0;
            $existing = 0.0;
            foreach ($members as $account) {
                $person = $context['persons'][$account['ownerId']];
                $recognizedCompensation = max($recognizedCompensation, self::planCompensation($account, $person));
                $existing = self::roundMoney($existing + self::annualAdditions($account['existingContributions']));
            }
            if ($context['parameters']['annualCompensation401a17'] !== null) {
                $recognizedCompensation = min(
                    $recognizedCompensation,
                    (float) $context['parameters']['annualCompensation401a17'],
                );
            }
            $limit = null;
            if (
                $context['parameters']['annualAdditions415c'] !== null
                && $context['parameters']['annualAdditionsCompensationFraction'] !== null
            ) {
                $limit = self::minMoney(
                    (float) $context['parameters']['annualAdditions415c'],
                    $recognizedCompensation * (float) $context['parameters']['annualAdditionsCompensationFraction'],
                );
            }
            $context['annualAdditionsPools'][$groupId] = [
                'id' => "415c:{$groupId}",
                'legalLimit' => 'IRC 415(c) annual-additions limit',
                'limit' => $limit,
                'used' => $existing,
                'compensation' => self::roundMoney($recognizedCompensation),
            ];
        }
    }

    /** @param array<string,mixed> $context
     *  @param list<array<string,mixed>> $accounts
     */
    /**
     * The IRC 457(b)(2) plan ceiling for one account and the capacity each
     * catch-up method adds above it.
     *
     * IRC 457(b)(2) and 26 CFR 1.457-4(c)(1)(i) set the ordinary plan ceiling at
     * the lesser of the IRC 457(e)(15) dollar amount and 100 percent of
     * includible compensation. IRC 457(b)(3) then provides that for the last
     * three years before normal retirement age "the ceiling set forth in
     * paragraph (2) shall be" the lesser of twice the dollar amount and the sum
     * of "the plan ceiling established for purposes of paragraph (2) for the
     * taxable year" and the unused portion of prior years' ceilings;
     * 26 CFR 1.457-4(c)(3)(ii)(A) states the first addend the same way. That term
     * is the compensation-bounded figure, not the dollar amount, so with D the
     * dollar amount, C includible compensation and U the prior-year underutilized
     * limitation:
     *
     *     basic plan ceiling      B = min(D, C)
     *     special plan ceiling    S = min(2D, B + U)
     *     special above the basic S - B = min(2D - B, U)
     *
     * min(D, U) is that only where B equals D. Where compensation binds, 2D - B
     * exceeds D, and reducing the special amount to min(D, U) understated the
     * ceiling by exactly the amount compensation fell short.
     *
     * The two methods also differ in whether compensation bounds them a second
     * time. IRC 457(b)(3) *replaces* the paragraph (2) ceiling, so the
     * 100-percent bound inside that paragraph is displaced rather than
     * reapplied. IRC 414(v) instead adds to the paragraph (2) ceiling, and
     * IRC 414(v)(2)(A)(ii) caps the addition at the excess of the participant's
     * compensation over the elective deferrals made without regard to it.
     * Comparing the raw catch-up dollar figures therefore answers a different
     * question from the one 26 CFR 1.457-4(c)(2)(ii) asks, which is between the
     * resulting plan ceilings.
     *
     * @param array<string,mixed> $account
     * @param array<string,mixed> $person
     * @return array{includibleCompensation: float, basicPlanCeiling: float, specialAdditional: float, ageAdditional: float, largestPossibleAgeAdditional: float}
     */
    private static function section457PlanCeilings(
        array $parameters,
        array $person,
        array $account,
        float $statutoryBase,
        float $compensationFraction,
    ): array {
        $traits = self::traits($account['type']);
        $includibleCompensation = self::section457IncludibleCompensation($account, $person);
        $deferrableCompensation = $includibleCompensation * $compensationFraction;
        $basicPlanCeiling = self::minMoney($statutoryBase, $deferrableCompensation);
        $special = $account['planRules']['section457SpecialCatchUp'] ?? null;
        $specialAdditional = (!is_array($special) || empty($special['eligible']))
            ? 0.0
            : self::minMoney(
                self::nonnegative(2.0 * $statutoryBase - $basicPlanCeiling),
                self::money(
                    $special['unusedDeferralsFromPriorYears'] ?? null,
                    "{$account['id']}.unused457Deferrals",
                ),
            );
        $ageCompensationRoom = self::nonnegative($deferrableCompensation - $basicPlanCeiling);
        // IRC 414(v)(6)(A)(ii) reaches only an eligible governmental plan.
        $permitsAge = !empty($traits['governmental457']) && !empty($traits['permitsAgeCatchUpByStatute']);

        return [
            'includibleCompensation' => $includibleCompensation,
            'basicPlanCeiling' => $basicPlanCeiling,
            'specialAdditional' => $specialAdditional,
            'ageAdditional' => $permitsAge
                ? self::minMoney(
                    self::workplaceCatchUpLimit($parameters, $person, $traits),
                    $ageCompensationRoom,
                )
                : 0.0,
            'largestPossibleAgeAdditional' => $permitsAge
                ? self::minMoney(
                    self::maximumAgeCatchUpLimitForYear($parameters, $traits),
                    $ageCompensationRoom,
                )
                : 0.0,
        ];
    }

    /**
     * IRC 457(e)(5) includible compensation for one account, from the most
     * specific supplied fact. Shared by the resolver and the allocator so a plan
     * ceiling is built from the same compensation in both.
     *
     * @param array<string,mixed> $account
     * @param array<string,mixed> $person
     */
    private static function section457IncludibleCompensation(array $account, array $person): float
    {
        return self::money(
            $account['planRules']['includibleCompensation457']
                ?? $account['planRules']['planCompensation']
                ?? self::planCompensation($account, $person),
            "{$account['id']}.includibleCompensation457",
        );
    }

    /**
     * Resolves the participant-wide catch-up method for every person holding an
     * IRC 457 account, from the plan ceilings each of their plans actually
     * produces rather than from whatever pool capacity happens to survive to a
     * given account.
     *
     * 26 CFR 1.457-5(a) states the individual limitation as the basic annual
     * limitation "plus either the age 50 catch-up amount under 1.457-4(c)(2), or
     * the special section 457 catch-up amount under 1.457-4(c)(3), applied by
     * taking into account the combined annual deferral for the participant for
     * any taxable year under all eligible plans", and 1.457-5(b) aggregates that
     * across the plans of every employer the participant has served. The
     * selection test is the one 1.457-4(c)(2)(ii) states: the special catch-up
     * applies "if and only if" the plan ceiling counting paragraph (c)(1) and the
     * special catch-up "is larger than" the plan ceiling counting paragraph
     * (c)(1) and the age 50 catch-up. Both sides share the paragraph (c)(1) term,
     * so the test reduces to the two *additional* amounts -- but only once each
     * is computed as the statute computes it, which is what
     * section457PlanCeilings does: the IRC 457(b)(3) amount grows as includible
     * compensation falls and the IRC 414(v) amount shrinks to nothing as it does.
     * Comparing the two raw dollar figures instead answers the question the
     * regulation does not ask, and gets the opposite result whenever compensation
     * binds. "Larger than" is strict, as IRC 414(v)(6)(C) and IRC 457(e)(18) also
     * read.
     *
     * Each AccountInput is one eligible plan for these purposes. A pension-linked
     * emergency savings account is an account inside a host IRC 457(b) plan
     * rather than a plan of its own, so a host plan's IRC 457(b)(3) provision has
     * to be stated on the emergency savings record too for that record to draw
     * the amount. README documents the contract; #53 tracks the plan-group key
     * that would let one statement cover both records.
     *
     * @param array<string,mixed> $context
     * @param list<array<string,mixed>> $accounts
     */
    private static function resolveSection457CatchUpModes(array &$context, array $accounts): void
    {
        $statutoryBase = $context['parameters']['section457b']['baseDeferralLimit'];
        $compensationFraction = $context['parameters']['section457b']['includibleCompensationFraction'];

        foreach ($context['persons'] as $person) {
            $personId = $person['id'];
            // 26 CFR 1.457-5(b) aggregates across the eligible plans the participant
            // is actually in. An account type the year does not offer is not one of
            // them: allocateAccount rejects it outright, so letting it declare a
            // method, a ceiling or an existing contribution here would let an account
            // that cannot legally exist for the year settle the answer for one that
            // can.
            $owned = [];
            foreach ($accounts as $account) {
                $accountTraits = self::traits($account['type']);
                if (
                    $account['ownerId'] === $personId
                    && $accountTraits['family'] === 'section457'
                    && self::availabilityForAccount($context['parameters'], $accountTraits)
                ) {
                    $owned[] = $account;
                }
            }
            if ($owned === []) {
                continue;
            }
            if ($statutoryBase === null || $compensationFraction === null) {
                $context['section457CatchUpResolutions'][$personId] = [
                    'mode' => 'none',
                    'headroom' => 0.0,
                    'ageAmount' => 0.0,
                    'specialAmount' => 0.0,
                    'existingAgeCatchUp' => 0.0,
                    'existingSpecialCatchUp' => 0.0,
                    'existingCatchUpClassificationUnreconciled' => false,
                    'eligibleAccountIds' => [],
                ];
                continue;
            }

            $ceilings = [];
            foreach ($owned as $account) {
                $ceilings[$account['id']] = self::section457PlanCeilings(
                    $context['parameters'],
                    $person,
                    $account,
                    (float) $statutoryBase,
                    (float) $compensationFraction,
                );
            }

            // IRC 414(v)(6)(A)(ii) reaches only an eligible governmental plan, so a
            // participant with no such account has no age-based method to choose.
            $governmentalAccounts = [];
            foreach ($owned as $account) {
                $accountTraits = self::traits($account['type']);
                if (!empty($accountTraits['governmental457']) && !empty($accountTraits['permitsAgeCatchUpByStatute'])) {
                    $governmentalAccounts[] = $account;
                }
            }
            // 26 CFR 1.457-5(c) applies the limitation "using the catch-up amount
            // under whichever plan has the largest catch-up amount applicable to the
            // participant" -- the largest, not the sum, and separately for each
            // method, because each plan bounds each method with its own includible
            // compensation. Its Example 2 works the special side through four plans
            // offering $7,000, $2,000, $8,000 and nothing, which yield one $8,000
            // catch-up that has to be deferred under the plan offering it.
            $ageAmount = 0.0;
            $largestPossibleAgeCatchUp = 0.0;
            foreach ($governmentalAccounts as $account) {
                $ageAmount = max($ageAmount, $ceilings[$account['id']]['ageAdditional']);
                $largestPossibleAgeCatchUp = max(
                    $largestPossibleAgeCatchUp,
                    $ceilings[$account['id']]['largestPossibleAgeAdditional'],
                );
            }
            $specialAccounts = [];
            $specialAmount = 0.0;
            foreach ($owned as $account) {
                if ($ceilings[$account['id']]['specialAdditional'] > 0.0) {
                    $specialAccounts[] = $account;
                    $specialAmount = max($specialAmount, $ceilings[$account['id']]['specialAdditional']);
                }
            }
            $ageUnknown = self::ageAtEndOfTaxYear($person, $context['taxYear']) === null;

            if ($specialAmount > $largestPossibleAgeCatchUp) {
                // IRC 414(v)(6)(C) removes the age-based method for a year in which a
                // higher IRC 457(b)(3) limitation applies. Where the IRC 457(b)(3)
                // amount beats the largest plan ceiling the year could produce at *any*
                // age, that is settled without knowing this participant's age -- and
                // where IRC 414(v)(2)(A)(ii) leaves no compensation for an age-based
                // catch-up, the largest such ceiling is the basic one, so the age is
                // moot.
                $mode = 'special';
            } elseif ($ageUnknown && $largestPossibleAgeCatchUp > 0.0) {
                // Not merely the amount but the *method* turns on the age, and the two
                // draw different pools and can carry different tax treatment.
                $mode = 'indeterminate';
            } elseif ($specialAmount > $ageAmount) {
                $mode = 'special';
            } elseif ($ageAmount > 0.0) {
                $mode = 'age';
            } else {
                $mode = 'none';
            }

            // For a resolved method the set is the statutory one: which plans provide
            // it. For an unresolved one it names every account either method could
            // reach, so the missing-age diagnostic can be considered for each; whether
            // an account has room left for a catch-up to occupy is settled in the
            // allocator, after the base deferral has been made, where the figures are
            // exact.
            $eligibleIds = [];
            if ($mode === 'special') {
                foreach ($specialAccounts as $account) {
                    $eligibleIds[] = $account['id'];
                }
            } elseif ($mode === 'age') {
                foreach ($governmentalAccounts as $account) {
                    $eligibleIds[] = $account['id'];
                }
            } elseif ($mode === 'indeterminate') {
                foreach (array_merge($governmentalAccounts, $specialAccounts) as $account) {
                    $eligibleIds[] = $account['id'];
                }
            }

            // 26 CFR 1.457-5(a) states the individual limitation as the basic annual
            // limitation plus *either* the age 50 catch-up or the special IRC 457(b)(3)
            // catch-up, and 1.457-5(b) applies it "on an aggregate basis" across the
            // eligible plans of every employer the participant served. Contributions
            // already recorded under both methods therefore breach the limitation as a
            // pair, however small each one is, and contributions recorded under the one
            // the statute did not select breach it however small the total is, so the
            // aggregates are carried here for the allocator to diagnose.
            $existingAgeCatchUp = 0.0;
            $existingSpecialCatchUp = 0.0;
            foreach ($owned as $account) {
                $existingAgeCatchUp += self::ageCatchUps($account['existingContributions']);
                $existingSpecialCatchUp += $account['existingContributions']['special457CatchUp']
                    + $account['existingContributions']['special457RothCatchUp'];
            }

            $existingAgeCatchUp = self::roundMoney($existingAgeCatchUp);
            $existingSpecialCatchUp = self::roundMoney($existingSpecialCatchUp);
            $headroom = $mode === 'special' ? $specialAmount : ($mode === 'age' ? $ageAmount : 0.0);
            $selectedExistingCatchUp = match ($mode) {
                'age' => $existingAgeCatchUp,
                'special' => $existingSpecialCatchUp,
                default => 0.0,
            };
            $unselectedExistingCatchUp = self::roundMoney(
                $existingAgeCatchUp + $existingSpecialCatchUp - $selectedExistingCatchUp,
            );
            $existingCatchUpClassificationUnreconciled = (
                $mode === 'indeterminate'
                && $existingAgeCatchUp + $existingSpecialCatchUp > 0.0
            ) || (
                $existingAgeCatchUp > 0.0
                && $existingSpecialCatchUp > 0.0
            ) || (
                $mode !== 'indeterminate'
                && $unselectedExistingCatchUp > 0.0
            ) || (
                $mode !== 'indeterminate'
                && $selectedExistingCatchUp > $headroom
            );
            foreach ($owned as $account) {
                $accountTraits = self::traits($account['type']);
                $accountExistingAgeCatchUp = self::ageCatchUps($account['existingContributions']);
                $accountExistingSpecialCatchUp = self::roundMoney(
                    $account['existingContributions']['special457CatchUp']
                    + $account['existingContributions']['special457RothCatchUp'],
                );
                $accountProvidesSpecialCatchUp = is_array($account['planRules']['section457SpecialCatchUp'] ?? null)
                    && !empty($account['planRules']['section457SpecialCatchUp']['eligible']);
                if (
                    (
                        $accountExistingAgeCatchUp > 0.0
                        && !(
                            !empty($accountTraits['governmental457'])
                            && !empty($accountTraits['permitsAgeCatchUpByStatute'])
                        )
                    ) || (
                        $accountExistingSpecialCatchUp > 0.0
                        && (
                            !$accountProvidesSpecialCatchUp
                            || $accountExistingSpecialCatchUp > $ceilings[$account['id']]['specialAdditional']
                        )
                    )
                ) {
                    $existingCatchUpClassificationUnreconciled = true;
                    break;
                }
            }
            $context['section457CatchUpResolutions'][$personId] = [
                'mode' => $mode,
                'headroom' => $headroom,
                'ageAmount' => $ageAmount,
                'specialAmount' => $specialAmount,
                'existingAgeCatchUp' => $existingAgeCatchUp,
                'existingSpecialCatchUp' => $existingSpecialCatchUp,
                'existingCatchUpClassificationUnreconciled' => $existingCatchUpClassificationUnreconciled,
                'eligibleAccountIds' => array_fill_keys($eligibleIds, true),
            ];
        }
    }

    private static function initializeSection457Pools(array &$context, array $accounts): void
    {
        self::resolveSection457CatchUpModes($context, $accounts);
        foreach ($context['persons'] as $person) {
            $id = $person['id'];
            $context['section457BasePools'][$id] = [
                'id' => "457b:{$id}",
                'legalLimit' => 'IRC 457(b) aggregate annual deferral limit (separate from IRC 402(g))',
                'limit' => $context['parameters']['section457b']['baseDeferralLimit'] === null
                    ? null
                    : (float) $context['parameters']['section457b']['baseDeferralLimit'],
                'used' => 0.0,
            ];
            $context['section457CatchUpPools'][$id] = [
                'id' => "457b-catch-up:{$id}",
                'legalLimit' => 'IRC 414(v) governmental 457(b) age-based catch-up limit',
                // The largest amount any one of the participant's governmental plans
                // can host, for the same reason the IRC 457(b)(3) pool below is:
                // 26 CFR 1.457-5(c) applies the limitation "using the catch-up amount
                // under whichever plan has the largest catch-up amount applicable to
                // the participant". Each plan bounds the IRC 414(v) amount with its own
                // includible compensation under IRC 414(v)(2)(A)(ii), so a pool holding
                // the unbounded annual figure instead let two plans whose compensation
                // each bound them separately add up past the individual limitation.
                'limit' => $context['section457CatchUpResolutions'][$id]['ageAmount'] ?? 0.0,
                'used' => 0.0,
            ];
            $context['section457SpecialCatchUpPools'][$id] = [
                'id' => "457b-special-catch-up:{$id}",
                'legalLimit' => 'IRC 457(b)(3) special last-three-years catch-up',
                // The largest amount any one of the participant's plans provides, not
                // the sum of what they all provide: 26 CFR 1.457-5(c). A pool limited
                // to the statutory base instead let two plans' separate amounts add.
                'limit' => $context['section457CatchUpResolutions'][$id]['specialAmount'] ?? 0.0,
                'used' => 0.0,
            ];
        }
        foreach ($accounts as $account) {
            $accountTraits = self::traits($account['type']);
            if ($accountTraits['family'] !== 'section457') {
                continue;
            }
            // An account type the year does not offer seeds nothing. allocateAccount
            // rejects it and reports EXISTING_CONTRIBUTION_BEFORE_ACCOUNT_AVAILABLE for
            // whatever it holds; charging that amount against a pool as well would let
            // it take capacity away from an account that does exist for the year.
            if (!self::availabilityForAccount($context['parameters'], $accountTraits)) {
                continue;
            }
            $components = $account['existingContributions'];
            $base = self::roundMoney(
                self::baseDeferrals($components)
                + $components['employeeAfterTax']
                + $components['employerPreTax']
                + $components['employerRoth'],
            );
            $ownerId = $account['ownerId'];
            $context['section457BasePools'][$ownerId]['used'] = self::roundMoney(
                $context['section457BasePools'][$ownerId]['used'] + $base,
            );
            $context['section457CatchUpPools'][$ownerId]['used'] = self::roundMoney(
                $context['section457CatchUpPools'][$ownerId]['used'] + self::ageCatchUps($components),
            );
            $context['section457SpecialCatchUpPools'][$ownerId]['used'] = self::roundMoney(
                // Both flavours seed the one IRC 457(b)(3) pool: the tax treatment of
                // a catch-up does not change which statutory limitation it was made
                // under.
                $context['section457SpecialCatchUpPools'][$ownerId]['used']
                    + $components['special457CatchUp']
                    + $components['special457RothCatchUp'],
            );
        }
    }


    private const HSA_MONTHS_IN_YEAR = 12;

    /** @var list<string> The IRC 223(c)(2) coverage tiers, in the order the TypeScript engine iterates. */
    private const HSA_COVERAGE_TIERS = ['self_only', 'family'];

    /** IRC 223 parameters for the year, or null when no revenue procedure is encoded.
     *  @param array<string,mixed> $hsaData
     *  @return array<string,mixed>|null
     */
    public static function hsaParametersForYear(array $hsaData, int $taxYear): ?array
    {
        $row = $hsaData['years'][(string) $taxYear] ?? null;
        return is_array($row) ? self::copy($row) : null;
    }

    /** @param array<string,mixed> $fsaData
     *  @return array<string,mixed>|null
     */
    public static function fsaParametersForYear(array $fsaData, int $taxYear): ?array
    {
        $row = $fsaData['years'][(string) $taxYear] ?? null;
        return is_array($row) ? self::copy($row) : null;
    }

    /**
     * Mirrors JavaScript Number.prototype.toLocaleString for a money amount.
     * The default JavaScript formatter carries zero to three fraction digits,
     * so a sub-cent amount such as 0.003 must survive; rounding to two here
     * printed it as 0 while the TypeScript engine printed 0.003.
     */
    /** The tier as it reads in a diagnostic sentence. */
    private static function hsaTierLabel(string $tier): string
    {
        return $tier === 'family' ? 'family' : 'self-only';
    }

    private static function localeNumber(float $value): string
    {
        $rounded = round($value, 3);
        if ($rounded === floor($rounded)) {
            return number_format($rounded, 0);
        }
        return rtrim(number_format($rounded, 3), '0');
    }

    /** Mirrors JavaScript template-literal number interpolation. */
    private static function jsNumber(float $value): string
    {
        if (is_finite($value) && floor($value) === $value && abs($value) < 1.0e15) {
            return (string) (int) $value;
        }
        return (string) json_encode($value);
    }

    /**
     * The four IRC 223(c)(2) coverage fields, in a stable order, so coverage
     * stated on a person can be compared with coverage stated on that person's
     * account.
     *
     * @param array<string,mixed> $coverage
     */
    private static function hsaCoverageSignature(array $coverage): string
    {
        return (string) json_encode([
            $coverage['coverageTier'] ?? null,
            $coverage['eligibleMonths'] ?? null,
            $coverage['monthlyCoverage'] ?? null,
            $coverage['hdhpAnnualDeductible'] ?? null,
        ]);
    }

    /**
     * What a person's coverage was in a month, read across every statement made
     * about them. Two projections are needed because two different questions
     * are asked of the same statements:
     *
     *  - the resolved slot ('none'|'self_only'|'family'|'unknown') decides the
     *    *amount* -- eligibility, the family portion, and the undivided
     *    self-only portion added to the IRC 223(b)(5) household ceiling. A
     *    disagreement between 'self_only' and no coverage gives different self
     *    portions, so it is 'unknown' here.
     *  - the family presence ('family'|'not_family'|'unknown') decides IRC
     *    223(b)(5)(A) *applicability*. That same disagreement is 'not_family'
     *    either way, so the other spouse's answer stays knowable.
     *
     * A variant whose 'months' is null stated nothing usable and does not vote.
     *
     * @param list<array<string,mixed>> $variants
     * @return list<string>
     */
    private static function resolvedCoverageSlotsFor(array $variants): array
    {
        $vectors = [];
        foreach ($variants as $variant) {
            if ($variant['months'] !== null) {
                $vectors[] = $variant['months'];
            }
        }
        // No usable statement is unknown, stated explicitly rather than left to
        // a vacuous answer over an empty list.
        if (count($vectors) === 0) {
            return array_fill(0, self::HSA_MONTHS_IN_YEAR, 'unknown');
        }
        $slots = [];
        for ($index = 0; $index < self::HSA_MONTHS_IN_YEAR; $index += 1) {
            $seen = [];
            foreach ($vectors as $vector) {
                $seen[($vector[$index] ?? null) === null ? 'none' : (string) $vector[$index]] = true;
            }
            $slots[] = count($seen) > 1 ? 'unknown' : (string) array_key_first($seen);
        }
        return $slots;
    }

    /**
     * @param list<string> $slots
     * @param list<array<string,mixed>> $variants
     * @return list<string>
     */
    private static function familyPresenceFor(array $slots, array $variants): array
    {
        $vectors = [];
        foreach ($variants as $variant) {
            if ($variant['months'] !== null) {
                $vectors[] = $variant['months'];
            }
        }
        if (count($vectors) === 0) {
            return array_fill(0, self::HSA_MONTHS_IN_YEAR, 'unknown');
        }
        $presence = [];
        for ($index = 0; $index < self::HSA_MONTHS_IN_YEAR; $index += 1) {
            if ($slots[$index] !== 'unknown') {
                $presence[] = $slots[$index] === 'family' ? 'family' : 'not_family';
                continue;
            }
            $family = false;
            $notFamily = false;
            foreach ($vectors as $vector) {
                if (($vector[$index] ?? null) === 'family') {
                    $family = true;
                } else {
                    $notFamily = true;
                }
            }
            $presence[] = $family && $notFamily ? 'unknown' : ($family ? 'family' : 'not_family');
        }
        return $presence;
    }

    /**
     * True when every supplied statement gives the same value for one field.
     *
     * @param list<mixed> $values
     */
    private static function unanimousField(array $values): bool
    {
        $seen = [];
        foreach ($values as $value) {
            $seen[(string) json_encode($value ?? null)] = true;
        }
        return count($seen) <= 1;
    }

    /** Twelve coverage slots, or null when no coverage facts were supplied at all.
     *  @param array<string,mixed> $rules
     *  @return list<string|null>|null
     */
    private static function resolveHsaMonths(array $rules): ?array
    {
        $months = array_fill(0, self::HSA_MONTHS_IN_YEAR, null);
        if (array_key_exists('monthlyCoverage', $rules)) {
            foreach ((array) $rules['monthlyCoverage'] as $entry) {
                $months[((int) $entry['month']) - 1] = (string) $entry['coverage'];
            }
            return $months;
        }
        if (!array_key_exists('coverageTier', $rules)) {
            return null;
        }
        $eligible = array_key_exists('eligibleMonths', $rules)
            ? (array) $rules['eligibleMonths']
            : range(1, self::HSA_MONTHS_IN_YEAR);
        foreach ($eligible as $month) {
            $months[((int) $month) - 1] = (string) $rules['coverageTier'];
        }
        return $months;
    }

    /**
     * The two spouses of a married couple, when both are present. IRC 223(b)(5)
     * applies to "individuals who are married to each other", which covers a
     * separate return as well as a joint one.
     *
     * @param array<string,mixed> $context
     * @return array{0:string,1:string}|null
     */
    private static function hsaMarriedCouple(array $context): ?array
    {
        if (
            $context['filingStatus'] !== FilingStatus::MARRIED_FILING_JOINTLY->value
            && $context['filingStatus'] !== FilingStatus::MARRIED_FILING_SEPARATELY->value
        ) {
            return null;
        }
        $taxpayerId = null;
        $spouseId = null;
        foreach ($context['persons'] as $person) {
            if (($person['role'] ?? null) === 'taxpayer' && $taxpayerId === null) {
                $taxpayerId = (string) $person['id'];
            }
            if (($person['role'] ?? null) === 'spouse' && $spouseId === null) {
                $spouseId = (string) $person['id'];
            }
        }
        return $taxpayerId !== null && $spouseId !== null ? [$taxpayerId, $spouseId] : null;
    }

    /**
     * IRC 223(b)(5)(B) takes the Archer MSA reduction out of the IRC 223(b)(1)
     * limitation and then divides what is left, so the reduction comes off the
     * divisible family-coverage-month portion first. Any excess comes off the same
     * individual's undivided self-only-month portion, because (b)(5)(B)(i) reduces
     * the paragraph (1) limitation itself and not one component of it. Neither
     * portion goes below zero.
     *
     * @return array{0:float,1:float} the reduced family portion and the reduced self-only portion
     */
    private static function archerReducedPortions(float $familyPortion, float $selfPortion, float $amount): array
    {
        return [
            max(0.0, $familyPortion - $amount),
            max(0.0, $selfPortion - max(0.0, $amount - $familyPortion)),
        ];
    }

    /**
     * IRC 223(b)(4) reduces "the limitation which would (but for this paragraph)
     * apply under this subsection" — the whole of subsection (b) — "but not below
     * zero". The paragraph (1) limitation absorbs the reduction first, and only
     * what paragraph (1) cannot absorb reaches the paragraph (3) additional
     * contribution amount, because the subsection is reduced once as a whole.
     *
     * @return array{0:float,1:float} the reduced paragraph (1) limitation and the reduced paragraph (3) amount
     */
    private static function subsectionBReducedBy(float $baseLimit, float $catchUp, float $amount): array
    {
        return [
            self::nonnegative($baseLimit - $amount),
            self::nonnegative($catchUp - max(0.0, $amount - $baseLimit)),
        ];
    }

    /**
     * IRC 125(i) applies employee-by-employee and employer-by-employer. Notice
     * 2012-40 aggregates employers treated as one under IRC 414(b), (c) or (m)
     * through IRC 125(g)(4), and lets an employee of two unrelated employers
     * elect the full amount under each. `employerId` is what expresses that
     * grouping, so two arrangements sharing one carry one limit and two without
     * an employer carry their own.
     *
     * @param array<string,mixed> $account
     */
    private static function healthFsaPoolKey(array $account): string
    {
        $employer = isset($account['employerId'])
            ? (string) $account['employerId']
            : 'account:' . (string) $account['id'];
        return (string) $account['ownerId'] . '::' . $employer;
    }

    /** @param array<string,mixed> $context
     *  @param list<array<string,mixed>> $accounts
     */
    private static function initializeHealthFsaPools(array &$context, array $accounts): void
    {
        $fsaAccounts = [];
        foreach ($accounts as $account) {
            if (self::traits($account['type'])['family'] === 'health_fsa') {
                $fsaAccounts[] = $account;
            }
        }
        if ($fsaAccounts === []) {
            return;
        }

        $taxYear = (int) $context['taxYear'];
        // A year in the available-without-statutory-dollar-limit state supplies
        // no ceiling, which is not the same as supplying a ceiling of nothing.
        // Treating the row as absent keeps the downstream "no statutory figure"
        // handling and lets a caller-supplied plan document still produce a
        // real maximum.
        $healthFsaYear = $context['fsaParameters']['healthFsa'] ?? null;
        $yearParameters = ($healthFsaYear['state'] ?? null) === 'statutory_dollar_limit' ? $healthFsaYear : null;
        $priorYear = self::fsaParametersForYear($context['fsaData'], $taxYear - 1);
        $priorYearRow = $priorYear['healthFsa'] ?? null;
        $priorYearParameters = ($priorYearRow['state'] ?? null) === 'statutory_dollar_limit' ? $priorYearRow : null;

        foreach ($fsaAccounts as $account) {
            $rules = $account['planRules']['healthFsa'] ?? [];
            if (!is_array($rules)) {
                $rules = [];
            }
            $path = "accounts.{$account['id']}";
            $diagnostics = [];
            $status = CalculationStatus::DETERMINATE->value;
            $indeterminate = false;

            $purpose = isset($rules['purpose']) ? (string) $rules['purpose'] : null;
            $elected = (float) $account['existingContributions']['healthFsaSalaryReduction'];
            $priorYearUnused = self::money(
                $rules['priorYearUnusedAmount'] ?? null,
                "{$path}.planRules.healthFsa.priorYearUnusedAmount",
            );
            $flexCredit = self::money(
                $rules['employerFlexCredit'] ?? null,
                "{$path}.planRules.healthFsa.employerFlexCredit",
            );

            // Notice 2012-40: flex credits are outside IRC 125(i) because the
            // section reaches salary reduction contributions alone, unless the
            // employee could have taken them as cash or another taxable
            // benefit, in which case they are treated as salary reduction
            // contributions.
            $flexCreditCounted = 0.0;
            if ($flexCredit > 0) {
                $electable = $rules['flexCreditElectableAsCash'] ?? null;
                if ($electable === true) {
                    $flexCreditCounted = $flexCredit;
                    $diagnostics[] = self::diagnostic(
                        'HEALTH_FSA_FLEX_CREDIT_COUNTS_AGAINST_LIMIT',
                        DiagnosticSeverity::INFO,
                        'Employer flex credits of $' . self::localeNumber($flexCredit) . ' could be elected as cash or '
                            . 'another taxable benefit, so Notice 2012-40 treats them as salary reduction contributions '
                            . 'for IRC 125(i) and they consume the limit.',
                        "{$path}.planRules.healthFsa.employerFlexCredit",
                        'IRC 125(i); Notice 2012-40',
                    );
                } elseif ($electable === false) {
                    $diagnostics[] = self::diagnostic(
                        'HEALTH_FSA_FLEX_CREDIT_OUTSIDE_LIMIT',
                        DiagnosticSeverity::INFO,
                        'IRC 125(i) limits salary reduction contributions alone, so the $'
                            . self::localeNumber($flexCredit) . ' of non-elective employer flex credits does not '
                            . 'consume the limit (Notice 2012-40; Prop. Treas. Reg. 1.125-5(b)).',
                        "{$path}.planRules.healthFsa.employerFlexCredit",
                        'IRC 125(i); Notice 2012-40',
                    );
                } else {
                    $status = CalculationStatus::DETERMINATE_WITH_ASSUMPTIONS->value;
                    $diagnostics[] = self::diagnostic(
                        'HEALTH_FSA_FLEX_CREDIT_CASH_ELECTION_FACT_REQUIRED',
                        DiagnosticSeverity::WARNING,
                        'Employer flex credits of $' . self::localeNumber($flexCredit) . ' were supplied without '
                            . 'stating whether the employee could elect them as cash or another taxable benefit. '
                            . 'Notice 2012-40 keeps non-elective flex credits outside IRC 125(i) and treats electable '
                            . 'ones as salary reduction contributions, so they are assumed non-elective here. Supply '
                            . 'planRules.healthFsa.flexCreditElectableAsCash to settle it.',
                        "{$path}.planRules.healthFsa.flexCreditElectableAsCash",
                        'IRC 125(i); Notice 2012-40',
                    );
                }
            }

            // Notice 2012-40 reads "taxable year" in IRC 125(i) as the plan year
            // of the cafeteria plan, while every annual revenue procedure
            // publishes the figure for taxable years beginning in a calendar
            // year. The two agree exactly for a calendar-year plan; for any
            // other plan year the governing figure depends on the plan year
            // start date, which is not an input here.
            if (($rules['planYearIsCalendarYear'] ?? null) === false) {
                $indeterminate = true;
                $diagnostics[] = self::diagnostic(
                    'HEALTH_FSA_NON_CALENDAR_PLAN_YEAR_INDETERMINATE',
                    DiagnosticSeverity::ERROR,
                    'Notice 2012-40 section III holds that "taxable year" in IRC 125(i) means the plan year of the '
                        . 'cafeteria plan, so a non-calendar plan year is governed by the figure for the calendar year '
                        . 'in which that plan year begins, and a short plan year is prorated by its months. This '
                        . 'package is keyed by tax year and does not hold the plan year start date, so the applicable '
                        . 'limit cannot be determined. Key the scenario to the tax year in which the plan year begins, '
                        . 'or supply the arrangement as a calendar-year plan.',
                    "{$path}.planRules.healthFsa.planYearIsCalendarYear",
                    'IRC 125(i); Notice 2012-40',
                );
            }

            $salaryReductionLimit = $yearParameters === null
                ? null
                : (float) $yearParameters['salaryReductionLimit'];
            $carryoverLimitForThisYear = $yearParameters === null
                ? null
                : (float) $yearParameters['carryoverLimit'];
            $planDocumentLimitEarly = array_key_exists('planDocumentLimit', $rules)
                ? self::money($rules['planDocumentLimit'], "{$path}.planRules.healthFsa.planDocumentLimit")
                : null;
            if ($yearParameters === null) {
                // No statutory ceiling is not the same as no answer. The
                // arrangement existed and IRC 125(a) excluded its salary
                // reductions; the ceiling was whatever the plan document
                // imposed. If the caller supplied that, the maximum is knowable
                // and withholding it would discard a fact they gave.
                $indeterminate = $planDocumentLimitEarly === null;
                $diagnostics[] = self::diagnostic(
                    $indeterminate
                        ? 'HEALTH_FSA_NO_STATUTORY_LIMIT_BEFORE_2013'
                        : 'HEALTH_FSA_LIMIT_RESTS_ENTIRELY_ON_PLAN_DOCUMENT',
                    $indeterminate ? DiagnosticSeverity::ERROR : DiagnosticSeverity::WARNING,
                    $indeterminate
                        ? 'IRC 125(i) was added by the Patient Protection and Affordable Care Act, Pub. L. 111-148 '
                            . 'section 9005, and Notice 2012-40 reads its effective date as reaching plan years '
                            . 'beginning after December 31, 2012, so no statutory salary reduction ceiling existed '
                            . "for tax year {$taxYear}. Health flexible spending arrangements did exist; what did "
                            . 'not exist is a statutory limit, so the ceiling was whatever the plan document '
                            . 'imposed. None was supplied, so none is reported.'
                        : "No statutory salary reduction ceiling existed for tax year {$taxYear}: IRC 125(i) reaches "
                            . 'plan years beginning after December 31, 2012. The arrangement existed and IRC 125(a) '
                            . 'excluded its salary reductions, and the plan document supplied here is the only '
                            . 'ceiling there was, so the reported maximum rests entirely on that supplied plan term '
                            . 'and on nothing statutory.',
                    'taxYear',
                    'IRC 125(i); Notice 2012-40',
                );
            }

            $planDocumentLimit = $planDocumentLimitEarly;
            $appliedLimit = $salaryReductionLimit ?? $planDocumentLimit;
            if ($appliedLimit !== null && $planDocumentLimit !== null && $planDocumentLimit < $appliedLimit) {
                $appliedLimit = $planDocumentLimit;
                $diagnostics[] = self::diagnostic(
                    'HEALTH_FSA_PLAN_DOCUMENT_LIMIT_APPLIED',
                    DiagnosticSeverity::INFO,
                    'The plan document limits salary reduction contributions to $'
                        . self::localeNumber($planDocumentLimit) . ', below the IRC 125(i) ceiling of $'
                        . self::localeNumber($salaryReductionLimit ?? 0.0)
                        . '. Notice 2013-71 confirms a plan may specify a lower amount.',
                    "{$path}.planRules.healthFsa.planDocumentLimit",
                    'IRC 125(i)',
                );
            }

            // Notice 2013-71: a plan may offer a carryover or a Prop. Treas.
            // Reg. 1.125-1(e) grace period for the same health FSA, or neither,
            // but never both. Asserting both describes a plan that cannot exist,
            // so the result is refused rather than computed from one of the two
            // facts.
            $carryoverFromPriorYear = 0.0;
            $carryoverLimitForPriorYear = $priorYearParameters === null
                ? null
                : (float) $priorYearParameters['carryoverLimit'];
            $forfeitedAmount = null;
            $offersCarryover = $rules['offersCarryover'] ?? null;
            $offersGracePeriod = $rules['offersGracePeriod'] ?? null;
            if ($offersCarryover === true && $offersGracePeriod === true) {
                $indeterminate = true;
                $carryoverLimitForPriorYear = null;
                $diagnostics[] = self::diagnostic(
                    'HEALTH_FSA_CARRYOVER_AND_GRACE_PERIOD_ARE_MUTUALLY_EXCLUSIVE',
                    DiagnosticSeverity::ERROR,
                    'Notice 2013-71 section IV holds that a section 125 cafeteria plan incorporating a carryover may '
                        . 'not also provide a grace period in the plan year to which unused amounts are carried. Both '
                        . 'were asserted, which describes a plan that cannot exist, so no carryover figure is produced.',
                    "{$path}.planRules.healthFsa",
                    'Notice 2013-71',
                );
            } elseif ($offersCarryover === true) {
                if ($carryoverLimitForPriorYear === null) {
                    $indeterminate = true;
                    $diagnostics[] = self::diagnostic(
                        'HEALTH_FSA_PRIOR_YEAR_CARRYOVER_LIMIT_NOT_ESTABLISHED',
                        DiagnosticSeverity::ERROR,
                        'The carryover cap belongs to the plan year the unused amount is carried FROM, and no cap is '
                            . 'encoded for ' . ($taxYear - 1) . '. Notice 2013-71 created the carryover for plan years '
                            . "beginning in 2013, so nothing could be carried into {$taxYear}.",
                        "{$path}.planRules.healthFsa.offersCarryover",
                        'Notice 2013-71',
                    );
                } else {
                    $carryoverFromPriorYear = self::minMoney($priorYearUnused, $carryoverLimitForPriorYear);
                    $forfeitedAmount = self::nonnegative($priorYearUnused - $carryoverFromPriorYear);
                    $diagnostics[] = self::diagnostic(
                        'HEALTH_FSA_CARRYOVER_DOES_NOT_REDUCE_THE_LIMIT',
                        DiagnosticSeverity::INFO,
                        'Of $' . self::localeNumber($priorYearUnused) . ' unused at the end of the ' . ($taxYear - 1)
                            . ' plan year, $' . self::localeNumber($carryoverFromPriorYear) . ' carries over, being '
                            . 'the lesser of that amount and the $' . self::localeNumber($carryoverLimitForPriorYear)
                            . ' cap for that year; $' . self::localeNumber($forfeitedAmount ?? 0.0)
                            . ' is forfeited. Notice 2013-71 holds that the carryover "does not count against or '
                            . 'otherwise affect" the IRC 125(i) salary reduction limit, so it sits on top of this '
                            . "year's ceiling rather than reducing it.",
                        "{$path}.planRules.healthFsa.priorYearUnusedAmount",
                        'Notice 2013-71; Notice 2020-33',
                    );
                    if ($taxYear - 1 === 2020 || $taxYear - 1 === 2021) {
                        $diagnostics[] = self::diagnostic(
                            'HEALTH_FSA_SECTION_214_RELIEF_NOT_MODELLED',
                            DiagnosticSeverity::WARNING,
                            'Section 214 of the Consolidated Appropriations Act, 2021, Pub. L. 116-260, implemented by '
                                . 'Notice 2021-15, permitted a plan to carry over ALL unused amounts from a plan year '
                                . 'ending in ' . ($taxYear - 1) . ', without the ordinary cap. Adopting it was '
                                . 'entirely a plan option that this engine cannot read, so the ordinary $'
                                . self::localeNumber($carryoverLimitForPriorYear) . ' cap has been applied. If the '
                                . 'plan adopted section 214 relief, the carried amount is the full unused amount and '
                                . 'this figure is too low.',
                            "{$path}.planRules.healthFsa.priorYearUnusedAmount",
                            'Pub. L. 116-260 s.214; Notice 2021-15',
                        );
                    }
                }
            } elseif ($offersGracePeriod === true) {
                $carryoverLimitForPriorYear = null;
                $diagnostics[] = self::diagnostic(
                    'HEALTH_FSA_GRACE_PERIOD_PRECLUDES_CARRYOVER',
                    DiagnosticSeverity::INFO,
                    'The plan offers a Prop. Treas. Reg. 1.125-1(e) grace period of up to two months and 15 days, so '
                        . 'under Notice 2013-71 it may not also carry unused amounts over and nothing is carried in. '
                        . "How much of the prior year's unused amount survives depends on expenses incurred during the "
                        . 'grace period, which is not a tax parameter, so no forfeiture figure is produced.',
                    "{$path}.planRules.healthFsa.offersGracePeriod",
                    'Notice 2005-42; Notice 2013-71',
                );
            } elseif ($offersCarryover === false) {
                $carryoverLimitForPriorYear = null;
                $forfeitedAmount = $priorYearUnused;
                if ($priorYearUnused > 0) {
                    $diagnostics[] = self::diagnostic(
                        'HEALTH_FSA_UNUSED_AMOUNTS_FORFEITED',
                        DiagnosticSeverity::INFO,
                        'The plan offers neither a carryover nor a grace period, so the use-or-lose rule forfeits the '
                            . 'whole $' . self::localeNumber($priorYearUnused) . ' unused at the end of the '
                            . ($taxYear - 1) . ' plan year.',
                        "{$path}.planRules.healthFsa.priorYearUnusedAmount",
                        'Prop. Treas. Reg. 1.125-5(c); Notice 2013-71',
                    );
                }
            } elseif ($priorYearUnused > 0) {
                $carryoverLimitForPriorYear = null;
                $status = CalculationStatus::DETERMINATE_WITH_ASSUMPTIONS->value;
                $diagnostics[] = self::diagnostic(
                    'HEALTH_FSA_CARRYOVER_FACT_REQUIRED',
                    DiagnosticSeverity::WARNING,
                    'A prior-year unused amount of $' . self::localeNumber($priorYearUnused) . ' was supplied without '
                        . 'stating whether the plan offers the Notice 2013-71 carryover or a grace period. Both are '
                        . 'plan options this engine cannot read and they are mutually exclusive, so nothing is carried '
                        . 'in and no forfeiture figure is produced. Supply planRules.healthFsa.offersCarryover or '
                        . 'offersGracePeriod.',
                    "{$path}.planRules.healthFsa.offersCarryover",
                    'Notice 2013-71',
                );
            }

            $statutoryMaximum = $indeterminate || $salaryReductionLimit === null
                ? null
                : self::nonnegative(self::roundMoney((float) $salaryReductionLimit - $flexCreditCounted));
            $appliedMaximum = $indeterminate || $appliedLimit === null
                ? null
                : self::nonnegative(self::roundMoney($appliedLimit - $flexCreditCounted));

            // IRC 125(i) is a plan-qualification condition rather than a cap the
            // plan may exceed and then correct: Notice 2012-40 holds that a
            // cafeteria plan failing to comply is not a section 125 cafeteria
            // plan at all, and the value of the taxable benefits the employee
            // could have elected is includible regardless of what was elected.
            // Truncating the election to the limit would report the wrong
            // consequence.
            $electedWithCredits = self::roundMoney($elected + $flexCreditCounted);
            // Exceeding the statutory ceiling and exceeding a lower
            // plan-document ceiling are different failures. Notice 2012-40
            // section III attaches its loss-of-IRC-125-status consequence to
            // the IRC 125(i) limit, so only the statutory breach carries it; a
            // plan-document breach is reported without asserting that the whole
            // exclusion fails.
            if ($appliedMaximum !== null
                && $salaryReductionLimit !== null
                && $electedWithCredits > (float) $salaryReductionLimit + 0.009
            ) {
                $diagnostics[] = self::diagnostic(
                    'HEALTH_FSA_ELECTION_EXCEEDS_SECTION_125I_LIMIT',
                    DiagnosticSeverity::ERROR,
                    'Salary reduction contributions of $'
                        . self::localeNumber(self::roundMoney($elected + $flexCreditCounted)) . ' exceed the $'
                        . self::localeNumber((float) $appliedLimit) . ' limit that applies. Notice 2012-40 holds that '
                        . 'a cafeteria plan permitting an election above IRC 125(i) is not a section 125 cafeteria '
                        . 'plan, so the value of the taxable benefits the employee could have elected becomes '
                        . 'includible in gross income regardless of the benefit elected. The excess is not truncated '
                        . 'here because truncation would report a smaller consequence than the statute produces.',
                    "{$path}.existingContributions.healthFsaSalaryReduction",
                    'IRC 125(i); IRC 125(d)(1)(B); Notice 2012-40',
                );
            } elseif ($appliedMaximum !== null
                && $appliedLimit !== null
                && $electedWithCredits > (float) $appliedLimit + 0.009
            ) {
                $diagnostics[] = self::diagnostic(
                    'HEALTH_FSA_ELECTION_EXCEEDS_PLAN_DOCUMENT_LIMIT',
                    DiagnosticSeverity::ERROR,
                    'Salary reduction contributions of $' . self::localeNumber($electedWithCredits)
                        . ' exceed the $' . self::localeNumber((float) $appliedLimit) . ' the plan document allows, '
                        . ($salaryReductionLimit === null
                            ? "and no IRC 125(i) ceiling existed for {$taxYear} to exceed"
                            : 'though they remain within the $' . self::localeNumber((float) $salaryReductionLimit)
                                . ' IRC 125(i) ceiling')
                        . '. Notice 2013-71 confirms a plan may specify a lower amount, and an '
                        . "election above the plan's own term is a plan-operation question this engine does not "
                        . 'resolve. The Notice 2012-40 section III loss of IRC 125 status is not asserted here, '
                        . 'because that holding addresses the IRC 125(i) limit rather than a lower plan term.',
                    "{$path}.existingContributions.healthFsaSalaryReduction",
                    'IRC 125(d)(1)(B); Notice 2013-71',
                );
            }

            $diagnostics[] = self::diagnostic(
                'HEALTH_FSA_FACTS_SUPPLIED_BY_CALLER',
                DiagnosticSeverity::INFO,
                'This calculation applies IRC 125(i) to the plan facts supplied. Plan design is not inferred: whether '
                    . 'the plan offers a carryover or a grace period, whether flex credits could be elected as cash, '
                    . "the arrangement's Rev. Rul. 2004-45 purpose, and any lower plan-document limit are all "
                    . 'caller-supplied. It does not test cafeteria plan qualification under IRC 125(b) through (d), '
                    . 'nondiscrimination, the IRC 414(b), (c) and (m) controlled-group aggregation that IRC 125(g)(4) '
                    . 'applies to the limit, the Notice 2012-40 proration of a short plan year, or the '
                    . 'uniform-coverage and run-out-period mechanics.',
                $path,
                'IRC 125(i)',
            );

            $detail = [
                'purpose' => $purpose,
                'salaryReductionLimit' => $salaryReductionLimit,
                'appliedSalaryReductionLimit' => $indeterminate ? null : $appliedLimit,
                'electedSalaryReduction' => $elected,
                'employerFlexCreditCountedAgainstLimit' => $flexCreditCounted,
                'carryoverFromPriorYear' => $carryoverFromPriorYear,
                'carryoverLimitForPriorYear' => $carryoverLimitForPriorYear,
                'carryoverLimitForThisYear' => $carryoverLimitForThisYear,
                'forfeitedAmount' => $forfeitedAmount,
                'disqualifiesHsaEligibility' => $purpose === null ? null : $purpose === 'general_purpose',
            ];

            if ($indeterminate) {
                $context['healthFsaPlans'][(string) $account['id']] = [
                    'status' => CalculationStatus::INDETERMINATE->value,
                    'diagnostics' => $diagnostics,
                    'statutoryMaximum' => null,
                    'appliedMaximum' => null,
                    'detail' => $detail,
                    'poolKey' => null,
                ];
                continue;
            }

            $poolKey = self::healthFsaPoolKey($account);
            if (!isset($context['healthFsaPools'][$poolKey])) {
                $context['healthFsaPools'][$poolKey] = [
                    'id' => "irc-125i:{$poolKey}",
                    'legalLimit' => 'IRC 125(i) health FSA salary reduction limit, per employee per employer',
                    // The pool is the statutory ceiling the employers treated
                    // as one under IRC 125(g)(4) share. A lower limit in one
                    // plan document is a term of that plan and binds elections
                    // under it; it gives that plan no power over elections
                    // under a different plan of the same group, so it caps the
                    // account rather than the pool.
                    'limit' => $statutoryMaximum === null ? null : $salaryReductionLimit,
                    'used' => 0.0,
                ];
            }
            $context['healthFsaPools'][$poolKey]['used'] = self::roundMoney(
                (float) $context['healthFsaPools'][$poolKey]['used'] + $flexCreditCounted + $elected,
            );

            $context['healthFsaPlans'][(string) $account['id']] = [
                'status' => self::accountStatusFromDiagnostics($status, $diagnostics),
                'diagnostics' => $diagnostics,
                'statutoryMaximum' => $statutoryMaximum,
                'appliedMaximum' => $appliedMaximum,
                'detail' => $detail,
                'poolKey' => $poolKey,
            ];
        }
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @return array<string,mixed>
     */
    private static function allocateHealthFsa(array &$context, array $account): array
    {
        $plan = $context['healthFsaPlans'][(string) $account['id']];
        $annual = $account['existingContributions'];
        $additional = self::zeroComponents();
        $sharedLimits = [];
        $diagnostics = $plan['diagnostics'];
        $poolKey = $plan['poolKey'];
        $hasPool = $poolKey !== null && isset($context['healthFsaPools'][$poolKey]);

        if ($plan['status'] === CalculationStatus::INDETERMINATE->value || !$hasPool) {
            if ($hasPool) {
                self::reportPoolWithoutConsuming($context['healthFsaPools'][$poolKey], $sharedLimits);
            }
            return [
                'status' => CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => $plan['statutoryMaximum'],
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
                'healthFsaDetail' => $plan['detail'],
            ];
        }

        // The pool offers the employer group's statutory room; this account may
        // only reach its own plan document's ceiling within it.
        $localHeadroom = ($plan['appliedMaximum'] ?? null) === null
            ? (self::poolRemaining($context['healthFsaPools'][$poolKey]) ?? 0.0)
            : self::nonnegative(self::roundMoney(
                (float) $plan['appliedMaximum'] - (float) $account['existingContributions']['healthFsaSalaryReduction'],
            ));
        if ($context['healthFsaPools'][$poolKey]['limit'] === null && ($plan['appliedMaximum'] ?? null) !== null) {
            // A year with no IRC 125(i) ceiling has no employer-group figure to
            // share, so there is nothing to draw down: the account is bounded by
            // its own plan document alone. Putting that document into the shared
            // pool instead would let one plan's term bind another plan of the
            // same group, which is the thing IRC 125(g)(4) does not do, and
            // would make the result depend on which account reached the pool
            // first.
            self::reportPoolWithoutConsuming($context['healthFsaPools'][$poolKey], $sharedLimits);
            $taken = $localHeadroom;
        } else {
            $taken = self::takeFromPool($context['healthFsaPools'][$poolKey], $localHeadroom, $sharedLimits);
        }
        $additional['healthFsaSalaryReduction'] = $taken;
        $annual['healthFsaSalaryReduction'] = self::roundMoney($annual['healthFsaSalaryReduction'] + $taken);

        return [
            'status' => self::accountStatusFromDiagnostics($plan['status'], $diagnostics),
            'statutoryMaximum' => $plan['statutoryMaximum'],
            'annualComponents' => $annual,
            'additionalComponents' => $additional,
            'planTermDependentCapacity' => 0.0,
            'sharedLimits' => $sharedLimits,
            'diagnostics' => $diagnostics,
            'healthFsaDetail' => $plan['detail'],
        ];
    }

    /**
     * IRC 129 was added by the Economic Recovery Tax Act of 1981, Pub. L.
     * 97-34, and the effective-date note to the section makes it applicable to
     * taxable years beginning after December 31, 1981. Before that the section
     * did not exist; from 1982 through 1986 it existed with no dollar
     * limitation, which the Tax Reform Act of 1986 Pub. L. 99-514 s.1163 added
     * for taxable years beginning after December 31, 1986. The three states
     * report differently.
     */
    private const DEPENDENT_CARE_FIRST_TAX_YEAR = 1982;

    /**
     * IRC 129(a)(2)(A) is a per-return amount, not a per-person one, and only a
     * joint return puts two people on one return. Spouses filing jointly
     * therefore share one pool; everybody else, including each spouse of a
     * married-separate pair filing their own return at the halved amount,
     * carries their own.
     *
     * @param array<string,mixed> $context
     */
    private static function dependentCarePoolKey(array $context, string $ownerId): string
    {
        if ($context['filingStatus'] === FilingStatus::MARRIED_FILING_JOINTLY->value) {
            $role = $context['persons'][$ownerId]['role'] ?? null;
            if ($role === 'taxpayer' || $role === 'spouse') {
                return 'return';
            }
        }
        return "return:{$ownerId}";
    }

    /** @param array<string,mixed> $context
     *  @param list<array<string,mixed>> $accounts
     */
    private static function initializeDependentCarePools(array &$context, array $accounts): void
    {
        // The IRC 129(a)(2)(A) amount is one figure for the return, so which
        // account draws on it first decides which employee's assistance is
        // includible. That must follow the same deterministic order the
        // allocation uses.
        $dcAccounts = [];
        foreach ($accounts as $account) {
            if (self::traits($account['type'])['family'] === 'dependent_care_fsa') {
                $dcAccounts[] = $account;
            }
        }
        if ($dcAccounts === []) {
            return;
        }
        usort(
            $dcAccounts,
            static fn (array $left, array $right): int =>
                (($left['priority'] ?? 100) <=> ($right['priority'] ?? 100))
                ?: ($left['inputIndex'] <=> $right['inputIndex']),
        );

        $taxYear = (int) $context['taxYear'];
        $dependentCareYear = $context['fsaParameters']['dependentCare'] ?? null;
        $yearParameters = ($dependentCareYear['state'] ?? null) === 'statutory_dollar_limit'
            ? $dependentCareYear
            : null;
        // IRC 129(a)(2)(C) determines marital status under IRC 21(e)(3) and (4),
        // so the halved amount and the lesser-of earned income rule do not
        // follow from the filing status alone. IRC 21(e)(4) treats a
        // married-separate taxpayer as not married when they maintained a
        // qualifying individual's principal place of abode for more than half
        // the year, furnished over half its cost, and their spouse was not a
        // member of the household for the last six months.
        $separateReturn = $context['filingStatus'] === FilingStatus::MARRIED_FILING_SEPARATELY->value;
        $treatedAsUnmarried = $separateReturn
            && ($context['treatedAsUnmarriedUnderSection21e4'] ?? null) === true;
        $married = $context['filingStatus'] === FilingStatus::MARRIED_FILING_JOINTLY->value
            || ($separateReturn && !$treatedAsUnmarried);
        $statutoryExclusion = null;
        if ($yearParameters !== null) {
            $statutoryExclusion = $separateReturn && !$treatedAsUnmarried
                ? (float) $yearParameters['marriedFilingSeparatelyExclusionLimit']
                : (float) $yearParameters['exclusionLimit'];
        }
        $section21e4Undetermined = $separateReturn
            && ($context['treatedAsUnmarriedUnderSection21e4'] ?? null) === null;

        foreach ($dcAccounts as $account) {
            $rules = $account['planRules']['dependentCareFsa'] ?? [];
            if (!is_array($rules)) {
                $rules = [];
            }
            $path = "accounts.{$account['id']}";
            $diagnostics = [];
            $status = CalculationStatus::DETERMINATE->value;
            $indeterminate = false;
            $unavailable = false;
            $earnedIncomeFactsMissing = false;

            $elected = (float) $account['existingContributions']['dependentCareAssistanceProvided'];

            $noStatutoryCeiling = false;
            if ($yearParameters === null) {
                // A year present in the table but carrying no statutory ceiling
                // is not an unavailable year: IRC 129 existed and excluded the
                // assistance, there was simply no dollar cap. Whether an answer
                // is possible then depends on whether the caller supplied a
                // ceiling of their own, which is not known until the plan and
                // earned income facts below have been read.
                $unavailable = $dependentCareYear === null;
                $noStatutoryCeiling = !$unavailable;
                $indeterminate = $unavailable;
                if ($unavailable) {
                    $diagnostics[] = self::diagnostic(
                        'DEPENDENT_CARE_NOT_AVAILABLE_FOR_TAX_YEAR',
                        DiagnosticSeverity::ERROR,
                        'IRC 129 was added by the Economic Recovery Tax Act of 1981, Pub. L. 97-34, applicable to '
                            . 'taxable years beginning after December 31, 1981, so no dependent care assistance '
                            . "exclusion existed for tax year {$taxYear}.",
                        'taxYear',
                        'IRC 129; Pub. L. 97-34 s.124(f)',
                    );
                }
            }

            // IRC 129(b)(1): the exclusion cannot exceed the employee's earned
            // income, or, for a married employee, the lesser of the employee's
            // and the spouse's. Both figures are caller-supplied; this package
            // does not derive income.
            $owner = $context['persons'][(string) $account['ownerId']] ?? null;
            $ownerSpouse = $owner === null ? null : self::spouseForPerson($context['persons'], $owner);
            $employeeEarnedIncome = $owner['dependentCareEarnedIncome'] ?? null;
            $spouseEarnedIncome = $ownerSpouse['dependentCareEarnedIncome'] ?? null;
            $earnedIncomeLimitation = null;
            if ($employeeEarnedIncome !== null && (!$married || $spouseEarnedIncome !== null)) {
                $earnedIncomeLimitation = $married
                    ? self::minMoney($employeeEarnedIncome, $spouseEarnedIncome)
                    : $employeeEarnedIncome;
            } elseif (!$indeterminate) {
                // IRC 129(b)(1) is a mandatory ceiling, not an optional
                // refinement. Reporting the IRC 129(a)(2)(A) amount as the
                // maximum the inputs support would assume earned income of at
                // least that amount, which the statute never permits. The
                // statutory figure is still reported separately, so failing
                // closed withholds an assumption rather than information.
                $earnedIncomeFactsMissing = true;
                $diagnostics[] = self::diagnostic(
                    'DEPENDENT_CARE_EARNED_INCOME_FACTS_REQUIRED',
                    DiagnosticSeverity::ERROR,
                    $married
                        ? "IRC 129(b)(1)(B) caps the exclusion at the lesser of the employee's and the spouse's earned "
                            . 'income for the taxable year. Both are caller-supplied facts and at least one was not '
                            . 'supplied, so the limitation has not been applied and the reported ceiling is the IRC '
                            . '129(a)(2)(A) amount alone, which may overstate it.'
                        : "IRC 129(b)(1)(A) caps the exclusion at the employee's earned income for the taxable year. "
                            . 'That is a caller-supplied fact and was not supplied, so the limitation has not been '
                            . 'applied and the reported ceiling is the IRC 129(a)(2)(A) amount alone, which may '
                            . 'overstate it.',
                    "persons.{$account['ownerId']}.dependentCareEarnedIncome",
                    'IRC 129(b)(1)',
                );
            }
            if ($section21e4Undetermined && !$indeterminate) {
                $status = CalculationStatus::DETERMINATE_WITH_ASSUMPTIONS->value;
                $diagnostics[] = self::diagnostic(
                    'DEPENDENT_CARE_SECTION_21E4_DETERMINATION_NOT_MADE',
                    DiagnosticSeverity::WARNING,
                    'IRC 129(a)(2)(C) determines marital status under IRC 21(e)(3) and (4), and IRC 21(e)(4) treats '
                        . 'a married individual filing separately as not married when they maintained a household '
                        . "that was a qualifying individual's principal place of abode for more than half the "
                        . 'taxable year, furnished over half the cost of maintaining it, and their spouse was not a '
                        . 'member of it during the last six months of the year. Those facts are not derivable from '
                        . 'anything supplied here and treatedAsUnmarriedUnderSection21e4 was not stated, so the '
                        . 'return has been treated as married: the halved IRC 129(a)(2)(A) amount and the '
                        . 'IRC 129(b)(1)(B) lesser-of-earned-income rule are applied. A taxpayer who meets '
                        . 'IRC 21(e)(4) takes the undivided amount and their own earned income instead.',
                    'treatedAsUnmarriedUnderSection21e4',
                    'IRC 129(a)(2)(C); IRC 21(e)(4)',
                );
            }
            if ((($owner['isStudentOrIncapableOfSelfCare'] ?? null) === true
                || ($ownerSpouse['isStudentOrIncapableOfSelfCare'] ?? null) === true) && !$indeterminate) {
                $status = CalculationStatus::DETERMINATE_WITH_ASSUMPTIONS->value;
                $diagnostics[] = self::diagnostic(
                    'DEPENDENT_CARE_DEEMED_SPOUSE_EARNED_INCOME_NOT_MODELLED',
                    DiagnosticSeverity::WARNING,
                    'IRC 129(b)(2) applies IRC 21(d)(2) to deem earned income for a spouse who is a student or '
                        . 'incapable of caring for himself. The IRC 21(d)(2) monthly schedule is not encoded here, '
                        . "because no primary source for it is committed to this package's evidence corpus and an "
                        . 'unattested figure is never encoded. Any dependentCareEarnedIncome supplied for the person is used exactly as '
                        . 'stated, so supply the deemed amount if the deeming applies.',
                    "persons.{$account['ownerId']}.isStudentOrIncapableOfSelfCare",
                    'IRC 129(b)(2); IRC 21(d)(2)',
                );
            }

            $planDocumentLimit = array_key_exists('planDocumentLimit', $rules)
                ? self::money($rules['planDocumentLimit'], "{$path}.planRules.dependentCareFsa.planDocumentLimit")
                : null;
            $suppliedCeilings = [];
            if ($earnedIncomeLimitation !== null) {
                $suppliedCeilings[] = $earnedIncomeLimitation;
            }
            if ($planDocumentLimit !== null) {
                $suppliedCeilings[] = $planDocumentLimit;
            }
            if ($noStatutoryCeiling && count($suppliedCeilings) === 0) {
                // Nothing statutory and nothing supplied leaves no ceiling.
                $indeterminate = true;
            }
            if ($noStatutoryCeiling) {
                $diagnostics[] = self::diagnostic(
                    $indeterminate
                        ? 'DEPENDENT_CARE_NO_EXCLUSION_LIMIT_BEFORE_1987'
                        : 'DEPENDENT_CARE_LIMIT_RESTS_ENTIRELY_ON_SUPPLIED_FACTS',
                    $indeterminate ? DiagnosticSeverity::ERROR : DiagnosticSeverity::WARNING,
                    $indeterminate
                        ? 'The IRC 129(a)(2)(A) limitation of exclusion was added by the Tax Reform Act of 1986, '
                            . 'Pub. L. 99-514 section 1163(a), applicable to taxable years beginning after '
                            . "December 31, 1986. For tax year {$taxYear} IRC 129 existed and excluded "
                            . 'employer-provided dependent care assistance, but carried no dollar ceiling on the '
                            . 'exclusion. None was supplied either, so none is reported.'
                        : "No IRC 129(a)(2)(A) dollar ceiling existed for tax year {$taxYear}: Pub. L. 99-514 "
                            . 'section 1163(a) added it for taxable years beginning after December 31, 1986. '
                            . 'IRC 129 did exist and excluded the assistance, and the IRC 129(b)(1) earned income '
                            . 'limitation and any plan maximum supplied here are the only ceilings there were, so '
                            . 'the reported maximum rests entirely on those supplied facts and on nothing '
                            . 'statutory.',
                    'taxYear',
                    'IRC 129(a)(2)(A); Pub. L. 99-514 s.1163',
                );
            }
            $applicableLimit = null;
            if (!$indeterminate && !$earnedIncomeFactsMissing) {
                if ($statutoryExclusion === null) {
                    $applicableLimit = self::minMoney(...$suppliedCeilings);
                } else {
                    $applicableLimit = self::minMoney($statutoryExclusion, ...$suppliedCeilings);
                }
            }

            $diagnostics[] = self::diagnostic(
                'DEPENDENT_CARE_FACTS_SUPPLIED_BY_CALLER',
                DiagnosticSeverity::INFO,
                'This calculation applies IRC 129(a)(2) and, where the earned income facts are supplied, IRC '
                    . '129(b)(1). It does not test whether the program meets the IRC 129(d) written-plan '
                    . 'requirements, the IRC 129(d)(2) through (8) nondiscrimination rules, the IRC 129(c) denial for '
                    . 'amounts paid to a related individual, or whether the individuals cared for qualify. It does not '
                    . "model the IRC 21 dependent care credit or the IRC 21(c) reduction of that credit's expense "
                    . 'base. A dependent care program may not offer a carryover at all except under section 214 of the '
                    . 'Consolidated Appropriations Act, 2021, which is a plan option this engine cannot read, so no '
                    . 'dependent care carryover is modelled.',
                $path,
                'IRC 129',
            );

            $detail = [
                'statutoryExclusion' => $indeterminate ? null : $statutoryExclusion,
                'earnedIncomeLimitation' => $earnedIncomeLimitation,
                'planDocumentLimit' => $planDocumentLimit,
                'applicableExclusionLimit' => $applicableLimit,
                'electedSalaryReduction' => $elected,
                'excludableAmount' => 0.0,
                'includibleInIncome' => 0.0,
                'householdExclusionShared' => false,
            ];

            if ($indeterminate) {
                $context['dependentCarePlans'][(string) $account['id']] = [
                    'status' => $unavailable
                        ? CalculationStatus::UNAVAILABLE->value
                        : CalculationStatus::INDETERMINATE->value,
                    'diagnostics' => $diagnostics,
                    'statutoryMaximum' => $unavailable ? 0.0 : null,
                    'detail' => $detail,
                    'poolKey' => null,
                    'earnedIncomeLimitation' => $earnedIncomeLimitation,
                    'planDocumentLimit' => $planDocumentLimit,
                ];
                continue;
            }

            $poolKey = self::dependentCarePoolKey($context, (string) $account['ownerId']);
            if (!isset($context['dependentCarePools'][$poolKey])) {
                $context['dependentCarePools'][$poolKey] = [
                    'id' => "irc-129:{$poolKey}",
                    'legalLimit' => 'IRC 129(a)(2)(A) dependent care assistance exclusion, per return',
                    'limit' => $statutoryExclusion,
                    'used' => 0.0,
                ];
            }

            $context['dependentCarePlans'][(string) $account['id']] = [
                'status' => self::accountStatusFromDiagnostics($status, $diagnostics),
                'diagnostics' => $diagnostics,
                // The IRC 129(a)(2)(A) figure itself. What the supplied facts
                // allow within it is the applicable limit, reported separately,
                // exactly as the health FSA path separates the IRC 125(i)
                // ceiling from a plan document.
                'statutoryMaximum' => $indeterminate ? null : $statutoryExclusion,
                'detail' => $detail,
                'poolKey' => $poolKey,
                'earnedIncomeLimitation' => $earnedIncomeLimitation,
                'planDocumentLimit' => $planDocumentLimit,
            ];
        }

        // A pool serving more than one account is a household figure two
        // employees draw on, which IRC 129(a)(2)(A) makes visible rather than
        // doubling.
        $accountsByPool = [];
        foreach ($context['dependentCarePlans'] as $plan) {
            if ($plan['poolKey'] === null) {
                continue;
            }
            $accountsByPool[$plan['poolKey']] = ($accountsByPool[$plan['poolKey']] ?? 0) + 1;
        }
        foreach ($context['dependentCarePlans'] as $accountId => $plan) {
            if ($plan['poolKey'] !== null && ($accountsByPool[$plan['poolKey']] ?? 0) > 1) {
                $context['dependentCarePlans'][$accountId]['detail']['householdExclusionShared'] = true;
            }
        }

        // IRC 129(b)(1) caps "the amount excluded from the income of an
        // employee under subsection (a) for any taxable year", which is that
        // year's aggregate rather than a per-plan figure, and Form 2441 Part III
        // computes a single excluded-benefits amount for the return. The
        // ceiling therefore belongs to the pool the accounts share.
        //
        // Every plan in a pool derives it from the same two people, so they
        // cannot disagree about it. That was not true while the figures lived
        // on each account's plan rules, and the contradiction then had to be
        // reported as an error; moving them onto the person removed the
        // possibility instead.
        foreach ($context['dependentCarePlans'] as $plan) {
            if ($plan['poolKey'] === null || $plan['earnedIncomeLimitation'] === null) {
                continue;
            }
            $context['dependentCareEarnedIncomeCeilings'][$plan['poolKey']] =
                (float) $plan['earnedIncomeLimitation'];
        }

        // Assistance actually supplied draws on the household amount before any
        // remaining capacity is offered, so what IRC 129(a)(2)(B) includes in
        // income is measured against the amounts supplied rather than against
        // capacity the scenario merely reports as available.
        foreach ($dcAccounts as $account) {
            $accountId = (string) $account['id'];
            if (!isset($context['dependentCarePlans'][$accountId])) {
                continue;
            }
            $plan = $context['dependentCarePlans'][$accountId];
            if ($plan['poolKey'] === null || !isset($context['dependentCarePools'][$plan['poolKey']])) {
                continue;
            }
            $elected = (float) $account['existingContributions']['dependentCareAssistanceProvided'];
            if ($elected <= 0) {
                continue;
            }
            $householdRemaining = self::poolRemaining($context['dependentCarePools'][$plan['poolKey']]) ?? 0.0;
            $earnedIncomeCeiling = $context['dependentCareEarnedIncomeCeilings'][$plan['poolKey']] ?? null;
            // Measured against what the pool has already excluded, not against
            // this account alone: the IRC 129(b)(1) ceiling is the return's for
            // the year.
            $ceilingCandidates = [$householdRemaining];
            if ($earnedIncomeCeiling !== null) {
                $ceilingCandidates[] = self::nonnegative(self::roundMoney(
                    (float) $earnedIncomeCeiling - (float) $context['dependentCarePools'][$plan['poolKey']]['used'],
                ));
            }
            if (($plan['planDocumentLimit'] ?? null) !== null) {
                $ceilingCandidates[] = (float) $plan['planDocumentLimit'];
            }
            $ceiling = self::minMoney(...$ceilingCandidates);
            $excludable = self::minMoney($elected, $ceiling);
            $includible = self::roundMoney($elected - $excludable);
            $context['dependentCarePools'][$plan['poolKey']]['used'] = self::roundMoney(
                (float) $context['dependentCarePools'][$plan['poolKey']]['used'] + $excludable,
            );
            $context['dependentCarePlans'][$accountId]['detail']['excludableAmount'] = $excludable;
            $context['dependentCarePlans'][$accountId]['detail']['includibleInIncome'] = $includible;
            if ($includible > 0) {
                array_unshift(
                    $context['dependentCarePlans'][$accountId]['diagnostics'],
                    self::diagnostic(
                        'DEPENDENT_CARE_AMOUNT_INCLUDIBLE_IN_INCOME',
                        DiagnosticSeverity::WARNING,
                        '$' . self::localeNumber($includible) . ' of the $' . self::localeNumber($elected)
                            . ' of dependent care assistance supplied exceeds the limitation that applies to this '
                            . 'employee, so IRC 129(a)(2)(B) includes it in gross income for the taxable year in which '
                            . 'the dependent care services were provided. The IRC 129(a)(2)(A) amount is a per-return '
                            . 'figure rather than a per-person one, so two employees on one return draw on a single '
                            . 'amount rather than one each.',
                        "accounts.{$account['id']}.existingContributions.dependentCareAssistanceProvided",
                        'IRC 129(a)(2)(B)',
                    ),
                );
                $context['dependentCarePlans'][$accountId]['status'] = self::accountStatusFromDiagnostics(
                    $context['dependentCarePlans'][$accountId]['status'],
                    $context['dependentCarePlans'][$accountId]['diagnostics'],
                );
            }
        }
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @return array<string,mixed>
     */
    private static function allocateDependentCareFsa(array &$context, array $account): array
    {
        $plan = $context['dependentCarePlans'][(string) $account['id']];
        $annual = $account['existingContributions'];
        $additional = self::zeroComponents();
        $sharedLimits = [];
        $diagnostics = $plan['diagnostics'];
        $detail = $plan['detail'];
        $poolKey = $plan['poolKey'];
        $hasPool = $poolKey !== null && isset($context['dependentCarePools'][$poolKey]);

        if ($plan['status'] === CalculationStatus::UNAVAILABLE->value
            || $plan['status'] === CalculationStatus::INDETERMINATE->value
            || !$hasPool
        ) {
            if ($hasPool) {
                self::reportPoolWithoutConsuming($context['dependentCarePools'][$poolKey], $sharedLimits);
            }
            // The components clone what the scenario supplied, so an elected
            // salary reduction would otherwise be reported as excluded by a
            // plan that has just said it cannot determine the exclusion. No
            // amount is substantiated here, and the detail already carries
            // zero for both halves.
            $annual['dependentCareAssistanceProvided'] = 0.0;
            $annual['dependentCareIncludibleInIncome'] = (float) $detail['includibleInIncome'];
            return [
                'status' => $plan['status'] === CalculationStatus::UNAVAILABLE->value
                    ? CalculationStatus::UNAVAILABLE->value
                    : CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => $plan['statutoryMaximum'],
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
                'dependentCareDetail' => $detail,
            ];
        }

        // The supplied assistance already drew on the household IRC 129(a)(2)(A)
        // amount, so what is left is the further exclusion this employee could
        // reach. The IRC 129(b)(1) ceiling is measured against everything the
        // pool has excluded rather than against this account alone, because it
        // caps the return's exclusion for the taxable year.
        $alreadyExcluded = (float) $detail['excludableAmount'];
        $earnedIncomeCeiling = $context['dependentCareEarnedIncomeCeilings'][$poolKey] ?? null;
        $ownCeilings = [];
        if ($earnedIncomeCeiling !== null) {
            $ownCeilings[] = self::nonnegative(self::roundMoney(
                (float) $earnedIncomeCeiling - (float) $context['dependentCarePools'][$poolKey]['used'],
            ));
        }
        if (($plan['planDocumentLimit'] ?? null) !== null) {
            $ownCeilings[] = self::nonnegative(self::roundMoney(
                (float) $plan['planDocumentLimit'] - $alreadyExcluded,
            ));
        }
        if ($context['dependentCarePools'][$poolKey]['limit'] === null && count($ownCeilings) > 0) {
            // A year with no IRC 129(a)(2)(A) ceiling has no household figure to
            // draw down. The supplied earned income and plan maximum are the
            // only ceilings there were, and they bound this account directly.
            self::reportPoolWithoutConsuming($context['dependentCarePools'][$poolKey], $sharedLimits);
            $additionalExcludable = self::minMoney(...$ownCeilings);
        } else {
            $headroom = self::minMoney(
                self::poolRemaining($context['dependentCarePools'][$poolKey]) ?? 0.0,
                ...$ownCeilings,
            );
            $additionalExcludable = self::takeFromPool(
                $context['dependentCarePools'][$poolKey],
                $headroom,
                $sharedLimits,
            );
        }

        $annual['dependentCareAssistanceProvided'] = self::roundMoney($alreadyExcluded + $additionalExcludable);
        $annual['dependentCareIncludibleInIncome'] = (float) $detail['includibleInIncome'];
        $additional['dependentCareAssistanceProvided'] = $additionalExcludable;
        $detail['excludableAmount'] = $annual['dependentCareAssistanceProvided'];

        return [
            'status' => self::accountStatusFromDiagnostics($plan['status'], $diagnostics),
            'statutoryMaximum' => $plan['statutoryMaximum'],
            'annualComponents' => $annual,
            'additionalComponents' => $additional,
            'planTermDependentCapacity' => 0.0,
            'sharedLimits' => $sharedLimits,
            'diagnostics' => $diagnostics,
            'dependentCareDetail' => $detail,
        ];
    }

    /**
     * What the health flexible spending arrangements in a scenario mean for IRC
     * 223 eligibility, collected per person.
     *
     * Rev. Rul. 2004-45 turns the answer entirely on what the arrangement may
     * reimburse: a general-purpose health FSA that pays section 213(d) medical
     * expenses before the IRC 223(c)(2)(A)(i) minimum annual deductible is
     * satisfied is coverage that is not a high deductible health plan and that
     * provides a benefit the HDHP covers, so IRC 223(c)(1)(A)(ii) is failed; a
     * limited-purpose arrangement reimbursing only vision, dental and
     * preventive care, or a post-deductible one reimbursing only after the
     * deductible is met, is not.
     *
     * @return array{generalPurpose:bool,purposeUnstated:bool,hsaCompatible:bool,generalPurposeCarryover:bool,generalPurposeGracePeriod:bool}
     */
    private static function emptyHealthFsaSection223Facts(): array
    {
        return [
            'generalPurpose' => false,
            'purposeUnstated' => false,
            'hsaCompatible' => false,
            'generalPurposeCarryover' => false,
            'generalPurposeGracePeriod' => false,
        ];
    }

    /** @param array<string,mixed> $context
     *  @param list<array<string,mixed>> $accounts
     *  @return array<string,array<string,bool>>
     */
    private static function healthFsaSection223FactsByOwner(array $context, array $accounts): array
    {
        $byOwner = [];
        foreach ($accounts as $account) {
            if (self::traits($account['type'])['family'] !== 'health_fsa') {
                continue;
            }
            $detail = $context['healthFsaPlans'][(string) $account['id']]['detail'] ?? null;
            if ($detail === null) {
                continue;
            }
            $ownerId = (string) $account['ownerId'];
            $byOwner[$ownerId] ??= self::emptyHealthFsaSection223Facts();
            $purpose = $detail['purpose'];
            if ($purpose === null) {
                $byOwner[$ownerId]['purposeUnstated'] = true;
            } elseif ($purpose === 'general_purpose') {
                $byOwner[$ownerId]['generalPurpose'] = true;
                if ((float) $detail['carryoverFromPriorYear'] > 0) {
                    $byOwner[$ownerId]['generalPurposeCarryover'] = true;
                }
                if (($account['planRules']['healthFsa']['offersGracePeriod'] ?? null) === true) {
                    $byOwner[$ownerId]['generalPurposeGracePeriod'] = true;
                }
            } else {
                $byOwner[$ownerId]['hsaCompatible'] = true;
            }
        }
        return $byOwner;
    }

    /** @param array<string,string>|null $couple
     *  @return string|null
     */
    private static function hsaSpouseIdOf(?array $couple, string $ownerId): ?string
    {
        if ($couple === null) {
            return null;
        }
        if ($couple[0] === $ownerId) {
            return $couple[1];
        }
        if ($couple[1] === $ownerId) {
            return $couple[0];
        }
        return null;
    }

    /** @param array<string,mixed> $context
     *  @param list<array<string,mixed>> $accounts
     */
    private static function initializeHsaPools(array &$context, array $accounts): void
    {
        $hsaAccounts = [];
        foreach ($accounts as $account) {
            if (self::traits($account['type'])['family'] === 'hsa') {
                $hsaAccounts[] = $account;
            }
        }
        if ($hsaAccounts === []) {
            return;
        }

        $ownerIds = [];
        $accountsByOwner = [];
        foreach ($hsaAccounts as $account) {
            $ownerId = (string) $account['ownerId'];
            if (!isset($accountsByOwner[$ownerId])) {
                $accountsByOwner[$ownerId] = [];
                $ownerIds[] = $ownerId;
            }
            $accountsByOwner[$ownerId][] = $account;
        }

        $parameters = $context['hsaParameters'];
        if ($parameters === null) {
            $minimum = (int) $context['hsaSupportedTaxYears']['minimum'];
            $maximum = (int) $context['hsaSupportedTaxYears']['maximum'];
            $before = $context['taxYear'] < $minimum;
            $entry = self::diagnostic(
                $before ? 'HSA_NOT_AVAILABLE_FOR_TAX_YEAR' : 'HSA_PARAMETERS_NOT_PUBLISHED_FOR_TAX_YEAR',
                DiagnosticSeverity::ERROR,
                $before
                    ? 'Health savings accounts were created for taxable years beginning after December 31, 2003, '
                        . "so no IRC 223 limitation exists for tax year {$context['taxYear']}."
                    : "No IRC 223 revenue procedure is encoded for tax year {$context['taxYear']}. "
                        . "Encoded HSA years are {$minimum}-{$maximum}; a future year is never extrapolated.",
                'taxYear',
                'IRC 223',
            );
            foreach ($ownerIds as $ownerId) {
                $context['hsaPlans'][$ownerId] = [
                    'status' => CalculationStatus::UNAVAILABLE->value,
                    'diagnostics' => [$entry],
                    'statutoryMaximum' => 0.0,
                    'detail' => null,
                    'familyPoolKey' => null,
                    // No IRC 223 year is encoded, so there is no family pool for the
                    // seeding to reach and nothing to determine a draw against.
                    'familyPoolUsageDeterminable' => false,
                ];
            }
            return;
        }

        $facts = [];
        foreach ($ownerIds as $ownerId) {
            $rules = null;
            $signature = null;
            $conflict = false;
            $accountVariants = [];
            $seenSignatures = [];
            foreach ($accountsByOwner[$ownerId] as $account) {
                if (!array_key_exists('hsa', $account['planRules'])) {
                    continue;
                }
                $supplied = $account['planRules']['hsa'];
                $encoded = (string) json_encode($supplied);
                if ($signature === null) {
                    $signature = $encoded;
                    $rules = is_array($supplied) ? $supplied : [];
                } elseif ($signature !== $encoded) {
                    $conflict = true;
                }
                if (!array_key_exists($encoded, $seenSignatures)) {
                    $seenSignatures[$encoded] = true;
                    $accountVariants[] = is_array($supplied) ? $supplied : [];
                }
            }
            $declared = $context['persons'][$ownerId]['hsaCoverage'] ?? null;
            $personConflict = $rules !== null
                && is_array($declared)
                && self::hsaCoverageSignature($rules) !== self::hsaCoverageSignature($declared);
            /*
             * Every coverage statement made about this person, the person-level
             * one included. persons[].hsaCoverage is a statement of the same fact
             * as planRules.hsa, so it belongs in the same set rather than in a
             * parallel branch: the person-versus-account contradiction is then the
             * ordinary disagreement, read by the same projection.
             */
            $coverageVariants = [];
            foreach ($accountVariants as $variant) {
                $coverageVariants[] = [
                    'source' => 'account',
                    'coverage' => $variant,
                    'months' => self::resolveHsaMonths($variant),
                ];
            }
            if (is_array($declared) && !array_key_exists((string) json_encode($declared), $seenSignatures)) {
                $coverageVariants[] = [
                    'source' => 'person',
                    'coverage' => $declared,
                    // The one place an empty object is an answer:
                    // persons[].hsaCoverage of {} records that this person held
                    // no high deductible health plan coverage.
                    'months' => self::resolveHsaMonths($declared)
                        ?? array_fill(0, self::HSA_MONTHS_IN_YEAR, null),
                ];
            }
            $shareValues = [];
            $lastMonthRuleValues = [];
            foreach ($accountVariants as $variant) {
                $shareValues[] = $variant['familyLimitShare'] ?? null;
                $lastMonthRuleValues[] = $variant['useLastMonthRule'] ?? null;
            }
            $facts[$ownerId] = [
                'ownerId' => $ownerId,
                'rules' => $rules,
                'conflict' => $conflict,
                'personConflict' => $personConflict,
                /**
                 * Every distinct coverage statement supplied for this person, in
                 * input order. Uncertainty is projected off these onto each
                 * operand it actually reaches, rather than off the bare fact that
                 * they disagree: two statements differing only in an annual
                 * deductible answer the IRC 223(b)(5)(A) family question
                 * identically, and in a year without the IRC 223(b)(2) cap they do
                 * not reach the couple's limitation at all.
                 */
                'coverageVariants' => $coverageVariants,
                /**
                 * persons[].hsaCoverage carries neither field, so it does not vote
                 * on either; only the person's own accounts can disagree about the
                 * IRC 223(b)(5)(B)(ii) division or the IRC 223(b)(8) election.
                 */
                'familyLimitShareConflict' => !self::unanimousField($shareValues),
                'useLastMonthRuleConflict' => !self::unanimousField($lastMonthRuleValues),
                'months' => $rules === null ? null : self::resolveHsaMonths($rules),
            ];
        }

        $couple = self::hsaMarriedCouple($context);
        $section223FsaFacts = self::healthFsaSection223FactsByOwner($context, $accounts);
        $coupleMembersWithAccounts = [];
        foreach ($couple ?? [] as $personId) {
            if (isset($accountsByOwner[$personId])) {
                $coupleMembersWithAccounts[] = $personId;
            }
        }

        /*
         * IRC 223(b)(5)(A) turns on whether *either spouse* has family coverage,
         * not on whether either spouse owns a health savings account. Coverage is
         * therefore read from the person: from planRules.hsa where that spouse has
         * an HSA, and from persons[].hsaCoverage where they do not.
         */
        /*
         * `hdhpAnnualDeductible` is null here whenever the caller stated no
         * annual deductible for this person, including where they supplied a
         * literal null: money() already treats null as "absent" everywhere
         * else in both engines, so the two must not be told apart here. The
         * `?? null` below is load-bearing for that, not defensive padding.
         */
        $coupleCoverage = [];
        foreach ($couple ?? [] as $personId) {
            if (isset($facts[$personId]) && $facts[$personId]['rules'] !== null) {
                $coupleCoverage[$personId] = [
                    'supplied' => true,
                    'months' => $facts[$personId]['months'],
                    'hdhpAnnualDeductible' => $facts[$personId]['rules']['hdhpAnnualDeductible'] ?? null,
                ];
                continue;
            }
            $declared = $context['persons'][$personId]['hsaCoverage'] ?? null;
            $coupleCoverage[$personId] = is_array($declared)
                ? [
                    'supplied' => true,
                    'months' => self::resolveHsaMonths($declared)
                        ?? array_fill(0, self::HSA_MONTHS_IN_YEAR, null),
                    'hdhpAnnualDeductible' => $declared['hdhpAnnualDeductible'] ?? null,
                ]
                : ['supplied' => false, 'months' => null, 'hdhpAnnualDeductible' => null];
        }

        /*
         * The coverage each spouse actually *stated*, month by month, snapshotted
         * before the IRC 223(b)(5)(A) recharacterization below rewrites self-only
         * months. The statute draws two different lines off these months and they
         * must not be confused: a spouse is *treated as* having family coverage for
         * the purpose of computing the limitation, but only a spouse who *has* a
         * family plan brings a deductible that competes for the lowest.
         *
         * The copy is deliberate rather than incidental. PHP arrays copy on
         * assignment, so this survives the recharacterization anyway; TypeScript
         * hands the same array to both $coupleCoverage and $facts and would see the
         * write. Snapshotting in both engines makes the rule the same in each
         * rather than leaving it to language semantics and statement order.
         */
        /*
         * The IRC 223(b)(5)(A) reading of every person the subsection could reach,
         * month by month. A spouse who owns no health savings account states their
         * coverage on persons[].hsaCoverage instead, so both routes are collected
         * here; a person who stated nothing at all is absent, which is the case
         * HSA_SPOUSE_COVERAGE_FACTS_REQUIRED already reports.
         */
        $coverageVariantsByPerson = [];
        $coverageSlotsByPerson = [];
        $familyStatusByPerson = [];
        $statusPersonIds = [];
        foreach ($ownerIds as $personId) {
            $statusPersonIds[$personId] = true;
        }
        foreach ($couple ?? [] as $personId) {
            $statusPersonIds[$personId] = true;
        }
        foreach (array_keys($statusPersonIds) as $personId) {
            $declaredCoverage = $context['persons'][$personId]['hsaCoverage'] ?? null;
            $variants = $facts[$personId]['coverageVariants'] ?? [];
            if (count($variants) === 0 && is_array($declaredCoverage)) {
                $variants = [[
                    'source' => 'person',
                    'coverage' => $declaredCoverage,
                    'months' => self::resolveHsaMonths($declaredCoverage)
                        ?? array_fill(0, self::HSA_MONTHS_IN_YEAR, null),
                ]];
            }
            $coverageVariantsByPerson[$personId] = $variants;
            // A person every one of whose statements is unusable has stated
            // nothing, so no projection is recorded and the absent-facts
            // diagnostic reports it.
            $anyUsable = false;
            foreach ($variants as $variant) {
                if ($variant['months'] !== null) {
                    $anyUsable = true;
                    break;
                }
            }
            if ($anyUsable) {
                $slots = self::resolvedCoverageSlotsFor($variants);
                $coverageSlotsByPerson[$personId] = $slots;
                $familyStatusByPerson[$personId] = self::familyPresenceFor($slots, $variants);
            }
        }

        /*
         * IRC 223(c)(2)(A)(i) sets a minimum annual deductible for each coverage
         * tier. This engine does not decide whether any plan is a high deductible
         * health plan; that is the eligibility test the scope boundary excludes
         * and HSA_ELIGIBILITY_FACTS_SUPPLIED_BY_CALLER disclaims. What it can do
         * without crossing that line is detect that the caller's own assertions
         * cannot all be true at once. A figure supplied as hdhpAnnualDeductible,
         * for months the same caller states were covered at a given tier, must
         * meet that tier's statutory minimum or it is not the thing the field is
         * declared to hold. The test is one-way: clearing the minimum proves
         * nothing about the plan, while falling below it disproves the caller's
         * own claim about the field.
         *
         * The consequence is not a lower ceiling. Notice 2004-50 Q&A-31 Example
         * (4) works a family plan with a $500 deductible against the 2004 family
         * minimum of $2,000 and concludes that *neither* spouse is an eligible
         * individual: the subminimum plan is neither ignored for failing the
         * minimum nor read as a $500 limitation. So the figure must not be
         * clamped up to the minimum and must not be published as a ceiling.
         *
         * Nor may the answer be ineligible. Rev. Rul. 2005-25 holds that a
         * spouse's non-HDHP family coverage which excludes the HSA owner does not
         * invoke IRC 223(b)(5) against that owner at all, and HsaCoverageInput
         * carries no fact about whom a plan covers, so Example (4) and that
         * ruling are indistinguishable from this input. Inconsistent input,
         * indeterminate output, which is the boundary a limits-only contract can
         * defend.
         *
         * Stated months are read rather than the IRC 223(b)(5)(A)
         * recharacterized ones: the question is what plan the caller described,
         * and a spouse merely *treated as* having family coverage never asserted
         * a family plan.
         */
        $subminimumDeductibleByPerson = [];
        foreach ($coverageVariantsByPerson as $personId => $variants) {
            foreach ($variants as $variant) {
                $stated = $variant['coverage']['hdhpAnnualDeductible'] ?? null;
                if ($stated === null || $variant['months'] === null) {
                    continue;
                }
                foreach (self::HSA_COVERAGE_TIERS as $tier) {
                    if (!in_array($tier, $variant['months'], true)) {
                        continue;
                    }
                    $minimum = (float) $parameters['hdhp']['minimumAnnualDeductible'][$tier === 'family' ? 'family' : 'selfOnly'];
                    // Compared at the cent precision every published figure
                    // carries, not with the tolerance used for accumulated
                    // float error elsewhere. A stated 999.991 against a 1000
                    // minimum is not a rounding artefact of this engine's
                    // arithmetic -- it is what the caller supplied, and
                    // subtracting 0.009 from the legal boundary would let it
                    // through to produce a determinate 999.99 ceiling visibly
                    // below the minimum.
                    if (self::roundMoney((float) $stated) >= $minimum) {
                        continue;
                    }
                    // Deterministic in both engines and independent of input
                    // order. The binding requirement is the highest minimum the
                    // stated figure falls below; family precedes self-only where
                    // two minimums tie; and where two statements fail the same
                    // minimum the lowest stated figure wins, because without
                    // that last clause reordering one owner's two contradictory
                    // accounts changed which deductible the message named.
                    $recorded = $subminimumDeductibleByPerson[$personId] ?? null;
                    if (
                        $recorded === null
                        || $minimum > $recorded['minimum']
                        || ($minimum === $recorded['minimum'] && $tier === 'family' && $recorded['tier'] !== 'family')
                        || ($minimum === $recorded['minimum'] && $tier === $recorded['tier']
                            && (float) $stated < $recorded['stated'])
                    ) {
                        $subminimumDeductibleByPerson[$personId] = [
                            'stated' => (float) $stated,
                            'tier' => $tier,
                            'minimum' => $minimum,
                        ];
                    }
                }
            }
        }

        /*
         * Stated coverage, projected onto the one question its two readers ask:
         * does this spouse have family coverage in this month. A month the
         * person's statements answer unanimously survives even when another month
         * is contradictory, which is what keeps a December limitation computable
         * while January is disputed. Reading a tier off whichever statement came
         * first would instead decide the applicability of IRC 223(b)(5)(A) from
         * input order.
         *
         * The projection is lossless only because nothing reads a non-family tier
         * from this map; a reader that needed 'self_only' back would need the tier
         * carried through the status instead.
         */
        $statedCoverageByPerson = [];
        foreach ($couple ?? [] as $personId) {
            $status = $familyStatusByPerson[$personId] ?? null;
            if ($status === null) {
                $statedCoverageByPerson[$personId] = null;
                continue;
            }
            $projected = [];
            foreach ($status as $slot) {
                $projected[] = $slot === 'family' ? 'family' : null;
            }
            $statedCoverageByPerson[$personId] = $projected;
        }

        $familyMonth = [];
        for ($month = 1; $month <= self::HSA_MONTHS_IN_YEAR; $month++) {
            $any = false;
            foreach ($couple ?? [] as $personId) {
                if (($statedCoverageByPerson[$personId][$month - 1] ?? null) === 'family') {
                    $any = true;
                }
            }
            $familyMonth[$month - 1] = $any;
        }
        $familySharingApplies = in_array(true, $familyMonth, true);
        $recharacterized = [];
        if ($familySharingApplies) {
            // IRC 223(b)(5)(A): if either spouse has family coverage, both are treated
            // as having only that family coverage. It does not make an otherwise
            // ineligible month eligible, so only supplied months are rewritten.
            foreach ($coupleMembersWithAccounts as $personId) {
                if ($facts[$personId]['months'] === null) {
                    continue;
                }
                for ($month = 1; $month <= self::HSA_MONTHS_IN_YEAR; $month++) {
                    if ($familyMonth[$month - 1] && $facts[$personId]['months'][$month - 1] === 'self_only') {
                        $facts[$personId]['months'][$month - 1] = 'family';
                        $recharacterized[$personId] = true;
                    }
                }
            }
        }

        /*
         * The spouses whose subminimum *family* plan actually reaches this
         * person's limitation, month by month rather than for the year.
         *
         * IRC 223(b)(5)(A) resolves the lowest-deductible comparison per month --
         * familyDeductibleByMonth below does exactly that -- so a contradiction in
         * a month this person was not eligible cannot touch the months they were.
         * A spouse's January-only family plan does not make a December-only
         * limitation unknowable, and treating familySharingApplies as the test
         * would refuse an answer the engine already has.
         *
         * Stated family months are read on the spouse's side, because only a
         * spouse who *has* a family plan brings a deductible that competes; the
         * owner's side reads eligibility alone, because a self-only month of
         * theirs is recharacterized to family in any month the spouse holds family
         * coverage.
         */
        $subminimumFamilySpousesFor = static function (string $personId) use (
            &$subminimumDeductibleByPerson,
            &$familySharingApplies,
            &$couple,
            &$facts,
            &$statedCoverageByPerson,
        ): array {
            $reaching = [];
            if ($familySharingApplies !== true) {
                return $reaching;
            }
            $ownMonths = $facts[$personId]['months'] ?? null;
            if ($ownMonths === null) {
                return $reaching;
            }
            foreach ($couple ?? [] as $otherId) {
                if ($otherId === $personId) {
                    continue;
                }
                $entry = $subminimumDeductibleByPerson[$otherId] ?? null;
                if ($entry === null || $entry['tier'] !== 'family') {
                    continue;
                }
                $otherFamilyMonths = $statedCoverageByPerson[$otherId] ?? null;
                if ($otherFamilyMonths === null) {
                    continue;
                }
                for ($month = 1; $month <= self::HSA_MONTHS_IN_YEAR; $month++) {
                    if ($otherFamilyMonths[$month - 1] === 'family' && $ownMonths[$month - 1] !== null) {
                        $reaching[] = [$otherId, $entry];
                        break;
                    }
                }
            }
            return $reaching;
        };

        /*
         * Whether the figure this check rejected could have reached this owner's
         * limitation arithmetic. Read in both passes: the first raises the
         * diagnostic, the second withholds the limitation detail built from the
         * rejected deductible, because saying in a diagnostic that a figure is not
         * published as a ceiling while appliedAnnualLimitByMonth still carries
         * twelve copies of it publishes it anyway.
         */
        $subminimumDeductibleReaches = static function (string $personId) use (
            &$subminimumDeductibleByPerson,
            &$subminimumFamilySpousesFor,
        ): bool {
            return array_key_exists($personId, $subminimumDeductibleByPerson)
                || count($subminimumFamilySpousesFor($personId)) > 0;
        };

        /*
         * Which of this person's months the rejected deductible actually fed.
         *
         * appliedAnnualLimitByMonth is month-granular by contract, and the reach
         * calculation above is already month-aware, so nulling all twelve threw
         * away known entries: a spouse's January-only subminimum plan leaves
         * February through December resting on nothing but the owner's own
         * lawful deductible. The two annual scalars beside it stay null whenever
         * *any* month is affected, because they are sums over all of them.
         *
         * The owner's own contradiction feeds every month they were eligible --
         * one deductible covers the whole input -- while a spouse's feeds only
         * the months their family plan was in force.
         */
        $subminimumAffectedMonths = static function (string $personId) use (
            &$subminimumDeductibleByPerson,
            &$subminimumFamilySpousesFor,
            &$facts,
            &$statedCoverageByPerson,
        ): array {
            $ownMonths = $facts[$personId]['months'] ?? null;
            if ($ownMonths === null) {
                return array_fill(0, self::HSA_MONTHS_IN_YEAR, false);
            }
            $ownContradiction = array_key_exists($personId, $subminimumDeductibleByPerson);
            $reachingSpouses = $subminimumFamilySpousesFor($personId);
            $affected = [];
            for ($month = 1; $month <= self::HSA_MONTHS_IN_YEAR; $month++) {
                if ($ownMonths[$month - 1] === null) {
                    $affected[] = false;
                    continue;
                }
                if ($ownContradiction) {
                    $affected[] = true;
                    continue;
                }
                $hit = false;
                foreach ($reachingSpouses as [$otherId, $entry]) {
                    if (($statedCoverageByPerson[$otherId][$month - 1] ?? null) === 'family') {
                        $hit = true;
                        break;
                    }
                }
                $affected[] = $hit;
            }
            return $affected;
        };

        /**
         * IRC 223(b)(5)(A) also treats spouses with family coverage under different
         * plans as covered by the plan with the lowest annual deductible. That only
         * changes an amount for 2004-2006, when IRC 223(b)(2) capped the monthly
         * limitation by the deductible.
         *
         * It is resolved per month, because the limitation itself is: IRC 223(b)(2)
         * builds the year out of twelve monthly amounts, each determined from the
         * coverage in force for that month. A spouse who takes family coverage in
         * December brings that plan's deductible to December and to no earlier
         * month, where they had no family plan for it to be the lowest of.
         *
         * Each entry is one of:
         *   ['state' => 'not_applicable']
         *   ['state' => 'known', 'value' => float]
         *   ['state' => 'indeterminate', 'missingPersonIds' => string[]]
         */
        $familyDeductibleByMonth = [];
        for ($month = 1; $month <= self::HSA_MONTHS_IN_YEAR; $month++) {
            // IRC 223(b)(5)(A) reaches the lowest annual deductible only "if such
            // spouses each have family coverage under different plans". A spouse
            // whose own coverage is self-only for this month has no family plan, so
            // their deductible is not a candidate and must not lower the couple's
            // family limitation — which is the deductible of a family plan, not of
            // whatever plan happens to be cheapest in the household.
            $candidates = [];
            foreach ($couple ?? [] as $personId) {
                if (($statedCoverageByPerson[$personId][$month - 1] ?? null) === 'family') {
                    $candidates[] = $personId;
                }
            }
            if ($candidates === []) {
                $familyDeductibleByMonth[$month - 1] = ['state' => 'not_applicable'];
                continue;
            }
            $missingPersonIds = [];
            $values = [];
            foreach ($candidates as $personId) {
                $value = $coupleCoverage[$personId]['hdhpAnnualDeductible'] ?? null;
                if ($value === null) {
                    $missingPersonIds[] = $personId;
                } else {
                    $values[] = (float) $value;
                }
            }
            // A candidate's plan could be the lowest, so an unstated one leaves the
            // least of them unknown rather than simply absent from the comparison.
            $familyDeductibleByMonth[$month - 1] = $missingPersonIds === []
                ? ['state' => 'known', 'value' => min($values)]
                : ['state' => 'indeterminate', 'missingPersonIds' => $missingPersonIds];
        }

        $amountsByOwner = [];
        foreach ($ownerIds as $ownerId) {
            $owner = $facts[$ownerId];
            $person = $context['persons'][$ownerId];
            $diagnostics = [];
            $indeterminate = false;
            /*
             * IRC 223(b)(5) asks two questions, and an input that leaves one
             * unanswered usually leaves the other answered:
             *
             *   (A) gives the spouses one family limitation. Whether *that
             *       amount* is knowable is $familyPoolAmountIndeterminate.
             *   (B)(ii) divides that one limitation between them, equally unless
             *       they agree otherwise. Whether *the division* is knowable is
             *       $familyDivisionIndeterminate.
             *
             * They were one flag until the two were separated, which nulled a
             * perfectly knowable couple-wide ceiling whenever the spouses' agreed
             * shares contradicted each other -- an amount the statute fixes from
             * coverage facts alone, withheld because of a disagreement about who
             * may use it.
             *
             * Both are narrower questions than whether this owner's overall result
             * is determinable. A missing birth year makes only the IRC 223(b)(3)
             * age-55 amount unknown and leaves the IRC 223(b)(5) family limit
             * perfectly knowable, so it deliberately sets neither. Neither does the
             * bare fact that this person's coverage statements disagree: see
             * $familyOperandConflict below, which asks which operand they disagree
             * about.
             */
            $familyPoolAmountIndeterminate = false;
            $familyDivisionIndeterminate = false;

            /*
             * Does this owner's disagreement actually reach the couple's IRC
             * 223(b)(5) ceiling? That ceiling is the divided family limitation
             * *plus* the spouses' undivided self-only portions, so the question is
             * which inputs can change familyPortionApplied, selfPortionApplied or
             * the division. HsaRulesInput is a closed surface of eight fields, so
             * the answer is enumerable rather than guessable -- an earlier attempt
             * listed only the operands of the divided family amount and let a
             * self-only month, a self-only deductible and the IRC 223(b)(8)
             * election through, each of which then fixed the ceiling from whichever
             * account happened to be listed first:
             *
             *   coverageTier, eligibleMonths, monthlyCoverage  reach it: tier and
             *       months of both portions.
             *   hdhpAnnualDeductible  reaches it in 2004-2006 only, when IRC
             *       223(b)(2) capped each covered month by it -- family months
             *       through the IRC 223(b)(5)(A) lowest-deductible comparison, and
             *       self-only months through the undivided self portion, which is
             *       why no family month is required for it to matter.
             *   useLastMonthRule  reaches it: IRC 223(b)(8) replaces the prorated
             *       amount with December's annualized one.
             *   familyLimitShare  reaches the division and nothing else. IRC
             *       223(b)(5)(B)(ii) divides a limitation subparagraph (A) has
             *       already fixed, so a disagreement about the shares cannot move
             *       the amount being shared -- which is why it is the one field
             *       here that sets the division flag rather than the amount one.
             *   testingPeriodSatisfied, testingPeriodFailureByDeathOrDisability do
             *       not. They are read only into the reported testing-period
             *       obligation, never into either portion, so a disagreement about
             *       a future compliance fact leaves this year's ceiling knowable.
             *
             * A ninth field must be classified here rather than defaulting to inert.
             */
            $ownerSlots = $coverageSlotsByPerson[$ownerId] ?? null;
            $deductibleValues = [];
            foreach ($coverageVariantsByPerson[$ownerId] ?? [] as $variant) {
                $deductibleValues[] = $variant['coverage']['hdhpAnnualDeductible'] ?? null;
            }
            $deductibleUnanimous = self::unanimousField($deductibleValues);
            $ownerHasCoveredMonth = false;
            foreach ($ownerSlots ?? [] as $slot) {
                if ($slot !== 'none') {
                    $ownerHasCoveredMonth = true;
                    break;
                }
            }
            $amountInputsIndeterminate = $ownerSlots === null
                || in_array('unknown', $ownerSlots, true)
                || ($parameters['contributionLimitCappedByHdhpAnnualDeductible']
                    && !$deductibleUnanimous
                    && $ownerHasCoveredMonth)
                || $owner['useLastMonthRuleConflict'];
            if ($amountInputsIndeterminate) {
                $familyPoolAmountIndeterminate = true;
            }
            if ($owner['familyLimitShareConflict']) {
                $familyDivisionIndeterminate = true;
            }

            if ($owner['conflict']) {
                $indeterminate = true;
                $diagnostics[] = self::diagnostic(
                    'HSA_CONFLICTING_COVERAGE_FACTS_FOR_OWNER',
                    DiagnosticSeverity::ERROR,
                    'Two health savings accounts owned by the same person supplied different IRC 223 coverage facts. '
                        . 'Coverage is a fact about the person, so it must be identical on every one of that person\'s HSAs.',
                    "persons.{$ownerId}",
                    'IRC 223(b)',
                );
            }
            if ($owner['personConflict']) {
                $indeterminate = true;
                $diagnostics[] = self::diagnostic(
                    'HSA_PERSON_AND_ACCOUNT_COVERAGE_FACTS_CONFLICT',
                    DiagnosticSeverity::ERROR,
                    'This person\'s persons[].hsaCoverage and their health savings account\'s planRules.hsa state '
                        . 'different IRC 223(c)(2) coverage. Coverage is one fact about the person, so the two must be '
                        . 'identical; persons[].hsaCoverage exists for a spouse who owns no HSA.',
                    "persons.{$ownerId}",
                    'IRC 223(b)',
                );
            }
            if ($owner['rules'] === null || $owner['months'] === null) {
                $indeterminate = true;
                $familyPoolAmountIndeterminate = true;
                $diagnostics[] = self::diagnostic(
                    'HSA_COVERAGE_FACTS_REQUIRED',
                    DiagnosticSeverity::ERROR,
                    'planRules.hsa with a coverage tier (or a monthlyCoverage list) is required. Whether a person is an '
                        . 'eligible individual under IRC 223(c)(1), including Medicare entitlement under IRC 223(b)(7), '
                        . 'is a caller-supplied fact.',
                    "persons.{$ownerId}",
                    'IRC 223(b)(1)',
                );
            }

            $months = $owner['months'] ?? array_fill(0, self::HSA_MONTHS_IN_YEAR, null);

            /*
             * IRC 223(b)(5)(A) makes the other spouse's coverage matter for two
             * independent reasons, and the sentence does two separate things:
             *
             *   *Recharacterization.* Spouses are treated as having family
             *   coverage for any month in which either has it, which can only ever
             *   *raise* a self-only month. So this reason bites exactly when the
             *   owner has a self-only month. Without the spouse's coverage the
             *   answer is genuinely unknown - self-only for the whole year is
             *   $4,400 for 2026, but a spouse's family coverage makes it a divided
             *   family limit instead - and answering with either number is a guess.
             *
             *   *Lowest deductible.* Spouses who each have family coverage under
             *   different plans are treated as covered by the plan with the lowest
             *   annual deductible. That changes an amount only for 2004-2006, when
             *   IRC 223(b)(2) capped each month by the deductible, and it bites on
             *   the owner's *family* months - where recharacterization has nothing
             *   left to do. An unstated spouse may hold a family plan whose
             *   deductible is lower than this owner's, in which case the couple's
             *   limitation is lower than the figure this owner's own plan produces.
             *
             * The second reason is why an owner with family coverage every month is
             * not safe in a capped year merely because no recharacterization is
             * possible. Treating an absent spouse as holding no competing family
             * plan would answer the subparagraph (A) comparison from a fact the
             * caller never supplied, and would fail open in the direction that
             * costs a taxpayer the IRC 4973 excise. persons[].hsaCoverage of {}
             * already exists as the cheap way to state that the spouse held no high
             * deductible health plan coverage, so absence and "no coverage" stay
             * distinguishable rather than being conflated here.
             */
            $marriedFiler = $context['filingStatus'] === FilingStatus::MARRIED_FILING_JOINTLY->value
                || $context['filingStatus'] === FilingStatus::MARRIED_FILING_SEPARATELY->value;
            // First match, not last: TypeScript selects with Array.prototype.find.
            // The two agree for every reachable input today, because a couple is
            // always exactly [taxpayer, spouse] and the duplicate-role check makes
            // an owner whose role is taxpayer or spouse a member of it -- so only
            // one element can differ. Taking the first here keeps the two engines
            // the same rule rather than the same answer by argument.
            $otherSpouseId = null;
            foreach ($couple ?? [] as $personId) {
                if ($personId !== $ownerId) {
                    $otherSpouseId = $personId;
                    break;
                }
            }
            $ownerIsSpouseOfCouple = $couple !== null && in_array($ownerId, $couple, true);
            /*
             * Supplied has to mean *usable*. An account whose planRules.hsa carries
             * no tier and no monthlyCoverage has not stated this person's coverage,
             * even though the property is present, so the other spouse is no better
             * placed than if nothing had been said. Reading the empty object as an
             * assertion of no family coverage would answer the IRC 223(b)(5)(A)
             * question from a fact the caller never supplied. persons[].hsaCoverage
             * of {} is the exception and is deliberately different: it is the
             * documented way to state that the person held no high deductible
             * health plan coverage.
             */
            $spouseCoverageSupplied = $otherSpouseId !== null
                && array_key_exists($otherSpouseId, $familyStatusByPerson);
            $recharacterizationCouldRaiseTier = in_array('self_only', $months, true);
            $lowestDeductibleCouldLowerAmount =
                $parameters['contributionLimitCappedByHdhpAnnualDeductible']
                && in_array('family', $months, true);
            if (
                $marriedFiler
                && ($ownerIsSpouseOfCouple || ($person['role'] ?? null) === 'taxpayer' || ($person['role'] ?? null) === 'spouse')
                && !$spouseCoverageSupplied
                && ($recharacterizationCouldRaiseTier || $lowestDeductibleCouldLowerAmount)
            ) {
                $indeterminate = true;
                $familyPoolAmountIndeterminate = true;
                $taxYear = $context['taxYear'];
                // Name the reason that actually applies. Both can, and a caller
                // told only about self-only months would go looking for one in a
                // record whose months are all family months.
                if ($recharacterizationCouldRaiseTier && $lowestDeductibleCouldLowerAmount) {
                    $reason = 'This owner has at least one self-only month, which that treatment can raise to a '
                        . 'family month, and at least one family month, whose limitation for tax year '
                        . "{$taxYear} is capped by the lowest of the spouses' family-plan annual deductibles under "
                        . 'IRC 223(b)(2). The other spouse\'s coverage changes the answer both ways and is not '
                        . 'supplied.';
                } elseif ($recharacterizationCouldRaiseTier) {
                    $reason = 'This owner has at least one self-only month, so the other spouse\'s coverage changes '
                        . 'the answer and is not supplied.';
                } else {
                    $reason = 'IRC 223(b)(5)(A) also treats spouses who each have family coverage under different '
                        . 'plans as having the family coverage with the lowest annual deductible, and for tax year '
                        . "{$taxYear} IRC 223(b)(2) capped each month's limitation by that deductible. This owner "
                        . 'has at least one family month, so an unstated spouse holding a family plan with a lower '
                        . 'deductible would lower this limitation, and their coverage is not supplied.';
                }
                $diagnostics[] = self::diagnostic(
                    'HSA_SPOUSE_COVERAGE_FACTS_REQUIRED',
                    DiagnosticSeverity::ERROR,
                    'IRC 223(b)(5)(A) treats both spouses as having family coverage for any month in which either of '
                        . 'them has it, whether or not that spouse owns a health savings account. '
                        . $reason
                        . ' State it on that spouse\'s persons[].hsaCoverage — an empty object records that '
                        . 'the spouse held no high deductible health plan coverage.',
                    "persons.{$ownerId}",
                    'IRC 223(b)(5)(A)',
                );
            }

            /**
             * The same question one step further out. A spouse who supplied
             * coverage facts that contradict each other on the family question
             * has not answered it either, so an owner with a self-only month is
             * no better placed than if that spouse had said nothing. This is a
             * sibling of the condition above rather than a reuse of it: the
             * remedy differs, because the caller must reconcile two statements
             * they already made instead of adding a missing one. It is also not
             * the IRC 223(b)(5) sharing error, which reports a family limitation
             * known to exist but not divisible; here whether the subsection
             * applies at all is what is unknown, and the sharing path is never
             * reached.
             */
            /*
             * The months have to be the same months. IRC 223(b)(5)(A)
             * recharacterizes a self-only month only where a spouse has family
             * coverage in *that* month, so a spouse whose statements contradict
             * each other in January leaves a December self-only limitation exactly
             * as computable as it ever was.
             */
            $otherSpouseFamilyStatus = $otherSpouseId === null
                ? null
                : ($familyStatusByPerson[$otherSpouseId] ?? null);
            $spouseCoverageAmbiguousOnFamily = false;
            if ($otherSpouseFamilyStatus !== null) {
                foreach ($months as $index => $tier) {
                    if ($tier === 'self_only' && ($otherSpouseFamilyStatus[$index] ?? null) === 'unknown') {
                        $spouseCoverageAmbiguousOnFamily = true;
                        break;
                    }
                }
            }
            if (
                $marriedFiler
                && ($ownerIsSpouseOfCouple || ($person['role'] ?? null) === 'taxpayer' || ($person['role'] ?? null) === 'spouse')
                && $spouseCoverageAmbiguousOnFamily
            ) {
                $indeterminate = true;
                $familyPoolAmountIndeterminate = true;
                $diagnostics[] = self::diagnostic(
                    'HSA_SPOUSE_COVERAGE_FACTS_CONFLICT',
                    DiagnosticSeverity::ERROR,
                    'IRC 223(b)(5)(A) treats both spouses as having family coverage for any month in which either of '
                        . 'them has it. The other spouse\'s supplied coverage facts disagree about whether they have '
                        . 'family coverage, so whether this subsection applies to this owner\'s self-only months '
                        . 'cannot be determined. Reconcile that spouse\'s coverage facts; coverage is one fact about '
                        . 'a person, so every statement of it must agree.',
                    "persons.{$ownerId}",
                    'IRC 223(b)(5)(A)',
                );
            }

            $eligibleMonthCount = 0;
            foreach ($months as $tier) {
                if ($tier !== null) {
                    $eligibleMonthCount++;
                }
            }
            $age = self::ageAtEndOfTaxYear($person, (int) $context['taxYear']);
            if ($age === null) {
                $indeterminate = true;
                $diagnostics[] = self::diagnostic(
                    'BIRTH_YEAR_OR_DATE_REQUIRED_FOR_HSA_LIMIT',
                    DiagnosticSeverity::ERROR,
                    'Birth year or birth date is required to determine whether the IRC 223(b)(3) additional contribution '
                        . 'amount for an individual who attains age 55 applies.',
                    "persons.{$ownerId}",
                    'IRC 223(b)(3)(A)',
                );
            }

            $ownDeductible = $owner['rules']['hdhpAnnualDeductible'] ?? null;
            $deductibleMissing = false;
            $missingDeductibleSpouses = [];
            $deductibleFor = static function (string $tier, int $monthIndex) use (
                $familySharingApplies,
                $familyDeductibleByMonth,
                $ownDeductible,
                $ownerId,
                &$missingDeductibleSpouses,
            ): ?float {
                if ($tier === 'family' && $familySharingApplies) {
                    $resolved = $familyDeductibleByMonth[$monthIndex];
                    if ($resolved['state'] === 'known') {
                        return (float) $resolved['value'];
                    }
                    if ($resolved['state'] === 'indeterminate') {
                        foreach ($resolved['missingPersonIds'] as $personId) {
                            if ($personId !== $ownerId) {
                                $missingDeductibleSpouses[$personId] = true;
                            }
                        }
                        return null;
                    }
                }
                return $ownDeductible === null ? null : (float) $ownDeductible;
            };
            $annualLimitFor = static function (string $tier, int $monthIndex) use (
                $parameters,
                $deductibleFor,
                &$deductibleMissing,
            ): float {
                $statutory = (float) $parameters['annualContributionLimit'][$tier === 'family' ? 'family' : 'selfOnly'];
                if ($parameters['contributionLimitCappedByHdhpAnnualDeductible'] !== true) {
                    return $statutory;
                }
                $deductible = $deductibleFor($tier, $monthIndex);
                if ($deductible === null) {
                    $deductibleMissing = true;
                    return $statutory;
                }
                return self::minMoney($deductible, $statutory);
            };

            $monthlyAnnualLimits = [];
            foreach ($months as $monthIndex => $tier) {
                $monthlyAnnualLimits[] = $tier === null ? null : $annualLimitFor($tier, $monthIndex);
            }
            if ($deductibleMissing) {
                $indeterminate = true;
                $familyPoolAmountIndeterminate = true;
                // One diagnostic per owner, not one per affected month: the missing
                // fact is a property of the person and their plan, not of each month
                // it reaches. Where the gap is a spouse's, name them, because the
                // input to supply lives on that spouse and not on this account.
                $spouseNote = '';
                foreach (array_keys($missingDeductibleSpouses) as $personId) {
                    $spouseNote .= " Spouse {$personId} stated family coverage but supplied no "
                        . "persons.{$personId}.hsaCoverage.hdhpAnnualDeductible, which IRC 223(b)(5)(A) needs to "
                        . 'identify the lowest family-plan deductible.';
                }
                $diagnostics[] = self::diagnostic(
                    'HSA_HDHP_ANNUAL_DEDUCTIBLE_REQUIRED',
                    DiagnosticSeverity::ERROR,
                    "For tax year {$context['taxYear']} IRC 223(b)(2) limited each month to one twelfth of the lesser of "
                        . 'the plan\'s annual deductible and the statutory amount, so planRules.hsa.hdhpAnnualDeductible '
                        . 'is required. The Tax Relief and Health Care Act of 2006 section 303 removed that cap for years '
                        . 'after 2006.' . $spouseNote,
                    "persons.{$ownerId}",
                    'IRC 223(b)(2)',
                );
            }

            /*
             * The IRC 223(c)(2)(A)(i) consistency check, reported on this
             * account.
             *
             * The owner's own contradiction always reaches them. A spouse's
             * reaches them only through a *family* plan, because that is the
             * only kind IRC 223(b)(5)(A) draws into the comparison: Notice
             * 2004-50 Q&A-31 Example (1) leaves the HSA owner eligible at the
             * full family amount where the competing plan is self-only, and
             * Example (4) makes neither spouse eligible once that competing plan
             * is family coverage.
             *
             * This fires in every year, not only the 2004-2006 years where IRC
             * 223(b)(2) read the deductible into the arithmetic. The fail-open
             * is not confined to those years: reporting the full statutory
             * amount for a taxpayer whose stated plan cannot be a high
             * deductible health plan is the same wrong answer, arrived at by
             * ignoring the field instead of by dividing by it.
             */
            $ownSubminimumDeductible = $subminimumDeductibleByPerson[$ownerId] ?? null;
            $spouseSubminimumDeductibles = $subminimumFamilySpousesFor($ownerId);
            if ($ownSubminimumDeductible !== null || count($spouseSubminimumDeductibles) > 0) {
                $indeterminate = true;
                // Only a *family*-tier contradiction reaches the couple's shared
                // limitation. A self-only one is the stating person's own
                // problem and must not null the other spouse's IRC 223(b)(5)
                // limit: Notice 2004-50 Q&A-31 Example (1) leaves the
                // family-covered spouse contributing the full amount beside a
                // self-only plan far below the minimum, and whether that spouse
                // happens to own an HSA of their own cannot change the coverage
                // rule.
                if (
                    ($ownSubminimumDeductible !== null && $ownSubminimumDeductible['tier'] === 'family')
                    || count($spouseSubminimumDeductibles) > 0
                ) {
                    $familyPoolAmountIndeterminate = true;
                }
                $clauses = [];
                if ($ownSubminimumDeductible !== null) {
                    $clauses[] = 'This person stated ' . self::hsaTierLabel($ownSubminimumDeductible['tier'])
                        . ' coverage with an annual deductible of $'
                        . self::localeNumber($ownSubminimumDeductible['stated'])
                        . ', below the $' . self::localeNumber($ownSubminimumDeductible['minimum'])
                        . " IRC 223(c)(2)(A)(i) minimum for that tier in {$context['taxYear']}.";
                }
                foreach ($spouseSubminimumDeductibles as [$personId, $entry]) {
                    $clauses[] = "Spouse {$personId} stated family coverage with an annual deductible of $"
                        . self::localeNumber($entry['stated'])
                        . ', below the $' . self::localeNumber($entry['minimum'])
                        . " IRC 223(c)(2)(A)(i) family minimum for {$context['taxYear']}, and IRC 223(b)(5)(A) "
                        . "draws that plan into the couple's lowest-deductible comparison.";
                }
                $diagnostics[] = self::diagnostic(
                    'HSA_HDHP_DEDUCTIBLE_BELOW_STATUTORY_MINIMUM',
                    DiagnosticSeverity::ERROR,
                    implode(' ', $clauses)
                        . ' A plan whose annual deductible is below the statutory minimum is not a high deductible '
                        . 'health plan, so the supplied facts cannot all be true. This engine does not test high '
                        . 'deductible health plan status and does not decide eligibility here: the figure is neither '
                        . 'raised to the minimum nor published as a ceiling, because Notice 2004-50 Q&A-31 Example (4) '
                        . 'makes a subminimum family plan an eligibility consequence rather than a lower limitation, '
                        . 'while Rev. Rul. 2005-25 leaves that consequence turning on whom the plan covers, which this '
                        . 'input does not carry. Correct the deductible or the coverage tier.',
                    "persons.{$ownerId}",
                    'IRC 223(c)(2)(A)(i); Notice 2004-50 Q&A-31 Example (4)',
                );
            }

            $tierPortion = static function (string $tier) use ($monthlyAnnualLimits, $months): float {
                $sum = 0.0;
                foreach ($monthlyAnnualLimits as $index => $value) {
                    if ($months[$index] === $tier) {
                        $sum += $value ?? 0.0;
                    }
                }
                return $sum / self::HSA_MONTHS_IN_YEAR;
            };
            $familyPortionWithoutLastMonthRule = $tierPortion('family');
            $selfPortionWithoutLastMonthRule = $tierPortion('self_only');
            $sum = 0.0;
            foreach ($monthlyAnnualLimits as $value) {
                $sum += $value ?? 0.0;
            }
            $proratedWithoutLastMonthRule = self::roundMoney($sum / self::HSA_MONTHS_IN_YEAR);
            $catchUpEligible = $age !== null && $age >= 55;
            $catchUpWithoutLastMonthRule = $catchUpEligible
                ? self::roundMoney(
                    ((float) $parameters['additionalContributionAmountAge55'] * $eligibleMonthCount)
                        / self::HSA_MONTHS_IN_YEAR,
                )
                : 0.0;

            $lastMonthRuleApplied = false;
            $appliedAnnualLimitByMonth = $monthlyAnnualLimits;
            $proratedApplied = $proratedWithoutLastMonthRule;
            $familyPortionApplied = $familyPortionWithoutLastMonthRule;
            $selfPortionApplied = $selfPortionWithoutLastMonthRule;
            $catchUpApplied = $catchUpWithoutLastMonthRule;

            if (!empty($owner['rules']['useLastMonthRule'])) {
                $decemberTier = $months[self::HSA_MONTHS_IN_YEAR - 1];
                if ($parameters['lastMonthRuleAvailable'] !== true) {
                    $diagnostics[] = self::diagnostic(
                        'HSA_LAST_MONTH_RULE_NOT_AVAILABLE_FOR_TAX_YEAR',
                        DiagnosticSeverity::WARNING,
                        'IRC 223(b)(8) was added by the Tax Relief and Health Care Act of 2006 section 305 for taxable '
                            . 'years beginning after December 31, 2006, so it does not apply to tax year '
                            . "{$context['taxYear']}. The ordinary month-by-month limitation is used instead.",
                        "persons.{$ownerId}",
                        'IRC 223(b)(8)',
                    );
                } elseif ($decemberTier === null) {
                    $diagnostics[] = self::diagnostic(
                        'HSA_LAST_MONTH_RULE_REQUIRES_DECEMBER_ELIGIBILITY',
                        DiagnosticSeverity::WARNING,
                        'IRC 223(b)(8)(A) applies only to an individual who is an eligible individual during the last '
                            . 'month of the taxable year. December is not an eligible month here, so the ordinary '
                            . 'month-by-month limitation is used instead.',
                        "persons.{$ownerId}",
                        'IRC 223(b)(8)(A)',
                    );
                } else {
                    $lastMonthRuleApplied = true;
                    $decemberAnnualLimit = $annualLimitFor($decemberTier, self::HSA_MONTHS_IN_YEAR - 1);
                    $appliedAnnualLimitByMonth = array_fill(0, self::HSA_MONTHS_IN_YEAR, $decemberAnnualLimit);
                    $proratedApplied = self::roundMoney($decemberAnnualLimit);
                    $familyPortionApplied = $decemberTier === 'family' ? (float) $decemberAnnualLimit : 0.0;
                    $selfPortionApplied = $decemberTier === 'family' ? 0.0 : (float) $decemberAnnualLimit;
                    $catchUpApplied = $catchUpEligible
                        ? self::roundMoney((float) $parameters['additionalContributionAmountAge55'])
                        : 0.0;
                }
            }


            // A health FSA whose Rev. Rul. 2004-45 purpose is not stated leaves
            // the IRC 223 answer unknown rather than merely unusual:
            // general-purpose coverage is disqualifying and limited-purpose or
            // post-deductible coverage is not, and the engine cannot read a plan
            // document to tell which this is. A confident ceiling computed from a
            // fact nobody supplied would be the bug, so this is the one part of
            // the interaction that refuses to compute.
            $spouseId = self::hsaSpouseIdOf($couple, $ownerId);
            $ownFsa = $section223FsaFacts[$ownerId] ?? self::emptyHealthFsaSection223Facts();
            $spouseFsa = $spouseId === null
                ? self::emptyHealthFsaSection223Facts()
                : ($section223FsaFacts[$spouseId] ?? self::emptyHealthFsaSection223Facts());
            if ($ownFsa['purposeUnstated'] || $spouseFsa['purposeUnstated']) {
                // Belt and braces: the ERROR severity below already forces the
                // result indeterminate through the ordinary rule, and the flag
                // keeps the refusal if that severity is ever softened. They are
                // redundant on purpose, so no mutation distinguishes them
                // individually.
                $indeterminate = true;
                $familyPoolAmountIndeterminate = true;
                $whose = $ownFsa['purposeUnstated'] ? 'held by this individual' : "held by this individual's spouse";
                $diagnostics[] = self::diagnostic(
                    'HEALTH_FSA_PURPOSE_REQUIRED_FOR_HSA_INTERACTION',
                    DiagnosticSeverity::ERROR,
                    "A health flexible spending arrangement {$whose} states no Rev. Rul. 2004-45 purpose. A "
                        . 'general-purpose arrangement is coverage that fails IRC 223(c)(1)(A)(ii) and a '
                        . 'limited-purpose or post-deductible one is not, so the IRC 223 answer turns on a plan-design '
                        . 'fact this engine cannot read and no limitation is reported. Supply '
                        . 'planRules.healthFsa.purpose.',
                    "persons.{$ownerId}",
                    'IRC 223(c)(1)(A)(ii); Rev. Rul. 2004-45',
                );
            }

            $amountsByOwner[$ownerId] = [
                'proratedApplied' => $proratedApplied,
                'proratedWithoutLastMonthRule' => $proratedWithoutLastMonthRule,
                'familyPortionApplied' => $familyPortionApplied,
                'selfPortionApplied' => $selfPortionApplied,
                'familyPortionWithoutLastMonthRule' => $familyPortionWithoutLastMonthRule,
                'selfPortionWithoutLastMonthRule' => $selfPortionWithoutLastMonthRule,
                'catchUpApplied' => $catchUpApplied,
                // IRC 223(b)(3) turns on age, so an absent birth year leaves its
                // amount untestable rather than nil.
                'ageKnown' => $age !== null,
                'catchUpWithoutLastMonthRule' => $catchUpWithoutLastMonthRule,
                'appliedAnnualLimitByMonth' => $appliedAnnualLimitByMonth,
                'eligibleMonthCount' => $eligibleMonthCount,
                'lastMonthRuleApplied' => $lastMonthRuleApplied,
                'diagnostics' => $diagnostics,
                'indeterminate' => $indeterminate,
                'familyPoolAmountIndeterminate' => $familyPoolAmountIndeterminate,
                'familyDivisionIndeterminate' => $familyDivisionIndeterminate,
            ];
        }

        /*
         * IRC 223(b)(4)(A) reduces an individual's own limitation by "the aggregate
         * amount paid for such taxable year to Archer MSAs of such individual", and
         * IRC 223(b)(5)(B)(i) reduces the one family limitation by "the aggregate
         * amount paid to Archer MSAs of such spouses". Both are amounts paid, so the
         * caller supplies them on the person and no part of IRC 220 is modelled.
         */
        $archerForPerson = static function (string $personId) use ($context): float {
            return (float) ($context['persons'][$personId]['archerMsaContributions'] ?? 0.0);
        };
        /*
         * IRC 223(b)(4)(C) reduces the limitation by "the aggregate amount
         * contributed to health savings accounts of such individual for such taxable
         * year under section 408(d)(9)". It is read per individual in every case, so
         * unlike the Archer amount it has no couple-wide aggregate.
         */
        $qualifiedHsaFundingFor = static function (string $personId) use ($context): float {
            return (float) ($context['persons'][$personId]['qualifiedHsaFundingDistributions'] ?? 0.0);
        };
        $coupleArcherAggregateRaw = 0.0;
        foreach ($couple ?? [] as $personId) {
            $coupleArcherAggregateRaw += $archerForPerson($personId);
        }
        $coupleArcherAggregate = self::roundMoney($coupleArcherAggregateRaw);
        $reducedPortionsFor = static function (string $personId) use ($amountsByOwner, $coupleArcherAggregate): array {
            return self::archerReducedPortions(
                (float) ($amountsByOwner[$personId]['familyPortionApplied'] ?? 0.0),
                (float) ($amountsByOwner[$personId]['selfPortionApplied'] ?? 0.0),
                $coupleArcherAggregate,
            );
        };

        /*
         * The couple-wide ceiling on family-month capacity: no division of the one
         * family limit can put more than the largest refigured family limitation into
         * the two HSAs combined. Each spouse divides their *own* refigured amount
         * (Form 8889 line 6, Steps 1-4), which is what sharedFamilyContributionLimit
         * reports per owner; this maximum is the aggregate guard, and self-only months
         * are added to it undivided.
         */
        $rawSharedFamilyLimit = null;
        $sharedFamilyLimit = null;
        if ($familySharingApplies) {
            $candidates = [];
            foreach ($coupleMembersWithAccounts as $personId) {
                $candidates[] = $reducedPortionsFor($personId)[0];
            }
            $rawSharedFamilyLimit = max($candidates);
            $sharedFamilyLimit = self::roundMoney($rawSharedFamilyLimit);
        }
        /**
         * True where any spouse who holds an HSA has an undeterminable
         * limitation of their own, which makes the IRC 223(b)(5) aggregate
         * built from those limitations undeterminable too.
         */
        $householdPoolAmountIndeterminate = false;
        foreach ($coupleMembersWithAccounts as $personId) {
            if (($amountsByOwner[$personId]['familyPoolAmountIndeterminate'] ?? false) === true) {
                $householdPoolAmountIndeterminate = true;
            }
        }
        /**
         * The other question, asked separately. IRC 223(b)(5)(B)(ii) divides one
         * limitation between the spouses, so a spouse whose own accounts state
         * contradictory shares makes *the division* undeterminable while leaving
         * the amount being divided exactly as computable as it was: subparagraph
         * (A) fixes that amount from coverage facts, which a disagreement about
         * shares does not touch.
         */
        $householdDivisionIndeterminate = false;
        foreach ($coupleMembersWithAccounts as $personId) {
            if (($amountsByOwner[$personId]['familyDivisionIndeterminate'] ?? false) === true) {
                $householdDivisionIndeterminate = true;
            }
        }
        /*
         * A second reason the division can be unknown, and it is not a
         * disagreement about shares. IRC 223(b)(5)(B)(ii) divides the limitation
         * between the spouses, but Notice 2004-50 Q&A-31 is explicit that the
         * division presupposes two eligible individuals: "if only one spouse is an
         * eligible individual, only that spouse may contribute to an HSA
         * (notwithstanding the treatment under section 223(b)(5)(A) of both
         * spouses as having only family coverage)". Example (1) of that Q&A works
         * it -- H contributes the whole 5000 while W, whose plan is not a high
         * deductible health plan, contributes nothing.
         *
         * Ordinarily the caller's month list *is* the eligibility assertion and
         * the equal division follows from it, which is why this engine can divide
         * without testing IRC 223(c)(1). A subminimum deductible is precisely the
         * case where that assertion is contradicted by another fact from the same
         * caller, so the engine cannot tell whether the couple's limitation
         * belongs wholly to the coherent spouse or is shared with them. Reporting
         * half would assert the eligibility this check has just called into
         * question.
         *
         * The *amount* is untouched, and deliberately so: a self-only plan never
         * competes for the lowest family deductible, so the pool keeps reporting
         * its number while the division above it goes unstated. Any tier counts
         * here, unlike the amount test, because eligibility is what is in doubt
         * and a self-only contradiction impeaches it just as well.
         *
         * Only spouses who own a health savings account are asked about. A spouse
         * without one receives no share in this model -- the limitation goes whole
         * to the account owner, as the self-only-spouse vectors already pin -- so
         * there is no division for their contradiction to make unknowable.
         */
        $divisionEligibilityDoubtPersons = [];
        if ($familySharingApplies) {
            foreach ($coupleMembersWithAccounts as $personId) {
                if (array_key_exists($personId, $subminimumDeductibleByPerson)) {
                    $divisionEligibilityDoubtPersons[] = $personId;
                }
            }
        }
        $householdDivisionUnknown = $householdDivisionIndeterminate
            || count($divisionEligibilityDoubtPersons) > 0;
        $familyPoolKey = $couple === null ? null : "{$couple[0]}|{$couple[1]}";

        $explicitShareHolders = [];
        foreach ($coupleMembersWithAccounts as $personId) {
            if (array_key_exists('familyLimitShare', $facts[$personId]['rules'] ?? [])) {
                $explicitShareHolders[] = $personId;
            }
        }
        $shareByOwner = [];
        $sharingDiagnostics = [];
        if ($familySharingApplies && $householdPoolAmountIndeterminate) {
            // IRC 223(b)(5)(A) gives the spouses one family limitation and (B)(ii)
            // divides it between them, so each share is a function of facts belonging
            // to both. A spouse whose own coverage is coherent still cannot be told
            // their share of a limitation the couple's facts do not fix. Nulling the
            // pool alone was not enough: the pool went null while the accounts drawing
            // on it stayed determinate and kept reporting a maximum they could never
            // allocate.
            $sharingDiagnostics[] = self::diagnostic(
                'HSA_SHARED_FAMILY_LIMIT_INDETERMINATE',
                DiagnosticSeverity::ERROR,
                'IRC 223(b)(5) gives the spouses one family limitation to divide, so it is no more '
                    . 'determinable than the coverage facts of either of them. Another spouse\'s health '
                    . 'savings account coverage facts are missing or conflicting, so this account\'s share '
                    . 'of that limitation cannot be stated either.',
                'accounts',
                'IRC 223(b)(5)',
            );
        }
        /**
         * The division, reported separately from the amount and in different
         * words. HSA_SHARED_FAMILY_LIMIT_INDETERMINATE says the limitation itself
         * cannot be stated, which is false here: the couple's ceiling is a number
         * and the IRC 223(b)(5) pool reports it. What is unknown is how much of
         * that number belongs to each account, so every account drawing on the
         * pool still has a null maximum -- a share of a known amount is unknown
         * when the share is -- and this is an ERROR for that reason rather than a
         * note.
         */
        if ($familySharingApplies && $householdDivisionUnknown) {
            // Two causes, reported in different words because they call for
            // different corrections: a share disagreement is fixed by stating one
            // share, an impeached eligibility assertion by correcting the
            // deductible or the tier.
            $shareCause = 'A spouse\'s health savings accounts state different '
                . 'planRules.hsa.familyLimitShare values, so the agreed division is not determinable '
                . 'and no account\'s share of the limitation can be stated. '
                . 'State one agreed share on every one of that spouse\'s health savings accounts.';
            $eligibilityCause = implode(' and ', array_map(
                static fn (string $personId): string => "Person {$personId}",
                $divisionEligibilityDoubtPersons,
            )) . ' stated an annual deductible below the IRC 223(c)(2)(A)(i) minimum for coverage they are '
                . 'also stated to hold, and Notice 2004-50 Q&A-31 divides the limitation only between '
                . 'spouses who are each an eligible individual: "if only one spouse is an eligible '
                . 'individual, only that spouse may contribute to an HSA". The month list supplied for '
                . 'that person asserts eligibility their own deductible contradicts, so the engine cannot '
                . 'tell whether this limitation belongs wholly to the other spouse, as in Example (1) of '
                . 'that Q&A, or is divided. Correct the deductible or the coverage tier.';
            $sharingDiagnostics[] = self::diagnostic(
                'HSA_FAMILY_LIMIT_DIVISION_INDETERMINATE',
                DiagnosticSeverity::ERROR,
                'IRC 223(b)(5)(B)(ii) divides the single family limitation between the spouses as they '
                    . 'agree. '
                    . ($householdDivisionIndeterminate ? $shareCause : $eligibilityCause)
                    . ' The limitation itself is '
                    . 'unaffected and the IRC 223(b)(5) shared limit still reports it: subparagraph (A) '
                    . 'fixes that amount from coverage facts, which this does not touch.',
                'accounts',
                'IRC 223(b)(5)(B)(ii)',
            );
        }
        if ($familySharingApplies) {
            if ($explicitShareHolders !== []) {
                if (count($explicitShareHolders) !== count($coupleMembersWithAccounts)) {
                    $sharingDiagnostics[] = self::diagnostic(
                        'HSA_FAMILY_LIMIT_SHARE_REQUIRED_FOR_BOTH_SPOUSES',
                        DiagnosticSeverity::ERROR,
                        'When one spouse supplies planRules.hsa.familyLimitShare, every spouse with a health savings '
                            . 'account must supply one, so that the agreed division of the single IRC 223(b)(5) family '
                            . 'limit is complete.',
                        'accounts',
                        'IRC 223(b)(5)(B)(ii)',
                    );
                }
                $total = 0.0;
                foreach ($coupleMembersWithAccounts as $personId) {
                    $share = (float) ($facts[$personId]['rules']['familyLimitShare'] ?? 0);
                    $shareByOwner[$personId] = $share;
                    $total += $share;
                }
                if ($total > 1 + 1e-9) {
                    $formatted = self::jsNumber($total);
                    $sharingDiagnostics[] = self::diagnostic(
                        'HSA_FAMILY_LIMIT_SHARES_EXCEED_ONE',
                        DiagnosticSeverity::ERROR,
                        "The supplied family-limit shares total {$formatted}. IRC 223(b)(5)(B)(ii) divides one family "
                            . 'limit between the spouses, so the shares cannot exceed 1.',
                        'accounts',
                        'IRC 223(b)(5)(B)(ii)',
                    );
                }
                /*
                 * The other half of the same sentence. IRC 223(b)(5)(B)(ii) says the
                 * limitation "shall be divided equally between them unless they agree on a
                 * different division" — a division, not an allocation of part of it, so
                 * shares that do not exhaust the limitation are as impossible as shares
                 * that overrun it, and are reported at the same severity.
                 *
                 * Only when both spouses own an HSA and both supplied a share. An
                 * incomplete supply is already HSA_FAMILY_LIMIT_SHARE_REQUIRED_FOR_BOTH_SPOUSES,
                 * and where only one spouse owns an HSA the shares in hand cover one spouse,
                 * so a share below 1 is a complete division whose remainder the other spouse
                 * simply has no account to use.
                 */
                if (
                    count($coupleMembersWithAccounts) > 1
                    && count($explicitShareHolders) === count($coupleMembersWithAccounts)
                    && $total < 1 - 1e-9
                ) {
                    $formatted = self::jsNumber($total);
                    $sharingDiagnostics[] = self::diagnostic(
                        'HSA_FAMILY_LIMIT_SHARES_BELOW_ONE',
                        DiagnosticSeverity::ERROR,
                        "The supplied family-limit shares total {$formatted}. IRC 223(b)(5)(B)(ii) divides one family "
                            . 'limit between the spouses, so they must exhaust it: a total below 1 leaves part of the '
                            . 'limitation allocated to neither spouse and would silently forfeit it.',
                        'accounts',
                        'IRC 223(b)(5)(B)(ii)',
                    );
                }
            } elseif (count($coupleMembersWithAccounts) > 1) {
                foreach ($coupleMembersWithAccounts as $personId) {
                    $shareByOwner[$personId] = 1 / count($coupleMembersWithAccounts);
                }
                $sharingDiagnostics[] = self::diagnostic(
                    'HSA_FAMILY_LIMIT_DIVIDED_EQUALLY_BY_DEFAULT',
                    DiagnosticSeverity::INFO,
                    'IRC 223(b)(5)(B)(ii) divides the single family contribution limit equally between the spouses '
                        . 'unless they agree on a different division. Supply planRules.hsa.familyLimitShare on each '
                        . 'spouse\'s HSA to record a different agreement.',
                    'accounts',
                    'IRC 223(b)(5)(B)(ii)',
                );
            } else {
                foreach ($coupleMembersWithAccounts as $personId) {
                    $shareByOwner[$personId] = 1.0;
                }
                $sharingDiagnostics[] = self::diagnostic(
                    'HSA_SOLE_SPOUSE_ACCOUNT_ASSUMED_FULL_FAMILY_LIMIT',
                    DiagnosticSeverity::INFO,
                    'Only one spouse has a health savings account, so the whole IRC 223(b)(5) family limit is allocated '
                        . 'to it. That is the division the spouses are assumed to have agreed on; the statutory default '
                        . 'absent an agreement is an equal division.',
                    'accounts',
                    'IRC 223(b)(5)(B)(ii)',
                );
            }
            if ($couple !== null && $familyPoolKey !== null) {
                $undividedSelfPortions = 0.0;
                foreach ($coupleMembersWithAccounts as $personId) {
                    $undividedSelfPortions += $reducedPortionsFor($personId)[1];
                }
                $context['hsaFamilyPools'][$familyPoolKey] = [
                    'id' => "hsa223b5:{$familyPoolKey}",
                    'legalLimit' => 'IRC 223(b)(5) single family contribution limit divided between the spouses, '
                        . 'plus their undivided self-only-month limitations',
                    // The one family limitation is built out of the spouses'
                    // own refigured limitations, so it is only as determinable
                    // as they are. Where a spouse's limitation could not be
                    // determined their portion falls back to a statutory amount
                    // the statute may not allow on its own -- for 2004-2006
                    // IRC 223(b)(2) reaches the dollar amount only after
                    // comparing it with the plan's annual deductible -- and
                    // reporting the sum anyway would state a household ceiling
                    // on facts that do not support one. The per-owner
                    // IRC 223(b)(1) and 223(b)(3) pools already report null in
                    // that case; this pool now agrees with them.
                    //
                    // Only the amount governs. A disagreement about the IRC
                    // 223(b)(5)(B)(ii) shares leaves this ceiling standing: it
                    // is the couple's, not any one account's, and the statute
                    // fixes it before the division is reached. Nulling it for a
                    // share conflict withheld a number the record did support.
                    'limit' => $householdPoolAmountIndeterminate
                        ? null
                        : ($rawSharedFamilyLimit === null
                            ? $sharedFamilyLimit
                            : self::roundMoney($rawSharedFamilyLimit + $undividedSelfPortions)),
                    'used' => 0.0,
                ];
            }
        }

        foreach ($ownerIds as $ownerId) {
            $amounts = $amountsByOwner[$ownerId];
            $isSharingMember = $familySharingApplies && in_array($ownerId, $coupleMembersWithAccounts, true);
            $share = $isSharingMember ? ($shareByOwner[$ownerId] ?? 1.0) : null;
            $diagnostics = $amounts['diagnostics'];
            if ($isSharingMember) {
                array_push($diagnostics, ...$sharingDiagnostics);
                if (isset($recharacterized[$ownerId])) {
                    $diagnostics[] = self::diagnostic(
                        'HSA_SPOUSE_TREATED_AS_HAVING_FAMILY_COVERAGE',
                        DiagnosticSeverity::INFO,
                        'IRC 223(b)(5)(A) treats both spouses as having family coverage for any month in which either '
                            . 'of them has it, so self-only months were recharacterized as family months.',
                        "persons.{$ownerId}",
                        'IRC 223(b)(5)(A)',
                    );
                }
            }

            $indeterminate = $amounts['indeterminate'] || self::hasError($diagnostics);

            /*
             * Form 8889 line 6: the spouse's agreed (or default equal) share applies to
             * the family-coverage months' limitation only; self-only months are added
             * back undivided. The same division is applied to the counterfactual
             * without the IRC 223(b)(8) last-month rule so the amount attributable to
             * that rule is measured against the limit that would actually have applied.
             */
            $divided = static function (float $familyPortion, float $selfPortion, float $undivided) use ($share): float {
                return $share === null
                    ? $undivided
                    : self::roundMoney($share * $familyPortion + $selfPortion);
            };

            /*
             * IRC 223(b)(4)(A) reduces "the limitation which would (but for this
             * paragraph) apply under this subsection" — the whole of subsection (b),
             * including the IRC 223(b)(3) increase — "but not below zero". Its flush
             * text withdraws it from any individual to whom IRC 223(b)(5) applies; for
             * that individual IRC 223(b)(5)(B)(i) instead reduces the paragraph (1)
             * limitation "without regard to any additional contribution amount under
             * paragraph (3)", and (ii) divides only what survives the reduction. So
             * the ordering differs with the paragraph, not just the amount.
             */
            $archerAmount = $share === null ? $archerForPerson($ownerId) : $coupleArcherAggregate;
            $reducedDivided = static function (
                float $familyPortion,
                float $selfPortion,
                float $undivided,
            ) use ($share, $archerAmount): float {
                if ($share === null) {
                    return self::nonnegative($undivided - $archerAmount);
                }
                [$family, $self] = self::archerReducedPortions($familyPortion, $selfPortion, $archerAmount);
                return self::roundMoney($share * $family + $self);
            };
            $baseLimitAfterArcher = $indeterminate
                ? null
                : $reducedDivided(
                    (float) $amounts['familyPortionApplied'],
                    (float) $amounts['selfPortionApplied'],
                    (float) $amounts['proratedApplied'],
                );
            $baseLimitWithoutLastMonthRuleAfterArcher = $indeterminate
                ? null
                : $reducedDivided(
                    (float) $amounts['familyPortionWithoutLastMonthRule'],
                    (float) $amounts['selfPortionWithoutLastMonthRule'],
                    (float) $amounts['proratedWithoutLastMonthRule'],
                );

            /*
             * Only IRC 223(b)(4)(A) can reach the IRC 223(b)(3) additional contribution
             * amount, and only with the part the paragraph (1) limitation could not
             * absorb, since the subsection (b) limitation is reduced once as a whole.
             */
            $catchUpArcherResidual = $share === null
                ? max(0.0, $archerAmount - (float) $amounts['proratedApplied'])
                : 0.0;
            $catchUpAfterArcher = self::nonnegative((float) $amounts['catchUpApplied'] - $catchUpArcherResidual);
            $catchUpWithoutLastMonthRuleAfterArcher = self::nonnegative(
                (float) $amounts['catchUpWithoutLastMonthRule']
                - ($share === null
                    ? max(0.0, $archerAmount - (float) $amounts['proratedWithoutLastMonthRule'])
                    : 0.0),
            );
            $archerMsaLimitReduction = $indeterminate || $baseLimitAfterArcher === null
                ? 0.0
                : self::nonnegative(
                    $divided(
                        (float) $amounts['familyPortionApplied'],
                        (float) $amounts['selfPortionApplied'],
                        (float) $amounts['proratedApplied'],
                    )
                    + (float) $amounts['catchUpApplied']
                    - $baseLimitAfterArcher
                    - $catchUpAfterArcher,
                );

            /*
             * IRC 223(b)(4)(C) then reduces what is left by "the aggregate amount
             * contributed to health savings accounts of such individual for such taxable
             * year under section 408(d)(9)". The IRC 223(b)(4) flush text withdraws
             * subparagraph (A) — and only (A) — from an individual to whom IRC 223(b)(5)
             * applies, and IRC 223(b)(5)(B)(i) reduces the family limitation by the Archer
             * amount alone, so nothing routes (C) through paragraph (5). It therefore
             * reduces this individual's own subsection (b) limitation in every case: for a
             * married individual, the share the IRC 223(b)(5)(B)(ii) division left them,
             * and the IRC 223(b)(3) amount with whatever the paragraph (1) limitation
             * could not absorb — which the Archer reduction never reaches for that
             * individual.
             */
            $fundingAmount = $qualifiedHsaFundingFor($ownerId);
            if ($baseLimitAfterArcher === null) {
                $baseLimit = null;
                $catchUpApplied = $catchUpAfterArcher;
            } else {
                [$baseLimit, $catchUpApplied] = self::subsectionBReducedBy(
                    $baseLimitAfterArcher,
                    $catchUpAfterArcher,
                    $fundingAmount,
                );
            }
            if ($baseLimitWithoutLastMonthRuleAfterArcher === null) {
                $baseLimitWithoutLastMonthRule = null;
                $catchUpWithoutLastMonthRule = $catchUpWithoutLastMonthRuleAfterArcher;
            } else {
                [$baseLimitWithoutLastMonthRule, $catchUpWithoutLastMonthRule] = self::subsectionBReducedBy(
                    $baseLimitWithoutLastMonthRuleAfterArcher,
                    $catchUpWithoutLastMonthRuleAfterArcher,
                    $fundingAmount,
                );
            }
            $qualifiedHsaFundingLimitReduction =
                $indeterminate || $baseLimitAfterArcher === null || $baseLimit === null
                    ? 0.0
                    : self::nonnegative(
                        $baseLimitAfterArcher + $catchUpAfterArcher - $baseLimit - $catchUpApplied,
                    );

            $context['hsaBasePools'][$ownerId] = [
                'id' => "hsa223b1:{$ownerId}",
                'legalLimit' => 'IRC 223(b)(1) annual HSA contribution limit',
                'limit' => $baseLimit,
                'used' => 0.0,
            ];
            $context['hsaCatchUpPools'][$ownerId] = [
                'id' => "hsa223b3:{$ownerId}",
                'legalLimit' => 'IRC 223(b)(3) age 55 additional contribution amount',
                'limit' => $indeterminate ? null : $catchUpApplied,
                'used' => 0.0,
            ];

            if (!$indeterminate && $catchUpApplied > 0 && $couple !== null) {
                $diagnostics[] = self::diagnostic(
                    'HSA_AGE_55_ADDITIONAL_CONTRIBUTION_IS_PER_SPOUSE',
                    DiagnosticSeverity::INFO,
                    'The IRC 223(b)(3) additional contribution amount belongs to the individual, is excluded from the '
                        . 'IRC 223(b)(5) family division, and must be contributed to that spouse\'s own HSA. Two '
                        . 'spouses aged 55 or older therefore have two of them.',
                    "persons.{$ownerId}",
                    'IRC 223(b)(3); IRC 223(b)(5)(B)',
                );
            }

            $status = $indeterminate
                ? CalculationStatus::INDETERMINATE->value
                : CalculationStatus::DETERMINATE->value;
            $testingPeriod = null;
            $attributable = $indeterminate || $baseLimit === null || $baseLimitWithoutLastMonthRule === null
                ? 0.0
                : self::nonnegative(self::roundMoney(
                    $baseLimit
                    + $catchUpApplied
                    - $baseLimitWithoutLastMonthRule
                    - $catchUpWithoutLastMonthRule,
                ));

            if ($amounts['lastMonthRuleApplied'] && !$indeterminate) {
                $rules = $facts[$ownerId]['rules'];
                $testingMonths = $parameters['testingPeriodMonths'] ?? 13;
                if (array_key_exists('testingPeriodSatisfied', $rules) && $rules['testingPeriodSatisfied'] === true) {
                    $testingStatus = 'satisfied';
                } elseif (
                    array_key_exists('testingPeriodSatisfied', $rules)
                    && $rules['testingPeriodSatisfied'] === false
                ) {
                    $testingStatus = ($rules['testingPeriodFailureByDeathOrDisability'] ?? null) === true
                        ? 'failed_exception_applies'
                        : 'failed';
                } else {
                    $testingStatus = 'unresolved';
                }
                $exposed = $testingStatus === 'failed' || $testingStatus === 'unresolved' ? $attributable : 0.0;
                $nextYear = $context['taxYear'] + 1;
                $testingPeriod = [
                    'months' => $testingMonths,
                    'startMonth' => "{$context['taxYear']}-12",
                    'endMonth' => "{$nextYear}-12",
                    'status' => $testingStatus,
                    'grossIncomeInclusionIfFailed' => $exposed,
                    'additionalTaxIfFailed' => self::roundMoney($exposed * 0.1),
                    'inclusionTaxYear' => $nextYear,
                ];
                if ($testingStatus === 'unresolved') {
                    $status = CalculationStatus::DETERMINATE_WITH_ASSUMPTIONS->value;
                    $formatted = self::localeNumber($attributable);
                    $diagnostics[] = self::diagnostic(
                        'HSA_LAST_MONTH_RULE_TESTING_PERIOD_UNRESOLVED',
                        DiagnosticSeverity::WARNING,
                        "The IRC 223(b)(8) last-month rule was elected, so \${$formatted} of the calculated ceiling "
                            . 'exists only because of IRC 223(b)(8)(A). Whether the '
                            . "{$testingMonths}-month testing period ending {$nextYear}-12 is satisfied was not "
                            . 'supplied, so compliance is not assumed. Failing it includes that amount in gross income '
                            . "for {$nextYear} and adds a 10 percent tax under IRC 223(b)(8)(B)(i).",
                        "persons.{$ownerId}",
                        'IRC 223(b)(8)(B)',
                    );
                } elseif ($testingStatus === 'failed') {
                    $formatted = self::localeNumber($exposed);
                    $diagnostics[] = self::diagnostic(
                        'HSA_LAST_MONTH_RULE_TESTING_PERIOD_FAILED',
                        DiagnosticSeverity::WARNING,
                        'The IRC 223(b)(8)(B)(iii) testing period is not satisfied. Under IRC 223(b)(8)(B)(i), '
                            . "\${$formatted} is included in gross income for tax year {$nextYear} and an additional "
                            . 'tax of 10 percent applies. The inclusion falls in the year of the failure, not the '
                            . 'contribution year, so it is not reflected in this year\'s federal tax effects.',
                        "persons.{$ownerId}",
                        'IRC 223(b)(8)(B)(i)',
                    );
                } elseif ($testingStatus === 'failed_exception_applies') {
                    $diagnostics[] = self::diagnostic(
                        'HSA_TESTING_PERIOD_FAILURE_EXCEPTED',
                        DiagnosticSeverity::INFO,
                        'The testing period was failed, but IRC 223(b)(8)(B)(ii) excepts a failure caused by the '
                            . 'individual\'s death or disability, so there is no income inclusion and no additional tax.',
                        "persons.{$ownerId}",
                        'IRC 223(b)(8)(B)(ii)',
                    );
                }
            }

            if (!$indeterminate && $archerAmount > 0) {
                $paidFormatted = self::localeNumber($archerAmount);
                $takenFormatted = self::localeNumber($archerMsaLimitReduction);
                $diagnostics[] = $share === null
                    ? self::diagnostic(
                        'HSA_ARCHER_MSA_CONTRIBUTIONS_REDUCE_LIMIT',
                        DiagnosticSeverity::INFO,
                        'IRC 223(b)(4)(A) reduces the IRC 223(b) limitation, but not below zero, by the '
                            . "\${$paidFormatted} aggregate amount paid for the taxable year to Archer MSAs of this "
                            . "individual, which took \${$takenFormatted} off the ceiling. The amount paid is taken "
                            . 'as supplied; IRC 220 is not modelled and the amount is not tested against the Archer '
                            . 'MSA contribution limitation.',
                        "persons.{$ownerId}",
                        'IRC 223(b)(4)(A)',
                    )
                    : self::diagnostic(
                        'HSA_ARCHER_MSA_CONTRIBUTIONS_REDUCE_LIMIT',
                        DiagnosticSeverity::INFO,
                        'IRC 223(b)(4) does not apply to an individual to whom IRC 223(b)(5) applies, so the '
                            . "\${$paidFormatted} aggregate amount paid to Archer MSAs of both spouses reduces the "
                            . 'single IRC 223(b)(1) family limitation under IRC 223(b)(5)(B)(i) before IRC '
                            . "223(b)(5)(B)(ii) divides it, which took \${$takenFormatted} off this spouse's "
                            . 'ceiling. IRC 223(b)(5)(B) is applied without regard to the IRC 223(b)(3) additional '
                            . 'contribution amount, so the reduction never reaches it. The amount paid is taken as '
                            . 'supplied; IRC 220 is not modelled.',
                        "persons.{$ownerId}",
                        'IRC 223(b)(5)(B)(i)',
                    );
            }

            if (!$indeterminate && $fundingAmount > 0) {
                $fundedFormatted = self::localeNumber($fundingAmount);
                $fundingTakenFormatted = self::localeNumber($qualifiedHsaFundingLimitReduction);
                $diagnostics[] = self::diagnostic(
                    'HSA_QUALIFIED_HSA_FUNDING_DISTRIBUTION_REDUCES_LIMIT',
                    DiagnosticSeverity::INFO,
                    $share === null
                        ? 'IRC 223(b)(4)(C) reduces the IRC 223(b) limitation, but not below zero, by the '
                            . "\${$fundedFormatted} aggregate amount contributed to health savings accounts of this "
                            . 'individual for the taxable year under IRC 408(d)(9), which took '
                            . "\${$fundingTakenFormatted} off the ceiling. The amount is taken as supplied; the IRC "
                            . '408(d)(9)(C) once-per-lifetime limitation and the separate IRC 408(d)(9)(D) testing '
                            . 'period are not modelled.'
                        : 'The IRC 223(b)(4) flush text withdraws subparagraph (A) alone from an individual to whom '
                            . 'IRC 223(b)(5) applies, so IRC 223(b)(4)(C) still applies to this spouse. The '
                            . "\${$fundedFormatted} contributed under IRC 408(d)(9) is an amount of this individual "
                            . 'and not of the couple, and IRC 223(b)(5)(B)(i) reduces the family limitation by the '
                            . 'Archer MSA amount alone, so it reduces this spouse\'s own limitation after the IRC '
                            . '223(b)(5)(B)(ii) division rather than the family limitation before it. That took '
                            . "\${$fundingTakenFormatted} off the ceiling, reaching the IRC 223(b)(3) additional "
                            . 'contribution amount with whatever the IRC 223(b)(1) limitation could not absorb. The '
                            . 'amount is taken as supplied; the IRC 408(d)(9)(C) once-per-lifetime limitation and the '
                            . 'separate IRC 408(d)(9)(D) testing period are not modelled.',
                    "persons.{$ownerId}",
                    'IRC 223(b)(4)(C)',
                );
            }

            // The IRC 125 / IRC 223 conflict is diagnosed and never enforced,
            // so the plan status is fixed before these diagnostics are attached.
            // Eligible-individual status is a caller-supplied fact everywhere
            // else in this engine and no rule here overrides one; a caller who
            // has already accounted for the conflict, by ending the arrangement
            // mid-year and supplying the correct eligible months, must still get
            // the answer their facts imply. Every IRC 223(b) figure therefore
            // survives intact and only the account's reported status reflects
            // the ERROR, through the ordinary rule that an error makes a result
            // indeterminate.
            $planStatus = self::accountStatusFromDiagnostics($status, $diagnostics);
            $spouseId = self::hsaSpouseIdOf($couple, $ownerId);
            $ownFsa = $section223FsaFacts[$ownerId] ?? self::emptyHealthFsaSection223Facts();
            $spouseFsa = $spouseId === null
                ? self::emptyHealthFsaSection223Facts()
                : ($section223FsaFacts[$spouseId] ?? self::emptyHealthFsaSection223Facts());
            if ($ownFsa['generalPurpose']) {
                $carryoverSentence = $ownFsa['generalPurposeCarryover']
                    ? ' Amounts carried over from the preceding plan year are general-purpose funds in the year they '
                        . 'land, so the disqualification reaches that whole plan year.'
                    : '';
                $diagnostics[] = self::diagnostic(
                    'HEALTH_FSA_DISQUALIFIES_HSA_ELIGIBILITY',
                    DiagnosticSeverity::ERROR,
                    'This individual holds a general-purpose health flexible spending arrangement. Rev. Rul. 2004-45 '
                        . 'holds that an individual covered by a health FSA paying or reimbursing section 213(d) '
                        . 'medical expenses before the IRC 223(c)(2)(A)(i) minimum annual deductible is satisfied is '
                        . 'not an eligible individual, because IRC 223(c)(1)(A)(ii) requires that an eligible '
                        . 'individual not be covered by a health plan that is not a high deductible health plan and '
                        . 'that provides coverage for a benefit the HDHP covers.' . $carryoverSentence
                        . ' The IRC 223(b) figures reported here are unchanged: eligible-individual status is supplied '
                        . 'by the caller and is never overridden, so this reports the conflict rather than enforcing '
                        . 'it.',
                    "persons.{$ownerId}",
                    'IRC 223(c)(1)(A)(ii); Rev. Rul. 2004-45',
                );
            }
            if ($spouseFsa['generalPurpose']) {
                $diagnostics[] = self::diagnostic(
                    'SPOUSE_HEALTH_FSA_DISQUALIFIES_HSA_ELIGIBILITY',
                    DiagnosticSeverity::ERROR,
                    "This individual's spouse holds a general-purpose health flexible spending arrangement. Rev. Rul. "
                        . '2004-45 states that the result is the same where the individual is covered by a health FSA '
                        . "sponsored by the employer of the individual's spouse, because such an arrangement can "
                        . "reimburse this individual's own section 213(d) medical expenses, so it is disqualifying "
                        . 'coverage under IRC 223(c)(1)(A)(ii) for both of them. As with an individual\'s own '
                        . 'arrangement, the IRC 223(b) figures are unchanged and the conflict is reported rather than '
                        . 'enforced.',
                    "persons.{$ownerId}",
                    'IRC 223(c)(1)(A)(ii); Rev. Rul. 2004-45',
                );
            }
            if ($ownFsa['generalPurposeGracePeriod'] || $spouseFsa['generalPurposeGracePeriod']) {
                $diagnostics[] = self::diagnostic(
                    'HEALTH_FSA_GRACE_PERIOD_EXTENDS_HSA_DISQUALIFICATION',
                    DiagnosticSeverity::INFO,
                    'The disqualifying arrangement offers a Prop. Treas. Reg. 1.125-1(e) grace period. Notice 2005-86 '
                        . 'holds that an individual covered by a general-purpose health FSA during a grace period is '
                        . 'generally not eligible to contribute to a health savings account until the first day of the '
                        . 'month following the end of that grace period, even where the arrangement has no unused '
                        . 'benefits left. The grace-period months of the following plan year are therefore affected as '
                        . "well, which this year's eligible-month input cannot express.",
                    "persons.{$ownerId}",
                    'Notice 2005-86',
                );
            }
            if (!$ownFsa['generalPurpose']
                && !$spouseFsa['generalPurpose']
                && !$ownFsa['purposeUnstated']
                && !$spouseFsa['purposeUnstated']
                && ($ownFsa['hsaCompatible'] || $spouseFsa['hsaCompatible'])
            ) {
                $diagnostics[] = self::diagnostic(
                    'HEALTH_FSA_TREATED_AS_HSA_COMPATIBLE',
                    DiagnosticSeverity::INFO,
                    'Every health flexible spending arrangement in this scenario is limited-purpose or '
                        . 'post-deductible. Rev. Rul. 2004-45 holds that an arrangement reimbursing only vision and '
                        . 'dental benefits, which are permitted coverage, and preventive care, or reimbursing only '
                        . 'expenses incurred after the IRC 223(c)(2)(A)(i) minimum annual deductible is satisfied, '
                        . 'leaves the individual an eligible individual, so IRC 223(c)(1)(A)(ii) is not failed.',
                    "persons.{$ownerId}",
                    'IRC 223(c)(1)(A)(ii); Rev. Rul. 2004-45',
                );
            }

            $diagnostics[] = self::diagnostic(
                'HSA_ELIGIBILITY_FACTS_SUPPLIED_BY_CALLER',
                DiagnosticSeverity::INFO,
                'This calculation applies IRC 223(b) to the months and coverage supplied. It does not test '
                    . 'eligible-individual status under IRC 223(c)(1), whether the plan is a high deductible health '
                    . 'plan under IRC 223(c)(2), the IRC 223(b)(6) denial for a person claimed as another taxpayer\'s '
                    . 'dependent, or Medicare entitlement under IRC 223(b)(7). The IRC 223(b)(4)(A) and '
                    . '223(b)(5)(B)(i) reductions are applied from the Archer MSA contributions supplied on '
                    . 'persons[].archerMsaContributions, which are taken as stated and not tested against IRC 220. '
                    . 'The IRC 223(b)(4)(C) reduction is applied from the qualified HSA funding distribution supplied '
                    . 'on persons[].qualifiedHsaFundingDistributions, which is likewise taken as stated: the IRC '
                    . '408(d)(9)(C) once-per-lifetime limitation and the separate IRC 408(d)(9)(D) testing period are '
                    . 'not tested. Where the scenario also holds a health flexible spending arrangement, the IRC '
                    . '223(c)(1)(A)(ii) consequence of its Rev. Rul. 2004-45 purpose is reported and never enforced: '
                    . 'the figures here are the ones the supplied months and coverage produce.',
                "persons.{$ownerId}",
                'IRC 223',
            );

            $detail = [
                'coverageTierByMonth' => $facts[$ownerId]['months'] ?? array_fill(0, self::HSA_MONTHS_IN_YEAR, null),
                'eligibleMonthCount' => $amounts['eligibleMonthCount'],
                // Withheld where the rejected deductible fed them. Coverage
                // months and the IRC 223(b)(3) amount beside them stay, because
                // neither is computed from the deductible; only the three
                // limitation figures are.
                'appliedAnnualLimitByMonth' => $subminimumDeductibleReaches($ownerId)
                    ? array_map(
                        static fn (bool $affected, $value) => $affected ? null : $value,
                        $subminimumAffectedMonths($ownerId),
                        $amounts['appliedAnnualLimitByMonth'],
                    )
                    : $amounts['appliedAnnualLimitByMonth'],
                'proratedContributionLimit' => $subminimumDeductibleReaches($ownerId)
                    ? null
                    : $amounts['proratedApplied'],
                'contributionLimitWithoutLastMonthRule' => $subminimumDeductibleReaches($ownerId)
                    ? null
                    : $amounts['proratedWithoutLastMonthRule'],
                'additionalContributionAmount' => $amounts['catchUpApplied'],
                // Null where the division is undeterminable rather than the
                // placeholder taken from whichever of the owner's accounts was listed
                // first. Reversing two contradictory accounts changed this from 0.5 to
                // 0.25 while the diagnostic said no share could be stated -- an
                // input-order-dependent number a caller could multiply by the
                // limitation the pool now preserves.
                'familyLimitShare' => $householdDivisionUnknown ? null : $share,
                // Null where the family limitation could not be determined, for the
                // same reason the IRC 223(b)(5) pool is: this field *is* that
                // limitation, seen per owner, and reporting the uncompared statutory
                // amount here would leave the ceiling the pool refuses to state still
                // published one field away. The field is already nullable for the
                // unrelated case of no family limit being shared at all.
                //
                // It follows the amount and not the division, because it is the
                // amount: its contract is the limitation this owner divides, taken
                // *before* the IRC 223(b)(5)(B)(ii) share is applied. A share
                // disagreement therefore leaves it reportable, and familyLimitShare
                // beside it is the field that goes unusable. Reading the division
                // flag here would null the one figure a caller reconciling
                // contradictory shares actually needs.
                'sharedFamilyContributionLimit' => $isSharingMember
                    && $householdPoolAmountIndeterminate !== true
                    ? self::roundMoney(self::archerReducedPortions(
                        (float) $amounts['familyPortionApplied'],
                        (float) $amounts['selfPortionApplied'],
                        $archerAmount,
                    )[0])
                    : null,
                'archerMsaContributionsApplied' => $archerAmount,
                'archerMsaReductionPrecedesFamilyDivision' => $share !== null,
                'archerMsaLimitReduction' => $archerMsaLimitReduction,
                'qualifiedHsaFundingDistributionsApplied' => $fundingAmount,
                'qualifiedHsaFundingLimitReduction' => $qualifiedHsaFundingLimitReduction,
                'lastMonthRuleApplied' => $amounts['lastMonthRuleApplied'],
                'amountAttributableToLastMonthRule' => $attributable,
                'testingPeriod' => $testingPeriod,
            ];

            $context['hsaPlans'][$ownerId] = [
                'status' => $planStatus,
                'diagnostics' => $diagnostics,
                'statutoryMaximum' => $baseLimit === null
                    ? null
                    : self::roundMoney($baseLimit + $catchUpApplied),
                'detail' => $detail,
                'familyPoolKey' => $isSharingMember ? $familyPoolKey : null,
                'familyPoolUsageDeterminable' => $amounts['ageKnown']
                    && (float) $amounts['catchUpApplied'] === 0.0
                    && $archerAmount === 0.0
                    && $fundingAmount === 0.0,
            ];
        }

        // Existing contributions consume the base limit first and then the IRC
        // 223(b)(3) increase, which is the only ordering that never reports capacity
        // the statute does not allow.
        //
        foreach ($hsaAccounts as $account) {
            $existing = self::roundMoney(
                $account['existingContributions']['hsaDeductible']
                + $account['existingContributions']['hsaEmployerOrCafeteria'],
            );
            if ($existing <= 0) {
                continue;
            }
            $ownerId = (string) $account['ownerId'];
            if (
                !isset($context['hsaBasePools'][$ownerId])
                || !isset($context['hsaCatchUpPools'][$ownerId])
            ) {
                continue;
            }
            $poolKeyEarly = $context['hsaPlans'][$ownerId]['familyPoolKey'] ?? null;
            /*
             * An owner whose own IRC 223(b)(1) limitation is undeterminable still has
             * a couple-wide IRC 223(b)(5) ceiling where only the (B)(ii) division is
             * unknown, and the pool reports it. What it may not do is publish a draw
             * against that ceiling it cannot compute.
             *
             * Existing contributions consume the paragraph (1) limitation first and
             * reach the paragraph (3) additional amount only once it is exhausted, so
             * wherever a paragraph (3) amount exists the draw turns on the size of
             * this owner's paragraph (1) share -- the very thing the unresolved IRC
             * 223(b)(5)(B)(ii) division leaves unknown. familyPoolUsageDeterminable
             * therefore requires no paragraph (3) amount at all and no IRC 223(b)(4)
             * reduction, those coming off the paragraph (1) share first and turning on
             * the same unknown.
             *
             * Both bounds were tried and both misreported. Charging everything paid in
             * accused a 56-year-old who contributed 9750 for 2026 -- 8750 under the
             * 1/0 division IRC 223(b)(5)(B)(ii) permits, plus their own 1000 -- of
             * exceeding an 8750 pool. Charging everything less the largest possible
             * paragraph (3) amount then reported a pool as wholly untouched when a
             * 9500 qualified HSA funding distribution had left at most 250 of room. A
             * bound is not a usage, and publishing one as though it were is what
             * produced both.
             */
            if ($context['hsaBasePools'][$ownerId]['limit'] === null) {
                if ($poolKeyEarly !== null && isset($context['hsaFamilyPools'][$poolKeyEarly])) {
                    if (($context['hsaPlans'][$ownerId]['familyPoolUsageDeterminable'] ?? false) === true) {
                        // Nothing can absorb a spill, so the whole contribution came
                        // out of the couple's limitation whichever way the division
                        // falls.
                        $context['hsaFamilyPools'][$poolKeyEarly]['used'] = self::roundMoney(
                            (float) $context['hsaFamilyPools'][$poolKeyEarly]['used'] + $existing,
                        );
                    } else {
                        $context['hsaFamilyPools'][$poolKeyEarly]['usageIndeterminate'] = true;
                    }
                }
                continue;
            }
            $basePool =& $context['hsaBasePools'][$ownerId];
            $toBase = self::minMoney($existing, self::nonnegative((float) $basePool['limit'] - (float) $basePool['used']));
            $basePool['used'] = self::roundMoney((float) $basePool['used'] + $toBase);
            unset($basePool);
            $context['hsaCatchUpPools'][$ownerId]['used'] = self::roundMoney(
                (float) $context['hsaCatchUpPools'][$ownerId]['used'] + $existing - $toBase,
            );
            $poolKey = $context['hsaPlans'][$ownerId]['familyPoolKey'] ?? null;
            if ($poolKey !== null && isset($context['hsaFamilyPools'][$poolKey])) {
                $context['hsaFamilyPools'][$poolKey]['used'] = self::roundMoney(
                    (float) $context['hsaFamilyPools'][$poolKey]['used'] + $toBase,
                );
            }
        }
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @return array<string,mixed>
     */
    private static function allocateHsa(array &$context, array $account): array
    {
        $ownerId = (string) $account['ownerId'];
        $plan = $context['hsaPlans'][$ownerId];
        $annual = $account['existingContributions'];
        $additional = self::zeroComponents();
        $sharedLimits = [];
        $diagnostics = $plan['diagnostics'];
        $hasBase = isset($context['hsaBasePools'][$ownerId]);
        $hasCatchUp = isset($context['hsaCatchUpPools'][$ownerId]);
        $familyPoolKey = $plan['familyPoolKey'];
        $hasFamily = $familyPoolKey !== null && isset($context['hsaFamilyPools'][$familyPoolKey]);

        if ($plan['status'] === CalculationStatus::UNAVAILABLE->value || !$hasBase || !$hasCatchUp) {
            $outcome = [
                'status' => CalculationStatus::UNAVAILABLE->value,
                'statutoryMaximum' => 0.0,
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
            ];
            if ($plan['detail'] !== null) {
                $outcome['hsaDetail'] = $plan['detail'];
            }
            return $outcome;
        }

        if ($plan['status'] === CalculationStatus::INDETERMINATE->value) {
            self::reportPoolWithoutConsuming($context['hsaBasePools'][$ownerId], $sharedLimits);
            if ($hasFamily) {
                self::reportPoolWithoutConsuming($context['hsaFamilyPools'][$familyPoolKey], $sharedLimits);
            }
            self::reportPoolWithoutConsuming($context['hsaCatchUpPools'][$ownerId], $sharedLimits);
            $outcome = [
                'status' => CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => $plan['statutoryMaximum'],
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
            ];
            if ($plan['detail'] !== null) {
                $outcome['hsaDetail'] = $plan['detail'];
            }
            return $outcome;
        }

        $refs = [['hsaBasePools', $ownerId]];
        if ($hasFamily) {
            $refs[] = ['hsaFamilyPools', $familyPoolKey];
        }
        $remaining = [];
        foreach ($refs as [$category, $key]) {
            $remaining[] = self::poolRemaining($context[$category][$key]);
        }
        $baseAmount = self::takeAcrossPools($context, $refs, self::minMoney(...$remaining), $sharedLimits);
        $catchUpPool =& $context['hsaCatchUpPools'][$ownerId];
        $catchUpAmount = self::takeFromPool(
            $catchUpPool,
            self::poolRemaining($catchUpPool) ?? 0.0,
            $sharedLimits,
        );
        unset($catchUpPool);
        $total = self::roundMoney($baseAmount + $catchUpAmount);

        // IRC 106(d) employer and cafeteria-plan contributions are excluded from
        // income rather than deducted, and IRC 223(b)(4)(B) makes them reduce the
        // IRC 223(a) deduction, so they are filled first out of the same ceiling.
        $employerTarget = self::money(
            $account['planRules']['expectedEmployerContribution'] ?? null,
            "accounts.{$account['id']}.planRules.expectedEmployerContribution",
        );
        $employerRemaining = self::nonnegative($employerTarget - $annual['hsaEmployerOrCafeteria']);
        $toEmployer = self::minMoney($total, $employerRemaining);
        $toDeductible = self::roundMoney($total - $toEmployer);

        $additional['hsaEmployerOrCafeteria'] = $toEmployer;
        $additional['hsaDeductible'] = $toDeductible;
        $annual['hsaEmployerOrCafeteria'] = self::roundMoney($annual['hsaEmployerOrCafeteria'] + $toEmployer);
        $annual['hsaDeductible'] = self::roundMoney($annual['hsaDeductible'] + $toDeductible);

        $outcome = [
            'status' => self::accountStatusFromDiagnostics($plan['status'], $diagnostics),
            'statutoryMaximum' => $plan['statutoryMaximum'],
            'annualComponents' => $annual,
            'additionalComponents' => $additional,
            'planTermDependentCapacity' => 0.0,
            'sharedLimits' => $sharedLimits,
            'diagnostics' => $diagnostics,
        ];
        if ($plan['detail'] !== null) {
            $outcome['hsaDetail'] = $plan['detail'];
        }
        return $outcome;
    }

    /** @param array<string,float> $components */
    private static function regularIraContributionAmount(array $components): float
    {
        return self::roundMoney(
            $components['deductibleIra']
            + $components['nondeductibleIra']
            + $components['rothIra']
            + $components['unclassifiedIra'],
        );
    }

    /** @param array<string,mixed> $pool */
    private static function poolRemaining(array $pool): ?float
    {
        if ($pool['limit'] === null || ($pool['usageIndeterminate'] ?? false) === true) {
            return null;
        }
        return self::nonnegative((float) $pool['limit'] - (float) $pool['used']);
    }

    /** @param array<string,mixed> $pool
     *  @param list<array<string,mixed>> $sharedLimits
     */
    private static function takeFromPool(array &$pool, float $requested, array &$sharedLimits): float
    {
        $usedBefore = (float) $pool['used'];
        if ($pool['limit'] === null || ($pool['usageIndeterminate'] ?? false) === true) {
            $sharedLimits[] = [
                'id' => $pool['id'],
                'legalLimit' => $pool['legalLimit'],
                // The ceiling is still reported where it is known. Only the draw
                // against it is withheld, which is the whole distinction the flag
                // exists to draw.
                'limit' => $pool['limit'] === null ? null : (float) $pool['limit'],
                // A null limit leaves the draw perfectly knowable; only the third
                // state withholds it.
                'usedBeforeAccount' => ($pool['usageIndeterminate'] ?? false) === true ? null : $usedBefore,
                'usedByAccount' => ($pool['usageIndeterminate'] ?? false) === true ? null : 0.0,
                'remainingAfterAccount' => null,
            ];
            return 0.0;
        }
        $taken = self::minMoney($requested, self::nonnegative((float) $pool['limit'] - (float) $pool['used']));
        $pool['used'] = self::roundMoney((float) $pool['used'] + $taken);
        $sharedLimits[] = [
            'id' => $pool['id'],
            'legalLimit' => $pool['legalLimit'],
            'limit' => (float) $pool['limit'],
            'usedBeforeAccount' => $usedBefore,
            'usedByAccount' => $taken,
            'remainingAfterAccount' => self::nonnegative((float) $pool['limit'] - (float) $pool['used']),
        ];
        return $taken;
    }

    /** @param array<string,mixed> $pool
     *  @param list<array<string,mixed>> $sharedLimits
     */
    private static function reportPoolWithoutConsuming(array $pool, array &$sharedLimits): void
    {
        $sharedLimits[] = [
            'id' => $pool['id'],
            'legalLimit' => $pool['legalLimit'],
            'limit' => $pool['limit'] === null ? null : (float) $pool['limit'],
            'usedBeforeAccount' => ($pool['usageIndeterminate'] ?? false) === true
                ? null
                : (float) $pool['used'],
            'usedByAccount' => ($pool['usageIndeterminate'] ?? false) === true ? null : 0.0,
            'remainingAfterAccount' => self::poolRemaining($pool),
        ];
    }

    /** @param list<array<string,mixed>> $diagnostics */
    private static function accountStatusFromDiagnostics(string $defaultStatus, array $diagnostics): string
    {
        if (self::hasError($diagnostics)) {
            return CalculationStatus::INDETERMINATE->value;
        }
        if ($defaultStatus === CalculationStatus::DETERMINATE->value) {
            foreach ($diagnostics as $entry) {
                $code = (string) ($entry['code'] ?? '');
                if (str_contains($code, 'ASSUM') || str_contains($code, 'PLAN_TERM')) {
                    return CalculationStatus::DETERMINATE_WITH_ASSUMPTIONS->value;
                }
            }
        }
        return $defaultStatus;
    }

    /** @param array<string,mixed> $context
     *  @param list<array{0:string,1:string}> $refs
     *  @param list<array<string,mixed>> $sharedLimits
     */
    private static function takeAcrossPools(
        array &$context,
        array $refs,
        float $requested,
        array &$sharedLimits,
    ): float {
        foreach ($refs as [$category, $key]) {
            if (
                $context[$category][$key]['limit'] === null
                || ($context[$category][$key]['usageIndeterminate'] ?? false) === true
            ) {
                foreach ($refs as [$reportCategory, $reportKey]) {
                    self::reportPoolWithoutConsuming($context[$reportCategory][$reportKey], $sharedLimits);
                }
                return 0.0;
            }
        }
        $limits = [$requested];
        foreach ($refs as [$category, $key]) {
            $limits[] = self::poolRemaining($context[$category][$key]);
        }
        $taken = self::minMoney(...$limits);
        foreach ($refs as [$category, $key]) {
            $pool =& $context[$category][$key];
            $usedBefore = (float) $pool['used'];
            $pool['used'] = self::roundMoney((float) $pool['used'] + $taken);
            $sharedLimits[] = [
                'id' => $pool['id'],
                'legalLimit' => $pool['legalLimit'],
                'limit' => (float) $pool['limit'],
                'usedBeforeAccount' => $usedBefore,
                'usedByAccount' => $taken,
                'remainingAfterAccount' => self::poolRemaining($pool),
            ];
            unset($pool);
        }
        return $taken;
    }

    /** @param array<string,mixed> $pool
     *  @param list<array<string,mixed>> $sharedLimits
     */
    private static function consumeExactFromPool(array &$pool, float $amount, array &$sharedLimits): void
    {
        $usedBefore = (float) $pool['used'];
        $pool['used'] = self::roundMoney((float) $pool['used'] + $amount);
        $sharedLimits[] = [
            'id' => $pool['id'],
            'legalLimit' => $pool['legalLimit'],
            'limit' => $pool['limit'] === null ? null : (float) $pool['limit'],
            'usedBeforeAccount' => $usedBefore,
            'usedByAccount' => $amount,
            'remainingAfterAccount' => self::poolRemaining($pool),
        ];
    }

    /** @param array<string,mixed> $account
     *  @param list<array<string,mixed>> $diagnostics
     *  @return array<string,mixed>
     */
    private static function emptyOutcome(
        array $account,
        string $status,
        ?float $statutoryMaximum,
        array $diagnostics = [],
    ): array {
        return [
            'status' => $status,
            'statutoryMaximum' => $statutoryMaximum,
            'annualComponents' => $account['existingContributions'],
            'additionalComponents' => self::zeroComponents(),
            'planTermDependentCapacity' => 0.0,
            'sharedLimits' => [],
            'diagnostics' => $diagnostics,
        ];
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @return array<string,mixed>
     */
    private static function allocateAccount(array &$context, array $account): array
    {
        $traits = self::traits($account['type']);
        if (!self::availabilityForAccount($context['parameters'], $traits)) {
            $diagnostics = [self::diagnostic(
                'ACCOUNT_TYPE_NOT_AVAILABLE_FOR_YEAR',
                DiagnosticSeverity::ERROR,
                "{$account['type']} was not available in tax year {$context['taxYear']}.",
                "accounts.{$account['id']}",
            )];
            if (self::sumComponents($account['existingContributions']) > 0) {
                $diagnostics[] = self::diagnostic(
                    'EXISTING_CONTRIBUTION_BEFORE_ACCOUNT_AVAILABLE',
                    DiagnosticSeverity::ERROR,
                    'Existing contributions were supplied for an account type that was not yet available.',
                    "accounts.{$account['id']}.existingContributions",
                );
            }
            return self::emptyOutcome($account, CalculationStatus::UNAVAILABLE->value, 0.0, $diagnostics);
        }
        return match ($traits['family']) {
            'regular_traditional_ira' => self::allocateTraditionalIra($context, $account),
            'regular_roth_ira' => self::allocateRothIra($context, $account),
            'inherited_ira' => self::emptyOutcome(
                $account,
                CalculationStatus::INELIGIBLE->value,
                0.0,
                [self::diagnostic(
                    'INHERITED_IRA_CANNOT_ACCEPT_REGULAR_CONTRIBUTIONS',
                    DiagnosticSeverity::INFO,
                    "An inherited IRA cannot accept the beneficiary's regular annual IRA contribution.",
                    "accounts.{$account['id']}",
                    'IRC 408(d)(3)(C)',
                )],
            ),
            'sep' => self::allocateSep($context, $account, $traits),
            'simple' => self::allocateSimple($context, $account, $traits),
            'qualified_elective' => self::allocateQualifiedElective($context, $account, $traits),
            'section457' => self::allocateSection457($context, $account, $traits),
            'annual_additions_only' => self::allocateAnnualAdditionsOnly($context, $account, $traits),
            'defined_benefit' => self::allocateDefinedBenefit($context, $account),
            'section457f' => self::allocateSection457f($account),
            'hsa' => self::allocateHsa($context, $account),
            'health_fsa' => self::allocateHealthFsa($context, $account),
            'dependent_care_fsa' => self::allocateDependentCareFsa($context, $account),
            default => throw new ParameterException(
                'UNSUPPORTED_ACCOUNT_FAMILY',
                "Unsupported account family {$traits['family']}.",
            ),
        };
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @return array<string,mixed>
     */
    private static function allocateTraditionalIra(array &$context, array $account): array
    {
        $diagnostics = [];
        $sharedLimits = [];
        $annual = $account['existingContributions'];
        $additional = self::zeroComponents();
        $ownerId = $account['ownerId'];
        $person = $context['persons'][$ownerId];
        $ownerPool =& $context['iraOwnerPools'][$ownerId];
        $compensationPoolId = $ownerPool['compensationPoolId'];
        $deductionPool =& $context['iraDeductionPools'][$ownerId];

        if ($ownerPool['blocked']) {
            $diagnostics[] = self::diagnostic(
                'IRA_POOL_BLOCKED_BY_PRIOR_INDETERMINATE_ACCOUNT',
                DiagnosticSeverity::ERROR,
                'A higher-priority IRA account has an indeterminate contribution limit, so remaining shared IRA capacity cannot be allocated reliably.',
                "accounts.{$account['id']}",
            );
            $result = [
                'status' => CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => $ownerPool['limit'],
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
            ];
            unset($ownerPool, $deductionPool);
            return $result;
        }

        if ($context['parameters']['ira']['traditionalContributionAge70HalfRestriction']) {
            $restricted = self::reachesAge70HalfByYearEnd($person, $context['taxYear']);
            if ($restricted === true) {
                unset($ownerPool, $deductionPool);
                return self::emptyOutcome($account, CalculationStatus::INELIGIBLE->value, 0.0, [
                    self::diagnostic(
                        'PRE_2020_TRADITIONAL_IRA_AGE_70_HALF_RESTRICTION',
                        DiagnosticSeverity::INFO,
                        'Traditional IRA contributions were not permitted after age 70½ for this tax year.',
                        "accounts.{$account['id']}",
                    ),
                ]);
            }
            if ($restricted === null) {
                $ownerPool['blocked'] = true;
                $diagnostics[] = self::diagnostic(
                    'BIRTH_DATE_REQUIRED_FOR_AGE_70_HALF_RULE',
                    DiagnosticSeverity::ERROR,
                    'An exact birth date is required to resolve the former age-70½ traditional IRA contribution restriction.',
                    "persons.{$person['id']}.birthDate",
                );
                $result = [
                    'status' => CalculationStatus::INDETERMINATE->value,
                    'statutoryMaximum' => $ownerPool['limit'],
                    'annualComponents' => $annual,
                    'additionalComponents' => $additional,
                    'planTermDependentCapacity' => 0.0,
                    'sharedLimits' => $sharedLimits,
                    'diagnostics' => $diagnostics,
                ];
                unset($ownerPool, $deductionPool);
                return $result;
            }
        }

        if (!$context['parameters']['ira']['universalEligibility']) {
            if (!array_key_exists('coveredByEmployerRetirementPlan', $person)) {
                $ownerPool['blocked'] = true;
                $diagnostics[] = self::diagnostic(
                    'EMPLOYER_PLAN_COVERAGE_REQUIRED_FOR_HISTORICAL_IRA_ELIGIBILITY',
                    DiagnosticSeverity::ERROR,
                    'Employer-plan coverage is required to resolve IRA eligibility before universal IRA eligibility began in 1982.',
                    "persons.{$person['id']}.coveredByEmployerRetirementPlan",
                );
                $result = [
                    'status' => CalculationStatus::INDETERMINATE->value,
                    'statutoryMaximum' => $ownerPool['limit'],
                    'annualComponents' => $annual,
                    'additionalComponents' => $additional,
                    'planTermDependentCapacity' => 0.0,
                    'sharedLimits' => $sharedLimits,
                    'diagnostics' => $diagnostics,
                ];
                unset($ownerPool, $deductionPool);
                return $result;
            }
            if ($person['coveredByEmployerRetirementPlan']) {
                unset($ownerPool, $deductionPool);
                return self::emptyOutcome($account, CalculationStatus::INELIGIBLE->value, 0.0, [
                    self::diagnostic(
                        'PRE_1982_ACTIVE_PARTICIPANT_IRA_INELIGIBLE',
                        DiagnosticSeverity::INFO,
                        'Before 1982, an active participant in an employer retirement plan generally could not make the modeled deductible IRA contribution.',
                        "accounts.{$account['id']}",
                    ),
                ]);
            }
        }

        if ($ownerPool['limit'] === null || $context['iraCompensationPools'][$compensationPoolId]['limit'] === null) {
            $ownerPool['blocked'] = true;
            $diagnostics[] = isset($context['section220TwiceTheLesserOwners'][(string) $person['id']])
                ? self::diagnostic(
                    'SPOUSAL_IRA_LIMIT_INDETERMINATE_UNDER_SECTION_220',
                    DiagnosticSeverity::ERROR,
                    "Former IRC 220(b)(1)(A) caps a one-earner couple's {$context['taxYear']} deduction at twice "
                        . 'the amount paid to whichever of the two individual retirement accounts received the '
                        . 'lesser amount, subject to the 15 percent and $1,750 ceilings in subparagraphs (B) and '
                        . '(C). That is a joint ceiling keyed to how the couple split their contributions rather '
                        . 'than a limit on this account: a worker who contributes nothing to their own account '
                        . 'makes the spousal amount deductible only to zero, and the maximizing split is equal '
                        . 'halves of $875. No per-account figure reproduces the rule, so no maximum is reported '
                        . 'for this account rather than an invented one.',
                    "persons.{$person['id']}",
                    'Former IRC 220(b)(1)(A); Tax Reform Act of 1976, Pub. L. 94-455 s.1501',
                )
                : self::diagnostic(
                    'BIRTH_YEAR_OR_DATE_REQUIRED_FOR_IRA_LIMIT',
                    DiagnosticSeverity::ERROR,
                    'Birth year or birth date is required to determine the IRA catch-up limit.',
                    "persons.{$person['id']}",
                );
            self::reportPoolWithoutConsuming($ownerPool, $sharedLimits);
            self::reportPoolWithoutConsuming($context['iraCompensationPools'][$compensationPoolId], $sharedLimits);
            $result = [
                'status' => CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => null,
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
            ];
            unset($ownerPool, $deductionPool);
            return $result;
        }

        $amount = self::takeAcrossPools(
            $context,
            [['iraOwnerPools', $ownerId], ['iraCompensationPools', $compensationPoolId]],
            self::minMoney(
                self::poolRemaining($ownerPool),
                self::poolRemaining($context['iraCompensationPools'][$compensationPoolId]),
            ),
            $sharedLimits,
        );
        if ($deductionPool['limit'] === null) {
            $additional['unclassifiedIra'] = $amount;
            $annual['unclassifiedIra'] = self::roundMoney($annual['unclassifiedIra'] + $amount);
            $diagnostics[] = self::diagnostic(
                'TRADITIONAL_IRA_DEDUCTIBILITY_INDETERMINATE',
                DiagnosticSeverity::ERROR,
                'The total traditional IRA contribution limit is known, but employer-plan coverage and/or traditional-IRA MAGI is required to classify it as deductible or nondeductible.',
                "accounts.{$account['id']}",
            );
            self::reportPoolWithoutConsuming($deductionPool, $sharedLimits);
        } else {
            $deductibleAdditional = self::minMoney($amount, self::poolRemaining($deductionPool));
            if ($deductibleAdditional > 0) {
                self::consumeExactFromPool($deductionPool, $deductibleAdditional, $sharedLimits);
            } else {
                self::reportPoolWithoutConsuming($deductionPool, $sharedLimits);
            }
            $additional['deductibleIra'] = $deductibleAdditional;
            $additional['nondeductibleIra'] = self::roundMoney($amount - $deductibleAdditional);
            $annual['deductibleIra'] = self::roundMoney($annual['deductibleIra'] + $deductibleAdditional);
            $annual['nondeductibleIra'] = self::roundMoney($annual['nondeductibleIra'] + $amount - $deductibleAdditional);
            if ($additional['nondeductibleIra'] > 0 && !$context['parameters']['ira']['nondeductibleContributionAvailable']) {
                $diagnostics[] = self::diagnostic(
                    'NONDEDUCTIBLE_IRA_NOT_AVAILABLE_FOR_YEAR',
                    DiagnosticSeverity::ERROR,
                    'A nondeductible traditional IRA contribution was not available in this historical tax year.',
                    "accounts.{$account['id']}",
                );
            }
        }
        $result = [
            'status' => self::accountStatusFromDiagnostics(CalculationStatus::DETERMINATE->value, $diagnostics),
            'statutoryMaximum' => $ownerPool['limit'],
            'annualComponents' => $annual,
            'additionalComponents' => $additional,
            'planTermDependentCapacity' => 0.0,
            'sharedLimits' => $sharedLimits,
            'diagnostics' => $diagnostics,
        ];
        unset($ownerPool, $deductionPool);
        return $result;
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @return array<string,mixed>
     */
    private static function allocateRothIra(array &$context, array $account): array
    {
        $diagnostics = [];
        $sharedLimits = [];
        $annual = $account['existingContributions'];
        $additional = self::zeroComponents();
        $ownerId = $account['ownerId'];
        $person = $context['persons'][$ownerId];
        $ownerPool =& $context['iraOwnerPools'][$ownerId];
        $compensationPoolId = $ownerPool['compensationPoolId'];
        $rothPool =& $context['iraRothEligibilityPools'][$ownerId];
        if ($ownerPool['blocked']) {
            $diagnostics[] = self::diagnostic(
                'IRA_POOL_BLOCKED_BY_PRIOR_INDETERMINATE_ACCOUNT',
                DiagnosticSeverity::ERROR,
                'A higher-priority IRA account has an indeterminate contribution limit.',
                "accounts.{$account['id']}",
            );
            $result = [
                'status' => CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => $rothPool['limit'],
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
            ];
            unset($ownerPool, $rothPool);
            return $result;
        }
        if (
            $ownerPool['limit'] === null
            || $context['iraCompensationPools'][$compensationPoolId]['limit'] === null
            || $rothPool['limit'] === null
        ) {
            $ownerPool['blocked'] = true;
            if (!array_key_exists('rothIra', $person['magi'])) {
                $diagnostics[] = self::diagnostic(
                    'ROTH_IRA_MAGI_REQUIRED',
                    DiagnosticSeverity::ERROR,
                    'Roth-IRA MAGI is required to determine the direct Roth IRA contribution limit.',
                    "persons.{$person['id']}.magi.rothIra",
                );
            }
            if ($ownerPool['limit'] === null) {
                $diagnostics[] = self::diagnostic(
                    'BIRTH_YEAR_OR_DATE_REQUIRED_FOR_IRA_LIMIT',
                    DiagnosticSeverity::ERROR,
                    'Birth year or birth date is required to determine the IRA catch-up limit.',
                    "persons.{$person['id']}",
                );
            }
            self::reportPoolWithoutConsuming($ownerPool, $sharedLimits);
            self::reportPoolWithoutConsuming($context['iraCompensationPools'][$compensationPoolId], $sharedLimits);
            self::reportPoolWithoutConsuming($rothPool, $sharedLimits);
            $result = [
                'status' => CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => $rothPool['limit'],
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
            ];
            unset($ownerPool, $rothPool);
            return $result;
        }
        $amount = self::takeAcrossPools(
            $context,
            [
                ['iraOwnerPools', $ownerId],
                ['iraCompensationPools', $compensationPoolId],
                ['iraRothEligibilityPools', $ownerId],
            ],
            self::minMoney(
                self::poolRemaining($ownerPool),
                self::poolRemaining($context['iraCompensationPools'][$compensationPoolId]),
                self::poolRemaining($rothPool),
            ),
            $sharedLimits,
        );
        $additional['rothIra'] = $amount;
        $annual['rothIra'] = self::roundMoney($annual['rothIra'] + $amount);
        $result = [
            'status' => CalculationStatus::DETERMINATE->value,
            'statutoryMaximum' => $rothPool['limit'],
            'annualComponents' => $annual,
            'additionalComponents' => $additional,
            'planTermDependentCapacity' => 0.0,
            'sharedLimits' => $sharedLimits,
            'diagnostics' => $diagnostics,
        ];
        unset($ownerPool, $rothPool);
        return $result;
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @param array<string,mixed> $traits
     */
    private static function accountPlanCatchUpLimit(array $context, array $account, array $traits): float
    {
        $person = $context['persons'][$account['ownerId']];
        $age = self::ageAtEndOfTaxYear($person, $context['taxYear']);
        if ($age === null || $age < 50 || empty($traits['permitsAgeCatchUpByStatute'])) {
            return 0.0;
        }
        if (!empty($traits['isStarter'])) {
            return (float) $context['parameters']['starterDeferralOnly']['age50CatchUp'];
        }
        if (!empty($traits['isSimple'])) {
            if ($age >= 60 && $age <= 63 && $context['parameters']['simple']['age60To63CatchUp'] !== null) {
                return (float) $context['parameters']['simple']['age60To63CatchUp'];
            }
            if (
                !empty($account['planRules']['simpleEnhancedLimitEligible'])
                && $context['parameters']['simple']['certainPlanAge50CatchUp'] !== null
            ) {
                return (float) $context['parameters']['simple']['certainPlanAge50CatchUp'];
            }
            return (float) $context['parameters']['simple']['generalAge50CatchUp'];
        }
        return self::workplaceCatchUpLimit($context['parameters'], $person, $traits);
    }

    /**
     * The IRC 402A(e)(3)(A) ceilings for a pension-linked emergency savings account,
     * or null for a year with no encoded limitation.
     *
     * The statute caps the portion of the *account balance* attributable to
     * participant contributions at the lesser of the published figure — clause (i) —
     * and any lower amount the plan sponsor sets — clause (ii), supplied as
     * `planDocumentEmployeeDeferralLimit`. Clause (ii) is a plan term rather than
     * encoded law, so it binds what may be contributed but not the statutory
     * maximum this package reports; the two rooms are kept apart for that reason.
     *
     * The supplied balance is the portion attributable to participant contributions
     * *immediately before the proposed allocation*: it includes amounts contributed
     * earlier in the same year that are still in the account and is net of
     * withdrawals under the plan's accounting. IRC 402A(e)(7) requires the plan to
     * permit withdrawal at least monthly, and the Department of Labor's PLESA
     * guidance states a plan may not impose a separate annual PLESA contribution
     * limit, so a year's gross contributions may exceed the cap once the balance has
     * been drawn down. A reading of the field as an opening or prior-year balance
     * could not express that without a withdrawal input the package does not have.
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $account
     * @return array{statutoryCap:float,effectiveCap:float,balance:float,plesaRoom:float,statutoryPlesaRoom:float}|null
     */
    private static function pensionLinkedEmergencySavingsCaps(array $context, array $account): ?array
    {
        $statutoryCap = $context['parameters']['pensionLinkedEmergencySavingsBalanceCap402A'];
        if ($statutoryCap === null) {
            return null;
        }
        $statutoryCap = (float) $statutoryCap;
        $sponsorCap = $account['planRules']['planDocumentEmployeeDeferralLimit'] ?? null;
        $effectiveCap = $sponsorCap === null
            ? $statutoryCap
            : self::minMoney($statutoryCap, self::money(
                $sponsorCap,
                "{$account['id']}.planDocumentEmployeeDeferralLimit",
            ));
        $balance = self::money(
            $account['planRules']['pensionLinkedEmergencySavingsParticipantContributionBalance'] ?? null,
            "{$account['id']}.pensionLinkedEmergencySavingsParticipantContributionBalance",
        );
        return [
            'statutoryCap' => $statutoryCap,
            'effectiveCap' => $effectiveCap,
            'balance' => $balance,
            'plesaRoom' => self::nonnegative($effectiveCap - $balance),
            'statutoryPlesaRoom' => self::nonnegative($statutoryCap - $balance),
        ];
    }

    /**
     * IRC 402A(e)(3)(A) as an account-local pool rather than as a deferral limit.
     *
     * Every participant-contribution component draws the same pool — a base
     * elective deferral and an IRC 414(v) catch-up alike — because the statute gates
     * the resulting *balance* and not the character of the contribution that would
     * produce it. Expressing the room as a base deferral limit instead would let a
     * catch-up allocated afterwards carry the balance past the cap.
     *
     * `used` is seeded from the supplied balance and never from
     * `existingContributions`. The balance is stated as of immediately before this
     * allocation and so already reflects contributions made earlier in the year that
     * are still in the account, while existing contributions separately seed the
     * host plan's annual pools; charging both would subtract the same dollars twice.
     *
     * @param array<string,mixed> $account
     * @param array{statutoryCap:float,effectiveCap:float,balance:float,plesaRoom:float,statutoryPlesaRoom:float} $caps
     * @return array<string,mixed>
     */
    private static function pensionLinkedEmergencySavingsPool(array $account, array $caps): array
    {
        return [
            'id' => "plesa402Ae3:{$account['id']}",
            'legalLimit' => 'IRC 402A(e)(3)(A) participant-contribution balance cap',
            'limit' => $caps['effectiveCap'],
            'used' => $caps['balance'],
        ];
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @param array<string,mixed> $traits
     */
    private static function baseDeferralLimitForAccount(array $context, array $account, array $traits): ?float
    {
        if (!empty($traits['isPlesa'])) {
            // The IRC 402A(e)(3)(A) room is enforced as an account-local pool that
            // both the base and the catch-up allocation draw, not as a deferral
            // limit, so what governs here is the host plan's own dollar deferral
            // limit. A year that encodes no cap has no such account at all, and
            // returning null there keeps the account indeterminate rather than
            // letting it defer under the host limit alone.
            //
            // Which host limit that is turns on the host. An IRC 457(b) deferral is
            // not among the elective deferrals IRC 402(g)(3) enumerates, so an
            // IRC 402A(f)(1)(C) account runs against the IRC 457(e)(15) applicable
            // dollar amount that IRC 457(b)(2)(A) imposes rather than IRC 402(g)(1).
            // The two figures happen to be equal for every year a pension-linked
            // emergency savings account has been available, so this branch is not
            // observable as a dollar difference — only as which pool the
            // contribution is drawn from.
            if ($context['parameters']['pensionLinkedEmergencySavingsBalanceCap402A'] === null) {
                return null;
            }
            $hostLimit = ($traits['family'] ?? null) === 'section457'
                ? $context['parameters']['section457b']['baseDeferralLimit']
                : $context['parameters']['electiveDeferral402g'];
            return $hostLimit === null ? null : (float) $hostLimit;
        }
        if (!empty($traits['isStarter'])) {
            return $context['parameters']['starterDeferralOnly']['baseDeferralLimit'] === null
                ? null
                : (float) $context['parameters']['starterDeferralOnly']['baseDeferralLimit'];
        }
        if (!empty($traits['isSimple'])) {
            if (
                !empty($account['planRules']['simpleEnhancedLimitEligible'])
                && $context['parameters']['simple']['certainPlanEnhancedSalaryReductionLimit'] !== null
            ) {
                return (float) $context['parameters']['simple']['certainPlanEnhancedSalaryReductionLimit'];
            }
            return $context['parameters']['simple']['salaryReductionLimit'] === null
                ? null
                : (float) $context['parameters']['simple']['salaryReductionLimit'];
        }
        return $context['parameters']['electiveDeferral402g'] === null
            ? null
            : (float) $context['parameters']['electiveDeferral402g'];
    }

    /** @param array<string,mixed> $parameters
     *  @param array<string,mixed> $account
     */
    private static function special403bCatchUpLimit(array $parameters, array $account): float
    {
        $input = $account['planRules']['special403bCatchUp'] ?? null;
        if (!is_array($input) || empty($input['eligible'])) {
            return 0.0;
        }
        $limits = $parameters['special403b15YearCatchUp'];
        $lifetimeRemaining = self::nonnegative(
            (float) $limits['lifetimeLimit']
            - self::money($input['priorSpecialCatchUpUsed'] ?? null, "{$account['id']}.priorSpecialCatchUpUsed"),
        );
        $serviceRemaining = self::nonnegative(
            (float) $limits['serviceLimitPerYear'] * (float) $input['yearsOfService']
            - self::money($input['priorElectiveDeferrals'] ?? null, "{$account['id']}.priorElectiveDeferrals"),
        );
        return self::floorMoney(self::minMoney((float) $limits['annualLimit'], $lifetimeRemaining, $serviceRemaining));
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @param array<string,mixed> $traits
     *  @param list<array<string,mixed>> $diagnostics
     *  @return 'pretax'|'roth'|'unavailable'|'unknown'
     */
    /**
     * How a catch-up on this account is taxed, and whether one may be allocated at
     * all, with the two IRC 414(v)(7)(A) questions asked in the order that keeps each
     * from swallowing the other.
     *
     * An amount already recorded on *this* account is settled first: it is not a
     * catch-up the engine is classifying but a completed contribution the supplied
     * wages condemn, so nothing later can change the answer.
     *
     * A *sibling* account's unreconciled amount is asked last, and only where it
     * could change something. It is a claim about capacity rather than about tax
     * character, so it cannot displace this account's own classification: an account
     * that still needs its own employer's wages is told so in the same pass. And it
     * is irrelevant to an account that could not take a catch-up anyway, where
     * reporting it would turn a knowable determinate answer into an unknown for a
     * reason that does not reach it.
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $account
     * @param array<string,mixed> $traits
     * @param list<array<string,mixed>> $diagnostics
     */
    private static function catchUpTaxTreatment(
        array $context,
        array $account,
        array $traits,
        array &$diagnostics,
        ?float $availableCatchUp = null,
    ): array {
        $blocked = ['treatment' => 'unknown', 'reportsHighWageRothAllocation' => false];
        if (self::appendHighWageExistingPreTaxCatchUpDiagnostic($context, $account, $traits, $diagnostics)) {
            return $blocked;
        }
        // Classified with its success diagnostic withheld, because both the capacity
        // gate and the sibling block below can still take the allocation away. An
        // account must not report both that its catch-up was allocated as Roth and
        // that no catch-up was allocated.
        //
        // This runs whatever room is left. Classification is not only a choice between
        // Roth and pre-tax for an amount about to be allocated: where the account
        // carries an existing pre-tax IRC 414(v) catch-up, it is also what adjudicates
        // that completed contribution, and the prior-year wages stay load-bearing for
        // it after the room for a new one is gone.
        $classification = self::classifyCatchUpTaxTreatment($context, $account, $traits, $diagnostics);
        // 'unavailable' means the plan offers no Roth catch-up above the threshold, so
        // this account has no capacity for the pool doubt to reach. Everything else --
        // including a treatment still unknown for want of this account's own wages --
        // keeps the block, so a caller is told about both in one pass rather than
        // finding the second after fixing the first.
        if ($classification['treatment'] === 'unavailable') {
            return $classification;
        }

        // What remains below is about allocating new catch-up, so neither is asked of
        // an account with no room to allocate into. $availableCatchUp is the amount
        // that would be taken if the classification allowed it, already bounded by the
        // plan limit, the existing components and remaining compensation; null means
        // the caller established there was some before calling, which is what the two
        // IRC 457 sites do.
        //
        // The nominal plan catch-up limit is not that test. An account whose base
        // deferral consumed its compensation still has a positive plan limit, and
        // reporting the sibling-pool doubt against it made the account indeterminate
        // without changing a number -- reconciling the sibling cannot create
        // compensation here. This is the principle the age diagnostic already follows,
        // and the one that makes both IRC 457 sites ask for a treatment only where
        // catch-up room survives.
        if ($availableCatchUp !== null && $availableCatchUp <= 0.0) {
            return $classification;
        }

        if (self::appendSiblingCatchUpPoolBlockDiagnostic($context, $account, $traits, $diagnostics)) {
            return $blocked;
        }
        return $classification;
    }

    /**
     * The IRC 414(v)(7)(A) success diagnostic, emitted only once nothing further can
     * withdraw the allocation. The sibling-pool block still can, which is why the
     * classification reports whether it would announce rather than announcing.
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $account
     * @param list<array<string,mixed>> $diagnostics
     */
    private static function appendHighWageRothCatchUpAllocatedDiagnostic(
        array $context,
        array $account,
        array &$diagnostics,
    ): void {
        $threshold = $context['parameters']['rothCatchUpPriorYearFicaWageThreshold'];
        $diagnostics[] = self::diagnostic(
            'HIGH_WAGE_CATCH_UP_ALLOCATED_AS_ROTH',
            DiagnosticSeverity::INFO,
            'Prior-year FICA wages exceeded $' . self::localeNumber((float) $threshold)
                . ', so the age-based catch-up is allocated as Roth.',
            "accounts.{$account['id']}",
            'IRC 414(v)(7)',
        );
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $account
     * @param array<string,mixed> $traits
     * @param list<array<string,mixed>> $diagnostics
     */
    private static function classifyCatchUpTaxTreatment(
        array $context,
        array $account,
        array $traits,
        array &$diagnostics,
    ): array {
        $person = $context['persons'][$account['ownerId']];
        $settled = static fn (string $treatment): array => [
            'treatment' => $treatment,
            'reportsHighWageRothAllocation' => false,
        ];
        $defaultTreatment = self::accountUsesRothEmployeeContributions($account, $traits) ? 'roth' : 'pretax';
        $threshold = $context['parameters']['rothCatchUpPriorYearFicaWageThreshold'];
        if (
            $threshold === null
            || $traits['family'] === 'simple'
            || !empty($traits['isSarsep'])
            // IRC 414(v)(7)(A) requires a high-wage participant's catch-up to be a
            // designated Roth contribution, and that is the only thing it does. It
            // can therefore have exactly two effects: force Roth treatment where the
            // default would have been pre-tax, and -- because it makes the catch-up
            // available "only if" the contribution is a designated Roth contribution
            // -- withdraw the catch-up from a plan that does not offer one. Where the
            // catch-up would be a designated Roth contribution anyway and the plan
            // offers it, neither effect is possible: the outcome is the same on both
            // sides of the threshold, so the prior-year wage figure the test would
            // need is not required and its absence is not a reason to decline the
            // answer.
            //
            // Both halves are load-bearing. A supplied contributionPreference of
            // pretax_first makes the default pre-tax on a designated Roth account
            // type, so the test still has Roth treatment to force. A supplied
            // permitsRothCatchUp of false leaves the two sides genuinely different --
            // the catch-up below the threshold, none above it -- so the wages are
            // genuinely needed and the account stays indeterminate without them.
            //
            // A pension-linked emergency savings account satisfies both halves by
            // statute rather than by supplied fact: IRC 402A(e)(1)(A)(i) treats it
            // "for purposes of this title as a designated Roth account", which is
            // what accountUsesRothEmployeeContributions and accountPermitsRothCatchUp
            // each read off it, so this clause reaches it without naming it.
            //
            // A third condition guards the same claim from the other direction. The
            // two effects above are both about the catch-up this engine would
            // allocate, so reasoning only about them holds only where the catch-up is
            // the engine's to classify. An existing pre-tax IRC 414(v) catch-up is
            // not: it is a completed contribution the caller reports, and
            // IRC 414(v)(7)(A) speaks to whether it was a valid one, which is a
            // question the threshold answers differently on each side. Below it the
            // component stands; above it the additional elective deferrals had to be
            // designated Roth contributions for IRC 414(v)(1) to apply at all. So the
            // wages remain load-bearing whenever one is supplied, and the account
            // keeps saying so rather than reporting a determinate result whose
            // pre-tax component carries an exclusion from gross income the statute
            // may not allow.
            //
            // The IRC 402(g)(7) and IRC 457(b)(3) special catch-ups are deliberately
            // not read here. Each is its own provision rather than an IRC 414(v)(1)
            // additional elective deferral, and IRC 414(v)(7)(A) reaches only the
            // latter.
            || ($defaultTreatment === 'roth'
                && self::accountPermitsRothCatchUp($account, $traits)
                && (float) ($account['existingContributions']['employeePreTaxCatchUp'] ?? 0.0) === 0.0)
            || self::accountPlanCatchUpLimit($context, $account, $traits) === 0.0
        ) {
            return $settled($defaultTreatment);
        }
        if (!empty($account['planRules']['isSelfEmployedOwner'])) {
            return $settled($defaultTreatment);
        }
        // Tested against absence rather than with empty(), which reads the accepted
        // identifier "0" as missing while the TypeScript engine does not. The two
        // engines returned different diagnostic codes for that input before this.
        if (($account['employerId'] ?? null) === null) {
            $diagnostics[] = self::diagnostic(
                'EMPLOYER_ID_REQUIRED_FOR_ROTH_CATCH_UP_WAGE_TEST',
                DiagnosticSeverity::ERROR,
                'An employerId is required to apply the prior-year FICA-wage test for catch-up contributions.',
                "accounts.{$account['id']}.employerId",
            );
            return $settled('unknown');
        }
        $employerId = (string) $account['employerId'];
        $wages = $person['priorYearFicaWagesByEmployer'][$employerId] ?? null;
        if ($wages === null) {
            $diagnostics[] = self::diagnostic(
                'PRIOR_YEAR_FICA_WAGES_REQUIRED_FOR_ROTH_CATCH_UP_CLASSIFICATION',
                DiagnosticSeverity::ERROR,
                "Prior-year FICA wages from employer {$employerId} are required to classify catch-up contributions.",
                "persons.{$person['id']}.priorYearFicaWagesByEmployer.{$employerId}",
            );
            return $settled('unknown');
        }
        if ((float) $wages <= (float) $threshold) {
            return $settled($defaultTreatment);
        }

        if (!self::accountPermitsRothCatchUp($account, $traits)) {
            $diagnostics[] = self::diagnostic(
                'HIGH_WAGE_CATCH_UP_REQUIRES_ROTH_BUT_PLAN_DOES_NOT_OFFER_IT',
                DiagnosticSeverity::WARNING,
                'Prior-year FICA wages exceeded $' . self::localeNumber((float) $threshold)
                    . '; no catch-up amount was allocated because the supplied plan rules do not permit Roth catch-up contributions.',
                "accounts.{$account['id']}.planRules.permitsRothCatchUp",
                'IRC 414(v)(7)',
            );
            return $settled('unavailable');
        }
        return ['treatment' => 'roth', 'reportsHighWageRothAllocation' => true];
    }

    /**
     * Which IRC 414(v) catch-up pool an account draws, for the purpose of deciding
     * how far an unreconciled contribution reaches.
     *
     * A qualified plan and an IRC 403(b) share one participant-wide IRC 414(v) pool
     * here; an eligible deferred compensation plan has its own, because
     * IRC 457(b)(3) and the IRC 457(e)(15) ceiling stand apart from IRC 402(g)(1).
     * An amount whose classification is unresolved therefore casts doubt over the
     * pool it was drawn from and no further: whether a pre-tax catch-up in a
     * IRC 401(k) was permitted says nothing about the capacity of a governmental
     * IRC 457(b).
     *
     * @param array<string,mixed> $traits
     */
    private static function catchUpPoolFamily(array $traits): string
    {
        return $traits['family'] === 'section457' ? 'section457' : 'qualified';
    }

    /**
     * The facts an existing pre-tax IRC 414(v) catch-up needs for IRC 414(v)(7)(A)
     * to condemn it, or null where the provision does not reach this account.
     *
     * Where the participant's prior-year wages from the employer sponsoring the plan
     * exceed the IRC 414(v)(7)(A) threshold, IRC 414(v)(1) applies "only if" the
     * additional elective deferrals are designated Roth contributions. A pre-tax
     * amount already recorded is therefore not an additional elective deferral the
     * paragraph permits -- and unlike the classification question the wage figure
     * usually answers, nothing here is missing. The supplied facts say directly that
     * the contribution was not one IRC 414(v) allows.
     *
     * The guards mirror catchUpTaxTreatment's, because the provision reaches the
     * same accounts either way: a year with no encoded threshold, a SIMPLE or SARSEP
     * plan, a self-employed owner with no IRC 3121 wages from a sponsoring employer,
     * and a plan offering no catch-up at all are all outside it.
     *
     * The employer identifier is tested against absence rather than with empty().
     * An identifier of "0" is one the input contract accepts, and empty() reads it
     * as missing while the TypeScript engine does not -- which would make the two
     * engines disagree on an accepted input.
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $account
     * @param array<string,mixed> $traits
     * @return array{existing: float, wages: float, threshold: float, employerId: string}|null
     */
    private static function highWageInvalidExistingPreTaxCatchUp(
        array $context,
        array $account,
        array $traits,
    ): ?array {
        $existing = (float) ($account['existingContributions']['employeePreTaxCatchUp'] ?? 0.0);
        if ($existing <= 0.0) {
            return null;
        }
        $threshold = $context['parameters']['rothCatchUpPriorYearFicaWageThreshold'];
        // IRC 414(v)(7)(C) disapplies subparagraph (A) for an applicable employer plan
        // described in IRC 414(v)(6)(A)(iv), which is "an arrangement meeting the
        // requirements of section 408(k) or (p)" -- a SEP or SARSEP, and a SIMPLE IRA.
        // It is not IRC 401(k)(11): a SIMPLE 401(k) is an employees' trust described in
        // IRC 401(a) and so falls under IRC 414(v)(6)(A)(i), which subparagraph (C)
        // does not reach. That is why the test is the account's family rather than its
        // isSimple trait, which simple401kTraits keeps while deliberately setting the
        // family to qualified_elective.
        if ($threshold === null || $traits['family'] === 'simple' || !empty($traits['isSarsep'])) {
            return null;
        }
        // IRC 414(v)(6)(C): "This subsection shall not apply to a participant for any
        // year for which a higher limitation applies to the participant under section
        // 457(b)(3)." It disapplies the whole of IRC 414(v), paragraph (7) included, so
        // where the participant-wide resolution selected the special method there is no
        // IRC 414(v)(7)(A) question to ask about this account's existing component --
        // the amount was not an IRC 414(v)(1) additional elective deferral in the first
        // place.
        //
        // The statute says "to a participant", not "to that plan", and read alone it
        // would strip the age-based catch-up from every plan of a participant who used
        // the IRC 457(b)(3) catch-up -- including an unrelated IRC 401(k). It does not.
        // 26 CFR 1.414(v)-1(a)(3) supplies the scope: "**In the case of an applicable
        // employer plan that is a section 457 eligible governmental plan**, the catch-up
        // contributions permitted under this section shall not apply to a catch-up
        // eligible participant for any taxable year for which a higher limitation
        // applies to such participant under section 457(b)(3)." 1.414(v)-1(e)(3)
        // confirms it from the other side: a plan does not fail universal availability
        // "merely because another applicable employer plan that is a section 457
        // eligible governmental plan does not provide for catch-up contributions to the
        // extent set forth in section 414(v)(6)(C)" -- which only has work to do if the
        // other plans keep theirs. Hence the family test here rather than a
        // participant-wide one. It is already reported under
        // SECTION_457_CATCH_UP_RECORDED_UNDER_UNSELECTED_METHOD, which is the right
        // diagnostic; adding the wage one asserted a statutory test that does not reach
        // the year. Returning null here also keeps the account out of the sibling-pool
        // block, because an amount IRC 414(v) never reached cannot have been charged
        // against an IRC 414(v)(2)(B) limit.
        if (
            $traits['family'] === 'section457'
            && (($context['section457CatchUpResolutions'][$account['ownerId']]['mode'] ?? null) === 'special')
        ) {
            return null;
        }
        if (!empty($account['planRules']['isSelfEmployedOwner'])) {
            return null;
        }
        if (self::accountPlanCatchUpLimit($context, $account, $traits) === 0.0) {
            return null;
        }
        $employerId = $account['employerId'] ?? null;
        if ($employerId === null) {
            return null;
        }
        $person = $context['persons'][$account['ownerId']];
        $wages = $person['priorYearFicaWagesByEmployer'][$employerId] ?? null;
        if ($wages === null || (float) $wages <= (float) $threshold) {
            return null;
        }
        return [
            'existing' => $existing,
            'wages' => (float) $wages,
            'threshold' => (float) $threshold,
            'employerId' => $employerId,
        ];
    }

    /**
     * Reports an existing pre-tax IRC 414(v) catch-up that the participant's own
     * prior-year wages say was not permitted, and says whether catch-up allocation
     * is blocked for this account.
     *
     * Retaining such an amount and reporting a determinate result put an exclusion
     * from gross income into federalTaxEffects that the statute does not allow,
     * which is a wrong number rather than an absent warning. The component is still
     * carried for audit, and no further catch-up is allocated until the caller
     * reconciles the classification: whether the amount counts against the
     * IRC 414(v)(2)(B) limit at all is exactly what is in doubt, so the room left
     * above it is not something to state. That is the treatment 26 CFR 1.457-5
     * already receives here for its own mutually exclusive methods.
     *
     * The amount is not reclassified as Roth. The caller stated a statutory
     * provenance through the component key, and inventing a different one would
     * answer a question about a completed contribution that only the caller and the
     * plan can answer.
     *
     * The doubt is pool-wide rather than account-wide, and that is the point of the
     * second branch. The IRC 414(v) limit is the participant's, not the plan's, and
     * an unreconciled amount has already been charged against it as though it were a
     * valid catch-up. A sibling account drawing the same pool would otherwise be
     * offered the residue -- a $3,000 amount in doubt leaving an apparent $5,000 for
     * the next account -- which states a remaining capacity that is only correct if
     * the contribution in doubt was valid. The block does not cross into the other
     * pool: see catchUpPoolFamily.
     *
     * Only employeePreTaxCatchUp is read. The IRC 402(g)(7) and IRC 457(b)(3)
     * special catch-ups are each their own provision rather than an IRC 414(v)(1)
     * additional elective deferral, so IRC 414(v)(7)(A) does not reach them.
     *
     * It is called from several places and pushes at most once per account.
     * catchUpTaxTreatment reaches it on every account whose catch-up it classifies,
     * which is where the blocking return is consumed; the IRC 457 and pension-linked
     * emergency savings allocators reach it directly, because each can return before
     * consulting catchUpTaxTreatment -- the former when no catch-up room survives,
     * the latter when the IRC 402A(e)(3)(A) balance is not supplied.
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $account
     * @param array<string,mixed> $traits
     * @param list<array<string,mixed>> $diagnostics
     */
    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $account
     * @param array<string,mixed> $traits
     * @param list<array<string,mixed>> $diagnostics
     */
    private static function appendHighWageExistingPreTaxCatchUpDiagnostic(
        array $context,
        array $account,
        array $traits,
        array &$diagnostics,
    ): bool {
        $own = self::highWageInvalidExistingPreTaxCatchUp($context, $account, $traits);
        if ($own === null) {
            return false;
        }
        $code = 'EXISTING_PRE_TAX_CATCH_UP_ABOVE_ROTH_CATCH_UP_WAGE_THRESHOLD';
        $alreadyReported = false;
        foreach ($diagnostics as $entry) {
            if (($entry['code'] ?? null) === $code) {
                $alreadyReported = true;
                break;
            }
        }
        if (!$alreadyReported) {
            $diagnostics[] = self::diagnostic(
                $code,
                DiagnosticSeverity::ERROR,
                'Existing contributions record $' . self::localeNumber($own['existing'])
                    . " of pre-tax age-based catch-up, but prior-year FICA wages from employer {$own['employerId']} of \$"
                    . self::localeNumber($own['wages']) . ' exceed the $' . self::localeNumber($own['threshold'])
                    . ' IRC 414(v)(7)(A) threshold. IRC 414(v)(1) applies "only if" the additional elective deferrals'
                    . ' are designated Roth contributions, so a pre-tax amount is not one this participant was'
                    . ' permitted to make. The amount is reported as supplied and is not reclassified; no further'
                    . ' catch-up is allocated until the classification is reconciled.',
                "accounts.{$account['id']}.existingContributions.employeePreTaxCatchUp",
                'IRC 414(v)(7)(A)',
            );
        }
        return true;
    }

    /**
     * Reports that another of the participant's plans holds an unreconciled pre-tax
     * catch-up which puts this account's remaining catch-up capacity in doubt.
     *
     * The IRC 414(v)(2)(B) limit is the participant's rather than the plan's, and an
     * unreconciled amount has already been charged against it as though it were a
     * valid catch-up. The residue a sibling account appears to have is therefore only
     * real if the contribution in doubt was valid. The doubt reaches the pool the
     * amount was drawn from and no further; see catchUpPoolFamily.
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $account
     * @param array<string,mixed> $traits
     * @param list<array<string,mixed>> $diagnostics
     */
    private static function appendSiblingCatchUpPoolBlockDiagnostic(
        array $context,
        array $account,
        array $traits,
        array &$diagnostics,
    ): bool {
        $family = self::catchUpPoolFamily($traits);
        $blockedBy = null;
        foreach ($context['accountsById'] as $other) {
            if ($other['id'] === $account['id'] || $other['ownerId'] !== $account['ownerId']) {
                continue;
            }
            $otherTraits = self::traits($other['type']);
            if (self::catchUpPoolFamily($otherTraits) !== $family) {
                continue;
            }
            if (self::highWageInvalidExistingPreTaxCatchUp($context, $other, $otherTraits) !== null) {
                $blockedBy = $other;
                break;
            }
        }
        if ($blockedBy === null) {
            return false;
        }
        $code = 'CATCH_UP_ALLOCATION_BLOCKED_BY_UNRECONCILED_EXISTING_PRE_TAX_CATCH_UP';
        $alreadyReported = false;
        foreach ($diagnostics as $entry) {
            if (($entry['code'] ?? null) === $code) {
                $alreadyReported = true;
                break;
            }
        }
        if (!$alreadyReported) {
            $diagnostics[] = self::diagnostic(
                $code,
                DiagnosticSeverity::ERROR,
                "No further catch-up is allocated because account {$blockedBy['id']} records a pre-tax age-based"
                    . ' catch-up that IRC 414(v)(7)(A) did not permit for this participant. The amount a participant'
                    . " may exclude as a catch-up is theirs for the taxable year rather than each plan's: IRC"
                    . ' 402(g)(1)(C) capped what an eligible participant\'s gross income could exclude "without regard'
                    . ' to the treatment of the elective deferrals by an applicable employer plan under section'
                    . ' 414(v)", and Notice 2023-62 preserves that result after SECURE 2.0 section 603(b)(1) struck'
                    . ' the subparagraph. So the block follows the participant and crosses employers, and the amount'
                    . ' in doubt has already been charged against that one limit -- the capacity apparently left for'
                    . ' this account is only correct if the contribution in doubt was valid. Reconcile the catch-up'
                    . " components on account {$blockedBy['id']} before relying on this account's remaining catch-up"
                    . ' capacity.',
                "accounts.{$account['id']}",
                'IRC 414(v)(7)(A); IRC 402(g)(1)(C) as preserved by Notice 2023-62',
            );
        }
        return true;
    }

    /**
     * Whether the plan, as its supplied rules state it, permits a catch-up
     * contribution to be a designated Roth contribution.
     *
     * IRC 414(v)(7)(A) makes a high-wage participant's catch-up available "only if"
     * it is a designated Roth contribution, so a plan that does not offer one takes
     * the catch-up away from that participant rather than making it pre-tax. The
     * fields are read most-specific first: a rule about the catch-up itself, then
     * the account's general Roth permission, then the account type's own character.
     *
     * IRC 402A(e)(1)(A)(i) leaves no such election open on a pension-linked
     * emergency savings account: it treats the account "for purposes of this title
     * as a designated Roth account", so a supplied permission flag states a plan
     * term the statute has already settled and is disregarded here for the same
     * reason accountUsesRothEmployeeContributions disregards a supplied preference.
     * pensionLinkedEmergencySavingsRothTreatmentDiagnostic reports the disregard.
     *
     * @param array<string,mixed> $account
     * @param array<string,mixed> $traits
     */
    private static function accountPermitsRothCatchUp(array $account, array $traits): bool
    {
        if (!empty($traits['isPlesa'])) {
            return true;
        }
        return (bool) ($account['planRules']['permitsRothCatchUp']
            ?? $account['planRules']['permitsRothContributions']
            ?? $traits['designatedRoth']);
    }

    /** @param array<string,mixed> $account
     *  @param array<string,mixed> $traits
     */
    private static function accountUsesRothEmployeeContributions(array $account, array $traits): bool
    {
        // IRC 402A(e)(1)(A)(i) treats a pension-linked emergency savings account "for
        // purposes of this title as a designated Roth account", so every participant
        // contribution to one is a designated Roth contribution. That is a
        // characteristic of the account, not an allocation choice, so it precedes the
        // caller's preference: a supplied preference or Roth-permission flag states a
        // plan election the statute does not leave open, and honouring it would
        // produce a pre-tax contribution to an account that cannot hold one. The
        // preference keeps its ordinary meaning on every other account type.
        if (!empty($traits['isPlesa'])) {
            return true;
        }
        $preference = $account['planRules']['contributionPreference'] ?? 'account_type';
        if ($preference === 'roth_first') {
            return (bool) ($account['planRules']['permitsRothContributions'] ?? $traits['designatedRoth']);
        }
        if ($preference === 'pretax_first') {
            return false;
        }
        return (bool) $traits['designatedRoth'];
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @param array<string,mixed> $traits
     *  @param array<string,float> $annual
     *  @param array<string,float> $additional
     *  @param list<array<string,mixed>> $diagnostics
     *  @param list<array<string,mixed>> $sharedLimits
     *  @return array{baseAdded:float,catchUpAdded:float,compensationRemaining:float}|null
     */
    private static function allocateBaseAndCatchUp(
        array &$context,
        array $account,
        array $traits,
        array &$annual,
        array &$additional,
        array &$diagnostics,
        array &$sharedLimits,
        bool $include415c,
    ): ?array {
        $ownerId = $account['ownerId'];
        $person = $context['persons'][$ownerId];
        $age = self::ageAtEndOfTaxYear($person, $context['taxYear']);
        $anyCatchUpAvailable =
            (float) $context['parameters']['generalAge50CatchUp'] > 0
            || (float) $context['parameters']['simple']['generalAge50CatchUp'] > 0
            || (float) $context['parameters']['starterDeferralOnly']['age50CatchUp'] > 0;
        // Only an account that can take an age-based catch-up needs an age.
        $catchUpNeedsAge = $age === null
            && $anyCatchUpAvailable
            && !empty($traits['permitsAgeCatchUpByStatute']);
        // A pension-linked emergency savings account is the one family where the age
        // is not always load-bearing: IRC 402A(e)(3)(A) caps the account whatever the
        // participant's age, so where the host's own base capacity already covers the
        // remaining room, no catch-up could change the answer and no birth date is
        // required. The question is therefore asked after the base allocation, once
        // the room it leaves is known.
        if ($catchUpNeedsAge && empty($traits['isPlesa'])) {
            $diagnostics[] = self::workplaceCatchUpAgeDiagnostic($person['id']);
        }
        $basePlanLimit = self::baseDeferralLimitForAccount($context, $account, $traits);
        $planComp = self::planCompensation($account, $person);
        $groupId = self::groupIdForAccount($account);
        $annualGroupExists = $include415c && isset($context['annualAdditionsPools'][$groupId]);
        if (
            $basePlanLimit === null
            || $context['elective402gPools'][$ownerId]['limit'] === null
            || ($include415c && (!$annualGroupExists || $context['annualAdditionsPools'][$groupId]['limit'] === null))
        ) {
            $diagnostics[] = self::diagnostic(
                'HISTORICAL_EMPLOYER_PLAN_LIMIT_INDETERMINATE',
                DiagnosticSeverity::ERROR,
                "A universal modern elective-deferral/annual-additions maximum is not encoded for {$context['taxYear']}; the historical plan document and applicable law are required.",
                "accounts.{$account['id']}",
            );
            self::reportPoolWithoutConsuming($context['elective402gPools'][$ownerId], $sharedLimits);
            if ($annualGroupExists) {
                self::reportPoolWithoutConsuming($context['annualAdditionsPools'][$groupId], $sharedLimits);
            }
            return null;
        }
        $existingBaseForAccount = self::baseDeferrals($account['existingContributions']);
        $employeePlanLimit = self::minMoney(
            $basePlanLimit,
            // IRC 402A(e)(3)(A)(ii) lets the plan sponsor set a lower amount than
            // clause (i), and it is supplied through this same field. But clause (ii)
            // caps the account *balance*, exactly as clause (i) does, and the
            // account-local pool below already enforces it against the balance the
            // caller supplied. Reading it a second time here — as an annual limit on
            // what may be deferred — charges this year's contributions against the
            // sponsor's amount twice, and strands room the statute leaves the
            // participant. A plan may not impose a separate annual limit on a
            // pension-linked emergency savings account in any event.
            (!empty($traits['isPlesa'])
                ? null
                : ($account['planRules']['planDocumentEmployeeDeferralLimit'] ?? null)) ?? $basePlanLimit,
            $planComp,
        );
        $accountAnnualRemainingBefore = !array_key_exists('planDocumentAnnualAdditionsLimit', $account['planRules'])
            ? $employeePlanLimit
            : self::nonnegative(
                self::money(
                    $account['planRules']['planDocumentAnnualAdditionsLimit'],
                    "{$account['id']}.planDocumentAnnualAdditionsLimit",
                ) - self::annualAdditions($account['existingContributions']),
            );
        $desiredBase = self::minMoney(
            self::nonnegative($employeePlanLimit - $existingBaseForAccount),
            $accountAnnualRemainingBefore,
        );
        // IRC 402A(e)(3)(A) gates the account balance rather than a deferral limit, so
        // the room is a pool this account's base and catch-up allocations both draw.
        // IRC 414(v)(3)(A)(i) puts catch-up contributions outside IRC 415(c), so only
        // the base draw carries the annual-additions group.
        $plesaCaps = !empty($traits['isPlesa'])
            ? self::pensionLinkedEmergencySavingsCaps($context, $account)
            : null;
        $hasPlesaPool = $plesaCaps !== null;
        if ($hasPlesaPool) {
            $context['plesaPools'][$account['id']] =
                self::pensionLinkedEmergencySavingsPool($account, $plesaCaps);
        }
        $refs = [['elective402gPools', $ownerId]];
        if ($annualGroupExists) {
            $refs[] = ['annualAdditionsPools', $groupId];
        }
        if ($hasPlesaPool) {
            $refs[] = ['plesaPools', $account['id']];
        }
        $baseAdded = self::takeAcrossPools($context, $refs, $desiredBase, $sharedLimits);
        if (self::accountUsesRothEmployeeContributions($account, $traits)) {
            $additional['employeeRothDeferral'] = $baseAdded;
            $annual['employeeRothDeferral'] = self::roundMoney($annual['employeeRothDeferral'] + $baseAdded);
        } else {
            $additional['employeePreTaxDeferral'] = $baseAdded;
            $annual['employeePreTaxDeferral'] = self::roundMoney($annual['employeePreTaxDeferral'] + $baseAdded);
        }
        $compensationRemaining = self::nonnegative(
            $planComp
            - self::baseDeferrals($annual)
            - self::ageCatchUps($annual)
            - $annual['special403bCatchUp'],
        );
        if (!empty($traits['is403b'])) {
            $specialLimit = self::special403bCatchUpLimit($context['parameters'], $account);
            $existingSpecial = $account['existingContributions']['special403bCatchUp'];
            $planDocumentRemaining = !array_key_exists('planDocumentAnnualAdditionsLimit', $account['planRules'])
                ? PHP_FLOAT_MAX
                : self::nonnegative(
                    self::money(
                        $account['planRules']['planDocumentAnnualAdditionsLimit'],
                        "{$account['id']}.planDocumentAnnualAdditionsLimit",
                    ) - self::annualAdditions($annual),
                );
            $desiredSpecial = self::minMoney(
                self::nonnegative($specialLimit - $existingSpecial),
                self::poolRemaining($context['special403bCatchUpPools'][$ownerId]),
                $compensationRemaining,
                $planDocumentRemaining,
            );
            if ($desiredSpecial > 0 && $annualGroupExists) {
                $specialAdded = self::takeAcrossPools(
                    $context,
                    [
                        ['annualAdditionsPools', $groupId],
                        ['special403bCatchUpPools', $ownerId],
                    ],
                    $desiredSpecial,
                    $sharedLimits,
                );
                $additional['special403bCatchUp'] = $specialAdded;
                $annual['special403bCatchUp'] = self::roundMoney($annual['special403bCatchUp'] + $specialAdded);
                $compensationRemaining = self::nonnegative($compensationRemaining - $specialAdded);
            }
        }
        // A birth date the base allocation made irrelevant is not demanded: it matters
        // only where the account still has room that a catch-up could fill, which
        // takes both unfilled IRC 402A(e)(3)(A) room and compensation left to defer.
        // The owner's IRC 414(v) pool is deliberately not consulted — it is itself
        // sized from the age being asked for, so it reads as empty exactly when the
        // question is open, and testing it would answer the question with its own
        // premise. `$anyCatchUpAvailable`, folded into `$catchUpNeedsAge`, is the part
        // of that test the year alone can settle.
        if (
            $catchUpNeedsAge
            && $hasPlesaPool
            && (self::poolRemaining($context['plesaPools'][$account['id']]) ?? 0.0) > 0
            && $compensationRemaining > 0
        ) {
            $diagnostics[] = self::workplaceCatchUpAgeDiagnostic($person['id']);
        }

        $planCatchUpLimit = self::accountPlanCatchUpLimit($context, $account, $traits);
        $existingCatchUp = self::ageCatchUps($account['existingContributions']);
        $desiredCatchUp = self::minMoney(
            self::nonnegative($planCatchUpLimit - $existingCatchUp),
            $compensationRemaining,
        );
        $catchUpAdded = 0.0;
        $catchUpRefs = [['catchUpPools', $ownerId]];
        if ($hasPlesaPool) {
            $catchUpRefs[] = ['plesaPools', $account['id']];
        }
        // $desiredCatchUp is passed so the classification is not asked -- and the
        // sibling-pool doubt not reported -- against room this account does not have.
        // The two IRC 457 sites reach their own treatment only where room survives,
        // which is the same gate stated a different way.
        $classification = self::catchUpTaxTreatment(
            $context,
            $account,
            $traits,
            $diagnostics,
            $desiredCatchUp,
        );
        $treatment = $classification['treatment'];
        if ($treatment === 'unknown') {
            self::reportPoolWithoutConsuming($context['catchUpPools'][$ownerId], $sharedLimits);
        } elseif ($treatment !== 'unavailable' && $desiredCatchUp > 0) {
            $catchUpAdded = self::takeAcrossPools(
                $context,
                $catchUpRefs,
                $desiredCatchUp,
                $sharedLimits,
            );
            // Announced only now. $desiredCatchUp is bounded by the plan limit, the
            // existing components and remaining compensation, but not by the owner's
            // shared IRC 414(v) pool, so it stays positive on an account that another
            // plan has already exhausted the pool for. Saying the catch-up "is allocated
            // as Roth" before takeAcrossPools returns is how an account came to report
            // that alongside an employeeRothCatchUp of zero.
            if ($catchUpAdded > 0 && $classification['reportsHighWageRothAllocation']) {
                self::appendHighWageRothCatchUpAllocatedDiagnostic($context, $account, $diagnostics);
            }
            if ($treatment === 'roth') {
                $additional['employeeRothCatchUp'] = $catchUpAdded;
                $annual['employeeRothCatchUp'] = self::roundMoney($annual['employeeRothCatchUp'] + $catchUpAdded);
            } else {
                $additional['employeePreTaxCatchUp'] = $catchUpAdded;
                $annual['employeePreTaxCatchUp'] = self::roundMoney($annual['employeePreTaxCatchUp'] + $catchUpAdded);
            }
            $compensationRemaining = self::nonnegative($compensationRemaining - $catchUpAdded);
        }
        return [
            'baseAdded' => $baseAdded,
            'catchUpAdded' => $catchUpAdded,
            'compensationRemaining' => $compensationRemaining,
        ];
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @return array{amount:float,known:bool,description:string}
     */
    private static function employerContributionMaximum(
        array $context,
        array $account,
        float $employeeBaseDeferral,
    ): array {
        $person = $context['persons'][$account['ownerId']];
        $recognizedCompensation = self::recognizedCompensationForEmployerAllocation(
            $context,
            $account,
            $person,
        );
        $rules = $account['planRules'];
        if (array_key_exists('expectedEmployerContribution', $rules)) {
            return [
                'amount' => self::money($rules['expectedEmployerContribution'], "{$account['id']}.expectedEmployerContribution"),
                'known' => true,
                'description' => 'caller-supplied employer contribution',
            ];
        }
        $amount = 0.0;
        $hasFormula = false;
        if (array_key_exists('employerNonelectiveRate', $rules)) {
            $amount += $recognizedCompensation * self::rate(
                $rules['employerNonelectiveRate'],
                "{$account['id']}.employerNonelectiveRate",
            );
            $hasFormula = true;
        }
        if (array_key_exists('employerMatchRate', $rules) && array_key_exists('employerMatchCompensationFraction', $rules)) {
            $matchableDeferral = self::minMoney(
                $employeeBaseDeferral,
                $recognizedCompensation * self::rate(
                    $rules['employerMatchCompensationFraction'],
                    "{$account['id']}.employerMatchCompensationFraction",
                ),
            );
            $amount += $matchableDeferral * self::rate($rules['employerMatchRate'], "{$account['id']}.employerMatchRate");
            $hasFormula = true;
        }
        if (!empty($rules['isSelfEmployedOwner']) && !$hasFormula) {
            $netEarnings = self::money(
                $rules['netEarningsFromSelfEmploymentAfterHalfSETax']
                    ?? $person['compensation']['selfEmploymentNetEarnings']
                    ?? null,
                "{$account['id']}.netEarningsFromSelfEmploymentAfterHalfSETax",
            );
            $amount = self::minMoney(
                $netEarnings * (float) $context['parameters']['sep']['selfEmployedEquivalentRate'],
                $recognizedCompensation * (float) $context['parameters']['sep']['maximumEmployerContributionRate'],
            );
            $hasFormula = true;
        }
        return [
            'amount' => self::floorMoney($amount),
            'known' => $hasFormula,
            'description' => $hasFormula ? 'supplied employer formula' : 'unknown plan/employer formula',
        ];
    }

    /** @param array<string,mixed> $account
     *  @param array<string,mixed> $traits
     *  @param array<string,float> $annual
     *  @param array<string,float> $additional
     */
    /** @param array<string,mixed> $account
     *  @param array<string,mixed> $traits
     */
    private static function employerContributionUsesRoth(array $account, array $traits): bool
    {
        return ($account['planRules']['employerContributionTaxTreatment'] ?? null) === 'roth'
            || ($traits['family'] === 'sep' && !empty($traits['designatedRoth']));
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @param array<string,mixed> $traits
     *  @param list<array<string,mixed>> $diagnostics
     */
    private static function validateEmployerRothAvailability(
        array $context,
        array $account,
        array $traits,
        array &$diagnostics,
    ): bool {
        if (!self::employerContributionUsesRoth($account, $traits) || $context['taxYear'] >= 2023) {
            return true;
        }
        $diagnostics[] = self::diagnostic(
            'ROTH_EMPLOYER_CONTRIBUTIONS_NOT_AVAILABLE_FOR_YEAR',
            DiagnosticSeverity::ERROR,
            'Employer matching and nonelective contributions designated as Roth are modeled as available beginning in 2023.',
            "accounts.{$account['id']}.planRules.employerContributionTaxTreatment",
        );
        return false;
    }

    private static function addEmployerContribution(
        array $account,
        array $traits,
        array &$annual,
        array &$additional,
        float $amount,
    ): void {
        $roth = self::employerContributionUsesRoth($account, $traits);
        if ($roth) {
            $additional['employerRoth'] = self::roundMoney($additional['employerRoth'] + $amount);
            $annual['employerRoth'] = self::roundMoney($annual['employerRoth'] + $amount);
        } else {
            $additional['employerPreTax'] = self::roundMoney($additional['employerPreTax'] + $amount);
            $annual['employerPreTax'] = self::roundMoney($annual['employerPreTax'] + $amount);
        }
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @return array{amount:float,known:bool,statutoryPotential:float,diagnostics:list<array<string,mixed>>}
     */
    private static function simpleEmployerContribution(
        array $context,
        array $account,
        float $annualEmployeeDeferrals,
        bool $applyCompensationLimitToMatch,
    ): array {
        $diagnostics = [];
        $person = $context['persons'][$account['ownerId']];
        $compensation = self::planCompensation($account, $person);
        $cappedCompensation = $context['parameters']['annualCompensation401a17'] === null
            ? $compensation
            : min($compensation, (float) $context['parameters']['annualCompensation401a17']);
        // SIMPLE IRA matching compensation is exempt from §401(a)(17); a SIMPLE 401(k)
        // is a qualified §401(k)(11) plan whose compensation remains subject to it.
        $matchCompensation = $applyCompensationLimitToMatch ? $cappedCompensation : $compensation;
        $matchMaximum = self::minMoney($annualEmployeeDeferrals, $matchCompensation * 0.03);
        $nonelectiveMaximum = $cappedCompensation * 0.02;
        $additionalCap = (float) ($context['parameters']['simple']['additionalNonelectiveContributionCap'] ?? 0.0);
        $additionalStatutoryMaximum = self::minMoney($additionalCap, $cappedCompensation * 0.10);
        $requestedAdditional = self::money(
            $account['planRules']['simpleAdditionalNonelectiveContribution'] ?? null,
            "{$account['id']}.simpleAdditionalNonelectiveContribution",
        );
        $additional = self::minMoney($requestedAdditional, $additionalStatutoryMaximum);
        if ($requestedAdditional > $additionalStatutoryMaximum) {
            $diagnostics[] = self::diagnostic(
                'SIMPLE_ADDITIONAL_NONELECTIVE_CONTRIBUTION_CAPPED',
                DiagnosticSeverity::WARNING,
                'The additional SIMPLE nonelective contribution was capped at $'
                    . self::localeNumber($additionalStatutoryMaximum)
                    . ', the lesser of the indexed dollar cap and 10% of recognized compensation.',
                "accounts.{$account['id']}.planRules.simpleAdditionalNonelectiveContribution",
            );
        }
        $method = $account['planRules']['simpleEmployerContributionMethod'] ?? null;
        $amount = 0.0;
        $known = true;
        switch ($method) {
            case 'match_3_percent':
                $amount = $matchMaximum;
                break;
            case 'nonelective_2_percent':
                $amount = $nonelectiveMaximum;
                break;
            case 'custom':
                $amount = self::money(
                    $account['planRules']['simpleCustomEmployerContribution'] ?? null,
                    "{$account['id']}.simpleCustomEmployerContribution",
                );
                break;
            default:
                $known = false;
                $diagnostics[] = self::diagnostic(
                    'SIMPLE_EMPLOYER_METHOD_IS_PLAN_TERM_DEPENDENT',
                    DiagnosticSeverity::WARNING,
                    'Select the SIMPLE 3% matching, 2% nonelective, or custom employer method to calculate the usable employer contribution.',
                    "accounts.{$account['id']}.planRules.simpleEmployerContributionMethod",
                );
        }
        return [
            'amount' => self::floorMoney($amount + $additional),
            'known' => $known,
            'statutoryPotential' => self::floorMoney(
                max($matchMaximum, $nonelectiveMaximum) + $additionalStatutoryMaximum,
            ),
            'diagnostics' => $diagnostics,
        ];
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @param array<string,mixed> $traits
     *  @return array<string,mixed>
     */
    private static function allocateQualifiedElective(array &$context, array $account, array $traits): array
    {
        $diagnostics = [];
        $sharedLimits = [];
        $annual = $account['existingContributions'];
        $additional = self::zeroComponents();
        $groupId = self::groupIdForAccount($account);
        if (!empty($traits['isSarsep']) && $context['taxYear'] >= 1997 && empty($account['planRules']['grandfatheredSarsep'])) {
            return self::emptyOutcome($account, CalculationStatus::INELIGIBLE->value, 0.0, [
                self::diagnostic(
                    'NEW_SARSEP_NOT_PERMITTED_AFTER_1996',
                    DiagnosticSeverity::ERROR,
                    'A SARSEP generally must have been established before 1997. Set grandfatheredSarsep for an eligible continuing plan.',
                    "accounts.{$account['id']}.planRules.grandfatheredSarsep",
                ),
            ]);
        }
        if (!isset($context['annualAdditionsPools'][$groupId]) || $context['annualAdditionsPools'][$groupId]['limit'] === null) {
            $diagnostics[] = self::diagnostic(
                'HISTORICAL_415C_LIMIT_INDETERMINATE',
                DiagnosticSeverity::ERROR,
                "The IRC 415(c) annual-additions limit is not encoded as a universal monetary maximum for {$context['taxYear']}.",
                "accounts.{$account['id']}",
            );
            if (isset($context['annualAdditionsPools'][$groupId])) {
                self::reportPoolWithoutConsuming($context['annualAdditionsPools'][$groupId], $sharedLimits);
            }
            return [
                'status' => CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => null,
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
            ];
        }
        // IRC 403(b)(2) capped the amount excludable from a tax-sheltered annuity
        // at the exclusion allowance, a third ceiling standing beside IRC 415(c)
        // and IRC 402(g) rather than behind them: IRS Publication 571 (2001)
        // chapter 5 computes the maximum amount contributable as the *least* of
        // the three. The allowance was 20 percent of includible compensation for
        // the most recent year of service, multiplied by years of service,
        // reduced by amounts previously excludable. That last term is a lifetime
        // aggregate across the participant's service with the employer, and
        // nothing in the scenario input supplies it, so the exclusion allowance
        // cannot be computed and the least of the three cannot be identified.
        // Returning the lesser of IRC 415(c) and IRC 402(g) would state a ceiling
        // that the omitted third term can only lower.
        //
        // EGTRRA (Pub. L. 107-16) section 632(a)(2)(B) struck IRC 403(b)(2) and
        // section 632(a)(3)(E) struck IRC 415(c)(4), whose elections could change
        // which limit bound; section 632(a)(4) applies both "to years beginning
        // after December 31, 2001". 2001 is therefore the last year the allowance
        // governs. The test reads as "<= 2001" because that is the statutory
        // boundary; years before 1987 never reach it, having already returned
        // above with no encoded IRC 415(c) limit at all.
        if (!empty($traits['is403b']) && $context['taxYear'] <= 2001) {
            $diagnostics[] = self::diagnostic(
                'PRE_2002_403B_EXCLUSION_ALLOWANCE_NOT_APPLIED',
                DiagnosticSeverity::ERROR,
                "For {$context['taxYear']}, IRC 403(b)(2) limited the amount excludable from gross income to the exclusion allowance \u{2014} 20 percent of includible compensation for the most recent year of service, multiplied by years of service, reduced by amounts previously excludable \u{2014} and the excludable maximum was the least of that allowance, the IRC 415(c) annual-additions limit, and the IRC 402(g) elective-deferral limit. Amounts previously excludable are a lifetime figure this package does not hold, and the IRC 415(c)(4) alternative elections could change which limit binds, so no universal maximum can be stated. EGTRRA (Pub. L. 107-16) section 632 repealed both for years beginning after December 31, 2001.",
                "accounts.{$account['id']}",
                'IRC 403(b)(2), 415(c)(4) (as in effect before Pub. L. 107-16 section 632)',
            );
            self::reportPoolWithoutConsuming($context['annualAdditionsPools'][$groupId], $sharedLimits);

            return [
                'status' => CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => null,
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
            ];
        }
        // IRC 402A(e)(3)(A) caps the portion of the *account balance* attributable to
        // participant contributions, not the contributions of any one year, and
        // IRC 402A(e)(7) lets the participant withdraw at least monthly, which puts
        // room back. What may still be contributed therefore depends on a balance no
        // other supplied fact expresses, and assuming an empty account would state a
        // ceiling the statute may not allow.
        if (!empty($traits['isPlesa'])) {
            $rothTreatmentDiagnostic = self::pensionLinkedEmergencySavingsRothTreatmentDiagnostic($account);
            if ($rothTreatmentDiagnostic !== null) {
                $diagnostics[] = $rothTreatmentDiagnostic;
            }
            // An explicitly supplied null states no more about the balance than omitting
            // the field does. Reading it as zero would answer a required question the
            // caller did not answer, and would answer it with the one value that yields
            // the largest ceiling the statute allows.
            if (($account['planRules']['pensionLinkedEmergencySavingsParticipantContributionBalance'] ?? null) === null) {
                // Reached before the return below for the same reason the IRC 457 host
                // reaches it: this path never consults catchUpTaxTreatment, so an
                // existing pre-tax catch-up that IRC 414(v)(7)(A) did not permit would
                // otherwise go unreported while its component and its pre-tax effect
                // stayed in the result.
                self::appendHighWageExistingPreTaxCatchUpDiagnostic($context, $account, $traits, $diagnostics);
                $diagnostics[] = self::diagnostic(
                    'PENSION_LINKED_EMERGENCY_SAVINGS_PRIOR_BALANCE_REQUIRED',
                    DiagnosticSeverity::ERROR,
                    'IRC 402A(e)(3)(A) caps the portion of a pension-linked emergency savings account balance attributable to participant contributions rather than the contributions of a single year, so the balance already attributable to them is required. Supply planRules.pensionLinkedEmergencySavingsParticipantContributionBalance, using 0 for a newly established account.',
                    "accounts.{$account['id']}.planRules.pensionLinkedEmergencySavingsParticipantContributionBalance",
                    'IRC 402A(e)(3)(A)',
                );
                self::reportPoolWithoutConsuming($context['annualAdditionsPools'][$groupId], $sharedLimits);

                return [
                    'status' => CalculationStatus::INDETERMINATE->value,
                    'statutoryMaximum' => null,
                    'annualComponents' => $annual,
                    'additionalComponents' => $additional,
                    'planTermDependentCapacity' => 0.0,
                    'sharedLimits' => $sharedLimits,
                    'diagnostics' => $diagnostics,
                ];
            }
            $caps = self::pensionLinkedEmergencySavingsCaps($context, $account);
            if ($caps !== null) {
                $diagnostics[] = self::diagnostic(
                    'PENSION_LINKED_EMERGENCY_SAVINGS_BALANCE_CAP_APPLIED',
                    DiagnosticSeverity::INFO,
                    '$' . self::localeNumber($caps['statutoryCap'])
                        . ' is the IRC 402A(e)(3)(A)(i) ceiling on the portion of the account balance attributable to participant contributions; $'
                        . self::localeNumber($caps['balance'])
                        . ' was supplied as attributable to them immediately before this allocation, leaving $'
                        . self::localeNumber($caps['plesaRoom'])
                        . '. That room is drawn by base deferrals and by any IRC 414(v) catch-up alike, because IRC 402A(e)(3)(A) gates the resulting balance rather than the character of the contribution.'
                        . ' Contributions are Roth by IRC 402A(e)(1)(A)(i); base deferrals count against IRC 402(g) and IRC 415(c), while a catch-up is outside IRC 415(c) under IRC 414(v)(3)(A)(i).'
                        . ' Eligibility under IRC 402A(e)(2), automatic enrollment under IRC 402A(e)(4), the withdrawal right under IRC 402A(e)(7),'
                        . " and the IRC 402A(e)(6)(A) rule directing matching contributions to the participant's other account are not modeled.",
                    "accounts.{$account['id']}",
                    'IRC 402A(e)(3)(A)(i)',
                );
            }
        }
        $deferral = self::allocateBaseAndCatchUp(
            $context,
            $account,
            $traits,
            $annual,
            $additional,
            $diagnostics,
            $sharedLimits,
            true,
        );
        if ($deferral === null) {
            return [
                'status' => CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => null,
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
            ];
        }
        $annualGroupLimit = (float) $context['annualAdditionsPools'][$groupId]['limit'];
        $accountAnnualLimit = self::minMoney(
            $annualGroupLimit,
            $account['planRules']['planDocumentAnnualAdditionsLimit'] ?? $annualGroupLimit,
        );
        $employeeBase = self::baseDeferrals($annual);
        // Starter 401(k) and deferral-only safe-harbor 403(b) plans take no employer
        // contribution by plan type; a pension-linked emergency savings account takes
        // none because IRC 402A(e)(6)(A) directs any match earned on its contributions
        // to the participant's other account under the plan, and IRC 402A(e)(8)(B)
        // forbids transfers into it from another account.
        $deferralOnly = !empty($traits['isStarter']) || !empty($traits['isPlesa']);
        $employerKnown = $deferralOnly;
        $employerDesired = 0.0;
        $statutoryEmployerPotential = 0.0;
        if ($deferralOnly) {
            // No employer contribution is allocated to this account.
        } elseif (!empty($traits['isSimple'])) {
            $simpleEmployer = self::simpleEmployerContribution(
                $context,
                $account,
                $employeeBase + self::ageCatchUps($annual),
                true,
            );
            array_push($diagnostics, ...$simpleEmployer['diagnostics']);
            $employerKnown = $simpleEmployer['known'];
            $employerDesired = self::nonnegative(
                $simpleEmployer['amount']
                - $account['existingContributions']['employerPreTax']
                - $account['existingContributions']['employerRoth'],
            );
            $statutoryEmployerPotential = $simpleEmployer['statutoryPotential'];
        } else {
            $employer = self::employerContributionMaximum($context, $account, $employeeBase);
            $employerKnown = $employer['known'];
            $employerDesired = self::nonnegative(
                $employer['amount']
                - $account['existingContributions']['employerPreTax']
                - $account['existingContributions']['employerRoth'],
            );
        }
        $accountRemainingBeforeEmployer = self::nonnegative($accountAnnualLimit - self::annualAdditions($annual));
        $employerAdded = 0.0;
        $employerTaxTreatmentAvailable = $employerDesired === 0.0
            || self::validateEmployerRothAvailability($context, $account, $traits, $diagnostics);
        if ($employerKnown && $employerTaxTreatmentAvailable) {
            $employerAdded = self::takeAcrossPools(
                $context,
                [['annualAdditionsPools', $groupId]],
                self::minMoney($employerDesired, $accountRemainingBeforeEmployer),
                $sharedLimits,
            );
        }
        if ($employerKnown && ($employerDesired === 0.0 || !$employerTaxTreatmentAvailable)) {
            self::reportPoolWithoutConsuming($context['annualAdditionsPools'][$groupId], $sharedLimits);
        }
        if ($employerAdded > 0.0) {
            self::addEmployerContribution($account, $traits, $annual, $additional, $employerAdded);
        }
        if (!empty($account['planRules']['permitsAfterTaxEmployeeContributions']) && !$deferralOnly) {
            $afterTaxCapacity = self::minMoney(
                self::poolRemaining($context['annualAdditionsPools'][$groupId]),
                self::nonnegative($accountAnnualLimit - self::annualAdditions($annual)),
                $deferral['compensationRemaining'],
            );
            if ($afterTaxCapacity > 0.0) {
                $afterTaxAdded = self::takeAcrossPools(
                    $context,
                    [['annualAdditionsPools', $groupId]],
                    $afterTaxCapacity,
                    $sharedLimits,
                );
                $additional['employeeAfterTax'] = $afterTaxAdded;
                $annual['employeeAfterTax'] = self::roundMoney($annual['employeeAfterTax'] + $afterTaxAdded);
            }
        }
        $planTermDependentCapacity = 0.0;
        if (
            !$deferralOnly
            && !$employerKnown
            && empty($account['planRules']['permitsAfterTaxEmployeeContributions'])
        ) {
            $planTermDependentCapacity = self::minMoney(
                self::poolRemaining($context['annualAdditionsPools'][$groupId]),
                self::nonnegative($accountAnnualLimit - self::annualAdditions($annual)),
            );
            if ($planTermDependentCapacity > 0.0) {
                $diagnostics[] = self::diagnostic(
                    'PLAN_TERM_DEPENDENT_415C_CAPACITY',
                    DiagnosticSeverity::WARNING,
                    '$' . self::localeNumber($planTermDependentCapacity)
                        . ' of potential annual-additions capacity requires an employer contribution formula or permission for voluntary after-tax contributions.',
                    "accounts.{$account['id']}.planRules",
                );
            }
        }
        $planCatchUp = self::accountPlanCatchUpLimit($context, $account, $traits);
        // The reported statutory maximum folds in encoded law and supplied facts but
        // not the plan's own restrictions, so it is built from the statutory host
        // base limit rather than from `$employeePlanLimit`.
        $statutoryHostBaseLimit = self::baseDeferralLimitForAccount($context, $account, $traits) ?? 0.0;
        $statutoryHostAnnualCapacity = self::roundMoney($statutoryHostBaseLimit + $planCatchUp);
        if (!empty($traits['isPlesa'])) {
            // A pension-linked emergency savings account is bounded twice over: by
            // what the host plan may take in a year, and by IRC 402A(e)(3)(A), which
            // stops contributions once the participant-contribution *balance* reaches
            // the cap. Reporting the host figure alone would state a ceiling this
            // account can never reach; reporting the room alone would understate it
            // where the balance already holds contributions made this year, since
            // those are themselves part of the annual total. The maximum is therefore
            // the lesser of the host's annual capacity and what the participant has
            // already put in plus the room that remains. Clause (ii) — the plan
            // sponsor's lower amount — is a plan term, so it lowers what may be
            // contributed without lowering the statutory figure reported here.
            $caps = self::pensionLinkedEmergencySavingsCaps($context, $account);
            $existingParticipantContributions = self::roundMoney(
                self::baseDeferrals($account['existingContributions'])
                + self::ageCatchUps($account['existingContributions']),
            );
            $statutoryMaximum = $caps === null
                ? $statutoryHostAnnualCapacity
                : self::minMoney(
                    $statutoryHostAnnualCapacity,
                    self::roundMoney($existingParticipantContributions + $caps['statutoryPlesaRoom']),
                );
        } elseif ($deferralOnly) {
            $statutoryMaximum = $statutoryHostAnnualCapacity;
        } else {
            $statutoryMaximum = self::roundMoney(
                $accountAnnualLimit
                + $planCatchUp
                + (!empty($traits['isSimple']) ? max(0.0, $statutoryEmployerPotential - $accountAnnualLimit) : 0.0),
            );
        }
        return [
            'status' => self::accountStatusFromDiagnostics(CalculationStatus::DETERMINATE->value, $diagnostics),
            'statutoryMaximum' => $statutoryMaximum,
            'annualComponents' => $annual,
            'additionalComponents' => $additional,
            'planTermDependentCapacity' => $planTermDependentCapacity,
            'sharedLimits' => $sharedLimits,
            'diagnostics' => $diagnostics,
        ];
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @param array<string,mixed> $traits
     *  @return array<string,mixed>
     */
    private static function allocateSimple(array &$context, array $account, array $traits): array
    {
        $diagnostics = [];
        $sharedLimits = [];
        $annual = $account['existingContributions'];
        $additional = self::zeroComponents();
        $deferral = self::allocateBaseAndCatchUp(
            $context,
            $account,
            $traits,
            $annual,
            $additional,
            $diagnostics,
            $sharedLimits,
            false,
        );
        if ($deferral === null) {
            return [
                'status' => CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => null,
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
            ];
        }
        $simpleEmployer = self::simpleEmployerContribution(
            $context,
            $account,
            self::baseDeferrals($annual) + self::ageCatchUps($annual),
            false,
        );
        array_push($diagnostics, ...$simpleEmployer['diagnostics']);
        if ($simpleEmployer['known']) {
            $employerAdded = self::nonnegative(
                $simpleEmployer['amount']
                - $account['existingContributions']['employerPreTax']
                - $account['existingContributions']['employerRoth'],
            );
            if (
                $employerAdded > 0.0
                && self::validateEmployerRothAvailability($context, $account, $traits, $diagnostics)
            ) {
                self::addEmployerContribution($account, $traits, $annual, $additional, $employerAdded);
            }
        }
        $planTermDependentCapacity = $simpleEmployer['known'] ? 0.0 : $simpleEmployer['statutoryPotential'];
        $baseLimit = self::baseDeferralLimitForAccount($context, $account, $traits) ?? 0.0;
        $catchUpLimit = self::accountPlanCatchUpLimit($context, $account, $traits);
        return [
            'status' => self::accountStatusFromDiagnostics(CalculationStatus::DETERMINATE->value, $diagnostics),
            'statutoryMaximum' => self::roundMoney($baseLimit + $catchUpLimit + $simpleEmployer['statutoryPotential']),
            'annualComponents' => $annual,
            'additionalComponents' => $additional,
            'planTermDependentCapacity' => $planTermDependentCapacity,
            'sharedLimits' => $sharedLimits,
            'diagnostics' => $diagnostics,
        ];
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @param array<string,mixed> $traits
     *  @return array<string,mixed>
     */
    private static function allocateSep(array &$context, array $account, array $traits): array
    {
        $diagnostics = [];
        $sharedLimits = [];
        $annual = $account['existingContributions'];
        $additional = self::zeroComponents();
        $groupId = self::groupIdForAccount($account);
        if (!isset($context['annualAdditionsPools'][$groupId]) || $context['annualAdditionsPools'][$groupId]['limit'] === null) {
            $diagnostics[] = self::diagnostic(
                'HISTORICAL_SEP_MAXIMUM_REQUIRES_PLAN_FACTS',
                DiagnosticSeverity::ERROR,
                "The SEP maximum cannot be reduced to a universal monetary amount for {$context['taxYear']} from the encoded facts.",
                "accounts.{$account['id']}",
            );
            if (isset($context['annualAdditionsPools'][$groupId])) {
                self::reportPoolWithoutConsuming($context['annualAdditionsPools'][$groupId], $sharedLimits);
            }
            return [
                'status' => CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => null,
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
            ];
        }
        $person = $context['persons'][$account['ownerId']];
        $compensation = self::planCompensation($account, $person);
        if (
            $context['parameters']['sep']['minimumEligibleCompensation'] !== null
            && $compensation < (float) $context['parameters']['sep']['minimumEligibleCompensation']
        ) {
            $diagnostics[] = self::diagnostic(
                'SEP_COMPENSATION_BELOW_MAXIMUM_EXCLUDABLE_THRESHOLD',
                DiagnosticSeverity::WARNING,
                'Compensation is below the statutory amount a SEP document may use to exclude an employee; actual eligibility depends on the plan document.',
                "accounts.{$account['id']}.planRules.planCompensation",
            );
        }
        $recognizedCompensation = self::recognizedCompensationForEmployerAllocation(
            $context,
            $account,
            $person,
        );
        $rateBasedMaximum = !empty($account['planRules']['isSelfEmployedOwner'])
            ? self::minMoney(
                $compensation * (float) $context['parameters']['sep']['selfEmployedEquivalentRate'],
                $recognizedCompensation * (float) $context['parameters']['sep']['maximumEmployerContributionRate'],
            )
            : $recognizedCompensation * (float) $context['parameters']['sep']['maximumEmployerContributionRate'];
        $groupLimit = (float) $context['annualAdditionsPools'][$groupId]['limit'];
        $planDocumentLimit = (float) ($account['planRules']['planDocumentAnnualAdditionsLimit'] ?? $groupLimit);
        $formulaMaximum = self::floorMoney(self::minMoney(
            $groupLimit,
            $planDocumentLimit,
            $rateBasedMaximum,
        ));
        $existingEmployer = self::roundMoney(
            $account['existingContributions']['employerPreTax'] + $account['existingContributions']['employerRoth'],
        );
        $desired = self::nonnegative($formulaMaximum - $existingEmployer);
        $employerAdded = $desired > 0.0
            && self::validateEmployerRothAvailability($context, $account, $traits, $diagnostics)
            ? self::takeAcrossPools(
                $context,
                [['annualAdditionsPools', $groupId]],
                $desired,
                $sharedLimits,
            )
            : 0.0;
        if ($employerAdded > 0.0) {
            self::addEmployerContribution($account, $traits, $annual, $additional, $employerAdded);
        }
        return [
            'status' => CalculationStatus::DETERMINATE->value,
            'statutoryMaximum' => $formulaMaximum,
            'annualComponents' => $annual,
            'additionalComponents' => $additional,
            'planTermDependentCapacity' => 0.0,
            'sharedLimits' => $sharedLimits,
            'diagnostics' => $diagnostics,
        ];
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @param array<string,mixed> $traits
     *  @return array<string,mixed>
     */
    private static function allocateAnnualAdditionsOnly(array &$context, array $account, array $traits): array
    {
        $diagnostics = [];
        $sharedLimits = [];
        $annual = $account['existingContributions'];
        $additional = self::zeroComponents();
        $groupId = self::groupIdForAccount($account);
        if (!isset($context['annualAdditionsPools'][$groupId]) || $context['annualAdditionsPools'][$groupId]['limit'] === null) {
            $diagnostics[] = self::diagnostic(
                'HISTORICAL_415C_LIMIT_INDETERMINATE',
                DiagnosticSeverity::ERROR,
                "The employer-plan contribution maximum for {$context['taxYear']} requires historical plan and compensation facts not represented by a universal encoded limit.",
                "accounts.{$account['id']}",
            );
            if (isset($context['annualAdditionsPools'][$groupId])) {
                self::reportPoolWithoutConsuming($context['annualAdditionsPools'][$groupId], $sharedLimits);
            }
            return [
                'status' => CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => null,
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
            ];
        }
        $groupLimit = (float) $context['annualAdditionsPools'][$groupId]['limit'];
        $accountAnnualLimit = self::minMoney(
            $groupLimit,
            $account['planRules']['planDocumentAnnualAdditionsLimit'] ?? $groupLimit,
        );
        $employer = self::employerContributionMaximum($context, $account, 0.0);
        if ($employer['known']) {
            $existingEmployer = self::roundMoney($annual['employerPreTax'] + $annual['employerRoth']);
            $desired = self::minMoney(
                self::nonnegative($employer['amount'] - $existingEmployer),
                self::nonnegative($accountAnnualLimit - self::annualAdditions($annual)),
            );
            $added = $desired > 0.0
                && self::validateEmployerRothAvailability($context, $account, $traits, $diagnostics)
                ? self::takeAcrossPools(
                    $context,
                    [['annualAdditionsPools', $groupId]],
                    $desired,
                    $sharedLimits,
                )
                : 0.0;
            if ($added > 0.0) {
                self::addEmployerContribution($account, $traits, $annual, $additional, $added);
            }
        }
        if (!empty($account['planRules']['permitsAfterTaxEmployeeContributions'])) {
            $desiredAfterTax = self::minMoney(
                self::poolRemaining($context['annualAdditionsPools'][$groupId]),
                self::nonnegative($accountAnnualLimit - self::annualAdditions($annual)),
            );
            if ($desiredAfterTax > 0.0) {
                $added = self::takeAcrossPools(
                    $context,
                    [['annualAdditionsPools', $groupId]],
                    $desiredAfterTax,
                    $sharedLimits,
                );
                $additional['employeeAfterTax'] = $added;
                $annual['employeeAfterTax'] = self::roundMoney($annual['employeeAfterTax'] + $added);
            }
        }
        $planTermDependentCapacity = 0.0;
        if (!$employer['known'] && empty($account['planRules']['permitsAfterTaxEmployeeContributions'])) {
            $planTermDependentCapacity = self::minMoney(
                self::poolRemaining($context['annualAdditionsPools'][$groupId]),
                self::nonnegative($accountAnnualLimit - self::annualAdditions($annual)),
            );
            $diagnostics[] = self::diagnostic(
                'EMPLOYER_CONTRIBUTION_REQUIRES_PLAN_FORMULA',
                DiagnosticSeverity::WARNING,
                "The Code-level annual-additions ceiling is known, but the usable contribution requires the plan's employer contribution formula or voluntary after-tax contribution terms.",
                "accounts.{$account['id']}.planRules",
            );
        }
        return [
            'status' => self::accountStatusFromDiagnostics(CalculationStatus::DETERMINATE->value, $diagnostics),
            'statutoryMaximum' => $accountAnnualLimit,
            'annualComponents' => $annual,
            'additionalComponents' => $additional,
            'planTermDependentCapacity' => $planTermDependentCapacity,
            'sharedLimits' => $sharedLimits,
            'diagnostics' => $diagnostics,
        ];
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @param array<string,mixed> $traits
     *  @return array<string,mixed>
     */
    private static function allocateSection457(array &$context, array $account, array $traits): array
    {
        $diagnostics = [];
        $sharedLimits = [];
        $annual = $account['existingContributions'];
        $additional = self::zeroComponents();
        $ownerId = $account['ownerId'];
        $person = $context['persons'][$ownerId];
        $statutoryBase = $context['parameters']['section457b']['baseDeferralLimit'];
        $compensationFraction = $context['parameters']['section457b']['includibleCompensationFraction'];
        if (
            $statutoryBase === null
            || $compensationFraction === null
            || $context['section457BasePools'][$ownerId]['limit'] === null
        ) {
            $diagnostics[] = self::diagnostic(
                'SECTION_457_LIMIT_INDETERMINATE',
                DiagnosticSeverity::ERROR,
                "The 457(b) monetary deferral limit is not available for tax year {$context['taxYear']}.",
                "accounts.{$account['id']}",
            );
            self::reportPoolWithoutConsuming($context['section457BasePools'][$ownerId], $sharedLimits);
            return [
                'status' => CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => null,
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
            ];
        }
        $resolution = $context['section457CatchUpResolutions'][$ownerId];
        $ceilings = self::section457PlanCeilings(
            $context['parameters'],
            $person,
            $account,
            (float) $statutoryBase,
            (float) $compensationFraction,
        );
        $accountExistingAgeCatchUp = self::ageCatchUps($account['existingContributions']);
        $accountExistingSpecialCatchUp = self::roundMoney(
            $account['existingContributions']['special457CatchUp']
                + $account['existingContributions']['special457RothCatchUp'],
        );
        $catchUpPoolCategory = $resolution['mode'] === 'special'
            ? 'section457SpecialCatchUpPools'
            : 'section457CatchUpPools';
        // IRC 402A(e)(3)(A) caps the portion of the *account balance* attributable to
        // participant contributions, not the contributions of any one year, and
        // IRC 402A(e)(7) lets the participant withdraw at least monthly, which puts
        // room back. What may still be contributed therefore depends on a balance no
        // other supplied fact expresses, and assuming an empty account would state a
        // ceiling the statute may not allow.
        if (!empty($traits['isPlesa'])) {
            $rothTreatmentDiagnostic = self::pensionLinkedEmergencySavingsRothTreatmentDiagnostic($account);
            if ($rothTreatmentDiagnostic !== null) {
                $diagnostics[] = $rothTreatmentDiagnostic;
            }
        }
        // An explicitly supplied null states no more about the balance than omitting
        // the field does. Reading it as zero would answer a required question the
        // caller did not answer, and would answer it with the one value that yields
        // the largest ceiling the statute allows.
        if (
            !empty($traits['isPlesa'])
            && ($account['planRules']['pensionLinkedEmergencySavingsParticipantContributionBalance'] ?? null) === null
        ) {
            $diagnostics[] = self::diagnostic(
                'PENSION_LINKED_EMERGENCY_SAVINGS_PRIOR_BALANCE_REQUIRED',
                DiagnosticSeverity::ERROR,
                'IRC 402A(e)(3)(A) caps the portion of a pension-linked emergency savings account balance '
                    . 'attributable to participant contributions rather than the contributions of a single '
                    . 'year, so the balance already attributable to them is required. Supply '
                    . 'planRules.pensionLinkedEmergencySavingsParticipantContributionBalance, using 0 for a '
                    . 'newly established account.',
                "accounts.{$account['id']}.planRules.pensionLinkedEmergencySavingsParticipantContributionBalance",
                'IRC 402A(e)(3)(A)',
            );
            self::appendHighWageExistingPreTaxCatchUpDiagnostic($context, $account, $traits, $diagnostics);
            $existingCatchUpClassificationInvalid = self::appendSection457ExistingCatchUpDiagnostics(
                $context,
                $account,
                $traits,
                $resolution,
                $ceilings,
                $diagnostics,
            );
            if ($resolution['mode'] === 'indeterminate' && $resolution['existingCatchUpClassificationUnreconciled']) {
                $diagnostics[] = self::workplaceCatchUpAgeDiagnostic($person['id']);
            } elseif (
                $resolution['existingCatchUpClassificationUnreconciled']
                && !$existingCatchUpClassificationInvalid
                && self::section457PlesaCatchUpCapacityUpperBoundBeforeBalance(
                    $context,
                    $account,
                    $resolution,
                    $ceilings,
                    $context['section457BasePools'][$ownerId],
                    $context[$catchUpPoolCategory][$ownerId],
                ) > 0.0
            ) {
                $diagnostics[] = self::section457UnreconciledCatchUpDiagnostic($account['id']);
            }
            self::reportPoolWithoutConsuming($context['section457BasePools'][$ownerId], $sharedLimits);
            return [
                'status' => CalculationStatus::INDETERMINATE->value,
                'statutoryMaximum' => null,
                'annualComponents' => $annual,
                'additionalComponents' => $additional,
                'planTermDependentCapacity' => 0.0,
                'sharedLimits' => $sharedLimits,
                'diagnostics' => $diagnostics,
            ];
        }
        // IRC 457(b)(2) sets the ceiling as the lesser of the applicable dollar amount
        // and 100 percent of includible compensation. Both are encoded law applied to
        // supplied facts, so both belong in the statutory figure this package reports.
        // A plan-document deferral limit is neither: it is a restriction the plan
        // chose, so it lowers what may actually be deferred without lowering the
        // reported statutory maximum. The qualified-plan path has always drawn that
        // line — README documents it — and drawing it here too is what lets a
        // pension-linked emergency savings account report the same kind of figure on
        // either host. section457PlanCeilings builds it, and the two catch-up amounts
        // above it, from the same facts the resolver used, so an account's own ceiling
        // and the participant's method are never derived from different figures.
        $includibleCompensation = $ceilings['includibleCompensation'];
        $statutoryHostBaseLimit = $ceilings['basicPlanCeiling'];
        // On a pension-linked emergency savings account the same input field carries
        // the sponsor's IRC 402A(e)(3)(A)(ii) amount, which caps the account *balance*
        // and is enforced as the account-local pool below. Reading it a second time as
        // an annual deferral limit would charge this year's contributions against it
        // once through the pool's balance and again through the limit, which is what
        // the qualified-plan host does not do either.
        $appliedHostBaseLimit = !empty($traits['isPlesa'])
            ? $statutoryHostBaseLimit
            : self::minMoney(
                $statutoryHostBaseLimit,
                $account['planRules']['planDocumentEmployeeDeferralLimit'] ?? $statutoryHostBaseLimit,
            );
        // IRC 402A(e)(3)(A) gates the account balance rather than a deferral limit, so
        // the room is an account-local pool that this account's base deferral and its
        // catch-up both draw. On this host that is the whole of the account's
        // interaction with IRC 415(c): there is none, because IRC 415(a) does not
        // reach an IRC 457(b) plan.
        $plesaCaps = !empty($traits['isPlesa'])
            ? self::pensionLinkedEmergencySavingsCaps($context, $account)
            : null;
        $hasPlesaPool = $plesaCaps !== null;
        if ($hasPlesaPool) {
            $context['plesaPools'][$account['id']] =
                self::pensionLinkedEmergencySavingsPool($account, $plesaCaps);
        }
        $plesaRefs = $hasPlesaPool ? [['plesaPools', $account['id']]] : [];
        $existingParticipantContributions = self::roundMoney(
            self::baseDeferrals($account['existingContributions'])
            + self::ageCatchUps($account['existingContributions'])
            + $account['existingContributions']['special457CatchUp']
            + $account['existingContributions']['special457RothCatchUp'],
        );
        if ($plesaCaps !== null) {
            $diagnostics[] = self::diagnostic(
                'PENSION_LINKED_EMERGENCY_SAVINGS_BALANCE_CAP_APPLIED',
                DiagnosticSeverity::INFO,
                '$' . self::localeNumber($plesaCaps['statutoryCap'])
                    . ' is the IRC 402A(e)(3)(A)(i) ceiling on the portion of the account balance attributable to participant contributions; $'
                    . self::localeNumber($plesaCaps['balance'])
                    . ' was supplied as attributable to them immediately before this allocation, leaving $'
                    . self::localeNumber($plesaCaps['plesaRoom'])
                    . '. That room is drawn by base deferrals and by a catch-up alike, because IRC 402A(e)(3)(A) gates the resulting balance rather than the character of the contribution.'
                    . ' Contributions are Roth by IRC 402A(e)(1)(A)(i) and count against IRC 457(e)(15) rather than IRC 402(g)(1), because IRC 402(g)(3) does not enumerate an IRC 457(b) deferral; IRC 415(c) does not reach an IRC 457(b) plan at all.'
                    . ' Eligibility under IRC 402A(e)(2), automatic enrollment under IRC 402A(e)(4), the withdrawal right under IRC 402A(e)(7),'
                    . " and the IRC 402A(e)(6)(A) rule directing matching contributions to the participant's other account are not modeled.",
                "accounts.{$account['id']}",
                'IRC 402A(e)(3)(A)(i)',
            );
        }
        $existingRegular = self::roundMoney(
            self::baseDeferrals($annual)
            + $annual['employeeAfterTax']
            + $annual['employerPreTax']
            + $annual['employerRoth'],
        );
        $expectedEmployer = self::money(
            $account['planRules']['expectedEmployerContribution'] ?? null,
            "{$account['id']}.expectedEmployerContribution",
        );
        $existingEmployer = self::roundMoney($annual['employerPreTax'] + $annual['employerRoth']);
        $employerDesired = self::minMoney(
            self::nonnegative($expectedEmployer - $existingEmployer),
            self::nonnegative($appliedHostBaseLimit - $existingRegular),
        );
        // IRC 402A(e)(6)(A) directs any match earned on emergency-savings
        // contributions to the participant's *other* account under the plan, and
        // IRC 402A(e)(8)(B) bars transfers in, so no employer contribution is ever
        // allocated here.
        if (
            empty($traits['isPlesa'])
            && $employerDesired > 0.0
            && self::validateEmployerRothAvailability($context, $account, $traits, $diagnostics)
        ) {
            $employerAdded = self::takeAcrossPools(
                $context,
                [['section457BasePools', $ownerId]],
                $employerDesired,
                $sharedLimits,
            );
            self::addEmployerContribution($account, $traits, $annual, $additional, $employerAdded);
        }
        $regularBeforeEmployee = self::roundMoney(
            self::baseDeferrals($annual)
            + $annual['employeeAfterTax']
            + $annual['employerPreTax']
            + $annual['employerRoth'],
        );
        $regularDesired = self::nonnegative($appliedHostBaseLimit - $regularBeforeEmployee);
        $regularAdded = self::takeAcrossPools(
            $context,
            array_merge([['section457BasePools', $ownerId]], $plesaRefs),
            $regularDesired,
            $sharedLimits,
        );
        if (self::accountUsesRothEmployeeContributions($account, $traits)) {
            $additional['employeeRothDeferral'] = $regularAdded;
            $annual['employeeRothDeferral'] = self::roundMoney($annual['employeeRothDeferral'] + $regularAdded);
        } else {
            $additional['employeePreTaxDeferral'] = $regularAdded;
            $annual['employeePreTaxDeferral'] = self::roundMoney($annual['employeePreTaxDeferral'] + $regularAdded);
        }
        $compensationRemaining = self::nonnegative(
            $includibleCompensation
            - self::baseDeferrals($annual)
            - self::ageCatchUps($annual)
            - $annual['special457CatchUp']
            - $annual['special457RothCatchUp']
            - $annual['employerPreTax']
            - $annual['employerRoth'],
        );
        // IRC 457(e)(18) and 26 CFR 1.457-4(c)(2)(ii) give the participant the greater
        // of the two catch-up methods for the year, never their sum, and 1.457-5(a)
        // applies that choice across every eligible plan at once. It is therefore
        // resolved for the participant before any account allocates — see
        // resolveSection457CatchUpModes — so that account priority decides only where
        // interchangeable capacity lands. Deciding it here, from whatever pool capacity
        // survived to this account, let two accounts pick different methods and use
        // both in one year.
        // 26 CFR 1.457-5(c): the special catch-up counts only to the extent the
        // deferral is actually made under a plan providing it, and the age-based method
        // reaches only a governmental plan. An account outside the selected method's
        // set draws nothing, whatever its priority.
        $mayDrawCatchUp = isset($resolution['eligibleAccountIds'][$account['id']]);

        // Every existing catch-up contribution carries a statutory provenance the
        // caller chose through the component key, so the invariants 26 CFR 1.457-4 and
        // 1.457-5 place on that provenance are checked before any further catch-up is
        // allocated. None of them reduces to a dollar total: each is satisfiable by
        // figures that sit under every ceiling in play, so the generic excess test sees
        // nothing. Where one fails the components are kept for audit, the account is
        // reported indeterminate and no further catch-up is allocated — reclassifying a
        // supplied component would answer a question only the caller can answer.
        // Reached directly rather than through catchUpTaxTreatment: allocateSection457
        // consults that only where catch-up room survives, and an existing amount
        // filling the IRC 414(v) pool leaves none. It is pushed before the count below
        // so it is not misread as an IRC 457 classification error.
        self::appendHighWageExistingPreTaxCatchUpDiagnostic($context, $account, $traits, $diagnostics);
        $classificationDiagnosticCount = count($diagnostics);
        $accountExistingAgeCatchUp = self::ageCatchUps($account['existingContributions']);
        $accountExistingSpecialCatchUp = self::roundMoney(
            $account['existingContributions']['special457CatchUp']
                + $account['existingContributions']['special457RothCatchUp'],
        );
        $selectedExistingCatchUp = match ($resolution['mode']) {
            'age' => $resolution['existingAgeCatchUp'],
            'special' => $resolution['existingSpecialCatchUp'],
            default => 0.0,
        };
        $unselectedExistingCatchUp = self::roundMoney(
            $resolution['existingAgeCatchUp'] + $resolution['existingSpecialCatchUp']
                - $selectedExistingCatchUp,
        );
        $accountSelectedExistingCatchUp = match ($resolution['mode']) {
            'age' => $accountExistingAgeCatchUp,
            'special' => $accountExistingSpecialCatchUp,
            default => 0.0,
        };
        $accountUnselectedExistingCatchUp = self::roundMoney(
            $accountExistingAgeCatchUp + $accountExistingSpecialCatchUp
                - $accountSelectedExistingCatchUp,
        );
        $selectedMethodName = $resolution['mode'] === 'age'
            ? 'IRC 414(v) age-based'
            : 'IRC 457(b)(3) special';

        // 26 CFR 1.457-5(a) allows the basic annual limitation plus *either* catch-up,
        // and 1.457-5(b) applies that on an aggregate basis across every eligible plan
        // the participant is in. Contributions already recorded under both methods are
        // therefore invalid as a pair, at any size: the breach is the pairing, not an
        // amount, so it cannot be left to the generic excess test, which sees nothing
        // whenever the two together still fit under the reported ceiling.
        if (
            $resolution['existingAgeCatchUp'] > 0.0
            && $resolution['existingSpecialCatchUp'] > 0.0
            && ($accountExistingAgeCatchUp > 0.0 || $accountExistingSpecialCatchUp > 0.0)
        ) {
            $diagnostics[] = self::section457MutuallyExclusiveCatchUpDiagnostic(
                $account['id'],
                $resolution['existingAgeCatchUp'],
                $resolution['existingSpecialCatchUp'],
            );
        } elseif (
            $resolution['mode'] !== 'indeterminate'
            && $unselectedExistingCatchUp > 0.0
            && $accountUnselectedExistingCatchUp > 0.0
        ) {
            // Only one method is present and it is not the one that applies. 26 CFR
            // 1.457-4(c)(2)(ii) makes that a determination rather than an election: the
            // age 50 catch-up "does not apply for any taxable year for which a higher
            // limitation applies" under the special catch-up, and IRC 414(v)(6)(C)
            // states the same rule from the other side. A contribution recorded under
            // the other method is therefore not a smaller version of a lawful one, and
            // it stays below every ceiling in play whenever the selected method's own
            // capacity is at least as large — which is why no monetary test finds it.
            $diagnostics[] = self::diagnostic(
                'SECTION_457_CATCH_UP_RECORDED_UNDER_UNSELECTED_METHOD',
                DiagnosticSeverity::ERROR,
                $resolution['mode'] === 'none'
                    ? 'Existing contributions record $'
                        . self::localeNumber($unselectedExistingCatchUp)
                        . ' of IRC 457 catch-up, but no catch-up method applies to this participant'
                        . " for {$context['taxYear']}: no plan supplied provides the IRC 457(b)(3)"
                        . ' catch-up, and no eligible governmental plan offers an IRC 414(v) amount'
                        . " the participant's age and compensation reach. Record the contribution"
                        . ' under the limitation it was actually made under, or supply the facts'
                        . ' that make a method apply.'
                    : 'Existing contributions record $'
                        . self::localeNumber($unselectedExistingCatchUp)
                        . ' of catch-up under the method that does not apply. 26 CFR'
                        . " 1.457-4(c)(2)(ii) selects the {$selectedMethodName} catch-up for this"
                        . ' participant, and makes that a determination rather than an election: the'
                        . ' age 50 catch-up "does not apply for any taxable year for which a higher'
                        . ' limitation applies" under the special 457 catch-up, and IRC 414(v)(6)(C)'
                        . ' states the same rule from the other side. Record the contribution under'
                        . ' the method actually used, or supply the plan facts that make the other'
                        . ' one apply.',
                "accounts.{$account['id']}.existingContributions",
                '26 CFR 1.457-4(c)(2)(ii); IRC 414(v)(6)(C)',
            );
        }
        if (
            $resolution['mode'] !== 'indeterminate'
            && $selectedExistingCatchUp > $resolution['headroom']
            && $accountSelectedExistingCatchUp > 0.0
        ) {
            // 26 CFR 1.457-5(b) determines the participant's deferrals "on an aggregate
            // basis" across every eligible plan, so two accounts each holding a share
            // within its own plan ceiling can still exceed the one amount the
            // participant is entitled to. No account's own maximum is breached, and
            // where neither allocates anything further the pool never reaches
            // sharedLimits either.
            $diagnostics[] = self::diagnostic(
                'SECTION_457_EXISTING_CATCH_UP_EXCEEDS_PARTICIPANT_LIMIT',
                DiagnosticSeverity::ERROR,
                'Existing contributions record $'
                    . self::localeNumber($selectedExistingCatchUp)
                    . " of {$selectedMethodName} catch-up across this participant's IRC 457 plans,"
                    . ' against the $'
                    . self::localeNumber($resolution['headroom'])
                    . ' that 26 CFR 1.457-5(a) allows above the basic annual limitation. 1.457-5(b)'
                    . ' determines the amounts "on an aggregate basis" across the eligible plans of'
                    . ' every employer, so the excess is the participant\'s even where no single'
                    . ' plan exceeds its own ceiling.',
                "accounts.{$account['id']}.existingContributions",
                '26 CFR 1.457-5(a); 26 CFR 1.457-5(b)',
            );
        }
        if (
            $accountExistingAgeCatchUp > 0.0
            && !(!empty($traits['governmental457']) && !empty($traits['permitsAgeCatchUpByStatute']))
        ) {
            // IRC 414(v)(6)(A)(ii) makes only an *eligible governmental* IRC 457(b) plan
            // an applicable employer plan, so a tax-exempt entity's plan hosts no
            // IRC 414(v) catch-up at all, whatever the participant's age.
            $diagnostics[] = self::diagnostic(
                'SECTION_457_AGE_CATCH_UP_NOT_AVAILABLE_ON_PLAN',
                DiagnosticSeverity::ERROR,
                'Existing contributions record $'
                    . self::localeNumber($accountExistingAgeCatchUp)
                    . ' of IRC 414(v) age-based catch-up on this account, but IRC 414(v)(6)(A)(ii)'
                    . ' makes only an eligible governmental IRC 457(b) plan an applicable employer'
                    . ' plan, so this plan cannot host one. Record the contribution under the'
                    . ' limitation it was actually made under.',
                "accounts.{$account['id']}.existingContributions",
                'IRC 414(v)(6)(A)(ii)',
            );
        }
        $accountProvidesSpecialCatchUp = is_array($account['planRules']['section457SpecialCatchUp'] ?? null)
            && !empty($account['planRules']['section457SpecialCatchUp']['eligible']);
        if ($accountExistingSpecialCatchUp > 0.0 && !$accountProvidesSpecialCatchUp) {
            // 26 CFR 1.457-5(c) counts the special catch-up "only to the extent that an
            // annual deferral is made for a participant under an eligible plan as a
            // result of plan provisions permitted under Sec. 1.457-4(c)(3)".
            $diagnostics[] = self::diagnostic(
                'SECTION_457_SPECIAL_CATCH_UP_NOT_PROVIDED_BY_PLAN',
                DiagnosticSeverity::ERROR,
                'Existing contributions record $'
                    . self::localeNumber($accountExistingSpecialCatchUp)
                    . ' of IRC 457(b)(3) special catch-up on this account, but no such plan'
                    . ' provision was supplied for it. 26 CFR 1.457-5(c) counts the special'
                    . ' catch-up only to the extent an annual deferral is made "as a result of plan'
                    . ' provisions permitted under Sec. 1.457-4(c)(3)". Supply'
                    . ' planRules.section457SpecialCatchUp for this plan, or record the contribution'
                    . ' under the limitation it was actually made under.',
                "accounts.{$account['id']}.existingContributions",
                '26 CFR 1.457-5(c)',
            );
        } elseif ($accountExistingSpecialCatchUp > $ceilings['specialAdditional']) {
            $diagnostics[] = self::diagnostic(
                'SECTION_457_SPECIAL_CATCH_UP_EXCEEDS_PLAN_AMOUNT',
                DiagnosticSeverity::ERROR,
                'Existing contributions record $'
                    . self::localeNumber($accountExistingSpecialCatchUp)
                    . ' of IRC 457(b)(3) special catch-up on this account, above the $'
                    . self::localeNumber($ceilings['specialAdditional'])
                    . ' its own plan ceiling provides above the basic annual limitation. 26 CFR'
                    . ' 1.457-4(c)(3)(i) caps that ceiling at the lesser of twice the IRC 457(e)(15)'
                    . ' amount and the (c)(3)(ii) underutilized limitation, and 1.457-5(c)'
                    . ' recognises the amount only under the plan whose provisions produce it.',
                "accounts.{$account['id']}.existingContributions",
                '26 CFR 1.457-4(c)(3)(i); 26 CFR 1.457-5(c)',
            );
        }
        $existingCatchUpClassificationInvalid = count($diagnostics) > $classificationDiagnosticCount;
        // The pool's own `used` already carries the participant's aggregate existing
        // catch-up under this method, so it is not subtracted a second time here. The
        // account's own IRC 457(b)(3) allowance is a separate bound: 26 CFR 1.457-5(c)
        // recognises the special catch-up "only to the extent that an annual deferral
        // is made for a participant under an eligible plan as a result of plan
        // provisions permitted under Sec. 1.457-4(c)(3)", so the largest amount the
        // participant is entitled to is not absorbable by a plan whose own provisions
        // are smaller, and what that plan already holds under the provision has spent
        // its share of it. 1.457-5(d) Example 2 is explicit on the first half: the
        // $8,000 figure comes from Plan Y and has to be deferred under Plan Y, even
        // though Plan W also offers the catch-up, at $7,000. Without the second half a
        // Plan W already holding its whole $7,000 could still take the participant's
        // last $1,000 and finish the year $1,000 above its own plan ceiling.
        $accountSpecialRemaining = $resolution['mode'] === 'special'
            ? self::nonnegative($ceilings['specialAdditional'] - $accountExistingSpecialCatchUp)
            : INF;
        // The account's own room, before the shared IRC 414(v) pool is consulted. It
        // is what decides whether the classification is worth asking for, because the
        // pool residue is the very thing an unreconciled sibling puts in doubt: an
        // invalid existing catch-up on another of the participant's IRC 457 plans
        // fills the pool, which would read here as "no capacity" and skip the
        // classification -- and with it the sibling block -- leaving this account
        // reported as a determinate zero when reconciling the sibling could restore
        // the whole amount. The qualified-plan site is bounded by $desiredCatchUp,
        // which is already pool-independent for the same reason.
        $ownCatchUpRoomWithoutPool = $mayDrawCatchUp
            ? self::minMoney(
                $compensationRemaining,
                $accountSpecialRemaining,
                $hasPlesaPool ? self::poolRemaining($context['plesaPools'][$account['id']]) : INF,
            )
            : 0.0;
        $monetaryCatchUpCapacityWithoutClassificationBlock = $mayDrawCatchUp
            ? self::minMoney(
                self::poolRemaining($context[$catchUpPoolCategory][$ownerId]),
                $ownCatchUpRoomWithoutPool,
            )
            : 0.0;
        $ageCatchUpTreatmentBeforeClassificationBlock = (
            $resolution['mode'] === 'age'
            && !$existingCatchUpClassificationInvalid
            && $ownCatchUpRoomWithoutPool > 0.0
        )
            ? self::catchUpTaxTreatment($context, $account, $traits, $diagnostics)['treatment']
            : null;
        $catchUpCapacityWithoutClassificationBlock = in_array(
            $ageCatchUpTreatmentBeforeClassificationBlock,
            ['unknown', 'unavailable'],
            true,
        )
            ? 0.0
            : $monetaryCatchUpCapacityWithoutClassificationBlock;
        if ($ageCatchUpTreatmentBeforeClassificationBlock === 'unknown') {
            self::reportPoolWithoutConsuming($context[$catchUpPoolCategory][$ownerId], $sharedLimits);
        }
        if (
            $resolution['mode'] !== 'indeterminate'
            && $resolution['existingCatchUpClassificationUnreconciled']
            && !$existingCatchUpClassificationInvalid
            && $catchUpCapacityWithoutClassificationBlock > 0.0
        ) {
            $diagnostics[] = self::section457UnreconciledCatchUpDiagnostic($account['id']);
        }
        $catchUpPotential = (
            $mayDrawCatchUp
            && !$existingCatchUpClassificationInvalid
            && !$resolution['existingCatchUpClassificationUnreconciled']
        )
            ? $catchUpCapacityWithoutClassificationBlock
            : 0.0;


        // A missing age changes the reported answer only where a catch-up could
        // actually land. Testing that after the base deferral has been allocated —
        // rather than from the account's opening room — is what separates a question
        // the supplied facts leave open from one they already settle: on an isolated
        // pension-linked emergency savings account the base deferral fills the whole
        // IRC 402A(e)(3)(A) room, so no catch-up of any size fits and the participant's
        // age cannot move a single figure. The age pool is deliberately not consulted:
        // it is zero precisely when the age is unknown, so reading it would answer the
        // question with its own premise.
        $roomACatchUpCouldOccupy = self::minMoney(
            $compensationRemaining,
            $hasPlesaPool ? self::poolRemaining($context['plesaPools'][$account['id']]) : INF,
        );
        // Kept before allocation spends it, so the report below can say how much of the
        // account's own IRC 457(b)(3) ceiling compensation — rather than the
        // participant's pool or another account's priority — is what leaves unfunded.
        $compensationBeforeCatchUp = $compensationRemaining;

        if ($resolution['mode'] === 'indeterminate') {
            if (
                $mayDrawCatchUp
                && (
                    $roomACatchUpCouldOccupy > 0.0
                    || $accountExistingAgeCatchUp + $accountExistingSpecialCatchUp > 0.0
                )
            ) {
                $diagnostics[] = self::workplaceCatchUpAgeDiagnostic($person['id']);
            }
        } elseif ($resolution['mode'] === 'special' && $catchUpPotential > 0.0) {
            // IRC 457(b)(3) raises "the ceiling set forth in paragraph (2)" — a
            // plan-level ceiling on deferrals, not an account-level one — so it
            // composes with the IRC 402A(e)(3)(A) balance cap in exactly the way
            // IRC 414(v) does, and draws the same account-local room.
            $specialAdded = self::takeAcrossPools(
                $context,
                array_merge([['section457SpecialCatchUpPools', $ownerId]], $plesaRefs),
                $catchUpPotential,
                $sharedLimits,
            );
            // IRC 457(b)(3) supplies the capacity; what decides the tax treatment is
            // the account it lands in. On a pension-linked emergency savings account
            // that is never a choice: IRC 402A(e)(1)(A)(i) treats the account as a
            // designated Roth account for purposes of the title, so every participant
            // contribution to it is Roth whatever limitation it was made under.
            if (self::accountUsesRothEmployeeContributions($account, $traits)) {
                $additional['special457RothCatchUp'] = $specialAdded;
                $annual['special457RothCatchUp'] = self::roundMoney($annual['special457RothCatchUp'] + $specialAdded);
            } else {
                $additional['special457CatchUp'] = $specialAdded;
                $annual['special457CatchUp'] = self::roundMoney($annual['special457CatchUp'] + $specialAdded);
            }
            $compensationRemaining = self::nonnegative($compensationRemaining - $specialAdded);
            if ($resolution['ageAmount'] > 0.0) {
                $diagnostics[] = self::diagnostic(
                    'SECTION_457_SPECIAL_CATCH_UP_SELECTED_OVER_AGE_CATCH_UP',
                    DiagnosticSeverity::INFO,
                    'The special last-three-years 457(b) catch-up produced the larger limit; it cannot be combined with the age-based catch-up.',
                    "accounts.{$account['id']}",
                );
            }
        } elseif ($resolution['mode'] === 'age' && $catchUpPotential > 0.0) {
            $classification = self::catchUpTaxTreatment($context, $account, $traits, $diagnostics);
            $treatment = $classification['treatment'];
            if ($treatment === 'unknown') {
                self::reportPoolWithoutConsuming($context['section457CatchUpPools'][$ownerId], $sharedLimits);
            } elseif ($treatment !== 'unavailable') {
                $ageAdded = self::takeAcrossPools(
                    $context,
                    array_merge([['section457CatchUpPools', $ownerId]], $plesaRefs),
                    $catchUpPotential,
                    $sharedLimits,
                );
                if ($ageAdded > 0 && $classification['reportsHighWageRothAllocation']) {
                    self::appendHighWageRothCatchUpAllocatedDiagnostic($context, $account, $diagnostics);
                }
                if ($treatment === 'roth') {
                    $additional['employeeRothCatchUp'] = $ageAdded;
                    $annual['employeeRothCatchUp'] = self::roundMoney($annual['employeeRothCatchUp'] + $ageAdded);
                } else {
                    $additional['employeePreTaxCatchUp'] = $ageAdded;
                    $annual['employeePreTaxCatchUp'] = self::roundMoney($annual['employeePreTaxCatchUp'] + $ageAdded);
                }
                $compensationRemaining = self::nonnegative($compensationRemaining - $ageAdded);
            }
        }
        // The catch-up this account may reach for the year, as a ceiling rather than as
        // whatever survived allocation. Built from residual capacity it shrank as other
        // accounts spent the pool, so an account holding existing IRC 457(b)(3)
        // contributions reported a maximum smaller than what it already held and
        // tripped the excess diagnostic by exactly that amount.
        //
        // It is bounded by the *plan's* own ceiling as well as the participant's.
        // 26 CFR 1.457-5(d) Example 2 states both figures for the same participant and
        // keeps them apart: the individual limitation is $23,000, which "is the
        // catch-up amount applicable to Participant E under Plan Y", while Plan W —
        // whose own underutilized limitation is $7,000 — separately permits "$22,000 to
        // Plan W and none to any of the other three plans". Reporting the
        // participant-wide amount on every plan turned each of the four into the
        // largest of them.
        $accountCatchUpCeiling = match ($resolution['mode']) {
            'special' => $ceilings['specialAdditional'],
            'age' => $ceilings['ageAdditional'],
            default => 0.0,
        };
        $applicableCatchUpHeadroom = $mayDrawCatchUp
            ? self::minMoney($resolution['headroom'], $accountCatchUpCeiling)
            : 0.0;
        // IRC 457(b)(3) replaces the paragraph (2) ceiling rather than adding to it, so
        // the 100-percent-of-compensation bound inside that paragraph does not apply to
        // the ceiling this account reports. A salary reduction is still bounded by the
        // compensation there is to reduce, so where the two diverge the difference is
        // capacity the plan lawfully has and the supplied facts cannot fill: reaching it
        // would take a nonelective employer contribution, which this engine allocates
        // only up to the paragraph (c)(1) ceiling. Saying so is what keeps the statutory
        // maximum and the based-on-inputs maximum legible as different figures rather
        // than looking like an inconsistency.
        $specialCeilingBeyondCompensation = self::nonnegative(
            self::nonnegative($applicableCatchUpHeadroom - $accountExistingSpecialCatchUp)
                - $compensationBeforeCatchUp,
        );
        if (
            $resolution['mode'] === 'special'
            && $mayDrawCatchUp
            && $specialCeilingBeyondCompensation > 0.0
        ) {
            $diagnostics[] = self::diagnostic(
                'SECTION_457_SPECIAL_CATCH_UP_EXCEEDS_DEFERRABLE_COMPENSATION',
                DiagnosticSeverity::INFO,
                '$'
                    . self::localeNumber($specialCeilingBeyondCompensation)
                    . ' of the IRC 457(b)(3) plan ceiling this account reports cannot be reached'
                    . ' from the supplied facts. IRC 457(b)(3) provides that the paragraph (2)'
                    . ' ceiling "shall be" the lesser of twice the IRC 457(e)(15) amount and the sum'
                    . " of the current paragraph (2) ceiling and prior years' unused limitation,"
                    . ' replacing the 100-percent-of-includible-compensation bound rather than'
                    . ' reapplying it, so the ceiling stands above what compensation alone can fund.'
                    . ' A deferral of compensation cannot exceed the compensation, so the difference'
                    . ' is reachable only by a nonelective employer contribution, which this engine'
                    . ' allocates no higher than the paragraph (c)(1) ceiling. The statutory maximum'
                    . ' reports the plan ceiling; the maximum based on inputs reports what the'
                    . ' supplied facts fund.',
                "accounts.{$account['id']}",
                'IRC 457(b)(3); 26 CFR 1.457-4(c)(3)(i)',
            );
        }
        if (!$traits['governmental457']) {
            $diagnostics[] = self::diagnostic(
                'NONGOVERNMENTAL_457B_ASSETS_REMAIN_EMPLOYER_PROPERTY',
                DiagnosticSeverity::INFO,
                "A nongovernmental tax-exempt 457(b) plan is generally unfunded; assets remain subject to the employer's general creditors.",
                "accounts.{$account['id']}",
            );
        }
        return [
            'status' => self::accountStatusFromDiagnostics(CalculationStatus::DETERMINATE->value, $diagnostics),
            // The host's own statutory annual capacity, and — for a pension-linked
            // emergency savings account — the IRC 402A(e)(3)(A)(i) room on top of what
            // the participant has already contributed. Only clause (i) is statutory: a
            // sponsor's lower clause (ii) amount is a plan term, so it lowers what may
            // actually be contributed without lowering the figure the statute reports,
            // exactly as on the IRC 401(a) and IRC 403(b) hosts.
            'statutoryMaximum' => self::roundMoney(
                $plesaCaps === null
                    ? $statutoryHostBaseLimit + $applicableCatchUpHeadroom
                    : self::minMoney(
                        $statutoryHostBaseLimit + $applicableCatchUpHeadroom,
                        $existingParticipantContributions + $plesaCaps['statutoryPlesaRoom'],
                    ),
            ),
            'annualComponents' => $annual,
            'additionalComponents' => $additional,
            'planTermDependentCapacity' => 0.0,
            'sharedLimits' => $sharedLimits,
            'diagnostics' => $diagnostics,
        ];
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $account
     *  @return array<string,mixed>
     */
    private static function allocateDefinedBenefit(array $context, array $account): array
    {
        $annualBenefitLimit = $context['parameters']['definedBenefitAnnualBenefit415b'] ?? null;
        $diagnostics = [
            self::diagnostic(
                'DEFINED_BENEFIT_CONTRIBUTION_REQUIRES_ACTUARIAL_VALUATION',
                DiagnosticSeverity::ERROR,
                'A defined-benefit or cash-balance contribution is determined by the plan formula, funding method, assets, assumptions, participant census, and minimum/maximum funding rules; it is not a single statutory contribution limit.',
                "accounts.{$account['id']}",
                'IRC 404, 412, 415(b); ERISA funding rules',
            ),
        ];
        if ($annualBenefitLimit !== null) {
            $annualBenefitLimit = (float) $annualBenefitLimit;
            $diagnostics[] = self::diagnostic(
                'DEFINED_BENEFIT_ANNUAL_BENEFIT_LIMIT_REPORTED',
                DiagnosticSeverity::INFO,
                'The IRC 415(b)(1)(A) limitation on the annual benefit for ' . $context['taxYear']
                    . ' is $' . self::localeNumber($annualBenefitLimit)
                    . '. It caps the benefit the plan may pay, stated as a straight life annuity beginning between ages 62 and 65, and is neither a contribution ceiling nor a funding figure.'
                    . ' IRC 415(b)(2) adjusts it for another benefit form or starting age and IRC 415(b)(5) reduces it for fewer than ten years; neither adjustment is applied here.',
                "accounts.{$account['id']}",
                'IRC 415(b)(1)(A), 415(d)',
            );
        }
        $outcome = self::emptyOutcome($account, CalculationStatus::INDETERMINATE->value, null, $diagnostics);
        $outcome['definedBenefitDetail'] = ['annualBenefitLimit' => $annualBenefitLimit];

        return $outcome;
    }

    /** @param array<string,mixed> $account
     *  @return array<string,mixed>
     */
    private static function allocateSection457f(array $account): array
    {
        return self::emptyOutcome($account, CalculationStatus::INDETERMINATE->value, null, [
            self::diagnostic(
                'SECTION_457F_HAS_NO_457B_ANNUAL_DEFERRAL_LIMIT',
                DiagnosticSeverity::ERROR,
                'A 457(f) arrangement is an ineligible deferred-compensation arrangement. Tax timing depends on substantial risk of forfeiture and plan terms rather than the 457(b) annual limit.',
                "accounts.{$account['id']}",
                'IRC 457(f)',
            ),
        ]);
    }

    /** @param list<array<string,mixed>> $conversions
     *  @param array<string,array<string,mixed>> $persons
     *  @param array<string,array<string,mixed>> $accountsById
     *  @return list<array<string,mixed>>
     */
    private static function normalizeConversions(mixed $conversionsInput, array $persons, array $accountsById): array
    {
        $conversions = $conversionsInput === null ? [] : self::toInputList($conversionsInput);
        if ($conversions === null) {
            throw new ParameterException('INVALID_CONVERSIONS', 'conversions must be an array.');
        }
        $ids = [];
        $result = [];
        foreach ($conversions as $index => $input) {
            if (!is_array($input)) {
                throw new ParameterException(
                    'INVALID_CONVERSION',
                    "conversions[{$index}] must be an object/associative array.",
                );
            }
            $id = self::trimmedIdentifier($input['id'] ?? null);
            if ($id === null) {
                throw new ParameterException(
                    'CONVERSION_ID_REQUIRED',
                    "conversions[{$index}].id is required.",
                );
            }
            if (isset($ids[$id])) {
                throw new ParameterException('DUPLICATE_CONVERSION_ID', "Duplicate conversion ID: {$id}");
            }
            $ids[$id] = true;
            $ownerId = self::trimmedIdentifier($input['ownerId'] ?? null);
            if ($ownerId === null) {
                throw new ParameterException('CONVERSION_OWNER_REQUIRED', "conversions[{$index}].ownerId is required.");
            }
            if (!isset($persons[$ownerId])) {
                throw new ParameterException(
                    'UNKNOWN_CONVERSION_OWNER',
                    "Conversion {$id} references unknown owner {$ownerId}.",
                );
            }
            if (isset($input['sourceAccountId']) && !isset($accountsById[$input['sourceAccountId']])) {
                throw new ParameterException(
                    'UNKNOWN_CONVERSION_SOURCE_ACCOUNT',
                    "Conversion {$id} references unknown source account {$input['sourceAccountId']}.",
                );
            }
            self::booleanFlag(
                $input,
                'otherwiseDistributableAmount',
                "conversions[{$index}].otherwiseDistributableAmount",
            );
            $normalized = $input;
            $normalized['id'] = $id;
            $normalized['ownerId'] = $ownerId;
            $normalized['type'] = self::parseConversionType(
                $input['type'] ?? null,
                array_key_exists('type', $input),
            );
            $normalized['amount'] = self::money($input['amount'] ?? null, "conversions[{$index}].amount");
            foreach (
                ['afterTaxBasisInConvertedAmount', 'aggregateIraBasisOverride', 'yearEndAggregateIraValueOverride']
                as $key
            ) {
                if (array_key_exists($key, $input)) {
                    $normalized[$key] = self::money($input[$key], "conversions[{$index}].{$key}");
                }
            }
            $normalized['inputIndex'] = $index;
            $result[] = $normalized;
        }
        return $result;
    }

    /** @return array<string,mixed> */
    private static function conversionTaxEffects(float $taxableAmount): array
    {
        $result = self::zeroTaxEffects();
        $result['federalAgiIncrease'] = $taxableAmount;
        $result['taxableRothConversion'] = $taxableAmount;
        $result['notes'][] = 'A taxable Roth conversion generally increases federal gross income but does not consume an annual contribution limit.';
        return $result;
    }

    /** @param array<string,mixed> $conversion
     *  @return array<string,mixed>
     */
    private static function unavailableConversion(array $conversion, string $code, string $message): array
    {
        return [
            'conversionId' => $conversion['id'],
            'conversionType' => $conversion['type'],
            'ownerId' => $conversion['ownerId'],
            'status' => CalculationStatus::UNAVAILABLE->value,
            'grossConvertedAmount' => $conversion['amount'],
            'taxableAmount' => null,
            'nontaxableBasisAmount' => null,
            'consumesAnnualContributionLimit' => false,
            'federalTaxEffects' => self::zeroTaxEffects(),
            'diagnostics' => [self::diagnostic(
                $code,
                DiagnosticSeverity::ERROR,
                $message,
                "conversions.{$conversion['id']}",
            )],
        ];
    }

    /** @param array<string,mixed> $conversion
     *  @param list<array<string,mixed>> $diagnostics
     *  @return array<string,mixed>
     */
    private static function indeterminateConversion(array $conversion, array $diagnostics): array
    {
        return [
            'conversionId' => $conversion['id'],
            'conversionType' => $conversion['type'],
            'ownerId' => $conversion['ownerId'],
            'status' => CalculationStatus::INDETERMINATE->value,
            'grossConvertedAmount' => $conversion['amount'],
            'taxableAmount' => null,
            'nontaxableBasisAmount' => null,
            'consumesAnnualContributionLimit' => false,
            'federalTaxEffects' => self::zeroTaxEffects(),
            'diagnostics' => $diagnostics,
        ];
    }

    /** @param array<string,mixed> $context
     *  @param list<array<string,mixed>> $conversions
     *  @param list<array<string,mixed>> $accountResults
     *  @return list<array<string,mixed>>
     */
    private static function calculateConversions(
        array $context,
        array $conversions,
        array $accountResults,
    ): array {
        $results = [];
        $iraByOwner = [];
        foreach ($conversions as $conversion) {
            if ($conversion['type'] === ConversionType::IRA_TO_ROTH_IRA->value) {
                $iraByOwner[$conversion['ownerId']][] = $conversion;
            } else {
                $results[$conversion['id']] = self::calculateNonIraConversion($context, $conversion);
            }
        }
        foreach ($iraByOwner as $ownerId => $ownerConversions) {
            foreach (self::calculateIraConversionGroup($context, $ownerId, $ownerConversions, $accountResults) as $result) {
                $results[$result['conversionId']] = $result;
            }
        }
        return array_map(
            static fn (array $conversion): array => $results[$conversion['id']],
            $conversions,
        );
    }

    /** @param array<string,mixed> $context
     *  @param array<string,mixed> $conversion
     *  @return array<string,mixed>
     */
    private static function calculateNonIraConversion(array $context, array $conversion): array
    {
        if ($conversion['type'] === ConversionType::QUALIFIED_PLAN_TO_ROTH_IRA->value) {
            if ($context['taxYear'] < 2008) {
                return self::unavailableConversion(
                    $conversion,
                    'DIRECT_QUALIFIED_PLAN_TO_ROTH_IRA_NOT_AVAILABLE',
                    'A direct qualified-plan rollover to a Roth IRA is modeled as available beginning in 2008.',
                );
            }
            $basis = self::minMoney(
                $conversion['amount'],
                self::money(
                    $conversion['afterTaxBasisInConvertedAmount'] ?? null,
                    "{$conversion['id']}.afterTaxBasisInConvertedAmount",
                ),
            );
            $taxable = self::roundMoney($conversion['amount'] - $basis);
            return [
                'conversionId' => $conversion['id'],
                'conversionType' => $conversion['type'],
                'ownerId' => $conversion['ownerId'],
                'status' => CalculationStatus::DETERMINATE->value,
                'grossConvertedAmount' => $conversion['amount'],
                'taxableAmount' => $taxable,
                'nontaxableBasisAmount' => $basis,
                'consumesAnnualContributionLimit' => false,
                'federalTaxEffects' => self::conversionTaxEffects($taxable),
                'diagnostics' => [],
            ];
        }
        if ($context['taxYear'] < 2010) {
            return self::unavailableConversion(
                $conversion,
                'IN_PLAN_ROTH_ROLLOVER_NOT_AVAILABLE',
                'In-plan Roth rollovers are modeled as available beginning in 2010.',
            );
        }
        if (empty($conversion['sourceAccountId'])) {
            return self::indeterminateConversion($conversion, [self::diagnostic(
                'SOURCE_ACCOUNT_REQUIRED_FOR_IN_PLAN_ROTH_ROLLOVER',
                DiagnosticSeverity::ERROR,
                'sourceAccountId is required to verify that the plan permits an in-plan Roth rollover.',
                "conversions.{$conversion['id']}.sourceAccountId",
            )]);
        }
        $source = $context['accountsById'][$conversion['sourceAccountId']];
        if (empty($source['planRules']['permitsInPlanRothRollover'])) {
            return self::indeterminateConversion($conversion, [self::diagnostic(
                'PLAN_DOES_NOT_PERMIT_IN_PLAN_ROTH_ROLLOVER',
                DiagnosticSeverity::ERROR,
                'The supplied source plan rules do not permit an in-plan Roth rollover.',
                "accounts.{$source['id']}.planRules.permitsInPlanRothRollover",
            )]);
        }
        if ($context['taxYear'] < 2013 && ($conversion['otherwiseDistributableAmount'] ?? false) !== true) {
            return self::unavailableConversion(
                $conversion,
                'PRE_2013_IN_PLAN_ROLLOVER_REQUIRES_DISTRIBUTABLE_AMOUNT',
                'For 2010-2012, the modeled in-plan Roth rollover amount must otherwise have been distributable.',
            );
        }
        $basis = self::minMoney(
            $conversion['amount'],
            self::money(
                $conversion['afterTaxBasisInConvertedAmount'] ?? null,
                "{$conversion['id']}.afterTaxBasisInConvertedAmount",
            ),
        );
        $taxable = self::roundMoney($conversion['amount'] - $basis);
        return [
            'conversionId' => $conversion['id'],
            'conversionType' => $conversion['type'],
            'ownerId' => $conversion['ownerId'],
            'status' => CalculationStatus::DETERMINATE->value,
            'grossConvertedAmount' => $conversion['amount'],
            'taxableAmount' => $taxable,
            'nontaxableBasisAmount' => $basis,
            'consumesAnnualContributionLimit' => false,
            'federalTaxEffects' => self::conversionTaxEffects($taxable),
            'diagnostics' => [],
        ];
    }

    /** @param array<string,mixed> $context
     *  @param list<array<string,mixed>> $conversions
     *  @param list<array<string,mixed>> $accountResults
     *  @return list<array<string,mixed>>
     */
    private static function calculateIraConversionGroup(
        array $context,
        string $ownerId,
        array $conversions,
        array $accountResults,
    ): array {
        if ($context['taxYear'] < 1998) {
            return array_map(
                static fn (array $conversion): array => self::unavailableConversion(
                    $conversion,
                    'ROTH_IRA_CONVERSION_NOT_AVAILABLE',
                    'Roth IRA conversions are modeled as available beginning in 1998.',
                ),
                $conversions,
            );
        }
        $person = $context['persons'][$ownerId];
        if ($context['taxYear'] < 2010) {
            if (
                $context['filingStatus'] === FilingStatus::MARRIED_FILING_SEPARATELY->value
                && self::livedWithSpouse($person, $context['filingStatus'])
            ) {
                return array_map(
                    static fn (array $conversion): array => self::unavailableConversion(
                        $conversion,
                        'PRE_2010_MFS_ROTH_CONVERSION_NOT_ELIGIBLE',
                        'Before 2010, a married-filing-separately taxpayer who lived with a spouse during the year is modeled as ineligible for a Roth IRA conversion.',
                    ),
                    $conversions,
                );
            }
            if (!array_key_exists('rothConversion', $person['magi'])) {
                return array_map(
                    static fn (array $conversion): array => self::indeterminateConversion($conversion, [
                        self::diagnostic(
                            'PRE_2010_CONVERSION_MAGI_REQUIRED',
                            DiagnosticSeverity::ERROR,
                            'Pre-conversion MAGI is required to apply the pre-2010 $100,000 Roth-conversion eligibility limit.',
                            "persons.{$conversion['ownerId']}.magi.rothConversion",
                        ),
                    ]),
                    $conversions,
                );
            }
            if ((float) $person['magi']['rothConversion'] > 100000.0) {
                return array_map(
                    static fn (array $conversion): array => self::unavailableConversion(
                        $conversion,
                        'PRE_2010_ROTH_CONVERSION_MAGI_LIMIT_EXCEEDED',
                        'The modeled pre-2010 $100,000 MAGI limit for Roth IRA conversions was exceeded.',
                    ),
                    $conversions,
                );
            }
        }
        $currentNondeductible = 0.0;
        $unclassified = 0.0;
        foreach ($accountResults as $result) {
            if ($result['ownerId'] !== $ownerId) {
                continue;
            }
            $currentNondeductible += (float) $result['contributionComponents']['nondeductibleIra'];
            $unclassified += (float) $result['contributionComponents']['unclassifiedIra'];
        }
        if ($unclassified > 0.0) {
            return array_map(
                static fn (array $conversion): array => self::indeterminateConversion($conversion, [
                    self::diagnostic(
                        'IRA_CONVERSION_BASIS_INDETERMINATE_FROM_UNCLASSIFIED_CONTRIBUTION',
                        DiagnosticSeverity::ERROR,
                        "A current-year traditional IRA contribution has unresolved deductibility, so aggregate IRA basis and the conversion's taxable amount are indeterminate.",
                        "conversions.{$conversion['id']}",
                    ),
                ]),
                $conversions,
            );
        }
        $firstBasisOverride = null;
        $basisOverrideFound = false;
        $firstValueOverride = null;
        $valueOverrideFound = false;
        foreach ($conversions as $conversion) {
            if (!$basisOverrideFound && array_key_exists('aggregateIraBasisOverride', $conversion)) {
                $firstBasisOverride = (float) $conversion['aggregateIraBasisOverride'];
                $basisOverrideFound = true;
            }
            if (!$valueOverrideFound && array_key_exists('yearEndAggregateIraValueOverride', $conversion)) {
                $firstValueOverride = (float) $conversion['yearEndAggregateIraValueOverride'];
                $valueOverrideFound = true;
            }
        }
        $priorBasis = $basisOverrideFound
            ? $firstBasisOverride
            : ($person['traditionalSepSimpleIraBasis'] ?? null);
        $yearEndValue = $valueOverrideFound
            ? $firstValueOverride
            : ($person['yearEndTraditionalSepSimpleIraValue'] ?? null);
        if ($priorBasis === null || $yearEndValue === null) {
            return array_map(
                static fn (array $conversion): array => self::indeterminateConversion($conversion, [
                    self::diagnostic(
                        'AGGREGATE_IRA_BASIS_AND_YEAR_END_VALUE_REQUIRED',
                        DiagnosticSeverity::ERROR,
                        'Aggregate traditional/SEP/SIMPLE IRA basis and December 31 value are required for the Form 8606 pro-rata calculation; explicitly provide zero when applicable.',
                        "persons.{$conversion['ownerId']}",
                        'Form 8606',
                    ),
                ]),
                $conversions,
            );
        }
        $inconsistent = false;
        foreach ($conversions as $conversion) {
            if (
                array_key_exists('aggregateIraBasisOverride', $conversion)
                && (float) $conversion['aggregateIraBasisOverride'] !== $firstBasisOverride
            ) {
                $inconsistent = true;
            }
            if (
                array_key_exists('yearEndAggregateIraValueOverride', $conversion)
                && (float) $conversion['yearEndAggregateIraValueOverride'] !== $firstValueOverride
            ) {
                $inconsistent = true;
            }
        }
        if ($inconsistent) {
            return array_map(
                static fn (array $conversion): array => self::indeterminateConversion($conversion, [
                    self::diagnostic(
                        'INCONSISTENT_AGGREGATE_IRA_OVERRIDES',
                        DiagnosticSeverity::ERROR,
                        'All IRA conversions for one owner must use the same aggregate basis and year-end IRA value overrides.',
                        "conversions.{$conversion['id']}",
                    ),
                ]),
                $conversions,
            );
        }
        $totalConversion = self::roundMoney(array_sum(array_column($conversions, 'amount')));
        $otherDistributions = self::money(
            $person['otherTraditionalSepSimpleIraDistributions'] ?? null,
            "{$ownerId}.otherTraditionalSepSimpleIraDistributions",
        );
        $denominator = self::roundMoney((float) $yearEndValue + $totalConversion + $otherDistributions);
        $availableBasis = self::minMoney($denominator, self::roundMoney((float) $priorBasis + $currentNondeductible));
        $nontaxableRatio = $denominator > 0.0 ? $availableBasis / $denominator : 0.0;
        $aggregateNontaxable = self::minMoney(
            $totalConversion,
            self::roundMoney($totalConversion * $nontaxableRatio),
        );
        $totalConversionCents = (int) round($totalConversion * 100);
        $targetNontaxableCents = (int) round($aggregateNontaxable * 100);
        $allocations = [];
        foreach ($conversions as $index => $conversion) {
            $amountCents = (int) round((float) $conversion['amount'] * 100);
            $rawCents = $totalConversionCents > 0
                ? ($amountCents * $targetNontaxableCents) / $totalConversionCents
                : 0.0;
            $floorCents = min($amountCents, (int) floor($rawCents));
            $allocations[$index] = [
                'index' => $index,
                'amountCents' => $amountCents,
                'cents' => $floorCents,
                'remainder' => $rawCents - $floorCents,
            ];
        }
        $residualCents = $targetNontaxableCents - array_sum(array_column($allocations, 'cents'));
        $allocationOrder = array_keys($allocations);
        usort($allocationOrder, static function (int $left, int $right) use ($allocations): int {
            $remainderComparison = $allocations[$right]['remainder'] <=> $allocations[$left]['remainder'];
            return $remainderComparison !== 0 ? $remainderComparison : ($left <=> $right);
        });
        foreach ($allocationOrder as $index) {
            if ($residualCents <= 0) {
                break;
            }
            if ($allocations[$index]['cents'] < $allocations[$index]['amountCents']) {
                $allocations[$index]['cents']++;
                $residualCents--;
            }
        }
        $results = [];
        foreach ($conversions as $index => $conversion) {
            $nontaxable = self::roundMoney($allocations[$index]['cents'] / 100);
            $taxable = self::roundMoney($conversion['amount'] - $nontaxable);
            $diagnostics = [];
            if ($context['taxYear'] === 2010) {
                $diagnostics[] = self::diagnostic(
                    '2010_SPECIAL_INCOME_INCLUSION_ELECTION_NOT_MODELED',
                    DiagnosticSeverity::INFO,
                    'The optional special timing rule for income from certain 2010 Roth conversions is outside this contribution-limit engine; the result reports total taxable conversion income.',
                    "conversions.{$conversion['id']}",
                );
            }
            $results[] = [
                'conversionId' => $conversion['id'],
                'conversionType' => $conversion['type'],
                'ownerId' => $conversion['ownerId'],
                'status' => CalculationStatus::DETERMINATE->value,
                'grossConvertedAmount' => $conversion['amount'],
                'taxableAmount' => $taxable,
                'nontaxableBasisAmount' => $nontaxable,
                'consumesAnnualContributionLimit' => false,
                'federalTaxEffects' => self::conversionTaxEffects($taxable),
                'diagnostics' => $diagnostics,
            ];
        }
        return $results;
    }

    /** @param list<array<string,mixed>> $accounts
     *  @param list<array<string,mixed>> $conversions
     *  @return array<string,float>
     */
    private static function totals(array $accounts, array $conversions): array
    {
        $totals = [
            'maximumAnnualContributionBasedOnInputs' => 0.0,
            'maximumAdditionalContributionBasedOnInputs' => 0.0,
            'employeePreTaxContribution' => 0.0,
            'employeeRothOrAfterTaxContribution' => 0.0,
            'employerPreTaxContribution' => 0.0,
            'employerRothContribution' => 0.0,
            'deductibleIraContribution' => 0.0,
            'nondeductibleIraContribution' => 0.0,
            'hsaContribution' => 0.0,
            'healthFsaSalaryReduction' => 0.0,
            'dependentCareAssistanceExclusion' => 0.0,
            'dependentCareIncludibleInIncome' => 0.0,
            'federalAgiReduction' => 0.0,
            'federalAgiIncrease' => 0.0,
            'taxableRothConversions' => 0.0,
        ];
        foreach ($accounts as $account) {
            $components = $account['contributionComponents'];
            $totals['maximumAnnualContributionBasedOnInputs'] = self::roundMoney(
                $totals['maximumAnnualContributionBasedOnInputs']
                + (float) ($account['maximumAnnualContributionBasedOnInputs'] ?? 0.0),
            );
            $totals['maximumAdditionalContributionBasedOnInputs'] = self::roundMoney(
                $totals['maximumAdditionalContributionBasedOnInputs']
                + (float) ($account['maximumAdditionalContributionBasedOnInputs'] ?? 0.0),
            );
            $totals['employeePreTaxContribution'] = self::roundMoney(
                $totals['employeePreTaxContribution']
                + $components['employeePreTaxDeferral']
                + $components['employeePreTaxCatchUp']
                + $components['special403bCatchUp']
                + $components['special457CatchUp'],
            );
            $totals['employeeRothOrAfterTaxContribution'] = self::roundMoney(
                $totals['employeeRothOrAfterTaxContribution']
                + $components['employeeRothDeferral']
                + $components['employeeRothCatchUp']
                + $components['special457RothCatchUp']
                + $components['employeeAfterTax']
                + $components['rothIra']
                + $components['nondeductibleIra']
                + $components['unclassifiedIra'],
            );
            $totals['employerPreTaxContribution'] = self::roundMoney(
                $totals['employerPreTaxContribution'] + $components['employerPreTax'],
            );
            $totals['employerRothContribution'] = self::roundMoney(
                $totals['employerRothContribution'] + $components['employerRoth'],
            );
            $totals['deductibleIraContribution'] = self::roundMoney(
                $totals['deductibleIraContribution'] + $components['deductibleIra'],
            );
            $totals['nondeductibleIraContribution'] = self::roundMoney(
                $totals['nondeductibleIraContribution']
                + $components['nondeductibleIra']
                + $components['unclassifiedIra'],
            );
            $totals['hsaContribution'] = self::roundMoney(
                $totals['hsaContribution']
                + $components['hsaDeductible']
                + $components['hsaEmployerOrCafeteria'],
            );
            $totals['healthFsaSalaryReduction'] = self::roundMoney(
                $totals['healthFsaSalaryReduction'] + $components['healthFsaSalaryReduction'],
            );
            $totals['dependentCareAssistanceExclusion'] = self::roundMoney(
                $totals['dependentCareAssistanceExclusion'] + $components['dependentCareAssistanceProvided'],
            );
            $totals['dependentCareIncludibleInIncome'] = self::roundMoney(
                $totals['dependentCareIncludibleInIncome'] + $components['dependentCareIncludibleInIncome'],
            );
            $totals['federalAgiReduction'] = self::roundMoney(
                $totals['federalAgiReduction'] + $account['federalTaxEffects']['federalAgiReduction'],
            );
            $totals['federalAgiIncrease'] = self::roundMoney(
                $totals['federalAgiIncrease'] + $account['federalTaxEffects']['federalAgiIncrease'],
            );
        }
        foreach ($conversions as $conversion) {
            $totals['federalAgiIncrease'] = self::roundMoney(
                $totals['federalAgiIncrease'] + $conversion['federalTaxEffects']['federalAgiIncrease'],
            );
            $totals['taxableRothConversions'] = self::roundMoney(
                $totals['taxableRothConversions'] + (float) ($conversion['taxableAmount'] ?? 0.0),
            );
        }
        return $totals;
    }
}
