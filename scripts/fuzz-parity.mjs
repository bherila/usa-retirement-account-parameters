#!/usr/bin/env node
/**
 * Seeded property-based TypeScript/PHP differential harness.
 *
 * `scripts/check-parity.mjs` compares the two engines on the conformance
 * vectors only, so any behaviour outside those inputs is unchecked. This
 * harness generates randomized scenarios across the supported tax years,
 * account types, HSA coverage shapes, existing contributions, conversions,
 * filing statuses, and deliberately malformed inputs, then diffs the complete
 * serialized output of both engines — including thrown error codes and
 * messages.
 *
 * It is deterministic: every run prints its seed, and `--seed=<n>` replays a
 * failure exactly. A divergence prints the offending input, both outputs, and
 * the first differing path, then exits non-zero.
 *
 *   node scripts/fuzz-parity.mjs                 # default case count, random seed
 *   node scripts/fuzz-parity.mjs --seed=12345    # replay
 *   node scripts/fuzz-parity.mjs --cases=5000    # deeper sweep
 */
import { readFile } from "node:fs/promises";
import { spawnSync } from "node:child_process";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import USTaxAdvantagedParams from "../dist/esm/USTaxAdvantagedParams.js";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");

function parseOption(name, fallback) {
  const prefix = `--${name}=`;
  const found = process.argv.find((argument) => argument.startsWith(prefix));
  if (found === undefined) return fallback;
  const parsed = Number.parseInt(found.slice(prefix.length), 10);
  if (!Number.isFinite(parsed)) throw new Error(`--${name} must be an integer.`);
  return parsed;
}

const seed = parseOption("seed", (Math.random() * 0xffffffff) >>> 0) >>> 0;
const caseCount = parseOption("cases", 5000);
const batchSize = parseOption("batch", 200);

/** mulberry32 — a small, fast, fully deterministic 32-bit PRNG. */
function makeRandom(initialSeed) {
  let state = initialSeed >>> 0;
  return function random() {
    state = (state + 0x6d2b79f5) >>> 0;
    let value = Math.imul(state ^ (state >>> 15), 1 | state);
    value = (value + Math.imul(value ^ (value >>> 7), 61 | value)) ^ value;
    return ((value ^ (value >>> 14)) >>> 0) / 4294967296;
  };
}

const random = makeRandom(seed);
const pick = (values) => values[Math.floor(random() * values.length)];
const chance = (probability) => random() < probability;
const integer = (low, high) => low + Math.floor(random() * (high - low + 1));
/** Money-shaped values, biased toward the boundaries where limits bind. */
const money = () => pick([0, 0.01, 1, 500, 3500, 7000, 7500, 12000, 23500, 24500, 47000, 70000, 100000, 350000, 1000000])
  + (chance(0.25) ? integer(0, 999) : 0);

// Both IRC 402A(f)(1) hosts, so the IRC 402A(e)(3)(A) balance rule is
// differentially fuzzed on each rather than only on the IRC 401(a)/403(b) one.
const PLESA_TYPES = [
  "pension_linked_emergency_savings",
  "governmental_457b_pension_linked_emergency_savings",
];

const ACCOUNT_TYPES = [
  "traditional_ira", "roth_ira", "rollover_ira", "payroll_deduction_ira",
  "deemed_traditional_ira", "deemed_roth_ira", "inherited_traditional_ira", "inherited_roth_ira",
  "sep_ira", "roth_sep_ira", "simple_ira", "roth_simple_ira", "sarsep_ira",
  "traditional_401k", "roth_401k", "solo_401k", "roth_solo_401k",
  "simple_401k", "roth_simple_401k", "starter_401k", "pension_linked_emergency_savings",
  "traditional_403b", "roth_403b", "safe_harbor_403b_deferral_only",
  "governmental_457b", "roth_governmental_457b", "nongovernmental_457b", "section_457f",
  "governmental_457b_pension_linked_emergency_savings",
  "traditional_tsp", "roth_tsp",
  "section_401a", "profit_sharing_plan", "money_purchase_plan", "keogh_plan", "esop",
  "defined_benefit_plan", "cash_balance_plan",
  "hsa",
  "health_fsa",
  "dependent_care_fsa",
];
const FILING_STATUSES = [
  "single", "married_filing_jointly", "married_filing_separately",
  "head_of_household", "qualifying_surviving_spouse",
  "MFJ", "MFS", "HOH", "QSS", "S",
];
const CONVERSION_TYPES = ["ira_to_roth_ira", "qualified_plan_to_roth_ira", "in_plan_roth_rollover"];
const CONTRIBUTION_PREFERENCES = ["account_type", "pretax_first", "roth_first"];
const EMPLOYER_TAX_TREATMENTS = ["pretax", "roth"];
const SIMPLE_METHODS = ["match_3_percent", "nonelective_2_percent", "custom"];
const COVERAGE_TIERS = ["self_only", "family"];
const HSA_FUZZ_MONTHS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
const HEALTH_FSA_PURPOSES = ["general_purpose", "limited_purpose", "post_deductible"];
const EXISTING_KEYS = [
  "employeePreTaxDeferral", "employeeRothDeferral", "employeePreTaxCatchUp", "employeeRothCatchUp",
  "employeeAfterTax", "employerPreTax", "employerRoth", "deductibleIra", "nondeductibleIra",
  "rothIra", "special403bCatchUp", "special457CatchUp", "special457RothCatchUp",
  "hsaDeductible", "hsaEmployerOrCafeteria",
  "healthFsaSalaryReduction", "dependentCareAssistanceProvided",
];

/**
 * Values that should be rejected identically by both engines. Malformed inputs
 * are where the two validators drift, so they are generated at a deliberate
 * rate rather than left to chance.
 */
