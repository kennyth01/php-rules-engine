<?php

namespace Kennyth01\PhpRulesEngine;

use Exception;

class Rule
{
    private array $conditions;
    private array $event;
    private array $failureEvent;
    private ?string $name;

    public function __construct(array $options)
    {
        $this->conditions = $options['conditions'] ?? [];
        $this->event = $options['event'] ?? [];
        $this->failureEvent = $options['failureEvent'] ?? [];
        $this->name = $options['name'] ?? null;

        if (empty($this->conditions)) {
            throw new Exception('Invalid rule: conditions are required');
        }
        if (empty($this->event)) {
            throw new Exception('Invalid rule: event is required');
        }
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function evaluate(Facts $facts, array $allRules): bool
    {
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
                    if (!$dependencyRule->evaluate($facts, $allRules)) {
                        return false;
                    }
                } else {
                    throw new Exception("Dependent rule '$dependencyRuleName' not found");
                }
            } elseif (isset($condition['all'])) {
                if (!$this->evaluateAll($condition['all'], $facts, $allRules)) {
                    return false;
                }
            } elseif (isset($condition['any'])) {
                if (!$this->evaluateAny($condition['any'], $facts, $allRules)) {
                    return false;
                }
            } elseif (isset($condition['not'])) {
                if ($this->evaluateCondition($condition['not'], $facts, $allRules)) {
                    return false; // Negate the condition
                }
            } else {
                if (!$this->evaluateCondition($condition, $facts, $allRules)) {
                    return false;
                }
            }
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
                    if ($dependencyRule->evaluate($facts, $allRules)) {
                        return true;
                    }
                } else {
                    throw new Exception("Dependent rule '$dependencyRuleName' not found");
                }
            } elseif (isset($condition['all'])) {
                if ($this->evaluateAll($condition['all'], $facts, $allRules)) {
                    return true;
                }
            } elseif (isset($condition['any'])) {
                if ($this->evaluateAny($condition['any'], $facts, $allRules)) {
                    return true;
                }
            } elseif (isset($condition['not'])) {
                if (!$this->evaluateCondition($condition['not'], $facts, $allRules)) {
                    return true; // Negate the condition
                }
            } else {
                if ($this->evaluateCondition($condition, $facts, $allRules)) {
                    return true;
                }
            }
        }
        return false;
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

        switch ($operator) {
            case 'equal':
                return $factData === $value;
            case 'lessThanInclusive':
                return $factData <= $value;
            case 'greaterThanInclusive':
                return $factData >= $value;
            case 'lessThan':
                return $factData < $value;
            case 'greaterThan':
                return $factData > $value;
            case 'in':
                return in_array($factData, $value, true);
            case 'notIn':
                return !in_array($factData, $value, true);
            case 'contains':
                return is_array($factData) && in_array($value, $factData);
            default:
                throw new Exception("Unknown operator: $operator");
        }
    }

    public function triggerEvent(Facts $facts): array
    {
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
        switch ($operator) {
            case 'equal':
                $operatorText = 'is equal to';
                break;
            case 'greaterThanInclusive':
                $operatorText = 'is >=';
                break;
            case 'lessThanInclusive':
                $operatorText = 'is <=';
                break;
            case 'lessThan':
                $operatorText = 'is less than';
                break;
            case 'greaterThan':
                $operatorText = 'is greater than';
                break;
            case 'in':
                $operatorText = 'is in';
                break;
            case 'notIn':
                $operatorText = 'is not in';
                break;
            case 'contains':
                $operatorText = 'contains';
                break;
            default:
                throw new Exception("Unknown operator: $operator");
        }
        return "$factDisplay $operatorText $value";
    }
}
