<?php

namespace Kennyth01\PhpRulesEngine;

use Exception;

class Engine
{
    private array $rules = [];
    private array $allRules = [];
    private ?Rule $targetRule = null;
    private Facts $facts;
    private bool $showInterpretation = false;
    private bool $showFailedConditions = false;

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
        $evaluationResult = $this->targetRule->evaluate($this->facts, $this->allRules);

        $extra = $this->getExtraData($evaluationResult);

        $triggerMethod = $evaluationResult ? 'triggerEvent' : 'triggerFailureEvent';
        $result[] = array_merge($this->targetRule->$triggerMethod($this->facts), $extra);

        return $result;
    }

    private function getExtraData(bool $evaluationResult): array
    {
        $extra = [];

        if ($this->showInterpretation) {
            $extra['interpretation'] = $this->targetRule->interpretRules();
        }

        if (!$evaluationResult && $this->showFailedConditions) {
            $extra['failedConditions'] = $this->targetRule->getFailedConditions();
        }

        return $extra;
    }

    public function showInterpretation(bool $showInterpretation): void
    {
        $this->showInterpretation = $showInterpretation;
    }
    public function showFailedConditions(bool $showFailedConditions): void
    {
        $this->showFailedConditions = $showFailedConditions;
    }
}