const JUNK = [
  // "0" and [] are truthy in JavaScript and falsy in PHP, so they belong here
  // deliberately: they are the values a coercing flag check disagrees about.
  null, true, false, "", " ", "0", "1", "yes", "PRETAX", "rothFirst", "Roth_First",
  "SELF_ONLY", "familyCoverage", -1, -0.5, 1.5, 2, Number.NaN, Number.POSITIVE_INFINITY,
  [], {}, [1, 2], { nested: true },
];
const junk = () => JUNK[Math.floor(random() * JUNK.length)];

function randomHsaRules() {
  const rules = {};
  if (chance(0.3)) {
    const count = integer(0, 12);
    const months = [];
    for (let month = 1; month <= 12; month += 1) {
      if (months.length < count && chance(count / 12)) {
        months.push({ month, coverage: pick(COVERAGE_TIERS) });
      }
    }
    rules.monthlyCoverage = months;
  } else if (chance(0.9)) {
    rules.coverageTier = pick(COVERAGE_TIERS);
    if (chance(0.5)) {
      const months = [];
      for (let month = 1; month <= 12; month += 1) if (chance(0.6)) months.push(month);
      rules.eligibleMonths = months;
    }
  }
  // Three shapes, deliberately: a stated amount, an explicit null, and the
  // property omitted entirely. Explicit null and omission must normalize to the
  // same absent fact; a stated amount stays distinct from both. Telling null and
  // omission apart is what produced the TS/PHP divergence. null belongs in this
  // list rather than only in the junk injector, where it sat below 0.04% per
  // rules object and stayed unreachable enough that the split survived until a
  // lucky seed found it.
  if (chance(0.3)) rules.hdhpAnnualDeductible = pick([0, 1000, 1500, 2650, 3000, 5000, 5150, 10500, null]);
  if (chance(0.3)) rules.useLastMonthRule = chance(0.05) ? junk() : chance(0.7);
  if (chance(0.25)) rules.testingPeriodSatisfied = chance(0.5);
  if (chance(0.15)) rules.testingPeriodFailureByDeathOrDisability = chance(0.5);
  if (chance(0.25)) rules.familyLimitShare = pick([0, 0.25, 0.5, 0.75, 1, 0.6]);
  if (chance(0.04)) rules[pick(["coverageTier", "eligibleMonths", "monthlyCoverage", "familyLimitShare", "hdhpAnnualDeductible"])] = junk();
  return rules;
}

/**
 * IRC 125(i) plan facts. The carryover and grace-period flags are generated
 * independently and at a high rate so the Notice 2013-71 mutually-exclusive
 * combination, and every incomplete supply of the pair, are all reached.
 */
function randomHealthFsaRules() {
  const rules = {};
  if (chance(0.7)) rules.purpose = chance(0.05) ? junk() : pick(HEALTH_FSA_PURPOSES);
  if (chance(0.5)) rules.offersCarryover = chance(0.05) ? junk() : chance(0.6);
  if (chance(0.4)) rules.offersGracePeriod = chance(0.05) ? junk() : chance(0.5);
  // Straddles the carryover caps ($500 fixed, then 20 percent of the limit) so
  // the lesser-of and the forfeiture both bind and both fall away.
  if (chance(0.5)) rules.priorYearUnusedAmount = pick([0, 0.01, 100, 500, 550, 570, 660, 680, 5000, money()]);
  if (chance(0.35)) rules.employerFlexCredit = pick([0, 1, 500, 2750, 3400, 10000, money()]);
  if (chance(0.6)) rules.flexCreditElectableAsCash = chance(0.05) ? junk() : chance(0.5);
  if (chance(0.25)) rules.planDocumentLimit = pick([0, 1, 500, 2500, 3400, 100000, money()]);
  if (chance(0.25)) rules.planYearIsCalendarYear = chance(0.05) ? junk() : chance(0.6);
  if (chance(0.04)) {
    rules[pick(["purpose", "offersCarryover", "priorYearUnusedAmount", "employerFlexCredit", "planDocumentLimit"])] = junk();
  }
  return rules;
}

/**
 * IRC 129 facts. Earned income is generated below, at, and above the
 * IRC 129(a)(2)(A) amounts for every encoded year so the IRC 129(b)(1)
 * limitation binds and falls away, and each of the employee-only, spouse-only
 * and neither-supplied shapes is reached.
 */
function randomDependentCareRules() {
  const rules = {};
  if (chance(0.5)) {
    rules.planDocumentLimit = chance(0.05) ? junk() : pick([0, 1, 2000, 2500, 3750, 5000, 7500, money()]);
  }
  return rules;
}

/**
 * The IRC 129(b)(1) facts live on the person, so they are generated there. The
 * values run below, at and above the IRC 129(a)(2)(A) amounts for every encoded
 * year, so the limitation binds and falls away, and the employee-only,
 * spouse-only and neither-supplied shapes are all reached across a run.
 */
function addDependentCareFactsToPerson(person) {
  if (chance(0.6)) {
    person.dependentCareEarnedIncome = chance(0.05)
      ? junk()
      : pick([0, 0.01, 1, 2500, 3750, 5000, 7500, 10500, 60000, money()]);
  }
  if (chance(0.3)) person.isStudentOrIncapableOfSelfCare = chance(0.05) ? junk() : chance(0.5);
  if (chance(0.04)) person[pick(["dependentCareEarnedIncome", "isStudentOrIncapableOfSelfCare"])] = junk();
}

