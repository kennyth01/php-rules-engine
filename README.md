# PHP Rules Engine

[![Run tests](https://github.com/kennyth01/php-rules-engine/actions/workflows/php.yml/badge.svg)](https://github.com/kennyth01/php-rules-engine/actions/workflows/php.yml)

`kennyth01/php-rules-engine` is a lightweight and flexible PHP rules engine that evaluates complex conditional logic using JSON-based rule configurations. It is designed to handle dynamic, reusable, and maintainable rule logic, making it ideal for applications with complex business requirements that must adapt to changing conditions.

This library, inspired by the `json-rules-engine`, ([link](https://github.com/CacheControl/json-rules-engine)) enables developers to define rules with nested conditions, logical operators (`all`, `any`, `not`), and rule dependencies.

## Features
- **JSON-Configurable Rules**: Easily define rules and conditions in JSON format.
- **Fact-to-Fact Comparison**: Compare two dynamic facts at runtime instead of comparing to static values.
- **Rule Dependencies**: Reference other rules as conditions to create complex evaluations.
- **Logical Operators**: Supports `all` (AND), `any` (OR), and `not` operators, allowing for nested conditions.
- **Custom Events and Failure Messages**: Attach custom messages for success or failure, making evaluations easy to interpret.
- **Interpret Rules**: Outputs a human readable English interpretation of the condition using logical operators.


## Installation
Install via Composer:
```bash
composer require kennyth01/php-rules-engine

```

## Basic Example
This example demonstrates an engine for detecting whether a basketball player has fouled out (a player who commits five personal fouls over the course of a 40-minute game, or six in a 48-minute game, fouls out).

###
1. Define the rule (lets assume you store this in `rule.player.isFouledOut.json`)
```json
{
   "name":"rule.player.isFouledOut",
   "conditions": {
     "any": [
       {
         "all": [
           {
             "fact": "gameDuration",
             "operator": "equal",
             "value": 40
           },
           {
             "fact": "personalFoulCount",
             "operator": "greaterThanInclusive",
             "value": 5
           }
         ],
         "name": "short foul limit"
       },
       {
         "all": [
           {
             "fact": "gameDuration",
             "operator": "equal",
             "value": 48
           },
           {
             "not": {
               "fact": "personalFoulCount",
               "operator": "lessThan",
               "value": 6
             }
           }
         ],
         "name": "long foul limit"
       }
     ]
   },
   "event": {
     "type": "fouledOut",
     "params": {
       "message": "Player has fouled out!"
     }
   },
   "failureEvent": {
      "type": "fouledOut",
      "params": {
         "message": "Player has not fouled out"
      }
    }
 }

```
###
2. Trigger the engine and evaluate
```php
$engine = new Engine();
$rule = json_decode(file_get_contents('rule.player.isFouledOut.json'), true);

$engine->addRule(new Rule($rule));
$engine->addFact('personalFoulCount', 6);
$engine->addFact('gameDuration', 40);

$engine->setTargetRule('rule.player.isFouledOut');

$result = $engine->evaluate();
print_r($result);
```
###
3. Output Example
```php
[
    'type' => 'fouledOut',
    'params' => [
        'message' => 'Player has fouled out!'
    ],
    'facts' => [
        'personalFoulCount' => 6,
        'gameDuration' => 40

    ],
    'interpretation' => '((gameDuration is equal to 40 AND personalFoulCount is >= 5) OR (gameDuration is equal to 48 AND NOT (personalFoulCount is less than 6)))'
]
```
## Advanced Examples
For other examples, refer to the `tests` directory

### Fact-to-Fact Comparison
The engine supports comparing two facts dynamically at runtime. This allows for flexible rule evaluation where comparison values can change based on context.

**Example: Speed Limit Check**
```php
$engine = new Engine();

$ruleConfig = [
    "name" => "speed.check",
    "conditions" => [
        "all" => [
            [
                "fact" => "currentSpeed",
                "operator" => "lessThanInclusive",
                "value" => ["fact" => "speedLimit"]  // Compare to another fact
            ]
        ]
    ],
    "event" => ["type" => "withinLimit", "params" => ["message" => "Speed is within limit"]],
    "failureEvent" => ["type" => "speeding", "params" => ["message" => "Exceeding speed limit"]]
];

$engine->addRule(new Rule($ruleConfig));
$engine->setTargetRule('speed.check');

// Add both facts dynamically
$engine->addFact('currentSpeed', 55);
$engine->addFact('speedLimit', 60);

$result = $engine->evaluate();
// Result: withinLimit - Speed is within limit
```

**Example: Nested Fact Comparison**
```php
$ruleConfig = [
    "name" => "age.verification",
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
    ],
    "event" => ["type" => "ageVerified", "params" => []],
    "failureEvent" => ["type" => "ageFailed", "params" => []]
];

$engine->addFact('user', ['age' => 25]);
$engine->addFact('requirements', ['minimumAge' => 18]);
```

## Run the test
```bash
./vendor/bin/phpunit tests
```

## License
[ISC](./LICENSE)
