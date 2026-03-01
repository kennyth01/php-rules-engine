# Fact-to-Fact Comparison Feature

## Overview
Added support for fact-to-fact comparison in the PHP Rules Engine, allowing rules to compare two dynamic facts at runtime instead of comparing a fact to a static value.

## Changes Made

### 1. Updated `src/Rule.php`

#### Modified `evaluateCondition()` method (lines 198-222)
Added logic to resolve fact references in the `value` field:

```php
// Resolve value if it's another fact (fact-to-fact comparison)
if (is_array($value) && isset($value['fact'])) {
    $value = $facts->get($value['fact'], $value['path'] ?? null);
}
```

This checks if the value is structured as a fact reference (an array with a `fact` key), and if so, retrieves that fact's value dynamically before performing the comparison.

#### Modified `interpretCondition()` method (lines 265-303)
Added logic to display fact-to-fact comparisons in human-readable format:

```php
// Handle fact-to-fact comparison display
if (is_array($value) && isset($value['fact'])) {
    $valueFact = $value['fact'];
    $valuePath = $value['path'] ?? null;
    $valueDisplay = $valueFact;
    if ($valuePath) {
        $valuePathParts = explode('.', ltrim($valuePath, '$.'));
        $valueDisplay = end($valuePathParts);
    }
    return "$factDisplay $operatorText $valueDisplay";
}
```

### 2. Updated `README.md`
- Added "Fact-to-Fact Comparison" to the Features list
- Added comprehensive documentation with examples:
  - Speed Limit Check example
  - Age Verification with nested paths example

### 3. Added `tests/EngineTest.php::testFactToFactComparison()`
Created comprehensive test coverage with three test cases:
- Basic fact-to-fact comparison (distance vs limit)
- Fact-to-fact comparison with failure scenario
- Nested fact comparison with paths (user.age vs requirements.minimumAge)

### 4. Created `examples/fact-to-fact-comparison.php`
Added a complete working example file demonstrating:
- Speed limit checking
- Age verification with nested paths
- Credit limit checking with multiple fact comparisons

## Usage Examples

### Basic Example
```php
$ruleConfig = [
    "name" => "test.factToFact",
    "conditions" => [
        "all" => [
            [
                "fact" => "distance",
                "operator" => "lessThanInclusive",
                "value" => ["fact" => "limit"]  // Reference another fact
            ]
        ]
    ],
    "event" => ["type" => "passed", "params" => []],
    "failureEvent" => ["type" => "failed", "params" => []]
];

$engine->addRule(new Rule($ruleConfig));
$engine->setTargetRule('test.factToFact');

$engine->addFact('distance', 40);
$engine->addFact('limit', 50);

$result = $engine->evaluate();
// Result: passed (40 <= 50)
```

### Nested Facts Example
```php
$ruleConfig = [
    "conditions" => [
        "all" => [
            [
                "fact" => "user",
                "path" => "$.age",
                "operator" => "greaterThanInclusive",
                "value" => [
                    "fact" => "requirements",
                    "path" => "$.minimumAge"
                ]
            ]
        ]
    ]
];

$engine->addFact('user', ['age' => 25]);
$engine->addFact('requirements', ['minimumAge' => 18]);
// Compares: user.age (25) >= requirements.minimumAge (18)
```

## Benefits
1. **Dynamic Thresholds**: Comparison values can change based on context without modifying rule definitions
2. **Runtime Flexibility**: Different scenarios can use different limits/thresholds
3. **Real-world Use Cases**: 
   - Compare `currentSpeed` to `speedLimit` (where limit changes by zone)
   - Compare `userAge` to `minimumAge` (where minimum might vary by country)
   - Compare `accountBalance` to `creditLimit` (personalized per user)

## Testing
All tests pass successfully:
```bash
./vendor/bin/phpunit tests/EngineTest.php

OK (8 tests, 32 assertions)
```

## Backward Compatibility
This feature is fully backward compatible. Existing rules that use static values continue to work as before. The fact-to-fact comparison is only activated when the `value` is an array with a `fact` key.
