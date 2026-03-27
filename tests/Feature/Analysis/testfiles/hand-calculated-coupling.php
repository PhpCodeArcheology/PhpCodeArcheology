<?php

/**
 * Hand-calculated Coupling / Instability test fixture
 *
 * Instability formula (Robert C. Martin, "Agile Software Development"):
 *   I = Ce / (Ca + Ce)
 *
 *   Where:
 *     Ce = Efferent Coupling = number of classes this class DEPENDS ON
 *          (uses, "fan-out") — tracked as usesForInstabilityCount in CouplingCalculator
 *     Ca = Afferent Coupling = number of classes that DEPEND ON this class
 *          (used-by, "fan-in") — tracked as usedByCount in CouplingCalculator
 *
 *   Note: Traits are excluded from the instability calculation (they don't count as Ce).
 *
 * Scenario: ServiceA → ServiceB
 *
 *   ServiceA ──depends on──▶ ServiceB
 *
 *   ServiceA: Ce=1 (depends on ServiceB), Ca=0 (nobody depends on A)
 *     I(ServiceA) = Ce / (Ca + Ce) = 1 / (0 + 1) = 1   (maximally unstable)
 *
 *   ServiceB: Ce=0 (no dependencies), Ca=1 (ServiceA depends on B)
 *     I(ServiceB) = Ce / (Ca + Ce) = 0 / (1 + 0) = 0   (maximally stable)
 *
 * PHP integer division note: when Ce+Ca divides evenly, the result is int, not float.
 *   1/1 = int(1), 0/1 = int(0), 1/2 = float(0.5)
 *
 * This is the canonical example from Martin's book:
 *   - Stable classes (I≈0) should be abstract (many dependents, hard to change)
 *   - Unstable classes (I≈1) should be concrete (few dependents, easy to change)
 *   - Distance from main sequence: D = |A + I - 1|
 */

declare(strict_types=1);

/**
 * ServiceA — depends on ServiceB
 *
 * Ce = 1 (usesForInstabilityCount: ServiceB)
 * Ca = 0 (usedByCount: none)
 * I  = 1 / (0 + 1) = 1  (int)
 */
class HandCalcServiceA
{
    private HandCalcServiceB $service;

    public function __construct(HandCalcServiceB $service)
    {
        $this->service = $service;
    }

    public function run(): string
    {
        return $this->service->doWork();
    }
}

/**
 * ServiceB — no dependencies, used by ServiceA
 *
 * Ce = 0 (usesForInstabilityCount: none)
 * Ca = 1 (usedByCount: HandCalcServiceA)
 * I  = 0 / (1 + 0) = 0  (int)
 */
class HandCalcServiceB
{
    public function doWork(): string
    {
        return 'work done';
    }
}