function randomPlanRules(type) {
  const rules = {};
  if (chance(0.8)) rules.planCompensation = money();
  if (chance(0.2)) rules.includibleCompensation457 = money();
  if (chance(0.2)) rules.annualAdditionsGroupId = pick(["g1", "g2", "0", 0, ""]);
  if (chance(0.15)) rules.planDocumentEmployeeDeferralLimit = money();
  if (chance(0.15)) rules.planDocumentAnnualAdditionsLimit = money();
  if (chance(0.4)) rules.permitsRothContributions = chance(0.05) ? junk() : chance(0.7);
  if (chance(0.25)) rules.permitsRothCatchUp = chance(0.7);
  if (chance(0.3)) rules.permitsAfterTaxEmployeeContributions = chance(0.6);
  if (chance(0.15)) rules.permitsInPlanRothRollover = chance(0.6);
  if (chance(0.35)) rules.contributionPreference = pick(CONTRIBUTION_PREFERENCES);
  if (chance(0.3)) rules.expectedEmployerContribution = money();
  if (chance(0.25)) rules.employerMatchRate = pick([0, 0.25, 0.5, 1]);
  if (chance(0.25)) rules.employerMatchCompensationFraction = pick([0, 0.03, 0.06, 1]);
  if (chance(0.25)) rules.employerNonelectiveRate = pick([0, 0.02, 0.03, 0.1, 0.25]);
  if (chance(0.25)) rules.employerContributionTaxTreatment = pick(EMPLOYER_TAX_TREATMENTS);
  if (chance(0.2)) rules.simpleEmployerContributionMethod = pick(SIMPLE_METHODS);
  if (chance(0.1)) rules.simpleCustomEmployerContribution = money();
  if (chance(0.15)) rules.simpleEnhancedLimitEligible = chance(0.6);
  if (chance(0.2)) rules.isSelfEmployedOwner = chance(0.6);
  if (chance(0.2)) rules.netEarningsFromSelfEmploymentAfterHalfSETax = money();
  if (chance(0.15)) {
    rules.special403bCatchUp = {
      eligible: chance(0.8),
      yearsOfService: integer(0, 30),
      priorElectiveDeferrals: money(),
      priorSpecialCatchUpUsed: money(),
    };
  }
  if (chance(0.15)) {
    rules.section457SpecialCatchUp = { eligible: chance(0.8), unusedDeferralsFromPriorYears: money() };
  }
  if (chance(0.1)) rules.grandfatheredSarsep = chance(0.5);
  if (chance(0.1)) rules.simpleAdditionalNonelectiveContribution = money();
  // Present most of the time on a pension-linked emergency savings account, in
  // either host, and
  // occasionally elsewhere, so both the supplied and the missing branch of the
  // IRC 402A(e)(3)(A) balance rule are differentially tested.
  if (PLESA_TYPES.includes(type) ? chance(0.7) : chance(0.08)) {
    rules.pensionLinkedEmergencySavingsParticipantContributionBalance = chance(0.05) ? junk() : money();
  }
  // An explicitly supplied null is a different input from an omitted key, and the
  // two engines read absence differently in more than one place -- `=== undefined`
  // against `?? null`. Only generating values and omissions left that whole class
  // of divergence outside the fuzz space.
  if (chance(0.06)) rules.pensionLinkedEmergencySavingsParticipantContributionBalance = null;
  if (chance(0.06)) rules.planDocumentEmployeeDeferralLimit = null;
  if (chance(0.04)) rules.planDocumentAnnualAdditionsLimit = null;
  if (chance(0.04)) rules.includibleCompensation457 = null;
  if (chance(0.04)) rules.planCompensation = null;
  if (type === "hsa" || chance(0.05)) rules.hsa = randomHsaRules();
  if (type === "health_fsa" || chance(0.05)) rules.healthFsa = randomHealthFsaRules();
  if (type === "dependent_care_fsa" || chance(0.05)) rules.dependentCareFsa = randomDependentCareRules();
  if (chance(0.04)) {
    rules[pick([
      "planCompensation", "employerMatchRate", "employerNonelectiveRate",
      "contributionPreference", "employerContributionTaxTreatment",
      "simpleEmployerContributionMethod", "expectedEmployerContribution",
    ])] = junk();
  }
  return rules;
}

function randomExisting() {
  const existing = {};
  for (const key of EXISTING_KEYS) if (chance(0.12)) existing[key] = money();
  if (chance(0.03)) existing[pick(EXISTING_KEYS)] = junk();
  return existing;
}

function randomPerson(id, role, taxYear) {
  const person = { id, role };
  addDependentCareFactsToPerson(person);
  if (chance(0.9)) {
    if (chance(0.5)) person.birthYear = integer(taxYear - 85, taxYear - 18);
    else person.birthDate = `${integer(taxYear - 85, taxYear - 18)}-${String(integer(1, 12)).padStart(2, "0")}-${String(integer(1, 28)).padStart(2, "0")}`;
  }
  if (chance(0.8)) {
    person.compensation = {};
    if (chance(0.8)) person.compensation.w2Compensation = money();
    if (chance(0.4)) person.compensation.iraCompensation = money();
    if (chance(0.3)) person.compensation.selfEmploymentNetEarnings = money();
  }
  if (chance(0.6)) {
    person.magi = {};
    if (chance(0.8)) person.magi.rothIra = money();
    if (chance(0.8)) person.magi.traditionalIraDeduction = money();
    if (chance(0.3)) person.magi.rothConversion = money();
  }
  if (chance(0.5)) person.coveredByEmployerRetirementPlan = chance(0.05) ? junk() : chance(0.6);
  if (chance(0.3)) person.livedWithSpouseDuringYear = chance(0.5);
  if (chance(0.15)) person.priorYearFicaWagesByEmployer = { e1: money() };
  if (chance(0.2)) person.traditionalSepSimpleIraBasis = money();
  if (chance(0.2)) person.yearEndTraditionalSepSimpleIraValue = money();
  if (chance(0.1)) person.otherTraditionalSepSimpleIraDistributions = money();
  if (chance(0.3)) person.archerMsaContributions = chance(0.05) ? junk() : pick([0, 1, 750, 2400, 5150, 12000, money()]);
  // IRC 223(b)(4)(C). Values straddle the IRC 223(b)(1) limitation so the
  // reduction lands wholly inside it, spills into the IRC 223(b)(3) amount, and
  // exhausts both.
  if (chance(0.3)) {
    person.qualifiedHsaFundingDistributions = chance(0.05) ? junk() : pick([0, 1, 900, 3400, 4400, 8750, 20000, money()]);
  }
  // Person-level IRC 223(c)(2) coverage. randomHsaRules() also emits the
  // account-only keys, which both engines must ignore identically here.
  // Spouse coverage drives the IRC 223(b)(5)(A) family-sharing and
  // lowest-deductible rules even when that spouse owns no HSA, so it is
  // generated often and is allowed to be family-with-a-deductible, family
  // without one, self-only with a deductible, or the empty object that records
  // no coverage at all. Mixed family/self-only couples are what proved a
  // self-only deductible must not lower the family limitation.
  if (chance(0.45)) {
    person.hsaCoverage = chance(0.1)
      ? {}
      : (chance(0.5)
        ? {
            // Per-month spouse coverage, not only a flat tier. The
            // IRC 223(b)(5)(A) lowest-deductible comparison is answered month by
            // month in a capped year, so a couple whose family-coverage months
            // differ is the shape that distinguishes a per-month resolution from
            // a whole-year one -- and nothing generated it before.
            ...(chance(0.4)
              ? { monthlyCoverage: HSA_FUZZ_MONTHS.filter(() => chance(0.4)).map((month) => ({ month, coverage: pick(COVERAGE_TIERS) })) }
              : { coverageTier: pick(COVERAGE_TIERS), ...(chance(0.4) ? { eligibleMonths: HSA_FUZZ_MONTHS.filter(() => chance(0.5)) } : {}) }),
            ...(chance(0.7) ? { hdhpAnnualDeductible: pick([1000, 3000, 5000, null]) } : {}),
          }
        : randomHsaRules());
  }
  if (chance(0.03)) person[pick(["birthYear", "role", "compensation", "magi", "coveredByEmployerRetirementPlan"])] = junk();
  return person;
}

