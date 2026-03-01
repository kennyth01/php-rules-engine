<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Kennyth01\PhpRulesEngine\Engine;
use Kennyth01\PhpRulesEngine\Rule;

echo "=== Fact-to-Fact Comparison Examples ===\n\n";

// Example 1: Speed Limit Check
echo "Example 1: Speed Limit Check\n";
echo "-----------------------------\n";

$engine1 = new Engine();

$speedRule = [
    "name" => "speed.check",
    "conditions" => [
        "all" => [
            [
                "fact" => "currentSpeed",
                "operator" => "lessThanInclusive",
                "value" => ["fact" => "speedLimit"]
            ]
        ]
    ],
    "event" => [
        "type" => "withinLimit",
        "params" => ["message" => "Speed is within limit"]
    ],
    "failureEvent" => [
        "type" => "speeding",
        "params" => ["message" => "Exceeding speed limit"]
    ]
];

$engine1->addRule(new Rule($speedRule));
$engine1->setTargetRule('speed.check');
$engine1->showInterpretation(true);

// Scenario A: Within speed limit
$engine1->addFact('currentSpeed', 55);
$engine1->addFact('speedLimit', 60);
$result1 = $engine1->evaluate();

echo "Scenario A: currentSpeed = 55, speedLimit = 60\n";
echo "Result: " . $result1[0]['type'] . "\n";
echo "Message: " . $result1[0]['params']['message'] . "\n";
echo "Interpretation: " . $result1[0]['interpretation'] . "\n\n";

// Scenario B: Exceeding speed limit
$engine1b = new Engine();
$engine1b->addRule(new Rule($speedRule));
$engine1b->setTargetRule('speed.check');
$engine1b->showInterpretation(true);
$engine1b->addFact('currentSpeed', 75);
$engine1b->addFact('speedLimit', 60);
$result1b = $engine1b->evaluate();

echo "Scenario B: currentSpeed = 75, speedLimit = 60\n";
echo "Result: " . $result1b[0]['type'] . "\n";
echo "Message: " . $result1b[0]['params']['message'] . "\n\n";

// Example 2: Age Verification with Nested Paths
echo "\nExample 2: Age Verification (Nested Paths)\n";
echo "-------------------------------------------\n";

$engine2 = new Engine();

$ageRule = [
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
    "event" => [
        "type" => "ageVerified",
        "params" => ["message" => "User meets age requirement"]
    ],
    "failureEvent" => [
        "type" => "ageFailed",
        "params" => ["message" => "User does not meet age requirement"]
    ]
];

$engine2->addRule(new Rule($ageRule));
$engine2->setTargetRule('age.verification');
$engine2->showInterpretation(true);

// Scenario A: User meets age requirement
$engine2->addFact('user', ['age' => 25, 'name' => 'John']);
$engine2->addFact('requirements', ['minimumAge' => 18, 'country' => 'US']);
$result2 = $engine2->evaluate();

echo "Scenario A: user.age = 25, requirements.minimumAge = 18\n";
echo "Result: " . $result2[0]['type'] . "\n";
echo "Message: " . $result2[0]['params']['message'] . "\n";
echo "Interpretation: " . $result2[0]['interpretation'] . "\n\n";

// Scenario B: User does not meet age requirement
$engine2b = new Engine();
$engine2b->addRule(new Rule($ageRule));
$engine2b->setTargetRule('age.verification');
$engine2b->showInterpretation(true);
$engine2b->addFact('user', ['age' => 16, 'name' => 'Jane']);
$engine2b->addFact('requirements', ['minimumAge' => 18, 'country' => 'US']);
$result2b = $engine2b->evaluate();

echo "Scenario B: user.age = 16, requirements.minimumAge = 18\n";
echo "Result: " . $result2b[0]['type'] . "\n";
echo "Message: " . $result2b[0]['params']['message'] . "\n\n";

// Example 3: Credit Limit Check
echo "\nExample 3: Credit Limit Check\n";
echo "------------------------------\n";

$engine3 = new Engine();

$creditRule = [
    "name" => "credit.limit.check",
    "conditions" => [
        "all" => [
            [
                "fact" => "requestedAmount",
                "operator" => "lessThanInclusive",
                "value" => ["fact" => "creditLimit"]
            ],
            [
                "fact" => "currentBalance",
                "operator" => "lessThan",
                "value" => ["fact" => "creditLimit"]
            ]
        ]
    ],
    "event" => [
        "type" => "approved",
        "params" => ["message" => "Transaction approved"]
    ],
    "failureEvent" => [
        "type" => "declined",
        "params" => ["message" => "Transaction declined"]
    ]
];

$engine3->addRule(new Rule($creditRule));
$engine3->setTargetRule('credit.limit.check');
$engine3->showInterpretation(true);

// Scenario: Transaction within limits
$engine3->addFact('requestedAmount', 500);
$engine3->addFact('currentBalance', 3000);
$engine3->addFact('creditLimit', 5000);
$result3 = $engine3->evaluate();

echo "Scenario: requestedAmount = 500, currentBalance = 3000, creditLimit = 5000\n";
echo "Result: " . $result3[0]['type'] . "\n";
echo "Message: " . $result3[0]['params']['message'] . "\n";
echo "Interpretation: " . $result3[0]['interpretation'] . "\n\n";

echo "=== All Examples Completed ===\n";
