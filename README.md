# us-tax-advantaged-params

[![CI](https://github.com/bherila/us-tax-advantaged-params/actions/workflows/ci.yml/badge.svg)](https://github.com/bherila/us-tax-advantaged-params/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

`us-tax-advantaged-params` is a dependency-free calculation engine for historical and current U.S. tax-advantaged account parameters. It calculates account-level and household-level contribution capacity, IRA phase-outs, shared statutory limits, federal income effects, and Roth-conversion taxability for retirement accounts, and contribution capacity for health savings accounts under IRC §223.

The repository contains two native implementations with the same behavior:

- **TypeScript** for npm, exported as `USTaxAdvantagedParams`.
- **PHP 8.4+** for Packagist, in the `USTaxAdvantagedParams` namespace.

Annual legal parameters are maintained once in `data/retirement-parameters.json` and `data/hsa-parameters.json`, and generated into each single-file runtime. Shared conformance vectors and a full-output parity check keep the TypeScript and PHP engines synchronized.

> **Tax-software scope, not tax advice.** This package calculates statutory parameters from caller-supplied facts. It does not determine whether a plan document permits a contribution, perform ERISA nondiscrimination testing, calculate self-employment tax, replace Form 8606, provide an actuarial valuation, or prepare a tax return. Review material results against the governing plan document and current primary authority.

## Supported tax years

The encoded range is **1975 through 2026**. The package does not extrapolate a future year. Calling a year outside the range throws `UnsupportedTaxYearError` in TypeScript or `UnsupportedTaxYearException` in PHP.

The 1975 starting point corresponds to the first generally available IRA contribution year. Some early employer-plan years cannot be reduced to a universal modern dollar ceiling from tax year alone. In those cases the engine returns an explicit `indeterminate` status and diagnostic rather than inventing a value.

```ts
USTaxAdvantagedParams.supportedTaxYears();
// { minimum: 1975, maximum: 2026 }
```

Health savings accounts have their own range, **2004 through 2026**, because IRC §223 was
added by the Medicare Prescription Drug, Improvement, and Modernization Act of 2003
effective for taxable years beginning after 2003. A year before 2004 returns an
`unavailable` HSA result rather than an extrapolated one.

```ts
USTaxAdvantagedParams.supportedHsaTaxYears();
// { minimum: 2004, maximum: 2026 }
```

Flexible spending arrangements have their own range, **1987 through 2026**. It starts at
1987 because the Tax Reform Act of 1986 §1163 added the §129(a)(2)(A) dependent care
exclusion limitation for taxable years beginning after December 31, 1986; before that
§129(a) carried no dollar cap. The §125(i) health FSA limit starts later, at **2013**,
because the Affordable Care Act §9005 added it for plan years beginning after December 31,
2012. A year between the two returns dependent care figures and a null `healthFsa`.

```ts
USTaxAdvantagedParams.supportedFsaTaxYears();
// { minimum: 1987, maximum: 2026 }
USTaxAdvantagedParams.fsaParametersForYear(2012)?.healthFsa;
// null
```

## Installation

### npm

```bash
npm install us-tax-advantaged-params
```

The npm package provides ESM, CommonJS, and TypeScript declarations and supports Node.js 20 or later.

```js
// ESM
import USTaxAdvantagedParams from "us-tax-advantaged-params";

// CommonJS — the class is the module's default export
const USTaxAdvantagedParams = require("us-tax-advantaged-params").default;
```

### Composer / Packagist

```bash
composer require bherila/us-tax-advantaged-params
```

The PHP package requires PHP 8.4 or later and loads the native single-file implementation through Composer.

## TypeScript builder example

```ts
import USTaxAdvantagedParams, {
  AccountType,
  ConversionType,
  FilingStatus,
} from "us-tax-advantaged-params";

const result = USTaxAdvantagedParams.forTaxYear(2026)
  .filingStatus(FilingStatus.MARRIED_FILING_JOINTLY)
  .taxpayer("taxpayer", (person) => {
    person
      .bornIn(1963)
      .iraCompensation(180_000)
      .w2Compensation(180_000)
      .rothIraMagi(240_000)
      .traditionalIraDeductionMagi(240_000)
      .coveredByEmployerPlan(true)
      .priorYearFicaWages("employer-a", 180_000)
      .aggregateTraditionalSepSimpleIraBasis(20_000)
      .yearEndTraditionalSepSimpleIraValue(80_000);
  })
  .spouse("spouse", (person) => {
    person
      .bornIn(1970)
      .iraCompensation(0)
      .rothIraMagi(240_000)
      .traditionalIraDeductionMagi(240_000)
      .coveredByEmployerPlan(false);
  })
  .account(
    "taxpayer-401k",
    "taxpayer",
    AccountType.TRADITIONAL_401K,
    (account) => {
      account
        .employer("employer-a")
        .annualAdditionsGroup("employer-a")
        .planCompensation(180_000)
        .permitsRothContributions()
        .permitsRothCatchUp()
        .permitsAfterTaxContributions()
        .expectedEmployerContribution(9_000)
        .priority(10);
    },
  )
  .account("taxpayer-roth-ira", "taxpayer", AccountType.ROTH_IRA, (account) => {
    account.priority(20);
  })
  .account("spouse-traditional-ira", "spouse", AccountType.TRADITIONAL_IRA, (account) => {
    account.priority(30);
  })
  .conversion(
    "ira-conversion",
    "taxpayer",
    ConversionType.IRA_TO_ROTH_IRA,
    10_000,
  )
  .calculate();

console.log(result.accounts[0].maximumAnnualContributionBasedOnInputs);
console.log(result.totals.federalAgiReduction);
console.log(result.conversions[0].taxableAmount);
```

A built scenario can be inspected and calculated repeatedly:

```ts
const scenario = USTaxAdvantagedParams.forTaxYear(2026)
  .filingStatus("MFJ")
  .taxpayer("taxpayer", (person) => person.bornIn(1980).w2Compensation(200_000))
  .build();

const input = scenario.toInput();
const result = scenario.calculate();
```

## PHP builder example

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use USTaxAdvantagedParams\AccountType;
use USTaxAdvantagedParams\FilingStatus;
use USTaxAdvantagedParams\PersonBuilder;
use USTaxAdvantagedParams\AccountBuilder;
use USTaxAdvantagedParams\USTaxAdvantagedParams as TaxAdvantagedParams;

$result = TaxAdvantagedParams::forTaxYear(2026)
    ->filingStatus(FilingStatus::MARRIED_FILING_JOINTLY)
    ->taxpayer('taxpayer', static function (PersonBuilder $person): void {
        $person
            ->bornIn(1963)
            ->iraCompensation(180_000)
            ->w2Compensation(180_000)
            ->rothIraMagi(240_000)
            ->traditionalIraDeductionMagi(240_000)
            ->coveredByEmployerPlan(true)
            ->priorYearFicaWages('employer-a', 180_000);
    })
    ->spouse('spouse', static function (PersonBuilder $person): void {
        $person
            ->bornIn(1970)
            ->iraCompensation(0)
            ->rothIraMagi(240_000)
            ->traditionalIraDeductionMagi(240_000)
            ->coveredByEmployerPlan(false);
    })
    ->account(
        'taxpayer-401k',
        'taxpayer',
        AccountType::TRADITIONAL_401K,
        static function (AccountBuilder $account): void {
            $account
                ->employer('employer-a')
                ->annualAdditionsGroup('employer-a')
                ->planCompensation(180_000)
                ->permitsRothContributions()
                ->permitsRothCatchUp()
                ->permitsAfterTaxContributions()
                ->expectedEmployerContribution(9_000)
                ->priority(10);
        },
    )
    ->account('spouse-ira', 'spouse', AccountType::TRADITIONAL_IRA)
    ->calculate();

var_dump($result['totals']);
```

The PHP result is an associative-array equivalent of the TypeScript result. Enum values serialize to the same snake-case strings.

## Direct unified interface

Builders are optional. Both engines accept the same language-neutral scenario shape, which is useful for services, fixtures, database records, and cross-runtime integrations.

```ts
const result = USTaxAdvantagedParams.calculate({
  taxYear: 2026,
  filingStatus: "HOH",
  persons: [
    {
      id: "taxpayer",
      role: "taxpayer",
      birthYear: 1975,
      compensation: { iraCompensation: 140_000, w2Compensation: 140_000 },
      magi: { rothIra: 158_000, traditionalIraDeduction: 158_000 },
      coveredByEmployerRetirementPlan: true,
    },
  ],
  accounts: [
    {
      id: "401k",
      ownerId: "taxpayer",
      type: "traditional_401k",
      employerId: "employer-a",
      planRules: {
        planCompensation: 140_000,
        annualAdditionsGroupId: "employer-a",
        expectedEmployerContribution: 7_000,
      },
    },
    { id: "roth-ira", ownerId: "taxpayer", type: "roth_ira", priority: 20 },
  ],
});
```

Filing-status aliases include `S`, `SINGLE`, `MFJ`, `MFS`, `HOH`, `QSS`, and `QW`. The alias `M` is accepted as MFJ but emits an ambiguity diagnostic. Canonical values are preferred in persisted data.

### Input rejection

The unified interface is where stale, mistyped, and cross-runtime data arrives, so an
input it cannot honour is rejected rather than coerced. Both engines throw the same error
code and the same message for the same bad input.

| Code | Raised for |
|---|---|
| `INVALID_TAX_YEAR` | A `taxYear` that is not an integer |
| `INVALID_FILING_STATUS` | A missing `filingStatus`, a non-string, or an unrecognized alias |
| `PERSON_REQUIRED` | `persons` missing, not a list, or empty |
| `INVALID_ACCOUNTS` / `INVALID_CONVERSIONS` | `accounts` or `conversions` present but not a list |
| `INVALID_PERSON` / `INVALID_ACCOUNT` / `INVALID_CONVERSION` | An entry of `persons`, `accounts`, or `conversions` that is not an object |
| `PERSON_ID_REQUIRED` / `ACCOUNT_ID_REQUIRED` / `CONVERSION_ID_REQUIRED` | An `id` that is missing, blank, or not a string |
| `ACCOUNT_OWNER_REQUIRED` / `CONVERSION_OWNER_REQUIRED` | An `ownerId` that is missing, blank, or not a string |
| `UNKNOWN_ACCOUNT_OWNER` / `UNKNOWN_CONVERSION_OWNER` | An `ownerId` that names no supplied person |
| `INVALID_ACCOUNT_TYPE` / `INVALID_CONVERSION_TYPE` | A type that is not a string, or an unrecognized one |
| `INVALID_INPUT_OBJECT` | A structured field — `planRules`, `existingContributions`, `compensation`, `magi`, `priorYearFicaWagesByEmployer`, `hsa`, `hsaCoverage`, `special403bCatchUp`, `section457SpecialCatchUp` — holding something other than an object |
| `INVALID_CONTRIBUTION_PREFERENCE` | A `contributionPreference` outside `account_type`, `pretax_first`, `roth_first` (a *valid* preference that a pension-linked emergency savings account cannot honour is reported as a diagnostic, not rejected — see [Pension-linked emergency savings accounts](#pension-linked-emergency-savings-accounts-irc-402ae)) |
| `INVALID_EMPLOYER_CONTRIBUTION_TAX_TREATMENT` | An `employerContributionTaxTreatment` outside `pretax`, `roth` |
| `INVALID_SIMPLE_EMPLOYER_CONTRIBUTION_METHOD` | A `simpleEmployerContributionMethod` outside `match_3_percent`, `nonelective_2_percent`, `custom` |
| `INVALID_MONEY` / `INVALID_RATE` | A negative or non-finite amount, or a rate outside 0 through 1 |
| `INVALID_BOOLEAN` | A flag field holding something other than `true` or `false` |

Enum-valued fields in particular are checked rather than compared loosely: a stale or
camel-cased value such as `"rothFirst"` would otherwise fall through to a different branch
and return a plausible but wrong allocation. Structured fields are checked for the same
reason — a scalar where an object belongs used to be ignored in silence, taking every rule
it carried with it. Flag fields must be actual booleans, because JavaScript and PHP
disagree about the truthiness of `"0"` and of an empty array.

Two shapes are deliberately *not* rejected. A missing `accounts` or `conversions` key, and
an explicit `null` in its place, both mean an empty list. And a JSON object whose keys are
exactly `"0"`, `"1"`, … is accepted wherever a list is expected, because `json_decode`
cannot tell it apart from a JSON array, so neither engine may.

## Account coverage

| Family | Account types |
|---|---|
| Individual retirement arrangements | Traditional IRA, Roth IRA, rollover IRA, payroll-deduction IRA, deemed traditional/Roth IRA, inherited traditional/Roth IRA |
| Small-employer arrangements | SEP IRA, Roth SEP IRA, SIMPLE IRA, Roth SIMPLE IRA, grandfathered SARSEP |
| Qualified elective plans | Traditional/Roth 401(k), Solo/Roth Solo 401(k), SIMPLE/Roth SIMPLE 401(k), starter 401(k), pension-linked emergency savings account (PLESA) |
| Tax-sheltered annuities | Traditional/Roth 403(b), safe-harbor deferral-only 403(b) — see the §403(b)(2) note below for tax years 1987-2001 |
| Deferred compensation | Governmental/Roth governmental 457(b), nongovernmental eligible 457(b), 457(f), governmental 457(b)-hosted PLESA |
| Federal plan | Traditional and Roth TSP |
| Employer-only defined-contribution plans | 401(a), profit-sharing, money-purchase, Keogh, ESOP |
| Pension arrangements | Defined-benefit and cash-balance plans |
| Health accounts | Health savings account (HSA), health flexible spending arrangement (health FSA) |
| Dependent care | Dependent care assistance program (dependent care FSA) |

Defined-benefit and cash-balance *contributions* are deliberately returned as `indeterminate`;
their funding requires the plan formula, census, assets, actuarial assumptions, and funding
rules. The §415(b)(1)(A) limitation on the annual *benefit* is a different thing — a flat
statutory ceiling published in the same annual notice as the defined-contribution figures,
requiring no actuary — so it is reported alongside that indeterminate contribution status:

```ts
const result = USTaxAdvantagedParams.calculate({
  taxYear: 2026,
  filingStatus: "S",
  persons: [{ id: "t", birthYear: 1970 }],
  accounts: [{ id: "db", ownerId: "t", type: "defined_benefit_plan", employerId: "e" }],
});
result.accounts[0].status;                             // "indeterminate"
result.accounts[0].statutoryMaximumAnnualContribution; // null
result.accounts[0].definedBenefit?.annualBenefitLimit; // 290000
```

| Rule | Treatment |
|---|---|
| §415(b)(1)(A) annual benefit | Reported on `definedBenefit.annualBenefitLimit` for both defined-benefit and cash-balance accounts, with an `info` diagnostic stating it |
| §415(b)(2) and §415(b)(5) adjustments | **Not** applied. The published figure assumes a straight life annuity beginning between ages 62 and 65; adjusting it for another benefit form, another starting age, or fewer than ten years of participation or service is participant-specific |
| Years with no transcribed figure | `null`. The encoded figures are those transcribed from the notices committed under `evidence/retirement-limits/`, which cover 2009, 2010, and 2013 onward. A year outside that set reports `null` rather than a carried-forward or extrapolated amount |
| Contribution and funding | Still `indeterminate`; a benefit ceiling is not a contribution ceiling, and nothing here computes a funding requirement |

## Pension-linked emergency savings accounts (IRC §402A(e))

SECURE 2.0 §127 added §402A(e), effective for plan years beginning after
December 31, 2023. §402A(e)(1)(A)(i) treats a PLESA "for purposes of this title
as a designated Roth account", so its contributions are always Roth.

That is a characteristic of the account rather than an election, so it precedes
the caller's: `planRules.contributionPreference`, `permitsRothContributions` and
`permitsRothCatchUp` are disregarded on a PLESA — with an INFO
`PENSION_LINKED_EMERGENCY_SAVINGS_CONTRIBUTIONS_ARE_ALWAYS_ROTH` saying so —
rather than honoured into a pre-tax contribution the statute leaves no capacity
for. No accepted input produces a pre-tax contribution to a PLESA. On every
other account type, including an ordinary designated Roth 401(k) or 403(b),
those fields keep their ordinary effect: §402A(b)(1) offers the designated Roth
election *in addition to* pre-tax deferrals, so there the split is a plan and
participant choice.

§402A(f)(1) names three plans that may host one, and **which limits apply turns
on the host**, so the third is a distinct account type:

| Host | Account type | Deferral limit | §415(c) |
|---|---|---|---|
| §401(a) trust — §402A(f)(1)(A) | `pension_linked_emergency_savings` | §402(g) | Yes |
| §403(b) plan — §402A(f)(1)(B) | `pension_linked_emergency_savings` | §402(g) | Yes |
| Governmental §457(b) — §402A(f)(1)(C) | `governmental_457b_pension_linked_emergency_savings` | §457(e)(15) | **No** |

For the first two, model the account inside the plan with the same
`annualAdditionsGroupId` as the plan's other accounts. The third shares no pool
with them: §402(g)(3) enumerates elective deferrals exhaustively and lists no
§457(b) deferral, so its contributions run against the §457(e)(15) applicable
dollar amount through §457(b)(2)(A); and §415(a)(1)–(2) enumerates the plans the
annual-additions limit reaches without naming §457(b), so it joins no
annual-additions group at all. One person may hold a PLESA on more than one host
in the same year, and the ceilings then stand side by side rather than as one —
§402A(e)(3)(A) caps "the portion of the account balance", so each account has its
own.

**The §402A(e)(3)(A)(i) figure is a cap on a balance, not an annual allowance.**
The statute bars a contribution "to the extent such contribution would cause the
portion of the account balance attributable to participant contributions to
exceed" the lesser of that figure and an amount the plan sponsor sets. That
portion of the balance carries across years, and §402A(e)(7) — which requires the
plan to permit withdrawal at least monthly — moves it back down. So the balance
must be supplied; the year alone does not determine what is left:

```ts
const result = USTaxAdvantagedParams.calculate({
  taxYear: 2026,
  filingStatus: "S",
  persons: [{ id: "t", compensation: { w2Compensation: 90000 } }],
  accounts: [{
    id: "plesa",
    ownerId: "t",
    type: "pension_linked_emergency_savings",
    employerId: "e",
    planRules: { pensionLinkedEmergencySavingsParticipantContributionBalance: 1000 },
  }],
});
result.accounts[0].statutoryMaximumAnnualContribution; // 1600 — 2600 less the 1000 balance
result.accounts[0].contributionComponents.employeeRothDeferral; // 1600
```

Because the cap is on a balance, a year's gross contributions may exceed it. A
participant who contributed $600, withdrew $400 under §402A(e)(7), and so holds
$200 attributable to participant contributions has $2,400 of room left and may
reach $3,000 for the year — the Department of Labor's PLESA guidance is explicit
that a plan may **not** impose a separate annual PLESA contribution limit,
precisely so the account can be replenished.

| Rule | Treatment |
|---|---|
| §402A(e)(3)(A)(i) dollar figure | `parameters.pensionLinkedEmergencySavingsBalanceCap402A`. $2,500 for 2024 and 2025, $2,600 for 2026 |
| §402A(e)(3)(A)(ii) plan sponsor amount | Supplied as `planRules.planDocumentEmployeeDeferralLimit`; it lowers the contributable amount but not the reported statutory maximum |
| Participant-contribution balance | **Required.** Supplied as `planRules.pensionLinkedEmergencySavingsParticipantContributionBalance`: the portion of the balance attributable to participant contributions **immediately before the proposed allocation** — including amounts contributed earlier in the same year that are still in the account, net of withdrawals under the plan's accounting, excluding earnings. Pass 0 for a new account. Omitted — or supplied as an explicit `null`, which states the absence of the fact in exactly the same way — the account is `indeterminate` with `PENSION_LINKED_EMERGENCY_SAVINGS_PRIOR_BALANCE_REQUIRED` (whose "prior" means *immediately prior to the allocation*, not an opening or prior-year figure) rather than defaulted to an empty account. This differs from the optional §402A(e)(3)(A)(ii) sponsor amount above, where a `null` means the sponsor set none |
| §402(g) and §415(c) | On a §401(a)- or §403(b)-hosted account, base deferrals are consumed like any other elective deferral and annual addition, in the owner's and the employer group's shared pools; a §414(v) catch-up draws the owner's catch-up pool and, per §414(v)(3)(A)(i), not the annual-additions group. A governmental §457(b)-hosted account consumes neither: it draws the owner's §457(e)(15) pool and joins no annual-additions group, whatever `annualAdditionsGroupId` the caller supplies |
| §457(b)(2)(B) includible compensation | Applies to a §457(b)-hosted account, capped at 100 percent of includible compensation like any other deferral under that plan |
| §457(b)(3) last-three-years catch-up | Available on a §457(b)-hosted account, within the balance cap, on the same reasoning as §414(v): it raises "the ceiling set forth in paragraph (2)", a limit on deferrals under the plan, while §402A(e)(3)(A) gates the account balance. §457(e)(18) gives the participant the greater of it and the §414(v) catch-up, never their sum, and that choice is made once for the participant across every eligible plan — see [Choosing between the two §457 catch-ups](#choosing-between-the-two-457-catch-ups). Reported as `special457RothCatchUp`, since a PLESA contribution is Roth whatever limitation supplied its capacity |
| §402A(e)(3)(A) room | An account-local pool, reported in `sharedLimits` as `plesa402Ae3:{accountId}`, seeded with the supplied balance and drawn by base deferrals and catch-up alike |
| Age-based catch-up | Available, within the balance cap. §402A(e)(3)(A) gates a *balance*, while §414(v) relieves a plan- or employee-level *deferral* limit — 26 CFR §1.414(v)-1(b)(1)(i) lists them and none is account-level — so the two compose and both bind. Once the host's §402(g) pool is spent, remaining room may be filled from the §414(v) catch-up; a catch-up is outside §415(c) under §414(v)(3)(A)(i). As elsewhere in this package, capacity follows from age rather than a plan-document election, under the standing assumption that the plan permits and so characterises it. A birth year is required only where a catch-up could reach unfilled room — not where the host's base capacity already covers it, and not on a §457(b) host where the §457(b)(3) catch-up **exceeds** the largest age-based catch-up the year offers at any age, since §414(v)(6)(C) then removes the age-based one whatever the participant's age. An equal §457(b)(3) amount is not enough: §414(v)(6)(C) speaks of a *higher* limitation, so the age still decides which route applies. Where the route is unresolved, no catch-up is allocated under either heading — §457(e)(18) chooses between pools that are reported separately, so a figure known to be reachable one way or the other is still not attributable to either |
| Employer contributions | Never allocated here. §402A(e)(6)(A) directs any match earned on PLESA contributions to the participant's *other* account under the plan, and §402A(e)(8)(B) bars transfers in |
| 2023 and earlier | `unavailable`, on every host. Pub. L. 117-328 §127(g) applies §127 to plan years beginning after December 31, 2023 |

The 2024 figure comes from the Code rather than from a notice: Notice 2023-75
does not state one, and the flush text of §402A(e)(3)(A) adjusts the $2,500 only
"[i]n the case of contributions made in taxable years beginning after December
31, 2024", leaving the first effective year on the unadjusted statutory amount.

## Health savings accounts (IRC §223)

HSA contribution capacity is calculated from caller-supplied coverage facts. Whether a
person is an eligible individual under §223(c)(1) — including Medicare entitlement under
§223(b)(7) — is an input, not something the engine infers.

| Rule | Treatment |
|---|---|
| §223(b)(2) monthly limitation | The limit is the sum of the monthly amounts divided by 12, so partial-year eligibility prorates by month of coverage |
| §223(b)(3) age-55 additional amount | Per spouse and **not** shareable; each spouse's catch-up must be contributed to that spouse's own HSA |
| §223(b)(5) family coverage | Spouses share a single family limit, divided equally or as agreed. Only the family-months portion is divided; self-only months stay with the individual |
| §223(b)(5)(B)(ii) agreed division | An agreed division must exhaust the limitation. Shares that total more or less than 1 are both reported as errors and return `indeterminate` (see below) |
| §223(b)(5)(A) | If either spouse has family coverage, both are treated as having family coverage for those months — whether or not that spouse owns an HSA (see below) |
| §223(b)(8) last-month rule | Eligible on December 1 allows the full annual amount, creating a 13-month testing period obligation |
| Testing-period failure | The attributable amount is included in income in the following year and carries a 10% additional tax, unless failure is by death or disability |
| Pre-2007 years | §223(b)(2) capped the monthly limitation at 1/12 of the *lesser* of the plan's annual deductible and the dollar amount, until the Tax Relief and Health Care Act of 2006 §303 removed it |
| §106(d) employer contributions | Excluded from income rather than deducted, reducing W-2 box 1 and FICA wages and reducing the §223(b)(4)(B) deduction |
| §223(b)(4)(A) Archer MSA reduction | The aggregate amount paid for the year to that individual's Archer MSAs reduces the whole subsection (b) limitation — the §223(b)(3) increase included — but not below zero |
| §223(b)(5)(B)(i) Archer MSA reduction | For a married individual to whom §223(b)(5) applies, both spouses' aggregate reduces the single family limitation **before** §223(b)(5)(B)(ii) divides it, and never touches the §223(b)(3) amount |
| §223(b)(4)(C) qualified HSA funding distribution | The amount contributed under §408(d)(9) reduces that individual's own subsection (b) limitation — the §223(b)(3) increase included — but not below zero. It is never withdrawn by the flush text and never taken before the §223(b)(5)(B)(ii) division |

The testing period spans two tax years, so a caller who has not yet resolved it receives
an explicit obligation in the result rather than an assumed outcome.

### Spousal coverage: `persons[].hsaCoverage`

§223(b)(5)(A) turns on whether **either spouse has family coverage**, not on whether either
spouse owns a health savings account. A spouse with family HDHP coverage and no HSA of their
own still changes the other spouse's limitation, so that coverage is stated on the person:

```ts
const result = USTaxAdvantagedParams.forTaxYear(2026)
  .filingStatus(FilingStatus.MARRIED_FILING_JOINTLY)
  .taxpayer("taxpayer", (person) => person.bornIn(1985))
  // The spouse has family HDHP coverage but no HSA of their own.
  .spouse("spouse", (person) => person.bornIn(1986).hsaCoverage("family"))
  .account("taxpayer-hsa", "taxpayer", AccountType.HSA, (account) => {
    account.hsaCoverage("self_only");
  })
  .calculate();
```

`persons[].hsaCoverage` takes the same coverage fields as `planRules.hsa`
(`coverageTier`, `eligibleMonths`, `monthlyCoverage`, `hdhpAnnualDeductible`). Where a person
owns an HSA, `planRules.hsa` already carries these facts; supplying both is allowed but they
must be identical, and a contradiction returns
`HSA_PERSON_AND_ACCOUNT_COVERAGE_FACTS_CONFLICT`.

**Supplying the key at all declares the fact known.** An empty object —
`{ id: "s", hsaCoverage: {} }`, or `.noHsaCoverage()` on the builder — records that the
spouse held no high deductible health plan coverage in any month.

### Archer MSA contributions: `persons[].archerMsaContributions`

§223(b)(4)(A) reduces the §223(b) limitation by "the aggregate amount paid for such taxable
year to Archer MSAs of such individual", and §223(b)(5)(B)(i) reduces the single family
limitation by "the aggregate amount paid to Archer MSAs of such spouses". Both take an
amount **paid**, not a §220 limitation, so the amount is a caller-supplied fact in the same
way eligible-individual status is, and no part of §220 is modelled or checked against it.

```ts
const result = USTaxAdvantagedParams.forTaxYear(2026)
  .filingStatus(FilingStatus.SINGLE)
  .taxpayer("taxpayer", (person) => person.bornIn(1986).archerMsaContributions(1200))
  .account("taxpayer-hsa", "taxpayer", AccountType.HSA, (account) => {
    account.hsaCoverage("self_only");
  })
  .calculate();
// 4400 - 1200 = 3200
```

Because §223(b)(5)(B)(i) reads the *couple's* aggregate, the amount belongs to the person
rather than to an account: a spouse who owns no HSA can still carry one, and it still
reduces the limitation the other spouse divides.

**The ordering is not cosmetic.** The §223(b)(4) flush text says "Subparagraph (A) shall not
apply with respect to any individual to whom paragraph (5) applies", so a married individual
with family coverage is reduced under §223(b)(5)(B)(i) — before the equal division, not
after. Two spouses with a 2026 family limitation of 8750 and 3000 of aggregate Archer
contributions get (8750 − 3000) ÷ 2 = 2875 each, not 4375 − 3000 = 1375 each, which would
subtract the aggregate twice. §223(b)(5)(B) also operates "without regard to any additional
contribution amount under paragraph (3)", so a married individual's age-55 amount survives a
reduction that would have consumed it under §223(b)(4)(A).

Each account's `hsa` detail reports `archerMsaContributionsApplied`,
`archerMsaReductionPrecedesFamilyDivision`, and `archerMsaLimitReduction`, and an
`HSA_ARCHER_MSA_CONTRIBUTIONS_REDUCE_LIMIT` diagnostic names the paragraph that applied.

### Qualified HSA funding distributions: `persons[].qualifiedHsaFundingDistributions`

§223(b)(4)(C) reduces the §223(b) limitation by "the aggregate amount contributed to health
savings accounts of such individual for such taxable year under section 408(d)(9)" — a
once-in-a-lifetime IRA-to-HSA rollover. Like the Archer amount it is a fact about the
person, taken as supplied: the §408(d)(9)(C) once-per-lifetime limitation and the separate
§408(d)(9)(D) testing period are **not** modelled, and the amount is not checked against the
IRA it came from.

```ts
const result = USTaxAdvantagedParams.forTaxYear(2026)
  .filingStatus(FilingStatus.SINGLE)
  .taxpayer("taxpayer", (person) => person.bornIn(1986).qualifiedHsaFundingDistributions(1500))
  .account("taxpayer-hsa", "taxpayer", AccountType.HSA, (account) => {
    account.hsaCoverage("self_only");
  })
  .calculate();
// 4400 - 1500 = 2900
```

**It behaves the opposite way to the Archer reduction for a married couple, and the flush
text is why.** "Subparagraph (A) shall not apply with respect to any individual to whom
paragraph (5) applies" names subparagraph (A) alone, and §223(b)(5)(B)(i) reduces the family
limitation only by the Archer amount, so nothing routes (C) through paragraph (5). It stays
an amount of "such individual" reducing the limitation applying to that individual under
subsection (b) — which for a married spouse is the share left by the §223(b)(5)(B)(ii)
division, **after** the division rather than before it, plus their own §223(b)(3) amount.

Two spouses with the 2026 family limitation of 8750 divide it to 4375 each. A $2,000
rollover by one spouse leaves 2375 and 4375; the same $2,000 paid to an Archer MSA instead
leaves 3375 and 3375, because that reduction comes off the 8750 first. And since (C) is not
governed by §223(b)(5)(B)'s "without regard to any additional contribution amount under
paragraph (3)", it reaches a married individual's age-55 amount where the Archer reduction
cannot.

When both reductions apply, §223(b)(4) reduces by "the sum of" them but not below zero. The
fall is attributed in subparagraph order, so `archerMsaLimitReduction` is reported in full
and `qualifiedHsaFundingLimitReduction` reports only what was left for (C) to reach. Each
account's `hsa` detail reports `qualifiedHsaFundingDistributionsApplied` and
`qualifiedHsaFundingLimitReduction`, and an
`HSA_QUALIFIED_HSA_FUNDING_DISTRIBUTION_REDUCES_LIMIT` diagnostic states which ordering
applied.

### The division is one fact about the couple

**Breaking change in 0.5.0.** Four fields moved off `planRules.hsa`. An account that still
carries one is rejected rather than read, so a stale call fails loudly instead of quietly
using half of what it stated:

| Removed from `planRules.hsa` | Now | Error code if still supplied |
|---|---|---|
| `familyLimitShare` | `hsaFamilyLimitDivision` on the **scenario** | `HSA_ACCOUNT_LEVEL_FAMILY_LIMIT_SHARE_REMOVED` |
| `useLastMonthRule` | `persons[].hsaLastMonthRule.useLastMonthRule` | `HSA_ACCOUNT_LEVEL_LAST_MONTH_RULE_REMOVED` |
| `testingPeriodSatisfied` | `persons[].hsaLastMonthRule.testingPeriodSatisfied` | `HSA_ACCOUNT_LEVEL_LAST_MONTH_RULE_REMOVED` |
| `testingPeriodFailureByDeathOrDisability` | `persons[].hsaLastMonthRule.testingPeriodFailureByDeathOrDisability` | `HSA_ACCOUNT_LEVEL_LAST_MONTH_RULE_REMOVED` |

None of the four was ever a fact about an account. §223(b)(5)(B)(ii) divides the limitation
between "them" — the married individuals — and §223(b)(8)(A) makes the last-month election for
"an individual". An owner's two HSAs cannot disagree about either, and Pub. 969 is explicit
that multiple HSAs do not subdivide their owner's maximum: "If you have more than one HSA in
2005, your total contributions to all the HSAs cannot be more than the limits discussed
earlier."

`hsaFamilyLimitDivision` is one statement, on the scenario:

```ts
{ status: "statutory_equal" }                  // the default; omit it for the same effect
{ status: "agreed", taxpayerShare: 0.25 }      // the spouse takes the remaining 0.75
{ status: "unknown" }                          // whether they agreed anything is not known
{ status: "disputed" }                         // the spouses report different divisions
{ status: "inconsistent" }                     // two records of one division conflict
```

`taxpayerShare` is the share belonging to the person whose `role` is `taxpayer`; the spouse
takes `1 - taxpayerShare`. Because there is one number rather than one per account, shares
can no longer fail to total 1, and the three diagnostics that policed that
(`HSA_FAMILY_LIMIT_SHARES_EXCEED_ONE`, `HSA_FAMILY_LIMIT_SHARES_BELOW_ONE`,
`HSA_FAMILY_LIMIT_SHARE_REQUIRED_FOR_BOTH_SPOUSES`) are gone with the states they described.

0 and 1 are both valid. Notice 2004-50 Q&A-32: spouses "can divide the annual HSA contribution
in any way they want, **including allocating nothing to one spouse**". The spouse allocated
nothing still keeps their own §223(b)(3) age-55 amount — Notice 2008-59 Q&A-22 holds that an
individual eligible for the catch-up "may only make such contributions to his or her own HSA",
and §223(b)(5)(B) divides the limitation "without regard to any additional contribution amount
under paragraph (3)", so a division cannot reach it either way.

**Omitting the field means `statutory_equal`, not unknown.** The statute divides equally
"unless they agree on a different division", so silence is the default rule rather than a
missing fact, and the Instructions for Form 8889 say the same. The three non-numeric statuses
exist because failing to establish the agreement is not the same input state as establishing
its absence: contradictory records are equally consistent with "they agreed equally and one is
wrong" and "they agreed 25/75 and the other is wrong", and defaulting those to 50/50 would
overstate one spouse's limitation in the second case. `unknown`, `disputed` and `inconsistent`
differ only in the wording of the diagnostic they produce; they are deliberately identical in
effect, and should stay that way.

**A spouse who owns the only HSA still gets half.** §223(b)(5)(B)(ii) divides "equally between
them", and *them* is "individuals who are married to each other" from the opening clause of
paragraph (5) — a phrase about a marriage, not about a pair of accounts. Owning an HSA is not a
condition of being an eligible individual under §223(c)(1), so a spouse with family coverage and
no account still holds their half and simply has nowhere to put it. A sole owner therefore takes
$4,375 of an $8,750 limitation, reports `HSA_SOLE_SPOUSE_ACCOUNT_TAKES_ONLY_ITS_EQUAL_SHARE`, and
reaches the whole $8,750 only through an agreement:

```ts
{ status: "agreed", taxpayerShare: 1 }   // Notice 2004-50 Q&A-32 permits exactly this
```

This changed in 0.5.0. Before it, an account-level model could not express a division at all, so
the engine assumed a sole owner had agreed to take everything and reported
`HSA_SOLE_SPOUSE_ACCOUNT_ASSUMED_FULL_FAMILY_LIMIT` with a `determinate_with_assumptions` status.
Now that the division can be *stated*, assuming one would override a caller who has said in so
many words that no different division was agreed. The status is `determinate`, because applying
the statutory default is not an assumption. **If you relied on the old behaviour, state the
agreement explicitly.**

**An unknown division of nothing is still determinate.** The division is only ever a fact about
something: where the limitation left after the §223(b)(5)(B)(i) Archer reduction is zero, every
division of it is the same division, so an unsettled status changes no account's answer and
nulls nothing. The same principle applies one level down — an eligibility doubt about a spouse
whose agreed share is already exactly `0` stands aside, because that spouse gets nothing whether
they are an eligible individual or not. Both rules exist because an unknown that cannot change
an answer is not worth withholding an answer for.

Two shapes are rejected outright, because each states an agreement and denies it in the same
object: `{ status: "agreed" }` with no share raises
`HSA_FAMILY_LIMIT_DIVISION_SHARE_REQUIRED`, and a `taxpayerShare` beside any other status
raises `HSA_FAMILY_LIMIT_DIVISION_SHARE_NOT_PERMITTED`.

### A known ceiling with an unknown draw

A shared limit can be a number while how much of it is already spent is not. The §223(b)(5) pool is
where this arises: existing contributions consume the §223(b)(1) limitation before reaching the
§223(b)(3) additional amount, so once any additional amount exists, how much of a contribution landed
on the couple's pool depends on the size of that spouse's share — which is exactly what an unresolved
§223(b)(5)(B)(ii) division leaves unknown. The §223(b)(4) reductions raise the same question, coming
off the paragraph (1) share first.

In that case the entry reports its `limit` and nulls `usedBeforeAccount`, `usedByAccount` and
`remainingAfterAccount`, and **no excess is diagnosed against it** — an excess is a statement about the
draw. The engine does not publish a bound in place of a usage: bounding upwards accused compliant
taxpayers of excess contributions, and bounding downwards reported a pool as untouched when a
qualified HSA funding distribution had consumed nearly all of it.

The `familyLimitShare` reported in each account's `hsa` detail is `null` under the same
condition. It is an output — the share this account actually got — and it stays `null` rather
than reporting a share nobody has established.

### An unknown division does not make the limitation unknown

§223(b)(5) settles two things, and they fail separately. Subparagraph (A) fixes **one family
limitation** for the couple; (B)(ii) **divides** it between them. A disagreement about the
division — an `hsaFamilyLimitDivision` status of `unknown`, `disputed` or `inconsistent` —
reaches only the second. Subparagraph (A) has already fixed the amount from coverage facts by the time (B)(ii)
is reached, so the couple's ceiling is still a number even though nobody can say whose it is.

The engine reports the two separately:

| Unknown | Diagnostic | `hsa223b5` shared limit | Account maximum |
|---|---|---|---|
| The amount — coverage or a 2004–2006 annual deductible | `HSA_SHARED_FAMILY_LIMIT_INDETERMINATE` | `null` | `null` |
| The division — an unsettled `hsaFamilyLimitDivision`, or an impeached eligibility assertion | `HSA_FAMILY_LIMIT_DIVISION_INDETERMINATE` | the limitation | `null` |

Both are `ERROR` and both leave every account's `statutoryMaximumAnnualContribution` null: a
share of a known amount is still unknown when the share is. What differs is the couple-wide
figure. `sharedFamilyContributionLimit` follows the **amount**, because that is its contract —
the limitation this owner divides, reported before the share is applied — so a caller settling
an unsettled division can still see the 8750 they are dividing.

### When the other spouse's coverage is required

§223(b)(5)(A) does two things, and each makes the other spouse's coverage matter in a
different case. On a married return, an owner with no stated spousal coverage returns
`indeterminate` with `HSA_SPOUSE_COVERAGE_FACTS_REQUIRED` — rather than a number the input
cannot support — whenever either applies:

| Sentence | Bites when the owner has | Years |
|---|---|---|
| Both spouses treated as having family coverage if either does | at least one **self-only** month, which it can raise | all |
| Spouses with family coverage under different plans take the **lowest** annual deductible | at least one **family** month, whose deductible it can lower | 2004–2006 only |

The first can only ever raise a self-only month to a family month, so an owner whose months
are all family months is unaffected by it — family is already the higher tier. The second is
why that owner is still not safe in 2004–2006: §223(b)(2) capped each month by the plan's
annual deductible in those years, and an unstated spouse may hold a family plan with a lower
one, which would make the couple's limitation *lower* than the owner's own plan produces.
Section 303 of the Tax Relief and Health Care Act of 2006 struck that comparison for years
after 2006, so from 2007 an unstated spouse's deductible cannot move any amount.

Absence is not an assertion. If the spouse genuinely held no HDHP coverage, say so with
`persons[].hsaCoverage: {}` — the documented way to record exactly that — and the limitation
stays determinate. The engine will not read silence as "no competing family plan", because
that would answer the comparison from a fact you never supplied, and in the direction that
costs a taxpayer the §4973 excise.

### A deductible below the statutory minimum is inconsistent input

`hdhpAnnualDeductible` is taken as stated, but it is checked for internal consistency. A figure
below the §223(c)(2)(A)(i) minimum for a tier you also state the person held returns
`indeterminate` with `HSA_HDHP_DEDUCTIBLE_BELOW_STATUTORY_MINIMUM`, in **every** year — not
only the 2004–2006 years where §223(b)(2) read the deductible into the arithmetic.

This is not the engine testing whether your plan is a high deductible health plan; it still
does not do that, and clearing the minimum proves nothing. The test is one-way: falling below
the minimum disproves your own claim that the field holds a qualifying plan's deductible.

What the engine deliberately does **not** do is as important:

| It does not | Because |
|---|---|
| raise the figure to the minimum | Notice 2004-50 Q&A-31 Example (4) does not treat a subminimum plan as if it met the floor |
| publish it as a lower ceiling | the same example makes the consequence an *eligibility* one, not a smaller limitation |
| return `ineligible` | Rev. Rul. 2005-25 makes that turn on whom the plan covers, and no input here carries that fact |

The tier decides reach, and it decides it for the **amount** only. A spouse's subminimum
**family** plan reaches the HSA owner's limitation, because §223(b)(5)(A) draws competing family
plans into the lowest-deductible comparison; it reaches only the months that plan was in force,
since that comparison is answered per month. A spouse's subminimum **self-only** plan never
enters it — Notice 2004-50 Q&A-31 Example (1) leaves the owner contributing the full family
amount in exactly that case.

The **division** is a separate question with a different answer, and any tier reaches it. Q&A-31
divides the limitation only between spouses who are each an eligible individual: "if only one
spouse is an eligible individual, only that spouse may contribute to an HSA". This engine reads
your month list as the assertion of eligibility, so a deductible contradicting that list
impeaches it. Where the contradicting spouse **owns an HSA**, the engine therefore cannot tell
whether the limitation is wholly the other spouse's — as in Example (1) — or divided, so
`familyLimitShare` and both maximums go null while the §223(b)(5) pool keeps reporting the
amount. Do not expect the full family maximum in that case; expect nothing, and a diagnostic
saying why. Where that spouse owns no HSA there is no division to doubt and the owner takes the
whole limitation.

Encoded HSA parameters are verified against the Revenue Procedure that published them —
see [`evidence/hsa-limits/`](evidence/hsa-limits/).

## Health flexible spending arrangements (IRC §125(i))

The §125(i) ceiling on salary reduction contributions is calculated from caller-supplied
plan facts. Plan design is not inferred: this engine cannot read a plan document, so
whether the plan offers a carryover or a grace period, whether employer flex credits could
be elected as cash, and the arrangement's Rev. Rul. 2004-45 purpose are all inputs.

| Rule | Treatment |
|---|---|
| §125(i)(1) salary-reduction limit | The indexed dollar limitation, applied per employee per employer |
| Years before 2013 | §125(i) did not exist, so there was **no statutory ceiling at all** — only whatever the plan document imposed. The result is `indeterminate` with a null limit, not a fabricated one and not `unavailable`: the account existed, the limit did not |
| Notice 2013-71 carryover | The carried amount is the lesser of the prior year's unused amount and **that year's** cap. The rest is forfeited |
| Carryover does not reduce the limit | Notice 2013-71: the carryover "does not count against or otherwise affect" the §125(i) limit, so it sits on top of the receiving year's ceiling |
| Carryover **or** grace period, never both | Notice 2013-71 forbids the combination. Asserting both describes a plan that cannot exist, so the result is `indeterminate` with an `ERROR` |
| Neither offered | The whole unused amount is forfeited under the use-or-lose rule, and the forfeiture is reported rather than dropped |
| Employer flex credits | Outside §125(i), which reaches salary reduction contributions alone — **unless** the employee could have elected them as cash or another taxable benefit, in which case Notice 2012-40 treats them as salary reduction contributions and they consume the limit |
| Election above the limit | An `ERROR`, never silent truncation. Notice 2012-40 holds that a plan permitting a higher election is not a §125 cafeteria plan at all, so truncating would report a smaller consequence than the statute produces |
| Per employee per employer | Notice 2012-40: two unrelated employers carry two full limits; arrangements sharing an `employerId` share one, which is how §125(g)(4) controlled-group aggregation is expressed |
| Spouses | Each spouse carries a full limit, even in the same plan of the same employer. This is the deliberate contrast with §129, which is per **return** |
| §125(a) exclusion, not a deduction | A salary reduction never enters gross income, so it reduces W-2 box 1 and FICA wages and contributes **nothing** to `federalAgiReduction` |

### The carryover cap belongs to the year the money came from

Notice 2013-71 created the carryover at a fixed $500 and Notice 2020-33 raised it to 20
percent of the §125(i) limit "for that plan year". Both phrase it as the maximum unused
amount **from** a plan year carried to the immediately following one, so
`carryoverLimitForPriorYear` is the figure that governs an amount arriving this year, and
`carryoverLimitForThisYear` is what may leave at the end of it. Reading the cap off the
receiving year is the natural mistake and gives a different number in every year the limit
moved.

### Plan year versus tax year

Notice 2012-40 §III holds that "taxable year" in §125(i) means the **plan year** of the
cafeteria plan, and prorates a short plan year by its months. Every annual Revenue
Procedure nonetheless publishes the figure "for taxable years beginning in" the year, and
this package is keyed by tax year throughout, so the two agree exactly for a calendar-year
plan — which is the ordinary case and the default here.

For a non-calendar plan year the governing figure depends on the plan year start date,
which the engine does not hold. Supplying `planYearIsCalendarYear: false` therefore returns
`indeterminate` with an `ERROR` rather than quietly applying the calendar-year figure. Key
the scenario to the tax year in which the plan year begins if you want that year's number.

### COVID-era relief is disclosed, not modelled

§214 of the Consolidated Appropriations Act, 2021 (Notice 2021-15) let a plan carry over
**all** unused amounts from plan years ending in 2020 and 2021, and let a dependent care
program carry over at all, which it otherwise may not. Adopting it was entirely a plan
option. The engine applies the ordinary cap and attaches
`HEALTH_FSA_SECTION_214_RELIEF_NOT_MODELLED` whenever a carryover out of 2020 or 2021 is
computed, so a plan that adopted the relief is visibly under-reported rather than silently
so.

### A bare `FSA` is rejected

`health_fsa`, `healthcare_fsa`, `medical_fsa`, and
`health_flexible_spending_arrangement` all resolve. `FSA` alone does not: it names a
health FSA and a dependent care FSA equally well, and the two carry different limits and
different household aggregation, so it raises `INVALID_ACCOUNT_TYPE` with a message naming
both spellings rather than silently picking one.

Encoded §125 and §129 parameters are verified against the documents that published them —
see [`evidence/fsa-limits/`](evidence/fsa-limits/).

## Dependent care assistance (IRC §129)

§129(a)(2)(A) is a **per-return** amount, which is the single most important
difference from §125(i). Two spouses filing jointly do not get one each.

| Rule | Treatment |
|---|---|
| §129(a)(2)(A) exclusion | Not inflation-adjusted, so it appears in no Revenue Procedure and is cited to the Code. Each year is encoded as its own row, so the 2021 increase and its reversion are both data rather than a rule |
| Married filing separately | The statutory parenthetical amount. Separate returns mean each spouse carries their own halved amount rather than dividing one |
| **2021 only** | ARPA §9632 substituted "$10,500 (half such dollar amount" for taxable years beginning after 2020 and before 2022 — enacted in March 2021, so Rev. Proc. 2020-45 could not carry it |
| **2026 onward** | Pub. L. 119-21 §70404 struck `$5,000 ($2,500` and inserted `$7,500 ($3,750` for taxable years beginning after December 31, 2025. A fixed-dollar substitution: the amount changed, the absence of indexing did not |
| Household sharing | Spouses filing jointly draw on one pool, reported through `sharedLimits` so the constraint is visible. Assistance above it is `includibleInIncome` under §129(a)(2)(B), not silently dropped |
| §129(b)(1) earned income | Applied whenever the caller supplies the figures: the employee's earned income, or for a married employee the lesser of theirs and their spouse's. Absent, the ceiling is the §129(a)(2)(A) amount alone and a `WARNING` says the limitation was not applied |
| Years before 1987 | §129 existed from 1982 but carried **no dollar ceiling** until the Tax Reform Act of 1986 §1163. Those years are `indeterminate` with a null limit; 1981 and earlier, when §129 did not exist at all, are `unavailable` with a zero |
| §129(a)(1) exclusion | Reduces W-2 box 1 and FICA wages and contributes nothing to `federalAgiReduction`, exactly as the §125 and §106(d) exclusions do |

### Why the earned income limitation is here at all

The package's boundary is that it does not *derive* income, not that it ignores
supplied facts. §129(b)(1) is a hard statutory ceiling, so leaving it out
entirely would over-report the exclusion for exactly the taxpayers it was
written for. Both figures are caller-supplied, like every other fact here.

**§129(b)(2) deeming is not modelled.** For a spouse who is a student or
incapable of self-care, §129(b)(2) applies the §21(d)(2) monthly schedule. That
schedule is not encoded, because no primary source for it is committed to this
package's evidence corpus and an unattested figure is never encoded. Asserting
`isStudentOrIncapableOfSelfCare` on the person records that the
`dependentCareEarnedIncome` supplied for them is the deemed amount, and emits a
diagnostic saying the schedule is not applied for you.

**The §129(b)(1) facts live on the person, not the program.** The limitation is
one figure for the return — the employee's own earned income, or for a married
employee the lesser of theirs and their spouse's — so `dependentCareEarnedIncome`
is a `PersonInput` field. While it sat on each account's plan rules, two
dependent care programs on one return could state it differently and the engine
had to report the contradiction as an error; putting it on the person removes the
possibility instead of diagnosing it.

## The §125 / §223 interaction: diagnose, do not enforce

A general-purpose health FSA and an HSA cannot both be right. The engine says
so and **returns the §223 figures the inputs imply, unchanged**.

| Health FSA `purpose` | Effect on the HSA in the same scenario |
|---|---|
| `general_purpose` | `ERROR` `HEALTH_FSA_DISQUALIFIES_HSA_ELIGIBILITY` citing §223(c)(1)(A)(ii) and Rev. Rul. 2004-45. **Every §223(b) figure is unchanged** — the limitation, the prorated amount, the components, the totals |
| `general_purpose` held by the **spouse** | `ERROR` `SPOUSE_HEALTH_FSA_DISQUALIFIES_HSA_ELIGIBILITY`. Rev. Rul. 2004-45 says the result is the same where the arrangement is sponsored by the spouse's employer, because it can reimburse this individual's expenses. Figures again unchanged |
| `limited_purpose` or `post_deductible` | No conflict. An `INFO` records that the arrangement was treated as HSA-compatible |
| **absent** | `ERROR` `HEALTH_FSA_PURPOSE_REQUIRED_FOR_HSA_INTERACTION`, and the §223 limitation is **`indeterminate`** |

The last row is the one that differs, and deliberately. With a stated
`general_purpose` the conflict is *known*, and reporting the caller's own
figures is the whole point: eligible-individual status is caller-supplied
everywhere in this engine, so someone who ended the arrangement mid-year and
supplied the correct eligible months must still get the answer their facts
imply. With the purpose *unstated* nothing about §223 is known — the two
classifications give opposite answers — so a confident number would be the
defect rather than the diagnostic.

Two consequences worth stating:

- **A carryover of general-purpose funds disqualifies the whole receiving plan
  year.** Notice 2013-71 makes the carried amount available for expenses
  incurred during the entire plan year it is carried to, so it is
  general-purpose coverage for that year and not merely until it is spent.
- **A grace period extends the disqualification into the following plan year.**
  Notice 2005-86: coverage during the grace period blocks eligibility until the
  first day of the month after it ends, even at a zero balance. Those months
  fall outside the year being calculated, so it is reported as `INFO` rather
  than folded into the month list.

The account's reported `status` still becomes `indeterminate` when an `ERROR` is
attached — that is the engine's uniform rule, not an enforcement of §223. What
"diagnose, do not enforce" means here is that **no number moves**.

A dependent care FSA never raises this: §129 assistance reimburses dependent
care rather than §213(d) medical expenses, so it is not coverage §223(c)(1)(A)(ii)
reaches.

## Multiple employers

Statutory pools are keyed to match the statute rather than to the taxpayer uniformly:

- **§402(g)(1) elective deferrals** aggregate **per person** across every employer.
- **§415(c) annual additions** apply **per employer**, so unrelated employers carry
  independent limits. Set `annualAdditionsGroupId` on the plan rules to aggregate plans
  of a controlled or affiliated service group under §414(b)/(c)/(m)/(o) and §415(h).
- **§414(v)(7)(A)** Roth catch-up classification tests prior-year FICA wages from the
  **sponsoring employer**, supplied through `priorYearFicaWages(employerId, amount)`.
  The figure is required only where the test can change the answer. §414(v)(7)(A) does
  two things and no more: it makes a catch-up that would have been pre-tax into a
  designated Roth contribution, and — because it allows the catch-up "only if" the
  contribution is a designated Roth one — it withdraws the catch-up from a plan whose
  terms do not offer one. On an account whose employee contributions are designated Roth
  already and whose rules permit a Roth catch-up, neither is possible and the wages are
  not asked for. They **are** asked for on a pre-tax account, on a designated Roth
  account carrying `contributionPreference: "pretax_first"` (which makes the default
  pre-tax, so there is Roth treatment left to force), and on one carrying
  `permitsRothCatchUp: false` (where the catch-up survives below the threshold and
  disappears above it). §402A(e)(1)(A)(i) settles both halves for a pension-linked
  emergency savings account, so one never needs the figure.

  The exemption covers only the catch-up the engine would itself classify. An
  `existingContributions.employeePreTaxCatchUp` the caller reports is a completed
  contribution whose validity §414(v)(7)(A) decides — it stands below the threshold
  and was not a permitted additional elective deferral above it — so an account
  carrying one asks for the wages whatever its Roth character. An existing *Roth*
  catch-up raises no such question and does not. The §402(g)(7) and §457(b)(3)
  special catch-ups are separate provisions that §414(v)(7)(A) does not reach, so
  neither is read here.

Whether two employers are a single employer for §415 is a legal determination about
ownership, so it is a caller-supplied fact rather than something inferred from the inputs.

## Result semantics

| Field | Meaning |
|---|---|
| `statutoryMaximumAnnualContribution` | Overall monetary legal ceiling when determinable from encoded law and supplied facts. Restrictions the plan document imposes are **not** folded in — they lower `maximumAnnualContributionBasedOnInputs` instead — so a §457(b) plan writing a deferral limit below the §457(e)(15) amount, or a PLESA sponsor setting a §402A(e)(3)(A)(ii) amount below the published figure, lowers what may be contributed without lowering this field |
| `maximumAnnualContributionBasedOnInputs` | Maximum supported by law and supplied plan capabilities/formulas |
| `maximumAdditionalContributionBasedOnInputs` | Remaining supported amount after existing contributions |
| `existingAnnualContribution` | Existing contribution components supplied by the caller |
| `excessContribution` | Supplied amount above the account's determinable statutory ceiling; `null` when that ceiling is indeterminate |
| `planTermDependentCapacity` | Potential space that cannot be allocated without additional plan/employer facts |
| `contributionComponents` | Pretax, Roth, after-tax, employer, IRA, and catch-up components. The statutory source of a catch-up and its tax treatment are independent, so both are recorded: a §457(b)(3) last-three-years catch-up is `special457CatchUp` when pre-tax and `special457RothCatchUp` when made to a designated Roth account — including any PLESA, where §402A(e)(1)(A)(i) makes Roth the only possibility. Both seed the same §457(b)(3) pool when handed back as an existing contribution |
| `federalTaxEffects` | Federal AGI, taxable-income, W-2 box 1, nondeductible, after-tax/Roth, and conversion effects |
| `sharedLimits` | Audit trail showing each statutory pool used by the account. Each entry has three states, not two: `limit` is `null` where the statute's ceiling could not be determined, and `usedBeforeAccount` / `usedByAccount` / `remainingAfterAccount` are `null` where the ceiling **is** known but the draw against it is not. Read the usage fields rather than inferring a draw of zero |
| `diagnostics` | Assumptions, warnings, unavailable rules, and legal references |

`maximumAnnualContributionBasedOnInputs` is a mechanical result, not a contribution recommendation.

## Shared-limit allocation

Accounts are allocated in ascending `priority` and then input order. This makes overlapping limits deterministic.

The engine tracks, among other pools:

- Traditional and Roth IRA contributions per owner.
- Joint-return compensation available for spousal IRAs.
- The owner-level §402(g) elective-deferral limit across applicable 401(k), 403(b), TSP, SARSEP, and SIMPLE sources.
- The owner-level §414(v) age-based catch-up pool.
- A separate §457(b) limit, drawn on by every §457(b) account including a §402A(f)(1)(C)-hosted PLESA.
- §415(c) annual additions per participant and controlled-employer group.
- The owner-level 403(b) 15-years-of-service catch-up pool.
- The 457(b) last-three-years special catch-up.

Use the same `annualAdditionsGroupId` for plans that share one §415(c) controlled-employer limit. Unrelated employers should normally use different group IDs.

## Recognized compensation under §401(a)(17)

When a caller supplies an employer contribution **rate**, the engine first limits plan compensation to the applicable annual recognized-compensation ceiling and then applies the rate. This applies to:

- Employer nonelective formulas.
- Employer matching formulas whose matchable compensation is expressed as a fraction of compensation.
- Common-law employee SEP formulas.
- The plan-rate side of self-employed SEP and qualified-plan formulas.

For a self-employed owner, the maximum percentage contribution is the lesser of:

1. net earnings after the deductible half of self-employment tax multiplied by the reduced self-employed rate; and
2. recognized compensation multiplied by the unreduced plan contribution rate.

The result remains subject to §415(c), plan-document limits, and existing annual additions.

The compensation ceiling is **not** imposed as an extra dollar cap that prematurely stops an employee’s otherwise valid §402(g) elective deferral. Employee deferrals remain subject to actual compensation, §402(g), catch-up rules, shared pools, and plan terms.

SIMPLE formulas preserve their distinct treatment: the ordinary 3% matching method is based on compensation and deferrals, while the 2% nonelective method and applicable additional nonelective contribution use recognized compensation.

Supplying `expectedEmployerContribution` bypasses formula inference because it represents a known caller-provided employer amount. The amount is still constrained by applicable annual-additions and plan-document ceilings.

## IRA phase-outs and spousal IRAs

The package models:

- The combined traditional/Roth IRA annual contribution limit.
- Age-50 IRA catch-up amounts.
- Roth IRA MAGI phase-outs.
- Traditional IRA active-participant deduction phase-outs.
- The separate phase-out for a noncovered spouse married to a covered participant.
- Married-filing-separately rules, including whether spouses lived together during the year.
- MFJ spousal-IRA compensation sharing.
- Historical one-earner spousal limits.
- The pre-2020 traditional-IRA age-70½ contribution restriction.
- Nondeductible traditional IRA capacity when a deduction is unavailable.
- IRS worksheet-style phase-out rounding and the positive reduced minimum.

Supply the MAGI value applicable to each calculation. The engine does not derive tax-return MAGI from raw income items.

## Catch-up contributions and birth data

Age is generally determined at the end of the tax year. `bornIn(year)` is sufficient for ordinary age-50 and age-60-to-63 catch-up rules; `bornOn(YYYY-MM-DD)` is preferred for legacy age-70½ edge cases.

There is no general pre-1960/post-1960 retirement-account contribution-limit split. The 1960 boundary is primarily associated with Social Security full retirement age, not these contribution limits.

Supported catch-up logic includes:

- Ordinary age-50 catch-up.
- Enhanced age-60-to-63 catch-up beginning in 2025.
- 403(b) 15-years-of-service catch-up, including annual and lifetime residuals.
- Governmental 457(b) age catch-up.
- The 457(b) special last-three-years catch-up, selecting the larger applicable method once per participant rather than combining incompatible methods.
- High-wage Roth catch-up classification using prior-year FICA wages for the sponsoring employer when applicable.

## Choosing between the two §457 catch-ups

A participant may use the age-based §414(v) catch-up or the §457(b)(3)
last-three-years catch-up for a year, never both. 26 CFR §1.457-5(a) states the
individual limitation as the basic annual limitation "plus **either** the age 50
catch-up amount under §1.457-4(c)(2), **or** the special section 457 catch-up
amount under §1.457-4(c)(3), applied by taking into account the combined annual
deferral for the participant for any taxable year under **all eligible plans**",
and §1.457-5(b) aggregates that across the plans of every employer the
participant has served.

So the choice is resolved **once per participant, before any account is
allocated**, from annual ceilings rather than from whatever pool capacity a
given account happens to see:

| | Rule |
|---|---|
| The plan ceilings compared | §1.457-4(c)(2)(ii) applies the special catch-up "if and only if" the plan ceiling counting it "is **larger than**" the plan ceiling counting the age 50 catch-up. Those are the ceilings the statute produces, not the two headline dollar figures. With `D` the §457(e)(15) amount, `C` includible compensation and `U` the prior-year underutilized limitation: the basic ceiling is `B = min(D, C)`; §457(b)(3) makes the special ceiling `S = min(2D, B + U)`, so the special catch-up above the basic ceiling is `min(2D − B, U)` — which equals `min(D, U)` only where compensation does not bind; §414(v)(2)(A)(ii) caps the age-based catch-up at `C − B`. As compensation falls the special amount **grows** and the age-based one **shrinks**, so the two figures can order oppositely to the raw dollar amounts. **Larger than is strict** — an equal §457(b)(3) ceiling leaves §414(v) available, as §414(v)(6)(C) and §457(e)(18) also read |
| Compensation bounds one method, not both | §457(b)(3) provides that the paragraph (2) ceiling "**shall be**" the special amount, replacing the 100-percent-of-includible-compensation bound inside that paragraph rather than reapplying it. §414(v) instead *adds* to the paragraph (2) ceiling and carries its own §414(v)(2)(A)(ii) compensation cap. A salary reduction is of course still bounded by the compensation there is to reduce, so where the special plan ceiling stands above it the difference is reported as `SECTION_457_SPECIAL_CATCH_UP_EXCEEDS_DEFERRABLE_COMPENSATION` (info) and left unfunded — reachable only by a nonelective employer contribution, which this engine allocates no higher than the paragraph (c)(1) ceiling |
| How much, participant-wide | §1.457-5(c): where a participant's plans provide different amounts, the limitation uses "the catch-up amount under whichever plan has the **largest** catch-up amount applicable to the participant" — the largest, not the sum. That applies to each method separately, since every plan bounds each method with its own includible compensation |
| How much, per plan | The participant's entitlement is not every plan's ceiling. §1.457-5(d) Example 2 states both figures for one participant: the individual limitation is $23,000, from Plan Y, while "$22,000 to Plan W and none to any of the other three plans" is separately lawful — W's own ceiling. Each account reports **its own** plan ceiling, and no account absorbs more of the resolved amount than its own plan provides **net of what it already holds** under that provision |
| Which accounts may draw it | §1.457-5(c) again: the special catch-up counts "only to the extent that an annual deferral is made … under an eligible plan as a result of plan provisions permitted under §1.457-4(c)(3)", and §414(v)(6)(A)(ii) reaches only a governmental plan |
| Accounts the year does not offer | Excluded from method resolution and from pool seeding entirely. An account type the year does not offer is not one of the "eligible plans" §1.457-5(b) aggregates, so it cannot select a method, contribute a ceiling, or spend a pool that a valid plan then finds empty. It is reported as `unavailable` with its supplied contributions preserved and diagnosed |
| When the age is unknown | The method itself is unresolved, not merely its size, so no catch-up is allocated under either heading and `BIRTH_YEAR_OR_DATE_REQUIRED_FOR_WORKPLACE_CATCH_UP` is raised where either a new catch-up could reach room **or an existing age/special component still needs classification**. New room is measured after the base deferral is allocated, so an account the basic limitation has already filled asks no age question when it carries no catch-up: an isolated §457(b)-hosted PLESA whose whole §402A(e)(3)(A) room the base deferral takes is fully determinate without a birth date. An existing catch-up is different because age can still decide whether its supplied component key names the method the law selected, even when the account has no room for another dollar. Nor does an age question arise where §414(v)(2)(A)(ii) leaves no compensation for an age-based catch-up at any age, or where a §457(b)(3) ceiling exceeds the largest age-route ceiling the year can produce at any age, which §414(v)(6)(C) settles without the age |

Existing catch-up contributions carry a statutory provenance the caller chose
through the component key, so six invariants are checked on that provenance
before any further catch-up is allocated. None of them reduces to a dollar
total — each is satisfiable by figures sitting under every ceiling in play, so
the ordinary excess test sees nothing. Where one fails, the supplied components
are kept for audit, the affected account is `indeterminate`, and no further
catch-up is allocated on any of that participant's §457 plans: §1.457-5(b)
determines the combined annual deferral on an aggregate basis, so adding the
selected method elsewhere could itself construct the prohibited two-method
combination. Independently determinable base deferrals remain available;
reclassifying an existing component would answer a question only the caller can
answer.

| Existing contributions | Diagnostic |
|---|---|
| Recorded under **both** methods | `SECTION_457_CATCH_UP_METHODS_ARE_MUTUALLY_EXCLUSIVE` (error). §1.457-5(a) permits the basic limitation plus one method, so the pairing breaches it **at any size** — including across two employers' plans, which §1.457-5(b) aggregates |
| Recorded solely under the **unselected** method | `SECTION_457_CATCH_UP_RECORDED_UNDER_UNSELECTED_METHOD` (error). §1.457-4(c)(2)(ii) makes the selection a determination, not an election: the age 50 catch-up "does not apply for any taxable year for which a higher limitation applies" under the special catch-up, and §414(v)(6)(C) says the same from the other side |
| Selected-method total above the participant's amount | `SECTION_457_EXISTING_CATCH_UP_EXCEEDS_PARTICIPANT_LIMIT` (error). §1.457-5(b) determines deferrals "on an aggregate basis" across every employer's plans, so two accounts each within their own ceiling can still exceed the one amount the participant is entitled to |
| Age-based catch-up on a plan that cannot host one | `SECTION_457_AGE_CATCH_UP_NOT_AVAILABLE_ON_PLAN` (error). §414(v)(6)(A)(ii) makes only an eligible **governmental** §457(b) plan an applicable employer plan |
| Special catch-up on a plan providing none | `SECTION_457_SPECIAL_CATCH_UP_NOT_PROVIDED_BY_PLAN` (error). §1.457-5(c) counts it only as a result of plan provisions permitted under §1.457-4(c)(3) |
| Special catch-up above that plan's own amount | `SECTION_457_SPECIAL_CATCH_UP_EXCEEDS_PLAN_AMOUNT` (error), even where the participant is entitled to more elsewhere |

**One `AccountInput` is one eligible plan** for all of the above. That matters
for a §457(b)-hosted PLESA, which is an account *inside* a host plan rather than
a plan of its own: a host plan's `section457SpecialCatchUp` facts must be stated
on the PLESA record too for that record to draw the amount. Issue #53 tracks the
plan-group key that would let one statement cover both records.

Account order therefore decides only *where* interchangeable capacity lands,
never which statutory method applies, what each plan's own ceiling is, or what
the participant's aggregate is. §1.457-5(d) Example 2 is committed as a
conformance vector: four plans offering $7,000, $2,000, $8,000 and nothing yield
one participant-wide ceiling of $15,000 + $8,000 = $23,000 for 2006, which is the
figure the regulation itself reaches, while the four accounts report the $22,000,
$17,000, $23,000 and $15,000 their own plans permit.

## Roth conversions and in-plan Roth rollovers

Conversions are separate from contributions and do not consume the annual IRA or elective-deferral limit.

Supported conversion categories are:

- Traditional/SEP/SIMPLE IRA to Roth IRA.
- Qualified plan to Roth IRA.
- In-plan Roth rollover.

For IRA conversions, the engine can allocate aggregate traditional/SEP/SIMPLE IRA basis using Form 8606-style pro-rata treatment. Supply aggregate basis, year-end aggregate IRA value, and other current-year distributions when relevant. Multiple same-year conversion inputs share basis without penny over-allocation.

The package reports gross converted amount, taxable amount, nontaxable basis, AGI increase, and diagnostics. It does not calculate withholding, estimated-tax penalties, five-year holding periods, early-distribution recapture, state tax, or full plan distribution eligibility.

## Calculation status and diagnostics

Possible statuses are:

- `determinate`
- `determinate_with_assumptions`
- `indeterminate`
- `unavailable`
- `ineligible`

### Pre-2002 403(b): the §403(b)(2) exclusion allowance

A 403(b) account for a tax year **1987 through 2001** returns `indeterminate` with
`PRE_2002_403B_EXCLUSION_ALLOWANCE_NOT_APPLIED`, and both its statutory maximum and its
input-supported maximum are `null`.

Before EGTRRA, §403(b)(2) capped the excludable amount at the *exclusion allowance*, and IRS
Publication 571 (2001) computes the maximum amount contributable as the **least** of that
allowance, the §415(c) annual-additions limit, and the §402(g) elective-deferral limit. The
allowance is 20% of includible compensation for the most recent year of service, multiplied by
years of service, reduced by *amounts previously excludable* — a lifetime aggregate over the
participant's service with that employer, which no input supplies. With one of the three
unknown, the least of them cannot be identified, so reporting the lesser of §415(c) and
§402(g) would state a ceiling the omitted term can only lower. The package does not model the
allowance; it declines to answer, exactly as SOURCES.md says it does.

The window closes at 2001 because EGTRRA (Pub. L. 107-16) §632(a)(2)(B) struck §403(b)(2) and
§632(a)(3)(E) struck the §415(c)(4) alternative elections, both applying "to years beginning
after December 31, 2001" (§632(a)(4)). 2002 onward is answerable from §415 and §402(g) alone.
The window opens at 1987 only because 1986 and earlier already return `indeterminate` with
`HISTORICAL_415C_LIMIT_INDETERMINATE`, there being no encoded §415(c) limit at all. Plans
other than 403(b) are untouched: a 2001 401(k) is still `determinate_with_assumptions`.

Do not discard diagnostics. They are part of the calculation contract. A non-error status may still contain warnings about missing plan terms, historical uncertainty, employer aggregation, Roth catch-up classification, or caller assumptions.

## Native TypeScript/PHP parity

The DRY boundary is the statutory data and behavioral specification, not a cross-language runtime dependency:

```text
data/retirement-parameters.json
           │
           ├── generated TypeScript parameter block
           ├── generated PHP parameter block
           └── shared conformance vectors
                         │
                         ├── complete serialized-output parity test
                         └── seeded randomized differential test
```

This gives npm consumers an idiomatic TypeScript package and Packagist consumers an idiomatic PHP package without duplicating annual parameter maintenance.

`npm run test:parity` compares complete serialized output for every conformance vector.
That set is fixed, so `npm run test:fuzz` compares the two engines on randomized scenarios
instead — varying tax year across the supported range, account types, HSA coverage shapes
and monthly patterns, existing contributions, conversions, filing statuses, and
deliberately malformed inputs — and diffs the full output including thrown error codes and
messages. It is deterministic: every run prints its seed, and `--seed=<n>` replays a
failure exactly.

```bash
npm run test:fuzz                            # 5,000 scenarios, random seed
node scripts/fuzz-parity.mjs --seed=1234      # replay
node scripts/fuzz-parity.mjs --cases=50000    # deeper sweep
```

It runs in `npm run verify` and in CI because it is cheap — 10,000 scenarios take under
three seconds, since the PHP side is batched into one process. Nine of the input-validation
divergences fixed in this package were found by it rather than by the vectors.

## Development

```bash
npm ci
npm run validate:data
npm run generate:check
npm run typecheck
npm run test:ts
npm run test:php
npm run test:parity
npm run test:fuzz
npm run verify
```

After changing `data/retirement-parameters.json`:

```bash
npm run generate
npm run verify
```

`npm run generate:check` fails if either native embedded data block differs from canonical JSON. `npm run test:parity` compares the complete TypeScript and PHP result for every shared vector, not merely selected assertions.

See [DESIGN.md](DESIGN.md), [SOURCES.md](SOURCES.md), and [CONTRIBUTING.md](CONTRIBUTING.md) before changing legal parameters or calculation semantics.

## Deliberate exclusions

The package does not calculate:

- State income-tax treatment.
- HRAs of every kind — standard, ICHRA, EBHRA, QSEHRA, suspended, retiree-only — even where they interact with §223 exactly as a health FSA does. Health FSAs under §125(i), including the carryover, *are* modelled.
- Archer MSAs themselves. The §220 limitation is not calculated, so an amount supplied as `persons[].archerMsaContributions` is taken as stated and never tested against it. The HSA §223(b)(4)(A) and §223(b)(5)(B)(i) reductions *are* applied, because both take an amount paid rather than an Archer limitation.
- Cafeteria plan qualification and nondiscrimination testing under §125(b)–(d), the §414(b)/(c)/(m) controlled-group determination that §125(g)(4) applies to the health FSA limit, the Notice 2012-40 proration of a short plan year, and the uniform-coverage and run-out-period mechanics.
- The §214 relief of the Consolidated Appropriations Act, 2021. It is entirely a plan option; a carryover computed out of 2020 or 2021 carries a diagnostic saying so.
- Adoption assistance under §137, commuter benefits under §132(f), and educational assistance under §127.
- The §21 dependent care **credit**, and the §21(c) interaction whereby §129 exclusions reduce that credit's expense base. The §129 exclusion is calculated; the credit is not.
- The §21(d)(2) deemed-earned-income schedule that §129(b)(2) applies to a student or incapacitated spouse. The §129(b)(1) limitation itself *is* applied, from the earned income supplied on `planRules.dependentCareFsa`.
- Whether a dependent care program meets the §129(d) written-plan and nondiscrimination requirements, the §129(c) denial for amounts paid to a related individual, and whether the individuals cared for qualify.
- The §408(d)(9)(C) once-per-lifetime limitation on a qualified HSA funding distribution and the separate §408(d)(9)(D) testing period. The §223(b)(4)(C) reduction itself *is* applied, from the amount supplied as `persons[].qualifiedHsaFundingDistributions`, which is taken as stated.
- The retirement savings contributions credit.
- Required minimum distributions or distribution penalties.
- Plan eligibility, vesting, loans, or distributions generally.
- ADP, ACP, coverage, top-heavy, or other nondiscrimination testing.
- Employer controlled-group ownership from raw entity records.
- Full payroll, self-employment tax, or tax-return MAGI.
- The pre-2002 §403(b)(2) maximum exclusion allowance and the §415(c)(4) alternative elections. Both are diagnosed and the affected years return `indeterminate`; neither is computed.
- Defined-benefit or cash-balance actuarial funding, and the participant-specific §415(b)(2) and §415(b)(5) adjustments to the annual benefit limit. The flat §415(b)(1)(A) figure itself *is* reported.
- Everything about a pension-linked emergency savings account except its §402A(e)(3)(A) contribution ceiling and the pools that ceiling feeds: the §402A(e)(2) eligibility test, which turns on §414(q) highly-compensated-employee status and the plan's own age and service terms; the §402A(e)(4) automatic contribution arrangement; the §402A(e)(5) participant disclosures; the §402A(e)(7) withdrawal right and the §402A(e)(8) treatment on termination; and the §402A(e)(12) anti-abuse procedures. All three §402A(f)(1) hosts are modelled, the governmental §457(b) one as its own account type. §402A(e)(9), which orders excess deferrals distributed under §402(g)(2)(A) out of the emergency account first, is not implemented at all — no excess-deferral ordering is — and its reach is in any case unsettled for a §457(b)-hosted account: it speaks of "any pension-linked emergency savings account of the participant", while a §457(b) deferral is not among the elective deferrals §402(g)(3) enumerates and so can produce no §402(g)(2)(A) excess of its own. No regulation or notice addresses the cross-plan case.
- Investment returns, retirement sufficiency, or withdrawal planning.

## License

MIT. See [LICENSE](LICENSE).