function randomScenario() {
  const hsaHeavy = chance(0.35);
  const fsaHeavy = chance(0.3);
  // A pension-linked emergency savings account exists only for plan years
  // beginning after December 31, 2023, and its IRC 402A(e)(3)(A)(i) figure has
  // taken two values so far, so the injection below needs a year range that
  // straddles the availability boundary and both amounts.
  const plesaHeavy = !hsaHeavy && !fsaHeavy && chance(0.35);
  const taxYear = hsaHeavy
    ? integer(2002, 2028)
    : fsaHeavy
      ? pick([integer(1979, 1990), integer(2009, 2028)])
      : plesaHeavy
        // 2023 weighted up on its own: the account type does not exist that year,
        // and an unavailable account must not select the IRC 457(e)(18) method,
        // seed a pool or contribute a ceiling for the valid plans beside it.
        ? (chance(0.25) ? 2023 : integer(2023, 2028))
        : pick([integer(1973, 1980), integer(1981, 2000), integer(2001, 2015), integer(2016, 2028)]);
  const filingStatus = pick(FILING_STATUSES);
  const personCount = chance(0.55) ? 2 : 1;
  const persons = [randomPerson("t", "taxpayer", taxYear)];
  if (personCount === 2) persons.push(randomPerson("s", chance(0.9) ? "spouse" : "other", taxYear));

  const accounts = [];
  const accountCount = integer(0, 3);
  for (let index = 0; index < accountCount; index += 1) {
    const type = hsaHeavy && chance(0.7)
      ? "hsa"
      : fsaHeavy && chance(0.8)
        ? pick(["health_fsa", "dependent_care_fsa", "dependent_care_fsa", "hsa"])
        : pick(ACCOUNT_TYPES);
    const account = {
      id: `a${index}`,
      ownerId: chance(0.03) ? "ghost" : pick(persons).id,
      type: chance(0.03) ? pick(["401K", "not_a_type", "", "hsa "]) : type,
    };
    // "0" is an identifier the input contract accepts, and it is in the pool because
    // PHP's empty() reads it as absent while JavaScript truthiness does not. That
    // divergence shipped and this generator did not reach it.
    //
    // The malformed values sit beside it for the same reason and cost nothing: the
    // input contract requires a non-empty string, so both engines must reject them
    // identically rather than each coercing in its own direction. A numeric 0 was
    // the second divergence here -- PHP read it as the employer "0" and classified
    // a catch-up, TypeScript read it as absent and returned indeterminate.
    if (chance(0.3)) account.employerId = pick(["e1", "e2", "0", 0, "", 1, null]);
    if (chance(0.3)) account.priority = integer(1, 200);
    if (chance(0.85)) account.planRules = randomPlanRules(type);
    if (chance(0.4)) account.existingContributions = randomExisting();
    accounts.push(account);
  }

  /**
   * One person holding two health savings accounts whose coverage statements
   * disagree, inserted at independently chosen array positions, alongside an
   * account for the other spouse.
   *
   * Everything this shape turns on is varied independently, because the rules
   * project the disagreement onto separate operands and each projection has its
   * own way of going wrong:
   *
   *  - *which months* the statements disagree about, against which months the
   *    other spouse is eligible and self-only, since IRC 223(b)(5)(A) is a
   *    monthly question and a January disagreement must leave December alone;
   *  - *which field* they disagree about -- coverage tier, annual deductible,
   *    the IRC 223(b)(5)(B)(ii) share -- since only some of those reach the
   *    couple's limitation, and the deductible only in 2004-2006;
   *  - whether family sharing is active at all, through the other spouse's tier;
   *  - and array position independently of `priority`, since coverage facts are
   *    read in input order while priority governs allocation. Emitting the pair
   *    in both orders is what compares the engines on that.
   *
   * The ordinary generator reaches none of this at a useful rate: it needs two
   * HSAs, one owner, and a specific kind of difference all at once.
   */
  if (personCount === 2 && chance(0.14)) {
    const conflictOwnerId = pick(persons).id;
    const monthsFor = () => HSA_FUZZ_MONTHS.filter(() => chance(0.4));
    const conflictShape = pick([
      "tier",
      "monthly",
      "deductible_only",
      "share_only",
      // A disagreement about *which months are covered at all* leaves the
      // family question answered the same way by both statements but changes
      // the undivided self-only portion that is added to the IRC 223(b)(5)
      // household ceiling.
      "eligible_months_only",
      // Neither field is coverage, and only one of the two reaches the ceiling.
      "last_month_rule",
      "testing_period",
      // An account statement carrying no usable coverage fact, which must not
      // read as an assertion that the person had none.
      "empty_account",
    ]);
    const sharedTier = pick(COVERAGE_TIERS);
    let pairRules;
    if (conflictShape === "monthly") {
      // Disjoint or overlapping month sets, chosen independently of the other
      // spouse's, so the same-month test is exercised both ways.
      const left = monthsFor();
      const right = monthsFor();
      pairRules = [
        { monthlyCoverage: left.map((month) => ({ month, coverage: pick(COVERAGE_TIERS) })) },
        { monthlyCoverage: right.map((month) => ({ month, coverage: pick(COVERAGE_TIERS) })) },
      ];
    } else if (conflictShape === "deductible_only") {
      pairRules = [
        { coverageTier: sharedTier, hdhpAnnualDeductible: money() },
        { coverageTier: sharedTier, hdhpAnnualDeductible: money() },
      ];
    } else if (conflictShape === "share_only") {
      pairRules = [
        { coverageTier: sharedTier, familyLimitShare: 0.5 },
        { coverageTier: sharedTier, familyLimitShare: chance(0.5) ? 0.5 : 0.25 },
      ];
    } else if (conflictShape === "eligible_months_only") {
      pairRules = [
        { coverageTier: sharedTier, eligibleMonths: monthsFor() },
        { coverageTier: sharedTier, eligibleMonths: monthsFor() },
      ];
    } else if (conflictShape === "last_month_rule") {
      const shared = { coverageTier: sharedTier, eligibleMonths: [12] };
      pairRules = [
        { ...shared, useLastMonthRule: true, ...(chance(0.6) ? { testingPeriodSatisfied: chance(0.5) } : {}) },
        { ...shared, useLastMonthRule: chance(0.75) ? false : true },
      ];
    } else if (conflictShape === "testing_period") {
      const shared = { coverageTier: sharedTier, eligibleMonths: [12], useLastMonthRule: true };
      pairRules = [
        { ...shared, testingPeriodSatisfied: true },
        {
          ...shared,
          ...(chance(0.75) ? { testingPeriodSatisfied: false } : {}),
          ...(chance(0.3) ? { testingPeriodFailureByDeathOrDisability: true } : {}),
        },
      ];
    } else if (conflictShape === "empty_account") {
      pairRules = [
        {},
        chance(0.5) ? { coverageTier: sharedTier } : {},
      ];
    } else {
      pairRules = [
        { coverageTier: "self_only", ...(chance(0.4) ? { hdhpAnnualDeductible: money() } : {}) },
        {
          coverageTier: chance(0.75) ? "family" : "self_only",
          ...(chance(0.5) ? { hdhpAnnualDeductible: money() } : {}),
        },
      ];
    }
    if (chance(0.5)) pairRules.reverse();
    pairRules.forEach((hsa, offset) => {
      const account = { id: `x${offset}`, ownerId: conflictOwnerId, type: "hsa", planRules: { hsa } };
      if (chance(0.3)) account.priority = integer(1, 200);
      accounts.splice(integer(0, accounts.length), 0, account);
    });
    const otherPerson = persons.find((person) => person.id !== conflictOwnerId);
    if (otherPerson !== undefined && chance(0.85)) {
      const hsa = chance(0.4)
        ? { monthlyCoverage: monthsFor().map((month) => ({ month, coverage: pick(COVERAGE_TIERS) })) }
        : {
            coverageTier: chance(0.7) ? "self_only" : "family",
            ...(chance(0.4) ? { eligibleMonths: monthsFor() } : {}),
            ...(chance(0.4) ? { hdhpAnnualDeductible: money() } : {}),
          };
      const account = { id: "x2", ownerId: otherPerson.id, type: "hsa", planRules: { hsa } };
      if (chance(0.3)) account.priority = integer(1, 200);
      accounts.splice(integer(0, accounts.length), 0, account);
    }
    // The person-level statement is one more variant of the same fact, so the
    // person-versus-account contradiction has to be generated too.
    if (chance(0.2)) {
      const target = pick(persons);
      target.hsaCoverage = chance(0.2)
        // The documented statement of no high deductible health plan coverage,
        // which must stay distinguishable from an account that states nothing.
        ? {}
        : chance(0.5)
          ? { coverageTier: pick(COVERAGE_TIERS) }
          : { monthlyCoverage: monthsFor().map((month) => ({ month, coverage: pick(COVERAGE_TIERS) })) };
    }
  }

  /**
   * A pension-linked emergency savings account beside another account of the
   * same host plan: same owner, same employer, same annual-additions group.
   *
   * IRC 402A(e)(3)(A) caps the account's own balance while the host's IRC 402(g)
   * and IRC 414(v) pools are shared with everything else in the plan, so the
   * interesting states are the ones where one of those runs out before the other
   * — and an isolated account, which is what the ordinary generator almost
   * always produces, never reaches them: its $2,500 or $2,600 of room sits far
   * below an untouched $23,500 base limit, so the base allocation always covers
   * it and neither the catch-up path nor the exhausted-host path is ever taken.
   *
   * Varied independently, because each turns a different part of the rule:
   *
   *  - the host's recognized compensation, biased to just below and just above
   *    the IRC 402(g)(1) limit, so the base pool is sometimes exhausted before
   *    the account is reached and sometimes not, and so the host does or does not
   *    consume the shared IRC 414(v) pool first;
   *  - the owner's age, including its absence, since a birth year is required
   *    only where a catch-up could reach the remaining room;
   *  - the supplied balance, from zero through the cap to above it, and its
   *    absence, which is a different answer from a zero balance;
   *  - a plan sponsor's lower IRC 402A(e)(3)(A)(ii) amount, which must bind the
   *    base and the catch-up together rather than either alone;
   *  - existing contributions on the account, which seed the host's annual pools
   *    while the balance seeds the account-local one, so a double subtraction
   *    shows up as a divergence;
   *  - and array position independently of `priority`.
   */
  if (plesaHeavy || chance(0.05)) {
    const owner = pick(persons);
    if (chance(0.75)) owner.birthYear = taxYear - pick([35, 45, 49, 50, 56, 61, 64]);
    else delete owner.birthYear;
    const employerId = pick(["e1", "e2", "0"]);
    const groupId = pick(["g1", "g2"]);
    // Which IRC 402A(f)(1) host. The governmental IRC 457(b) one at
    // IRC 402A(f)(1)(C) spends a different base pool (IRC 457(e)(15) rather than
    // IRC 402(g)), joins no IRC 415(c) group, and reaches a catch-up the other
    // hosts do not have at all -- the IRC 457(b)(3) last-three-years one -- so
    // the pair is generated on either host rather than only the first two.
    const section457Host = chance(0.4);
    const plesaType = section457Host
      ? "governmental_457b_pension_linked_emergency_savings"
      : "pension_linked_emergency_savings";
    const hostType = section457Host
      ? pick(["governmental_457b", "roth_governmental_457b", "nongovernmental_457b"])
      : pick([
        "traditional_401k", "roth_401k", "solo_401k", "traditional_403b", "safe_harbor_403b_deferral_only",
      ]);
    // Half the time the host is arranged to have spent the shared IRC 402(g)
    // pool without touching the shared IRC 414(v) one, by recognizing exactly
    // the compensation it has already deferred. That is the state the reported
    // defect lived in, and leaving it to chance produced it a handful of times
    // in thousands of cases.
    const exhaustHost = chance(0.5);
    const deferred = pick([23000, 23500, 24500]);
    const hostCompensation = exhaustHost
      ? deferred
      : pick([0, 1000, 15500, 23000, 23500, 24500, 60000, 350000]);
    const hostRules = {
      annualAdditionsGroupId: groupId,
      planCompensation: hostCompensation,
      expectedEmployerContribution: chance(0.6) ? 0 : money(),
    };
    // An IRC 457(b) account measures its own ceiling against includible
    // compensation, so exhausting that host means constraining this field.
    if (section457Host) hostRules.includibleCompensation457 = hostCompensation;
    if (chance(0.3)) hostRules.planDocumentEmployeeDeferralLimit = money();
    const plesaRules = { annualAdditionsGroupId: groupId };
    if (chance(0.85)) {
      plesaRules.pensionLinkedEmergencySavingsParticipantContributionBalance =
        pick([0, 1, 200, 1000, 2500, 2600, 5000, money()]);
    }
    if (chance(0.25)) plesaRules.planDocumentEmployeeDeferralLimit = pick([0, 500, 1000, 2500, money()]);
    if (chance(0.2)) plesaRules.planCompensation = money();
    // Explicitly null, on the account shape that actually reaches the
    // IRC 402A(e)(3)(A) cap arithmetic. The generic rule generator emits nulls
    // too, but a pension-linked emergency savings account assembled there needs
    // several coincidences before the cap path runs at all, so the case the two
    // engines read differently was effectively outside the fuzz space.
    if (chance(0.12)) plesaRules.planDocumentEmployeeDeferralLimit = null;
    if (chance(0.08)) plesaRules.pensionLinkedEmergencySavingsParticipantContributionBalance = null;
    // A pension-linked emergency savings account holds designated Roth
    // contributions by IRC 402A(e)(1)(A)(i) whatever the caller states, so the
    // fields that would otherwise elect pre-tax treatment have to reach this
    // shape: they are the ones an engine could honour by mistake, and the
    // generic rule generator does not build a PLESA that reaches the allocation.
    if (chance(0.35)) plesaRules.contributionPreference = pick(CONTRIBUTION_PREFERENCES);
    if (chance(0.25)) plesaRules.permitsRothContributions = chance(0.5);
    if (chance(0.2)) plesaRules.permitsRothCatchUp = chance(0.5);
    // The IRC 457(b)(3) catch-up is the one this host has and the others do not.
    // Put it on either account, since IRC 457(e)(18) picks between it and the
    // IRC 414(v) catch-up per participant and IRC 414(v)(6)(C) turns the age
    // question off for a year in which it applies.
    if (section457Host && chance(0.35)) {
      const special = { eligible: chance(0.8), unusedDeferralsFromPriorYears: pick([0, 500, 5000, 20000, money()]) };
      if (chance(0.5)) plesaRules.section457SpecialCatchUp = special;
      else hostRules.section457SpecialCatchUp = special;
    }
    // Sometimes the emergency savings account stands alone, with no host
    // account sharing the participant's base pool. That is the shape where the
    // base deferral itself fills the whole IRC 402A(e)(3)(A) room, leaving no
    // room for a catch-up of any size, so it is the shape that separates an
    // answer the age cannot move from one it can. Generated as a pair only, it
    // never occurred: the host always either spent the base pool, leaving the
    // account-local room intact, or was reached second.
    const isolatedPlesa = chance(0.2);
    const pair = [
      { id: "p0", ownerId: owner.id, type: hostType, employerId, planRules: hostRules },
      { id: "p1", ownerId: owner.id, type: plesaType, employerId, planRules: plesaRules },
    ];
    if (chance(0.3)) pair[1].existingContributions = randomExisting();
    if (exhaustHost) {
      pair[0].existingContributions = section457Host && hostType === "roth_governmental_457b"
        ? { employeeRothDeferral: deferred }
        : { employeePreTaxDeferral: deferred };
    }
    else if (chance(0.3)) pair[0].existingContributions = randomExisting();
    if (chance(0.5)) pair.reverse();
    pair.forEach((account) => {
      if (isolatedPlesa && account.id === "p0") return;
      if (chance(0.4)) account.priority = integer(1, 200);
      accounts.splice(integer(0, accounts.length), 0, account);
    });
    if (chance(0.3)) owner.priorYearFicaWagesByEmployer = { [employerId]: money() };
  }

  // A second targeted shape: two ordinary IRC 457(b) accounts for one
  // participant. IRC 457(e)(18) and 26 CFR 1.457-4(c)(2)(ii) give the greater of
  // the two catch-up methods, and 1.457-5(c) takes the largest single plan's
  // IRC 457(b)(3) amount rather than the sum, so both rules only bind once a
  // participant holds more than one such account. The PLESA pair above emits at
  // most one IRC 457(b)(3) declaration per participant, which left the whole
  // multi-account conflict outside the fuzz space.
  if (chance(0.3)) {
    const owner = pick(persons);
    const employerId = pick(["e0", "e1", "0"]);
    // The age is the fact the method choice turns on where neither IRC 457(b)(3)
    // amount clears the year's largest age-based catch-up, so it is dropped
    // outright a good part of the time rather than left to the generic 10%.
    if (chance(0.35)) { delete owner.birthYear; delete owner.birthDate; }
    // 26 CFR 1.457-5(b) applies the individual limitation across "eligible plans of
    // all employers for whom a participant has performed services", so the two
    // plans belong to two employers part of the time: the aggregate bounds and the
    // both-methods pairing have to hold across that split as much as within one
    // employer, and a shared employer id alone never showed it.
    const splitEmployers = chance(0.4);
    const twins = [0, 1].map((index) => {
      // Compensation below the IRC 457(e)(15) amount is what separates the two
      // catch-up methods: 26 CFR 1.457-4(c)(3)(ii)(A) builds the IRC 457(b)(3)
      // ceiling on the compensation-bounded paragraph (2) ceiling, so the special
      // amount grows as compensation falls, while IRC 414(v)(2)(A)(ii) shrinks the
      // age-based one to nothing over the same range. Unequal values across the
      // pair also separate the participant's IRC 414(v) entitlement -- the largest
      // one plan allows under 1.457-5(c) -- from the sum of what both allow.
      const rules = {
        includibleCompensation457: pick([0, 5000, 10000, 24500, 26000, 30000, 60000, 350000]),
      };
      if (chance(0.7)) {
        rules.section457SpecialCatchUp = {
          eligible: chance(0.85),
          // Deliberately unequal across the two, since equal amounts cannot show
          // a largest-of rule apart from a sum-of one.
          unusedDeferralsFromPriorYears: pick([0, 1000, 5000, 8000, 11250, 20000, 24500, money()]),
        };
      }
      if (chance(0.2)) rules.planDocumentEmployeeDeferralLimit = money();
      const account = {
        id: `s45${index}`,
        ownerId: owner.id,
        type: pick(["governmental_457b", "roth_governmental_457b", "nongovernmental_457b"]),
        employerId: splitEmployers && index === 1 ? "e2" : employerId,
        planRules: rules,
      };
      // Existing contributions under either heading, so the aggregate bound and
      // the both-methods-supplied case are reachable. One shape carries both
      // headings at once: 26 CFR 1.457-5(a) allows the basic limitation plus
      // either catch-up, so the pairing is a breach at any size, and picking one
      // heading per account left the within-one-account form of it unreachable.
      if (chance(0.35)) {
        account.existingContributions = pick([
          { special457CatchUp: pick([1000, 19000, money()]) },
          { special457RothCatchUp: pick([1000, 19000, money()]) },
          { employeePreTaxCatchUp: pick([1000, 8000, money()]) },
          { employeePreTaxDeferral: pick([23000, 24500, money()]) },
          {
            employeePreTaxCatchUp: pick([1000, 4000, 8000]),
            special457CatchUp: pick([1000, 4000, 19000]),
          },
          {
            employeeRothCatchUp: pick([1000, 4000, 8000]),
            special457RothCatchUp: pick([1000, 4000, 19000]),
          },
          // A single heading at a size that clears the participant's entitlement
          // only once both accounts are counted, so 26 CFR 1.457-5(b)'s aggregate
          // bound is reachable with every per-account and per-plan figure lawful.
          { employeePreTaxCatchUp: pick([4000, 5000, 6000]) },
          { special457CatchUp: pick([4000, 5000, 6000]) },
          randomExisting(),
        ]);
      }
      if (chance(0.4)) account.priority = integer(1, 200);
      return account;
    });
    if (chance(0.5)) twins.reverse();
    twins.forEach((account) => accounts.splice(integer(0, accounts.length), 0, account));
    if (chance(0.4)) owner.priorYearFicaWagesByEmployer = { [employerId]: money() };
  }

  const scenario = { taxYear, filingStatus, persons, accounts };

  if (chance(0.25)) {
    const conversions = [];
    for (let index = 0; index < integer(1, 2); index += 1) {
      const conversion = {
        id: `c${index}`,
        ownerId: chance(0.1) ? undefined : pick(persons).id,
        type: chance(0.05) ? "not_a_conversion" : pick(CONVERSION_TYPES),
        amount: money(),
      };
      if (conversion.ownerId === undefined) delete conversion.ownerId;
      if (chance(0.3)) conversion.afterTaxBasisInConvertedAmount = money();
      if (chance(0.3)) conversion.aggregateIraBasisOverride = money();
      if (chance(0.3)) conversion.yearEndAggregateIraValueOverride = money();
      if (chance(0.2)) conversion.otherwiseDistributableAmount = chance(0.5);
      if (chance(0.2)) conversion.sourceAccountId = accounts.length > 0 && chance(0.7) ? accounts[0].id : "missing";
      conversions.push(conversion);
    }
    scenario.conversions = conversions;
  }

  // Structurally malformed scenarios: the shape checks themselves must agree.
  if (chance(0.03)) scenario.persons = chance(0.5) ? [junk()] : junk();
  if (chance(0.03)) scenario.accounts = chance(0.5) ? [junk()] : junk();
  if (chance(0.02)) scenario.conversions = chance(0.5) ? [junk()] : junk();
  if (chance(0.02)) scenario.taxYear = junk();
  if (chance(0.02)) scenario.filingStatus = junk();

  return scenario;
}

