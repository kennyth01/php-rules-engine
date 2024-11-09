<?php

namespace Kennyth01\PhpRulesEngine;

use Exception;

class Engine
{
    private array $rules = [];
    private array $allRules = [];
    private ?Rule $targetRule = null;
    private Facts $facts;

    public function __construct()
    {
        $this->facts = new Facts();
    }

    // Add a new rule to the engine
    public function addRule(Rule $rule): void
    {
        $this->rules[] = $rule;
        $this->allRules[$rule->getName()] = $rule; // Store rule by name
    }

    // Add a fact to the engine
    public function addFact(string $factName, $value): void
    {
        $this->facts->add($factName, $value);
    }

    // Set target rule
    public function setTargetRule(string $ruleName): void
    {
        if (isset($this->allRules[$ruleName])) {
            $this->targetRule = $this->allRules[$ruleName];
        } else {
            throw new Exception("Rule '$ruleName' not found");
        }
    }

    // Run the engine: evaluate all rules against the current facts
    public function run(): array
    {
        $results = [];
        foreach ($this->rules as $rule) {
            if ($rule->evaluate($this->facts, $this->allRules)) {
                $results[] = $rule->triggerEvent($this->facts);
            } else {
                $results[] = $rule->triggerFailureEvent($this->facts);
            }
        }
        return $results;
    }

    // Evaluate a specific target rule against the facts
    public function evaluate(): array
    {
        if (!$this->targetRule) {
            throw new Exception('No target rule set for evaluation.');
        }

        $result = [];
        $failedConditions = [];

        // Evaluate the target rule
        if ($this->targetRule->evaluate($this->facts, $this->allRules)) {
            $result[] = array_merge(
                $this->targetRule->triggerEvent($this->facts),
                ['interpretation' => $this->targetRule->interpretRules()]
            );
        } else {
            $failedConditions = $this->targetRule->getFailedConditions();

            $result[] = array_merge(
                $this->targetRule->triggerFailureEvent($this->facts),
                [
                    'interpretation' => $this->targetRule->interpretRules(),
                    'failedConditions' => $failedConditions
                ]
            );
        }

        return $result;
    }
}
