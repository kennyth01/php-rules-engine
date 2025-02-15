<?php

namespace Kennyth01\PhpRulesEngine;

use Exception;

class Rule
{
    private array $conditions;
    private array $decisions;
    private array $event;
    private array $failureEvent;
    private ?string $name;
    private array $failedConditions = [];
    private array $dependencyRules = [];
    private array $conditionSuccessEvents = [];

    public function __construct(array $options)
    {
        $this->conditions = $options['conditions'] ?? [];
        $this->decisions = $options['decisions'] ?? [];
        $this->event = $options['event'] ?? [];
        $this->failureEvent = $options['failureEvent'] ?? [];
        $this->name = $options['name'] ?? null;

        if (empty($this->conditions) && empty($this->decisions)) {
            throw new Exception('Invalid rule: conditions are required');
        }
        if (empty($this->event)) {
            throw new Exception('Invalid rule: event is required');
        }
    }

    public function getConditionSuccessEvents(): array
    {
        return $this->conditionSuccessEvents;
    }

    public function getDependencyRules(): array
    {
        return $this->dependencyRules;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    private function transformDecisionsToConditionWithAny(array $decisions): array
    {
        $newDecisions = array_map(function ($decision) {
            $newDecision = [
                'name' => $decision['name'],
                'event' => $decision['event'],
            ];

            foreach (['all', 'any', 'not'] as $conditionType) {
                if (isset($decision['conditions'][$conditionType])) {
                    $newDecision[$conditionType] = $decision['conditions'][$conditionType];
                    break;
                }
            }

            return $newDecision;
        }, $decisions);

        return ['any' => $newDecisions];
    }

    public function evaluate(Facts $facts, array $allRules): bool
    {
        $this->failedConditions = []; // Reset failed conditions before evaluation

        // if there's a 'decision' key, convert it to conditions with 'any' key
        if (!empty($this->decisions)) {
            $this->conditions = $this->transformDecisionsToConditionWithAny($this->decisions);
        }

        if (isset($this->conditions['all'])) {
            return $this->evaluateAll($this->conditions['all'], $facts, $allRules);
        } elseif (isset($this->conditions['any'])) {
            return $this->evaluateAny($this->conditions['any'], $facts, $allRules);
        } elseif (isset($this->conditions['not'])) {
            return !$this->evaluateCondition($this->conditions['not'], $facts, $allRules);
        }

        return false;
    }

    private function evaluateAll(array $conditions, Facts $facts, array $allRules): bool
    {
        foreach ($conditions as $condition) {
            if (isset($condition['condition'])) {
                $dependencyRuleName = $condition['condition'];
                if (isset($allRules[$dependencyRuleName])) {
                    $dependencyRule = $allRules[$dependencyRuleName];
                    $this->dependencyRules[] = $dependencyRule;
                    if (!$dependencyRule->evaluate($facts, $allRules)) {
                        $this->failedConditions[] = $condition;
                        return false;
                    }
                } else {
                    throw new Exception("Dependent rule '$dependencyRuleName' not found");
                }
            } elseif (isset($condition['all'])) {
                if (!$this->evaluateAll($condition['all'], $facts, $allRules)) {
                    $this->failedConditions[] = $condition;
                    return false;
                }
            } elseif (isset($condition['any'])) {
                if (!$this->evaluateAny($condition['any'], $facts, $allRules)) {
                    $this->failedConditions[] = $condition;
                    return false;
                }
            } elseif (isset($condition['not'])) {
                if ($this->evaluateCondition($condition['not'], $facts, $allRules)) {
                    $this->failedConditions[] = $condition;
                    return false; // Negate the condition
                }
            } else {
                if (!$this->evaluateCondition($condition, $facts, $allRules)) {
                    $this->failedConditions[] = $condition;
                    return false;
                }
            }
        }
        if (isset($condition['event'])) {
            $this->conditionSuccessEvents = $condition['event'];
        }
        return true;
    }

    private function evaluateAny(array $conditions, Facts $facts, array $allRules): bool
    {
        foreach ($conditions as $condition) {
            if (isset($condition['condition'])) {
                $dependencyRuleName = $condition['condition'];
                if (isset($allRules[$dependencyRuleName])) {
                    $dependencyRule = $allRules[$dependencyRuleName];
                    $this->dependencyRules[] = $dependencyRule;
                    if ($dependencyRule->evaluate($facts, $allRules)) {
                        if (isset($condition['event'])) {
                            $this->conditionSuccessEvents = $condition['event'];
                        }
                        return true;
                    } else {
                        $this->failedConditions[] = $condition;
                    }
                } else {
                    throw new Exception("Dependent rule '$dependencyRuleName' not found");
                }
            } elseif (isset($condition['all'])) {
                if ($this->evaluateAll($condition['all'], $facts, $allRules)) {
                    if (isset($condition['event'])) {
                        $this->conditionSuccessEvents = $condition['event'];
                    }
                    return true;
                } else {
                    $this->failedConditions[] = $condition;
                }
            } elseif (isset($condition['any'])) {
                if ($this->evaluateAny($condition['any'], $facts, $allRules)) {
                    if (isset($condition['event'])) {
                        $this->conditionSuccessEvents = $condition['event'];
                    }
                    return true;
                } else {
                    $this->failedConditions[] = $condition;
                }
            } elseif (isset($condition['not'])) {
                if (!$this->evaluateCondition($condition['not'], $facts, $allRules)) {
                    if (isset($condition['event'])) {
                        $this->conditionSuccessEvents = $condition['event'];
                    }
                    return true; // Negate the condition
                } else {
                    $this->failedConditions[] = $condition;
                }
            } else {
                if ($this->evaluateCondition($condition, $facts, $allRules)) {
                    if (isset($condition['event'])) {
                        $this->conditionSuccessEvents = $condition['event'];
                    }
                    return true;
                } else {
                    $this->failedConditions[] = $condition;
                }
            }
        }
        return false;
    }

    public function getFailedConditions(): array
    {
        return $this->failedConditions;
    }

    private function evaluateCondition(array $condition, Facts $facts, array $allRules): bool
    {
        $factName = $condition['fact'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;
        $path = $condition['path'] ?? null; // Optional path to drill down into fact data

        if (!$factName || !$operator) {
            throw new Exception('Invalid condition: fact and operator are required');
        }

        $factData = $facts->get($factName, $path);

        return match ($operator) {
            'equal'                => $factData === $value,
            'lessThanInclusive'    => $factData <= $value,
            'greaterThanInclusive' => $factData >= $value,
            'lessThan'             => $factData < $value,
            'greaterThan'          => $factData > $value,
            'in'                   => in_array($factData, $value, true),
            'notIn'                => !in_array($factData, $value, true),
            'contains'             => is_array($factData) && in_array($value, $factData),
            default                => throw new Exception("Unknown operator: $operator"),
        };
    }

    public function triggerEvent(Facts $facts): array
    {
        if (!empty($this->conditionSuccessEvents)) {
            return $this->conditionSuccessEvents;
        }

        return [
            'type' => $this->event['type'],
            'params' => $this->event['params'],
            'facts' => $facts->getAll()
        ];
    }

    public function triggerFailureEvent(Facts $facts): array
    {
        return [
            'type' => $this->failureEvent['type'],
            'params' => $this->failureEvent['params'],
            'facts' => $facts->getAll()
        ];
    }

    public function interpretRules(array $conditions = null): string
    {
        if ($conditions === null) {
            $conditions = $this->conditions;
        }

        if (isset($conditions['all'])) {
            $clauses = array_map([$this, 'interpretRules'], $conditions['all']);
            return '(' . implode(' AND ', $clauses) . ')';
        } elseif (isset($conditions['any'])) {
            $clauses = array_map([$this, 'interpretRules'], $conditions['any']);
            return '(' . implode(' OR ', $clauses) . ')';
        } elseif (isset($conditions['not'])) {
            return 'NOT (' . $this->interpretRules($conditions['not']) . ')';
        } else {
            // It's a leaf condition
            return $this->interpretCondition($conditions);
        }
    }
    private function interpretCondition(array $condition): string
    {
        $fact = $condition['fact'] ?? '';
        $operator = $condition['operator'] ?? '';
        $value = $condition['value'] ?? 'NULL';
        $path = $condition['path'] ?? null; // Optional path to drill down into fact data
        $dependencyRule = $condition['condition'] ?? null;

        if ($dependencyRule) {
            return $dependencyRule;
        }

        // Handle path if present
        //$factDisplay = $path ? end(explode('.', $path)) : $fact;
        $factDisplay = $fact;
        if ($path) {
            $pathParts = explode('.', $path); // Assign the result to a variable
            $factDisplay = end($pathParts);   // Pass the variable to `end()`
        }

        // Mapping operator to a readable format
        $operatorText = match ($operator) {
            'equal'                => 'is equal to',
            'greaterThanInclusive' => 'is >=',
            'lessThanInclusive'    => 'is <=',
            'lessThan'             => 'is less than',
            'greaterThan'          => 'is greater than',
            'in'                   => 'is in',
            'notIn'                => 'is not in',
            'contains'             => 'contains',
            default                => throw new Exception("Unknown operator: $operator"),
        };

        if (is_array($value)) {
            $value = json_encode($value);
        }

        return "$factDisplay $operatorText $value";
    }
}