function runTypeScript(input) {
  try {
    return USTaxAdvantagedParams.calculate(input);
  } catch (error) {
    if (error instanceof Error && typeof error.code === "string") {
      return { __error: { code: error.code, message: error.message } };
    }
    return { __throw: `${error?.constructor?.name ?? "Error"}: ${error?.message ?? String(error)}` };
  }
}

function runPhp(inputs) {
  const php = spawnSync("php", [join(root, "scripts/fuzz-parity-runner.php")], {
    cwd: root,
    input: JSON.stringify(inputs),
    encoding: "utf8",
    maxBuffer: 256 * 1024 * 1024,
  });
  if (php.error) throw php.error;
  if (php.status !== 0) {
    throw new Error(`fuzz-parity-runner exited ${php.status}:\n${php.stderr}`);
  }
  return JSON.parse(php.stdout);
}

/** First path at which two JSON-serializable values differ, or null. */
function firstDifference(left, right, path = "") {
  if (Object.is(left, right)) return null;
  const leftIsObject = left !== null && typeof left === "object";
  const rightIsObject = right !== null && typeof right === "object";
  if (!leftIsObject || !rightIsObject) {
    return { path: path || "<root>", left, right };
  }
  if (Array.isArray(left) !== Array.isArray(right)) {
    return { path: path || "<root>", left, right };
  }
  const keys = [...new Set([...Object.keys(left), ...Object.keys(right)])];
  for (const key of keys) {
    const next = Array.isArray(left) ? `${path}[${key}]` : path ? `${path}.${key}` : key;
    const difference = firstDifference(left[key], right[key], next);
    if (difference !== null) return difference;
  }
  return null;
}

/**
 * JSON round-trip. PHP emits its result through json_encode, so comparing the
 * TypeScript object directly would report differences that are artifacts of
 * `undefined` keys rather than real divergence — the same normalization the
 * parity runner already applies to the PHP side.
 */
const serializable = (value) => JSON.parse(JSON.stringify(value));

const packageJson = JSON.parse(await readFile(join(root, "package.json"), "utf8"));
console.log(
  `fuzz-parity: ${caseCount} cases, seed ${seed} (replay with --seed=${seed}), engine ${packageJson.version}`,
);

const maxReported = parseOption("report", 5);
/** Array indices collapsed, so repeats of one bug group into one signature. */
const signatureOf = (path) => path.replace(/\[\d+\]/g, "[]");
const truncate = (value, limit = 400) => {
  const text = JSON.stringify(value) ?? "undefined";
  return text.length > limit ? `${text.slice(0, limit)}…` : text;
};

let compared = 0;
let errorCases = 0;
const failures = [];
const signatures = new Map();

for (let start = 0; start < caseCount; start += batchSize) {
  const size = Math.min(batchSize, caseCount - start);
  /*
   * Round-trip every generated scenario through JSON before either engine sees
   * it. PHP receives the input as JSON, so handing TypeScript the live object
   * would let values JSON cannot carry — NaN and Infinity both serialize to
   * null — reach one engine and not the other, and report a harness artifact
   * as a divergence.
   */
  const inputs = Array.from({ length: size }, () => JSON.parse(JSON.stringify(randomScenario())));
  const tsResults = inputs.map((input) => runTypeScript(input));
  const phpResults = runPhp(inputs);
  for (let index = 0; index < size; index += 1) {
    const tsResult = serializable(tsResults[index]);
    const phpResult = phpResults[index];
    if (tsResult?.__error) errorCases += 1;
    compared += 1;
    const difference = firstDifference(tsResult, phpResult);
    if (difference === null) continue;
    const signature = signatureOf(difference.path);
    const seen = signatures.get(signature) ?? 0;
    signatures.set(signature, seen + 1);
    // One worked example per distinct signature, so a single run shows every
    // kind of divergence rather than five repeats of the loudest one.
    if (seen === 0 && failures.length < maxReported) {
      failures.push({ caseIndex: start + index, signature, input: inputs[index], difference, tsResult, phpResult });
    }
  }
}

if (signatures.size > 0) {
  for (const failure of failures) {
    console.error(`\n=== TypeScript/PHP divergence [${failure.signature}], case ${failure.caseIndex} (seed ${seed}) ===`);
    console.error(`path: ${failure.difference.path}`);
    console.error(`  TS : ${truncate(failure.difference.left)}`);
    console.error(`  PHP: ${truncate(failure.difference.right)}`);
    console.error(`  TS result : ${truncate(failure.tsResult, 300)}`);
    console.error(`  PHP result: ${truncate(failure.phpResult, 300)}`);
    console.error(`input: ${JSON.stringify(failure.input)}`);
  }
  const divergent = [...signatures.values()].reduce((sum, count) => sum + count, 0);
  console.error("\ndivergence signatures:");
  for (const [signature, count] of [...signatures].sort((a, b) => b[1] - a[1])) {
    console.error(`  ${String(count).padStart(5)}  ${signature}`);
  }
  console.error(
    `\nfuzz-parity FAILED: ${divergent} divergent case(s) out of ${compared} compared. Replay with --seed=${seed}.`,
  );
  process.exit(1);
}

console.log(
  `fuzz-parity passed: ${compared} randomized scenarios agreed byte for byte (${errorCases} rejected identically by both engines).`,
);
